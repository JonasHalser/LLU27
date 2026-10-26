<?php
declare(strict_types=1);
require_once dirname(__DIR__,3).'/backyard_private/app/supporter.php';
require_once dirname(__DIR__,3).'/backyard_private/app/missions.php';
$u=supporterRequire();$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try {
    verifyCsrf();$action=(string)($_POST['action']??'');
    if($action==='create'){
        supporterLimit('mission-create:'.$u['id'],20,86400);
        missionCreate((int)$u['id'],missionInput($_POST));
    }elseif($action==='cancel')missionCancel((int)$u['id'],(int)($_POST['mission_id']??0));
    else throw new RuntimeException('Unbekannte Aktion.');
    redirect('supporter/missions.php?saved=1');
}catch(Throwable $e){$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Mission derzeit nicht speicherbar.';}
$missions=[];$missionBoard=[];$setupError='';
try{missionRefreshAll();$missions=missionList(null,true);$missionBoard=missionPointsBoard(true);}catch(PDOException $e){$setupError='Supporter-Missionen sind noch nicht eingerichtet. Bitte die Datenbankmigration ausführen.';}
$active=array_values(array_filter($missions,static fn($m)=>$m['open']));
$history=array_values(array_filter($missions,static fn($m)=>!$m['open']));
$defaultDeadline=(new DateTimeImmutable('+7 days',new DateTimeZone('Europe/Berlin')))->setTime(20,0)->format('Y-m-d\TH:i');
supporterHead('Missionen',$u);?>
<section class="mission-hero"><p class="eyebrow">SUPPORTER · AUFGABEN</p><h2>Missionen vergeben</h2><p>Fordere das Team heraus. Du legst Ziel, Belohnung, Annahmemodus und Frist fest.</p></section>
<?php if($error):?><p class="mission-error" role="alert"><?=h($error)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p class="mission-success" role="status">Mission gespeichert.</p><?php endif;?><?php if($setupError):?><p class="mission-error"><?=h($setupError)?></p><?php else:?>
<section class="mission-create"><h2>Neue Mission</h2><form method="post"><?php supporterField();?><input type="hidden" name="action" value="create"><div class="mission-form-grid">
 <label class="wide">Titel<input name="title" maxlength="120" required placeholder="Zum Beispiel: Zwei schnelle Loops"></label>
 <label class="wide">Beschreibung (optional)<textarea name="description" maxlength="500" placeholder="Motivation, Regeln oder ein persönlicher Gruß"></textarea></label>
 <label>Missionsart<select name="unit" required><option value="km">Kilometer</option><option value="minutes">Laufzeit in Minuten</option><option value="loops">Vollständige Loops</option></select></label>
 <label>Zielwert<input type="number" name="target_value" min="0.1" max="10000" step="0.1" required placeholder="z. B. 20"></label>
 <label>Punkte<select name="points" required><?php foreach(range(1,5) as $points):?><option value="<?=$points?>"><?=$points?> <?=$points===1?'Punkt':'Punkte'?></option><?php endforeach;?></select></label>
 <label>Wer darf annehmen?<select name="acceptance_mode" required><option value="exclusive">Nur einer – der erste blockiert</option><option value="all">Alle Läufer separat</option></select></label>
 <label class="wide">Enddatum und Endzeit (Berlin)<input type="datetime-local" name="deadline_at" required value="<?=h($defaultDeadline)?>"></label>
 </div><p class="mission-note">Gezählt werden synchronisierte Laufaktivitäten, die nach der jeweiligen Annahme beginnen und anhand ihrer Bewegungszeit bis zur Frist abgeschlossen sind. Eine angenommene Mission kann nicht zurückgegeben werden.</p><button class="btn" type="submit">Mission veröffentlichen</button></form></section>
<section aria-labelledby="supporter-mission-score"><h2 id="supporter-mission-score">Missionspunkte</h2><div class="mission-board"><?php foreach($missionBoard as $place=>$runner):?><article class="mission-score"><span>#<?=h($place+1)?> · <?=h($runner['display_name'])?></span><strong><?=h($runner['points'])?> Punkte</strong><span><?=h($runner['completed'])?> Missionen geschafft</span></article><?php endforeach;?></div></section>
<section class="mission-section"><h2>Aktive Missionen</h2><div class="mission-list"><?php if(!$active):?><p class="mission-empty">Noch keine aktiven Missionen.</p><?php endif;?><?php foreach($active as $mission):$missionAudience='supporter';require dirname(__DIR__).'/partials/mission-card.php';endforeach;?></div></section>
<?php if($history):?><section class="mission-section"><h2>Verlauf</h2><div class="mission-list"><?php foreach($history as $mission):$missionAudience='supporter';require dirname(__DIR__).'/partials/mission-card.php';endforeach;?></div></section><?php endif;?>
<?php endif;?><?php supporterFoot();?>
