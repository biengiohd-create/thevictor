export function overviewRange(period,today){
 const date=new Date(today+'T00:00:00Z');if(!/^\d{4}-\d{2}-\d{2}$/.test(today)||!Number.isFinite(+date))throw Error('Ngày không hợp lệ');
 const iso=d=>d.toISOString().slice(0,10),shift=(d,n)=>{const v=new Date(d);v.setUTCDate(v.getUTCDate()+n);return v;};let from=date,to=date;
 switch(period){case 'today':break;case 'yesterday':from=to=shift(date,-1);break;case 'day_before_yesterday':from=to=shift(date,-2);break;case 'this_week':from=shift(date,-((date.getUTCDay()+6)%7));to=shift(from,6);break;case 'last_week':from=shift(date,-((date.getUTCDay()+6)%7)-7);to=shift(from,6);break;case 'this_month':from=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth(),1));to=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth()+1,0));break;case 'last_month':from=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth()-1,1));to=new Date(Date.UTC(date.getUTCFullYear(),date.getUTCMonth(),0));break;case 'custom':return null;default:throw Error('Mốc thời gian không hợp lệ');}
 return {from:iso(from),to:iso(to)};
}
export function overviewRangeLabel(from,to){const fmt=s=>/^\d{4}-\d{2}-\d{2}$/.test(s)?s.split('-').reverse().join('/'):'…';return from===to?fmt(from):fmt(from)+' – '+fmt(to);}
