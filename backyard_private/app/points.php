<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/challenge_score.php';
require_once __DIR__.'/missions.php';

/** Combine challenge awards, mission awards and joker penalties into one team balance. */
function teamPointsBoard(bool $supporterView=false): array {
    $challengeRows=db()->query("SELECT u.username,w.* FROM challenge_weekly w JOIN users u ON u.id=w.user_id WHERE u.username IN ('tim','jonas','noah') ORDER BY w.iso_year DESC,w.iso_week DESC")->fetchAll();
    $score=challengeScore($challengeRows);

    try {
        $missionRows=missionPointsBoard($supporterView);
    } catch(PDOException $e) {
        if($e->getCode()!=='42S02') throw $e;
        $missionRows=[];
    }
    $missionByRunner=[];
    foreach($missionRows as $row) $missionByRunner[$row['username']]=$row;

    $board=[];
    foreach($score['board'] as $username=>$challengeRunner) {
        $missions=$missionByRunner[$username]??[];
        $challengePoints=(int)$challengeRunner['points'];
        $missionPoints=(int)($missions['mission_points']??0);
        $jokerPenalties=(int)($missions['penalties']??0);
        $board[$username]=array_merge($challengeRunner,[
            'challenge_points'=>$challengePoints,
            'mission_points'=>$missionPoints,
            'joker_penalties'=>$jokerPenalties,
            'completed_missions'=>(int)($missions['completed']??0),
            'failed_jokers'=>(int)($missions['failed_jokers']??0),
            'points'=>$challengePoints+$missionPoints-$jokerPenalties,
        ]);
    }
    $ranking=array_values($board);
    usort($ranking,static fn(array $a,array $b):int=>($b['points']<=>$a['points'])?:($b['challenge_points']<=>$a['challenge_points'])?:($b['loops']<=>$a['loops'])?:strcmp($a['name'],$b['name']));
    $score['board']=$board;
    $score['ranking']=$ranking;
    return $score;
}

/** Weekly cumulative balances with the source of each point change. */
function teamPointsTimeline(array $challengeScore): array {
    $events=[];
    foreach($challengeScore['history'] as $week) {
        foreach($challengeScore['people'] as $username=>$person) {
            $loops=(int)($week['members'][$username]['loops']??0);
            $bonus=in_array($username,$week['winners'],true)?5:0;
            $events[$week['week']][$username]['challenge']=($events[$week['week']][$username]['challenge']??0)+$loops+$bonus;
        }
    }

    try {
        $missions=db()->query("SELECT u.username,e.completed_at,m.points FROM supporter_mission_entries e JOIN supporter_missions m ON m.id=e.mission_id JOIN users u ON u.id=e.runner_id WHERE e.completed_at IS NOT NULL AND u.username IN ('tim','jonas','noah') AND (m.acceptance_mode='exclusive' OR (SELECT COUNT(*) FROM supporter_mission_entries team_entries WHERE team_entries.mission_id=m.id)>=2)")->fetchAll();
        foreach($missions as $row) {
            $key=(new DateTimeImmutable((string)$row['completed_at'],new DateTimeZone('UTC')))->format('o-\\WW');
            $username=(string)$row['username'];
            $events[$key][$username]['missions']=($events[$key][$username]['missions']??0)+(int)$row['points'];
        }
    } catch(PDOException $e) {
        if($e->getCode()!=='42S02') throw $e;
    }

    try {
        $jokers=db()->query("SELECT u.username,j.resolved_at,j.penalty_points FROM supporter_jokers j JOIN users u ON u.id=j.runner_id WHERE j.status='failed' AND j.resolved_at IS NOT NULL AND u.username IN ('tim','jonas','noah')")->fetchAll();
        foreach($jokers as $row) {
            $key=(new DateTimeImmutable((string)$row['resolved_at'],new DateTimeZone('UTC')))->format('o-\\WW');
            $username=(string)$row['username'];
            $events[$key][$username]['joker']=($events[$key][$username]['joker']??0)+(int)$row['penalty_points'];
        }
    } catch(PDOException $e) {
        if($e->getCode()!=='42S02') throw $e;
    }

    $current=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $currentKey=$current->format('o-\\WW');
    if(!$events) return ['labels'=>[],'series'=>[],'weeks'=>[]];
    $firstKey=min(array_keys($events));
    if(!preg_match('/^(\\d{4})-W(\\d{2})$/D',$firstKey,$m)) $firstKey=$currentKey;
    preg_match('/^(\\d{4})-W(\\d{2})$/D',$firstKey,$m);
    $cursor=(new DateTimeImmutable('now',new DateTimeZone('UTC')))->setISODate((int)$m[1],(int)$m[2])->setTime(0,0);
    $last=$current->setISODate((int)$current->format('o'),(int)$current->format('W'))->setTime(0,0);
    if($cursor>$last)$cursor=$last;

    $labels=[];$series=[];$weeks=[];$balances=[];
    foreach($challengeScore['people'] as $username=>$person) {
        $series[$username]=['name'=>$person['name'],'color'=>$person['color'],'values'=>[]];
        $balances[$username]=0;
    }
    while($cursor<=$last) {
        $key=$cursor->format('o-\\WW');
        $parts=explode('-W',$key);
        $label='KW '.(int)($parts[1]??0).' / '.($parts[0]??'');
        $members=[];
        foreach($challengeScore['people'] as $username=>$person) {
            $event=$events[$key][$username]??[];
            $challengePoints=(int)($event['challenge']??0);
            $missionPoints=(int)($event['missions']??0);
            $jokerPenalties=(int)($event['joker']??0);
            $change=$challengePoints+$missionPoints-$jokerPenalties;
            $balances[$username]+=$change;
            $members[$username]=[
                'challenge_points'=>$challengePoints,
                'mission_points'=>$missionPoints,
                'joker_penalties'=>$jokerPenalties,
                'change'=>$change,
                'balance'=>$balances[$username],
            ];
            $series[$username]['values'][]=$balances[$username];
        }
        $labels[]=$label;
        $weeks[]=['key'=>$key,'label'=>$label,'members'=>$members];
        $cursor=$cursor->modify('+7 days');
    }
    return ['labels'=>$labels,'series'=>$series,'weeks'=>$weeks];
}
