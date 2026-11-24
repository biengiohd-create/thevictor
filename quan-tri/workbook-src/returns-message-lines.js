// Return the original chat text once, with a status for each original line.
const normalize=value=>String(value??'').trim().replace(/\s+/gu,' ');
const trackingCodes=text=>String(text).match(/(?<!\d)\d{8,20}(?!\d)/gu)??[];
export function returnMessageLines(message){
 const entries=message.entries??[],allCodes=trackingCodes(message.body??'');
 return String(message.body??'').split(/\r\n|\r|\n/u).map(text=>{
  const codes=trackingCodes(text);
  let related=codes.length?entries.filter(e=>codes.includes(String(e.tracking))):entries.filter(e=>normalize(e.raw)===normalize(text));
  if(!related.length&&allCodes.length===1)related=entries.filter(e=>String(e.tracking)===allCodes[0]);
  const matched=related.length>0&&related.every(e=>e.state==='matched'&&e.matches?.length>0);
  return {text,state:matched?'matched':'unresolved',title:matched?'Đã tự động điền lý do hoàn':'Chưa tìm thấy hoặc chưa tự động điền được'};
 });
}
