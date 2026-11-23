export function chatPasteButton(input,onMessage){
 const button=document.createElement('button');button.type='button';button.className='chat-paste';button.textContent='Dán văn bản';
 button.onclick=async()=>{if(input.disabled||input.readOnly){onMessage('Đang xử lý nội dung trước. Chờ xong rồi dán tiếp.');return;}try{if(!navigator.clipboard?.readText)throw Error('unsupported');const text=await navigator.clipboard.readText();const start=input.selectionStart??input.value.length,end=input.selectionEnd??start;input.setRangeText(text,start,end,'end');input.focus();input.dispatchEvent(new Event('input',{bubbles:true}));onMessage('Đã dán văn bản.');}catch{input.focus();onMessage('Bấm Ctrl+V, hoặc chạm giữ ô nhập và chọn Dán. Trình duyệt chưa cho nút Dán đọc clipboard.');}};return button;
}
