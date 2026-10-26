<?php
declare(strict_types=1);
require_once __DIR__.'/supporter.php';

function supporterCoinPlaceWeekly(int $supporterId,int $runnerId,int $stake): void {
    $week=supporterWeek();
    if(!$week['open']) throw new RuntimeException('Der Wettpool für diese Woche ist bereits geschlossen.');
    $runnerIds=array_map('intval',array_column(supporterTeam(),'id'));
    if(!in_array($runnerId,$runnerIds,true)) throw new RuntimeException('Ungültiger Wochentipp.');
    if($stake<1||$stake>1000) throw new RuntimeException('Der Einsatz muss zwischen 1 und 1.000 Coins liegen.');
    $p=db();$p->beginTransaction();
    try {
        $account=$p->prepare('SELECT coin_balance FROM supporters WHERE id=? FOR UPDATE');$account->execute([$supporterId]);
        if($account->fetchColumn()===false) throw new RuntimeException('Supporter-Konto nicht gefunden.');
        $p->prepare("INSERT INTO supporter_coin_markets(market_type,iso_year,iso_week,closes_at) VALUES('weekly_winner',?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),closes_at=IF(settled_at IS NULL,VALUES(closes_at),closes_at)")->execute([$week['year'],$week['week'],$week['deadline']->format('Y-m-d H:i:s')]);
        $marketId=(int)$p->lastInsertId();
        $market=$p->prepare('SELECT settled_at,closes_at FROM supporter_coin_markets WHERE id=? FOR UPDATE');$market->execute([$marketId]);$state=$market->fetch();
        if(!$state||$state['settled_at']!==null||strtotime($state['closes_at'].' UTC')<=time()) throw new RuntimeException('Der Wettpool ist bereits geschlossen.');
        $debit=$p->prepare('UPDATE supporters SET coin_balance=coin_balance-? WHERE id=? AND coin_balance>=?');$debit->execute([$stake,$supporterId,$stake]);
        if($debit->rowCount()!==1) throw new RuntimeException('Dafür reicht dein Coin-Guthaben nicht.');
        $p->prepare('INSERT INTO supporter_coin_bets(market_id,supporter_id,option_key,stake) VALUES(?,?,?,?)')->execute([$marketId,$supporterId,(string)$runnerId,$stake]);
        $betId=(int)$p->lastInsertId();
        $p->prepare("INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key) VALUES(?,?,'wager',?)")->execute([$supporterId,-$stake,'bet:'.$betId]);
        $p->commit();
    } catch(Throwable $e) { if($p->inTransaction())$p->rollBack();throw $e; }
}

function supporterCoinWeeklyState(array $week,int $supporterId): array {
    $q=db()->prepare("SELECT id,closes_at,settled_at FROM supporter_coin_markets WHERE market_type='weekly_winner' AND iso_year=? AND iso_week=?");
    $q->execute([$week['year'],$week['week']]);$market=$q->fetch();
    if(!$market)return ['market'=>null,'pools'=>[],'mine'=>[]];
    $q=db()->prepare('SELECT option_key,SUM(stake) coins,COUNT(*) bets FROM supporter_coin_bets WHERE market_id=? GROUP BY option_key');$q->execute([$market['id']]);$pools=[];
    foreach($q->fetchAll() as $row)$pools[(int)$row['option_key']]=['coins'=>(int)$row['coins'],'bets'=>(int)$row['bets']];
    $q=db()->prepare('SELECT b.*,u.username FROM supporter_coin_bets b JOIN users u ON u.id=CAST(b.option_key AS UNSIGNED) WHERE b.market_id=? AND b.supporter_id=? ORDER BY b.placed_at DESC,b.id DESC');$q->execute([$market['id'],$supporterId]);
    return ['market'=>$market,'pools'=>$pools,'mine'=>$q->fetchAll()];
}

function supporterCoinSettle(array $market,string $winningOption,bool $refund=false): void {
    $p=db();$p->beginTransaction();
    try {
        $q=$p->prepare('SELECT * FROM supporter_coin_markets WHERE id=? FOR UPDATE');$q->execute([$market['id']]);$locked=$q->fetch();
        if(!$locked||$locked['settled_at']!==null){$p->commit();return;}
        $q=$p->prepare('SELECT * FROM supporter_coin_bets WHERE market_id=? ORDER BY id FOR UPDATE');$q->execute([$locked['id']]);$bets=$q->fetchAll();
        $winning=[];$winningStake=0;$pool=0;
        foreach($bets as $bet){$pool+=(int)$bet['stake'];if(!$refund&&$bet['option_key']===$winningOption){$winning[]=$bet;$winningStake+=(int)$bet['stake'];}}
        $payouts=[];
        if($refund||$winningStake===0){foreach($bets as $bet)$payouts[(int)$bet['id']]=(int)$bet['stake'];$refund=true;}
        else {
            $allocated=0;
            foreach($winning as $bet){$payout=intdiv((int)$bet['stake']*$pool,$winningStake);$payouts[(int)$bet['id']]=$payout;$allocated+=$payout;}
            $remainder=$pool-$allocated;
            foreach($winning as $bet){if($remainder<=0)break;$payouts[(int)$bet['id']]++;$remainder--;}
        }
        $updateBet=$p->prepare('UPDATE supporter_coin_bets SET payout=?,bonus=? WHERE id=?');
        $credit=$p->prepare('UPDATE supporters SET coin_balance=coin_balance+? WHERE id=?');
        $ledger=$p->prepare("INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key) VALUES(?,?,?,?)");
        foreach($bets as $bet){$id=(int)$bet['id'];$amount=$payouts[$id]??0;$bonus=(!$refund&&$amount>0&&$bet['option_key']===$winningOption)?intdiv((int)$bet['stake']*20,100):0;$updateBet->execute([$amount,$bonus,$id]);if(($amount>0||$bonus>0)&&$bet['supporter_id']!==null){$credit->execute([$amount+$bonus,$bet['supporter_id']]);if($amount>0)$ledger->execute([$bet['supporter_id'],$amount,$refund?'refund':'payout','bet:'.$id]);if($bonus>0)$ledger->execute([$bet['supporter_id'],$bonus,'bonus','bet-bonus:'.$id]);}}
        $p->prepare('UPDATE supporter_coin_markets SET winning_option=?,settled_at=UTC_TIMESTAMP() WHERE id=?')->execute([$refund?null:$winningOption,$locked['id']]);
        $p->commit();
    } catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

function supporterCoinSettleWeekly(array $history): void {
    $current=supporterWeek();
    if($current['open'])db()->prepare("UPDATE supporter_coin_markets SET closes_at=? WHERE market_type='weekly_winner' AND iso_year=? AND iso_week=? AND settled_at IS NULL AND closes_at<?")
        ->execute([$current['deadline']->format('Y-m-d H:i:s'),$current['year'],$current['week'],$current['deadline']->format('Y-m-d H:i:s')]);
    $markets=db()->query("SELECT * FROM supporter_coin_markets WHERE market_type='weekly_winner' AND settled_at IS NULL AND closes_at<=UTC_TIMESTAMP() ORDER BY id")->fetchAll();
    foreach($markets as $market){
        $key=sprintf('%04d-W%02d',$market['iso_year'],$market['iso_week']);$result=null;
        foreach($history as $week)if($week['week']===$key&&$week['complete']){$result=$week;break;}
        if($result===null)continue;
        if(!$result['winners']){supporterCoinSettle($market,'',true);continue;}
        $ids=array_map('intval',array_column(supporterTeam(),'id'));$winnerIds=[];
        foreach($ids as $id){$q=db()->prepare('SELECT username FROM users WHERE id=?');$q->execute([$id]);$name=$q->fetchColumn();if($name!==false&&in_array($name,$result['winners'],true))$winnerIds[]=$id;}
        $q=db()->prepare('SELECT DISTINCT option_key FROM supporter_coin_bets WHERE market_id=?');$q->execute([$market['id']]);$options=array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));
        $winning=array_intersect(array_map('strval',$winnerIds),$options);
        if(!$winning){supporterCoinSettle($market,'',true);continue;}
        // A tied week is treated as a winning pick for any tied runner. Their
        // winning stakes form one side and split the full pool proportionally.
        supporterCoinSettleOptions($market,array_values($winning));
    }
}

function supporterCoinSettleOptions(array $market,array $winningOptions): void {
    $p=db();$p->beginTransaction();
    try {
        $q=$p->prepare('SELECT * FROM supporter_coin_markets WHERE id=? FOR UPDATE');$q->execute([$market['id']]);$locked=$q->fetch();
        if(!$locked||$locked['settled_at']!==null){$p->commit();return;}
        $q=$p->prepare('SELECT * FROM supporter_coin_bets WHERE market_id=? ORDER BY id FOR UPDATE');$q->execute([$locked['id']]);$bets=$q->fetchAll();
        $winnerStakes=[];$pool=0;$winningStake=0;
        foreach($bets as $bet){$pool+=(int)$bet['stake'];if(in_array($bet['option_key'],$winningOptions,true)){$winnerStakes[]=$bet;$winningStake+=(int)$bet['stake'];}}
        if($winningStake===0){$p->commit();supporterCoinSettle($market,'',true);return;}
        $payouts=[];$allocated=0;
        foreach($winnerStakes as $bet){$payout=intdiv((int)$bet['stake']*$pool,$winningStake);$payouts[(int)$bet['id']]=$payout;$allocated+=$payout;}
        $remainder=$pool-$allocated;foreach($winnerStakes as $bet){if($remainder--<=0)break;$payouts[(int)$bet['id']]++;}
        $update=$p->prepare('UPDATE supporter_coin_bets SET payout=?,bonus=? WHERE id=?');$credit=$p->prepare('UPDATE supporters SET coin_balance=coin_balance+? WHERE id=?');$ledger=$p->prepare("INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key) VALUES(?,?,?,?)");
        foreach($bets as $bet){$id=(int)$bet['id'];$amount=$payouts[$id]??0;$bonus=$amount>0?intdiv((int)$bet['stake']*20,100):0;$update->execute([$amount,$bonus,$id]);if(($amount>0||$bonus>0)&&$bet['supporter_id']!==null){$credit->execute([$amount+$bonus,$bet['supporter_id']]);if($amount>0)$ledger->execute([$bet['supporter_id'],$amount,'payout','bet:'.$id]);if($bonus>0)$ledger->execute([$bet['supporter_id'],$bonus,'bonus','bet-bonus:'.$id]);}}
        $p->prepare('UPDATE supporter_coin_markets SET winning_option=?,settled_at=UTC_TIMESTAMP() WHERE id=?')->execute([implode(',',$winningOptions),$locked['id']]);$p->commit();
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

function supporterCoinHasOpenBets(int $supporterId): bool {
    $q=db()->prepare('SELECT COUNT(*) FROM supporter_coin_bets b JOIN supporter_coin_markets m ON m.id=b.market_id WHERE b.supporter_id=? AND m.settled_at IS NULL');$q->execute([$supporterId]);return (int)$q->fetchColumn()>0;
}

function supporterCoinPlaceMission(int $supporterId,int $missionId,int $runnerId,string $type,string $option,int $stake): void {
    if($type!=='mission_complete'||!in_array($option,['yes','no'],true))throw new RuntimeException('Es kann nur auf den Abschluss einer Mission gewettet werden.');
    if($stake<1||$stake>1000)throw new RuntimeException('Der Einsatz muss zwischen 1 und 1.000 Coins liegen.');
    if(!in_array($runnerId,array_map('intval',array_column(supporterTeam(),'id')),true))throw new RuntimeException('Ungültiger Läufer.');
    $p=db();$p->beginTransaction();
    try {
        $account=$p->prepare('SELECT coin_balance FROM supporters WHERE id=? FOR UPDATE');$account->execute([$supporterId]);
        if($account->fetchColumn()===false)throw new RuntimeException('Supporter-Konto nicht gefunden.');
        $q=$p->prepare('SELECT id,acceptance_mode,deadline_at,cancelled_at FROM supporter_missions WHERE id=? FOR UPDATE');$q->execute([$missionId]);$mission=$q->fetch();
        if(!$mission||$mission['cancelled_at']!==null||strtotime($mission['deadline_at'].' UTC')<=time())throw new RuntimeException('Diese Mission nimmt keine Einsätze mehr an.');
        $q=$p->prepare('SELECT accepted_at,completed_at FROM supporter_mission_entries WHERE mission_id=? AND runner_id=?');$q->execute([$missionId,$runnerId]);$entry=$q->fetch();
        if($type==='mission_complete'&&$entry&&$entry['completed_at']!==null)throw new RuntimeException('Der Missionsabschluss dieses Läufers ist bereits bekannt.');
        $p->prepare("INSERT INTO supporter_coin_markets(market_type,reference_id,subject_id,closes_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)")->execute([$type,$missionId,$runnerId,$mission['deadline_at']]);
        $marketId=(int)$p->lastInsertId();$q=$p->prepare('SELECT settled_at,closes_at FROM supporter_coin_markets WHERE id=? FOR UPDATE');$q->execute([$marketId]);$market=$q->fetch();
        if(!$market||$market['settled_at']!==null||strtotime($market['closes_at'].' UTC')<=time())throw new RuntimeException('Dieser Missionspool ist geschlossen.');
        $debit=$p->prepare('UPDATE supporters SET coin_balance=coin_balance-? WHERE id=? AND coin_balance>=?');$debit->execute([$stake,$supporterId,$stake]);
        if($debit->rowCount()!==1)throw new RuntimeException('Dafür reicht dein Coin-Guthaben nicht.');
        $p->prepare('INSERT INTO supporter_coin_bets(market_id,supporter_id,option_key,stake) VALUES(?,?,?,?)')->execute([$marketId,$supporterId,$option,$stake]);$betId=(int)$p->lastInsertId();
        $p->prepare("INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key) VALUES(?,?,'wager',?)")->execute([$supporterId,-$stake,'bet:'.$betId]);$p->commit();
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

function supporterCoinMissionStates(array $missions): array {
    if(!$missions)return [];$missionIds=array_map('intval',array_column($missions,'id'));$marks=implode(',',array_fill(0,count($missionIds),'?'));
    $q=db()->prepare("SELECT m.id,m.reference_id,m.market_type,m.subject_id,m.closes_at,m.settled_at,b.option_key,SUM(b.stake) coins FROM supporter_coin_markets m LEFT JOIN supporter_coin_bets b ON b.market_id=m.id WHERE m.reference_id IN ($marks) AND m.market_type='mission_complete' GROUP BY m.id,m.reference_id,m.market_type,m.subject_id,m.closes_at,m.settled_at,b.option_key");$q->execute($missionIds);$rows=$q->fetchAll();$states=[];
    foreach($rows as $row){$type=$row['market_type'];$runner=(int)$row['subject_id'];$key=(int)$row['reference_id'];$states[$key][$type][$runner]['market']=(int)$row['id'];$states[$key][$type][$runner]['settled_at']=$row['settled_at'];$states[$key][$type][$runner]['closes_at']=$row['closes_at'];$states[$key][$type][$runner]['totals'][$row['option_key']??'none']=(int)($row['coins']??0);}
    return $states;
}

function supporterCoinSettleMissionDue(): void {
    $markets=db()->query("SELECT m.*,sm.acceptance_mode,sm.deadline_at,sm.cancelled_at FROM supporter_coin_markets m LEFT JOIN supporter_missions sm ON sm.id=m.reference_id WHERE m.market_type IN ('mission_accept','mission_complete') AND m.settled_at IS NULL ORDER BY m.id")->fetchAll();
    foreach($markets as $market){
        if($market['cancelled_at']!==null){supporterCoinSettle($market,'',true);continue;}
        $q=db()->prepare('SELECT accepted_at,completed_at FROM supporter_mission_entries WHERE mission_id=? AND runner_id=?');$q->execute([$market['reference_id'],$market['subject_id']]);$entry=$q->fetch();$accepted=$entry!==false;$completed=$accepted&&$entry['completed_at']!==null;
        if($market['market_type']==='mission_accept'&&$accepted){supporterCoinSettle($market,'yes');continue;}
        if($market['market_type']==='mission_complete'&&$completed){supporterCoinSettle($market,'yes');continue;}
        if(strtotime($market['deadline_at'].' UTC')<=time())supporterCoinSettle($market,'no');
    }
}

function supporterCoinShareableRunners(): array {
    return db()->query("SELECT u.id,u.username,IF(s.runner_id IS NULL,CONCAT(UPPER(LEFT(u.username,1)),SUBSTRING(u.username,2)),u.display_name) display_name
        FROM users u JOIN supporter_sharing s ON s.runner_id=u.id
        WHERE u.username IN ('tim','jonas','noah') ORDER BY FIELD(u.username,'tim','jonas','noah')")->fetchAll();
}

function supporterCoinCreateCustomMarket(int $supporterId,int $runnerId,string $metric,float $target,string $deadlineInput): void {
    if(!in_array($metric,['distance_km','loops'],true))throw new RuntimeException('Wähle Kilometer oder vollständige Loops.');
    if(!is_finite($target)||$target<=0||$target>1000)throw new RuntimeException('Das Laufziel muss zwischen 0 und 1.000 liegen.');
    $target=round($target,2);
    $runners=supporterCoinShareableRunners();$runner=null;
    foreach($runners as $candidate)if((int)$candidate['id']===$runnerId){$runner=$candidate;break;}
    if(!$runner)throw new RuntimeException('Für diesen Läufer sind keine Supporter-Laufdaten freigegeben.');
    $berlin=new DateTimeZone('Europe/Berlin');$deadlineLocal=DateTimeImmutable::createFromFormat('Y-m-d\\TH:i',$deadlineInput,$berlin);$parse=DateTimeImmutable::getLastErrors();
    if(!$deadlineLocal||($parse!==false&&($parse['warning_count']||$parse['error_count']))||$deadlineLocal->format('Y-m-d\\TH:i')!==$deadlineInput)throw new RuntimeException('Bitte gib eine gültige Frist in Berliner Zeit ein.');
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));$deadlineUtc=$deadlineLocal->setTimezone(new DateTimeZone('UTC'));
    if($deadlineUtc<=$now->modify('+60 seconds'))throw new RuntimeException('Die Frist muss mindestens eine Minute in der Zukunft liegen.');
    if($deadlineUtc>$now->modify('+7 days'))throw new RuntimeException('Automatische Laufziele dürfen höchstens sieben Tage laufen.');
    $unitLabel=$metric==='distance_km'?'km':'Loops';$targetLabel=rtrim(rtrim(number_format($target,2,',','.'),'0'),',');$title=$runner['display_name'].' schafft bis '.$deadlineLocal->format('d.m.Y H:i').' Uhr mindestens '.$targetLabel.' '.$unitLabel;
    $p=db();$p->beginTransaction();
    try {
        $account=$p->prepare('SELECT id FROM supporters WHERE id=? FOR UPDATE');$account->execute([$supporterId]);
        if(!$account->fetchColumn())throw new RuntimeException('Supporter-Konto nicht gefunden.');
        $share=$p->prepare('SELECT runner_id FROM supporter_sharing WHERE runner_id=? FOR UPDATE');$share->execute([$runnerId]);
        if(!$share->fetchColumn())throw new RuntimeException('Die Supporter-Freigabe für diesen Läufer wurde widerrufen.');
        $p->prepare('INSERT INTO supporter_custom_markets(creator_id,runner_id,title,metric,target_value,starts_at,deadline_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(),?)')
            ->execute([$supporterId,$runnerId,$title,$metric,number_format($target,2,'.',''),$deadlineUtc->format('Y-m-d H:i:s')]);
        $customId=(int)$p->lastInsertId();
        $p->prepare("INSERT INTO supporter_coin_markets(market_type,reference_id,subject_id,closes_at) VALUES('custom_target',?,?,?)")
            ->execute([$customId,$runnerId,$deadlineUtc->format('Y-m-d H:i:s')]);
        $p->commit();
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

function supporterCoinPlaceCustomBet(int $supporterId,int $customMarketId,string $option,int $stake): void {
    if(!in_array($option,['yes','no'],true))throw new RuntimeException('Wähle Ja oder Nein.');
    if($stake<1||$stake>1000)throw new RuntimeException('Der Einsatz muss zwischen 1 und 1.000 Coins liegen.');
    supporterCoinRefreshCustomMarkets();
    $p=db();$p->beginTransaction();
    try{
        $account=$p->prepare('SELECT coin_balance FROM supporters WHERE id=? FOR UPDATE');$account->execute([$supporterId]);
        if($account->fetchColumn()===false)throw new RuntimeException('Supporter-Konto nicht gefunden.');
        $q=$p->prepare('SELECT * FROM supporter_custom_markets WHERE id=? FOR UPDATE');$q->execute([$customMarketId]);$custom=$q->fetch();
        if(!$custom||$custom['status']!=='open'||strtotime($custom['deadline_at'].' UTC')<=time())throw new RuntimeException('Dieses Laufziel nimmt keine Einsätze mehr an.');
        $q=$p->prepare("SELECT id,settled_at,closes_at FROM supporter_coin_markets WHERE market_type='custom_target' AND reference_id=? FOR UPDATE");$q->execute([$customMarketId]);$market=$q->fetch();
        if(!$market||$market['settled_at']!==null||strtotime($market['closes_at'].' UTC')<=time())throw new RuntimeException('Dieser Wettpool ist geschlossen.');
        $debit=$p->prepare('UPDATE supporters SET coin_balance=coin_balance-? WHERE id=? AND coin_balance>=?');$debit->execute([$stake,$supporterId,$stake]);
        if($debit->rowCount()!==1)throw new RuntimeException('Dafür reicht dein Coin-Guthaben nicht.');
        $p->prepare('INSERT INTO supporter_coin_bets(market_id,supporter_id,option_key,stake) VALUES(?,?,?,?)')->execute([$market['id'],$supporterId,$option,$stake]);$betId=(int)$p->lastInsertId();
        $p->prepare("INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key) VALUES(?,?,'wager',?)")->execute([$supporterId,-$stake,'bet:'.$betId]);$p->commit();
    }catch(Throwable $e){if($p->inTransaction())$p->rollBack();throw $e;}
}

function supporterCoinRefreshCustomMarkets(): void {
    $p=db();
    $p->exec("INSERT INTO supporter_custom_market_activities(custom_market_id,activity_id,activity_date,distance_m,completed_loops,observed_at)
        SELECT cm.id,a.id,a.activity_date,GREATEST(a.distance_m,0),FLOOR(GREATEST(a.distance_m,0)/6706),UTC_TIMESTAMP()
        FROM supporter_custom_markets cm
        JOIN supporter_sharing sh ON sh.runner_id=cm.runner_id
        JOIN activities a ON a.user_id=cm.runner_id AND a.activity_date>=cm.starts_at
            AND a.activity_date<=LEAST(cm.deadline_at,UTC_TIMESTAMP())
            AND TIMESTAMPADD(SECOND,GREATEST(a.moving_seconds,0),a.activity_date)<=LEAST(cm.deadline_at,UTC_TIMESTAMP())
        WHERE cm.status='open' AND cm.deadline_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY
            AND a.cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY
        ON DUPLICATE KEY UPDATE distance_m=VALUES(distance_m),completed_loops=VALUES(completed_loops),observed_at=UTC_TIMESTAMP()");
    $markets=$p->query("SELECT cm.*,m.id market_id,m.settled_at market_settled
        FROM supporter_custom_markets cm JOIN supporter_coin_markets m ON m.market_type='custom_target' AND m.reference_id=cm.id
        WHERE cm.status='open' ORDER BY cm.id")->fetchAll();
    foreach($markets as $custom){
        $share=$p->prepare('SELECT COUNT(*) FROM supporter_sharing WHERE runner_id=?');$share->execute([$custom['runner_id']]);
        if((int)$share->fetchColumn()===0){supporterCoinSettle(['id'=>(int)$custom['market_id']],'',true);$p->prepare("UPDATE supporter_custom_markets SET status='cancelled',resolved_at=UTC_TIMESTAMP() WHERE id=? AND status='open'")->execute([$custom['id']]);$p->prepare('DELETE FROM supporter_custom_market_activities WHERE custom_market_id=?')->execute([$custom['id']]);continue;}
        if($custom['market_settled']!==null)continue;
        $q=$p->prepare('SELECT COALESCE(SUM(distance_m),0) distance_m,COALESCE(SUM(completed_loops),0) loops FROM supporter_custom_market_activities WHERE custom_market_id=?');$q->execute([$custom['id']]);$totals=$q->fetch();
        $progress=$custom['metric']==='distance_km'?(float)$totals['distance_m']/1000:(float)$totals['loops'];
        if($progress>=(float)$custom['target_value']){supporterCoinSettle(['id'=>(int)$custom['market_id']],'yes');$p->prepare("UPDATE supporter_custom_markets SET status='succeeded',resolved_at=UTC_TIMESTAMP() WHERE id=? AND status='open'")->execute([$custom['id']]);$p->prepare('DELETE FROM supporter_custom_market_activities WHERE custom_market_id=?')->execute([$custom['id']]);}
        elseif(strtotime($custom['deadline_at'].' UTC')<=time()){supporterCoinSettle(['id'=>(int)$custom['market_id']],'no');$p->prepare("UPDATE supporter_custom_markets SET status='failed',resolved_at=UTC_TIMESTAMP() WHERE id=? AND status='open'")->execute([$custom['id']]);$p->prepare('DELETE FROM supporter_custom_market_activities WHERE custom_market_id=?')->execute([$custom['id']]);}
    }
}

function supporterCoinCustomMarkets(): array {
    return db()->query("SELECT cm.*,m.id market_id,m.winning_option,m.settled_at,
        COALESCE(s.display_name,'Ehemaliger Supporter') creator_name,
        IF(sh.runner_id IS NULL,CONCAT(UPPER(LEFT(u.username,1)),SUBSTRING(u.username,2)),u.display_name) runner_name,
        COALESCE(a.distance_m,0) progress_m,COALESCE(a.completed_loops,0) progress_loops
        FROM supporter_custom_markets cm
        JOIN supporter_coin_markets m ON m.market_type='custom_target' AND m.reference_id=cm.id
        LEFT JOIN supporters s ON s.id=cm.creator_id
        JOIN users u ON u.id=cm.runner_id
        LEFT JOIN supporter_sharing sh ON sh.runner_id=cm.runner_id
        LEFT JOIN (SELECT custom_market_id,SUM(distance_m) distance_m,SUM(completed_loops) completed_loops FROM supporter_custom_market_activities GROUP BY custom_market_id) a ON a.custom_market_id=cm.id
        ORDER BY (cm.status='open') DESC,cm.created_at DESC LIMIT 100")->fetchAll();
}

function supporterCoinLiveSnapshot(array $team): array {
    $teamNames=[];foreach($team as $runner)$teamNames[(int)$runner['id']]=$runner['display_name'];
    $rows=db()->query("SELECT m.id,m.market_type,m.reference_id,m.subject_id,m.iso_year,m.iso_week,m.closes_at,
        b.option_key,b.stake,b.placed_at,COALESCE(s.display_name,'Ehemaliger Supporter') supporter_name,
        cm.title custom_title,mi.title mission_title,u.username runner_username
        FROM supporter_coin_markets m
        LEFT JOIN supporter_coin_bets b ON b.market_id=m.id
        LEFT JOIN supporters s ON s.id=b.supporter_id
        LEFT JOIN supporter_custom_markets cm ON m.market_type='custom_target' AND cm.id=m.reference_id AND cm.status='open'
        LEFT JOIN supporter_missions mi ON m.market_type='mission_complete' AND mi.id=m.reference_id
        LEFT JOIN users u ON u.id=m.subject_id
        WHERE m.settled_at IS NULL AND m.market_type<>'mission_accept'
        ORDER BY m.id DESC,b.placed_at DESC,b.id DESC")->fetchAll();
    $result=[];
    foreach($rows as $row){$id=(int)$row['id'];$type=$row['market_type'];$key=(string)($row['option_key']??'');
        if(!isset($result[$id])){$runnerName=$teamNames[(int)$row['subject_id']]??$row['runner_username']??'Läufer';$title=match($type){'weekly_winner'=>'Wochensieger · '.sprintf('%04d-W%02d',$row['iso_year'],$row['iso_week']),'mission_complete'=>($row['mission_title']??'Mission').' · '.$runnerName,'custom_target'=>$row['custom_title']??'Laufziel',default=>'Wettpool'};$result[$id]=['id'=>$id,'type'=>$type,'title'=>$title,'closes_at'=>$row['closes_at'],'total'=>0,'options'=>[]];}
        if($key==='')continue;
        $label=$type==='weekly_winner'?($teamNames[(int)$key]??$row['runner_username']??'Läufer'):($key==='yes'?'Ja':'Nein');
        $result[$id]['options'][$key]??=['key'=>$key,'label'=>$label,'stake'=>0,'bets'=>[]];
        $result[$id]['options'][$key]['stake']+=(int)$row['stake'];$result[$id]['total']+=(int)$row['stake'];
        $result[$id]['options'][$key]['bets'][]=['supporter'=>$row['supporter_name'],'stake'=>(int)$row['stake'],'placed_at'=>$row['placed_at']];
    }
    foreach($result as &$market){foreach($market['options'] as &$option)$option['share']=$market['total']>0?round(100*$option['stake']/$market['total'],1):0;unset($option);}unset($market);
    return array_values($result);
}
