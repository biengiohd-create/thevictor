import {setupCancelChat} from './cancel-chat.js';
import {ensureTotalRows,protectTotals,totalsInternal} from './total-rows.js';
import {setupReturnsChat} from './returns-chat.js';
import {SetRangeValuesMutation} from '@univerjs/sheets';
import {setupFillButton} from './color-policy.js';
import {setupClipboardKeys} from './clipboard-keys.js';
import {compactGridSnapshot,dailyTemplate,setupArrowEdges} from './grid-defaults.js';
import {setupUndoKeys,clearInitializationHistory} from './undo-keys.js';
import {refineAppearance,removeAgeColumns} from './appearance.js';
import {setupClearKeys} from './clear-selection.js';
import {setupCopyWidths} from './copy-widths.js';
import {ensureTimeColumns,protectTime,applyTimePatches,timeInternal,stampRows} from './row-time.js';
import {setupMobileSheet} from './mobile.js';
import {createEditor} from './engine.js';
import './workbook.css';
import {setupMenus} from './menus.js';
import {ensureSerialColumn} from './serial.js';
const fillButton=setupFillButton();const returnsOrigin=crypto.randomUUID();
const $=id=>document.getElementById(id);let editor,revision=0,user=null,epoch=0,savedEpoch=0,saving=false,loading=false,paused=false,cellEditing=false,userTouched=false,timer,mobileControls,clearControls,undoControls,arrowControls,clipboardControls,operations=[],returnsBusy=false,applyingReturns=false;
const status=(text,error=false)=>{$('saveStatus').textContent=text;$('saveStatus').classList.toggle('error',error);};
const can=right=>!!user&&(user.owner||user.rights.includes(right));const editable=()=>!returnsBusy&&can('edit');
const dirty=()=>epoch!==savedEpoch;
const demo={id:'victor-staff',name:'THE VICTOR · Kiểm thử',appVersion:'1.5.2',locale:'viVN',sheetOrder:['orders'],styles:{header:{bg:{rgb:'#00e500'},bl:1,fs:12},money:{n:{pattern:'#,##0'}},text:{n:{pattern:'@'}}},sheets:{orders:{id:'orders',name:'Đơn hàng',rowCount:1000,columnCount:32,defaultRowHeight:32,defaultColumnWidth:130,freeze:{xSplit:0,ySplit:1,startRow:1,startColumn:0},rowData:{0:{h:60}},columnData:{0:{w:140},1:{w:190},2:{w:150},3:{w:350},4:{w:230},5:{w:230}},cellData:{0:Object.fromEntries(['Tiền','Tên khách','SĐT','Địa chỉ','Sản phẩm / size','Note','Check size','Tạo đơn','Chiều cao / cân nặng','Tình trạng','Mã vận đơn','Hoàn'].map((v,i)=>[i,{v,t:1,s:'header'}])),1:{0:{v:639000,t:2},1:{v:'KH KIỂM THỬ A',t:1},2:{v:'0320000000',t:4,s:'text'},3:{v:'Địa chỉ giả lập',t:1},4:{v:'DTQ26 L',t:1}},2:{0:{v:529000,t:2},1:{v:'KH KIỂM THỬ B',t:1},2:{v:'0900000000',t:4,s:'text'}}}}}};let demoSnapshot=demo;
async function request(action='load',data){if(import.meta.env.MODE==='preview'){if(action==='save'){demoSnapshot=data.snapshot;revision++;return {version:revision};}if(action==='version')return {version:revision};if(action==='history')return {history:[]};return {snapshot:structuredClone(demoSnapshot),version:revision||1,user:{username:'preview',name:'Kiểm thử',owner:true,rights:['view','edit','export','history','restore','manage_staff','manage_groups','inbox_status','board']}};}const response=await fetch('workbook-api.php'+(!data?'?action='+action:''),{cache:'no-store',...(data?{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...data,action,csrf:document.querySelector('meta[name=csrf-token]').content})}:{})});const result=await response.json();if(!response.ok){const error=Error(result.error||'Không xử lý được yêu cầu.');error.status=response.status;throw error;}return result;}
function download(data,name,type){const url=URL.createObjectURL(new Blob([data],{type}));const a=document.createElement('a');a.href=url;a.download=name;a.click();setTimeout(()=>URL.revokeObjectURL(url),10000);}
async function mount(snapshot){loading=true;clipboardControls?.dispose();arrowControls?.dispose();undoControls?.dispose();clearControls?.dispose();mobileControls?.dispose();if(editor)editor.api.dispose();const headerChanged=Object.values(snapshot.sheets).some(s=>!s.custom?.victorGreenHeader||Object.values(s.cellData?.[0]??{}).some(c=>['#dfede5','#e4eee8'].includes(String((typeof c.s==='string'?snapshot.styles?.[c.s]:c.s)?.bg?.rgb??'').toLowerCase())));const dimensionChanged=compactGridSnapshot(snapshot);editor=createEditor(refineAppearance(snapshot));$('bookName').value=snapshot.name==='THE VICTOR · Bảng đơn nhân viên'?'THE VICTOR · BẢNG ĐƠN':snapshot.name;epoch=0;savedEpoch=0;paused=false;userTouched=false;operations=[];
 $('editor').onpointerdown=()=>{userTouched=true;};$('editor').onkeydown=()=>{userTouched=true;};
 if(!editable())await editor.workbook.getWorkbookPermission().setMode('viewer');
 const ageChanged=editable()?removeAgeColumns(editor):false;const timeChanged=editable()?ensureTimeColumns(editor):false;const serialChanged=ensureSerialColumn(editor.workbook,editable(),editor.api);clearInitializationHistory(editor);arrowControls=setupArrowEdges({editor,editing:()=>cellEditing});
 ensureTotalRows(editor);protectTotals(editor,()=>!!user?.owner,status);protectTime(editor,status);clipboardControls=setupClipboardKeys({editor,editable,editing:()=>cellEditing,status});undoControls=setupUndoKeys({editor,editable,editing:()=>cellEditing,history:()=>{if(can('history'))$('history').click();},status});setupCopyWidths(editor);clearControls=setupClearKeys({editor,editable,editing:()=>cellEditing,touch:()=>{userTouched=true;},status});mobileControls=setupMobileSheet({editor,editable,status,touch:()=>{userTouched=true;}});
 const phoneColumn=(sheet,col)=>['sđt','sdt','số điện thoại'].includes(String(sheet.getRange(0,col).getValue()??'').trim().toLocaleLowerCase('vi'));
 const preparePhone=(sheet,row,col,matrix)=>{if(row<1||!phoneColumn(sheet,col))return;const cell=sheet.getRange(row,col).getCellData();if(!cell?.s)sheet.getRange(row,col).setNumberFormat('@');};
 editor.api.addEvent(editor.api.Event.BeforeSheetEditStart,params=>{if(!params.cancel)preparePhone(params.worksheet,params.row,params.column);});
 editor.api.addEvent(editor.api.Event.BeforeClipboardPaste,({worksheet,text})=>{const range=worksheet.getActiveRange();if(!range)return;const row=range.getRow(),col=range.getColumn(),lines=String(text??'').split(/\r?\n/);const height=Math.min(50000,Math.max(range.getHeight(),lines.length)),width=Math.min(512-col,lines.reduce((max,line)=>Math.max(max,line.split('\t').length),range.getWidth()));for(let c=col;c<col+width;c++)if(phoneColumn(worksheet,c)){const start=Math.max(1,row),count=Math.min(worksheet.getMaxRows(),row+height)-start;if(count>0)worksheet.getRange(start,c,count,1).setNumberFormat('@');}});
 editor.api.addEvent(editor.api.Event.ClipboardPasted,p=>{if(editable())stampRows(editor,p.worksheet);});editor.api.addEvent(editor.api.Event.SheetEditStarted,()=>{cellEditing=true;clearTimeout(timer);});editor.api.addEvent(editor.api.Event.SheetEditEnded,p=>{if(editable()&&p.isConfirm)stampRows(editor,p.worksheet,p.row,p.row);cellEditing=false;if(dirty())schedule();});
 editor.api.addEvent(editor.api.Event.CommandExecuted,e=>{if(applyingReturns||totalsInternal())return;if(e.type===1&&/insert-row|remove-row|insert-sheet|copy-sheet/.test(e.id))ensureTotalRows(editor);if(!loading&&userTouched&&editable()&&e.type===1&&!e.id.startsWith('formula.')&&operations.length<1000)operations.push({command:e.id,at:Date.now()});if(timeInternal()||loading||!userTouched||!editable()||e.type!==2||e.id.startsWith('formula.')||e.id.includes('formula-mark-dirty'))return;if(e.id==='sheet.mutation.set-range-values'&&e.params?.cellValue){const rows=Object.keys(e.params.cellValue).map(Number).filter(Number.isInteger);if(rows.length)stampRows(editor,editor.workbook.getSheetBySheetId(e.params.subUnitId),Math.min(...rows),Math.max(...rows));}epoch++;status('Có thay đổi · Đang chờ tự lưu');schedule();});
 editor.api.addEvent(editor.api.Event.SelectionChanged,()=>{try{const range=editor.workbook.getActiveSheet().getActiveRange();if(range)$('selectedCell').textContent=range.getA1Notation()+' · '+(range.getHeight()===1&&range.getWidth()===1?String(range.getValue()??'').slice(0,100):range.getHeight()+' hàng × '+range.getWidth()+' cột');}catch{}});
 await new Promise(requestAnimationFrame);loading=false;$('save').disabled=!editable();$('receive').disabled=!editable();$('bookName').readOnly=!editable();$('teamOpen').hidden=true;$('history').hidden=!can('history');$('csv').hidden=!can('export');setupMenus({editor,can,status,touch:()=>{userTouched=true;},reload:()=>$('reload').click(),csv:()=>$('csv').click(),history:()=>$('history').click(),team:()=>location.assign('people.php?tab=settings')});status('Đã đồng bộ · Phiên bản '+revision);if((serialChanged||timeChanged||dimensionChanged||headerChanged||ageChanged)&&editable()){epoch++;schedule();}}
function schedule(){clearTimeout(timer);if(!paused&&!cellEditing&&editable())timer=setTimeout(()=>save(),3000);}
async function save(){if(saving||loading||!editor||!editable())return;await editor.workbook.endEditingAsync(true);if(!dirty())return;clearTimeout(timer);saving=true;$('save').disabled=true;status('Đang lưu…');try{await editor.workbook.endEditingAsync(true);ensureSerialColumn(editor.workbook,editable(),editor.api,false);ensureTimeColumns(editor,true);const currentEpoch=epoch;const pendingOperations=operations.slice();const snapshot=editor.workbook.save();snapshot.name=$('bookName').value.trim()||'THE VICTOR · Đơn hàng';const result=await request('save',{version:revision,snapshot,operations:pendingOperations});revision=result.version;applyTimePatches(editor,result.timePatches);savedEpoch=currentEpoch;operations.splice(0,pendingOperations.length);status('Đã lưu lúc '+new Date().toLocaleTimeString('vi-VN')+' · Phiên bản '+revision);if(dirty())schedule();}catch(e){paused=true;status(e.message+' Tự lưu đã tạm dừng; phần đang sửa vẫn được giữ.',true);}finally{saving=false;$('save').disabled=!editable();}}
async function load(force=false){if(saving||loading||(returnsBusy&&!force))return;if(dirty()&&!force&&!confirm('Làm mới và bỏ phần chưa lưu? Nội dung chưa lưu sẽ bị bỏ.'))return;clearTimeout(timer);try{status('Đang mở sổ tính…');const result=await request();revision=result.version;user=result.user;await mount(result.snapshot);}catch(e){status(e.message,true);}}
$('save').onclick=save;$('reload').onclick=()=>load();$('bookName').oninput=()=>{if(editable()){epoch++;schedule();}};
$('receive').onclick=async()=>{if(saving||!editable())return;await save();if(dirty()){status('Cần lưu thành công phần đang sửa trước khi nhận đơn mới.',true);return;}try{const result=await request('receive',{version:revision});revision=result.version;await mount(result.snapshot);status('Đã nhận '+result.added+' đơn mới. Không nhập lại các đơn đã nhận.');}catch(e){status(e.message,true);}};
$('csv').onclick=async()=>{if(!can('export')){status('Bạn chưa được cấp quyền xuất CSV.',true);return;}try{await editor.workbook.endEditingAsync(true);if(editable())await save();if(dirty())throw Error('Cần lưu thành công trước khi xuất CSV.');const sheet=editor.workbook.getActiveSheet();if(import.meta.env.MODE==='preview'){const rows=sheet.getRange(0,0,5,8).getDisplayValues();download(rows.map(r=>r.join(',')).join('\r\n'),'preview.csv','text/csv');return;}const response=await fetch('workbook-api.php?action=export&sheet='+encodeURIComponent(sheet.getSheetId()),{cache:'no-store'});if(!response.ok){const error=await response.json();throw Error(error.error);}download(await response.text(),'thevictor-bang-don.csv','text/csv;charset=utf-8');}catch(e){status(e.message,true);}};
$('history').onclick=async()=>{try{const data=await request('history');$('historyList').replaceChildren();for(const h of data.history){const row=document.createElement('div');row.className='history-row';const label=document.createElement('span');label.textContent='Phiên bản '+h.version+' · '+h.actor+' · '+new Date(h.updated_at).toLocaleString('vi-VN');row.append(label);if(can('restore')&&editable()){const button=document.createElement('button');button.textContent='Khôi phục';button.onclick=async()=>{if(!confirm('Khôi phục toàn bộ bảng về phiên bản '+h.version+'?'))return;try{const result=await request('restore',{version:revision,restoreVersion:+h.version});revision=result.version;await mount(result.snapshot);$('historyDialog').close();}catch(err){status(err.message,true);}};row.append(button);}$('historyList').append(row);}if(!data.history.length)$('historyList').textContent='Chưa có phiên bản cũ.';$('historyDialog').showModal();}catch(e){status(e.message,true);}};$('historyClose').onclick=()=>$('historyDialog').close();
setInterval(async()=>{if(!editor||saving||dirty()||returnsBusy)return;try{const askedRevision=revision;const result=await request('version');if(askedRevision!==revision)return;if(result.version!==revision){if(!cellEditing&&!(userTouched&&epoch>0))await load();else status('Có bản mới trên máy chủ. Làm mới khi đã hoàn tất Hoàn tác/Làm lại của phiên này.');}}catch{}},10000);
window.addEventListener('beforeunload',e=>{if(dirty()||cellEditing){e.preventDefault();e.returnValue='';}});window.addEventListener('pagehide',()=>clearTimeout(timer));load();

if('BroadcastChannel' in window){const inboxChannel=new BroadcastChannel('victor-workbook');inboxChannel.onmessage=e=>{if(returnsBusy||e.data?.origin===returnsOrigin)return;if(!dirty()&&!cellEditing&&!saving&&!(userTouched&&epoch>0))load();else status(e.data?.type==='returns'?'Có cập nhật lý do hoàn. Lưu xong rồi làm mới để xem.':'Có đơn vừa được lên bảng. Lưu xong rồi làm mới để xem.');};}





const dailyButton=document.createElement('button');dailyButton.textContent='Tạo sheet theo ngày';dailyButton.onclick=()=>{if(!editable())return;const dialog=document.createElement('dialog');dialog.innerHTML='<form method="dialog"><h2>Tạo sheet NAM / NỮ</h2><label>Ngày đơn <input type="date" name="day" required></label><p>Hai sheet cùng ngày; không tạo trùng tên đã có.</p><button value="cancel" formnovalidate>Hủy</button><button value="create" class="primary">Tạo sheet</button></form>';document.body.append(dialog);dialog.querySelector('input').value=new Date().toLocaleDateString('en-CA',{timeZone:'Asia/Ho_Chi_Minh'});dialog.addEventListener('close',()=>{if(dialog.returnValue==='create'){const parts=dialog.querySelector('input').value.split('-');const date=Number(parts[2])+'/'+Number(parts[1]);const snapshot=editor.workbook.save();const base=snapshot.sheets[snapshot.sheetOrder[0]];for(const group of ['NAM','NỮ']){const name=date+' '+group;if(Object.values(editor.workbook.save().sheets).some(s=>s.name===name))continue;userTouched=true;editor.workbook.insertSheet(name,{sheet:dailyTemplate(base)});}epoch++;schedule();}dialog.remove();});dialog.showModal();};document.querySelector('.workbook-actions').append(dailyButton);

setupReturnsChat({
 identity:()=>user?.username,
 editable:()=>can('edit'),
 prepare:async()=>{
  if(saving||loading)throw Error('Bảng đang đồng bộ. Đợi hoàn tất rồi gửi lại.');
  await save();
  if(dirty()||paused||cellEditing)throw Error('Cần lưu thành công phần bảng đang sửa trước khi gửi thông báo hoàn.');
  returnsBusy=true;document.body.classList.add('returns-busy');
  document.querySelectorAll('#editor,.sheet-menus,.workbook-actions').forEach(e=>e.inert=true);
 },
 release:()=>{
  returnsBusy=false;document.body.classList.remove('returns-busy');
  document.querySelectorAll('#editor,.sheet-menus,.workbook-actions').forEach(e=>e.inert=false);
 },
 apply:async result=>{
  if(result.version===revision)return;
  if(dirty())throw Error('Tin đã lưu. Bảng có thay đổi chưa lưu; giữ nguyên dữ liệu và làm mới sau khi lưu xong.');
  if(result.baseVersion!==revision){status('Thông báo hoàn đã lưu. Bảng vừa được người khác thay đổi; bấm Làm mới để đồng bộ.',true);return;}
  applyingReturns=true;
  try{
   for(const p of result.patches??[])editor.api.syncExecuteCommand(SetRangeValuesMutation.id,{unitId:editor.workbook.getId(),subUnitId:p.sheet,cellValue:{[p.row]:{[p.col]:p.cell??{v:null}}}});
   revision=result.version;status('Đã cập nhật lý do hoàn · Phiên bản '+revision);
  }finally{applyingReturns=false;}
  if('BroadcastChannel' in window){const c=new BroadcastChannel('victor-workbook');c.postMessage({type:'returns',origin:returnsOrigin});c.close();}
 }
});
document.addEventListener('keydown',e=>{if(returnsBusy&&!e.target.closest?.('.returns-panel')){e.preventDefault();e.stopImmediatePropagation();}},true);

setupCancelChat({editable:()=>can('edit'),identity:()=>user?.username});
