<?php
$missionAudience=$missionAudience??'runner';
$missionComplete=false;
foreach($mission['entries'] as $missionEntryCheck)if($missionEntryCheck['completed_at']!==null)$missionComplete=true;
$missionClass=$mission['cancelled_at']!==null?' is-cancelled':($missionComplete?' is-complete':'');
$missionPointsLabel=(int)$mission['points']===1?'Punkt':'Punkte';
$missionState=$mission['cancelled_at']!==null?'Abgesagt':($mission['expired']?'Frist abgelaufen':(($mission['acceptance_mode']==='exclusive'&&$mission['entries'])?($missionComplete?'Erfüllt':'Exklusiv vergeben'):'Offen bis '.missionDeadline($mission['deadline_at'])));
?>
<article class="mission-card<?=$missionClass?>">
 <div class="mission-card-head"><div><p class="eyebrow"><?=h($mission['supporter_name'])?> · MISSION #<?=h($mission['id'])?></p><h3><?=h($mission['title'])?></h3></div><span class="mission-reward">+<?=h($mission['points'])?> <?=$missionPointsLabel?></span></div>
 <div class="mission-badges"><span class="mission-badge"><?=h(missionModeLabel($mission['acceptance_mode']))?></span><span class="mission-badge <?=($mission['open']?'open':'closed')?>"><?=h($missionState)?></span></div>
 <?php if($mission['description']!==''):?><p class="mission-description"><?=h($mission['description'])?></p><?php endif;?>
 <p class="mission-target">Ziel: <?=h(missionValue((float)$mission['target_value'],$mission['unit']))?></p>
 <?php if($mission['entries']):?><div class="mission-entries">
  <?php foreach($mission['entries'] as $entry): $progress=(float)$entry['progress_value'];$target=(float)$mission['target_value'];$percent=min(100,max(0,$progress/$target*100));?>
   <div class="mission-entry<?=$entry['completed_at']!==null?' is-complete':''?>"><div class="mission-entry-head"><strong><?=h($entry['display_name'])?></strong><span><?=$entry['completed_at']!==null?'Geschafft · +'.h($mission['points']).' '.$missionPointsLabel:h(missionValue($progress,$mission['unit'])).' / '.h(missionValue($target,$mission['unit']))?></span></div><progress max="100" value="<?=h(round($percent,2))?>"><?=h(round($percent))?> %</progress><small>Angenommen am <?=h(missionDeadline($entry['accepted_at']))?><?=$entry['completed_at']!==null?' · bestätigt am '.h(missionDeadline($entry['completed_at'])):''?></small></div>
  <?php endforeach;?>
 </div><?php else:?><p class="mission-note">Noch von keinem Läufer angenommen.</p><?php endif;?>
 <?php if($missionAudience==='runner'&&$mission['can_accept']):?><div class="mission-actions"><form method="post" onsubmit="return confirm('Mission verbindlich annehmen? Sie kann danach nicht zurückgegeben werden.')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="accept"><input type="hidden" name="mission_id" value="<?=h($mission['id'])?>"><button class="btn" type="submit">Mission verbindlich annehmen</button></form><p class="mission-note">Nach der Annahme zählen neu gestartete, synchronisierte Läufe bis zur Frist. Eine angenommene Mission kann nicht zurückgegeben werden.</p></div>
 <?php elseif($missionAudience==='runner'&&$mission['acceptance_mode']==='exclusive'&&$mission['open']&&!$mission['own_entry']&&$mission['entries']):?><p class="mission-note">Diese exklusive Mission ist bereits blockiert.</p>
 <?php elseif($missionAudience==='supporter'&&(int)($mission['supporter_id']??0)===(int)$u['id']&&$mission['open']&&!$missionComplete):?><div class="mission-actions"><form method="post" onsubmit="return confirm('Mission wirklich absagen?')"><?php supporterField();?><input type="hidden" name="action" value="cancel"><input type="hidden" name="mission_id" value="<?=h($mission['id'])?>"><button class="btn outline" type="submit">Mission absagen</button></form></div><?php endif;?>
</article>
