const panel=document.querySelector('body[data-workspace="orders"] .orders-panel:not([hidden])');
if(panel){
 const dashboard=panel.closest('.dashboard'),total=document.querySelector('.web-total-row'),actions=document.querySelector('.workspace-actions');
 if(total&&actions){total.append(actions);document.querySelector('.workspace-nav-row').hidden=true;}
 const filters=panel.querySelector('#filters'),filterBox=document.createElement('details'),filterTitle=document.createElement('summary');filterBox.className='orders-filter-panel';filterTitle.textContent='Tìm kiếm & bộ lọc';filterBox.append(filterTitle);filters.before(filterBox);filterBox.append(filters);const small=matchMedia('(max-width:700px)');filterBox.open=!small.matches;small.addEventListener('change',()=>{filterBox.open=!small.matches;});
 const wrap=panel.querySelector('.table-wrap'),table=document.getElementById('ordersTable'),select=document.getElementById('ordersPerPage');
 const rail=document.createElement('div'),width=document.createElement('div');rail.className='web-horizontal-scroll';rail.setAttribute('role','region');rail.setAttribute('aria-label','Cuộn ngang danh sách đơn');rail.tabIndex=0;rail.append(width);document.body.append(rail);
 let frame;
 rail.addEventListener('scroll',()=>{if(wrap.scrollLeft!==rail.scrollLeft)wrap.scrollLeft=rail.scrollLeft;},{passive:true});
 wrap.addEventListener('scroll',()=>{if(rail.scrollLeft!==wrap.scrollLeft)rail.scrollLeft=wrap.scrollLeft;},{passive:true});
 function layout(){cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>{const viewport=window.visualViewport,screenHeight=viewport?.height||innerHeight;dashboard.style.setProperty('--orders-height',Math.max(260,screenHeight-dashboard.getBoundingClientRect().top)+'px');const rect=wrap.getBoundingClientRect();width.style.width=table.scrollWidth+'px';rail.style.left=rect.left+'px';rail.style.width=wrap.clientWidth+'px';rail.scrollLeft=wrap.scrollLeft;});}
 // Automatic mode loads a scrollable batch, never a single row fitted to an empty table.
 window.victorPageSize=()=>200;
 select.addEventListener('change',layout);
 const observer=new ResizeObserver(layout);observer.observe(table);observer.observe(panel);observer.observe(document.querySelector('.victor-header'));window.addEventListener('resize',layout);window.visualViewport?.addEventListener('resize',layout);layout();select.dispatchEvent(new Event('change'));
}
