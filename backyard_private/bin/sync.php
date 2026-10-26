<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once dirname(__DIR__) . '/app/sync.php';
require_once dirname(__DIR__) . '/app/performance_sync.php';
require_once dirname(__DIR__) . '/app/weekly_goals.php';
require_once dirname(__DIR__) . '/app/missions.php';
require_once dirname(__DIR__) . '/app/jokers.php';
require_once dirname(__DIR__) . '/app/supporter_coins.php';

$lock = fopen(dirname(__DIR__) . '/sync.lock', 'c');

if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("Sync läuft bereits\n");
}

try {
    try {
        supporterCoinRefreshCustomMarkets();
    } catch (PDOException $e) {
        echo "Eigene Wettziele noch nicht eingerichtet\n";
    }

    foreach (runSync() as $line) {
        echo $line . "\n";
    }

    try {
        weeklyGoalRefreshCurrentAndPrevious();
        echo "Wochenziele aktualisiert\n";
    } catch (PDOException $e) {
        // Training sync remains available until the optional migration is installed.
        echo "Wochenziele noch nicht eingerichtet\n";
    }

    try {
        missionRefreshAll();
        echo "Supporter-Missionen aktualisiert\n";
    } catch (PDOException $e) {
        // Training sync remains available until the optional migration is installed.
        echo "Supporter-Missionen noch nicht eingerichtet\n";
    }

    try {
        supporterCoinRefreshCustomMarkets();
        echo "Eigene Wettziele aktualisiert\n";
    } catch (PDOException $e) {
        echo "Eigene Wettziele noch nicht eingerichtet\n";
    }

    try {
        jokerRefreshAll();
        echo "Supporter-Joker aktualisiert\n";
    } catch (PDOException $e) {
        // Training sync remains available until the optional migration is installed.
        echo "Supporter-Joker noch nicht eingerichtet\n";
    }

    foreach (ll27SyncPerformance() as $line) {
        echo $line . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
