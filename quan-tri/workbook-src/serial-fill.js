import {IAutoFillService,AUTO_FILL_APPLY_TYPE,AUTO_FILL_HOOK_TYPE,SetRangeValuesMutation,SetRangeValuesUndoMutationFactory} from '@univerjs/sheets';
import {serialFillValues} from './serial-fill-core.js';
import {isTotalRow} from './total-row-core.js';
export function setupSerialFill(editor){
 const injector=editor.univer.__getInjector(),fill=injector.get(IAutoFillService);
 const serialSheet=location=>{const sheet=editor.workbook.getSheetBySheetId(location.subUnitId);return location.source.cols.length===1&&String(sheet?.getRange(0,location.source.cols[0]).getValue()).trim().toUpperCase()==='STT'?sheet:null;};
 return fill.addHook({id:'victor-serial-values-only',type:AUTO_FILL_HOOK_TYPE.APPEND,
  onBeforeFillData(location){if(serialSheet(location))return AUTO_FILL_APPLY_TYPE.NO_FORMAT;},
  onFillData(location){
   const sheet=serialSheet(location);if(!sheet)return {undos:[],redos:[]};
   const col=location.source.cols[0],matrix=sheet.getSheet().getCellMatrix().getMatrix();
   const values=serialFillValues(location.source.rows.map(row=>({row,value:matrix[row]?.[col]?.v})),location.target.rows.filter(row=>row>0&&!isTotalRow({cellData:matrix},row)));
   if(!values.length)return {undos:[],redos:[]};
   const params={unitId:location.unitId,subUnitId:location.subUnitId,cellValue:Object.fromEntries(values.map(({row,value})=>[row,{[col]:{v:value,t:2,f:null,p:null}}]))};
   return {undos:[{id:SetRangeValuesMutation.id,params:SetRangeValuesUndoMutationFactory(injector,params)}],redos:[{id:SetRangeValuesMutation.id,params}]};
  }
 });
}
