<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/performance.php';
function verifyPerformance(bool $ok,string $message):void {
    if(!$ok)throw new RuntimeException($message);
}
$stream=['time'=>['data'=>[]],'distance'=>['data'=>[]],'heartrate'=>['data'=>[]]];
for($i=0;$i<=600;$i++){
    $stream['time']['data'][]=$i;
    $stream['distance']['data'][]=$i*3;
    $stream['heartrate']['data'][]=150;
}
$metrics=ll27Performance($stream,190);
verifyPerformance($metrics['analysis_version']===3,'Version for cache refresh');
verifyPerformance(count($metrics['timeline'])===20,'Thirty-second windows');
verifyPerformance($metrics['timeline'][0]['pace']===333.0,'Pace in seconds per kilometre');
verifyPerformance($metrics['timeline'][0]['hr']===150.0,'Time-weighted heart rate');
verifyPerformance(count($metrics['splits'])===1,'Only complete kilometres');
verifyPerformance($metrics['timeline'][19]['km']===1.8,'Final distance retained');
$gap=ll27Timeline([['t'=>0,'d'=>0,'hr'=>150],['t'=>180,'d'=>500,'hr'=>155]]);
verifyPerformance($gap[0]['pace']===null&&$gap[0]['hr']===null,'Do not bridge measurement gaps');
$missing=ll27Performance(['time'=>['data'=>[0,30]],'distance'=>['data'=>[0,90]]],190);
verifyPerformance($missing['timeline'][0]['hr']===null,'Missing heart rate stays null');
verifyPerformance($missing['timeline'][0]['pace']===333.0,'Pace without heart rate');
verifyPerformance(ll27Performance([],190)['analysis_version']===3,'Empty streams do not retry forever');
$long=[];
for($i=0;$i<=20000;$i++)$long[]=['t'=>$i,'d'=>$i*3,'hr'=>150];
verifyPerformance(count(ll27Timeline($long))<=601,'Bounded payload for long runs');
$extra=$stream;
foreach(['altitude'=>120,'watts'=>250,'cadence'=>85,'grade_smooth'=>2,'velocity_smooth'=>4,'moving'=>true] as $key=>$value){
    $extra[$key]['data']=array_fill(0,601,$value);
}
$extended=ll27Performance($extra,190)['timeline'][0];
verifyPerformance($extended['altitude']===120.0&&$extended['watts']===250.0,'Elevation and power retained');
verifyPerformance($extended['cadence']===85.0,'Cadence kept in original units');
verifyPerformance($extended['grade_smooth']===2.0,'Grade retained');
verifyPerformance($extended['pace']===250.0,'Smoothed speed supplies pace');
$extra['moving']['data']=array_fill(0,601,false);
verifyPerformance(ll27Performance($extra,190)['timeline'][0]['pace']===null,'Stopped windows have no pace');
verifyPerformance($metrics['timeline'][0]['watts']===null,'Missing power is not zero');
$extra['watts']['data']=array_fill(0,601,0);
verifyPerformance(ll27Performance($extra,190)['timeline'][0]['watts']===0.0,'Measured zero power retained');
$extra['altitude']['data']=array_fill(0,601,99999);
verifyPerformance(ll27Performance($extra,190)['timeline'][0]['altitude']===null,'Invalid altitude rejected');
echo "OK: performance analysis\n";
