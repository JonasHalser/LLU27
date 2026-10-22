<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/backyard_private/app/bootstrap.php';
safeSession();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
verifyCsrf();revokeRememberToken();$_SESSION=[];
if(ini_get('session.use_cookies'))setcookie(session_name(),'', ['expires'=>time()-3600,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_destroy();redirect('index.php');
