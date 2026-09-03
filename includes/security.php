<?php
/**
 * includes/security.php — CSRF protection + login brute-force lockout
 */

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(400);
        die('Security check failed. Please go back and try again.');
    }
}

/**
 * Ethiopian phone numbers only, in either local (0912345678) or
 * international (+251912345678) format — 10 digits after the leading 0,
 * or 9 digits after +251. An empty string is considered valid since the
 * phone field itself decides whether it's required.
 */
function is_valid_et_phone($phone) {
    $phone = normalize_et_phone($phone);
    if ($phone === '') return true;
    // After normalize: 09xxxxxxxx or 07xxxxxxxx
    return (bool) preg_match('/^0(9|7)[0-9]{8}$/', $phone);
}

/**
 * Normalize Ethiopian mobile to 0XXXXXXXXX (10 digits).
 * Accepts: 09…, 07…, +2519…, +2517…, 2519…, 9…, 7…
 */
function normalize_et_phone($phone) {
    $phone = trim((string)$phone);
    if ($phone === '') return '';
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    // +2519… / 2519… → 09…
    if (preg_match('/^\+?251([97][0-9]{8})$/', $phone, $m)) {
        return '0' . $m[1];
    }
    // 9xxxxxxxx / 7xxxxxxxx (9 digits) → 09… / 07…
    if (preg_match('/^([97][0-9]{8})$/', $phone, $m)) {
        return '0' . $m[1];
    }
    // already 09… / 07…
    if (preg_match('/^0([97][0-9]{8})$/', $phone, $m)) {
        return '0' . $m[1];
    }
    return $phone; // return as-is; validator will reject if invalid
}
