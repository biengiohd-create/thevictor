/** @OnlyCurrentDoc */
// Bound to the user's source workbook. Never writes to source cells.
function victorSetup() {
  const p=PropertiesService.getScriptProperties();
  if(!p.getProperty('VICTOR_SECRET')) throw new Error('Thiếu khóa đồng bộ trong Script Properties.');
  const ss=SpreadsheetApp.getActiveSpreadsheet();
  p.setProperty('VICTOR_FILE',ss.getId());
  const names=['victorEdit','victorChange','victorWorker'];
  ScriptApp.getProjectTriggers().forEach(t=>{if(names.includes(t.getHandlerFunction()))ScriptApp.deleteTrigger(t);});
  ScriptApp.newTrigger('victorEdit').forSpreadsheet(ss).onEdit().create();
  ScriptApp.newTrigger('victorChange').forSpreadsheet(ss).onChange().create();
  ScriptApp.newTrigger('victorWorker').timeBased().everyMinutes(1).create();
  p.setProperty('VICTOR_CURSOR','0');p.setProperty('VICTOR_NEXT','0');
  victorWorker();
}
function victorManifest(ss) {return ss.getSheets().map(s=>({id:'google-'+s.getSheetId(),name:s.getName(),hidden:s.isSheetHidden()}));}
function victorRead(s) {
  const stamp=Date.now(),nr=Math.max(1,s.getLastRow()),nc=Math.max(1,s.getLastColumn());
  if(nr>49990||nc>512||nr*nc>500000)throw new Error('Sheet vượt giới hạn: '+s.getName());
  const range=s.getRange(1,1,nr,nc),display=range.getDisplayValues(),values=range.getValues();
  // Use Google's calculated/displayed result, preserving phone/tracking leading zeroes.
  for(let r=0;r<display.length;r++)for(let c=0;c<display[r].length;c++)if(c===1&&typeof values[r][c]==='number')display[r][c]=values[r][c];
  return {id:'google-'+s.getSheetId(),stamp:stamp,values:display};
}
function victorSend(ss,manifest,stamp,sheets) {
  const body=JSON.stringify({fileId:ss.getId(),manifest:manifest,stamp:stamp,sheets:sheets});
  if(Utilities.newBlob(body).getBytes().length>7900000)throw new Error('Gói đồng bộ quá lớn.');
  const response=UrlFetchApp.fetch('https://thevictor.vn/lamviec/google-sync-api.php',{method:'post',contentType:'application/json',headers:{Authorization:'Bearer '+PropertiesService.getScriptProperties().getProperty('VICTOR_SECRET')},payload:body,muteHttpExceptions:true});
  if(response.getResponseCode()!==200)throw new Error('Máy chủ chưa nhận dữ liệu: HTTP '+response.getResponseCode());
  const result=JSON.parse(response.getContentText());if(!result.ok)throw new Error('Máy chủ chưa xác nhận đồng bộ.');
  PropertiesService.getScriptProperties().setProperty('VICTOR_STATUS',JSON.stringify({at:new Date().toISOString(),pending:result.pending,version:result.version||0}));
  return result;
}
function victorEdit(e) {
  if(!e||!e.range)return;
  const lock=LockService.getScriptLock();if(!lock.tryLock(1000))return;
  try{const ss=e.source,stamp=Date.now(),m=victorManifest(ss);victorSend(ss,m,stamp,[victorRead(e.range.getSheet())]);}
  finally{lock.releaseLock();}
}
function victorChange(e) {const p=PropertiesService.getScriptProperties();p.setProperty('VICTOR_CURSOR','0');p.setProperty('VICTOR_NEXT','0');}
function victorWorker() {
  const lock=LockService.getScriptLock();if(!lock.tryLock(1000))return;
  const p=PropertiesService.getScriptProperties();
  try {
    if(Date.now()<Number(p.getProperty('VICTOR_NEXT')||0))return;
    const ss=SpreadsheetApp.getActiveSpreadsheet();if(ss.getId()!==p.getProperty('VICTOR_FILE'))throw new Error('Sai trang tính nguồn.');
    const started=Date.now(),manifest=victorManifest(ss),sheets=ss.getSheets(),signature=JSON.stringify(manifest);
    let cursor=Number(p.getProperty('VICTOR_CURSOR')||0);
    if(p.getProperty('VICTOR_MANIFEST')!==signature){cursor=0;p.setProperty('VICTOR_MANIFEST',signature);}
    while(cursor<sheets.length&&Date.now()-started<180000){
      const stamp=Date.now(),batch=[];let chars=0;
      while(cursor<sheets.length&&batch.length<15&&chars<1800000&&Date.now()-started<180000){const s=victorRead(sheets[cursor]);batch.push(s);chars+=JSON.stringify(s).length;cursor++;}
      if(batch.length)victorSend(ss,manifest,stamp,batch);
      p.setProperty('VICTOR_CURSOR',String(cursor));
    }
    if(cursor>=sheets.length){p.setProperty('VICTOR_CURSOR','0');p.setProperty('VICTOR_NEXT',String(Date.now()+30*60000));}
    p.deleteProperty('VICTOR_ERROR');
  } catch(e){p.setProperty('VICTOR_ERROR',new Date().toISOString()+' '+String(e.message).slice(0,200));throw e;}
  finally{lock.releaseLock();}
}
