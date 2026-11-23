// Synchronous browser copy events need no asynchronous clipboard permission.
// Keep Univer's rich payload and internal cache for formulas, styles and widths.
export function writeNativeSelection(service,unitId,subUnitId,range,data){
 if(!data)return false;
 const content=service.generateCopyContent(unitId,subUnitId,range,{copyType:'COPY'});
 if(!content)return false;
 data.setData('text/plain',content.plain??'');
 data.setData('text/html',content.html??'');
 service.copyContentCache().set(content.copyId,{unitId,subUnitId,range:content.discreteRange,matrix:content.matrixFragment,copyType:'COPY',skipCellCopy:false});
 return true;
}
