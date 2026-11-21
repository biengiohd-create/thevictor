import {bindPeriod} from './date-presets.js';
const form=document.getElementById('filters');if(form){bindPeriod(form);form.elements.period.addEventListener('change',()=>{if(form.elements.period.value!=='custom')form.requestSubmit();});}
