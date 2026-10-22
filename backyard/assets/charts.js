document.addEventListener('DOMContentLoaded',()=>{
 const message=(id,txt)=>{const canvas=document.getElementById(id);if(!canvas)return;const p=document.createElement('p');p.className='muted chart-message';p.textContent=txt;canvas.parentNode.replaceChildren(p);};
 if(typeof Chart==='undefined'){
  ['weekly','heart','scatter','team-weekly','team-heart'].forEach(id=>message(id,'Diagrammbibliothek konnte nicht geladen werden. Internetverbindung/Adblocker prüfen.'));
  return;
 }
 const el=document.getElementById('chart-data');if(!el)return;
 let d;try{d=JSON.parse(el.textContent)}catch(e){return;}
 Chart.defaults.color='#aebccd';Chart.defaults.borderColor='#314255';Chart.defaults.font.family='system-ui,-apple-system,sans-serif';
 const palette={tim:'#fc4c02',jonas:'#2dd4bf',noah:'#a5a0ff'};
 const opts=(yLabel,zero=false)=>({responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:true,position:'bottom',labels:{color:'#d6e1ee',usePointStyle:true}},tooltip:{mode:'index',intersect:false}},scales:{x:{grid:{display:false},ticks:{maxRotation:45,minRotation:0}},y:{beginAtZero:zero,title:{display:!!yLabel,text:yLabel}}}});
 function draw(id,type,labels,datasets,options){const canvas=document.getElementById(id);if(!canvas)return; if(!datasets.some(x=>x.data.some(v=>v!==null&&v!==undefined&&v!==0))){message(id,'Für dieses Diagramm sind noch keine passenden Daten vorhanden.');return;}new Chart(canvas,{type,data:{labels,datasets},options});}
 const series=(label,data,color,extra={})=>({label,data,borderColor:color,backgroundColor:color,borderWidth:2,tension:.28,pointRadius:4,...extra});
 draw('weekly','bar',d.weeks?.labels||[],[series('Kilometer',d.weeks?.values||[],palette.tim,{borderRadius:5})],opts('Kilometer',true));
 draw('heart','line',d.heart?.labels||[],[series('Ø HF',d.heart?.values||[],'#ff665c')],opts('Schläge / min'));
 // Originalfehler: d.scatter ist ein Array, nicht d.scatter.values.
 const points=Array.isArray(d.scatter)?d.scatter:(d.scatter?.values||[]);
 const scatter=document.getElementById('scatter');
 if(scatter){if(!points.length){message('scatter','Keine Läufe mit Pace UND Herzfrequenz vorhanden.');}else{
  new Chart(scatter,{type:'scatter',data:{datasets:[{label:'Deine Läufe',data:points,backgroundColor:'#2dd4bf',borderColor:'#2dd4bf',pointRadius:6,pointHoverRadius:8}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>`Pace ${Number(c.parsed.x).toFixed(2).replace('.',',')} min/km · HF ${Math.round(c.parsed.y)} bpm`}}},scales:{x:{type:'linear',title:{display:true,text:'Pace (min/km)'}},y:{title:{display:true,text:'Ø Herzfrequenz (bpm)'}}}}});}}
 const team=d.team||{labels:[],distance:{},heart:{}};
 const names=['tim','jonas','noah'];
 const buttons=[...document.querySelectorAll('.runner-toggle[data-athlete]')];
 const selection=new Set(names);
 const teamCharts={};
 const makeSeries=(dataKey,type)=>names.map(n=>series(n.toUpperCase(),team[type]?.[n]||[],palette[n],type==='distance'?{borderRadius:3}:{spanGaps:false}));
 function createTeamChart(id,type,kind,yLabel){
  const canvas=document.getElementById(id);
  if(!canvas)return;
  const allData=names.flatMap(n=>team[type]?.[n]||[]);
  if(!allData.some(v=>v!==null&&v!==undefined&&v!==0)){
   message(id,'Für dieses Diagramm sind noch keine passenden Daten vorhanden.');
   return;
  }
  const chart=new Chart(canvas,{type:kind,data:{labels:team.labels||[],datasets:makeSeries(null,type)},options:opts(yLabel,type==='distance')});
  teamCharts[id]=chart;
 }
 createTeamChart('team-weekly','distance','bar','Kilometer');
 createTeamChart('team-heart','heart','line','Ø HF (bpm)');
 function syncSelection(){
  buttons.forEach(btn=>btn.setAttribute('aria-pressed',selection.has(btn.dataset.athlete)?'true':'false'));
  for(const row of document.querySelectorAll('.team-entry')){
   const athlete=names.find(n=>row.classList.contains('team-'+n));
   if(athlete)row.classList.toggle('is-filtered',!selection.has(athlete));
  }
  for(const chart of Object.values(teamCharts)){
   chart.data.datasets.forEach(ds=>{ds.hidden=!selection.has(ds.label.toLowerCase());});
   chart.update();
  }
  const summary=document.getElementById('team-selection-count');
  if(summary)summary.textContent=`${selection.size} von ${names.length}`;
 }
 buttons.forEach(btn=>btn.addEventListener('click',()=>{
  const n=btn.dataset.athlete;
  if(!names.includes(n))return;
  if(selection.has(n)) selection.delete(n);else selection.add(n);
  syncSelection();
 }));
 syncSelection();
});
