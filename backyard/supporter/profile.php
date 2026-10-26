<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter.php');
require_once lluPrivatePath('app/supporter_coins.php');
$u=supporterRequire(); $error=''; $notice='';
if($_SERVER['REQUEST_METHOD']==='POST') try {
    verifyCsrf(); $action=(string)($_POST['action']??'');
    supporterLimit('profile:'.$u['id'],20,900);
    if($action==='name') {
        $name=supporterDisplay((string)($_POST['display_name']??''),$u['username']);
        db()->prepare('UPDATE supporters SET display_name=? WHERE id=?')->execute([$name,$u['id']]); $u['display_name']=$name; $notice='Anzeigename gespeichert.';
    } elseif(in_array($action,['password','delete'],true)) {
        if(!password_verify((string)($_POST['current_password']??''),$u['password_hash'])) throw new RuntimeException('Aktuelles Passwort ist falsch.');
        if($action==='password') {
            supporterPassword((string)($_POST['password']??''),(string)($_POST['confirm']??''));
            db()->prepare('UPDATE supporters SET password_hash=? WHERE id=?')->execute([password_hash((string)$_POST['password'],PASSWORD_DEFAULT),$u['id']]);
            supporterRevoke((int)$u['id']);redirect('supporter/login.php');
        }
        $p=db();$p->beginTransaction();
        $lock=$p->prepare('SELECT id FROM supporters WHERE id=? FOR UPDATE');$lock->execute([$u['id']]);
        if(!$lock->fetchColumn())throw new RuntimeException('Supporter-Konto nicht gefunden.');
        $open=$p->prepare('SELECT COUNT(*) FROM supporter_coin_bets b JOIN supporter_coin_markets m ON m.id=b.market_id WHERE b.supporter_id=? AND m.settled_at IS NULL');$open->execute([$u['id']]);
        if((int)$open->fetchColumn()>0)throw new RuntimeException('Dein Konto kann erst gelöscht werden, wenn alle offenen Wettpools ausgewertet sind.');
        $p->prepare('DELETE FROM supporter_cheers WHERE supporter_id=?')->execute([$u['id']]);
        $p->prepare('DELETE FROM supporters WHERE id=?')->execute([$u['id']]);$p->commit();
        supporterCookie();$_SESSION=[];session_regenerate_id(true);redirect('supporter/login.php');
    } elseif($action==='push-off') { db()->prepare('DELETE FROM push_subscriptions WHERE supporter_id=?')->execute([$u['id']]); $notice='Push auf allen Geräten deaktiviert.'; }
} catch(Throwable $e) { if(isset($p)&&$p instanceof PDO&&$p->inTransaction())$p->rollBack();$error=$e instanceof RuntimeException&&!($e instanceof PDOException)?$e->getMessage():'Änderung derzeit nicht möglich.'; }
supporterCoinSettleWeekly(supporterScore()['history']);supporterCoinSettleMissionDue();
$balance=db()->prepare('SELECT coin_balance FROM supporters WHERE id=?');$balance->execute([$u['id']]);$u['coin_balance']=(int)$balance->fetchColumn();
supporterHead('Dein Profil',$u); ?>
<?php if($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?><?php if($notice): ?><p role="status"><?=h($notice)?></p><?php endif; ?>
<?php $ledger=db()->prepare('SELECT amount,entry_type,created_at FROM supporter_coin_ledger WHERE supporter_id=? ORDER BY id DESC LIMIT 50');$ledger->execute([$u['id']]);$coinHistory=$ledger->fetchAll();$coinBets=db()->prepare("SELECT b.stake,b.payout,b.bonus,b.option_key,m.market_type,m.iso_year,m.iso_week,m.reference_id,m.winning_option,m.settled_at FROM supporter_coin_bets b JOIN supporter_coin_markets m ON m.id=b.market_id WHERE b.supporter_id=? ORDER BY b.id DESC LIMIT 50");$coinBets->execute([$u['id']]);$coinBetHistory=$coinBets->fetchAll(); ?>
<section id="coins" class="panel wallet-panel"><div class="wallet-head"><div><p class="eyebrow">DEIN KONTO</p><h2>LLU Coins</h2></div><strong class="wallet-balance"><?=h(number_format((int)$u['coin_balance']))?></strong></div><p>Bei der Registrierung bekommst du einmalig 100 Coins. Für deinen täglichen Login kommen <?=h(cfg('SUPPORTER_DAILY_COIN_REWARD','10'))?> Coins dazu. Einsätze werden beim Platzieren abgezogen; Gewinne, Wettboni und Rückerstattungen stehen hier im Kontoverlauf.</p><h3>Letzte Bewegungen</h3><?php if(!$coinHistory):?><p>Noch keine Kontobewegungen.</p><?php else:?><div class="tablewrap"><table><thead><tr><th>Datum (UTC)</th><th>Bewegung</th><th>Coins</th></tr></thead><tbody><?php foreach($coinHistory as $entry):$label=['signup_grant'=>'Startguthaben','daily_login'=>'Tagesbonus','wager'=>'Einsatz','payout'=>'Poolgewinn','bonus'=>'20-%-Wettbonus','refund'=>'Rückerstattung'][$entry['entry_type']]??'Buchung';?><tr><td><?=h($entry['created_at'])?></td><td><?=h($label)?></td><td class="<?=((int)$entry['amount']>0?'coin-positive':'coin-negative')?>"><?=((int)$entry['amount']>0?'+':'')?><?=h(number_format((int)$entry['amount']))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
<h3>Deine Einsätze</h3><?php if(!$coinBetHistory):?><p>Noch keine Einsätze.</p><?php else:?><div class="tablewrap"><table><thead><tr><th>Pool</th><th>Einsatz</th><th>Poolauszahlung</th><th>20-%-Bonus</th><th>Status</th></tr></thead><tbody><?php foreach($coinBetHistory as $bet):$label=$bet['market_type']==='weekly_winner'?'Wochensieger · KW '.$bet['iso_week'].'/'.$bet['iso_year']:($bet['market_type']==='mission_accept'?'Mission annimmt':'Mission schafft');?><?php if($bet['market_type']!=='weekly_winner')$label.=' #'.$bet['reference_id'];?><?php if($bet['market_type']==='weekly_winner'){$runner=db()->prepare('SELECT display_name FROM users WHERE id=?');$runner->execute([(int)$bet['option_key']]);$label.=' · '.($runner->fetchColumn()?:'Läufer');}else{$label.=' · '.($bet['option_key']==='yes'?'Ja':'Nein');}?><tr><td><?=h($label)?></td><td><?=h(number_format((int)$bet['stake']))?></td><td><?= $bet['payout']===null?'—':h(number_format((int)$bet['payout']))?></td><td><?=h(number_format((int)$bet['bonus']))?></td><td><?= $bet['settled_at']===null?'Offen':($bet['winning_option']===null?'Erstattet':((int)$bet['payout']===0?'Verloren':'Ausgezahlt'))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<section class="panel"><h2><?=h($u['username'])?></h2><form method="post"><?php supporterField(); ?><input type="hidden" name="action" value="name"><label>Anzeigename<input name="display_name" maxlength="80" value="<?=h($u['display_name'])?>"></label><button class="btn">Speichern</button></form></section>
<section class="panel"><h2>Passwort ändern</h2><form method="post"><?php supporterField(); ?><input type="hidden" name="action" value="password"><label>Aktuelles Passwort<input type="password" name="current_password" required autocomplete="current-password"></label><label>Neues Passwort<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Bestätigen<input type="password" name="confirm" required autocomplete="new-password"></label><button class="btn">Passwort ändern und überall abmelden</button></form></section>
<?php require dirname(__DIR__).'/partials/supporter-push.php'; ?>
<section class="panel"><h2>Konto löschen</h2><p>Dein Konto, deine Tipps, Anfeuerungen und Push-Abonnements werden dauerhaft gelöscht. Offene Coin-Einsätze müssen zuerst ausgewertet werden.</p><form method="post"><?php supporterField(); ?><input type="hidden" name="action" value="delete"><label>Aktuelles Passwort<input type="password" name="current_password" required autocomplete="current-password"></label><button class="btn">Konto endgültig löschen</button></form></section><?php supporterFoot(); ?>
