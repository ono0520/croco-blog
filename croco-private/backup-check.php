<?php
/**
 * Backup prerequisites check, version 1.
 * Upload to croco-private/backup-check.php and run from Lolipop cron.
 * Read-only: no WordPress bootstrap, database connection, file changes,
 * network requests, credentials or database contents in the output.
 */

// Cron may use CLI or CGI PHP. Reject HTTP requests before any inspection.
if (isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit;
}

date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', '0');

function backup_check_flag($value) {
    return $value ? 'YES' : 'NO';
}

$root = dirname(__DIR__);
$database = __DIR__ . '/database.sqlite';
$sqliteAvailable = class_exists('SQLite3', false);
$pdoAvailable = class_exists('PDO', false);
$report = array(
    'report' => 'LOLLIPOP_BACKUP_CHECK_V1',
    'checked_at_jst' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'php_sapi' => PHP_SAPI,
    'sqlite3_extension' => backup_check_flag($sqliteAvailable),
    'sqlite3_backup_method' => backup_check_flag(
        $sqliteAvailable && method_exists('SQLite3', 'backup')
    ),
    'pdo_sqlite' => backup_check_flag(
        $pdoAvailable && in_array('sqlite', PDO::getAvailableDrivers(), true)
    ),
    'zip_extension' => backup_check_flag(class_exists('ZipArchive', false)),
    'sha256_available' => backup_check_flag(
        function_exists('hash_algos') && in_array('sha256', hash_algos(), true)
    ),
    'private_folder_writable' => backup_check_flag(is_writable(__DIR__)),
    'database_exists' => backup_check_flag(is_file($database)),
    'database_readable' => backup_check_flag(is_readable($database)),
    'database_wal_exists' => backup_check_flag(is_file($database . '-wal')),
    'database_shm_exists' => backup_check_flag(is_file($database . '-shm')),
    'wordpress_config_exists' => backup_check_flag(
        is_file($root . '/croco.blog/wp-config.php')
    ),
    'private_htaccess_exists' => backup_check_flag(
        is_file(__DIR__ . '/.htaccess')
    ),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
);

foreach (array('croco-private', 'croco.blog', 'exifframe.net',
               'sendaiplus-stg', 'sendaiplus.com') as $folder) {
    $report['folder_' . $folder] = backup_check_flag(
        is_dir($root . '/' . $folder) && is_readable($root . '/' . $folder)
    );
}

echo "=== BACKUP READINESS CHECK (not a backup) ===\n";
foreach ($report as $key => $value) {
    echo $key . ': ' . $value . "\n";
}
echo "=== END ===\n";
