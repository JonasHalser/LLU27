<?php
declare(strict_types=1);
require_once __DIR__.'/partials/private-path.php';

require_once lluPrivatePath('app/bootstrap.php');
safeSession();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verifyCsrf();revokeRememberToken();$_SESSION=[];
if(ini_get('session.use_cookies'))setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>secureCookiesEnabled(),'httponly'=>true,'samesite'=>'Lax']);
session_destroy();redirect('index.php');
