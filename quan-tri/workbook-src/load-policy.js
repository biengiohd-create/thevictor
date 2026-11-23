export const TIME_COLUMN_WIDTH=88;
const text=cell=>String(cell?.v??'').trim().toLocaleUpperCase('vi');
export function prepareTimeWidths(snapshot){let changed=false;for(const sheet of Object.values(snapshot.sheets)){if(text(sheet.cellData?.[0]?.[0])!=='THỜI GIAN'||sheet.custom?.victorTimeWidth88)continue;sheet.columnData??={};sheet.columnData[0]={...sheet.columnData[0],w:TIME_COLUMN_WIDTH};sheet.custom={...sheet.custom,victorTimeWidth88:true};changed=true;}return changed;}
export function needsTimeSetup(snapshot){return Object.values(snapshot.sheets).some(sheet=>text(sheet.cellData?.[0]?.[0])!=='THỜI GIAN');}
export function needsTotalSetup(snapshot){return Object.values(snapshot.sheets).some(sheet=>text(sheet.cellData?.[0]?.[0])==='THỜI GIAN'&&text(sheet.cellData?.[sheet.rowCount-1]?.[0])!=='TỔNG');}
