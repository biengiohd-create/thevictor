export function refineAppearance(snapshot){
 const base=typeof snapshot.defaultStyle==='string'?snapshot.styles?.[snapshot.defaultStyle]??{}:snapshot.defaultStyle??{};snapshot.defaultStyle={...base,ff:base.ff??'Arial',fs:base.fs??11,cl:base.cl??{rgb:'#172b24'},vt:base.vt??2};
 for(const sheet of Object.values(snapshot.sheets)){for(const cell of Object.values(sheet.cellData?.[0]??{})){if(!cell)continue;const style=typeof cell.s==='string'?snapshot.styles?.[cell.s]??{}:cell.s??{};const bg=String(style.bg?.rgb??'').toLowerCase();if(['THỜI GIAN','STT'].includes(String(cell.v??'').trim().toLocaleUpperCase('vi')))cell.s={...style,vt:2,cl:{rgb:'#163b2c'},ff:'Arial',fs:11,bl:1};if(['#00e500','#00ff00','#00e600'].includes(bg))cell.s={...style,bg:{rgb:'#dfede5'},cl:{rgb:'#163b2c'},ff:'Arial',fs:11,bl:1,vt:2};}}
 return snapshot;
}
