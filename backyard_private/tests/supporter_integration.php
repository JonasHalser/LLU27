<?php
declare(strict_types=1);
// CLI only, CI only, hard-coded disposable DB; never run against the private live .env.
if(PHP_SAPI!=='cli'||getenv('GITHUB_ACTIONS')!=='true') exit("Only available in disposable CI environment.\n");
$private=dirname(__DIR__); $env=$private.'/.env';
if(is_file($env)) throw new RuntimeException('Refusing to overwrite an existing configuration.');
file_put_contents($env,"DB_HOST=127.0.0.1\nDB_NAME=llu27_test\nDB_USER=root\nDB_PASS=test-only-password\nAPP_URL=http://127.0.0.1:8765/backyard\nTOKEN_KEY=".base64_encode(random_bytes(32))."\n");
register_shutdown_function(static function()use($env){unlink($env);});
require_once $private.'/app/supporter.php';
require_once $private.'/app/weekly_goals.php';
$p=db(); if($p->query('SELECT DATABASE()')->fetchColumn()!=='llu27_test') throw new RuntimeException('Unsafe database.');
$checks=0;
function test(bool $ok,string $label): void {global $checks; if(!$ok) throw new RuntimeException('FAIL: '.$label); $checks++;}
foreach(['tim','jonas','noah'] as $name) $p->prepare('INSERT INTO users(username,display_name,password_hash,start_date) VALUES(?,?,?,?)')->execute([$name,ucfirst($name),password_hash('runner-password',PASSWORD_DEFAULT),'2026-01-01']);
// The pre-existing runner dashboard expects this consent column, absent from base schema.sql.
$p->exec('ALTER TABLE users ADD COLUMN share_team_hr TINYINT NOT NULL DEFAULT 0');
$p->exec('CREATE TABLE activity_performance (activity_id BIGINT UNSIGNED PRIMARY KEY,user_id INT UNSIGNED NOT NULL,detail_json LONGTEXT NULL)');
$p->prepare('INSERT INTO supporters(username,display_name,password_hash) VALUES(?,?,?)')->execute(['support_test','Test Supporter',password_hash('support-password',PASSWORD_DEFAULT)]); $sid=(int)$p->lastInsertId();
try { $p->prepare('INSERT INTO supporters(username,display_name,password_hash) VALUES(?,?,?)')->execute(['SUPPORT_TEST','duplicate','hash']); test(false,'unique username'); } catch(PDOException $e) { test($e->getCode()==='23000','case insensitive unique username'); }
test((int)$p->query('SELECT COUNT(*) FROM users')->fetchColumn()===3,'supporter never becomes runner');
$team=supporterTeam(); $runner=(int)$team[0]['id'];
supporterCheer($sid,$runner,'ONE MORE LOOP! <script>');
test((int)$p->query('SELECT COUNT(*) FROM supporter_cheers')->fetchColumn()===1,'cheer saved');
test((int)$p->query('SELECT COUNT(*) FROM supporter_push_jobs')->fetchColumn()===1,'push queued');
try { supporterCheer($sid,$runner,'Spam'); test(false,'cooldown'); } catch(RuntimeException $e) { test(true,'cooldown'); }
test((int)$p->query('SELECT COUNT(*) FROM supporter_cheers')->fetchColumn()===1,'spam not saved');
try { supporterCheer($sid,99999,'Invalid'); test(false,'invalid recipient'); } catch(RuntimeException $e) { test(true,'invalid recipient'); }
$q=$p->prepare('INSERT INTO challenge_weekly(user_id,iso_year,iso_week,completed_loops,run_count,finalized,updated_at) VALUES(?,2026,40,?,1,1,UTC_TIMESTAMP())');
foreach($team as $i=>$r) $q->execute([$r['id'],$i===2?2:4]);
$score=supporterScore(); test(count($score['history'][0]['winners'])===2,'DB finalized tie');
$p->prepare('INSERT INTO supporter_tips(supporter_id,iso_year,iso_week,runner_id) VALUES(?,2026,40,?)')->execute([$sid,$runner]);
$p->prepare('INSERT INTO supporter_tips(supporter_id,iso_year,iso_week,runner_id) VALUES(?,2026,40,?) ON DUPLICATE KEY UPDATE runner_id=VALUES(runner_id)')->execute([$sid,$team[1]['id']]);
test((int)$p->query('SELECT COUNT(*) FROM supporter_tips')->fetchColumn()===1,'one prediction per week');
supporterLimit('test',1,30);
try {supporterLimit('test',1,30);test(false,'rate limit');}catch(RuntimeException $e){test(true,'rate limit');}
// Match Hostinger: public_html/backyard and backyard_private are siblings.
// Serving the repository's backyard directory directly breaks the production
// dirname(__DIR__, N) includes. Copy public files: symlinking them would cause
// PHP's __DIR__ to resolve back to the incompatible repository layout.
$fixture=sys_get_temp_dir().'/llu27-http-'.bin2hex(random_bytes(8));
$documentRoot=$fixture.'/public_html'; $web=$documentRoot.'/backyard';
mkdir($web,0700,true);
if(!symlink($private,$fixture.'/backyard_private')) throw new RuntimeException('Cannot link private test application.');
$source=dirname($private).'/backyard';
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
foreach($iterator as $item) {
    $destination=$web.'/'.substr($item->getPathname(),strlen($source)+1);
    if($item->isDir()) mkdir($destination,0700,true);
    elseif(!copy($item->getPathname(),$destination)) throw new RuntimeException('Cannot copy public test file.');
}
$serverLog=$fixture.'/http.log';
$process=proc_open([PHP_BINARY,'-S','127.0.0.1:8765','-t',$documentRoot],[['pipe','r'],['file',$serverLog,'a'],['file',$serverLog,'a']],$pipes);
if(!is_resource($process)) throw new RuntimeException('HTTP server failed.');
register_shutdown_function(static function()use($process){proc_terminate($process);proc_close($process);});
$cookies=[];
function request(string $path,?array $data=null,string $jar='supporter'): array {
    global $cookies,$serverLog; $ch=curl_init('http://127.0.0.1:8765/backyard/'.$path); $headers=[];
    // Secure cookies cannot travel over HTTP; replay only in this local harness to test sessions.
    $cookie=implode('; ',array_map(static fn($k,$v)=>$k.'='.$v,array_keys($cookies[$jar]??[]),array_values($cookies[$jar]??[])));
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>15,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers,$jar){global $cookies;$headers[]=$line;if(preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i',$line,$m))$cookies[$jar][$m[1]]=$m[2];return strlen($line);}]);
    if($data!==null) {curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($data));}
    $body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
    if($body===false || $status>=500) throw new RuntimeException('HTTP request failed: '.$path.' status='.$status.' curl='.$error."\n".(is_file($serverLog)?file_get_contents($serverLog):'No server log available.'));
    return [$status,(string)$body,$headers];
}
function token(string $body): string { if(!preg_match('/name="csrf" value="([a-f0-9]+)"/',$body,$m))throw new RuntimeException('CSRF missing');return $m[1]; }
$ready=false;
for($i=0;$i<30;$i++){ $socket=@fsockopen('127.0.0.1',8765);if($socket){fclose($socket);$ready=true;break;}usleep(100000); }
if(!$ready) throw new RuntimeException('HTTP server did not start: '.(is_file($serverLog)?file_get_contents($serverLog):'No server log available.'));
[$status,$body]=request('supporter/register.php');test($status===200,'registration form');$csrf=token($body);
[$status]=request('supporter/register.php',['csrf'=>$csrf,'username'=>'new_support','display_name'=>'New','password'=>'new-password-123','confirm'=>'new-password-123']);test($status===302,'registration succeeds');
[$status,$body]=request('supporter/register.php',['csrf'=>$csrf,'username'=>'new_support','password'=>'new-password-123','confirm'=>'new-password-123']);test($status===200&&str_contains($body,'bereits vergeben'),'duplicate registration');
[$status,$body]=request('supporter/login.php');$csrf=token($body);
[$status,$body]=request('supporter/login.php',['csrf'=>$csrf,'username'=>'new_support','password'=>'wrong']);test($status===200&&str_contains($body,'Passwort falsch'),'wrong password');
$old=$cookies['supporter']['llu27_supporter_session'];
[$status]=request('supporter/login.php',['csrf'=>$csrf,'username'=>'new_support','password'=>'new-password-123','remember'=>1]);test($status===302,'login succeeds');
test($old!==$cookies['supporter']['llu27_supporter_session'],'session rotation');
test(isset($cookies['supporter']['llu27_supporter_remember']),'remember issued');
[$status,$body]=request('supporter/index.php');test($status===200&&str_contains($body,'Anfeuern'),'authenticated dashboard');
[$status,$body]=request('supporter/profile.php');test($status===200&&str_contains($body,'Push-Benachrichtigungen'),'supporter profile and push partial');
[$status]=request('dashboard.php');test($status===302,'supporter denied runner dashboard');
[$status,$body]=request('index.php'); $runnerCsrf=token($body);
[$status]=request('index.php',['csrf'=>$runnerCsrf,'username'=>'tim','password'=>'runner-password']);test($status===302,'runner login while supporter logged in');
[$status,$body]=request('dashboard.php');
test($status===200&&substr_count(strtolower($body),'<!doctype html>')===1&&str_contains($body,'Letzte Läufe im Team'),'runner dashboard renders completely');
test(str_contains($body,'href="weekly-goals.php"')&&!str_contains($body,'value="weekly_goal_save"')&&!str_contains($body,'value="team_goal_save"')&&!str_contains($body,'Wochenziele konfigurieren'),'dashboard weekly goals are read only');
[$status,$goalPage]=request('weekly-goals.php');
test($status===200&&str_contains($goalPage,'value="weekly_goal_save"')&&str_contains($goalPage,'value="team_goal_save"'),'weekly goal settings page');
$goalCsrf=token($goalPage);$period=weeklyGoalPeriod();weeklyGoalSave((int)$team[1]['id'],$period,['unit'=>'loops','target'=>4]);
[$status]=request('weekly-goals.php',['csrf'=>$goalCsrf,'action'=>'weekly_goal_save','goal_unit'=>'hours','goal_value'=>'3.5']);test($status===302,'weekly goal saved');
test((int)$p->query('SELECT COUNT(*) FROM weekly_goals')->fetchColumn()===2,'team weekly goals persisted');
$p->prepare('INSERT INTO activities(id,user_id,name,activity_date,distance_m,moving_seconds,elevation_m,cache_updated_at) VALUES(?,?,?,?,?,?,0,UTC_TIMESTAMP())')->execute([99001,$runner,'Goal test',$period['start']->modify('+1 day')->format('Y-m-d H:i:s'),12000,14400]);
[$status,$body]=request('dashboard.php');test($status===200&&str_contains($body,'Wochenziele im Team')&&str_contains($body,'3,5 Std.')&&str_contains($body,'4 Loops'),'team weekly goals visible');
test((bool)$p->query('SELECT achieved_at FROM weekly_goals WHERE user_id='.$runner.' AND iso_year='.$period['year'].' AND iso_week='.$period['week'])->fetchColumn(),'weekly goal achievement persisted');
$history=$p->prepare('INSERT INTO weekly_goals(user_id,iso_year,iso_week,unit,target_value,progress_value,achieved_at,updated_at) VALUES(?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
foreach([-1,-2] as $offset){$past=weeklyGoalPeriod($period['start']->modify($offset.' week'));$history->execute([$runner,$past['year'],$past['week'],'km',10,10]);}
[$status,$body]=request('dashboard.php');test($status===200&&str_contains($body,'3 Wochen in Folge'),'weekly goal streak visible');
[$status,$goalPage]=request('weekly-goals.php');$teamCsrf=token($goalPage);
[$status]=request('weekly-goals.php',['csrf'=>$teamCsrf,'action'=>'team_goal_save','goal_unit'=>'km','goal_value'=>'100']);test($status===302,'team goal saved');
test((int)$p->query('SELECT COUNT(*) FROM team_weekly_goals')->fetchColumn()===1,'team goal persisted');
[$status,$body]=request('dashboard.php');test($status===200&&str_contains($body,'GEMEINSAME MISSION')&&str_contains($body,'100,0 km'),'team goal visible');
test((float)$p->query('SELECT progress_value FROM team_weekly_goals')->fetchColumn()===12.0,'team goal progress persisted');
[$status]=request('challenge.php');test($status===200,'runner challenge');
[$status]=request('performance.php');test($status===200,'runner performance');
[$status,$body]=request('supporter/index.php');test($status===200&&str_contains($body,'Hallo New'),'supporter session independent');
test(isset($cookies['supporter']['backyard_session'])&&isset($cookies['supporter']['llu27_supporter_session']),'separate session cookies');
[$status,$body]=request('supporter/index.php',['csrf'=>'bad','action'=>'cheer','runner'=>$runner,'message'=>'no']);test(str_contains($body,'Formularanfrage'),'CSRF protection');
$remember=$cookies['supporter']['llu27_supporter_remember'];
$cookies['restore']=['llu27_supporter_remember'=>$remember];
[$status]=request('supporter/index.php',null,'restore');test($status===200,'remember restoration');
test(($cookies['restore']['llu27_supporter_remember']??'')!==$remember,'remember rotation');
[$status,$body]=request('supporter/logout.php');$csrf=token($body);$remember=$cookies['supporter']['llu27_supporter_remember'];
[$status]=request('supporter/logout.php',['csrf'=>$csrf]);test($status===302,'logout');
$cookies['replay']=['llu27_supporter_remember'=>$remember];[$status]=request('supporter/index.php',null,'replay');test($status===302,'remember revoked');
$p->prepare('DELETE FROM supporters WHERE id=?')->execute([$sid]);
test((int)$p->query('SELECT COUNT(*) FROM supporter_tips')->fetchColumn()===0,'tips cascade');
test((int)$p->query('SELECT COUNT(*) FROM users')->fetchColumn()===3,'runner accounts preserved');
echo "OK: $checks integration checks\n";
