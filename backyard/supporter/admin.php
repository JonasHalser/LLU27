<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter_admin.php');
$u=requireUser();

$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try {
    verifyCsrf();
    if(($_POST['action']??'')!=='delete') throw new RuntimeException('Unbekannte Aktion.');
    $id=(int)($_POST['supporter_id']??0);
    $q=db()->prepare('SELECT username FROM supporters WHERE id=?');$q->execute([$id]);$username=$q->fetchColumn();
    if($username===false) throw new RuntimeException('Supporter-Konto nicht gefunden.');
    supporterAdminDelete($id);
    redirect('supporter/admin.php?deleted='.rawurlencode((string)$username));
}catch(Throwable $e) {
    $error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Das Supporter-Konto konnte nicht gelöscht werden.';
}

$supporters=[];$setupError='';
try {
    $supporters=db()->query("SELECT s.id,s.username,s.display_name,s.created_at,
        (SELECT COUNT(*) FROM supporter_cheers c WHERE c.supporter_id=s.id) cheer_count,
        (SELECT COUNT(*) FROM supporter_tips t WHERE t.supporter_id=s.id) tip_count,
        (SELECT COUNT(*) FROM supporter_missions m WHERE m.supporter_id=s.id) mission_count,
        (SELECT COUNT(*) FROM supporter_jokers j WHERE j.supporter_id=s.id) joker_count
        FROM supporters s ORDER BY s.created_at DESC,s.id DESC")->fetchAll();
}catch(PDOException $e) {
    $setupError='Supporter-Datenbanktabellen sind noch nicht vollständig eingerichtet.';
}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="../"><title>Supporter-Verwaltung · LLU 27</title><link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css"><link rel="stylesheet" href="assets/missions.css"><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script>
<style>
.admin-head{margin:28px 0 20px;padding:clamp(22px,4vw,36px);border:1px solid #405267;border-radius:20px;background:linear-gradient(135deg,#1b2b3d,#111b29)}.admin-head h1{font-size:clamp(30px,5vw,48px);margin:8px 0}.admin-head p{color:#b4c3d2;max-width:740px;line-height:1.6}.admin-list{display:grid;gap:12px}.admin-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;align-items:center;border:1px solid #35475b;border-radius:15px;background:#172537;padding:18px}.admin-row h2{font-size:18px;margin:0 0 4px}.admin-row p{margin:5px 0;color:#aebdcc;font-size:12px;line-height:1.55}.admin-meta{color:#8194a7!important}.admin-delete{border:1px solid #944e4b;border-radius:10px;background:#3a2227;color:#ffd6d2;padding:10px 14px;font:inherit;font-weight:800;cursor:pointer}.admin-delete:hover{background:#512a30}.admin-notice{border:1px solid #34745d;border-radius:12px;background:#17382d;color:#bff4da;padding:13px;margin:12px 0}.admin-error{border:1px solid #b85e54;border-radius:12px;background:#4a2427;color:#ffe1de;padding:13px;margin:12px 0}.admin-explainer{margin:20px 0;padding:16px;border:1px solid #3b4c60;border-radius:12px;color:#b4c3d2;font-size:12px;line-height:1.6}@media(max-width:620px){.admin-row{grid-template-columns:1fr}.admin-delete{justify-self:start}}
</style></head><body class="llu-layout"><?php require __DIR__.'/../partials/navigation.php';?><main class="shell">
<header class="admin-head"><p class="eyebrow">SUPPORTER ZONE</p><h1>Supporter verwalten</h1><p>Hier kannst du registrierte Supporter-Konten prüfen und dauerhaft entfernen. Die Verwaltung steht allen angemeldeten Läufern offen.</p></header>
<?php if(isset($_GET['deleted'])):?><p class="admin-notice" role="status">Das Konto <?=h((string)$_GET['deleted'])?> wurde gelöscht.</p><?php endif;?><?php if($error):?><p class="admin-error" role="alert"><?=h($error)?></p><?php endif;?><?php if($setupError):?><p class="admin-error" role="alert"><?=h($setupError)?></p><?php else:?>
<section class="panel"><h2>Supporter-Konten (<?=h((string)count($supporters))?>)</h2><?php if(!$supporters):?><p class="muted">Es sind noch keine Supporter-Konten registriert.</p><?php else:?><div class="admin-list"><?php foreach($supporters as $supporter):?><article class="admin-row"><div><h2><?=h($supporter['display_name'])?></h2><p>@<?=h($supporter['username'])?> · registriert <?=h(date('d.m.Y',strtotime((string)$supporter['created_at'])))?></p><p>Nachrichten: <?=h((string)$supporter['cheer_count'])?> · Tipps: <?=h((string)$supporter['tip_count'])?> · Missionen: <?=h((string)$supporter['mission_count'])?> · Joker: <?=h((string)$supporter['joker_count'])?></p></div><form method="post" action="supporter/admin.php" onsubmit="return confirm('Supporter-Konto @<?=h($supporter['username'])?> dauerhaft löschen? Eigene Nachrichten, Tipps, Anmeldetokens und Push-Abos werden entfernt.')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="supporter_id" value="<?=h((string)$supporter['id'])?>"><button class="admin-delete" type="submit">Konto löschen</button></form></article><?php endforeach;?></div><?php endif;?></section>
<p class="admin-explainer">Beim Löschen werden Anfeuerungen dieses Kontos, Tipps, Anmeldetokens und Push-Abonnements entfernt. Zugehörige Missionen und Joker bleiben als Teamverlauf erhalten; der Supporter wird dort als ehemalig angezeigt. Läuferkonten und deren Trainingsdaten bleiben unberührt.</p><p><a href="supporter/moderation.php">Anfeuerungen moderieren</a></p>
<?php endif;?></main></body></html>
