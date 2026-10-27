(()=>{
 'use strict';
 const colors=['#4dd0a3','#2dd4bf','#f6ca56','#fc8751','#fc4c02'];
 const pace=value=>{const seconds=Math.round(value);return Math.floor(seconds/60)+':'+String(seconds%60).padStart(2,'0');};
 function render(){
  const node=document.getElementById('performance-data');if(!node)return;
  let data;try{data=JSON.parse(node.textContent);}catch(_){return;}
  const empty=(id,text)=>{const canvas=document.getElementById(id);if(canvas){const wrapper=canvas.parentElement;if(wrapper?.classList.contains('perf-chart'))wrapper.style.height='auto';canvas.replaceWith(Object.assign(document.createElement('p'),{className:'perf-empty',textContent:text}));}};
  if(!window.Chart){['perf-zones','perf-timeline','perf-splits','perf-elevation','perf-power','perf-cadence'].forEach(id=>empty(id,'Diagrammbibliothek konnte nicht geladen werden.'));return;}
  const base=()=>({responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:'#dce5ed'}}},scales:{x:{ticks:{color:'#aebccd'},grid:{color:'#314255'}},y:{ticks:{color:'#aebccd'},grid:{color:'#314255'}}}});
  const zones=data.zones||[];
  if(document.getElementById('perf-zones')){
   if(zones.length===5&&zones.some(x=>x>0))new Chart(document.getElementById('perf-zones'),{type:'doughnut',data:{labels:['Z1','Z2','Z3','Z4','Z5'],datasets:[{data:zones,backgroundColor:colors,borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{color:'#dce5ed'}},tooltip:{callbacks:{label:ctx=>ctx.label+': '+Math.round(zones[ctx.dataIndex]/60)+' Min.'}}}}});
   else empty('perf-zones','Keine ausreichenden Herzfrequenzmessungen vorhanden.');
  }
  const timeline=data.timeline||[];
  let timelineChart=null,map=null,marker=null;
  const route=(data.route||[]).filter(p=>Array.isArray(p.latlng)&&p.latlng.length===2&&p.latlng.every(Number.isFinite));
  const nearest=(items,seconds)=>items.reduce((best,p,index)=>Math.abs(p.seconds-seconds)<Math.abs(items[best].seconds-seconds)?index:best,0);
  function showPosition(index,fromMap=false){
   const p=route[index];if(!p||!map)return;
   if(!marker)marker=L.circleMarker(p.latlng,{radius:7,color:'#fff',weight:3,fillColor:'#fc4c02',fillOpacity:1}).addTo(map);
   else marker.setLatLng(p.latlng);
   const sample=timeline.length?timeline[nearest(timeline,p.seconds)]:null;
   document.getElementById('perf-map-info').textContent=p.km.toLocaleString('de-DE',{maximumFractionDigits:2})+' km · '+(p.hr==null?'Kein Pulswert':p.hr+' bpm')+' · '+(sample?.pace==null?'Keine Pace':pace(sample.pace)+' min/km');
   if(fromMap&&timelineChart&&timeline.length){
    const index=nearest(timeline,p.seconds);
    const active=timelineChart.data.datasets.map((_,datasetIndex)=>({datasetIndex,index}));
    timelineChart.setActiveElements(active);
    const point=timelineChart.getDatasetMeta(0).data[index];
    if(point)timelineChart.tooltip.setActiveElements(active,{x:point.x,y:point.y});
    timelineChart.update('none');
   }
  }
  const mapNode=document.getElementById('perf-map');
  if(mapNode){
   if(route.length<2){mapNode.style.height='auto';mapNode.textContent='Noch keine GPS-Strecke verfügbar. Bestehende Läufe werden schrittweise nachgeladen; Indoor-Läufe haben häufig keine GPS-Daten.';}
   else if(!window.L){mapNode.style.height='auto';mapNode.textContent='Kartenbibliothek konnte nicht geladen werden.';}
   else{
    map=L.map(mapNode,{scrollWheelZoom:true,preferCanvas:true});
    const streets=L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'}).addTo(map);
    const satellite=L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',{maxZoom:19,attribution:'Tiles &copy; Esri &mdash; Sources: Esri, Maxar, Earthstar Geographics, and the GIS User Community'});
    L.control.layers({'Straßenkarte':streets,'Satellit':satellite},null,{collapsed:false,position:'topright'}).addTo(map);
    map.fitBounds(L.latLngBounds(route.map(p=>p.latlng)),{padding:[22,22],maxZoom:16});
    const layer=L.layerGroup().addTo(map);
    const mode=document.getElementById('perf-map-mode');
    function drawRoute(){
     layer.clearLayers();
     const isPace=mode.value==='pace';
     document.getElementById('perf-map-legend').textContent=isPace?'Pace: Türkis (schneller) → Rot (langsamer), relativ zu diesem Lauf · Grau: kein Messwert':'HF: Türkis → Gelb → Rot mit steigender HF-Zone · Grau: kein Messwert';
     const paces=timeline.map(p=>p.pace).filter(Number.isFinite).sort((a,b)=>a-b);
     const low=paces[Math.floor(paces.length*.1)]||0,high=paces[Math.floor(paces.length*.9)]||low;
     for(let i=1;i<route.length;i++){
      const a=route[i-1],b=route[i];if(b.break||b.seconds-a.seconds>120)continue;
      let color='#64748b';
      if(isPace&&timeline.length){const value=timeline[nearest(timeline,b.seconds)].pace;if(Number.isFinite(value))color=colors[Math.max(0,Math.min(4,Math.floor((value-low)/Math.max(1,high-low)*4)))];}
      else if(!isPace&&b.hr!=null){const ratio=b.hr/(data.hrMax||190);color=colors[ratio<.6?0:ratio<.7?1:ratio<.8?2:ratio<.9?3:4];}
      L.polyline([a.latlng,b.latlng],{color,weight:5,opacity:.95}).addTo(layer).on('mouseover',()=>showPosition(i,true)).on('click',()=>showPosition(i,true));
     }
    }
    mode.addEventListener('change',drawRoute);drawRoute();
    L.circleMarker(route[0].latlng,{radius:5,color:'#fff',fillColor:'#2dd4bf',fillOpacity:1}).addTo(map).bindTooltip('Erster verfügbarer GPS-Punkt');
    L.circleMarker(route[route.length-1].latlng,{radius:5,color:'#fff',fillColor:'#fc4c02',fillOpacity:1}).addTo(map).bindTooltip('Letzter verfügbarer GPS-Punkt');
   }
  }
  if(document.getElementById('perf-timeline')){
   if(timeline.length){
    const options=base();
    options.interaction={mode:'index',intersect:false};
    options.scales.x={...options.scales.x,type:'linear',title:{display:true,text:'Laufstrecke (km)',color:'#aebccd'}};
    options.scales.y={...options.scales.y,reverse:true,title:{display:true,text:'Pace (min/km)',color:'#aebccd'},ticks:{color:'#aebccd',callback:pace}};
    options.scales.hr={position:'right',grid:{drawOnChartArea:false},ticks:{color:'#2dd4bf'},title:{display:true,text:'Puls (bpm)',color:'#2dd4bf'}};
    options.plugins.tooltip={callbacks:{title:items=>items.length?Number(items[0].parsed.x).toLocaleString('de-DE',{maximumFractionDigits:2})+' km':'',label:ctx=>ctx.dataset.label+': '+(ctx.dataset.yAxisID==='hr'?Math.round(ctx.parsed.y)+' bpm':pace(ctx.parsed.y)+' min/km')}};
    options.onHover=(event,elements)=>{if(elements.length&&route.length)showPosition(nearest(route,timeline[elements[0].index].seconds));};
    timelineChart=new Chart(document.getElementById('perf-timeline'),{type:'line',data:{datasets:[{label:'Pace',data:timeline.map(p=>({x:p.km,y:p.pace})),borderColor:'#fc4c02',backgroundColor:'#fc4c02',yAxisID:'y',pointRadius:0,borderWidth:2,spanGaps:false},{label:'Puls',data:timeline.map(p=>({x:p.km,y:p.hr})),borderColor:'#2dd4bf',backgroundColor:'#2dd4bf',yAxisID:'hr',pointRadius:0,borderWidth:2,spanGaps:false}]},options});
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
