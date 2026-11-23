const panel=document.querySelector('body[data-workspace="orders"] .orders-panel');
if(panel){
 const total=document.querySelector('.web-total-row'),actions=document.querySelector('.workspace-actions');if(total&&actions){total.append(actions);document.querySelector('.workspace-nav-row').hidden=true;}
 const wrap=panel.querySelector('.table-wrap'),table=document.getElementById('ordersTable'),pagination=panel.querySelector('.pagination'),select=document.getElementById('ordersPerPage');
 const rail=document.createElement('div'),width=document.createElement('div');rail.className='web-horizontal-scroll';rail.setAttribute('role','region');rail.setAttribute('aria-label','Cuộn ngang danh sách đơn');rail.tabIndex=0;rail.append(width);document.body.append(rail);
 let syncing=false,frame,autoLimit=200,adjusting=false,resized=false;
 function sync(from,to){if(syncing)return;syncing=true;to.scrollLeft=from.scrollLeft;syncing=false;}
 rail.onscroll=()=>sync(rail,wrap);wrap.addEventListener('scroll',()=>sync(wrap,rail),{passive:true});
 function layout(){cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>{const top=wrap.getBoundingClientRect().top;wrap.style.height=Math.max(160,window.innerHeight-top-pagination.offsetHeight-38)+'px';width.style.width=table.scrollWidth+'px';rail.style.left=wrap.getBoundingClientRect().left+'px';rail.style.width=wrap.clientWidth+'px';rail.scrollLeft=wrap.scrollLeft;if(select.value==='auto'&&!document.getElementById('loadStatus').textContent.startsWith('Đang')){const fit=window.victorPageSize();if(resized||table.tBodies[0].rows.length>fit){if(resized)autoLimit=200;else autoLimit=fit;resized=false;adjusting=true;select.dispatchEvent(new Event('change'));adjusting=false;}}});}
 window.victorPageSize=()=>{const rows=[...table.tBodies[0].rows],available=wrap.clientHeight-(table.tHead?.offsetHeight||42);let used=0,fit=0;for(const row of rows){const h=row.getBoundingClientRect().height;if(used+h>available)break;used+=h;fit++;}if(!rows.length)fit=Math.floor(available/70);else if(fit===rows.length)fit=Math.floor(available/(used/rows.length));return Math.max(1,Math.min(autoLimit,200,fit));};
 try{const saved=localStorage.getItem('victor:web-page-size');if([...select.options].some(o=>o.value===saved))select.value=saved;}catch{}
 select.addEventListener('change',()=>{if(!adjusting)autoLimit=200;try{localStorage.setItem('victor:web-page-size',select.value);}catch{}layout();},{capture:true});
 const observer=new ResizeObserver(layout);observer.observe(table);observer.observe(panel);new MutationObserver(layout).observe(table.tBodies[0],{childList:true});window.addEventListener('resize',()=>{resized=true;layout();});layout();if(select.value!=='20')select.dispatchEvent(new Event('change'));
}
