(()=>{
 'use strict';
 const colors=['#4dd0a3','#2dd4bf','#f6ca56','#fc8751','#fc4c02'];
 const pace=value=>{const seconds=Math.round(value);return Math.floor(seconds/60)+':'+String(seconds%60).padStart(2,'0');};
 function render(){
  const node=document.getElementById('performance-data');if(!node)return;
  let data;try{data=JSON.parse(node.textContent);}catch(_){return;}
  const empty=(id,text)=>{const canvas=document.getElementById(id);if(canvas)canvas.replaceWith(Object.assign(document.createElement('p'),{className:'perf-empty',textContent:text}));};
  if(!window.Chart){['perf-zones','perf-timeline','perf-splits','perf-elevation','perf-power','perf-cadence'].forEach(id=>empty(id,'Diagrammbibliothek konnte nicht geladen werden.'));return;}
  const base=()=>({responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:'#dce5ed'}}},scales:{x:{ticks:{color:'#aebccd'},grid:{color:'#314255'}},y:{ticks:{color:'#aebccd'},grid:{color:'#314255'}}}});
  const zones=data.zones||[];
  if(document.getElementById('perf-zones')){
   if(zones.length===5&&zones.some(x=>x>0))new Chart(document.getElementById('perf-zones'),{type:'doughnut',data:{labels:['Z1','Z2','Z3','Z4','Z5'],datasets:[{data:zones,backgroundColor:colors,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:'#dce5ed'}},tooltip:{callbacks:{label:ctx=>ctx.label+': '+Math.round(zones[ctx.dataIndex]/60)+' Min.'}}}}});
   else empty('perf-zones','Keine ausreichenden Herzfrequenzmessungen vorhanden.');
  }
  const timeline=data.timeline||[];
  if(document.getElementById('perf-timeline')){
   if(timeline.length){
    const options=base();
    options.interaction={mode:'index',intersect:false};
    options.scales.x={...options.scales.x,type:'linear',title:{display:true,text:'Laufstrecke (km)',color:'#aebccd'}};
    options.scales.y={...options.scales.y,reverse:true,title:{display:true,text:'Pace (min/km)',color:'#aebccd'},ticks:{color:'#aebccd',callback:pace}};
    options.scales.hr={position:'right',grid:{drawOnChartArea:false},ticks:{color:'#2dd4bf'},title:{display:true,text:'Puls (bpm)',color:'#2dd4bf'}};
    options.plugins.tooltip={callbacks:{title:items=>items.length?Number(items[0].parsed.x).toLocaleString('de-DE',{maximumFractionDigits:2})+' km':'',label:ctx=>ctx.dataset.label+': '+(ctx.dataset.yAxisID==='hr'?Math.round(ctx.parsed.y)+' bpm':pace(ctx.parsed.y)+' min/km')}};
    new Chart(document.getElementById('perf-timeline'),{type:'line',data:{datasets:[{label:'Pace',data:timeline.map(p=>({x:p.km,y:p.pace})),borderColor:'#fc4c02',backgroundColor:'#fc4c02',yAxisID:'y',pointRadius:0,borderWidth:2,spanGaps:false},{label:'Puls',data:timeline.map(p=>({x:p.km,y:p.hr})),borderColor:'#2dd4bf',backgroundColor:'#2dd4bf',yAxisID:'hr',pointRadius:0,borderWidth:2,spanGaps:false}]},options});
   }else empty('perf-timeline','Verlaufsdaten werden beim nächsten verfügbaren Detail-Sync nachgeladen. Liefert Strava keine Messreihe, bleibt der Verlauf leer.');
  }
  function streamChart(id,definitions){
   const canvas=document.getElementById(id);if(!canvas)return;
   const available=definitions.filter(def=>timeline.some(p=>Number.isFinite(p[def.key])));
   if(!available.length){empty(id,'Keine Messwerte verfügbar. Ältere Analysen werden schrittweise nachgeladen; nicht jede Aufzeichnung enthält diese Daten.');return;}
   const options=base();options.interaction={mode:'index',intersect:false};
   options.scales={x:{type:'linear',ticks:{color:'#aebccd'},grid:{color:'#314255'},title:{display:true,text:'Laufstrecke (km)',color:'#aebccd'}}};
   available.forEach((def,index)=>{options.scales[def.key]={position:index===0?'left':'right',grid:{color:'#314255',drawOnChartArea:index===0},ticks:{color:def.color},title:{display:true,text:def.label+' ('+def.unit+')',color:def.color}};});
   options.plugins.tooltip={callbacks:{title:items=>items.length?Number(items[0].parsed.x).toLocaleString('de-DE',{maximumFractionDigits:2})+' km':'',label:ctx=>{const def=available[ctx.datasetIndex];return def.label+': '+Number(ctx.parsed.y).toLocaleString('de-DE',{maximumFractionDigits:1})+' '+def.unit;}}};
   new Chart(canvas,{type:'line',data:{datasets:available.map(def=>({label:def.label,data:timeline.map(p=>({x:p.km,y:p[def.key]??null})),yAxisID:def.key,borderColor:def.color,backgroundColor:def.fill||def.color,fill:!!def.fill,pointRadius:0,borderWidth:2,spanGaps:false}))},options});
  }
  streamChart('perf-elevation',[{key:'altitude',label:'Höhe',unit:'m',color:'#a5a0ff',fill:'rgba(165,160,255,0.15)'},{key:'grade_smooth',label:'Steigung',unit:'%',color:'#2dd4bf'}]);
  streamChart('perf-power',[{key:'watts',label:'Leistung',unit:'W',color:'#fc4c02'}]);
  streamChart('perf-cadence',[{key:'cadence',label:'Schrittfrequenz',unit:'U/min',color:'#f6ca56'}]);
  const splits=data.splits||[];
  if(document.getElementById('perf-splits')){
   if(splits.length){
    const options=base();options.plugins.legend.display=false;
    options.scales.y={...options.scales.y,beginAtZero:true,title:{display:true,text:'Zeit pro Kilometer (min:ss)',color:'#aebccd'},ticks:{color:'#aebccd',callback:pace}};
    options.plugins.tooltip={callbacks:{label:ctx=>{const split=splits[ctx.dataIndex];return [pace(split.seconds)+' min/km',split.hr==null?'Kein Pulswert':split.hr+' bpm'];}}};
    const color=hr=>{if(hr==null)return '#64748b';const ratio=hr/(data.hrMax||190);return colors[ratio<.6?0:ratio<.7?1:ratio<.8?2:ratio<.9?3:4];};
    new Chart(document.getElementById('perf-splits'),{type:'bar',data:{labels:splits.map(p=>'KM '+p.km),datasets:[{label:'Kilometerzeit',data:splits.map(p=>p.seconds),backgroundColor:splits.map(p=>color(p.hr)),borderRadius:5}]},options});
   }else empty('perf-splits','Keine vollständigen Kilometer-Splits verfügbar.');
  }
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',render);else render();
})();