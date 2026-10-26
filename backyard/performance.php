<?php
declare(strict_types=1);
require_once __DIR__.'/partials/private-path.php';

require_once lluPrivatePath('app/bootstrap.php');
$u=requireUser();$uid=(int)$u['id'];
// Nur eigene Aktivitäten und Details – keine Weitergabe von Roh-HF-Streams an andere Nutzer.
$q=db()->prepare("SELECT a.id,a.name,a.activity_date,a.distance_m,a.moving_seconds,a.avg_hr,p.detail_json
  FROM activities a LEFT JOIN activity_performance p ON p.activity_id=a.id AND p.user_id=a.user_id
  WHERE a.user_id=? AND a.activity_date>=CONCAT(?,' 00:00:00') AND a.cache_updated_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY
    AND a.avg_hr IS NOT NULL AND a.avg_hr>0
  ORDER BY a.activity_date DESC LIMIT 250");
$q->execute([$uid,$u['start_date']]);$activities=$q->fetchAll();
$byId=[];foreach($activities as $r)$byId[(string)$r['id']]=$r;
$id=(string)($_GET['activity']??'');if(!isset($byId[$id]))$id=(string)($activities[0]['id']??'');
$selected=$byId[$id]??null;
$details=$selected && $selected['detail_json'] ? json_decode((string)$selected['detail_json'],true) : null;
$longest=null;$fastest=null;$maxKm=0.0;
foreach($activities as $r){
 $km=(float)$r['distance_m']/1000;
 if($km>$maxKm){$longest=$r;$maxKm=$km;}
 if($km>=1 && (int)$r['moving_seconds']>0){
  $pace=(int)$r['moving_seconds']/$km;
  if($fastest===null || $pace<$fastest['pace'])$fastest=['pace'=>$pace,'run'=>$r];
 }
}
$splitArr=$details['splits']??[];$zoneArr=$details['zones']??[];$drift=$details['drift']??null;
function ll27Pace(int $seconds):string {return sprintf('%d:%02d',intdiv($seconds,60),$seconds%60);}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LLU 27 · Performance</title>
<link rel="stylesheet" href="assets/style.css?v=performance-1"><link rel="apple-touch-icon" href="assets/apple-touch-icon-v4.png"><link rel="icon" href="assets/llu27-icon.png"><meta name="theme-color" content="#101923">
<style>
.perf-head{display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap;margin:26px 0}.perf-head h1{margin:0;font-size:clamp(28px,5vw,48px)}.perf-head h1 span{color:#fc4c02}.perf-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:20px 0}.perf-card{background:#1a2635;border:1px solid #2c3948;border-radius:15px;padding:22px}.perf-card strong{display:block;color:#fff;font-size:clamp(22px,3vw,31px);margin-top:7px}.perf-card small{color:#9dadbd}.perf-panels{display:grid;grid-template-columns:1fr 1fr;gap:16px}.perf-panels .panel{min-width:0}.perf-panel-wide{grid-column:1/-1}.perf-select{width:100%;max-width:650px;color:white;background:#101c29;border:1px solid #455466;border-radius:10px;padding:12px}.perf-splits{width:100%;border-collapse:collapse}.perf-splits td,.perf-splits th{padding:10px;text-align:left;border-bottom:1px solid #344457}.perf-empty{color:#9dadbd}.perf-pill{display:inline-block;background:#293a4d;padding:6px 12px;border-radius:99px;color:#ff875d;font-weight:700;font-size:12px}.perf-chart{height:280px}.perf-note{color:#9dadbd;font-size:12px;line-height:1.6}@media(max-width:850px){.perf-grid{grid-template-columns:1fr}.perf-panels{grid-template-columns:1fr}.perf-panel-wide{grid-column:auto}}
</style><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js" defer></script><script src="performance.js?v=2" defer></script><link rel="stylesheet" href="assets/llu-navigation.css?v=2"><script src="assets/llu-navigation.js?v=2" defer></script></head>
<body class="llu-layout">
<?php require __DIR__ . "/partials/navigation.php"; ?>
<div class="shell"><header class="top"><div class="brand"><img src="assets/llu27-icon.png" alt="LLU 27" width="44" height="44" style="border-radius:11px;vertical-align:middle;margin-right:8px">LLU <em>27</em> / PERFORMANCE</div><nav style="display:flex;gap:10px;flex-wrap:wrap"><a class="btn outline" href="dashboard.php">← Dashboard</a><a class="btn outline" href="profile.php">Mein Profil</a></nav></header>
<div class="perf-head"><div><p class="eyebrow">LLU 27 · BACKYARD PERFORMANCE</p><h1>RUN SMARTER. <span>LAST LONGER.</span></h1><p class="muted">Laufeffizienz, HF-Zonen, Kilometer-Splits und persönliche Rekorde</p></div><span class="perf-pill">6,706 KM · ONE MORE LOOP</span></div>
<section class="perf-grid"><article class="perf-card"><small>LÄNGSTER LAUF IM CACHE</small><strong><?= $longest?h(number_format($maxKm,1,',','.')).' km':'–' ?></strong><small><?= $longest?h($longest['name']):'Keine Läufe' ?></small></article><article class="perf-card"><small>SCHNELLSTE Ø PACE AB 1 KM</small><strong><?= $fastest? h(ll27Pace((int)round($fastest['pace']))).' min/km':'–' ?></strong><small>Nur erfasste Aktivitäten, nicht Bestzeiten je Distanz</small></article><article class="perf-card"><small>BACKYARD-RUNDENÄQUIVALENTE</small><strong><?=h(number_format(array_sum(array_map(static fn($a)=>(float)$a['distance_m'],$activities))/6706,1,',','.'))?></strong><small>Erfasste Distanz / 6,706 km</small></article></section>
<section class="panel" style="margin-bottom:18px"><h2>Aktivität analysieren</h2><p class="muted">Wähle einen deiner Läufe mit erfasster Ø-Herzfrequenz. Detaildaten werden im Cronjob schrittweise geladen.</p>
<form method="get"><select class="perf-select" name="activity" onchange="this.form.submit()"><option value="">Lauf auswählen</option><?php foreach($activities as $r):?><option value="<?=h($r['id'])?>" <?= $id===(string)$r['id']?'selected':'' ?>><?=h(date('d.m.Y',strtotime($r['activity_date'])).' · '.$r['name'].' · '.number_format((float)$r['distance_m']/1000,1,',','.').' km')?></option><?php endforeach;?></select><noscript><button class="btn">Anzeigen</button></noscript></form></section>
<?php if(!$details):?><section class="panel"><h2>Noch keine Detaildaten</h2><p class="muted">Die Aktivität ist im Cache, aber der Stream-Abruf steht noch aus oder liefert keine unterstützten Messwerte. Bei alten Läufen kann der Detailabruf aus Budgetgründen länger dauern.</p></section><?php else:?>
<div class="perf-panels"><article class="panel"><h2>Herzfrequenzzonen</h2><p class="muted">Geschätzte Zonen anhand deiner HFmax-Einstellung von <?=h((string)$details['hr_max_reference'])?> bpm</p><div class="perf-chart"><canvas id="perf-zones"></canvas></div><p class="perf-note">Z1 &lt; 60 %, Z2 60–69 %, Z3 70–79 %, Z4 80–89 %, Z5 ≥90 % der eingestellten HFmax. Keine medizinische Klassifikation.</p></article>
<article class="panel"><h2>HF-Drift &amp; Effizienz</h2><p class="muted">Vergleich HF / Geschwindigkeit der ersten und zweiten Distanzhälfte</p><strong style="display:block;font-size:clamp(28px,4vw,48px);color:#fc4c02;margin:17px 0"><?= $drift!==null?h(number_format((float)$drift,1,',','.')).' %':'–' ?></strong><p class="perf-note">Positive Drift: höhere HF relativ zur Geschwindigkeit in der zweiten Hälfte. Ausreichende Daten, mindestens 5 km und vergleichbare Bedingungen nötig; keine valide Aussage bei vielen Stopps, Intervallen oder Anstiegen.</p></article>
<article class="panel perf-panel-wide"><h2>Kilometer-Splits</h2><p class="muted">Interpoliert aus Distanz- und Zeit-Messpunkten; Elapsed Time kann Pausen enthalten</p><?php if(!$splitArr):?><p class="perf-empty">Keine vollständigen Kilometer-Splits verfügbar.</p><?php else:?><div class="tablewrap"><table class="perf-splits"><thead><tr><th>KM</th><th>Zeit</th><th>Ø HF</th></tr></thead><tbody><?php foreach($splitArr as $split):?><tr><td>KM <?=h($split['km'])?></td><td><?=h(ll27Pace((int)$split['seconds']))?></td><td><?=h($split['hr']??'–')?> bpm</td></tr><?php endforeach;?></tbody></table></div><?php endif;?></article></div>
<?php endif;?><p class="perf-note" style="margin:18px 0">Die Auswertungen berücksichtigen ausschließlich deine eigenen Aktivitäten, soweit diese im maximal siebentägigen Strava-Cache verfügbar sind. Keine dauerhafte Historie und keine garantierte Vollständigkeit.</p></div>
<script id="performance-data" type="application/json"><?=json_encode(['zones'=>$zoneArr],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script></body></html>
