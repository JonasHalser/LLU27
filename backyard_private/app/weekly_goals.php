<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function weeklyGoalPeriod(?DateTimeImmutable $now=null): array {
    $utc=new DateTimeZone('UTC');
    $now=$now ? $now->setTimezone($utc) : new DateTimeImmutable('now',$utc);
    $start=$now->modify('monday this week')->setTime(0,0);
    return [
        'year'=>(int)$start->format('o'),
        'week'=>(int)$start->format('W'),
        'start'=>$start,
        'end'=>$start->modify('+7 days'),
        'label'=>'KW '.(int)$start->format('W').' / '.$start->format('o'),
    ];
}

function weeklyGoalInput(string $unit,string $value): array {
    $unit=trim($unit);
    $value=str_replace(',','.',trim($value));
    $limits=['km'=>1000.0,'minutes'=>10080.0,'hours'=>168.0,'loops'=>500.0];
    if(!isset($limits[$unit])) throw new RuntimeException('Bitte eine gültige Einheit auswählen.');
    if($value===''||!is_numeric($value)) throw new RuntimeException('Bitte ein gültiges Wochenziel eingeben.');
    $target=(float)$value;
    if(!is_finite($target)||$target<=0||$target>$limits[$unit]) throw new RuntimeException('Das Wochenziel liegt außerhalb des gültigen Bereichs.');
    if(in_array($unit,['minutes','loops'],true) && floor($target)!==$target) throw new RuntimeException('Minuten und Loops müssen als ganze Zahl angegeben werden.');
    return ['unit'=>$unit,'target'=>round($target,2)];
}

function weeklyGoalSave(int $userId,array $period,array $goal): void {
    $q=db()->prepare('INSERT INTO weekly_goals(user_id,iso_year,iso_week,unit,target_value,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE unit=VALUES(unit),target_value=VALUES(target_value),progress_value=0,achieved_at=NULL,updated_at=UTC_TIMESTAMP()');
    $q->execute([$userId,$period['year'],$period['week'],$goal['unit'],$goal['target']]);
}

function weeklyGoalDelete(int $userId,array $period): void {
    $q=db()->prepare('DELETE FROM weekly_goals WHERE user_id=? AND iso_year=? AND iso_week=?');
    $q->execute([$userId,$period['year'],$period['week']]);
}

function weeklyGoalProgress(array $row): float {
    return match($row['unit']??null) {
        'km'=>(float)($row['distance_m']??0)/1000,
        'minutes'=>(float)($row['moving_seconds']??0)/60,
        'hours'=>(float)($row['moving_seconds']??0)/3600,
        'loops'=>(float)($row['completed_loops']??0),
        default=>0.0,
    };
}

function weeklyGoalUnitLabel(string $unit): string {
    return match($unit) {'km'=>'km','minutes'=>'Min.','hours'=>'Std.','loops'=>'Loops',default=>''};
}

function weeklyGoalValue(float $value,string $unit): string {
    $decimals=$unit==='loops'||$unit==='minutes' ? 0 : 1;
    return number_format($value,$decimals,',','.').' '.weeklyGoalUnitLabel($unit);
}

function weeklyGoalTeam(array $period): array {
    $q=db()->prepare("SELECT u.id,u.username,u.display_name,g.unit,g.target_value,g.progress_value,g.achieved_at,
        COALESCE(SUM(a.distance_m),0) distance_m,
        COALESCE(SUM(a.moving_seconds),0) moving_seconds,
        COALESCE(SUM(FLOOR(GREATEST(a.distance_m,0)/6706)),0) completed_loops
        FROM users u
        LEFT JOIN weekly_goals g ON g.user_id=u.id AND g.iso_year=? AND g.iso_week=?
        LEFT JOIN activities a ON a.user_id=u.id AND a.activity_date>=? AND a.activity_date<?
            AND a.activity_date>=CONCAT(u.start_date,' 00:00:00')
            AND a.cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY
        WHERE u.username IN ('tim','jonas','noah')
        GROUP BY u.id,u.username,u.display_name,g.unit,g.target_value,g.progress_value,g.achieved_at
        ORDER BY FIELD(u.username,'tim','jonas','noah')");
    $q->execute([$period['year'],$period['week'],$period['start']->format('Y-m-d H:i:s'),$period['end']->format('Y-m-d H:i:s')]);
    $rows=$q->fetchAll();
    $snapshot=db()->prepare('UPDATE weekly_goals SET progress_value=GREATEST(progress_value,?),achieved_at=IF(achieved_at IS NULL AND ?>=target_value,UTC_TIMESTAMP(),achieved_at) WHERE user_id=? AND iso_year=? AND iso_week=?');
    foreach($rows as &$row) {
        $live=weeklyGoalProgress($row);
        $row['progress']=max($live,(float)($row['progress_value']??0));
        $target=(float)($row['target_value']??0);
        $row['percent']=$target>0 ? min(100.0,$row['progress']/$target*100) : 0.0;
        $row['achieved']=$target>0 && ($row['achieved_at']!==null||$row['progress']>=$target);
        if($target>0) $snapshot->execute([$row['progress'],$row['progress'],(int)$row['id'],$period['year'],$period['week']]);
    }
    unset($row);
    return $rows;
}

function weeklyGoalWeekStart(array $row): DateTimeImmutable {
    return (new DateTimeImmutable('now',new DateTimeZone('UTC')))->setISODate((int)$row['iso_year'],(int)$row['iso_week'])->setTime(0,0);
}

function weeklyGoalStreakStats(array $rows,array $period): array {
    $byWeek=[];$ordered=[];
    foreach($rows as $row) {
        $start=weeklyGoalWeekStart($row);$key=$start->format('Y-m-d');
        $achieved=!empty($row['achieved_at']);$byWeek[$key]=$achieved;
        $ordered[]=['time'=>$start->getTimestamp(),'achieved'=>$achieved];
    }
    $cursor=$period['start'];$currentKey=$cursor->format('Y-m-d');
    if(empty($byWeek[$currentKey])) $cursor=$cursor->modify('-7 days');
    $current=0;
    while(!empty($byWeek[$cursor->format('Y-m-d')])) {$current++;$cursor=$cursor->modify('-7 days');}
    usort($ordered,static fn($a,$b)=>$a['time']<=>$b['time']);
    $best=0;$run=0;$previous=null;
    foreach($ordered as $row) {
        if(!$row['achieved']) {$run=0;$previous=$row['time'];continue;}
        $run=$previous!==null&&$row['time']-$previous===604800 ? $run+1 : 1;
        $best=max($best,$run);$previous=$row['time'];
    }
    return ['current'=>$current,'best'=>$best];
}

function weeklyGoalStreaks(array $period): array {
    $rows=db()->query("SELECT u.username,g.iso_year,g.iso_week,g.achieved_at FROM weekly_goals g JOIN users u ON u.id=g.user_id WHERE u.username IN ('tim','jonas','noah') ORDER BY g.iso_year,g.iso_week")->fetchAll();
    $grouped=[];foreach($rows as $row)$grouped[$row['username']][]=$row;
    $result=[];foreach(['tim','jonas','noah'] as $username)$result[$username]=weeklyGoalStreakStats($grouped[$username]??[],$period);
    return $result;
}

function teamWeeklyGoalSave(int $userId,array $period,array $goal): void {
    $q=db()->prepare('INSERT INTO team_weekly_goals(iso_year,iso_week,unit,target_value,updated_by,updated_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE unit=VALUES(unit),target_value=VALUES(target_value),progress_value=0,achieved_at=NULL,updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()');
    $q->execute([$period['year'],$period['week'],$goal['unit'],$goal['target'],$userId]);
}

function teamWeeklyGoalDelete(array $period): void {
    $q=db()->prepare('DELETE FROM team_weekly_goals WHERE iso_year=? AND iso_week=?');
    $q->execute([$period['year'],$period['week']]);
}

function teamWeeklyGoal(array $period,array $teamRows): ?array {
    $q=db()->prepare('SELECT g.*,u.display_name updated_by_name FROM team_weekly_goals g LEFT JOIN users u ON u.id=g.updated_by WHERE g.iso_year=? AND g.iso_week=?');
    $q->execute([$period['year'],$period['week']]);$goal=$q->fetch();
    if(!$goal)return null;
    $totals=['unit'=>$goal['unit'],'distance_m'=>0.0,'moving_seconds'=>0,'completed_loops'=>0];
    foreach($teamRows as $row) {
        $totals['distance_m']+=(float)($row['distance_m']??0);
        $totals['moving_seconds']+=(int)($row['moving_seconds']??0);
        $totals['completed_loops']+=(int)($row['completed_loops']??0);
    }
    $live=weeklyGoalProgress($totals);$goal['progress']=max($live,(float)$goal['progress_value']);
    $target=(float)$goal['target_value'];$goal['percent']=min(100.0,$goal['progress']/$target*100);
    $goal['achieved']=$goal['achieved_at']!==null||$goal['progress']>=$target;
    $update=db()->prepare('UPDATE team_weekly_goals SET progress_value=GREATEST(progress_value,?),achieved_at=IF(achieved_at IS NULL AND ?>=target_value,UTC_TIMESTAMP(),achieved_at) WHERE iso_year=? AND iso_week=?');
    $update->execute([$goal['progress'],$goal['progress'],$period['year'],$period['week']]);
    return $goal;
}

function weeklyGoalRefreshCurrentAndPrevious(): void {
    $current=weeklyGoalPeriod();
    foreach([$current,weeklyGoalPeriod($current['start']->modify('-1 day'))] as $period) {
        $team=weeklyGoalTeam($period);teamWeeklyGoal($period,$team);
    }
}
