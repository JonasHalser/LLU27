<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';

// Read-only Strava cache -> weekly scores. No Strava API requests.
// A full loop is 6,706 metres within a SINGLE running activity.
// For example two runs of 4 km are 0 loops, not 1 loop.
$pdo = db();
$lock = fopen(dirname(__DIR__) . '/challenge.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { exit("Challenge läuft bereits\n"); }
$utc = new DateTimeZone('UTC');
$now = new DateTimeImmutable('now', $utc);
$monday = $now->modify('monday this week')->setTime(0, 0);
$lastMonday = $monday->modify('-7 days');
$previousEnd = $monday->modify('-1 second');

$users = $pdo->query("SELECT id, username, start_date FROM users WHERE username IN ('tim','jonas','noah')")->fetchAll(PDO::FETCH_ASSOC);
if (!$users) { exit("Keine Teamkonten gefunden\n"); }

// Sync prerequisite: the regular Strava import must have completed recently
// for each connected athlete. Do not silently finalize a missing-data week.
$recent = $pdo->query("SELECT user_id, last_sync, sync_error FROM athletes")->fetchAll(PDO::FETCH_ASSOC);
$lastSync = [];
foreach ($recent as $a) $lastSync[(int)$a['user_id']] = $a;
$canFinalize = $now >= $monday->modify('+2 days');
foreach ($users as $u) {
    $a = $lastSync[(int)$u['id']] ?? null;
    if (!$a || !empty($a['sync_error']) || empty($a['last_sync']) || strtotime($a['last_sync'] . ' UTC') < time() - 86400) {
        $canFinalize = false;
    }
}

$select = $pdo->prepare('SELECT distance_m FROM activities WHERE user_id = ? AND activity_date >= ? AND activity_date < ? AND activity_date >= ? AND cache_updated_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY');
$existing = $pdo->prepare('SELECT finalized FROM challenge_weekly WHERE user_id=? AND iso_year=? AND iso_week=?');
$save = $pdo->prepare('INSERT INTO challenge_weekly(user_id,iso_year,iso_week,completed_loops,run_count,finalized,updated_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE completed_loops=IF(finalized=0,VALUES(completed_loops),completed_loops),run_count=IF(finalized=0,VALUES(run_count),run_count),finalized=IF(finalized=0,VALUES(finalized),finalized),updated_at=IF(finalized=0,UTC_TIMESTAMP(),updated_at)');

// Refresh only current and previous calendar week. Older archived results stay immutable.
foreach ([[$lastMonday, $monday], [$monday, $monday->modify('+7 days')]] as [$start, $end]) {
    $past = $end <= $monday;
    $year = (int)$start->format('o');
    $week = (int)$start->format('W');
    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $existing->execute([$uid, $year, $week]);
        if ((int)$existing->fetchColumn() === 1) continue;
        $select->execute([$uid, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s'), $u['start_date'].' 00:00:00']);
        $loops = 0; $runs = 0;
        foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $meters) {
            $runs++;
            $loops += (int)floor(max(0.0, (float)$meters) / 6706.0);
        }
        $save->execute([$uid, $year, $week, $loops, $runs, ($past && $canFinalize) ? 1 : 0]);
        echo $u['username'].' KW '.$week.'/'.$year.': '.$loops.' Loops'.(($past && $canFinalize) ? ' (final)' : '')."\n";
    }
}
if (!$canFinalize) echo "Vorwoche noch nicht final: warte auf Mittwoch UTC und erfolgreichen aktuellen Sync aller drei Läufer.\n";
