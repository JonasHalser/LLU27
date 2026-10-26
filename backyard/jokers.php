<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backyard_private/app/bootstrap.php';
require_once dirname(__DIR__,2).'/backyard_private/app/jokers.php';
$u=requireUser();$jokers=[];$setupError='';
try{jokerRefreshAll();$jokers=jokerList((int)$u['id']);}
catch(PDOException $e){$setupError='Supporter-Joker sind noch nicht eingerichtet. Bitte die Datenbankmigration ausführen.';}
$mine=array_values(array_filter($jokers,static fn($j)=>$j['is_own']));
$team=array_values(array_filter($jokers,static fn($j)=>!$j['is_own']));
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Joker · LLU 27</title><link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=jokers-1"><link rel="stylesheet" href="assets/missions.css?v=jokers-1"><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script></head><body class="llu-layout">
<?php require __DIR__.'/partials/navigation.php';?>
<main class="shell"><header class="mission-hero joker-hero"><p class="eyebrow">LLU 27 · SUPPORTER-JOKER</p><h1>Verteidige deine Punkte</h1><p>Supporter dürfen einmal pro Monat einen Joker einsetzen. Du musst ihn nicht annehmen: Er gilt sofort. Erfolg schützt deine Punktzahl, Scheitern kostet 3 Missionspunkte.</p></header>
<?php if($setupError):?><p class="mission-error"><?=h($setupError)?></p><?php else:?>
<section class="mission-section"><h2>Meine Joker</h2><div class="mission-list"><?php if(!$mine):?><p class="mission-empty">Gegen dich wurde bisher kein Joker eingesetzt.</p><?php endif;?><?php foreach($mine as $joker)require __DIR__.'/partials/joker-card.php';?></div></section>
<?php if($team):?><section class="mission-section"><h2>Joker im Team</h2><div class="mission-list"><?php foreach($team as $joker)require __DIR__.'/partials/joker-card.php';?></div></section><?php endif;?>
<?php endif;?></main></body></html>
