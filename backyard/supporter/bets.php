<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter.php');
require_once lluPrivatePath('app/supporter_coins.php');
require_once lluPrivatePath('app/missions.php');

$u=supporterRequire();$error='';
if($_SERVER['REQUEST_METHOD']==='POST')try{
    verifyCsrf();supporterLimit('bets:'.$u['id'],60,86400);
    $action=(string)($_POST['action']??'');
    if($action==='weekly-bet')supporterCoinPlaceWeekly((int)$u['id'],(int)($_POST['runner_id']??0),(int)($_POST['stake']??0));
    elseif($action==='mission-bet'){missionRefreshAll();supporterCoinSettleMissionDue();supporterCoinPlaceMission((int)$u['id'],(int)($_POST['mission_id']??0),(int)($_POST['runner_id']??0),(string)($_POST['market_type']??''),(string)($_POST['option']??''),(int)($_POST['stake']??0));}
    elseif($action==='custom-create')supporterCoinCreateCustomMarket((int)$u['id'],(int)($_POST['runner_id']??0),(string)($_POST['metric']??''),(float)($_POST['target']??0),(string)($_POST['deadline']??''));
    elseif($action==='custom-bet')supporterCoinPlaceCustomBet((int)$u['id'],(int)($_POST['custom_market_id']??0),(string)($_POST['option']??''),(int)($_POST['stake']??0));
    else throw new RuntimeException('Unbekannte Wettaktion.');
    redirect('supporter/bets.php?saved=1');
}catch(Throwable $e){$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Einsatz derzeit nicht möglich.';}

$week=supporterWeek();$team=supporterTeam();$score=supporterScore();
$active=[];$states=[];$weekly=[];$leaders=[];$shareable=[];$customMarkets=[];$liveMarkets=[];$setupError='';
try{
    missionRefreshAll();
    supporterCoinRefreshCustomMarkets();
    supporterCoinSettleWeekly($score['history']);
    supporterCoinSettleMissionDue();
    $active=array_values(array_filter(missionList(null,true),static fn($m)=>$m['open']));
    $states=supporterCoinMissionStates($active);
    $weekly=supporterCoinWeeklyState($week,(int)$u['id']);
    $shareable=supporterCoinShareableRunners();
    $customMarkets=supporterCoinCustomMarkets();
    $liveMarkets=supporterCoinLiveSnapshot($team);
    $leaders=db()->query("SELECT s.id,s.display_name,
        SUM(COALESCE(b.payout,0)+COALESCE(b.bonus,0)) won,
        SUM(COALESCE(b.bonus,0)) bonus,
        COUNT(CASE WHEN b.payout>0 THEN 1 END) winning_bets
        FROM supporters s
        JOIN supporter_coin_bets b ON b.supporter_id=s.id
        JOIN supporter_coin_markets m ON m.id=b.market_id
        WHERE m.settled_at IS NOT NULL AND m.winning_option IS NOT NULL
        GROUP BY s.id,s.display_name
        ORDER BY won DESC,winning_bets DESC,s.display_name ASC LIMIT 10")->fetchAll();
}catch(PDOException $e){$setupError='Das Wettportal benötigt die Supporter-, Missions- und Coin-Migrationen.';}
$canBetWeekly=$week['open']&&(($weekly['market']['settled_at']??null)===null);
$customDefaultDeadline=(new DateTimeImmutable('+5 days',new DateTimeZone('Europe/Berlin')))->setTime(20,0)->format('Y-m-d\\TH:i');
$customStateLabels=['open'=>'Offen','succeeded'=>'Ziel geschafft','failed'=>'Ziel verfehlt','cancelled'=>'Abgebrochen'];

supporterHead('Wettportal',$u);?>
<?php if($error):?><p role="alert"><?=h($error)?></p><?php endif;?>
<?php if(isset($_GET['saved'])):?><p role="status">Gespeichert.</p><?php endif;?>
<?php if($setupError):?><p role="alert"><?=h($setupError)?></p><?php else:?>
<section class="mission-hero"><p class="eyebrow">SUPPORTER · COIN-POOLS</p><h2>Tippen, mitfiebern, gewinnen</h2><p>Die Grafiken und Einsätze anderer Supporter aktualisieren sich automatisch. Wer richtig liegt, erhält einen anteiligen Poolgewinn plus 20&nbsp;% Bonus auf den eigenen Einsatz. Bonusbruchteile werden auf ganze Coins abgerundet.</p></section>

<section class="mission-betting-panel"><h2>Live · alle offenen Wettpools</h2><p class="mission-note">Einsätze und Verteilung aktualisieren sich alle 15 Sekunden.</p><div class="bet-live-board" data-live-board aria-live="polite"><p class="mission-note">Lade offene Wettpools …</p></div></section>

<section class="mission-betting-panel"><h2>Wochensieger · <?=h($week['key'])?></h2><p class="mission-note">Tipps sind bis Mittwoch, <?=h($week['deadline']->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i'))?> Uhr Berliner Zeit möglich. Bei Gleichstand zählen alle Wochensieger; ohne richtige Tipps werden Einsätze erstattet.</p>
<?php if(!empty($weekly['market']['id'])):?><div class="bet-live" data-market-live="<?=h($weekly['market']['id'])?>" aria-live="polite"><p class="mission-note">Lade aktuelle Einsätze …</p></div><?php else:?><p>Noch kein Einsatz im Pool. Der erste Tipp eröffnet ihn.</p><?php endif;?>
<?php if($canBetWeekly):?><form method="post" class="coin-bet-form"><?php supporterField();?><input type="hidden" name="action" value="weekly-bet"><label>Wochensieger<select name="runner_id" required><?php foreach($team as $runner):?><option value="<?=h($runner['id'])?>"><?=h($runner['display_name'])?></option><?php endforeach;?></select></label><label>Einsatz in Coins<input type="number" name="stake" min="1" max="1000" step="1" value="10" required></label><button class="btn" type="submit">Coins setzen</button></form><?php else:?><p class="mission-note">Der Wochensieger-Pool ist geschlossen.</p><?php endif;?>
<?php if($weekly['mine']):?><h3>Deine Tipps</h3><?php foreach($weekly['mine'] as $bet):?><p><?=h($bet['username'])?> · <?=h($bet['stake'])?> Coins<?php if($bet['payout']!==null):?> · <?=h($bet['payout'])?> Poolauszahlung + <?=h($bet['bonus']??0)?> Bonus<?php endif;?></p><?php endforeach;?><?php endif;?></section>

<section class="mission-betting-panel"><h2>Missionsabschluss</h2><p class="mission-note">Setze darauf, ob ein Läufer eine aktive Mission bis zur Frist schafft. Annahmen von Missionen sind nicht bewettbar.</p>
<?php if(!$active):?><p class="mission-note">Aktuell gibt es keine offenen Missionen.</p><?php endif;?>
<?php foreach($active as $mission):?><article class="bet-mission"><h3><?=h($mission['title'])?></h3><p class="mission-note">Frist: <?=h(missionDeadline($mission['deadline_at']))?></p><?php foreach($team as $runner):$rid=(int)$runner['id'];$state=$states[(int)$mission['id']]['mission_complete'][$rid]??[];$known=false;foreach($mission['entries'] as $entry)if((int)$entry['runner_id']===$rid&&$entry['completed_at']!==null)$known=true;?><div class="mission-bet-row"><div><strong><?=h($runner['display_name'])?></strong><?php if(!empty($state['market'])):?><div class="bet-live" data-market-live="<?=h($state['market'])?>" aria-live="polite"><p class="mission-note">Lade aktuelle Einsätze …</p></div><?php else:?><p class="mission-note">Pool startet mit dem ersten Tipp.</p><?php endif;?></div><?php if(!$known):?><form method="post"><?php supporterField();?><input type="hidden" name="action" value="mission-bet"><input type="hidden" name="mission_id" value="<?=h($mission['id'])?>"><input type="hidden" name="runner_id" value="<?=h($rid)?>"><input type="hidden" name="market_type" value="mission_complete"><label>Tipp<select name="option"><option value="yes">Schafft sie</option><option value="no">Schafft sie nicht</option></select></label><label>Einsatz<input type="number" name="stake" min="1" max="1000" value="10" required></label><button class="btn" type="submit">Coins setzen</button></form><?php else:?><span class="muted">Mission bereits geschafft</span><?php endif;?></div><?php endforeach;?></article><?php endforeach;?></section>

<section class="mission-betting-panel"><h2>Eigenes Laufziel erstellen</h2><p class="mission-note">Eröffne einen automatisch ausgewerteten Pool: Läufer, Ziel und Frist festlegen, anschließend können alle Supporter auf Ja oder Nein setzen. Es sind nur Ziele für Läufer mit freigegebenen Supporter-Laufdaten möglich; Fristen liegen höchstens sieben Tage voraus.</p>
<?php if($shareable):?><form method="post" class="custom-market-form"><?php supporterField();?><input type="hidden" name="action" value="custom-create"><label>Läufer<select name="runner_id" required><?php foreach($shareable as $runner):?><option value="<?=h($runner['id'])?>"><?=h($runner['display_name'])?></option><?php endforeach;?></select></label><label>Zielwert<input type="number" name="target" min="0.1" max="1000" step="0.1" value="100" required></label><label>Einheit<select name="metric"><option value="distance_km">Kilometer</option><option value="loops">vollständige Loops</option></select></label><label>Frist (Berliner Zeit)<input type="datetime-local" name="deadline" min="<?=h((new DateTimeImmutable('+1 minute',new DateTimeZone('Europe/Berlin')))->format('Y-m-d\\TH:i'))?>" max="<?=h((new DateTimeImmutable('+7 days',new DateTimeZone('Europe/Berlin')))->format('Y-m-d\\TH:i'))?>" value="<?=h($customDefaultDeadline)?>" required></label><button class="btn" type="submit">Wettpool eröffnen</button></form><?php else:?><p class="mission-note">Ein Laufziel ist erst möglich, wenn mindestens ein Läufer seine Laufdaten für Supporter freigegeben hat.</p><?php endif;?></section>

<section class="mission-betting-panel"><h2>Eigene Laufziele</h2><?php if(!$customMarkets):?><p class="mission-note">Noch keine selbst erstellten Laufziele.</p><?php endif;?><?php foreach($customMarkets as $custom):$progress=$custom['metric']==='distance_km'?(float)$custom['progress_m']/1000:(float)$custom['progress_loops'];$target=(float)$custom['target_value'];$percent=$target>0?min(100,100*$progress/$target):0;?><article class="custom-market-card"><div class="custom-market-head"><div><h3><?=h($custom['title'])?></h3><p class="mission-note">Eröffnet von <?=h($custom['creator_name'])?> · Zielperson: <?=h($custom['runner_name'])?></p></div><strong class="custom-market-status status-<?=h($custom['status'])?>"><?=h($customStateLabels[$custom['status']]??$custom['status'])?></strong></div><?php if($custom['status']==='open'):?><p class="mission-note">Fortschritt bisher: <?=h(number_format($progress,1,',','.'))?> / <?=h(number_format($target,1,',','.'))?> <?=h($custom['metric']==='distance_km'?'km':'Loops')?> · Frist <?=h((new DateTimeImmutable($custom['deadline_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i'))?> Uhr</p><div class="goal-progress"><span style="width:<?=h((string)round($percent,1))?>%"></span></div><div class="bet-live" data-market-live="<?=h($custom['market_id'])?>" aria-live="polite"><p class="mission-note">Lade aktuelle Einsätze …</p></div><form method="post" class="coin-bet-form"><?php supporterField();?><input type="hidden" name="action" value="custom-bet"><input type="hidden" name="custom_market_id" value="<?=h($custom['id'])?>"><label>Tipp<select name="option"><option value="yes">Ziel erreicht</option><option value="no">Ziel verfehlt</option></select></label><label>Einsatz in Coins<input type="number" name="stake" min="1" max="1000" value="10" required></label><button class="btn" type="submit">Coins setzen</button></form><?php else:?><p class="mission-note">Ergebnis: <?=$custom['status']==='succeeded'?'Ziel erreicht':'Ziel nicht erreicht'?> · Frist <?=h((new DateTimeImmutable($custom['deadline_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i'))?> Uhr</p><?php endif;?></article><?php endforeach;?></section>

<section class="mission-betting-panel"><h2>Supporter mit den höchsten Gewinnen</h2><p class="mission-note">Sortiert nach ausgezahlten Wettgewinnen einschließlich Poolanteil und 20-%-Bonus.</p><?php if(!$leaders):?><p class="mission-note">Noch keine Wette wurde ausgewertet.</p><?php else:?><div class="supporter-winners"><?php foreach($leaders as $i=>$leader):?><article class="supporter-winner"><span>#<?=h($i+1)?> · <?=h($leader['display_name'])?></span><strong><?=h(number_format((int)$leader['won']))?> Coins gewonnen</strong><small><?=h($leader['winning_bets'])?> richtige Tipps · darin <?=h(number_format((int)$leader['bonus']))?> Bonus-Coins</small></article><?php endforeach;?></div><?php endif;?></section>
<?php endif;?><script src="<?=h(url('supporter/bets-live.js?v=1'))?>" defer></script><?php supporterFoot();
