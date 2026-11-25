// Synchronous browser copy events need no asynchronous clipboard permission.
// Keep Univer's rich payload and internal cache for formulas, styles and widths.
export function writeNativeSelection(service,unitId,subUnitId,range,data,onCopied=()=>{}){
 if(!data)return false;
 const content=service.generateCopyContent(unitId,subUnitId,range,{copyType:'COPY'});
 if(!content)return false;
 data.setData('text/plain',content.plain??'');
 data.setData('text/html',content.html??'');
 service.copyContentCache().set(content.copyId,{unitId,subUnitId,range:content.discreteRange,matrix:content.matrixFragment,copyType:'COPY',skipCellCopy:false});
 onCopied(content);
 return true;
}

export async function mirrorNativeCopy(content,platform=globalThis){
 const clipboard=platform.navigator?.clipboard;
 if(!clipboard)return;
 try{
  if(clipboard.write&&platform.ClipboardItem&&platform.Blob){
   await clipboard.write([new platform.ClipboardItem({'text/plain':new platform.Blob([content.plain??''],{type:'text/plain'}),'text/html':new platform.Blob([content.html??''],{type:'text/html'})})]);return;
  }
 }catch{}
 try{await clipboard.writeText?.(content.plain??'');}catch{}
}
