<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';

function missionInput(array $input,?DateTimeImmutable $now=null): array {
    $title=trim((string)($input['title']??''));
    $description=str_replace(["\r\n","\r"],"\n",trim((string)($input['description']??'')));
    if($title===''||mb_strlen($title)>120||preg_match('/[\x00-\x1f\x7f]/u',$title)) throw new RuntimeException('Der Titel muss 1–120 Zeichen lang sein.');
    if(mb_strlen($description)>500||preg_match('/[\x00-\x08\x0b-\x1f\x7f]/u',$description)) throw new RuntimeException('Die Beschreibung darf höchstens 500 Zeichen lang sein.');

    $unit=(string)($input['unit']??'');
    $limits=['km'=>10000.0,'minutes'=>129600.0,'loops'=>10000.0];
    $rawTarget=str_replace(',','.',trim((string)($input['target_value']??'')));
    if(!isset($limits[$unit])) throw new RuntimeException('Bitte eine gültige Missionsart auswählen.');
    if($rawTarget===''||!is_numeric($rawTarget)) throw new RuntimeException('Bitte ein gültiges Ziel eingeben.');
    $target=(float)$rawTarget;
    if(!is_finite($target)||$target<=0||$target>$limits[$unit]) throw new RuntimeException('Das Ziel liegt außerhalb des gültigen Bereichs.');
    if(in_array($unit,['minutes','loops'],true)&&floor($target)!==$target) throw new RuntimeException('Minuten und Loops müssen als ganze Zahl angegeben werden.');

    $rawPoints=(string)($input['points']??'');
    if(!preg_match('/^[1-5]$/D',$rawPoints)) throw new RuntimeException('Die Belohnung muss zwischen 1 und 5 Punkten liegen.');
    $mode=(string)($input['acceptance_mode']??'');
    if(!in_array($mode,['exclusive','all'],true)) throw new RuntimeException('Bitte einen gültigen Annahmemodus auswählen.');

    $berlin=new DateTimeZone('Europe/Berlin');
    $rawDeadline=trim((string)($input['deadline_at']??''));
    $deadline=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$rawDeadline,$berlin);
    if(!$deadline||$deadline->format('Y-m-d\TH:i')!==$rawDeadline) throw new RuntimeException('Bitte ein gültiges Enddatum mit Uhrzeit eingeben.');
    $utc=new DateTimeZone('UTC');
    $now=($now??new DateTimeImmutable('now',$utc))->setTimezone($utc);
    $deadlineUtc=$deadline->setTimezone($utc);
    if($deadlineUtc<$now->modify('+5 minutes')) throw new RuntimeException('Die Frist muss mindestens fünf Minuten in der Zukunft liegen.');
    if($deadlineUtc>$now->modify('+90 days')) throw new RuntimeException('Die Frist darf höchstens 90 Tage in der Zukunft liegen.');

    return [
        'title'=>$title,
        'description'=>$description,
        'unit'=>$unit,
        'target'=>round($target,2),
        'points'=>(int)$rawPoints,
        'mode'=>$mode,
        'deadline'=>$deadlineUtc->format('Y-m-d H:i:s'),
    ];
}

function missionCreate(int $supporterId,array $mission): int {
    $q=db()->prepare('INSERT INTO supporter_missions(supporter_id,title,description,unit,target_value,points,acceptance_mode,deadline_at) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$supporterId,$mission['title'],$mission['description'],$mission['unit'],$mission['target'],$mission['points'],$mission['mode'],$mission['deadline']]);
    return (int)db()->lastInsertId();
}

function missionCancel(int $supporterId,int $missionId): void {
    $p=db();$p->beginTransaction();
    try {
        $q=$p->prepare('SELECT supporter_id,cancelled_at,deadline_at>UTC_TIMESTAMP() AS is_open FROM supporter_missions WHERE id=? FOR UPDATE');$q->execute([$missionId]);$mission=$q->fetch();
        if(!$mission||(int)$mission['supporter_id']!==$supporterId) throw new RuntimeException('Mission nicht gefunden.');
        if($mission['cancelled_at']!==null) throw new RuntimeException('Diese Mission wurde bereits abgesagt.');
        if(!(bool)$mission['is_open']) throw new RuntimeException('Eine beendete Mission kann nicht mehr abgesagt werden.');
        $q=$p->prepare('SELECT completed_at FROM supporter_mission_entries WHERE mission_id=? FOR UPDATE');$q->execute([$missionId]);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $completedAt)if($completedAt!==null)throw new RuntimeException('Eine bereits erfüllte Mission kann nicht mehr abgesagt werden.');
        $p->prepare('UPDATE supporter_missions SET cancelled_at=UTC_TIMESTAMP() WHERE id=?')->execute([$missionId]);
        $p->commit();
    } catch(Throwable $e) {if($p->inTransaction())$p->rollBack();throw $e;}
}

function missionTeamRunner(int $runnerId): bool {
    $q=db()->prepare("SELECT COUNT(*) FROM users WHERE id=? AND username IN ('tim','jonas','noah')");$q->execute([$runnerId]);return (int)$q->fetchColumn()===1;
}

function missionAccept(int $missionId,int $runnerId): void {
    if(!missionTeamRunner($runnerId)) throw new RuntimeException('Nur Teamläufer können Missionen annehmen.');
    $p=db();$p->beginTransaction();
    try {
        $q=$p->prepare('SELECT *,deadline_at>UTC_TIMESTAMP() AS is_open FROM supporter_missions WHERE id=? FOR UPDATE');$q->execute([$missionId]);$mission=$q->fetch();
        if(!$mission||$mission['cancelled_at']!==null||!(bool)$mission['is_open']) throw new RuntimeException('Diese Mission ist nicht mehr verfügbar.');
        $q=$p->prepare('SELECT runner_id FROM supporter_mission_entries WHERE mission_id=?');$q->execute([$missionId]);$accepted=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        if(in_array($runnerId,$accepted,true)) throw new RuntimeException('Du hast diese Mission bereits angenommen.');
        if($mission['acceptance_mode']==='exclusive'&&$accepted) throw new RuntimeException('Diese Mission wurde bereits von einem anderen Läufer angenommen.');
        $p->prepare('INSERT INTO supporter_mission_entries(mission_id,runner_id,accepted_at) VALUES(?,?,UTC_TIMESTAMP())')->execute([$missionId,$runnerId]);
        $p->commit();
    } catch(Throwable $e) {if($p->inTransaction())$p->rollBack();throw $e;}
}

function missionRefreshRunner(int $runnerId): void {
    $p=db();
    $credit=$p->prepare("INSERT INTO supporter_mission_activities(mission_id,runner_id,activity_id,distance_m,moving_seconds,completed_loops,observed_at)
        SELECT e.mission_id,e.runner_id,a.id,GREATEST(a.distance_m,0),GREATEST(a.moving_seconds,0),FLOOR(GREATEST(a.distance_m,0)/6706),UTC_TIMESTAMP()
        FROM supporter_mission_entries e
        JOIN supporter_missions m ON m.id=e.mission_id
        JOIN activities a ON a.user_id=e.runner_id AND a.activity_date>=e.accepted_at
            AND TIMESTAMPADD(SECOND,GREATEST(a.moving_seconds,0),a.activity_date)<=LEAST(m.deadline_at,UTC_TIMESTAMP())
        WHERE e.runner_id=? AND e.completed_at IS NULL AND m.cancelled_at IS NULL AND m.deadline_at>=UTC_TIMESTAMP()-INTERVAL 8 DAY
            AND a.cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY
        ON DUPLICATE KEY UPDATE distance_m=VALUES(distance_m),moving_seconds=VALUES(moving_seconds),completed_loops=VALUES(completed_loops),observed_at=UTC_TIMESTAMP()");
    $credit->execute([$runnerId]);
    $q=$p->prepare("SELECT e.mission_id,e.runner_id,m.unit,m.target_value,
        COALESCE(SUM(CASE m.unit WHEN 'km' THEN a.distance_m/1000 WHEN 'minutes' THEN a.moving_seconds/60 WHEN 'loops' THEN a.completed_loops ELSE 0 END),0) progress
        FROM supporter_mission_entries e JOIN supporter_missions m ON m.id=e.mission_id
        LEFT JOIN supporter_mission_activities a ON a.mission_id=e.mission_id AND a.runner_id=e.runner_id
        WHERE e.runner_id=? AND e.completed_at IS NULL AND m.cancelled_at IS NULL AND m.deadline_at>=UTC_TIMESTAMP()-INTERVAL 8 DAY
        GROUP BY e.mission_id,e.runner_id,m.unit,m.target_value");
    $q->execute([$runnerId]);
    $save=$p->prepare('UPDATE supporter_mission_entries e JOIN supporter_missions m ON m.id=e.mission_id SET e.progress_value=CAST(? AS DECIMAL(12,2)),e.completed_at=IF(CAST(? AS DECIMAL(12,2))>=m.target_value,UTC_TIMESTAMP(),e.completed_at) WHERE e.mission_id=? AND e.runner_id=? AND m.cancelled_at IS NULL');
    foreach($q->fetchAll() as $row) {
        $progress=round((float)$row['progress'],2);
        $save->execute([$progress,$progress,(int)$row['mission_id'],$runnerId]);
    }
}

function missionRefreshAll(): void {
    $ids=db()->query('SELECT DISTINCT e.runner_id FROM supporter_mission_entries e JOIN supporter_missions m ON m.id=e.mission_id WHERE e.completed_at IS NULL AND m.cancelled_at IS NULL AND m.deadline_at>=UTC_TIMESTAMP()-INTERVAL 8 DAY')->fetchAll(PDO::FETCH_COLUMN);
    foreach($ids as $id) missionRefreshRunner((int)$id);
}

function missionList(?int $runnerId=null,bool $supporterView=false): array {
    $missions=db()->query("SELECT m.*,COALESCE(s.display_name,'Ehemaliger Supporter') supporter_name
        FROM supporter_missions m LEFT JOIN supporters s ON s.id=m.supporter_id
        ORDER BY (m.cancelled_at IS NULL AND m.deadline_at>UTC_TIMESTAMP()) DESC,m.deadline_at ASC,m.id DESC LIMIT 100")->fetchAll();
    if(!$missions)return [];
    $ids=array_map('intval',array_column($missions,'id'));$marks=implode(',',array_fill(0,count($ids),'?'));
    $display=$supporterView?"IF(s.runner_id IS NULL,CONCAT(UPPER(LEFT(u.username,1)),SUBSTRING(u.username,2)),u.display_name)":"u.display_name";
    $sharing=$supporterView?' LEFT JOIN supporter_sharing s ON s.runner_id=u.id ':'';
    $q=db()->prepare("SELECT e.*,u.username,$display display_name FROM supporter_mission_entries e JOIN users u ON u.id=e.runner_id $sharing WHERE e.mission_id IN ($marks) ORDER BY e.accepted_at");$q->execute($ids);
    $byMission=[];foreach($q->fetchAll() as $entry)$byMission[(int)$entry['mission_id']][]=$entry;
    $now=time();
    foreach($missions as &$mission) {
        $mission['entries']=$byMission[(int)$mission['id']]??[];$mission['own_entry']=null;
        foreach($mission['entries'] as $entry)if($runnerId!==null&&(int)$entry['runner_id']===$runnerId)$mission['own_entry']=$entry;
        $mission['expired']=strtotime($mission['deadline_at'].' UTC')<=$now;
        $mission['open']=$mission['cancelled_at']===null&&!$mission['expired'];
        $mission['can_accept']=$runnerId!==null&&$mission['open']&&$mission['own_entry']===null&&($mission['acceptance_mode']==='all'||count($mission['entries'])===0);
    }
    unset($mission);return $missions;
}

function missionPointsBoard(bool $supporterView=false): array {
    $display=$supporterView?"IF(s.runner_id IS NULL,CONCAT(UPPER(LEFT(u.username,1)),SUBSTRING(u.username,2)),u.display_name)":"u.display_name";
    $sharing=$supporterView?' LEFT JOIN supporter_sharing s ON s.runner_id=u.id ':'';
    $group=$supporterView?'u.id,u.username,u.display_name,s.runner_id':'u.id,u.username,u.display_name';
    return db()->query("SELECT u.id,u.username,$display display_name,CAST(COALESCE(mp.points,0) AS SIGNED)-CAST(COALESCE(jp.penalties,0) AS SIGNED) points,
        COALESCE(mp.completed,0) completed,COALESCE(jp.failed,0) failed_jokers,COALESCE(jp.penalties,0) penalties
        FROM users u $sharing
        LEFT JOIN (SELECT e.runner_id,SUM(m.points) points,COUNT(*) completed FROM supporter_mission_entries e JOIN supporter_missions m ON m.id=e.mission_id WHERE e.completed_at IS NOT NULL GROUP BY e.runner_id) mp ON mp.runner_id=u.id
        LEFT JOIN (SELECT runner_id,SUM(penalty_points) penalties,COUNT(*) failed FROM supporter_jokers WHERE status='failed' GROUP BY runner_id) jp ON jp.runner_id=u.id
        WHERE u.username IN ('tim','jonas','noah') GROUP BY $group,mp.points,mp.completed,jp.penalties,jp.failed ORDER BY points DESC,completed DESC,FIELD(u.username,'tim','jonas','noah')")->fetchAll();
}

function missionValue(float $value,string $unit): string {
    if($unit==='minutes') {
        $minutes=(int)round($value);$hours=intdiv($minutes,60);$rest=$minutes%60;
        return $hours>0?$hours.' Std.'.($rest?' '.$rest.' Min.':''):$minutes.' Min.';
    }
    if($unit==='loops')return (string)(int)round($value).' Loops';
    return number_format($value,1,',','.').' km';
}

function missionDeadline(string $utc): string {
    return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i').' Uhr';
}

function missionModeLabel(string $mode): string {
    return $mode==='exclusive'?'Exklusiv · nur der erste Läufer':'Offen · alle Läufer';
}
