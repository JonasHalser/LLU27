<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter.php');
$u=supporterRequire();
if($_SERVER['REQUEST_METHOD']==='POST') { verifyCsrf(); supporterRevoke((int)$u['id']); redirect('supporter/login.php'); }
supporterHead('Abmelden',$u); ?><section class="panel"><p>Alle deine Supporter-Sitzungen und dauerhaften Anmeldungen werden widerrufen.</p><form method="post"><?php supporterField(); ?><button class="btn">Auf allen Geräten abmelden</button></form></section><?php supporterFoot(); ?>
