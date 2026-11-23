import{test}from'node:test';import assert from'node:assert/strict';import{isTotalRow}from'./total-row-core.js';
test('recognizes footer after spreadsheet engine drops custom metadata',()=>{const s={cellData:{0:{0:{v:'THỜI GIAN'},2:{v:'Tiền'}},199:{0:{v:'15:08:12'},1:{v:199},2:{f:'=SUM(C2:C199)',v:2487000}}}};assert.equal(isTotalRow(s,199),true);s.cellData[199][3]={v:'Actual customer'};assert.equal(isTotalRow(s,199),false);});
test('literal footer survives before formula recalculation',()=>assert.equal(isTotalRow({cellData:{199:{0:{v:'TỔNG'}}}},199),true));
