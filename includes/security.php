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
    $phone = trim($phone);
    if ($phone === '') return true;
    return (bool) preg_match('/^(?:\+251|0)9\d{8}$/', $phone);
}
