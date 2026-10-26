<?php
$jokerStatus=(string)$joker['status'];
$jokerType=(string)$joker['joker_type'];
?>
<article class="joker-card joker-<?=h($jokerStatus)?><?=$joker['is_own']?' is-own':''?>">
 <div class="mission-card-head"><div><p class="eyebrow"><?=h($joker['supporter_name'])?> · JOKER FÜR <?=h(strtoupper((string)$joker['runner_name']))?></p><h3><?=h(jokerTypeLabel($jokerType))?></h3></div><span class="joker-status"><?=h(jokerStatusLabel($jokerStatus))?></span></div>
 <?php if($jokerType==='weekly_boost'):?>
  <p class="mission-target">Wochenziel: <?=h(jokerValue((float)$joker['baseline_value'],(string)$joker['unit']))?> → <?=h(jokerValue((float)$joker['target_value'],(string)$joker['unit']))?></p>
  <p class="muted">Das persönliche Ziel der <?=h(sprintf('KW %d / %d',(int)$joker['iso_week'],(int)$joker['iso_year']))?> wurde um 20 % erhöht.</p>
 <?php elseif($jokerType==='run_30h'):?>
  <p class="mission-target">Mindestens <?=h(jokerValue((float)$joker['target_value'],'meters'))?> innerhalb von 30 Stunden</p>
  <p class="muted">Berechnet aus 75 % der bisherigen Durchschnittsdistanz von <?=h(jokerValue((float)$joker['baseline_value'],'meters'))?>.</p>
 <?php else:?>
  <p class="mission-target">Der nächste Lauf muss mindestens <?=h(jokerValue((float)$joker['target_value'],'meters'))?> lang sein</p>
  <p class="muted">Berechnet aus 150 % der bisherigen Durchschnittsdistanz von <?=h(jokerValue((float)$joker['baseline_value'],'meters'))?>.</p>
 <?php endif;?>
 <div class="mission-badges"><span class="mission-badge">Eingesetzt: <?=h(jokerDate((string)$joker['issued_at']))?></span><?php if($joker['deadline_at']!==null):?><span class="mission-badge<?=$jokerStatus==='active'?' open':' closed'?>">Frist: <?=h(jokerDate((string)$joker['deadline_at']))?></span><?php endif;?></div>
 <?php if($joker['activity_id']!==null):?><p class="joker-result">Gewerteter Lauf: <?=h(jokerValue((float)$joker['activity_distance_m'],'meters'))?></p><?php endif;?>
 <?php if($jokerStatus==='active'):?><p class="mission-note">Bei Erfolg werden keine Punkte vergeben. Bei Nichterfüllung werden 3 Missionspunkte abgezogen.</p><?php elseif($jokerStatus==='succeeded'):?><p class="joker-success">Punkte erfolgreich verteidigt · keine Belohnung, kein Abzug.</p><?php else:?><p class="joker-failure">Joker nicht geschafft · 3 Missionspunkte wurden abgezogen.</p><?php endif;?>
</article>
