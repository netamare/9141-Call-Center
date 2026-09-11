<?php
/**
 * Operator AI Copilot v2 — improved local suggestion engine.
 *
 * Still: no external API, no auto-write to DB.
 * Operator reviews suggestions → "Apply suggestions" → then registers.
 *
 * v2 upgrades:
 *  - Score-based category (best keyword hits, not first match)
 *  - Richer Oromo / Amharic / English keyword banks
 *  - Smarter priority + department routing
 *  - Medium confidence when partial evidence
 *  - Better place matching (partial / multi-word)
 *  - Suggested action + short bilingual note
 *  - Supervisor can also use
 */
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../includes/security.php';
require __DIR__ . '/../includes/maps.php';
require_role(['administrator', 'operator', 'supervisor']);
header('Content-Type: application/json; charset=utf-8');

verify_csrf();

$description = trim($_POST['description'] ?? '');
$locationHint = trim($_POST['location'] ?? '');
if ($description === '') {
    echo json_encode(['ok' => false, 'error' => 'empty_description']);
    exit;
}

function ai_norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    return preg_replace('/\s+/', ' ', $s ?? '') ?? '';
}

/** Count how many keywords appear; return [count, first_hit]. */
function ai_score(string $haystack, array $words): array {
    $count = 0;
    $first = null;
    foreach ($words as $w) {
        if ($w !== '' && mb_strpos($haystack, $w) !== false) {
            $count++;
            if ($first === null) $first = $w;
        }
    }
    return [$count, $first];
}

function ai_any(string $haystack, array $words): ?string {
    foreach ($words as $w) {
        if ($w !== '' && mb_strpos($haystack, $w) !== false) return $w;
    }
    return null;
}

$norm = ai_norm($description . ' ' . $locationHint);

/* ------------------------------------------------------------------ */
/* 1. Category — score-based                                           */
/* ------------------------------------------------------------------ */

$categoryKeywords = [
    'emergency' => [
        'balaa', 'ibidda', 'gubate', 'gubuu', 'aksidantii', 'aksidentii', "du'e", "du'de", 'duute',
        'dhiigsa', 'lubbuu', 'hospitaala', 'hospital', 'summii', "hin qabne hafuura", 'rasaasa',
        'ajjeef', 'madaa guddaa', 'fire', 'accident', 'dying', 'emergency', 'ambulance',
        'collision', 'crash', 'injured', 'wounded', 'flood', 'lola bishaan', 'qilleensa',
        'car accident', 'motors', 'konkolaataa cige', 'walitti bu\'e',
    ],
    'illegal' => [
        'hattuu', 'hanna', 'hatani', 'seeraan ala', 'daldala seeraan ala', 'doorsisa',
        'gowwoomsaa', 'maallaqa sobaa', 'nyaaphaa', 'kontirobaandii', 'illegal',
        'smuggl', 'fraud', 'bribe', 'malaammaltummaa', 'theft', 'steal', 'stolen',
        'jibba', 'magalaa sobaa', 'qabeenya hatani', 'bank break', 'burglary',
    ],
    'security' => [
        'lola', 'waraana', 'shakkisiisaa', 'nageenya', 'hidhannoo', 'saamicha',
        'jeequmsa', 'weerara', 'reebicha', 'miidhaa', 'fight', 'robbery', 'gun',
        'knife', 'weapon', 'suspicious', 'security', 'threat', 'doorsisa nageenyaa',
        'assault', 'violence', 'bomb', 'explosion', 'terror', 'crowd control',
        'police needed', 'poolisii',
    ],
    'service' => [
        'bishaan', 'ibsaa', 'ibsituu', 'daandii', 'xurii', 'kalaqa', 'tajaajila',
        'manholii', 'magaalaa qulqulleessuu', 'water', 'electricity', 'power outage',
        'road', 'garbage', 'waste', 'service', 'sewage', 'traash', 'trash',
        'street light', 'pothole', 'drainage', 'pipe burst', 'no water', 'no power',
        'ibsaa hin jiru', 'bishaan hin jiru', 'daandii cige', 'xurii baay\'ee',
    ],
];

$bestSlug = null;
$bestScore = 0;
$categoryHit = null;
foreach ($categoryKeywords as $slug => $words) {
    [$score, $hit] = ai_score($norm, $words);
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestSlug = $slug;
        $categoryHit = $hit;
    }
}

$categorySlug = $bestScore > 0 ? $bestSlug : null;
$catConfidence = $bestScore >= 2 ? 'high' : ($bestScore === 1 ? 'medium' : 'none');

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
    'dying', 'gun', 'weapon', 'bomb', 'explosion', 'mass casualty',
];
$highWords = [
    'aksidantii', 'aksidentii', 'saamicha', 'lola', 'miidhaa guddaa', 'reebicha', 'robbery',
    'accident', 'fight', 'injury', 'madaa', 'threat', 'fire', 'ibidda', 'gubate',
    'collision', 'crash', 'assault', 'armed',
];
$lowWords = [
    'xurii', 'garbage', 'street light', 'noise', 'complain', 'komii', 'gadi-aanaa',
];

$priority = 'medium';
$priorityConfidence = 'low';
$priorityHit = null;

if ($hit = ai_any($norm, $criticalWords)) {
    $priority = 'critical';
    $priorityConfidence = 'high';
    $priorityHit = $hit;
} elseif ($hit = ai_any($norm, $highWords)) {
    $priority = 'high';
    $priorityConfidence = 'high';
    $priorityHit = $hit;
} elseif ($hit = ai_any($norm, $lowWords)) {
    $priority = 'low';
    $priorityConfidence = 'medium';
    $priorityHit = $hit;
} elseif ($categorySlug === 'emergency') {
    $priority = 'high';
    $priorityConfidence = 'medium';
} elseif ($categorySlug === 'illegal' || $categorySlug === 'security') {
    $priority = 'high';
    $priorityConfidence = 'medium';
} elseif ($categorySlug === 'service') {
    $priority = 'medium';
    $priorityConfidence = 'medium';
}

/* ------------------------------------------------------------------ */
/* 3. Address / place from adama_places()                              */
/* ------------------------------------------------------------------ */

$places = function_exists('adama_places') ? adama_places() : [];
$matchedPlace = null;
$placeConfidence = 'none';

if ($places) {
    // Prefer longer place names first (more specific)
    usort($places, function ($a, $b) {
        return mb_strlen($b['name'] ?? '') <=> mb_strlen($a['name'] ?? '');
    });
    foreach ($places as $p) {
        $pname = ai_norm($p['name'] ?? '');
        if ($pname === '') continue;
        if (mb_strpos($norm, $pname) !== false) {
            $matchedPlace = $p;
            $placeConfidence = 'high';
            break;
        }
        // partial: all significant tokens of place name present
        $tokens = array_filter(explode(' ', $pname), fn($t) => mb_strlen($t) >= 3);
        if (count($tokens) >= 2) {
            $ok = true;
            foreach ($tokens as $t) {
                if (mb_strpos($norm, $t) === false) { $ok = false; break; }
            }
            if ($ok) {
                $matchedPlace = $p;
                $placeConfidence = 'medium';
                break;
            }
        }
    }
}

/* ------------------------------------------------------------------ */
/* 4. Department routing                                               */
/* ------------------------------------------------------------------ */

$departments = $pdo->query("SELECT * FROM departments")->fetchAll();

function ai_dept_by_name_like(array $deps, string $needle): ?array {
    $n = mb_strtolower($needle);
    foreach ($deps as $d) {
        if (mb_stripos($d['name'] ?? '', $n) !== false) return $d;
    }
    return null;
}

$deptRow = null;
$trafficWords = ['konkolaataa', 'trafikaa', 'automobile', 'car crash', 'traffic', 'daandii geejjibaa', 'collision', 'crash', 'aksidantii', 'aksidentii'];
$fireWords = ['ibidda', 'gubate', 'gubuu', 'fire', 'smoke', 'aara'];
$healthWords = ['hospitaala', 'hospital', 'ambulance', 'dhiigsa', 'madaa', 'injury', 'summii', 'poison'];
$waterWords = ['bishaan', 'water', 'pipe', 'sewage', 'drainage', 'manholii'];
$powerWords = ['ibsaa', 'ibsituu', 'electricity', 'power', 'transformer'];

if ($categorySlug === 'illegal' || $categorySlug === 'security') {
    if (ai_any($norm, $trafficWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Traffic') ?? ai_dept_by_name_like($departments, 'Police');
    } else {
        $deptRow = ai_dept_by_name_like($departments, 'Police')
            ?? ai_dept_by_name_like($departments, 'Security')
            ?? ai_dept_by_name_like($departments, 'Law');
    }
} elseif ($categorySlug === 'emergency') {
    if (ai_any($norm, $fireWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Fire') ?? ai_dept_by_name_like($departments, 'Emergency');
    } elseif (ai_any($norm, $trafficWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Traffic') ?? ai_dept_by_name_like($departments, 'Emergency');
    } elseif (ai_any($norm, $healthWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Health')
            ?? ai_dept_by_name_like($departments, 'Ambulance')
            ?? ai_dept_by_name_like($departments, 'Emergency')
            ?? ai_dept_by_name_like($departments, 'Fire');
    } else {
        $deptRow = ai_dept_by_name_like($departments, 'Emergency')
            ?? ai_dept_by_name_like($departments, 'Fire');
    }
} elseif ($categorySlug === 'service') {
    if (ai_any($norm, $waterWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Water') ?? ai_dept_by_name_like($departments, 'City');
    } elseif (ai_any($norm, $powerWords)) {
        $deptRow = ai_dept_by_name_like($departments, 'Electric') ?? ai_dept_by_name_like($departments, 'Power') ?? ai_dept_by_name_like($departments, 'City');
    } else {
        $deptRow = ai_dept_by_name_like($departments, 'City Services')
            ?? ai_dept_by_name_like($departments, 'Service')
            ?? ai_dept_by_name_like($departments, 'Municipal')
            ?? ai_dept_by_name_like($departments, 'City');
    }
}

$deptConfidence = $deptRow ? ($bestScore >= 2 ? 'high' : 'medium') : 'none';

/* ------------------------------------------------------------------ */
/* 5. Duplicates (48h)                                                 */
/* ------------------------------------------------------------------ */

function ai_significant_words(string $norm): array {
    static $stop = [
        'fi','akka','kan',"ta'e",'jira','irratti','keessatti','waan','yeroo','sana',
        'the','a','an','and','of','in','on','at','to','is','was','were','with','from',
        'this','that','for','are','be','by','or','as','it','an','namni','nama',
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

        $sameArea = $matchedPlace && !empty($ev['address'])
            && mb_stripos($ev['address'], $matchedPlace['name']) !== false;

        if ($similarity >= 0.30 || ($sameArea && $similarity >= 0.18)) {
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
/* 6. Summary + suggested action                                       */
/* ------------------------------------------------------------------ */

$words = preg_split('/\s+/', trim($description));
$summary = implode(' ', array_slice($words, 0, 22)) . (count($words) > 22 ? '…' : '');

$actions = [
    'emergency' => 'Confirm location → dispatch emergency unit immediately',
    'illegal'   => 'Notify police / security desk and preserve evidence notes',
    'security'  => 'Alert security unit; monitor for escalation',
    'service'   => 'Assign to city services department and set follow-up',
];
$suggestedAction = $actions[$categorySlug] ?? 'Review details and assign appropriate department';

if ($priority === 'critical') {
    $suggestedAction = 'CRITICAL — escalate now. ' . $suggestedAction;
}

/* ------------------------------------------------------------------ */
/* Response                                                            */
/* ------------------------------------------------------------------ */

echo json_encode([
    'ok' => true,
    'version' => 'v2',
    'summary' => $summary,
    'suggested_action' => $suggestedAction,
    'category' => $categoryRow ? [
        'id' => (int) $categoryRow['id'],
        'name' => $categoryRow['name'],
        'slug' => $categoryRow['slug'],
        'matched_on' => $categoryHit,
        'score' => $bestScore,
        'confidence' => $catConfidence,
    ] : ['id' => null, 'confidence' => 'none'],
    'priority' => [
        'value' => ($categorySlug || $priorityHit) ? $priority : null,
        'matched_on' => $priorityHit,
        'confidence' => ($categorySlug || $priorityHit) ? $priorityConfidence : 'none',
    ],
    'address' => $matchedPlace ? [
        'name' => $matchedPlace['name'],
        'lat' => $matchedPlace['lat'] ?? null,
        'lng' => $matchedPlace['lng'] ?? null,
        'confidence' => $placeConfidence,
    ] : ['name' => null, 'confidence' => 'none'],
    'department' => $deptRow ? [
        'id' => (int) $deptRow['id'],
        'name' => $deptRow['name'],
        'confidence' => $deptConfidence,
    ] : ['id' => null, 'confidence' => 'none'],
    'duplicates' => $duplicates,
], JSON_UNESCAPED_UNICODE);
