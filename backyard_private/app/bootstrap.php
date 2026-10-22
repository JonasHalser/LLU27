<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
function envload(): array { static $env=null; if ($env!==null) return $env; $env=[]; $path=ROOT.'/.env'; if (!is_file($path)) throw new RuntimeException('Konfiguration fehlt (.env).'); foreach(file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){ $line=trim($line); if($line===''||str_starts_with($line,'#')) continue; $p=explode('=',$line,2); if(count($p)===2)$env[trim($p[0])]=trim($p[1]," \t\r\n\"'"); } return $env; }
function cfg(string $key,string $default=''): string {return envload()[$key]??$default;}
function db(): PDO {static $db=null;if($db)return $db;$db=new PDO('mysql:host='.cfg('DB_HOST','localhost').';port='.cfg('DB_PORT','3306').';dbname='.cfg('DB_NAME').';charset=utf8mb4',cfg('DB_USER'),cfg('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $db;}
function url(string $path=''):string{return rtrim(cfg('APP_URL'),'/' ).'/'.ltrim($path,'/');}
function safeSession(): void {if(session_status()===PHP_SESSION_ACTIVE)return; session_name('backyard_session');session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);ini_set('session.use_strict_mode','1');session_start();}
function csrf():string{safeSession();return $_SESSION['csrf']??=bin2hex(random_bytes(24));}
function verifyCsrf():void{if(!hash_equals(csrf(),(string)($_POST['csrf']??'')))throw new RuntimeException('Ungültige Formularanfrage.');}
function h(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function redirect(string $to):never{header('Location: '.url($to),true,302);exit;}
function currentUser():?array{safeSession();if(!isset($_SESSION['user_id']))restoreRememberedUser();if(!isset($_SESSION['user_id']))return null;$s=db()->prepare('SELECT id,username,display_name,start_date FROM users WHERE id=?');$s->execute([$_SESSION['user_id']]);return $s->fetch()?:null;}
function requireUser():array{$u=currentUser();if(!$u)redirect('index.php');return $u;}
function cryptoKey():string{$raw=base64_decode(cfg('TOKEN_KEY'),true);if($raw===false||strlen($raw)!==32)throw new RuntimeException('TOKEN_KEY muss 32 Byte Base64 sein.');return $raw;}
function enc(string $value):string{$nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',cryptoKey(),OPENSSL_RAW_DATA,$nonce,$tag);if($cipher===false)throw new RuntimeException('Verschlüsselung fehlgeschlagen');return base64_encode($nonce.$tag.$cipher);}
function dec(string $value):string{$b=base64_decode($value,true);if($b===false||strlen($b)<29)throw new RuntimeException('Ungültige Token-Daten');$plain=openssl_decrypt(substr($b,28),'aes-256-gcm',cryptoKey(),OPENSSL_RAW_DATA,substr($b,0,12),substr($b,12,16));if($plain===false)throw new RuntimeException('Entschlüsselung fehlgeschlagen');return $plain;}
function activeAthlete(int $uid):?array{$s=db()->prepare('SELECT * FROM athletes WHERE user_id=?');$s->execute([$uid]);return $s->fetch()?:null;}
function httpJson(string $method,string $uri,array $args=[],?string $token=null):array{$ch=curl_init();$headers=['Accept: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;if($method==='POST'){$headers[]='Content-Type: application/x-www-form-urlencoded';curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($args));}elseif($args)$uri.=(str_contains($uri,'?')?'&':'?').http_build_query($args);curl_setopt_array($ch,[CURLOPT_URL=>$uri,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);if($body===false)throw new RuntimeException('API-Verbindung fehlgeschlagen: '.$err);$obj=json_decode($body,true);if($status>=400)throw new RuntimeException('Strava HTTP '.$status.': '.substr((string)($obj['message']??'API-Fehler'),0,120));if(!is_array($obj))throw new RuntimeException('Ungültige API-Antwort');return $obj;}
function tokenRefresh(array $a):string{if((int)$a['expires_at']>time()+180)return dec($a['access_token']);$r=httpJson('POST','https://www.strava.com/oauth/token',['client_id'=>cfg('STRAVA_CLIENT_ID'),'client_secret'=>cfg('STRAVA_CLIENT_SECRET'),'grant_type'=>'refresh_token','refresh_token'=>dec($a['refresh_token'])]);$s=db()->prepare('UPDATE athletes SET access_token=?,refresh_token=?,expires_at=? WHERE user_id=?');$s->execute([enc((string)$r['access_token']),enc((string)$r['refresh_token']),(int)$r['expires_at'],$a['user_id']]);return (string)$r['access_token'];}
function percent(float $v):string{return number_format($v,1,',','.');}

// Dauerhafte Anmeldung: nur der Hash des zufälligen Tokens wird serverseitig gespeichert.
function rememberCookieName():string{return 'llu27_remember';}
function rememberCookie(string $value,int $expires):void{
 setcookie(rememberCookieName(),$value,['expires'=>$expires,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
}
function forgetRememberCookie():void{rememberCookie('',time()-3600);unset($_COOKIE[rememberCookieName()]);}
function revokeRememberToken():void{
 $value=(string)($_COOKIE[rememberCookieName()]??'');
 if(preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/D',$value,$m)){
  $q=db()->prepare('DELETE FROM remember_tokens WHERE selector=?');$q->execute([$m[1]]);
 }
 forgetRememberCookie();
}
function issueRememberToken(int $userId):void{
 revokeRememberToken();
 $selector=bin2hex(random_bytes(12));$validator=bin2hex(random_bytes(32));
 $q=db()->prepare('INSERT INTO remember_tokens(user_id,selector,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
 $q->execute([$userId,$selector,hash('sha256',$validator)]);
 $value=$selector.'.'.$validator;
 rememberCookie($value,time()+30*86400);$_COOKIE[rememberCookieName()]=$value;
}
function restoreRememberedUser():void{
 $value=(string)($_COOKIE[rememberCookieName()]??'');
 if(!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/D',$value,$m))return;
 $q=db()->prepare('SELECT user_id,token_hash,expires_at FROM remember_tokens WHERE selector=?');
 $q->execute([$m[1]]);$row=$q->fetch();
 if(!$row||strtotime($row['expires_at'].' UTC')<=time()||!hash_equals($row['token_hash'],hash('sha256',$m[2]))){revokeRememberToken();return;}
 // Token bei jeder Wiederanmeldung rotieren (Replay eines alten Cookies wird wertlos).
 $del=db()->prepare('DELETE FROM remember_tokens WHERE selector=?');$del->execute([$m[1]]);
 session_regenerate_id(true);$_SESSION['user_id']=(int)$row['user_id'];
 issueRememberToken((int)$row['user_id']);
}
