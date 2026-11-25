import test from 'node:test';import assert from 'node:assert/strict';import {freshNoticeIds,retainActiveNoticeIds} from './notifications-state.js';
test('new order sounds only once even when delivered by more than one poll',()=>{const first=freshNoticeIds([8,8,9],7);assert.deepEqual(first,[8,9]);assert.deepEqual(freshNoticeIds([8,9,9],Math.max(...first)),[]);});
test('already acknowledged orders are skipped and new arrivals retain order',()=>assert.deepEqual(freshNoticeIds([15,12,11,14],12),[14,15]));
test('transferred or otherwise processed orders leave the delayed audio queue',()=>assert.deepEqual(retainActiveNoticeIds([3,4,4,5],[4,6]),[4]));
