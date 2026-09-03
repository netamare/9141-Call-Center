<?php
/**
 * includes/notifications.php
 * In-app notification center + the data behind the operator alarm sound.
 * Notifications are fanned out to individual users at creation time
 * (one row per recipient) so "read" state is simply per-row.
 */

function create_notification($pdo, $user_id, $event_id, $type, $title, $message, $is_urgent = false) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, event_id, type, title, message, is_urgent) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $event_id, $type, $title, $message, $is_urgent ? 1 : 0]);
}

/** Notify every active user in the given roles (optionally scoped to one department). */
function notify_roles($pdo, array $roles, $event_id, $type, $title, $message, $is_urgent = false, $department_id = null) {
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $sql = "SELECT id FROM users WHERE status='active' AND role IN ($placeholders)";
    $params = $roles;
    if ($department_id !== null) {
        $sql .= " AND (role != 'department_officer' OR department_id = ?)";
        $params[] = $department_id;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        create_notification($pdo, $uid, $event_id, $type, $title, $message, $is_urgent);
    }
}

/** Fired when a new event comes in — alerts operators/admins.
 *  No immediate sound (is_urgent=false). Sound only after the 5-minute
 *  stale escalation (check_stale_new_events) if still unhandled. */
function notify_new_event($pdo, $event_id, $priority, $category_name, $tracking_code) {
    $urgent = false; // never immediate alarm; wait for 5 min escalation
    $title = "New $priority priority event";
    notify_roles($pdo, ['administrator', 'operator'], $event_id, 'new_event', $title, "$category_name — $tracking_code", $urgent);
}

/** Fired when an event is escalated to a department. */
function notify_escalation($pdo, $event_id, $department_id, $tracking_code, $priority) {
    $urgent = in_array($priority, ['high', 'critical'], true);
    notify_roles($pdo, ['department_officer'], $event_id, 'escalation', 'Event escalated to your department', $tracking_code, $urgent, $department_id);
    notify_roles($pdo, ['administrator', 'supervisor'], $event_id, 'escalation', 'Event escalated', $tracking_code, false);
}

/**
 * Fired right after an operator escalates an event to a department.
 * On top of the existing in-app notify_escalation() above, this texts the
 * department's own contact_phone (from admin/departments.php) so the
 * department is reachable even before anyone opens the dashboard. Quietly
 * does nothing if the department has no contact_phone or SMS isn't
 * configured/enabled in Settings — escalation itself always still happens.
 */
function notify_department_escalation_sms($pdo, $department_id, $event_id, $tracking_code, $category_name, $priority) {
    $stmt = $pdo->prepare("SELECT contact_phone FROM departments WHERE id = ?");
    $stmt->execute([$department_id]);
    $phone = $stmt->fetchColumn();
    if (!$phone) return false;
    $priorityText = t_raw('severity_' . $priority) ?: $priority;
    $message = sprintf(t_raw('sms_department_escalation'), $tracking_code, $category_name, $priorityText);
    return send_sms($pdo, $phone, $message, $event_id);
}

/**
 * Fired whenever an event's status changes (typically the assigned
 * department officer moving it to Ongoing/Solved/Unsolved). Notifies the
 * operator who originally logged the event (in-app + SMS to their phone,
 * if one is on file) plus administrators/supervisors, so the escalation
 * loop is closed: operator -> department -> back to operator.
 */
function notify_status_update_to_operator($pdo, $event_id, $operator_id, $tracking_code, $status) {
    $statusText = t_raw('status_' . $status) ?: $status;
    $title = t_raw('notif_status_update_title');
    $message = "$tracking_code — $statusText";
    $urgent = in_array($status, ['solved', 'unsolved'], true);

    if ($operator_id) {
        create_notification($pdo, $operator_id, $event_id, 'status_update', $title, $message, $urgent);
        $u = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $u->execute([$operator_id]);
        $phone = $u->fetchColumn();
        if ($phone) {
            send_sms($pdo, $phone, sprintf(t_raw('sms_operator_status_update'), $tracking_code, $statusText), $event_id);
        }
    }
    notify_roles($pdo, ['administrator', 'supervisor'], $event_id, 'status_update', $title, $message, false);
}

function get_recent_notifications($pdo, $user_id, $limit = 12) {
    $stmt = $pdo->prepare("SELECT n.*, e.tracking_code FROM notifications n LEFT JOIN events e ON e.id = n.event_id
                            WHERE n.user_id = ? ORDER BY n.created_at DESC LIMIT $limit");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

function get_unread_count($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn();
}

/** Any unread + urgent notification triggers the operator alarm sound. */
function has_unread_urgent($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0 AND is_urgent = 1");
    $stmt->execute([$user_id]);
    return (int) $stmt->fetchColumn() > 0;
}

function mark_all_read($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
}

/**
 * Escalating alert: any event still sitting in "new" (i.e. no operator has
 * picked it up / escalated it yet) for longer than the configured threshold
 * (default 5 minutes, admin/settings.php -> operator_alert_minutes) fires an
 * urgent notification to operators and administrators, once per event
 * (events.stale_alert_sent guards against re-firing on every poll).
 * This is the ONLY time the operator alarm sound plays (for ~30 seconds).
 * New events themselves never set is_urgent, so no immediate sound.
 * Call this cheaply on each notification poll so it's effectively real-time
 * without needing a cron job.
 */
function check_stale_new_events($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'operator_alert_minutes'");
    $stmt->execute();
    $minutes = (int) ($stmt->fetchColumn() ?: 5);

    $stale = $pdo->prepare("SELECT e.id, e.tracking_code, e.priority, c.name AS category_name
                             FROM events e LEFT JOIN categories c ON c.id = e.category_id
                             WHERE e.status = 'new' AND e.stale_alert_sent = 0
                               AND e.created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stale->execute([$minutes]);
    $rows = $stale->fetchAll();
    if (!$rows) return;

    $mark = $pdo->prepare("UPDATE events SET stale_alert_sent = 1 WHERE id = ?");
    foreach ($rows as $r) {
        notify_roles(
            $pdo, ['administrator', 'operator'], $r['id'], 'escalating_alert',
            "⏳ Escalating: unhandled {$minutes}+ min",
            ($r['category_name'] ?: 'Event') . ' — ' . $r['tracking_code'],
            true // always urgent — this is the only sound trigger (30s for operators)
        );
        $mark->execute([$r['id']]);
    }
}

/** Count of currently-unhandled "new" events older than the alert threshold — used for the dashboard banner. */
function count_stale_new_events($pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'operator_alert_minutes'");
    $stmt->execute();
    $minutes = (int) ($stmt->fetchColumn() ?: 5);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM events WHERE status = 'new' AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$minutes]);
    return ['count' => (int) $stmt->fetchColumn(), 'minutes' => $minutes];
}

/**
 * SMS notifications to citizens (tracking code on submit, status update on
 * resolution). No SMS provider is bundled — same reasoning as the README's
 * "Strategic System Expansion" note on IVR/WhatsApp: that needs a paid
 * telecom/gateway account this codebase can't supply on its own. Instead,
 * Admin > Settings lets you paste ANY simple HTTP(S) gateway endpoint as a
 * template, e.g.:
 *   https://api.yourprovider.com/send?to={phone}&msg={message}&key={apikey}&from={sender}
 * {phone}, {message}, {apikey}, {sender} are substituted (urlencoded) from
 * Settings and the caller. Works with most REST-style local/bulk SMS
 * resellers (Africa's Talking, GeezSMS-style, Twilio-compatible, etc.)
 * without touching code. If SMS isn't enabled/configured, this quietly
 * does nothing — every other feature keeps working without an SMS account.
 */
function send_sms($pdo, $phone, $message, $event_id = null) {
    $phone = trim((string) $phone);
    if ($phone === '') return false;

    // AfroMessage path (token + identifier / provider) — logs message_id for delivery tracking
    if (!function_exists('sms_send')) {
        $smsFile = __DIR__ . '/sms.php';
        if (is_file($smsFile)) {
            require_once $smsFile;
        }
    }
    if (function_exists('sms_send')) {
        $afro = sms_send($pdo, $phone, $message, $event_id ? (int)$event_id : null);
        if (is_array($afro)) {
            $ok = !empty($afro['ok']);
            if ($event_id) {
                $note = $ok
                    ? ('SMS sent to ' . $phone . (isset($afro['message_id']) ? ' id=' . $afro['message_id'] : ''))
                    : ('SMS attempt failed for ' . $phone . ': ' . ($afro['error'] ?? ''));
                try {
                    $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note) VALUES (?, 'sms', ?)");
                    $log->execute([$event_id, $note]);
                } catch (Throwable $e) { /* ignore */ }
            }
            return $ok;
        }
        // false = not using Afro → fall through to generic gateway
    }

    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings
                          WHERE setting_key IN ('sms_enabled','sms_gateway_url','sms_gateway_method','sms_api_key','sms_sender_id')")
                ->fetchAll(PDO::FETCH_KEY_PAIR);

    if (($rows['sms_enabled'] ?? '0') !== '1') return false;
    $urlTemplate = trim($rows['sms_gateway_url'] ?? '');
    if ($urlTemplate === '') return false;

    $method = strtoupper($rows['sms_gateway_method'] ?? 'GET') === 'POST' ? 'POST' : 'GET';
    $built = strtr($urlTemplate, [
        '{phone}'   => urlencode($phone),
        '{message}' => urlencode($message),
        '{apikey}'  => urlencode($rows['sms_api_key'] ?? ''),
        '{sender}'  => urlencode($rows['sms_sender_id'] ?? ''),
    ]);

    $ok = false;
    if (function_exists('curl_init')) {
        try {
            $ch = curl_init();
            if ($method === 'POST') {
                $parts = parse_url($built);
                $fields = [];
                if (!empty($parts['query'])) parse_str($parts['query'], $fields);
                $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '')
                      . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '');
                curl_setopt($ch, CURLOPT_URL, $base);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
            } else {
                curl_setopt($ch, CURLOPT_URL, $built);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $ok = ($response !== false) && $httpCode >= 200 && $httpCode < 300;
        } catch (Throwable $e) {
            $ok = false;
        }
    }

    if ($event_id) {
        $log = $pdo->prepare("INSERT INTO event_logs (event_id, action, note) VALUES (?, 'sms', ?)");
        $log->execute([$event_id, ($ok ? 'SMS sent to ' : 'SMS attempt failed for ') . $phone]);
    }
    return $ok;
}
