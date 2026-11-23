
import {OpenFindDialogOperation,OpenReplaceDialogOperation} from '@univerjs/find-replace';
import {OpenConditionalFormattingOperator} from '@univerjs/sheets-conditional-formatting-ui';
let closeFilterPopover=()=>{};
export function setupMenus({editor,can,status,touch,reload,csv,history,team}){
 closeFilterPopover();const host=document.getElementById('sheetMenus');if(!host)return;host.replaceChildren();const sheet=()=>editor.workbook.getActiveSheet(),range=()=>sheet().getActiveRange();
 const bounds=()=>{let row=0,col=0;for(const [r,cells]of Object.entries(editor.workbook.save().sheets[sheet().getSheetId()].cellData||{}))for(const [c,cell]of Object.entries(cells||{}))if(cell&&(cell.v!==undefined||cell.f||cell.p)){row=Math.max(row,+r);col=Math.max(col,+c);}return {row,col};};
 const filter=mode=>{const active=sheet().getFilter();if(active)active.remove();if(mode==='off')return;const b=bounds();if(mode==='column')sheet().getRange(0,range().getColumn(),Math.max(2,b.row+1),1).createFilter();else sheet().getRange(0,0,Math.max(2,b.row+1),b.col+1).createFilter();};
 const sort=ascending=>{const b=bounds();if(b.row<1)return;sheet().getRange(1,0,b.row,b.col+1).sort({column:range().getColumn(),ascending});};
 const menus=[['Tệp',[['Làm mới',reload],['Xuất CSV',csv,'export']]],['Chỉnh sửa',[['Hoàn tác',()=>editor.api.undo(),'edit'],['Làm lại',()=>editor.api.redo(),'edit'],['Tìm kiếm',()=>editor.api.executeCommand(OpenFindDialogOperation.id)],['Tìm và thay thế',()=>editor.api.executeCommand(OpenReplaceDialogOperation.id),'edit'],['Xóa nội dung vùng chọn',()=>range().clearContent(),'edit']]],['Xem',[['Cố định hàng tiêu đề',()=>sheet().setFrozenRows(1),'edit'],['Cố định cột STT',()=>sheet().setFrozenColumns(1),'edit'],['Bỏ cố định',()=>{sheet().setFrozenRows(0);sheet().setFrozenColumns(0);},'edit'],['Ẩn đường lưới',()=>sheet().setHiddenGridlines(true),'edit'],['Hiện đường lưới',()=>sheet().setHiddenGridlines(false),'edit']]],['Chèn',[['Hàng phía trên',()=>sheet().insertRowsBefore(range().getRow(),1),'edit'],['Hàng phía dưới',()=>sheet().insertRowsAfter(range().getRow(),1),'edit'],['Cột bên trái',()=>sheet().insertColumnsBefore(range().getColumn(),1),'edit'],['Cột bên phải',()=>sheet().insertColumnsAfter(range().getColumn(),1),'edit'],['Trang tính mới',()=>editor.workbook.create('Trang '+Date.now().toString().slice(-5),200,17).activate(),'edit']]],['Định dạng',[['In đậm',()=>range().setFontWeight('bold'),'edit'],['Chữ thường',()=>range().setFontWeight('normal'),'edit'],['Xuống dòng trong ô',()=>range().setWrap(true),'edit'],['Gộp ô',()=>range().merge(),'edit'],['Bỏ gộp ô',()=>range().breakApart(),'edit'],['Định dạng có điều kiện',()=>editor.api.executeCommand(OpenConditionalFormattingOperator.id,{value:3}),'edit'],['Định dạng số tiền',()=>range().setNumberFormat('#,##0'),'edit'],['Văn bản thuần túy',()=>range().setNumberFormat('@'),'edit']]],['Dữ liệu',[['Lọc toàn bộ bảng',()=>filter('all'),'edit'],['Lọc cột đang chọn',()=>filter('column'),'edit'],['Bỏ bộ lọc',()=>filter('off'),'edit'],['Xác thực dữ liệu / danh sách chọn',()=>editor.api.executeCommand('data-validation.operation.open-validation-panel',{isAdd:true}),'edit'],['Sắp xếp bảng A → Z theo cột đang chọn',()=>sort(true),'edit'],['Sắp xếp bảng Z → A theo cột đang chọn',()=>sort(false),'edit']]],['Công cụ',[['Lịch sử',history,'history'],['CÀI ĐẶT tài khoản và phân quyền',team,'settings']]]];
 const run=async(fn,permission)=>{if(permission&&!can(permission)){status('Bạn chưa được cấp quyền thao tác này.',true);return;}try{if(permission==='edit')touch();await editor.workbook.endEditingAsync(true);await fn();}catch(e){status(e.message,true);}};
 for(const [name,items]of menus){const d=document.createElement('details'),summary=document.createElement('summary');summary.textContent=name;d.append(summary);const list=document.createElement('div');list.className='menu-items';for(const [label,fn,permission]of items){const b=document.createElement('button');b.type='button';b.textContent=label;b.disabled=permission&&!can(permission);b.onclick=()=>{d.open=false;run(fn,permission);};list.append(b);}d.append(list);d.ontoggle=()=>{if(d.open)host.querySelectorAll('details').forEach(other=>{if(other!==d)other.open=false;});};host.append(d);}

 const openFilterMenu=()=>{
  closeFilterPopover();const anchor=document.querySelector('[data-u-command="victor.filter.toolbar"]');if(!anchor)return;
  const menu=document.createElement('div');menu.className='victor-filter-popover';menu.setAttribute('role','menu');menu.setAttribute('aria-label','Bộ lọc');
  const controller=new AbortController();closeFilterPopover=()=>{controller.abort();menu.remove();};
  for(const [label,mode]of [['Lọc toàn bộ bảng','all'],['Lọc cột đang chọn','column'],['Bỏ bộ lọc','off']]){const b=document.createElement('button');b.type='button';b.setAttribute('role','menuitem');b.textContent=label;b.disabled=!can('edit');b.onclick=()=>{closeFilterPopover();run(()=>filter(mode),'edit');};menu.append(b);}
  const rect=anchor.getBoundingClientRect();menu.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-240))+'px';menu.style.top=rect.bottom+4+'px';document.body.append(menu);menu.querySelector('button')?.focus();
  document.addEventListener('pointerdown',e=>{if(!menu.contains(e.target)&&!anchor.contains(e.target))closeFilterPopover();},{capture:true,signal:controller.signal});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeFilterPopover();anchor.focus();}},{signal:controller.signal});
 };
 editor.api.createMenu({id:'victor.filter.toolbar',title:'Bộ lọc',tooltip:'Bộ lọc',order:-100,action:openFilterMenu}).appendTo('ribbon.start.layout');
 host.onkeydown=e=>{if(e.key==='Escape')host.querySelectorAll('details').forEach(d=>d.open=false);};
}




