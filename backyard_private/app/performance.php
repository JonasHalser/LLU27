<?php
declare(strict_types=1);
/** LLU27 Performance analytics: compact activity streams; no GPS coordinates. */
function ll27Performance(array $stream, float $maxHr): array {
    $ts = $stream['time']['data'] ?? [];
    $ds = $stream['distance']['data'] ?? [];
    $hs = $stream['heartrate']['data'] ?? [];
    if (!is_array($ts) || !is_array($ds)) return ['analysis_version'=>3,'timeline'=>[],'splits'=>[], 'zones'=>[], 'drift'=>null, 'points'=>0];
    $n = min(count($ts), count($ds));
    $samples=[]; $lastDistance=-1.0; $lastTime=-1.0;
    for ($i=0;$i<$n;$i++) {
        if (!is_numeric($ts[$i]) || !is_numeric($ds[$i])) continue;
        $time=(float)$ts[$i];$dist=(float)$ds[$i];
        if($time<0 || $dist<0 || $dist<$lastDistance || $time<=$lastTime)continue;
        $hr= isset($hs[$i]) && is_numeric($hs[$i]) && $hs[$i]>=40 && $hs[$i]<=230 ? (float)$hs[$i] : null;
        $sample=['t'=>$time,'d'=>$dist,'hr'=>$hr];
        foreach(['altitude'=>[-500,10000],'watts'=>[0,3000],'cadence'=>[0,300],
            'grade_smooth'=>[-100,100],'velocity_smooth'=>[0,15]] as $key=>$range){
            $value=$stream[$key]['data'][$i]??null;
            $sample[$key]=is_numeric($value)&&is_finite((float)$value)&&$value>=$range[0]&&$value<=$range[1]?(float)$value:null;
        }
        $moving=$stream['moving']['data'][$i]??null;
        $sample['moving']=is_bool($moving)?$moving:null;
        $samples[]=$sample;$lastTime=$time;$lastDistance=$dist;
    }
    $n=count($samples);if($n<2)return ['analysis_version'=>3,'timeline'=>[],'splits'=>[], 'zones'=>[], 'drift'=>null, 'points'=>$n];
    $zones=array_fill(0,5,0.0);$zoneLimits=[.60,.70,.80,.90];
    // Zwischen zwei Messpunkten Zeit nur bis 120 s werten; GPS/HR-Lücken nicht extrapolieren.
    for($i=1;$i<$n;$i++){
        $dt=$samples[$i]['t']-$samples[$i-1]['t'];$hr=$samples[$i-1]['hr'];
        if($dt<=0 || $dt>120 || $hr===null)continue;
        $ratio=$hr/$maxHr;$z=4;
        foreach($zoneLimits as $ix=>$edge){if($ratio<$edge){$z=$ix;break;}}
        $zones[$z]+=$dt;
    }
    $lastD=$samples[$n-1]['d'];$splits=[];$previousT=null;$startD=$samples[0]['d'];
    // Splits ab exakt 1,000 m, 2,000 m etc. Bei Start-Distanz >0 keine erfundenen Anfangsdaten.
    if($startD<=50){$previousT=$samples[0]['t'];$j=1;
        for($i=1;$i<$n && $j<=100;$i++){
            $a=$samples[$i-1];$b=$samples[$i];
            while($j*1000<=$b['d'] && $j*1000>$a['d'] && $j<=100){
                $fraction=($j*1000-$a['d'])/max(.0001,$b['d']-$a['d']);
                $at=$a['t']+$fraction*($b['t']-$a['t']);$sec=$at-$previousT;
                if($sec>0 && $sec<7200)$splits[]=['km'=>$j,'seconds'=>round($sec),'pace'=>round($sec/60,2), 'hr'=>ll27MeanHrInRange($samples,($j-1)*1000,$j*1000)];
                $previousT=$at;$j++;
            }
        }
    }
    // HF-Drift: Erste vs. zweite Distanzhälfte, nur mit >= 5 km und hinreichender HR-Abdeckung.
    $drift=null;
    if($lastD-$startD>=5000){
        $mid=($lastD+$startD)/2;
        $one=ll27Efficiency($samples,$startD,$mid);$two=ll27Efficiency($samples,$mid,$lastD);
        if($one!==null && $two!==null && $one>0)$drift=round(($two/$one-1)*100,1);
    }
    return ['analysis_version'=>3,'timeline'=>ll27Timeline($samples),'splits'=>$splits,'zones'=>array_map(static fn($x)=>(int)round($x),$zones),'drift'=>$drift,'points'=>$n,'distance_m'=>round($lastD,1)];
}
// Zeitfenster statt sekündlicher GPS-Pace: höchstens etwa 600 Punkte pro Lauf.
// Zeitgewichtete Fenster; optionale Streams bleiben bei fehlenden Messungen null.
function ll27Timeline(array $samples): array {
    $first=$samples[0];$last=$samples[count($samples)-1];
    $window=max(30.0,ceil(($last['t']-$first['t'])/600));
    $result=[];$anchor=$first;$hrSum=0.0;$hrSeconds=0.0;$gap=false;$sums=[];$weights=[];
    for($i=1,$n=count($samples);$i<$n;$i++){
        $a=$samples[$i-1];$b=$samples[$i];$dt=$b['t']-$a['t'];
        if($dt>120)$gap=true;
        elseif($a['hr']!==null){$hrSum+=$a['hr']*$dt;$hrSeconds+=$dt;}
        if($dt<=120){
            foreach(['altitude','watts','cadence','grade_smooth','velocity_smooth','moving'] as $key){
                $value=$a[$key]??null;
                if($value===null)continue;
                // Geschwindigkeit nur in als bewegt erkannten Abschnitten mitteln.
                if($key==='velocity_smooth'&&($a['moving']??true)===false)continue;
                $sums[$key]=($sums[$key]??0)+(float)$value*$dt;
                $weights[$key]=($weights[$key]??0)+$dt;
            }
        }
        if($b['t']-$anchor['t']<$window && $i<$n-1)continue;
        $duration=$b['t']-$anchor['t'];$distance=$b['d']-$anchor['d'];
        $pace=$distance>0 ? $duration*1000/$distance : null;
        if(($weights['velocity_smooth']??0)>0){
            $speed=$sums['velocity_smooth']/$weights['velocity_smooth'];
            $pace=$speed>0?1000/$speed:null;
        }
        if(($weights['moving']??0)>0&&$sums['moving']===0.0)$pace=null;
        $point=['km'=>round($b['d']/1000,3),'seconds'=>round($b['t']),
            'pace'=>!$gap && $pace!==null && $pace>=120 && $pace<=1800 ? round($pace) : null,
            'hr'=>!$gap && $hrSeconds>0 ? round($hrSum/$hrSeconds) : null];
        foreach(['altitude','watts','cadence','grade_smooth','moving'] as $key){
            $point[$key]=!$gap&&($weights[$key]??0)>0?round($sums[$key]/$weights[$key],2):null;
        }
        $result[]=$point;
        $anchor=$b;$hrSum=0.0;$hrSeconds=0.0;$gap=false;$sums=[];$weights=[];
    }
    return $result;
}
function ll27MeanHrInRange(array $samples,float $from,float $to):?float {
    $values=[];foreach($samples as $s){if($s['d']>=$from && $s['d']<$to && $s['hr']!==null)$values[]=$s['hr'];}
    return count($values)>=2 ? round(array_sum($values)/count($values)) : null;
}
function ll27Efficiency(array $samples,float $from,float $to):?float {
    $first=null;$last=null;$hrs=[];
    foreach($samples as $s){if($s['d']<$from || $s['d']>$to)continue;if($first===null)$first=$s;$last=$s;if($s['hr']!==null)$hrs[]=$s['hr'];}
    if($first===null || $last===null || count($hrs)<8 || ($last['d']-$first['d'])<1000)return null;
    $duration=$last['t']-$first['t'];if($duration<=0)return null;
    $speed=($last['d']-$first['d'])/$duration;
    if($speed<.4 || $speed>10)return null;
    // Einheiten HF/(m/s), Steigung bedeutet ungünstigere HF-zu-Tempo-Relation.
    return (array_sum($hrs)/count($hrs))/$speed;
}
