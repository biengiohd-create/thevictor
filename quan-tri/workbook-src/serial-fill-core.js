export function serialFillValues(source,targetRows){
 const sorted=[...source].sort((a,b)=>a.row-b.row);
 if(sorted.length<2||sorted.some(x=>x.value==null||String(x.value).trim()===''||!Number.isFinite(Number(x.value))))return [];
 const first=sorted[0],second=sorted[1],step=(Number(second.value)-Number(first.value))/(second.row-first.row);
 if(!Number.isFinite(step)||sorted.some(x=>Math.abs(Number(x.value)-(Number(first.value)+(x.row-first.row)*step))>1e-9))return [];
 return targetRows.map(row=>({row,value:Number(first.value)+(row-first.row)*step}));
}
