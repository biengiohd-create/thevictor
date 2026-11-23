// Let native text editing handle clipboard operations before spreadsheet shortcuts.
const inChat=e=>e.target instanceof Element&&!!e.target.closest('.returns-panel input,.returns-panel textarea,[contenteditable=true].returns-body');
for(const type of ['copy','cut','paste','contextmenu'])window.addEventListener(type,e=>{if(inChat(e))e.stopImmediatePropagation();},{capture:true});
window.addEventListener('keydown',e=>{if(inChat(e)&&(e.ctrlKey||e.metaKey)&&['c','v','x','a','z','y'].includes(e.key.toLowerCase()))e.stopImmediatePropagation();},{capture:true});
