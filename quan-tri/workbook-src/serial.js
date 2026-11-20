// Insert through Univer so existing formulas, formats and references move with their columns.
import {SetRangeValuesMutation} from '@univerjs/sheets';
export function ensureSerialColumn(workbook,editable,api,initialize=true){
 if(!editable)return false;const sheet=workbook.getSheetBySheetId('orders');if(!sheet)return false;
 let changed=false;
 if(String(sheet.getRange(0,0).getValue()??'').trim().toUpperCase()!=='STT'){
  if(!initialize||Object.values(workbook.save().sheets.orders.cellData?.[0]??{}).some(c=>String(c?.v??'').trim().toUpperCase()==='STT'))return false;
  sheet.insertColumnsBefore(0,1);sheet.setColumnWidth(0,68);
  sheet.getRange(0,0).setValue('STT').setBackgroundColor('#00e500').setFontWeight('bold');changed=true;
 }
 const cells=workbook.save().sheets.orders.cellData??{};
 for(const [row,data]of Object.entries(cells)){
  if(+row<1||!data)continue;
  const occupied=Object.entries(data).some(([col,cell])=>+col>0&&cell&&(cell.f||cell.p||(cell.v!==undefined&&cell.v!==null&&cell.v!=='')));
  const serial=data[0];if(occupied&&!serial?.f&&(serial?.v===undefined||serial.v===null||serial.v==='')){
   let n=sheet.getMaxColumns(),last='';while(n){n--;last=String.fromCharCode(65+n%26)+last;n=Math.floor(n/26);}
   // Derived numbering is not a user edit: it must not push an undo entry or clear redo.
   api.syncExecuteCommand(SetRangeValuesMutation.id,{unitId:workbook.getId(),subUnitId:sheet.getSheetId(),cellValue:{[row]:{0:{f:`=IF(COUNTA(B${+row+1}:${last}${+row+1})=0,"",ROW()-1)`,custom:{victorSerial:true}}}}});changed=true;
  }
 }
 return changed;
}

