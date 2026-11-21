export function freshNoticeIds(ids,last){return [...new Set(ids.filter(id=>Number.isSafeInteger(id)&&id>last))].sort((a,b)=>a-b);}
