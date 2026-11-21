export function refineAppearance(snapshot){
 const base=typeof snapshot.defaultStyle==='string'?snapshot.styles?.[snapshot.defaultStyle]??{}:snapshot.defaultStyle??{};snapshot.defaultStyle={...base,ff:base.ff??'Arial',fs:base.fs??11,cl:base.cl??{rgb:'#172b24'},vt:base.vt??2};
 for(const sheet of Object.values(snapshot.sheets)){if(sheet.custom?.victorGreenHeader)continue;for(const cell of Object.values(sheet.cellData?.[0]??{})){if(!cell)continue;const style=typeof cell.s==='string'?snapshot.styles?.[cell.s]??{}:cell.s??{};cell.s={...style,bg:{rgb:'#00ff00'},cl:{rgb:'#102b19'},ff:'Arial',fs:11,bl:1,vt:2};}sheet.custom={...sheet.custom,victorGreenHeader:true};}
 return snapshot;
}
export function removeAgeColumns(editor){let changed=false;for(const sheet of editor.workbook.getSheets()){const headers=sheet.getSheet().getCellMatrix().getMatrix()[0]??{};for(const col of Object.keys(headers).map(Number).sort((a,b)=>b-a)){if(!['tuổi','tuoi'].includes(String(headers[col]?.v??'').trim().toLocaleLowerCase('vi')))continue;sheet.deleteColumns(col,1);changed=true;}if(sheet.getMaxColumns()<17)sheet.setColumnCount(17);}return changed;}
