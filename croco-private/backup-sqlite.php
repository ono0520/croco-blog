<?php
/**
 * croco.blog SQLite snapshot, version 1.
 * Install beside database.sqlite in croco-private and run via Lolipop cron.
 * Source is opened read-only. Does not load WordPress or copy website files.
 * Publishes latest.json only after integrity validation and SHA-256 hashing.
 * Retains seven successful server-side snapshots; NAS retention is separate.
 */
if (isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit;
}
date_default_timezone_set('Asia/Tokyo');
ini_set('display_errors', '0');
umask(0077);

$stage = 'INIT';
$finished = false;
$exitCode = 0;
$lock = null;
$source = null;
$destination = null;
$temporary = null;
$manifestTemporary = null;
register_shutdown_function(function () use (&$finished, &$stage) {
    if (!$finished) {
        echo "SQLITE BACKUP FAILED: interrupted at " . $stage . "\n";
    }
});

try {
    if (!class_exists('SQLite3') || !method_exists('SQLite3', 'backup')) {
        throw new RuntimeException('SQLite backup unavailable');
    }
    $database = __DIR__ . '/database.sqlite';
    $directory = __DIR__ . '/backup-snapshots';
    $stage = 'PREPARE_DIRECTORY';
    if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0700))) {
        throw new RuntimeException('Cannot create snapshot directory');
    }
    $lock = fopen($directory . '/snapshot.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Snapshot already running or lock unavailable');
    }
    // This directory inherits the tested parent protection; also deny explicitly.
    $deny = "Order allow,deny\nDeny from all\n";
    if (file_put_contents($directory . '/.htaccess', $deny, LOCK_EX) !== strlen($deny)) {
        throw new RuntimeException('Cannot write access protection');
    }
    if (!is_file($database) || !is_readable($database) || is_link($database)) {
        throw new RuntimeException('Source database unavailable');
    }
    $identifier = date('Ymd-His') . '-' . bin2hex(random_bytes(6));
    $filename = 'snapshot-' . $identifier . '.sqlite';
    $temporary = $directory . '/' . $filename . '.partial';
    $finalPath = $directory . '/' . $filename;
    $stage = 'SQLITE_BACKUP';
    $source = new SQLite3($database, SQLITE3_OPEN_READONLY);
    $source->enableExceptions(true);
    $source->busyTimeout(5000);
    $destination = new SQLite3($temporary, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    $destination->enableExceptions(true);
    $destination->busyTimeout(5000);
    if (!$source->backup($destination)) {
        throw new RuntimeException('SQLite backup returned failure');
    }
    $source->close();
    $source = null;
    // Ensure the completed copy is self-contained, including for WAL sources.
    if (strtolower((string) $destination->querySingle('PRAGMA journal_mode=DELETE')) !== 'delete') {
        throw new RuntimeException('Cannot finalize standalone database');
    }
    $stage = 'INTEGRITY_CHECK';
    if ($destination->querySingle('PRAGMA integrity_check') !== 'ok') {
        throw new RuntimeException('Integrity check failed');
    }
    $destination->close();
    $destination = null;
    $bytes = filesize($temporary);
    $hash = hash_file('sha256', $temporary);
    if ($bytes === false || $bytes < 512 || $hash === false) {
        throw new RuntimeException('Invalid snapshot file');
    }
    $stage = 'PUBLISH';
    if (!rename($temporary, $finalPath)) {
        throw new RuntimeException('Cannot publish snapshot');
    }
    $temporary = null;
    $manifest = json_encode(array(
        'schema' => 1,
        'created_at' => date(DATE_ATOM),
        'source' => 'croco-private/database.sqlite',
        'file' => $filename,
        'bytes' => $bytes,
        'sha256' => $hash,
        'integrity_check' => 'ok'
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    $manifestTemporary = $directory . '/latest-' . $identifier . '.json.partial';
    if (file_put_contents($manifestTemporary, $manifest, LOCK_EX) !== strlen($manifest)
        || !rename($manifestTemporary, $directory . '/latest.json')) {
        throw new RuntimeException('Cannot publish manifest');
    }
    $manifestTemporary = null;
    $stage = 'RETENTION';
    $files = array();
    foreach (glob($directory . '/snapshot-*.sqlite') ?: array() as $path) {
        if (preg_match('/^snapshot-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\.sqlite$/D', basename($path))
            && is_file($path) && !is_link($path)) {
            $files[] = $path;
        }
    }
    rsort($files, SORT_STRING);
    // Always protect the manifest target, even if the system clock moves back.
    $keep = array($finalPath);
    foreach ($files as $path) {
        if ($path !== $finalPath && count($keep) < 7) {
            $keep[] = $path;
        }
    }
    foreach ($files as $path) {
        if (!in_array($path, $keep, true) && !unlink($path)) {
            echo "WARNING: Could not remove an old server snapshot\n";
        }
    }
    echo "SQLITE BACKUP SUCCESS\n";
    echo "Snapshot: " . $filename . "\n";
    echo "Bytes: " . $bytes . "\n";
    echo "Integrity: ok\n";
    echo "NAS transfer: not performed by this script\n";
    $finished = true;
} catch (Throwable $error) {
    echo "SQLITE BACKUP FAILED: stage=" . $stage . " type=" . get_class($error) . "\n";
    $finished = true;
    $exitCode = 1;
} finally {
    if ($source instanceof SQLite3) { $source->close(); }
    if ($destination instanceof SQLite3) { $destination->close(); }
    if ($temporary !== null && is_file($temporary)) { @unlink($temporary); }
    if ($manifestTemporary !== null && is_file($manifestTemporary)) { @unlink($manifestTemporary); }
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
}
exit($exitCode);
