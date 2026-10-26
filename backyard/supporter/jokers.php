<?php
declare(strict_types=1);
require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/supporter.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/jokers.php';
$u=supporterRequire();$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try {
    verifyCsrf();
    if(($_POST['action']??'')!=='create')throw new RuntimeException('Unbekannte Aktion.');
    supporterLimit('joker-create:'.$u['id'],5,86400);
    jokerCreate((int)$u['id'],(int)($_POST['runner_id']??0),(string)($_POST['joker_type']??''));
    redirect('supporter/jokers.php?saved=1');
}catch(Throwable $e){$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Joker derzeit nicht einsetzbar.';}
$team=[];$jokers=[];$availability=[];$setupError='';
try{jokerRefreshAll();$team=supporterTeam();$jokers=jokerList(null,true);$availability=jokerAvailability((int)$u['id']);}
catch(PDOException $e){$setupError='Supporter-Joker sind noch nicht eingerichtet. Bitte die Datenbankmigration ausführen.';}
$active=array_values(array_filter($jokers,static fn($j)=>$j['status']==='active'));
$history=array_values(array_filter($jokers,static fn($j)=>$j['status']!=='active'));
supporterHead('Joker',$u);?>
<section class="mission-hero joker-hero"><p class="eyebrow">SUPPORTER · MONATS-JOKER</p><h2>Punkte unter Druck</h2><p>Einmal pro Kalendermonat kannst du einen Läufer herausfordern. Erfolg schützt dessen Gesamtpunktzahl; Scheitern kostet 3 Punkte.</p></section>
<?php if($error):?><p class="mission-error" role="alert"><?=h($error)?></p><?php endif;?><?php if(isset($_GET['saved'])):?><p class="mission-success" role="status">Joker eingesetzt. Die Herausforderung läuft.</p><?php endif;?>
<?php if($setupError):?><p class="mission-error"><?=h($setupError)?></p><?php else:?>
<section class="mission-create"><div class="joker-availability"><div><p class="eyebrow">DEIN JOKER · <?=h($availability['month']['label'])?></p><h2><?=$availability['available']?'Bereit zum Einsatz':'Diesen Monat eingesetzt'?></h2></div><strong class="joker-token<?=$availability['available']?' available':''?>"><?=$availability['available']?'1 / 1':'0 / 1'?></strong></div>
<?php if($availability['available']):?><form method="post" onsubmit="return confirm('Joker wirklich endgültig einsetzen?')"><?php supporterField();?><input type="hidden" name="action" value="create"><div class="mission-form-grid">
 <label>Läufer<select name="runner_id" required><?php foreach($team as $runner):?><option value="<?=h($runner['id'])?>"><?=h($runner['display_name'])?></option><?php endforeach;?></select></label>
 <label>Joker<select name="joker_type" required><option value="weekly_boost">Wochenziel um 20 % erhöhen</option><option value="run_30h">Lauf in 30 Stunden · mindestens 75 % Durchschnitt</option><option value="next_run_150">Nächster Lauf · mindestens 150 % Durchschnitt</option></select></label>
 </div><p class="mission-note">Der Wert wird beim Einsatz festgeschrieben. Der Wochenziel-Joker benötigt ein bestehendes persönliches Wochenziel. Ein eingesetzter Joker kann nicht zurückgenommen werden und endet spätestens mit dem Berliner Monatswechsel.</p><button class="btn" type="submit">Joker endgültig einsetzen</button></form><?php else:?><p class="mission-note">Am ersten Tag des nächsten Kalendermonats steht automatisch wieder genau ein neuer Joker bereit. Ungenutzte Joker werden nicht angespart.</p><?php endif;?></section>
<section class="mission-section"><h2>Aktive Joker</h2><div class="mission-list"><?php if(!$active):?><p class="mission-empty">Aktuell läuft kein Joker.</p><?php endif;?><?php foreach($active as $joker)require dirname(__DIR__).'/partials/joker-card.php';?></div></section>
<?php if($history):?><section class="mission-section"><h2>Joker-Verlauf</h2><div class="mission-list"><?php foreach($history as $joker)require dirname(__DIR__).'/partials/joker-card.php';?></div></section><?php endif;?>
<?php endif;?><?php supporterFoot();?>
