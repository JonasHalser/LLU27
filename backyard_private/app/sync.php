<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
function syncOne(array $a):string{
 $pdo=db();$uid=(int)$a['user_id'];$tok=tokenRefresh($a);
 $full=empty($a['last_full_sync'])||strtotime((string)$a['last_full_sync'])<time()-86400;
 $days=max(7,min(365,(int)cfg('HISTORY_DAYS','180')));
 $after=$full?time()-$days*86400:time()-8*86400;
 $all=[];$complete=false;
 for($page=1;$page<=10;$page++){
   $batch=httpJson('GET','https://www.strava.com/api/v3/athlete/activities',['after'=>$after,'per_page'=>100,'page'=>$page],$tok);
   if(count($batch)===0){$complete=true;break;}
   foreach($batch as $v){if(!is_array($v))continue;if(!in_array($v['sport_type']??$v['type']??'', ['Run','TrailRun','VirtualRun'],true))continue;$all[]=$v;}
   if(count($batch)<100){$complete=true;break;}
 }
 $pdo->beginTransaction();
 try {
  $up=$pdo->prepare('INSERT INTO activities (id,user_id,name,activity_date,distance_m,moving_seconds,elevation_m,avg_hr,max_hr,cache_updated_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE name=VALUES(name),activity_date=VALUES(activity_date),distance_m=VALUES(distance_m),moving_seconds=VALUES(moving_seconds),elevation_m=VALUES(elevation_m),avg_hr=VALUES(avg_hr),max_hr=VALUES(max_hr),cache_updated_at=UTC_TIMESTAMP()');
  $ids=[];
  foreach($all as $v){$id=(int)($v['id']??0);if(!$id)continue;$ids[]=$id;$up->execute([$id,$uid,mb_substr((string)($v['name']??'Lauf'),0,255),gmdate('Y-m-d H:i:s',strtotime((string)($v['start_date']??'now'))),(float)($v['distance']??0),(int)($v['moving_time']??0),(float)($v['total_elevation_gain']??0),isset($v['average_heartrate'])?(float)$v['average_heartrate']:null,isset($v['max_heartrate'])?(float)$v['max_heartrate']:null]);}
  // Bei komplettem Abruf die im geprüften Zeitraum verschwundenen Aktivitäten löschen.
  if($complete){$st=$pdo->prepare('SELECT id FROM activities WHERE user_id=? AND activity_date>=?');$st->execute([$uid,gmdate('Y-m-d H:i:s',$after)]);$del=$pdo->prepare('DELETE FROM activities WHERE id=? AND user_id=?');$keep=array_fill_keys($ids,true);foreach($st->fetchAll(PDO::FETCH_COLUMN) as $old){if(!isset($keep[(int)$old]))$del->execute([(int)$old,$uid]);}}
  $s=$pdo->prepare('UPDATE athletes SET last_sync=UTC_TIMESTAMP(),last_full_sync=IF(?,UTC_TIMESTAMP(),last_full_sync),sync_error=NULL WHERE user_id=?');$s->execute([$full&&$complete?1:0,$uid]);$pdo->commit();
 }catch(Throwable $e){$pdo->rollBack();throw $e;}
 return 'Nutzer '.$uid.': '.count($all).' Läufe, '.($full?'Vollsync':'Kurzsync');
}
function runSync():array{
 $pdo=db();$pdo->exec('DELETE FROM activities WHERE cache_updated_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
 $out=[];
 foreach($pdo->query('SELECT * FROM athletes ORDER BY user_id') as $a){try{$out[]=syncOne($a);}catch(Throwable $e){$out[]='Fehler Nutzer '.$a['user_id'].': '.$e->getMessage();$s=$pdo->prepare('UPDATE athletes SET sync_error=? WHERE user_id=?');$s->execute([mb_substr($e->getMessage(),0,255),$a['user_id']]);}}
 return $out;
}
