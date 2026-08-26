<?php
// Supported languages: code => native name
// Afaan Oromoo is the main/default language; others are selectable options
const SUPPORTED_LANGS = [
    'om' => 'Afaan Oromoo',
    'en' => 'English',
    'am' => 'አማርኛ',
    'ar' => 'العربية',
    'ti' => 'ትግርኛ',
    'so' => 'Soomaali',
    'aa' => 'Qafar Af',
];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Determine active language: ?lang= wins, then session, then default Afaan Oromoo (English secondary)
$requested = $_GET['lang'] ?? null;
if ($requested && isset(SUPPORTED_LANGS[$requested])) {
    $_SESSION['lang'] = $requested;
}
$CURRENT_LANG = $_SESSION['lang'] ?? 'om';
if (!isset(SUPPORTED_LANGS[$CURRENT_LANG])) {
    $CURRENT_LANG = 'om';
}

$LANG_DIR = __DIR__ . '/../lang';
$STRINGS = require $LANG_DIR . '/en.php'; // base fallback
$active = require $LANG_DIR . '/' . $CURRENT_LANG . '.php';
$STRINGS = array_merge($STRINGS, $active);

// Translation helper - falls back to the key itself if missing
function t($key) {
    global $STRINGS;
    return htmlspecialchars($STRINGS[$key] ?? $key);
}

// Raw (non-escaped) translation, for use inside attributes already escaped by caller if needed
function t_raw($key) {
    global $STRINGS;
    return $STRINGS[$key] ?? $key;
}

// Builds the language-switcher <select> that resubmits the current URL with a new ?lang=
function render_lang_switcher() {
    global $CURRENT_LANG;
    $params = $_GET;
    echo '<form method="get" class="lang-switch" onchange="this.submit()">';
    foreach ($_GET as $k => $v) {
        if ($k === 'lang') continue;
        echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
    }
    echo '<select name="lang" aria-label="Language">';
    foreach (SUPPORTED_LANGS as $code => $name) {
        $sel = $code === $CURRENT_LANG ? ' selected' : '';
        echo '<option value="' . $code . '"' . $sel . '>' . htmlspecialchars($name) . '</option>';
    }
    echo '</select></form>';
}
