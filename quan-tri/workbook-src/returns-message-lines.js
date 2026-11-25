// Keep original lines and distinguish automatic matches from manual approval.
const normalize=value=>String(value??'').trim().replace(/\s+/gu,' ');
const trackingCodes=text=>String(text).match(/(?<!\d)\d{8,20}(?!\d)/gu)??[];
export function returnMessageLines(message){
 const entries=message.entries??[],allCodes=trackingCodes(message.body??'');
 return String(message.body??'').split(/\r\n|\r|\n/u).map((text,index)=>{
  if(message.processing==='pending')return {text,state:'pending',title:'Đã nhận, đang đối chiếu'};
  const codes=trackingCodes(text);let related=codes.length?entries.filter(e=>codes.includes(String(e.tracking))):entries.filter(e=>normalize(e.raw)===normalize(text));
  if(!related.length&&allCodes.length===1)related=entries.filter(e=>String(e.tracking)===allCodes[0]);
  const matched=related.length>0&&related.every(e=>e.state==='matched'&&e.matches?.length>0),approval=message.manual_approvals?.[index];
  return {text,state:matched?'matched':approval?'manual':'unresolved',title:matched?'Đã tự động điền lý do hoàn':approval?'Đã duyệt thủ công bởi '+approval.actor+' · '+new Date(approval.at).toLocaleString('vi-VN'):'Chưa tìm thấy hoặc chưa tự động điền được'};
 });
}
