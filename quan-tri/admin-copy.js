'use strict';
function copyValue(value,label='thông tin',className=''){
 const text=String(value??'');if(!text)return '<span class="muted">—</span>';
 const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 return `<button type="button" class="copy-value ${escape(className)}" data-copy="${escape(text)}" data-copy-label="${escape(label)}" title="Bấm để sao chép ${escape(label)} đầy đủ" aria-label="Sao chép ${escape(label)}: ${escape(text)}">${escape(text)}</button>`;
}
if(typeof module!=='undefined'&&module.exports)module.exports={copyValue};
if(typeof document!=='undefined'){
 const toast=document.createElement('p');toast.className='copy-toast';toast.setAttribute('role','status');toast.setAttribute('aria-live','polite');toast.hidden=true;document.body.appendChild(toast);let timer;
 document.addEventListener('click',async event=>{const button=event.target.closest('[data-copy]');if(!button)return;event.preventDefault();event.stopPropagation();const text=button.dataset.copy;let copied=false;
  try{await navigator.clipboard.writeText(text);copied=true}catch{
   const field=document.createElement('textarea');field.value=text;field.className='copy-fallback';field.readOnly=true;document.body.appendChild(field);field.select();try{copied=document.execCommand('copy')}catch{}field.remove();button.focus({preventScroll:true});
  }
  toast.textContent=copied?'Đã sao chép '+(button.dataset.copyLabel||'thông tin')+'.':'Chưa sao chép được. Anh có thể bôi chọn nội dung để sao chép.';toast.hidden=false;clearTimeout(timer);timer=setTimeout(()=>toast.hidden=true,2400);
 });
}
