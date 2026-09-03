<?php
// Database configuration - edit these to match your server (e.g. XAMPP/WAMP defaults shown)
define('DB_HOST', 'localhost');
define('DB_NAME', 'callcenter9141');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Generates a short unique tracking code so a caller/reporter can follow up, e.g. 9141-3F82A1
if (!function_exists('generate_tracking_code')) {
    function generate_tracking_code() {
        return '9141-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }
}

// --- File upload settings (images, video, voice/audio, documents) ---
// Video uploads support up to 50 GB (requires matching php.ini / web-server limits).
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('UPLOAD_URL', 'uploads');
define('MAX_UPLOAD_BYTES', 50 * 1024 * 1024 * 1024);      // 50 GB overall ceiling
define('MAX_VIDEO_UPLOAD_BYTES', 50 * 1024 * 1024 * 1024); // 50 GB for video
define('MAX_OTHER_UPLOAD_BYTES', 100 * 1024 * 1024);       // 100 MB for image/audio/doc
define('MAX_FILES_PER_REPORT', 5);

const ALLOWED_UPLOAD_TYPES = [
    'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
    'mp4' => 'video', 'mov' => 'video', '3gp' => 'video', 'webm' => 'video',
    'mkv' => 'video', 'avi' => 'video', 'm4v' => 'video', 'ts' => 'video',
    'mp3' => 'audio', 'wav' => 'audio', 'm4a' => 'audio', 'ogg' => 'audio', 'amr' => 'audio',
    'pdf' => 'document', 'doc' => 'document', 'docx' => 'document',
];

/**
 * Ensure uploads folder + event_attachments table exist (Backend Upload bootstrap).
 */
function ensure_upload_backend(PDO $pdo): void {
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0755, true);
    }
    // Protect against script execution inside uploads/
    $htaccess = UPLOAD_DIR . '/.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "# Deny script execution in uploads\n"
            . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|py|asp|aspx|jsp)$\">\n"
            . "  Require all denied\n"
            . "</FilesMatch>\n"
            . "Options -Indexes\n");
    }
    // index.html so directory listing is empty if Options fails
    $indexHtml = UPLOAD_DIR . '/index.html';
    if (!is_file($indexHtml)) {
        @file_put_contents($indexHtml, '');
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS event_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_id INT NOT NULL,
            file_path VARCHAR(512) NOT NULL,
            original_name VARCHAR(255) NULL,
            file_type VARCHAR(32) NOT NULL DEFAULT 'document',
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (event_id),
            INDEX (file_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        // table may already exist with slightly different schema — ignore
    }
}

/**
 * Saves one uploaded file for an event and records it in event_attachments.
 * Videos may be up to 50 GB; other types capped at MAX_OTHER_UPLOAD_BYTES.
 *
 * Returns true on success, or a string error message on failure
 * (truthy check: === true means success; any string means failure).
 * Callers that only check truthy still work: failed returns are non-empty strings.
 */
function save_report_attachment($pdo, $event_id, $file) {
    ensure_upload_backend($pdo);

    if (!is_array($file) || !isset($file['error'])) {
        return 'Faayilli sirrii miti.';
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'Faayilli guddaa dha (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Faayilli guddaa dha (MAX_FILE_SIZE).',
            UPLOAD_ERR_PARTIAL    => 'Faayilli guutummaatti hin olkaa\'amne.',
            UPLOAD_ERR_NO_FILE    => 'Faayilli hin filatamne.',
            UPLOAD_ERR_NO_TMP_DIR => 'Tmp directory hin jiru.',
            UPLOAD_ERR_CANT_WRITE => 'Disk irratti barreessuun hin danda\'amne.',
            UPLOAD_ERR_EXTENSION  => 'Extension PHP faayila dhowwe.',
        ];
        return $errMap[(int)$file['error']] ?? ('Upload error #' . (int)$file['error']);
    }

    $original = $file['name'] ?? '';
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if ($ext === '' || !isset(ALLOWED_UPLOAD_TYPES[$ext])) {
        return 'Gosti faayilaa hin eeyyamamne: .' . ($ext ?: '?');
    }
    $type = ALLOWED_UPLOAD_TYPES[$ext];

    $size = (int)($file['size'] ?? 0);
    $limit = ($type === 'video') ? MAX_VIDEO_UPLOAD_BYTES : MAX_OTHER_UPLOAD_BYTES;
    if ($size <= 0) {
        return 'Faayilli duwwaa ykn guddina hin beekamne.';
    }
    if ($size > $limit) {
        $limitMb = $limit >= 1024 * 1024 * 1024
            ? round($limit / (1024 * 1024 * 1024), 1) . ' GB'
            : round($limit / (1024 * 1024)) . ' MB';
        return 'Faayilli guddaa dha. Daangaa: ' . $limitMb . '.';
    }

    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'Faayilli tmp irratti hin argamne (is_uploaded_file failed).';
    }

    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0755, true)) {
        return 'Folder uploads/ uumuu hin dandeenye.';
    }
    if (!is_writable(UPLOAD_DIR)) {
        return 'Folder uploads/ barreessuu hin danda\'amu (permissions).';
    }

    $safe_name = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $safe_name;

    if (!@move_uploaded_file($tmp, $dest)) {
        return 'move_uploaded_file hin milkoofne – disk / permission ilaali.';
    }

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO event_attachments (event_id, file_path, original_name, file_type, file_size)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$event_id,
            UPLOAD_URL . '/' . $safe_name,
            $original,
            $type,
            $size,
        ]);
    } catch (Throwable $e) {
        @unlink($dest);
        return 'DB insert hin milkoofne: ' . $e->getMessage();
    }

    return true;
}

/**
 * Register an already-saved file on disk as an event attachment
 * (used by live-stream recording).
 */
function register_attachment_from_path($pdo, $event_id, $relativePath, $originalName, $fileType, $fileSize) {
    ensure_upload_backend($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO event_attachments (event_id, file_path, original_name, file_type, file_size)
         VALUES (?, ?, ?, ?, ?)"
    );
    return $stmt->execute([(int)$event_id, $relativePath, $originalName, $fileType, (int)$fileSize]);
}
