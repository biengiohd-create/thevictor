export const ZOOM_BASE=1.2;
export const zoomPercent=ratio=>Math.round(Number(ratio)/ZOOM_BASE*100);
export const zoomRatio=percent=>Math.max(.3,Math.min(3.6,Number((Number(percent)/100*ZOOM_BASE).toFixed(4))));
export function prepareZoomBaseline(snapshot){for(const sheet of Object.values(snapshot.sheets??{}))sheet.zoomRatio=ZOOM_BASE;}
export function setupBaselineZoom(editor){
 const root=document.getElementById('editor'),known=new Set(editor.workbook.getSheets().map(s=>s.getSheetId())),bar=document.createElement('div');bar.className='baseline-zoom';bar.setAttribute('aria-label','Thu phóng bảng');
 const minus=document.createElement('button'),plus=document.createElement('button'),range=document.createElement('input'),select=document.createElement('select');minus.type=plus.type='button';minus.textContent='−';plus.textContent='+';minus.setAttribute('aria-label','Thu nhỏ bảng');plus.setAttribute('aria-label','Phóng to bảng');range.type='range';range.min=25;range.max=300;range.step=5;range.setAttribute('aria-label','Thu phóng bảng');select.setAttribute('aria-label','Mức thu phóng bảng');
 const options=[25,50,75,90,100,110,125,150,175,200,250,300];for(const n of options){const o=document.createElement('option');o.value=n;o.textContent=n+'%';select.append(o);}bar.append(minus,range,plus,select);
 const sheet=()=>editor.workbook.getActiveSheet();let native=null,raf=0;
 function update(){try{const percent=zoomPercent(sheet().getZoom());range.value=percent;const option=[...select.options].find(o=>o.dataset.dynamic==='1');option?.remove();if(!options.includes(percent)){const o=document.createElement('option');o.dataset.dynamic='1';o.value=percent;o.textContent=percent+'%';select.append(o);}select.value=percent;}catch{}}
 const set=percent=>{sheet().zoom(zoomRatio(percent));update();};minus.onclick=()=>set(zoomPercent(sheet().getZoom())-10);plus.onclick=()=>set(zoomPercent(sheet().getZoom())+10);range.oninput=()=>set(+range.value);select.onchange=()=>set(+select.value);
 function install(){const reset=root.querySelector('[data-u-comp="zoom-reset"]'),section=reset?.closest('section');if(section&&section!==native){native=section;native.style.display='none';native.before(bar);}else if(native&&!bar.isConnected)native.before(bar);}
 const observer=new MutationObserver(()=>{if(!raf)raf=requestAnimationFrame(()=>{raf=0;install();});});observer.observe(root,{childList:true,subtree:true});install();update();
 const listener=editor.api.addEvent(editor.api.Event.CommandExecuted,e=>{if(/insert-sheet|copy-sheet/.test(e.id))for(const s of editor.workbook.getSheets())if(!known.has(s.getSheetId())){known.add(s.getSheetId());s.zoom(ZOOM_BASE);}if(/zoom|set-worksheet-active/.test(e.id))update();});
 return {dispose(){observer.disconnect();cancelAnimationFrame(raf);listener.dispose();bar.remove();if(native)native.style.display='';}};
}
