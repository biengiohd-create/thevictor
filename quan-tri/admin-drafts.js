'use strict';
function draftMarkup(d){
 const fields=[['Họ và tên',d.customerName],['Số điện thoại',d.phone],['Địa chỉ',d.address],['Ghi chú',d.note]];
 const products=(d.items||[]).map(i=>(i.sku||i.name)+' · Size '+(i.size==='Nhờ tư vấn'?'Chưa chọn':i.size)+' · SL '+i.qty).join('\n');
 const total=(d.items||[]).reduce((n,i)=>n+(Number(i.price)||0)*(Number(i.qty)||0),0);
 return `<article class="draft-card"><header><strong>Chưa xác nhận đặt hàng</strong><span>${esc(date(d.updatedAt))} · ${esc(({meta:'Quảng cáo Meta',direct:'Trực tiếp',other:'Nguồn khác'})[d.source]||d.source)}</span></header><dl>${fields.map(([label,value])=>`<div><dt>${esc(label)}</dt><dd>${value?copyValue(value,label.toLocaleLowerCase('vi')):'<span class="muted">Chưa điền</span>'}</dd></div>`).join('')}<div><dt>Sản phẩm đang chọn</dt><dd>${copyValue(products,'sản phẩm và size')}</dd></div><div><dt>Tạm tính</dt><dd>${copyValue(money(total),'tạm tính')}</dd></div></dl></article>`;
}
if(document.body.dataset.workspace==='orders'){
 let draftPage=1,draftPages=1,draftSequence=0,draftLoading=false;
 async function loadDrafts(){const sequence=++draftSequence;draftLoading=true;$('refreshDrafts').disabled=true;$('draftsStatus').textContent='Đang tải đơn nháp…';const q=filterQuery();q.set('action','drafts');q.set('page',draftPage);try{const data=await request(q);if(sequence!==draftSequence)return;draftPage=data.page;draftPages=data.pages;$('orderDrafts').innerHTML=data.drafts.map(draftMarkup).join('')||'<p class="muted">Chưa có đơn nháp phù hợp.</p>';$('draftsStatus').textContent=data.total+' đơn nháp · Trang '+data.page+'/'+data.pages+' · Bấm vào thông tin để sao chép đầy đủ.';$('draftPrev').disabled=draftPage<=1;$('draftNext').disabled=draftPage>=draftPages}catch(e){if(sequence===draftSequence)$('draftsStatus').textContent=e.message}finally{if(sequence===draftSequence){draftLoading=false;$('refreshDrafts').disabled=false}}}
 $('refreshDrafts').onclick=loadDrafts;$('draftPrev').onclick=()=>{draftPage--;loadDrafts()};$('draftNext').onclick=()=>{draftPage++;loadDrafts()};$('filters').addEventListener('submit',()=>{draftPage=1;loadDrafts()});$('refreshOrders').addEventListener('click',loadDrafts);loadDrafts();setInterval(()=>{if(!document.hidden&&!draftLoading)loadDrafts()},30000);
}
