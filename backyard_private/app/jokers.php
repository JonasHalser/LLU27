<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/weekly_goals.php';

function jokerMonth(?DateTimeImmutable $now=null): array {
    $utc=new DateTimeZone('UTC');$berlin=new DateTimeZone('Europe/Berlin');
    $now=($now??new DateTimeImmutable('now',$utc))->setTimezone($berlin);
    $start=new DateTimeImmutable($now->format('Y-m-01 00:00:00'),$berlin);
    return [
        'year'=>(int)$start->format('Y'),
        'month'=>(int)$start->format('n'),
        'label'=>$start->format('m/Y'),
        'start'=>$start->setTimezone($utc),
        'end'=>$start->modify('+1 month')->setTimezone($utc),
    ];
}

function jokerTypeLabel(string $type): string {
    return match($type) {
        'weekly_boost'=>'Wochenziel +20 %',
        'run_30h'=>'Lauf innerhalb von 30 Stunden',
        'next_run_150'=>'Nächster Lauf mit 150 %',
        default=>'Unbekannter Joker',
    };
}

function jokerBoostedTarget(float $baseline,string $unit): float {
    $target=$baseline*1.2;
    return in_array($unit,['minutes','loops'],true)?(float)ceil($target):round($target,2);
}

function jokerDistanceTarget(float $average,string $type): float {
    $factor=match($type) {'run_30h'=>0.75,'next_run_150'=>1.5,default=>throw new RuntimeException('Ungültiger Distanz-Joker.')};
    return round($average*$factor,2);
}

function jokerAverageDistance(int $runnerId): float {
    $q=db()->prepare("SELECT AVG(GREATEST(a.distance_m,0)) FROM activities a JOIN users u ON u.id=a.user_id WHERE a.user_id=? AND a.activity_date>=CONCAT(u.start_date,' 00:00:00') AND TIMESTAMPADD(SECOND,GREATEST(a.moving_seconds,0),a.activity_date)<=UTC_TIMESTAMP() AND a.distance_m>0 AND a.cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY");
    $q->execute([$runnerId]);$average=(float)$q->fetchColumn();
    if($average<=0)throw new RuntimeException('Für diesen Läufer sind noch keine synchronisierten Läufe für einen Durchschnitt verfügbar.');
    return round($average,2);
}

function jokerCreate(int $supporterId,int $runnerId,string $type,?DateTimeImmutable $now=null): int {
    if(!in_array($type,['weekly_boost','run_30h','next_run_150'],true))throw new RuntimeException('Bitte einen gültigen Joker auswählen.');
    $utc=new DateTimeZone('UTC');$now=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc);
    $month=jokerMonth($now);$period=$type==='weekly_boost'?weeklyGoalPeriod($now):null;
    if($period!==null)weeklyGoalTeam($period);

    $p=db();$p->beginTransaction();
    try {
        $q=$p->prepare('SELECT id FROM supporters WHERE id=? FOR UPDATE');$q->execute([$supporterId]);
        if(!$q->fetchColumn())throw new RuntimeException('Supporter nicht gefunden.');
        $q=$p->prepare("SELECT id FROM users WHERE id=? AND username IN ('tim','jonas','noah')");$q->execute([$runnerId]);
        if(!$q->fetchColumn())throw new RuntimeException('Bitte einen gültigen Läufer auswählen.');
        $q=$p->prepare('SELECT id FROM supporter_jokers WHERE supporter_id=? AND joker_year=? AND joker_month=?');$q->execute([$supporterId,$month['year'],$month['month']]);
        if($q->fetchColumn())throw new RuntimeException('Du hast deinen Joker für diesen Monat bereits eingesetzt.');

        $unit='meters';$baseline=0.0;$target=0.0;$isoYear=null;$isoWeek=null;$deadline=null;
        if($type==='weekly_boost') {
            $q=$p->prepare('SELECT unit,target_value,progress_value FROM weekly_goals WHERE user_id=? AND iso_year=? AND iso_week=? FOR UPDATE');
            $q->execute([$runnerId,$period['year'],$period['week']]);$goal=$q->fetch();
            if(!$goal)throw new RuntimeException('Dieser Läufer hat für die aktuelle Woche noch kein persönliches Wochenziel.');
            $unit=(string)$goal['unit'];$baseline=(float)$goal['target_value'];$target=jokerBoostedTarget($baseline,$unit);
            if((float)$goal['progress_value']>=$target)throw new RuntimeException('Das erhöhte Wochenziel wurde bereits erreicht. Wähle einen anderen Joker.');
            $isoYear=$period['year'];$isoWeek=$period['week'];
            $deadlineAt=$period['end']<$month['end']?$period['end']:$month['end'];$deadline=$deadlineAt->format('Y-m-d H:i:s');
            $update=$p->prepare('UPDATE weekly_goals SET target_value=?,achieved_at=NULL,updated_at=UTC_TIMESTAMP() WHERE user_id=? AND iso_year=? AND iso_week=?');
            $update->execute([$target,$runnerId,$isoYear,$isoWeek]);
        } else {
            $baseline=jokerAverageDistance($runnerId);$target=jokerDistanceTarget($baseline,$type);
            $deadlineAt=$type==='run_30h'?$now->modify('+30 hours'):$month['end'];
            if($deadlineAt>$month['end'])$deadlineAt=$month['end'];
            $deadline=$deadlineAt->format('Y-m-d H:i:s');
        }

        $insert=$p->prepare('INSERT INTO supporter_jokers(supporter_id,runner_id,joker_year,joker_month,joker_type,unit,baseline_value,target_value,iso_year,iso_week,issued_at,deadline_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([$supporterId,$runnerId,$month['year'],$month['month'],$type,$unit,$baseline,$target,$isoYear,$isoWeek,$now->format('Y-m-d H:i:s'),$deadline]);
        $id=(int)$p->lastInsertId();$p->commit();return $id;
    } catch(Throwable $e) {if($p->inTransaction())$p->rollBack();throw $e;}
}

function jokerWeeklyGoalLocked(int $runnerId,array $period): bool {
    $q=db()->prepare("SELECT COUNT(*) FROM supporter_jokers WHERE runner_id=? AND joker_type='weekly_boost' AND iso_year=? AND iso_week=?");
    $q->execute([$runnerId,$period['year'],$period['week']]);
    return (int)$q->fetchColumn()>0;
}

function jokerAssertWeeklyGoalEditable(int $runnerId,array $period): void {
    if(jokerWeeklyGoalLocked($runnerId,$period))throw new RuntimeException('Dein Wochenziel wurde durch einen Supporter-Joker erhöht und kann in dieser Woche nicht mehr geändert oder gelöscht werden.');
}

function jokerResolve(int $id,string $status,?array $activity=null): void {
    if(!in_array($status,['succeeded','failed'],true))throw new RuntimeException('Ungültiger Joker-Status.');
    $q=db()->prepare('UPDATE supporter_jokers SET status=?,resolved_at=UTC_TIMESTAMP(),activity_id=?,activity_distance_m=? WHERE id=? AND status=\'active\'');
    $q->execute([$status,$activity['id']??null,$activity['distance_m']??null,$id]);
}

function jokerRefreshAll(): void {
    $p=db();$active=$p->query("SELECT * FROM supporter_jokers WHERE status='active' ORDER BY issued_at,id")->fetchAll();
    if(!$active)return;
    $periods=[];
    foreach($active as $joker)if($joker['joker_type']==='weekly_boost') {
        $key=$joker['iso_year'].'-'.$joker['iso_week'];
        $periods[$key]=weeklyGoalPeriod((new DateTimeImmutable('now',new DateTimeZone('UTC')))->setISODate((int)$joker['iso_year'],(int)$joker['iso_week'])->setTime(12,0));
    }
    foreach($periods as $period)weeklyGoalTeam($period);

    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $goal=$p->prepare('SELECT progress_value FROM weekly_goals WHERE user_id=? AND iso_year=? AND iso_week=?');
    $qualifying=$p->prepare("SELECT id,distance_m FROM activities WHERE user_id=? AND activity_date>=? AND TIMESTAMPADD(SECOND,GREATEST(moving_seconds,0),activity_date)<=? AND distance_m>=? AND cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY ORDER BY activity_date,id LIMIT 1");
    $next=$p->prepare("SELECT id,distance_m FROM activities WHERE user_id=? AND activity_date>=? AND TIMESTAMPADD(SECOND,GREATEST(moving_seconds,0),activity_date)<=LEAST(?,UTC_TIMESTAMP()) AND cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY ORDER BY activity_date,id LIMIT 1");
    foreach($active as $joker) {
        $type=(string)$joker['joker_type'];$target=(float)$joker['target_value'];
        if($type==='weekly_boost') {
            $goal->execute([(int)$joker['runner_id'],(int)$joker['iso_year'],(int)$joker['iso_week']]);$progress=(float)$goal->fetchColumn();
            if($progress>=$target)jokerResolve((int)$joker['id'],'succeeded');
            elseif($joker['deadline_at']!==null&&$now>=new DateTimeImmutable($joker['deadline_at'],new DateTimeZone('UTC')))jokerResolve((int)$joker['id'],'failed');
        } elseif($type==='run_30h') {
            $qualifying->execute([(int)$joker['runner_id'],$joker['issued_at'],$joker['deadline_at'],$target]);$activity=$qualifying->fetch();
            if($activity)jokerResolve((int)$joker['id'],'succeeded',$activity);
            elseif($now>=new DateTimeImmutable($joker['deadline_at'],new DateTimeZone('UTC')))jokerResolve((int)$joker['id'],'failed');
        } else {
            $next->execute([(int)$joker['runner_id'],$joker['issued_at'],$joker['deadline_at']]);$activity=$next->fetch();
            if($activity)jokerResolve((int)$joker['id'],(float)$activity['distance_m']>=$target?'succeeded':'failed',$activity);
            elseif($now>=new DateTimeImmutable($joker['deadline_at'],new DateTimeZone('UTC')))jokerResolve((int)$joker['id'],'failed');
        }
    }
}

function jokerAvailability(int $supporterId,?DateTimeImmutable $now=null): array {
    $month=jokerMonth($now);$q=db()->prepare('SELECT id FROM supporter_jokers WHERE supporter_id=? AND joker_year=? AND joker_month=?');
    $q->execute([$supporterId,$month['year'],$month['month']]);
    return ['available'=>!$q->fetchColumn(),'month'=>$month];
}

function jokerList(?int $runnerId=null,bool $supporterView=false): array {
    $runnerDisplay=$supporterView?"IF(sh.runner_id IS NULL,CONCAT(UPPER(LEFT(u.username,1)),SUBSTRING(u.username,2)),u.display_name)":"u.display_name";
    $sharing=$supporterView?' LEFT JOIN supporter_sharing sh ON sh.runner_id=u.id ':'';
    $q=db()->query("SELECT j.*,u.username,$runnerDisplay runner_name,COALESCE(s.display_name,'Ehemaliger Supporter') supporter_name FROM supporter_jokers j JOIN users u ON u.id=j.runner_id LEFT JOIN supporters s ON s.id=j.supporter_id $sharing ORDER BY (j.status='active') DESC,j.issued_at DESC,j.id DESC LIMIT 100");
    $rows=$q->fetchAll();
    foreach($rows as &$row)$row['is_own']=$runnerId!==null&&(int)$row['runner_id']===$runnerId;
    unset($row);return $rows;
}

function jokerValue(float $value,string $unit): string {
    return $unit==='meters'?number_format($value/1000,1,',','.').' km':weeklyGoalValue($value,$unit);
}

function jokerStatusLabel(string $status): string {
    return match($status) {'active'=>'Aktiv','succeeded'=>'Verteidigt','failed'=>'Nicht geschafft · −3 Punkte',default=>'Unbekannt'};
}

function jokerDate(string $utc): string {
    return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i').' Uhr';
}
