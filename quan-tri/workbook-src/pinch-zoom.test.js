import {test} from 'node:test';import assert from 'node:assert/strict';import {pinchZoom} from './pinch-zoom.js';
test('pinch distance scales sheet zoom and remains bounded',()=>{assert.equal(pinchZoom(1,100,150),1.5);assert.equal(pinchZoom(1,100,50),.5);assert.equal(pinchZoom(1,100,1000),3);assert.equal(pinchZoom(1,100,1),.25);});
test('invalid gesture baseline keeps the current zoom',()=>{assert.equal(pinchZoom(.75,0,50),.75);assert.equal(pinchZoom(.75,100,NaN),.75);});
