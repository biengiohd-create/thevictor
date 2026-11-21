import './notifications.js?v=15';
// Polling and a background tab do not extend payable time. Only user interaction sends activity.
const csrf=document.querySelector('meta[name=csrf-token]')?.content;let last=0,sent=0,timer;
function send(){if(!csrf||!last||last<=sent)return;const at=last;sent=at;fetch('people-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'activity',csrf,at:Math.floor(at/1000)}),keepalive:true}).catch(()=>{sent=0;});}
function activity(e){if(e&&!e.isTrusted)return;last=Date.now();clearTimeout(timer);if(last-sent>=20000)send();else timer=setTimeout(send,20000-(last-sent));}
for(const type of ['pointerdown','keydown','input','wheel','touchmove'])document.addEventListener(type,activity,{passive:true,capture:true});window.addEventListener('pagehide',send);activity();
