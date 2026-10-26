<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backyard_private/app/bootstrap.php';
require_once dirname(__DIR__,2).'/backyard_private/app/missions.php';
require_once dirname(__DIR__,2).'/backyard_private/app/jokers.php';
$u=requireUser();$error='';$missionSetupError='';
if($_SERVER['REQUEST_METHOD']==='POST')try {
    verifyCsrf();
    if(($_POST['action']??'')!=='accept')throw new RuntimeException('Unbekannte Aktion.');
    missionAccept((int)($_POST['mission_id']??0),(int)$u['id']);
    redirect('missions.php?accepted=1');
}catch(Throwable $e){$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Mission derzeit nicht verfügbar.';}
$missions=[];$missionBoard=[];
try{missionRefreshRunner((int)$u['id']);jokerRefreshAll();$missions=missionList((int)$u['id']);$missionBoard=missionPointsBoard();}
catch(PDOException $e){$missionSetupError='Supporter-Missionen sind noch nicht eingerichtet. Bitte die Datenbankmigration ausführen.';}
$mine=array_values(array_filter($missions,static fn($m)=>$m['own_entry']!==null));
$available=array_values(array_filter($missions,static fn($m)=>(bool)$m['can_accept']));
$other=array_values(array_filter($missions,static fn($m)=>$m['own_entry']===null&&!$m['can_accept']));
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Missionen · LLU 27</title><link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=missions-1"><link rel="stylesheet" href="assets/missions.css?v=1"><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script></head><body class="llu-layout">
<?php require __DIR__.'/partials/navigation.php';?>
<main class="shell"><header class="mission-hero"><p class="eyebrow">LLU 27 · SUPPORTER MISSIONS</p><h1>Deine Missionen</h1><p>Entscheide selbst, welche Aufgabe du annimmst. Danach zählen deine synchronisierten Strava-Läufe bis zur angegebenen Frist.</p></header>
<?php if($error):?><p class="mission-error" role="alert"><?=h($error)?></p><?php endif;?><?php if(isset($_GET['accepted'])):?><p class="mission-success" role="status">Mission angenommen. Viel Erfolg!</p><?php endif;?>
<?php if($missionSetupError):?><p class="mission-error"><?=h($missionSetupError)?></p><?php else:?>
<section aria-labelledby="mission-score-heading"><h2 id="mission-score-heading">Missionspunkte</h2><div class="mission-board"><?php foreach($missionBoard as $place=>$runner):?><article class="mission-score"><span>#<?=h($place+1)?> · <?=h($runner['display_name'])?></span><strong><?=h($runner['points'])?> Punkte</strong><span><?=h($runner['completed'])?> Missionen geschafft<?=$runner['failed_jokers']?' · −'.h($runner['penalties']).' durch Joker':''?></span></article><?php endforeach;?></div></section>
<section class="mission-section"><h2>Meine Missionen</h2><p class="muted">Bereits angenommene Aufgaben und dein Fortschritt.</p><div class="mission-list"><?php if(!$mine):?><p class="mission-empty">Du hast aktuell keine Mission angenommen.</p><?php endif;?><?php foreach($mine as $mission):$missionAudience='runner';require __DIR__.'/partials/mission-card.php';endforeach;?></div></section>
<section class="mission-section"><h2>Verfügbare Missionen</h2><p class="muted">Die Teilnahme ist freiwillig. Exklusive Missionen gehören dem ersten Läufer, der sie annimmt.</p><div class="mission-list"><?php if(!$available):?><p class="mission-empty">Aktuell wartet keine Mission auf dich.</p><?php endif;?><?php foreach($available as $mission):$missionAudience='runner';require __DIR__.'/partials/mission-card.php';endforeach;?></div></section>
<?php if($other):?><section class="mission-section"><h2>Team und Verlauf</h2><div class="mission-list"><?php foreach($other as $mission):$missionAudience='runner';require __DIR__.'/partials/mission-card.php';endforeach;?></div></section><?php endif;?>
<?php endif;?></main></body></html>
