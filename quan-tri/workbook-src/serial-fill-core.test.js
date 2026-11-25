import {test} from 'node:test';import assert from 'node:assert/strict';import {serialFillValues} from './serial-fill-core.js';
test('two numeric text seeds extend as a sequence rather than repeating',()=>{assert.deepEqual(serialFillValues([{row:1,value:'1'},{row:2,value:'2'}],[3,4,5]),[{row:3,value:3},{row:4,value:4},{row:5,value:5}]);});
test('offset and step are preserved in either drag direction',()=>{assert.deepEqual(serialFillValues([{row:8,value:10},{row:9,value:12}],[6,7,10]),[{row:6,value:6},{row:7,value:8},{row:10,value:14}]);});
test('invalid or nonarithmetic source is left to regular fill',()=>{assert.deepEqual(serialFillValues([{row:1,value:'1'},{row:2,value:''}],[3]),[]);assert.deepEqual(serialFillValues([{row:1,value:1},{row:2,value:2},{row:3,value:5}],[4]),[]);});
