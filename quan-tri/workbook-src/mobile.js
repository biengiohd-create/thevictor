import {zoomPercent,ZOOM_BASE} from './zoom-baseline.js';
import {setupMobileFrame} from './mobile-frame.js';
import {pinchZoom} from './pinch-zoom.js';
import {SetScrollRelativeCommand} from '@univerjs/sheets-ui';
import {TouchMotion,coastStep} from './touch-motion.js';

export function setupMobileSheet({editor,editable,status,touch}){
 const root=document.getElementById('editor'),mobile=matchMedia('(pointer:coarse)').matches||innerWidth<=768;
 if(!mobile)return {dispose(){}};
 const abort=new AbortController(),signal=abort.signal,original=new Map(),motion=new TouchMotion();let allowEdit=false,editing=false,active=null,raf=0,lastTap=null,suppressClickUntil=0,draggingRail=false;
 document.body.classList.add('mobile-sheet');const frame=setupMobileFrame();
 const sheet=()=>editor.workbook.getActiveSheet();
 const bar=document.createElement('div');bar.className='mobile-sheet-actions';bar.setAttribute('aria-label','Thao tác bảng trên điện thoại');
 const makeButton=(label,fn)=>{const b=document.createElement('button');b.type='button';b.textContent=label;b.onclick=fn;bar.append(b);return b;};
 const edit=makeButton('Sửa ô',beginEditing);edit.disabled=!editable();
 makeButton('Ẩn phím',finishEditing);
 makeButton('Cuối bảng',()=>{stopCoast();sheet().scrollToCell(sheet().getMaxRows()-1,sheet().getScrollState().sheetViewStartColumn,150);});
 const zoomLabel=document.createElement('output');zoomLabel.className='mobile-zoom-value';const zoomTo=value=>{sheet().zoom(Math.max(.3,Math.min(3.6,value)));zoomLabel.textContent=zoomPercent(sheet().getZoom())+'%';};const minus=makeButton('−',()=>zoomTo(sheet().getZoom()-.1*ZOOM_BASE));minus.setAttribute('aria-label','Thu nhỏ bảng');bar.append(zoomLabel);const plus=makeButton('+',()=>zoomTo(sheet().getZoom()+.1*ZOOM_BASE));plus.setAttribute('aria-label','Phóng to bảng');zoomLabel.textContent=zoomPercent(sheet().getZoom())+'%';
 root.before(bar);const wrapper=document.createElement('div');wrapper.className='mobile-editor-wrap';root.before(wrapper);wrapper.append(root);
 const rail=document.createElement('div');rail.className='mobile-scroll-rail';const slider=document.createElement('input');slider.type='range';slider.min='0';slider.step='1';slider.setAttribute('aria-label','Cuộn đến dòng');slider.setAttribute('orient','vertical');const label=document.createElement('output');rail.append(slider,label);wrapper.append(rail);

 function lockInputs(){
  if(allowEdit)return;
  for(const el of root.querySelectorAll('input,textarea,[contenteditable="true"]')){
   if(el.closest('[role="toolbar"]')||el===slider||el.matches('input.univer-appearance-none'))continue;
   if(!original.has(el))original.set(el,{mode:el.getAttribute('inputmode'),readOnly:el.readOnly,editable:el.getAttribute('contenteditable')});
   if(el.getAttribute('inputmode')!=='none')el.setAttribute('inputmode','none');
   if('readOnly' in el&&!el.readOnly)el.readOnly=true;
   if(el.getAttribute('contenteditable')==='true')el.setAttribute('contenteditable','false');
  }
 }
 function unlockInputs(){for(const [el,old]of original){if(!el.isConnected){original.delete(el);continue;}if(old.mode===null)el.removeAttribute('inputmode');else el.setAttribute('inputmode',old.mode);if('readOnly' in el)el.readOnly=old.readOnly;if(old.editable!==null)el.setAttribute('contenteditable',old.editable);}}
 function beginEditing(){if(!editable())return;stopCoast();allowEdit=true;unlockInputs();touch();editor.workbook.startEditing();}
 async function finishEditing(){stopCoast();try{await editor.workbook.endEditingAsync(true);}catch(e){status(e.message,true);}allowEdit=false;editing=false;const focused=document.activeElement;if(focused instanceof HTMLElement)focused.blur();lockInputs();updateViewport();}
 const before=editor.api.addEvent(editor.api.Event.BeforeSheetEditStart,e=>{if(!allowEdit)e.cancel=true;});
 const started=editor.api.addEvent(editor.api.Event.SheetEditStarted,()=>{editing=true;});
 const ended=editor.api.addEvent(editor.api.Event.SheetEditEnded,()=>{editing=false;allowEdit=false;queueMicrotask(lockInputs);});
 const observer=new MutationObserver(lockInputs);observer.observe(root,{childList:true,subtree:true,attributes:true,attributeFilter:['contenteditable','inputmode','readonly']});lockInputs();
 function gridTarget(e){return e.target instanceof Element&&e.target.tagName==='CANVAS'&&e.target.id.startsWith('univer-sheet-main-canvas');}
 function stopCoast(){cancelAnimationFrame(raf);raf=0;}
 function scroll(dx,dy){editor.api.executeCommand(SetScrollRelativeCommand.id,{offsetX:dx,offsetY:dy}).catch(()=>{});}
 function coast(vx,vy){let last=performance.now(),start=last;function frame(now){const step=coastStep(vx,vy,now-last);last=now;vx=step.vx;vy=step.vy;scroll(step.dx,step.dy);if(Math.hypot(vx,vy)>.025&&now-start<1500)raf=requestAnimationFrame(frame);else raf=0;}raf=requestAnimationFrame(frame);}
 const points=new Map();let pinch=null,zoomFrame=0,pendingZoom=1;const distance=()=>{const [a,b]=[...points.values()];return Math.hypot(a.x-b.x,a.y-b.y);};
 root.addEventListener('pointerdown',e=>{if(e.pointerType!=='touch'||!gridTarget(e))return;points.set(e.pointerId,{x:e.clientX,y:e.clientY});if(points.size===2){stopCoast();active=null;pinch={distance:distance(),zoom:sheet().getZoom()};e.preventDefault();e.stopImmediatePropagation();}},{capture:true,passive:false,signal});
 root.addEventListener('pointermove',e=>{if(!points.has(e.pointerId))return;points.set(e.pointerId,{x:e.clientX,y:e.clientY});if(!pinch)return;e.preventDefault();e.stopImmediatePropagation();if(points.size===2){pendingZoom=pinchZoom(pinch.zoom,pinch.distance,distance());if(!zoomFrame)zoomFrame=requestAnimationFrame(()=>{zoomFrame=0;zoomTo(pendingZoom);});}},{capture:true,passive:false,signal});
 const endPinch=e=>{if(!points.has(e.pointerId))return;points.delete(e.pointerId);if(!pinch)return;e.preventDefault();e.stopImmediatePropagation();suppressClickUntil=performance.now()+450;lastTap=null;if(!points.size)pinch=null;};root.addEventListener('pointerup',endPinch,{capture:true,passive:false,signal});root.addEventListener('pointercancel',endPinch,{capture:true,passive:false,signal});
 root.addEventListener('pointerdown',e=>{if(e.pointerType!=='touch'||!e.isPrimary||!gridTarget(e))return;stopCoast();if(editing)finishEditing();allowEdit=false;lockInputs();active=e.pointerId;motion.begin(e.clientX,e.clientY,e.timeStamp);},{capture:true,signal});
 root.addEventListener('pointermove',e=>{if(e.pointerId!==active)return;const delta=motion.move(e.clientX,e.clientY,e.timeStamp);if(!delta)return;e.preventDefault();e.stopImmediatePropagation();scroll(delta.dx,delta.dy);},{capture:true,passive:false,signal});
 root.addEventListener('pointerup',e=>{if(e.pointerId!==active)return;active=null;if(motion.dragged){e.preventDefault();e.stopImmediatePropagation();suppressClickUntil=performance.now()+450;lastTap=null;if(e.timeStamp-motion.time<100)coast(motion.vx,motion.vy);return;}const now=performance.now();if(lastTap&&now-lastTap.time<350&&Math.hypot(e.clientX-lastTap.x,e.clientY-lastTap.y)<22){lastTap=null;beginEditing();}else lastTap={x:e.clientX,y:e.clientY,time:now};},{capture:true,signal});
 root.addEventListener('pointercancel',()=>{active=null;stopCoast();lastTap=null;},{capture:true,signal});
 root.addEventListener('click',e=>{if(performance.now()<suppressClickUntil){e.preventDefault();e.stopImmediatePropagation();}},{capture:true,signal});
 slider.onpointerdown=()=>{stopCoast();draggingRail=true;};slider.onpointerup=slider.onpointercancel=()=>{draggingRail=false;};
 slider.oninput=()=>{const row=+slider.value;label.textContent=String(row+1);sheet().scrollToCell(row,sheet().getScrollState().sheetViewStartColumn);};
 function updateRail(){try{zoomLabel.textContent=zoomPercent(sheet().getZoom())+'%';const current=sheet(),state=current.getScrollState(),visible=current.getVisibleRange(),count=visible?Math.max(1,visible.endRow-visible.startRow+1):10;slider.max=String(Math.max(0,current.getMaxRows()-count));if(!draggingRail)slider.value=String(Math.min(+slider.max,state.sheetViewStartRow));label.textContent=String(state.sheetViewStartRow+1);slider.setAttribute('aria-valuetext','Dòng '+(state.sheetViewStartRow+1)+' trên '+current.getMaxRows());}catch{}}
 function updateViewport(){document.documentElement.style.setProperty('--sheet-viewport-height',(window.visualViewport?.height??innerHeight)+'px');}
 window.visualViewport?.addEventListener('resize',updateViewport,{signal});window.addEventListener('resize',updateViewport,{signal});updateViewport();updateRail();const timer=setInterval(updateRail,180);
 return {dispose(){cancelAnimationFrame(zoomFrame);frame.dispose();abort.abort();observer.disconnect();before.dispose();started.dispose();ended.dispose();clearInterval(timer);stopCoast();unlockInputs();bar.remove();wrapper.before(root);wrapper.remove();document.body.classList.remove('mobile-sheet');document.documentElement.style.removeProperty('--sheet-viewport-height');}};
}


