// Insert through Univer so existing formulas, formats and references move with their columns.
export function ensureSerialColumn(workbook,editable){
 if(!editable)return false;const sheet=workbook.getSheetBySheetId('orders');if(!sheet)return false;
 let changed=false;
 if(String(sheet.getRange(0,0).getValue()??'').trim().toUpperCase()!=='STT'){
  sheet.insertColumnsBefore(0,1);sheet.setColumnWidth(0,68);
  sheet.getRange(0,0).setValue('STT').setBackgroundColor('#00e500').setFontWeight('bold');changed=true;
 }
 const cells=workbook.save().sheets.orders.cellData??{};
 for(const [row,data]of Object.entries(cells)){
  if(+row<1||!data)continue;
  const occupied=Object.entries(data).some(([col,cell])=>+col>0&&cell&&(cell.f||cell.p||(cell.v!==undefined&&cell.v!==null&&cell.v!=='')));
  const serial=data[0];if(occupied&&!serial?.f&&(serial?.v===undefined||serial.v===null||serial.v==='')){
   sheet.getRange(+row,0).setFormula('=ROW()-1');changed=true;
  }
 }
 return changed;
}
