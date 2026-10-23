<?php
// Cron-only wrapper: record execution even when notification mail is delayed.
if (isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['REMOTE_ADDR'])) {
    http_response_code(403);
    exit;
}
date_default_timezone_set('Asia/Tokyo');
umask(0077);
ini_set('display_errors', '0');
$runnerReport = __DIR__ . '/backup-run-report.txt';
$runnerStart = "START " . date(DATE_ATOM) . "\nPHP " . PHP_VERSION . "\nSAPI " . PHP_SAPI . "\n";
if (file_put_contents($runnerReport, $runnerStart, LOCK_EX) === false) {
    echo "RUNNER FAILED: cannot write backup-run-report.txt\n";
    exit(1);
}
echo $runnerStart;
ob_start(function ($buffer) use ($runnerReport) {
    file_put_contents($runnerReport, $buffer, FILE_APPEND | LOCK_EX);
    return $buffer;
});
register_shutdown_function(function () {
    $last = error_get_last();
    if ($last !== null && in_array($last['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        echo "FATAL type=" . $last['type'] . " file=" . basename($last['file']) . " line=" . $last['line'] . "\n";
    }
    echo "RUNNER END " . date(DATE_ATOM) . " (see backup result above)\n";
});
try {
    $runnerTarget = __DIR__ . '/backup-sqlite.php';
    if (!is_file($runnerTarget)) {
        echo "RUNNER FAILED: backup-sqlite.php missing\n";
        exit(1);
    }
    echo "STARTING backup-sqlite.php\n";
    require $runnerTarget;
} catch (Throwable $runnerError) {
    echo "RUNNER FAILED: " . get_class($runnerError) . " file=" . basename($runnerError->getFile()) . " line=" . $runnerError->getLine() . "\n";
    exit(1);
}
