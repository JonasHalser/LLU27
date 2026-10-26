<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter.php');
$u=supporterRequire();$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try {
    verifyCsrf();
    if(($_POST['action']??'')!=='cheer')throw new RuntimeException('Unbekannte Aktion.');
    supporterCheer((int)$u['id'],(int)($_POST['runner']??0),(string)($_POST['message']??''));
    redirect('supporter/cheers.php?saved=1');
}catch(Throwable $e){$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Anfeuerung derzeit nicht möglich.';}
$team=supporterTeam();
$cheers=db()->query("SELECT c.message,c.created_at,COALESCE(s.display_name,'Ehemaliger Supporter') sender,CONCAT(UPPER(LEFT(r.username,1)),SUBSTRING(r.username,2)) runner FROM supporter_cheers c LEFT JOIN supporters s ON s.id=c.supporter_id JOIN users r ON r.id=c.runner_id ORDER BY c.id DESC LIMIT 30")->fetchAll();
supporterHead('Anfeuern',$u); ?>
<?php if($error):?><p class="admin-error" role="alert"><?=h($error)?></p><?php endif;?>
<?php if(isset($_GET['saved'])):?><p class="admin-notice" role="status">Deine Anfeuerung wurde gesendet.</p><?php endif;?>
<section class="panel"><h2>Team anfeuern</h2><p>Schick den Läufern eine Nachricht. Sie ist für alle Supporter sichtbar.</p>
<form method="post"><?php supporterField();?><input type="hidden" name="action" value="cheer"><label>Für wen?<select name="runner" required><?php foreach($team as $runner):?><option value="<?=h($runner['id'])?>"><?=h($runner['display_name'])?></option><?php endforeach;?></select></label><label>Deine Nachricht<input name="message" required maxlength="240" list="motivation" placeholder="ONE MORE LOOP!"></label><datalist id="motivation"><?php foreach(['ONE MORE LOOP!','DU PACKST DAS!','STARKER LAUF!','WEITER SO!','NEVER GIVE UP!'] as $message):?><option value="<?=h($message)?>"><?php endforeach;?></datalist><small>Maximal 240 Zeichen. 30 Sekunden Pause zwischen Nachrichten.</small><button class="btn" type="submit">Anfeuerung senden</button></form>
</section>
<section class="panel"><h2>Letzte Anfeuerungen</h2><?php if(!$cheers):?><p>Noch keine Anfeuerungen.</p><?php endif;?><?php foreach($cheers as $cheer):?><article class="cheer"><strong><?=h($cheer['sender'])?> → <?=h($cheer['runner'])?></strong><p><?=h($cheer['message'])?></p><small><?=h($cheer['created_at'])?> UTC</small></article><?php endforeach;?></section>
<?php supporterFoot(); ?>
