<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backyard_private/app/bootstrap.php';
require_once dirname(__DIR__,2).'/backyard_private/app/weekly_goals.php';
$u=requireUser();
$goalPeriod=weeklyGoalPeriod();
$err='';

if($_SERVER['REQUEST_METHOD']==='POST') try {
    verifyCsrf();
    $action=(string)($_POST['action']??'');
    if($action==='weekly_goal_save') {
        weeklyGoalSave((int)$u['id'],$goalPeriod,weeklyGoalInput((string)($_POST['goal_unit']??''),(string)($_POST['goal_value']??'')));
    } elseif($action==='weekly_goal_delete') {
        weeklyGoalDelete((int)$u['id'],$goalPeriod);
    } elseif($action==='team_goal_save') {
        teamWeeklyGoalSave((int)$u['id'],$goalPeriod,weeklyGoalInput((string)($_POST['goal_unit']??''),(string)($_POST['goal_value']??'')));
    } elseif($action==='team_goal_delete') {
        teamWeeklyGoalDelete($goalPeriod);
    } else {
        throw new RuntimeException('Unbekannte Aktion.');
    }
    redirect('weekly-goals.php#weekly-goals');
} catch(Throwable $e) {
    $err=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Aktion derzeit nicht möglich.';
}

$weeklyGoals=[];$weeklyGoalStreaks=[];$teamWeeklyGoal=null;$weeklyGoalError='';
try {
    $weeklyGoals=weeklyGoalTeam($goalPeriod);
    $weeklyGoalStreaks=weeklyGoalStreaks($goalPeriod);
    $teamWeeklyGoal=teamWeeklyGoal($goalPeriod,$weeklyGoals);
} catch(PDOException $e) {
    $weeklyGoalError='Wochenziele sind noch nicht eingerichtet. Bitte die Datenbankmigration ausführen.';
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wochenziele · LLU 27</title><link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=weekly-goals-2"><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script></head><body class="llu-layout">
<?php require __DIR__.'/partials/navigation.php'; ?>
<main class="shell"><header style="margin:30px 0 22px"><p class="eyebrow">LLU 27 · TRAININGSPLANUNG</p><h1 style="font-size:clamp(32px,5vw,54px);margin:7px 0">Wochenziele</h1><p class="muted">Konfiguriere dein persönliches Ziel und das gemeinsame Teamziel.</p></header>
<?php if($err):?><p class="error"><?=h($err)?></p><?php endif;?>
<?php $weeklyGoalConfigure=true; require __DIR__.'/partials/weekly-goals.php'; ?>
</main></body></html>
