import {test} from 'node:test';import assert from 'node:assert/strict';import {returnMessageLines} from './returns-message-lines.js';
const entry=(tracking,state='matched')=>({tracking,state,matches:state==='matched'?[{sheet:'18/11 NỮ',row:7}]:[]});
test('a mixed batch colors each original order line independently without duplicate text',()=>{const body='QL89 XS RỘNG 842625232732\nE1 S CHẬT 842625232733';const lines=returnMessageLines({body,entries:[entry('842625232732'),entry('842625232733','missing')]});assert.deepEqual(lines.map(l=>l.state),['matched','unresolved']);assert.equal(lines.map(l=>l.text).join('\n'),body);});
test('one order split across lines shares its result and preserves the user text',()=>{const lines=returnMessageLines({body:'842625232732\nQL89 XS\nRỘNG',entries:[entry('842625232732')]});assert(lines.every(l=>l.state==='matched'));assert.equal(lines[1].text,'QL89 XS');});
test('incomplete, ambiguous and unmatched entries never look updated',()=>{for(const entries of [[],[entry('842625232732','incomplete')],[{...entry('842625232732'),matches:[]}]])assert.equal(returnMessageLines({body:'QL89 XS RỘNG 842625232732',entries})[0].state,'unresolved');});

test('pending results stay neutral and manual approval stays distinct from an automatic match',()=>{
 const body='E1 S CHẬT 842625232733\nE2 M RỘNG 842625232734';
 assert.deepEqual(returnMessageLines({body,processing:'pending'}).map(x=>x.state),['pending','pending']);
 const result=returnMessageLines({body,entries:[entry('842625232733','missing')],manual_approvals:{0:{actor:'manager',at:'2026-11-24T00:00:00Z'}}});
 assert.deepEqual(result.map(x=>x.state),['manual','unresolved']);assert.match(result[0].title,/manager/);
});
