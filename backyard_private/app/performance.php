<?php
declare(strict_types=1);
/** LLU27 Performance analytics: only time/distance/heartrate streams; no GPS is requested or stored. */
function ll27Performance(array $stream, float $maxHr): array {
    $ts = $stream['time']['data'] ?? [];
    $ds = $stream['distance']['data'] ?? [];
    $hs = $stream['heartrate']['data'] ?? [];
    if (!is_array($ts) || !is_array($ds)) return ['splits'=>[], 'zones'=>[], 'drift'=>null, 'points'=>0];
    $n = min(count($ts), count($ds));
    $samples=[]; $lastDistance=-1.0; $lastTime=-1.0;
    for ($i=0;$i<$n;$i++) {
        if (!is_numeric($ts[$i]) || !is_numeric($ds[$i])) continue;
        $time=(float)$ts[$i];$dist=(float)$ds[$i];
        if($time<0 || $dist<0 || $dist<$lastDistance || $time<=$lastTime)continue;
        $hr= isset($hs[$i]) && is_numeric($hs[$i]) && $hs[$i]>=40 && $hs[$i]<=230 ? (float)$hs[$i] : null;
        $samples[]=['t'=>$time,'d'=>$dist,'hr'=>$hr];$lastTime=$time;$lastDistance=$dist;
    }
    $n=count($samples);if($n<2)return ['splits'=>[], 'zones'=>[], 'drift'=>null, 'points'=>$n];
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
    return ['splits'=>$splits,'zones'=>array_map(static fn($x)=>(int)round($x),$zones),'drift'=>$drift,'points'=>$n,'distance_m'=>round($lastD,1)];
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
