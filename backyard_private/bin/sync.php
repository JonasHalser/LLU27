<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/app/sync.php';
require_once dirname(__DIR__) . '/app/performance_sync.php';

$lock = fopen(dirname(__DIR__) . '/sync.lock', 'c');

if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Sync läuft bereits\n");
}

try {
    foreach (runSync() as $line) {
        echo $line . "\n";
    }

    foreach (ll27SyncPerformance() as $line) {
        echo $line . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
