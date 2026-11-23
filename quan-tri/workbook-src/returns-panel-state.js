export function clampPanel(position,viewport,size){const width=Math.min(size.width,Math.max(100,viewport.width-24)),height=Math.min(size.height,Math.max(120,viewport.height-24));return {x:Math.max(12,Math.min(Number(position.x)||12,viewport.width-width-12)),y:Math.max(12,Math.min(Number(position.y)||12,viewport.height-height-12)),width,height};}

export function setupReturnsPanel({panel,head,toggle,identity,close,getDraft,setDraft}){
 const abort=new AbortController();let drag=null,draftTimer,restored='',placed=false;
 const userKey=()=>identity?.()||'';
 const read=key=>{try{return JSON.parse(localStorage.getItem(key)||'null');}catch{return null;}};
 let buttonDrag=null,suppressClick=false,buttonRestored='';
 const placeButton=p=>{const r=toggle.getBoundingClientRect();toggle.style.left=Math.max(8,Math.min(Number(p.x)||8,innerWidth-r.width-8))+'px';toggle.style.top=Math.max(8,Math.min(Number(p.y)||8,innerHeight-r.height-8))+'px';toggle.style.right='auto';toggle.style.bottom='auto';};
 function restoreButton(){const user=userKey();if(!user||buttonRestored===user)return false;buttonRestored=user;const saved=read('victor:returns:toggle:'+user);if(saved)placeButton(saved);return true;}
 const buttonInit=setInterval(()=>{if(restoreButton())clearInterval(buttonInit);},250);
 toggle.style.touchAction='none';toggle.title='Bấm để mở / đóng. Kéo để đổi vị trí.';
 toggle.addEventListener('pointerdown',e=>{if(e.button!==0)return;restoreButton();const r=toggle.getBoundingClientRect();buttonDrag={id:e.pointerId,x:e.clientX,y:e.clientY,left:r.left,top:r.top,moved:false};toggle.setPointerCapture(e.pointerId);},{signal:abort.signal});
 toggle.addEventListener('pointermove',e=>{if(!buttonDrag||buttonDrag.id!==e.pointerId)return;if(Math.hypot(e.clientX-buttonDrag.x,e.clientY-buttonDrag.y)>5)buttonDrag.moved=true;if(buttonDrag.moved){placeButton({x:buttonDrag.left+e.clientX-buttonDrag.x,y:buttonDrag.top+e.clientY-buttonDrag.y});e.preventDefault();}},{signal:abort.signal});
 function dropButton(e){if(!buttonDrag||buttonDrag.id!==e.pointerId)return;suppressClick=buttonDrag.moved;buttonDrag=null;if(toggle.hasPointerCapture(e.pointerId))toggle.releasePointerCapture(e.pointerId);if(suppressClick&&userKey()){const r=toggle.getBoundingClientRect();try{localStorage.setItem('victor:returns:toggle:'+userKey(),JSON.stringify({x:r.left,y:r.top}));}catch{}}}
 toggle.addEventListener('pointerup',dropButton,{signal:abort.signal});toggle.addEventListener('pointercancel',dropButton,{signal:abort.signal});toggle.addEventListener('click',e=>{if(suppressClick){suppressClick=false;e.preventDefault();e.stopImmediatePropagation();}},{capture:true,signal:abort.signal});
 function persist(){clearTimeout(draftTimer);const user=userKey();if(!user)return;try{const draft=getDraft();const key='victor:returns:draft:'+user;if(draft.body?.trim())localStorage.setItem(key,JSON.stringify(draft));else localStorage.removeItem(key);}catch{}}
 function place(position){const rect=panel.getBoundingClientRect(),v=window.visualViewport;const viewport={width:v?.width||window.innerWidth,height:v?.height||window.innerHeight};const p=clampPanel(position,viewport,{width:rect.width||420,height:rect.height||550});Object.assign(panel.style,{left:p.x+'px',top:p.y+'px',right:'auto',bottom:'auto',height:p.height+'px',maxWidth:'calc(100vw - 24px)'});placed=true;return p;}
 function opened(){const user=userKey();if(user&&restored!==user){const draft=read('victor:returns:draft:'+user);if(draft&&typeof draft.body==='string'&&draft.body.length<=25000)setDraft(draft);restored=user;}const stored=user?read('victor:returns:position:'+user):null;if(stored)place(stored);else if(placed)place({x:panel.getBoundingClientRect().left,y:panel.getBoundingClientRect().top});}
 document.addEventListener('pointerdown',e=>{if(!panel.hidden&&!drag&&!panel.contains(e.target)&&!toggle.contains(e.target))close();},{capture:true,signal:abort.signal});
 head.style.cursor='grab';head.style.touchAction='none';head.style.userSelect='none';head.title='Kéo thanh tiêu đề để di chuyển ô chat';
 head.addEventListener('pointerdown',e=>{if(e.button!==0||e.target.closest('button'))return;const rect=panel.getBoundingClientRect();drag={id:e.pointerId,x:e.clientX,y:e.clientY,left:rect.left,top:rect.top};head.setPointerCapture(e.pointerId);head.style.cursor='grabbing';e.preventDefault();},{signal:abort.signal});
 head.addEventListener('pointermove',e=>{if(!drag||drag.id!==e.pointerId)return;place({x:drag.left+e.clientX-drag.x,y:drag.top+e.clientY-drag.y});},{signal:abort.signal});
 function drop(e){if(!drag||drag.id!==e.pointerId)return;drag=null;head.style.cursor='grab';if(head.hasPointerCapture(e.pointerId))head.releasePointerCapture(e.pointerId);const user=userKey();if(user){const rect=panel.getBoundingClientRect();try{localStorage.setItem('victor:returns:position:'+user,JSON.stringify({x:rect.left,y:rect.top}));}catch{}}}
 head.addEventListener('pointerup',drop,{signal:abort.signal});head.addEventListener('pointercancel',drop,{signal:abort.signal});
 function resize(){if(!panel.hidden&&placed){const rect=panel.getBoundingClientRect();place({x:rect.left,y:rect.top});}}
 window.addEventListener('resize',resize,{signal:abort.signal});window.addEventListener('pagehide',persist,{signal:abort.signal});
 return {opened,persist,changed(){clearTimeout(draftTimer);draftTimer=setTimeout(persist,200);},dispose(){persist();clearTimeout(draftTimer);clearInterval(buttonInit);abort.abort();}};
}
