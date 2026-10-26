<?php
declare(strict_types=1);
require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/supporter.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/supporter_coins.php';

header('Content-Type: application/json; charset=utf-8');
try{
    supporterRequire();
    echo json_encode(['markets'=>supporterCoinLiveSnapshot(supporterTeam()),'updated_at'=>gmdate('c')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){
    http_response_code(503);
    echo json_encode(['error'=>'Live-Einsätze konnten nicht geladen werden.'],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
}
