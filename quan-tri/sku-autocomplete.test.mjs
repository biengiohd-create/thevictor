import {test} from 'node:test';import assert from 'node:assert/strict';import {skuSuggestions} from './sku-autocomplete.js';
const catalog=[{sku:'E1234',name:'Nhóm nam'},{sku:'E2',name:'Áo nam'},{sku:'E1',name:'Áo cơ bản'},{sku:'E1',name:'Duplicate'},...Array.from({length:20},(_,i)=>({sku:'TEST'+i,name:'Sản phẩm thử'}))];
test('empty focus does not expose whole catalogue',()=>assert.deepEqual(skuSuggestions(catalog,''),[]));
test('typing filters immediately and ranks exact SKU before prefix matches',()=>assert.deepEqual(skuSuggestions(catalog,' e1 ').map(p=>p.sku),['E1','E1234']));
test('suggestion list is bounded and deduplicated',()=>{assert.equal(skuSuggestions(catalog,'test').length,8);assert.equal(skuSuggestions(catalog,'E1').length,2);});
test('names accept Vietnamese with or without accents without selecting unrelated codes',()=>{assert.equal(skuSuggestions(catalog,'ao co ban')[0].sku,'E1');assert.deepEqual(skuSuggestions(catalog,'NO-SUCH-SKU'),[]);});
