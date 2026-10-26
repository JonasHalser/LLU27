<?php
declare(strict_types=1);require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/bootstrap.php';$u=requireUser();$state=bin2hex(random_bytes(24));$_SESSION['oauth_state']=$state;
$qs=http_build_query(['client_id'=>cfg('STRAVA_CLIENT_ID'),'redirect_uri'=>url('callback.php'),'response_type'=>'code','approval_prompt'=>'auto','scope'=>'read,activity:read_all','state'=>$state]);header('Location: https://www.strava.com/oauth/authorize?'.$qs);exit;
