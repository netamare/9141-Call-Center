<?php
/**
 * Operator AI Copilot — rule/keyword based suggestion engine.
 *
 * NOT a trained ML model, NOT an external API call — everything runs
 * locally against the description text the operator typed, plus the
 * existing categories/departments/adama_places() data and recent
 * events (for duplicate detection).
 *
 * The endpoint only ever returns SUGGESTIONS. It never writes to the
 * database and never changes an event. The operator must review the
 * suggestions and press "Apply suggestions" in the UI before any form
 * field is filled in — see assets AI copilot script in new_event.php.
 *
 * When there isn't enough evidence in the text, a field is returned
 * as null with confidence "none" so the UI shows "Review manually"
 * instead of guessing.
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/maps.php';
require_role(['administrator', 'operator']);
header('Content-Type: application/json; charset=utf-8');

verify_csrf();

$description = trim($_POST['description'] ?? '');
if ($description === '') {
    echo json_encode(['ok' => false, 'error' => 'empty_description']);
    exit;
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */

function ai_norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    // Collapse punctuation to spaces so keyword matching isn't thrown
    // off by commas/periods glued to a word.
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    return preg_replace('/\s+/', ' ', $s ?? '') ?? '';
}

/** True if any keyword in $words appears as a substring of $haystack. */
function ai_any(string $haystack, array $words): ?string {
    foreach ($words as $w) {
        if ($w !== '' && mb_strpos($haystack, $w) !== false) {
            return $w;
        }
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* 1. Category — matched against the four fixed categories/slugs       */
/* ------------------------------------------------------------------ */

$norm = ai_norm($description);

$categoryKeywords = [
    'emergency' => [
        'balaa', 'ibidda', 'gubate', 'gubuu', 'aksidantii', "du'e", "du'de", 'duute',
        'dhiigsa', 'lubbuu', 'hospitaala', 'summii', "hin qabne hafuura", 'rasaasa',
        'ajjeef', 'madaa guddaa', 'fire', 'accident', 'dying', 'emergency', 'ambulance',
    ],
    'illegal' => [
        'hattuu', 'hanna', 'hatani', 'seeraan ala', 'daldala seeraan ala', 'doorsisa',
        'gowwoomsaa', 'maallaqa sobaa', 'nyaaphaa', 'kontirobaandii', 'illegal',
        'smuggl', 'fraud', 'bribe', 'malaammaltummaa',
    ],
    'security' => [
        'lola', 'waraana', 'shakkisiisaa', 'nageenya', 'hidhannoo', 'saamicha',
        'jeequmsa', 'weerara', 'reebicha', 'miidhaa', 'fight', 'robbery', 'gun',
        'knife', 'weapon', 'suspicious', 'security', 'threat', 'doorsisa nageenyaa',
    ],
    'service' => [
        'bishaan', 'ibsaa', 'ibsituu', 'daandii', 'xurii', 'kalaqa', 'tajaajila',
        'manholii', 'magaalaa qulqulleessuu', 'water', 'electricity', 'power outage',
        'road', 'garbage', 'waste', 'service', 'sewage', 'traash',
    ],
];

$categorySlug = null;
$categoryHit = null;
foreach ($categoryKeywords as $slug => $words) {
    $hit = ai_any($norm, $words);
    if ($hit) { $categorySlug = $slug; $categoryHit = $hit; break; }
}

$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
$categoryRow = null;
if ($categorySlug) {
    foreach ($categories as $c) {
        if (($c['slug'] ?? '') === $categorySlug) { $categoryRow = $c; break; }
    }
}

/* ------------------------------------------------------------------ */
/* 2. Priority                                                         */
/* ------------------------------------------------------------------ */

$criticalWords = [
    "du'e", "du'de", 'duute', 'lubbuu', 'ibidda guddaa', 'dhiigsa guddaa', 'summii',
    'hidhannoo', 'rasaasa', "hin qabne hafuura", 'ajjeef', 'murder', 'critical',
    'dying', 'gun', 'weapon',
];
$highWords = [
    'aksidantii', 'saamicha', 'lola', 'miidhaa guddaa', 'reebicha', 'robbery',
    'accident', 'fight', 'injury', 'madaa', 'threat',
];

$priority = 'medium';
$priorityConfidence = 'low';
$priorityHit = ai_any($norm, $criticalWords);
if ($priorityHit) {
    $priority = 'critical';
    $priorityConfidence = 'high';
} else {
    $priorityHit = ai_any($norm, $highWords);
    if ($priorityHit) {
        $priority = 'high';
        $priorityConfidence = 'high';
    } elseif ($categorySlug === 'emergency') {
        $priority = 'high';
        $priorityConfidence = 'high';
    } elseif ($categorySlug) {
        $priorityConfidence = 'medium';
    }
}

/* ------------------------------------------------------------------ */
/* 3. Address — match against the known Adama place list               */
/* ------------------------------------------------------------------ */

$places = adama_places();
$matchedPlace = null;
foreach ($places as $p) {
    // Compare each significant part of the place name (split on / or ,)
    // against the description, so "Bole" matches "Bole (Sub-city)".
    $parts = preg_split('/[\/,()]+/u', $p['name']);
    foreach ($parts as $part) {
        $part = trim($part);
        if (mb_strlen($part) < 3) continue;
        if (mb_strpos($norm, ai_norm($part)) !== false) {
            $matchedPlace = $p;
            break 2;
        }
    }
}

/* ------------------------------------------------------------------ */
/* 4. Department — derived from category (+ a few extra cues)          */
/* ------------------------------------------------------------------ */

$departments = $pdo->query("SELECT * FROM departments ORDER BY id")->fetchAll();
function ai_dept_by_name_like(array $departments, string $needle) {
    foreach ($departments as $d) {
        if (mb_stripos($d['name'], $needle) !== false) return $d;
    }
    return null;
}

$deptRow = null;
$trafficWords = ['konkolaataa', 'trafikaa', 'automobile', 'car crash', 'traffic', 'daandii geejjibaa'];
$fireWords = ['ibidda', 'gubate', 'gubuu', 'fire'];

if ($categorySlug === 'illegal' || $categorySlug === 'security') {
    if (ai_any($norm, $trafficWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Traffic');
    } else {
        $deptRow = ai_dept_by_name_like($departments, 'Police');
    }
} elseif ($categorySlug === 'emergency') {
    if (ai_any($norm, $trafficWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Traffic');
    } elseif (ai_any($norm, $fireWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Fire');
    } else {
        $deptRow = ai_dept_by_name_like($departments, 'Fire');
    }
} elseif ($categorySlug === 'service') {
    $deptRow = ai_dept_by_name_like($departments, 'City Services') ?? ai_dept_by_name_like($departments, 'Service');
}

/* ------------------------------------------------------------------ */
/* 5. Duplicate detection — recent events with overlapping wording     */
/*    and/or the same area, within the last 48 hours.                  */
/* ------------------------------------------------------------------ */

function ai_significant_words(string $norm): array {
    static $stop = [
        'fi','akka','kan','ta\'e','jira','irratti','keessatti','waan','yeroo','sana',
        'the','a','an','and','of','in','on','at','to','is','was','were','with',
    ];
    $words = array_filter(explode(' ', $norm), function ($w) use ($stop) {
        return mb_strlen($w) >= 4 && !in_array($w, $stop, true);
    });
    return array_values(array_unique($words));
}

$myWords = ai_significant_words($norm);
$duplicates = [];

if (count($myWords) >= 2) {
    $stmt = $pdo->prepare("SELECT id, tracking_code, description, address, category_id, created_at
                            FROM events
                            WHERE created_at >= (NOW() - INTERVAL 48 HOUR)
                            ORDER BY created_at DESC LIMIT 200");
    $stmt->execute();
    $recent = $stmt->fetchAll();

    foreach ($recent as $ev) {
        $evNorm = ai_norm($ev['description'] ?? '');
        $evWords = ai_significant_words($evNorm);
        if (!$evWords) continue;

        $shared = array_intersect($myWords, $evWords);
        $union = array_unique(array_merge($myWords, $evWords));
        $similarity = count($union) ? count($shared) / count($union) : 0;

        $sameArea = $matchedPlace && $ev['address'] && mb_stripos($ev['address'], $matchedPlace['name']) !== false;

        if ($similarity >= 0.35 || ($sameArea && $similarity >= 0.2)) {
            $duplicates[] = [
                'tracking_code' => $ev['tracking_code'],
                'created_at' => $ev['created_at'],
                'address' => $ev['address'],
                'similarity' => round($similarity, 2),
            ];
        }
        if (count($duplicates) >= 3) break;
    }
}

/* ------------------------------------------------------------------ */
/* 6. Short summary (extractive, no model — first ~20 words)           */
/* ------------------------------------------------------------------ */

$words = preg_split('/\s+/', trim($description));
$summary = implode(' ', array_slice($words, 0, 20)) . (count($words) > 20 ? '…' : '');

/* ------------------------------------------------------------------ */
/* Response                                                             */
/* ------------------------------------------------------------------ */

echo json_encode([
    'ok' => true,
    'summary' => $summary,
    'category' => $categoryRow ? [
        'id' => (int) $categoryRow['id'],
        'name' => $categoryRow['name'],
        'slug' => $categoryRow['slug'],
        'matched_on' => $categoryHit,
        'confidence' => 'high',
    ] : ['id' => null, 'confidence' => 'none'],
    'priority' => [
        'value' => $categorySlug || $priorityHit ? $priority : null,
        'matched_on' => $priorityHit,
        'confidence' => ($categorySlug || $priorityHit) ? $priorityConfidence : 'none',
    ],
    'address' => $matchedPlace ? [
        'name' => $matchedPlace['name'],
        'lat' => $matchedPlace['lat'],
        'lng' => $matchedPlace['lng'],
        'confidence' => 'high',
    ] : ['name' => null, 'confidence' => 'none'],
    'department' => $deptRow ? [
        'id' => (int) $deptRow['id'],
        'name' => $deptRow['name'],
        'confidence' => 'high',
    ] : ['id' => null, 'confidence' => 'none'],
    'duplicates' => $duplicates,
]);
