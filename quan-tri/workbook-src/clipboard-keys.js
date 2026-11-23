import {IUniverInstanceService} from '@univerjs/core';
import {ISheetClipboardService} from '@univerjs/sheets-ui';
import {writeNativeSelection} from './native-copy.js';
export function setupClipboardKeys({editor,editable,editing,status}){
 const abort=new AbortController(),root=document.getElementById('editor'),injector=editor.univer.__getInjector(),clipboard=injector.get(ISheetClipboardService),instances=injector.get(IUniverInstanceService);
 const inGrid=e=>e.target instanceof Element&&!e.target.closest('.returns-panel,button,summary,a,[role="dialog"]')&&(root.contains(e.target)||e.target.id.startsWith('__editor__'));
 const allowed=()=>{const range=editor.workbook.getActiveSheet()?.getActiveRange();if(!range)return false;instances.focusUnit(editor.workbook.getId());return true;};
 let copying=false;
 window.addEventListener('victor-copy-data',e=>{const source=e.detail.event;if(editing()||(!copying&&!inGrid(source)))return;try{const sheet=editor.workbook.getActiveSheet(),range=sheet?.getActiveRange()?.getRange();if(!range)return;e.detail.handled=writeNativeSelection(clipboard,editor.workbook.getId(),sheet.getSheetId(),range,source.clipboardData);if(e.detail.handled)status('Đã sao chép vùng chọn.');}catch(error){status(error.message,true);}},{signal:abort.signal});
 const copy=e=>{if(editing()||e.isComposing||!inGrid(e)||!allowed())return false;copying=true;let ok=false;try{ok=document.execCommand('copy');}finally{copying=false;}if(!ok)status('Chưa sao chép được vùng chọn. Chọn ô rồi bấm Ctrl+C lại.',true);return ok;};
 window.addEventListener('victor-copy-selection',e=>{e.detail.handled=copy(e.detail.event);},{signal:abort.signal});
 window.addEventListener('keydown',e=>{if(!(e.ctrlKey||e.metaKey)||e.altKey||e.shiftKey||e.isComposing||editing()||!inGrid(e))return;const key=e.key.toLowerCase();if(!['c','x'].includes(key))return;if(key==='c'){if(copy(e)){e.preventDefault();e.stopImmediatePropagation();}return;}e.preventDefault();e.stopImmediatePropagation();if(!editable()||!allowed())return;Promise.resolve(clipboard.cut()).then(ok=>{if(ok)status('Đã cắt vùng chọn; chọn ô đích để dán.');}).catch(error=>status(error.message,true));},{capture:true,signal:abort.signal});
 window.addEventListener('paste',e=>{if(editing()||!editable()||!inGrid(e)||!e.clipboardData)return;const html=e.clipboardData.getData('text/html'),text=e.clipboardData.getData('text/plain'),files=Array.from(e.clipboardData.files??[]);if(!html&&!text&&!files.length)return;e.preventDefault();e.stopImmediatePropagation();if(!allowed())return;clipboard.legacyPaste(html,text,files).catch(error=>status(error.message,true));},{capture:true,signal:abort.signal});
 return {dispose:()=>abort.abort()};
}
