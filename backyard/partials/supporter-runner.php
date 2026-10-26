<?php
require_once __DIR__.'/private-path.php';
// Optional module: a pending supporter migration must never break the runner dashboard.
require_once lluPrivatePath('app/supporter.php');
require_once lluPrivatePath('app/push.php');
$supporterError=''; $supporterReady=false; $sharing=false;
$supporterAdmin=isSupporterAdminRunner((int)$u['id']);
try {
    $q=db()->prepare('SELECT runner_id FROM supporter_sharing WHERE runner_id=?'); $q->execute([$u['id']]); $sharing=(bool)$q->fetchColumn(); $supporterReady=true;
    if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='supporter_share') {
        verifyCsrf();
        if(!empty($_POST['approved'])) db()->prepare('INSERT INTO supporter_sharing(runner_id,approved_at) VALUES(?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE approved_at=UTC_TIMESTAMP()')->execute([$u['id']]);
        else db()->prepare('DELETE FROM supporter_sharing WHERE runner_id=?')->execute([$u['id']]);
        $sharing=!empty($_POST['approved']);
    }
} catch(Throwable $e) { $supporterError='Supporter-Freigabe derzeit nicht verfügbar. Trainingsfunktionen bleiben verfügbar.'; }
?>
<section class="panel" id="supporter-sharing"><h2>Supporter-Freigabe</h2><?php if($supporterError): ?><p><?=h($supporterError)?></p><?php endif; ?><?php if($supporterReady): ?>
<p>Freigegeben werden dein Anzeigename, aggregierte Kilometer, Laufanzahl, Loops, Wochenkilometer und Challenge-Ergebnisse. Keine Routen, Aktivitätsdetails oder Gesundheitswerte.</p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="supporter_share"><label><input type="checkbox" name="approved" value="1" <?=$sharing?'checked':''?>> Ich gebe meine Trainingssummen und Challenge-Ergebnisse für registrierte Supporter frei.</label><button type="submit">Freigabe speichern</button></form>
<p><a href="cheers.php">Anfeuerungen ansehen</a> · <a href="supporter/admin.php">Supporter verwalten</a><?php if($supporterAdmin): ?> · <a href="supporter/moderation.php">Anfeuerungen moderieren</a><?php endif; ?></p>
<?php endif; ?></section><?php if($supporterReady) pushPanel('runner'); ?>
