<?php
declare(strict_types=1);
require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/bootstrap.php';
safeSession();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verifyCsrf();revokeRememberToken();$_SESSION=[];
if(ini_get('session.use_cookies'))setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>secureCookiesEnabled(),'httponly'=>true,'samesite'=>'Lax']);
session_destroy();redirect('index.php');
