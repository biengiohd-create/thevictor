import {test} from 'node:test';import assert from 'node:assert/strict';import {withoutNumericTextMarker} from './text-marker.js';
test('hide only numeric-text triangle without changing phone, type, format or source markers',()=>{
 const source={v:'0829402508',t:1,s:{n:{pattern:'@'}},markers:{tl:{color:'#409f11',size:6},tr:{color:'#ff0000',size:4}}};
 const result=withoutNumericTextMarker(source);assert.equal(result.v,'0829402508');assert.equal(result.t,1);assert.equal(result.s,source.s);assert.equal(result.markers.tl,undefined);assert.equal(result.markers.tr,source.markers.tr);assert(source.markers.tl);
});
test('other warnings and unmarked cells remain unchanged',()=>{for(const cell of [null,{v:123},{v:'x',markers:{tl:{color:'#ff0000',size:6}}}])assert.equal(withoutNumericTextMarker(cell),cell);});
