<?php
// Shared LLU 27 navigation – only include in authenticated pages.
// All pages call requireUser() before including this file.
$lluPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
$lluLinks = [
  ['dashboard.php', 'Dashboard', '▦'],
  ['performance.php', 'Performance', '↗'],
  ['challenge.php', 'Challenge', '★'],
  ['motivation.php', 'Motivation', '✦'],
  ['profile.php', 'Mein Profil', '◉'],
];
?>
<button class="llu-menu-button" type="button" id="llu-menu-button" aria-controls="llu-sidebar" aria-expanded="false" aria-label="Navigation öffnen">
  <span aria-hidden="true">☰</span><span>LLU 27</span><span class="llu-menu-button-label">Menü</span>
</button>
<div class="llu-menu-backdrop" id="llu-menu-backdrop" hidden></div>
<aside class="llu-sidebar" id="llu-sidebar" aria-label="Hauptnavigation">
  <a class="llu-sidebar-brand" href="dashboard.php">
    <img src="assets/llu27-icon.png" alt="" width="44" height="44" loading="eager">
    <span><strong>LLU <b>27</b></strong><small>BACKYARD RUNNING</small></span>
  </a>
  <p class="llu-sidebar-section">TRAINING</p>
  <nav class="llu-sidebar-nav" aria-label="Bereiche">
  <?php foreach ($lluLinks as $lluLink): ?>
    <a href="<?=h($lluLink[0])?>" class="llu-nav-link<?= $lluPage === $lluLink[0] ? ' is-current' : '' ?>" <?= $lluPage === $lluLink[0] ? 'aria-current="page"' : '' ?>>
      <span class="llu-nav-icon" aria-hidden="true"><?=h($lluLink[2])?></span><span><?=h($lluLink[1])?></span>
    </a>
  <?php endforeach; ?>
  </nav>
  <div class="llu-sidebar-bottom">
    <span class="llu-user-label">Angemeldet als <strong><?=h((string)($u['display_name'] ?? $viewer['display_name'] ?? 'Athlet'))?></strong></span>
    <form method="post" action="logout.php">
      <input type="hidden" name="csrf" value="<?=h(csrf())?>">
      <button type="submit" class="llu-signout">Abmelden</button>
    </form>
    <span class="llu-sidebar-motto">ONE MORE LOOP. ONE MORE HOUR.</span>
  </div>
</aside>
