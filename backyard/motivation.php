<?php
declare(strict_types=1);
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/backyard_private/app/bootstrap.php';
require_once dirname($_SERVER['DOCUMENT_ROOT']) . '/backyard_private/app/motivation.php';

$u = requireUser();
$error = '';
$tableMissing = false;
$statusMessages = [
    'created' => 'Spruch hinzugefügt.',
    'updated' => 'Spruch gespeichert.',
    'deleted' => 'Spruch gelöscht.',
];
$success = $statusMessages[(string)($_GET['status'] ?? '')] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $action = (string)($_POST['action'] ?? '');
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'create') {
            motivationAdd((string)($_POST['quote'] ?? ''), (int)$u['id']);
            redirect('motivation.php?status=created');
        } elseif ($action === 'update' && $id > 0) {
            motivationUpdate($id, (string)($_POST['quote'] ?? ''), (int)$u['id']);
            redirect('motivation.php?status=updated');
        } elseif ($action === 'delete' && $id > 0) {
            motivationDelete($id);
            redirect('motivation.php?status=deleted');
        } else {
            throw new RuntimeException('Ungültige Aktion.');
        }
    } catch (Throwable $e) {
        $tableMissing = motivationTableMissing($e);
        $error = $tableMissing ? '' : $e->getMessage();
        $success = '';
    }
}

$quotes = [];
try {
    $quotes = motivationQuotes();
} catch (PDOException $e) {
    if (!motivationTableMissing($e)) {
        throw $e;
    }
    $tableMissing = true;
}
?>
<!doctype html>
<html lang="de"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>LLU 27 · Motivation</title>
<link rel="icon" href="assets/llu27-icon.png"><link rel="stylesheet" href="assets/style.css?v=motivation-1">
<meta name="theme-color" content="#101923">
<style>
.motivation-head{border:1px solid #354355;background:radial-gradient(circle at 95% 5%,#582b24 0,transparent 42%),linear-gradient(120deg,#1c2b3c,#111b29);border-radius:22px;padding:clamp(24px,4vw,42px);margin:30px 0 20px}
.motivation-head h1{font-size:clamp(36px,6vw,65px);line-height:1.04;letter-spacing:-.04em;margin:10px 0}.motivation-head h1 span{color:#fc4c02}
.motivation-head p:not(.eyebrow){color:#b4c3d2;max-width:680px}
.motivation-title{font-size:clamp(20px,3vw,26px);margin:0 0 6px}
.motivation-add{display:flex;gap:10px;margin-top:16px;flex-wrap:wrap}
.motivation-add input,.motivation-item input[type="text"]{flex:1 1 280px;min-width:0}
.motivation-list{display:grid;gap:12px;margin-top:18px}
.motivation-item{display:grid;grid-template-columns:42px 1fr;gap:14px;align-items:start;background:#142131;border:1px solid #354355;border-radius:14px;padding:15px}
.motivation-number{font-size:20px;font-weight:900;color:#ff875d;padding-top:9px;text-align:center}
.motivation-row{display:flex;gap:10px;flex-wrap:wrap}
.motivation-meta{font-size:11px;color:#9dadbd;margin:8px 0 0}
.motivation-hint{font-size:12px;color:#9dadbd;margin:10px 0 0}
.motivation-empty{color:#9dadbd;margin-top:16px}
.motivation-panel{margin-bottom:22px}
@media(max-width:560px){.motivation-item{grid-template-columns:1fr;gap:6px}.motivation-number{text-align:left;padding-top:0}.motivation-row button,.motivation-add button{flex:1 1 auto}}
</style><link rel="stylesheet" href="assets/llu-navigation.css?v=3"><script src="assets/llu-navigation.js?v=3" defer></script></head><body class="llu-layout">
<?php require __DIR__ . "/partials/navigation.php"; ?>
<div class="shell">
<header class="top"><div class="brand">LLU <em>27</em><span class="brand-caption"> / BACKYARD RUNNING</span></div><a href="dashboard.php" class="btn outline">Dashboard</a></header>

<section class="motivation-head"><p class="eyebrow">LLU 27 · MOTIVATION</p><h1>ONE MORE LOOP.<br><span>ONE MORE WORD.</span></h1><p>Diese Sprüche erscheinen auf dem Dashboard unter dem Countdown – Buchstabe für Buchstabe. Jeder im Team kann sie hinzufügen, bearbeiten und löschen.</p></section>

<?php if ($error): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if ($success): ?><p class="notice" role="status"><?= h($success) ?></p><?php endif; ?>

<?php if ($tableMissing): ?>
<section class="panel motivation-panel"><h2 class="motivation-title">Datenbank noch nicht eingerichtet</h2><p class="muted">Die Tabelle <code>motivation_quotes</code> fehlt. Bitte einmalig <code>backyard_private/sql/motivation_quotes.sql</code> in der Datenbank ausführen.</p></section>
<?php else: ?>
<section class="panel motivation-panel" aria-labelledby="motivation-add-heading">
 <p class="eyebrow">NEUER SPRUCH</p>
 <h2 class="motivation-title" id="motivation-add-heading">Spruch hinzufügen</h2>
 <form method="post" class="motivation-add">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="create">
  <input type="text" name="quote" maxlength="<?= MOTIVATION_MAX_LENGTH ?>" required placeholder="z. B. Eine Runde nach der anderen." aria-label="Neuer Spruch">
  <button class="btn" type="submit">Hinzufügen +</button>
 </form>
 <p class="motivation-hint">Ein Einzeiler, maximal <?= MOTIVATION_MAX_LENGTH ?> Zeichen.</p>
</section>

<section class="panel motivation-panel" aria-labelledby="motivation-list-heading">
 <p class="eyebrow">ALLE SPRÜCHE</p>
 <h2 class="motivation-title" id="motivation-list-heading">Aktuelle Sprüche (<?= h((string)count($quotes)) ?>)</h2>
 <?php if (!$quotes): ?>
  <p class="motivation-empty">Noch keine Sprüche vorhanden. Ohne Sprüche bleibt die Zeile auf dem Dashboard leer.</p>
 <?php else: ?>
 <div class="motivation-list">
 <?php foreach ($quotes as $i => $q): ?>
  <form method="post" class="motivation-item">
   <div class="motivation-number">#<?= h((string)($i + 1)) ?></div>
   <div>
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="id" value="<?= h((string)$q['id']) ?>">
    <div class="motivation-row">
     <input type="text" name="quote" value="<?= h($q['quote']) ?>" maxlength="<?= MOTIVATION_MAX_LENGTH ?>" required aria-label="Spruch <?= h((string)($i + 1)) ?> bearbeiten">
     <button class="btn" type="submit" name="action" value="update">Speichern</button>
     <button class="outline danger" type="submit" name="action" value="delete" formnovalidate onclick="return confirm('Diesen Spruch wirklich löschen?')">Löschen</button>
    </div>
    <p class="motivation-meta">
     Erstellt<?= $q['created_by_name'] ? ' von ' . h($q['created_by_name']) : '' ?> am <?= h(date('d.m.Y', strtotime((string)$q['created_at']))) ?>
     <?php if ($q['updated_at'] !== $q['created_at'] && $q['updated_by_name']): ?> · zuletzt bearbeitet von <?= h($q['updated_by_name']) ?> am <?= h(date('d.m.Y', strtotime((string)$q['updated_at']))) ?><?php endif; ?>
    </p>
   </div>
  </form>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
</section>
<?php endif; ?>
</div></body></html>
