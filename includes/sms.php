<?php
/**
 * includes/sms.php — AfroMessage SMS (send + status + delivery log)
 * Used by send_sms() in notifications.php and by sms_callback.php
 */

/**
 * Normalize Ethiopian phone to digits starting with 251...
 */
function sms_normalize_phone(string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if ($phone === '') {
        return '';
    }
    if (strpos($phone, '0') === 0) {
        $phone = '251' . substr($phone, 1);
    }
    if (strpos($phone, '251') !== 0 && strlen($phone) === 9) {
        $phone = '251' . $phone;
    }
    return $phone;
}

/**
 * Load SMS-related settings as key => value
 */
function sms_settings(PDO $pdo): array
{
    $keys = [
        'sms_enabled',
        'sms_gateway_url',
        'sms_gateway_method',
        'sms_api_key',
        'sms_sender_id',
        'sms_identifier',
        'sms_callback_url',
        'sms_provider',
    ];
    $in = "'" . implode("','", $keys) . "'";
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($in)")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * True when AfroMessage path should be used
 */
function sms_use_afromessage(array $s): bool
{
    $provider = strtolower(trim($s['sms_provider'] ?? ''));
    if ($provider === 'afromessage' || $provider === 'afro') {
        return true;
    }
    $url = strtolower($s['sms_gateway_url'] ?? '');
    if (strpos($url, 'afromessage.com') !== false) {
        return true;
    }
    // Token + identifier set → prefer Afro
    if (trim($s['sms_api_key'] ?? '') !== '' && trim($s['sms_identifier'] ?? '') !== '') {
        return true;
    }
    return false;
}

/**
 * Ensure sms_logs table exists (safe if already there)
 */
function sms_ensure_logs_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `sms_logs` (
          `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `event_id`    INT DEFAULT NULL,
          `user_id`     INT DEFAULT NULL,
          `phone`       VARCHAR(32)  NOT NULL,
          `message_id`  VARCHAR(64)  DEFAULT NULL,
          `message`     TEXT         NOT NULL,
          `status`      VARCHAR(32)  NOT NULL DEFAULT 'pending',
          `provider`    VARCHAR(32)  NOT NULL DEFAULT 'afromessage',
          `raw_response` TEXT,
          `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at`  TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_sms_message_id` (`message_id`),
          KEY `idx_sms_phone` (`phone`),
          KEY `idx_sms_event` (`event_id`),
          KEY `idx_sms_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Send via AfroMessage API (GET /api/send)
 *
 * @return array{ok:bool, message_id?:string, status?:string, error?:string, raw?:mixed}
 */
function sms_send_afromessage(PDO $pdo, string $phone, string $message, ?int $eventId = null): array
{
    $s = sms_settings($pdo);
    if (($s['sms_enabled'] ?? '0') !== '1') {
        return ['ok' => false, 'error' => 'SMS disabled'];
    }

    $token      = trim($s['sms_api_key'] ?? '');
    $from       = trim($s['sms_identifier'] ?? '');
    $sender     = trim($s['sms_sender_id'] ?? '9141');
    $callback   = trim($s['sms_callback_url'] ?? '');
    // Ignore placeholder / invalid callback URLs
    if ($callback !== '' && (
        strpos($callback, 'yourdomain.com') !== false
        || strpos($callback, 'example.com') !== false
        || !preg_match('#^https?://#i', $callback)
    )) {
        $callback = '';
    }

    if ($token === '') {
        return ['ok' => false, 'error' => 'sms_api_key (token) missing'];
    }
    // Token may have accidental whitespace/newlines from paste
    $token = preg_replace('/\s+/', '', $token);

    if ($from === '') {
        return ['ok' => false, 'error' => 'sms_identifier (from) missing — set in Settings'];
    }

    $to = sms_normalize_phone($phone);
    if ($to === '') {
        return ['ok' => false, 'error' => 'Invalid phone'];
    }

    $ch = curl_init();
    $msg = curl_escape($ch, $message);

    $url = 'https://api.afromessage.com/api/send'
         . '?from='   . urlencode($from)
         . '&sender=' . urlencode($sender)
         . '&to='     . urlencode($to)
         . '&message=' . $msg;

    if ($callback !== '') {
        $url .= '&callback=' . urlencode($callback);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'GET',
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    $errstr = curl_error($ch);
    $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'error' => 'cURL: ' . $errstr];
    }

    $data = json_decode((string) $result, true);
    if ($code !== 200 || !is_array($data)) {
        sms_log_row($pdo, $eventId, $to, $message, null, 'error', $result);
        $hint = is_string($result) ? substr($result, 0, 200) : '';
        return ['ok' => false, 'error' => 'HTTP ' . $code . ($hint ? ': ' . $hint : ''), 'raw' => $result];
    }

    if (($data['acknowledge'] ?? '') !== 'success') {
        sms_log_row($pdo, $eventId, $to, $message, null, 'error', $result);
        // Extract AfroMessage error text
        $errMsg = 'API failure';
        if (!empty($data['response']['errors']) && is_array($data['response']['errors'])) {
            $errMsg = implode('; ', array_map('strval', $data['response']['errors']));
        } elseif (!empty($data['response']['error'])) {
            $errMsg = (string) $data['response']['error'];
        } elseif (!empty($data['response']['message'])) {
            $errMsg = (string) $data['response']['message'];
        } elseif (!empty($data['errors']) && is_array($data['errors'])) {
            $errMsg = implode('; ', array_map('strval', $data['errors']));
        }
        return ['ok' => false, 'error' => $errMsg, 'raw' => $data];
    }

    $messageId = $data['response']['message_id'] ?? null;
    $status    = 'pending';

    sms_log_row($pdo, $eventId, $to, $message, $messageId, $status, $result);

    return [
        'ok'         => true,
        'message_id' => $messageId,
        'status'     => $status,
        'raw'        => $data,
    ];
}

/**
 * Insert / update sms_logs
 */
function sms_log_row(
    PDO $pdo,
    ?int $eventId,
    string $phone,
    string $message,
    ?string $messageId,
    string $status,
    $raw = null
): void {
    sms_ensure_logs_table($pdo);
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sms_logs (event_id, phone, message_id, message, status, provider, raw_response)
             VALUES (:event_id, :phone, :message_id, :message, :status, :provider, :raw)'
        );
        $stmt->execute([
            ':event_id'   => $eventId,
            ':phone'      => $phone,
            ':message_id' => $messageId,
            ':message'    => $message,
            ':status'     => $status,
            ':provider'   => 'afromessage',
            ':raw'        => is_string($raw) ? $raw : json_encode($raw),
        ]);
    } catch (Throwable $e) {
        // silent
    }
}

/**
 * Update status by message_id (callback or poll)
 */
function sms_update_status(PDO $pdo, string $messageId, string $status): bool
{
    sms_ensure_logs_table($pdo);
    $status = strtoupper(trim($status));
    if ($messageId === '' || $status === '') {
        return false;
    }
    try {
        $stmt = $pdo->prepare(
            'UPDATE sms_logs SET status = :status WHERE message_id = :mid LIMIT 1'
        );
        $stmt->execute([':status' => $status, ':mid' => $messageId]);
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Poll AfroMessage GET /api/status?id=
 * Rate limit: ~1 req / 2s
 *
 * @return array{ok:bool, status?:string, description?:string, error?:string, raw?:mixed}
 */
function sms_check_status(PDO $pdo, string $messageId, bool $updateDb = true): array
{
    $s = sms_settings($pdo);
    $token = trim($s['sms_api_key'] ?? '');
    if ($token === '') {
        return ['ok' => false, 'error' => 'sms_api_key missing'];
    }
    if ($messageId === '') {
        return ['ok' => false, 'error' => 'message_id required'];
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://api.afromessage.com/api/status?id=' . urlencode($messageId),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $token],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result = curl_exec($ch);
    $errno  = curl_errno($ch);
    $errstr = curl_error($ch);
    $code   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'error' => 'cURL: ' . $errstr];
    }

    $data = json_decode((string) $result, true);
    if ($code !== 200 || !is_array($data) || ($data['acknowledge'] ?? '') !== 'success') {
        return ['ok' => false, 'error' => 'HTTP ' . $code, 'raw' => $result];
    }

    $status = strtoupper((string) ($data['response']['status'] ?? 'UNKNOWN'));
    $desc   = (string) ($data['response']['description'] ?? '');

    if ($updateDb) {
        sms_update_status($pdo, $messageId, $status);
    }

    return [
        'ok'          => true,
        'status'      => $status,
        'description' => $desc,
        'raw'         => $data,
    ];
}

/**
 * High-level: send SMS (Afro if configured, else false for caller to use generic gateway)
 *
 * @return array|false  array result from Afro, or false if not using Afro
 */
function sms_send(PDO $pdo, string $phone, string $message, ?int $eventId = null)
{
    $s = sms_settings($pdo);
    if (($s['sms_enabled'] ?? '0') !== '1') {
        return ['ok' => false, 'error' => 'SMS disabled'];
    }
    if (!sms_use_afromessage($s)) {
        return false; // let notifications.php generic gateway handle
    }
    return sms_send_afromessage($pdo, $phone, $message, $eventId);
}
