<?php
declare(strict_types=1);
require_once __DIR__.'/../partials/private-path.php';

require_once lluPrivatePath('app/supporter.php');
supporterSession(); $error=''; $username=''; $display='';
$privacy=cfg('SUPPORTER_PRIVACY_URL'); $terms=cfg('SUPPORTER_TERMS_URL');
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        verifyCsrf(); supporterLimit('register:'.($_SERVER['REMOTE_ADDR']??''),5,3600);
        if(!empty($_POST['website'])) throw new RuntimeException('Registrierung konnte nicht verarbeitet werden.');
        $username=supporterUsername((string)($_POST['username']??'')); $display=supporterDisplay((string)($_POST['display_name']??''),$username);
        supporterPassword((string)($_POST['password']??''),(string)($_POST['confirm']??''));
        if(($privacy!==''||$terms!=='') && empty($_POST['accept'])) throw new RuntimeException('Bitte Datenschutz und Nutzungsbedingungen bestätigen.');
        $p=db();$p->beginTransaction();
        try {
            $q=$p->prepare('INSERT INTO supporters(username,display_name,password_hash,coin_balance) VALUES(?,?,?,100)');
            $q->execute([$username,$display,password_hash((string)$_POST['password'],PASSWORD_DEFAULT)]);
            $p->prepare("INSERT INTO supporter_coin_ledger(supporter_id,amount,entry_type,reference_key) VALUES(?,100,'signup_grant','initial')")->execute([(int)$p->lastInsertId()]);
            $p->commit();
        } catch(PDOException $e) { if($p->inTransaction())$p->rollBack();if($e->getCode()==='23000') throw new RuntimeException('Dieser Benutzername ist bereits vergeben.'); throw $e; }
        redirect('supporter/login.php?registered=1');
    } catch(Throwable $e) { $error=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'Registrierung derzeit nicht möglich.'; }
}
supporterHead('Dabei sein'); ?>
<section class="panel auth"><p>Registriere dich kostenlos und feuere Tim, Jonas und Noah an. Als Startguthaben erhältst du einmalig 100 LLU Coins.</p><?php if($error): ?><p role="alert"><?=h($error)?></p><?php endif; ?>
<form method="post"><?php supporterField(); ?><label>Benutzername<input name="username" required minlength="3" maxlength="40" pattern="[a-zA-Z0-9][a-zA-Z0-9_\-]{2,39}" autocomplete="username" value="<?=h($username)?>"></label><small>3–40 Zeichen: a–z, 0–9, _ und -.</small><label>Anzeigename (optional)<input name="display_name" maxlength="80" value="<?=h($display)?>" autocomplete="nickname"></label><label>Passwort<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><label>Passwort bestätigen<input type="password" name="confirm" required minlength="12" autocomplete="new-password"></label><div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<?php if($privacy!==''||$terms!==''): ?><label><input type="checkbox" name="accept" required> Ich akzeptiere <?php if($privacy): ?><a href="<?=h($privacy)?>">Datenschutz</a><?php endif; ?> <?php if($terms): ?><a href="<?=h($terms)?>">Nutzungsbedingungen</a><?php endif; ?>.</label><?php endif; ?><button class="btn">Konto erstellen</button></form><p><a href="login.php">Bereits registriert? Anmelden</a></p></section><?php supporterFoot(); ?>
