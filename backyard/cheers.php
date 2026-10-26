<?php
declare(strict_types=1);
require_once __DIR__.'/partials/private-path.php';
require_once lluPrivatePath('app/bootstrap.php');
$u=requireUser();
$error='';$messages=[];
$supporterAdmin=isSupporterAdminRunner((int)$u['id']);

if($_SERVER['REQUEST_METHOD']==='POST') try {
    verifyCsrf();
    if(($_POST['action']??'')!=='supporter_delete' || !$supporterAdmin) throw new RuntimeException('Diese Aktion ist nicht erlaubt.');
    db()->prepare('DELETE FROM supporter_cheers WHERE id=? AND runner_id=?')->execute([(int)($_POST['cheer_id']??0),(int)$u['id']]);
    redirect('cheers.php?deleted=1');
} catch(Throwable $e) {
    $error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Anfeuerungen sind derzeit nicht verfügbar.';
}

try {
    $q=db()->prepare("SELECT c.id,c.message,c.created_at,COALESCE(s.display_name,'Ehemaliger Supporter') sender FROM supporter_cheers c LEFT JOIN supporters s ON s.id=c.supporter_id WHERE c.runner_id=? ORDER BY c.id DESC LIMIT 50");
    $q->execute([$u['id']]);$messages=$q->fetchAll();
} catch(PDOException $e) {
    $error='Der Anfeuern-Tab ist noch nicht eingerichtet. Bitte die Supporter-Datenbankmigrationen ausführen.';
}

?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Anfeuern · LLU 27</title><link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=cheers-1"><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script>
<style>.cheers-list{display:grid;gap:12px}.cheer-card{padding:15px;border:1px solid #35485d;border-radius:13px;background:#142131}.cheer-card header{display:flex;justify-content:space-between;gap:12px;align-items:baseline}.cheer-card time,.cheers-empty{color:#aebdcc;font-size:13px}.cheer-card p{white-space:pre-wrap;overflow-wrap:anywhere}.cheer-card form{margin-top:10px}@media(max-width:600px){.cheer-card header{align-items:flex-start;flex-direction:column}}</style></head><body class="llu-layout">
<?php require __DIR__.'/partials/navigation.php'; ?>
<main class="shell"><header style="margin:30px 0 22px"><p class="eyebrow">LLU 27 · SUPPORTER</p><h1 style="font-size:clamp(32px,5vw,54px);margin:7px 0">Anfeuern</h1><p class="muted">Hier findest du die Nachrichten deiner Supporter.</p></header>
<?php if(isset($_GET['deleted'])):?><p class="notice" role="status">Anfeuerung gelöscht.</p><?php endif;?>
<?php if($error):?><p class="error" role="alert"><?=h($error)?></p><?php endif;?>
<section class="panel"><h2>Deine Anfeuerungen</h2>
<?php if(!$messages&&!$error):?><p class="cheers-empty">Noch keine Anfeuerungen. Teile den Link zur <a href="supporter/register.php">Supporter-Registrierung</a>.</p><?php endif;?>
<div class="cheers-list"><?php foreach($messages as $message):?><article class="cheer-card"><header><strong><?=h($message['sender'])?></strong><time datetime="<?=h(gmdate('c',strtotime((string)$message['created_at'].' UTC')))?>"><?=h($message['created_at'])?> UTC</time></header><p><?=h($message['message'])?></p><?php if($supporterAdmin):?><form method="post" onsubmit="return confirm('Diese Anfeuerung löschen?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="supporter_delete"><input type="hidden" name="cheer_id" value="<?=h($message['id'])?>"><button class="outline danger" type="submit">Anfeuerung löschen</button></form><?php endif;?></article><?php endforeach;?></div>
</section></main></body></html>
