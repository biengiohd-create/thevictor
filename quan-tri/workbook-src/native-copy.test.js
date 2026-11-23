import {test} from 'node:test';import assert from 'node:assert/strict';import {writeNativeSelection} from './native-copy.js';
test('native copy writes tabular text for chat and rich internal data without browser clipboard APIs',()=>{
 const data=new Map(),cache=new Map(),range={startRow:1,endRow:2,startColumn:0,endColumn:4};
 const matrix={1:{0:{v:'15:10:50'},2:{f:'=SUM(C2:C3)',v:1508000},3:{v:'Tên khách'},4:{v:'0829402508'}}};
 const service={generateCopyContent:(book,sheet,selection)=>{assert.equal(book,'book');assert.equal(sheet,'women');assert.equal(selection,range);return {copyId:'copy-id',plain:'15:10:50\t1\t1.508.000\tTên khách\t0829402508',html:'<table data-copy-id="copy-id"><tr><td>0829402508</td></tr></table>',discreteRange:range,matrixFragment:matrix};},copyContentCache:()=>cache};
 assert.equal(writeNativeSelection(service,'book','women',range,{setData:(type,value)=>data.set(type,value)}),true);
 assert.match(data.get('text/plain'),/0829402508$/);assert.match(data.get('text/html'),/data-copy-id/);
 assert.equal(cache.get('copy-id').matrix,matrix);assert.equal(cache.get('copy-id').copyType,'COPY');
});
test('missing clipboard event or empty selection leaves native text copy untouched',()=>{
 const service={generateCopyContent:()=>null};assert.equal(writeNativeSelection(service,'b','s',{},null),false);assert.equal(writeNativeSelection(service,'b','s',{},{}),false);
});
