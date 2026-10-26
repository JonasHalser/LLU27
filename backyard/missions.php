<?php
declare(strict_types=1);
require_once __DIR__.'/partials/private-path.php';

require_once lluPrivatePath('app/bootstrap.php');
$u=requireUser();
if($_SERVER['REQUEST_METHOD']==='POST') {
    require_once lluPrivatePath('app/missions.php');
    try {
        verifyCsrf();
        if(($_POST['action']??'')!=='accept')throw new RuntimeException('Unbekannte Aktion.');
        missionAccept((int)($_POST['mission_id']??0),(int)$u['id']);
        redirect('competition.php?accepted=1#missions');
    } catch(Throwable $e) {
        redirect('competition.php?mission_error=1#missions');
    }
}
redirect('competition.php#missions');
