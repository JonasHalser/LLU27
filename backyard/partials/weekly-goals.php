<?php
$weeklyGoalConfigure=$weeklyGoalConfigure??false;
$ownGoal=null;
foreach($weeklyGoals as $goalRow) if((int)$goalRow['id']===(int)$u['id']) {$ownGoal=$goalRow;break;}
?>
<section class="panel weekly-goal-panel" id="weekly-goals" aria-labelledby="weekly-goal-heading">
 <div class="weekly-goal-head"><div><p class="eyebrow">PERSÖNLICHE ZIELE · <?=h($goalPeriod['label'])?></p><h2 id="weekly-goal-heading">Wochenziele im Team</h2></div><p class="muted">Montag 00:00 bis Sonntag 23:59 UTC</p></div>
 <?php if($weeklyGoalError):?><p class="error"><?=h($weeklyGoalError)?></p><?php else:?>
 <div class="team-goal"><div class="team-goal-top"><div><p class="eyebrow">GEMEINSAME MISSION</p><h3>Teamziel</h3><p class="muted"><?=$weeklyGoalConfigure?'Jeder Läufer kann das gemeinsame Ziel anpassen.':'Gemeinsamer Fortschritt aller drei Läufer.'?></p></div>
 <?php if($teamWeeklyGoal): $teamUnit=(string)$teamWeeklyGoal['unit']; $teamTarget=(float)$teamWeeklyGoal['target_value'];?>
  <div class="team-goal-progress"><strong><?=h(weeklyGoalValue((float)$teamWeeklyGoal['progress'],$teamUnit))?> / <?=h(weeklyGoalValue($teamTarget,$teamUnit))?></strong><progress max="<?=h((string)$teamTarget)?>" value="<?=h((string)min((float)$teamWeeklyGoal['progress'],$teamTarget))?>"><?=h((string)round((float)$teamWeeklyGoal['percent']))?> %</progress><p><?=h((string)round((float)$teamWeeklyGoal['percent']))?> % erreicht<?=$teamWeeklyGoal['achieved']?' · Gemeinsam geschafft!':''?></p><?php if($teamWeeklyGoal['updated_by_name']):?><small>Zuletzt gesetzt von <?=h($teamWeeklyGoal['updated_by_name'])?></small><?php endif;?></div>
 <?php else:?><p class="weekly-goal-empty">Für diese Woche wurde noch kein Teamziel gesetzt.</p><?php endif;?></div>
 <?php if($weeklyGoalConfigure):?>
 <form method="post" class="weekly-goal-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="team_goal_save"><label>Teamziel<input type="number" name="goal_value" min="0.01" max="10080" step="0.01" required value="<?=h($teamWeeklyGoal?(string)(float)$teamWeeklyGoal['target_value']:'')?>" placeholder="z. B. 150"></label><label>Einheit<select name="goal_unit" required><?php foreach(['km'=>'Kilometer','minutes'=>'Minuten','hours'=>'Stunden','loops'=>'Loops'] as $unit=>$label):?><option value="<?=h($unit)?>" <?=($teamWeeklyGoal['unit']??'km')===$unit?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label><button class="btn" type="submit">Teamziel speichern</button></form>
 <?php if($teamWeeklyGoal):?><form method="post" class="weekly-goal-remove" onsubmit="return confirm('Gemeinsames Teamziel wirklich löschen?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="team_goal_delete"><button class="outline" type="submit">Teamziel löschen</button></form><?php endif;?>
 <?php endif;?></div>
 <div class="weekly-goal-grid">
 <?php foreach($weeklyGoals as $goal): $goalUnit=(string)($goal['unit']??''); $goalTarget=(float)($goal['target_value']??0); $goalClass='team-'.strtolower((string)$goal['username']); $streak=$weeklyGoalStreaks[$goal['username']]??['current'=>0,'best'=>0];?>
  <article class="weekly-goal-card <?=h($goalClass)?>"><h3><?=h($goal['display_name'])?></h3>
  <?php if($goalUnit!==''&&$goalTarget>0):?>
   <strong><?=h(weeklyGoalValue((float)$goal['progress'],$goalUnit))?> / <?=h(weeklyGoalValue($goalTarget,$goalUnit))?></strong>
   <progress max="<?=h((string)$goalTarget)?>" value="<?=h((string)min((float)$goal['progress'],$goalTarget))?>"><?=h((string)round((float)$goal['percent']))?> %</progress>
   <p><?=h((string)round((float)$goal['percent']))?> % erreicht<?=$goal['progress']>=$goalTarget?' · Ziel geschafft!':''?></p>
  <?php else:?><p class="weekly-goal-empty">Noch kein Ziel für diese Woche.</p><?php endif;?>
   <p class="goal-streak">🔥 <?=h((string)$streak['current'])?> <?=$streak['current']===1?'Woche':'Wochen'?> in Folge <span class="muted">· Bestserie <?=h((string)$streak['best'])?></span></p>
  </article>
 <?php endforeach;?>
 </div>
 <?php if($weeklyGoalConfigure):?>
 <?php if(!empty($weeklyGoalLocked)):?><p class="error">Ein Supporter-Joker hat dein Wochenziel um 20 % erhöht. Das Ziel ist deshalb bis zum Ende dieser Woche gesperrt.</p><?php else:?>
 <form method="post" class="weekly-goal-form"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="weekly_goal_save">
  <label>Mein Ziel<input type="number" name="goal_value" min="0.01" max="10080" step="0.01" required value="<?=h($ownGoal?(string)(float)$ownGoal['target_value']:'')?>" placeholder="z. B. 50"></label>
  <label>Einheit<select name="goal_unit" required><?php foreach(['km'=>'Kilometer','minutes'=>'Minuten','hours'=>'Stunden','loops'=>'Loops'] as $unit=>$label):?><option value="<?=h($unit)?>" <?=($ownGoal['unit']??'km')===$unit?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label>
  <button class="btn" type="submit">Wochenziel speichern</button>
 </form>
 <?php if($ownGoal):?><form method="post" class="weekly-goal-remove" onsubmit="return confirm('Wochenziel für diese Woche wirklich löschen?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="weekly_goal_delete"><button class="outline" type="submit">Mein Ziel löschen</button></form><?php endif;?>
 <?php endif;?>
 <?php endif;?>
 <p class="team-disclaimer">Der Fortschritt basiert auf den aktuell synchronisierten Strava-Läufen im siebentägigen Cache. Ein Loop zählt nur, wenn 6,706 km innerhalb eines einzelnen Laufs vollständig erreicht wurden.</p>
 <?php endif;?>
</section>
