export function freshNoticeIds(ids,last){return [...new Set(ids.filter(id=>Number.isSafeInteger(id)&&id>last))].sort((a,b)=>a-b);}

export function retainActiveNoticeIds(pending,active){const allowed=new Set(active);return [...new Set(pending.filter(id=>allowed.has(id)))];}
