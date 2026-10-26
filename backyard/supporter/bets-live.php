<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter.php');
require_once lluPrivatePath('app/supporter_coins.php');

header('Content-Type: application/json; charset=utf-8');
try{
    supporterRequire();
    echo json_encode(['markets'=>supporterCoinLiveSnapshot(supporterTeam()),'updated_at'=>gmdate('c')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(503);
    echo json_encode(['error'=>'Live-Einsätze konnten nicht geladen werden.'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
}
