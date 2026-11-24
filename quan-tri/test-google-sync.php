<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit;require __DIR__.'/google-sync-lib.php';
function gscheck(bool $v,string $m):void {if(!$v)throw new RuntimeException('FAIL '.$m);echo 'PASS '.$m.PHP_EOL;}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);wb_schema($db);
$old=wb_empty();$db->prepare('INSERT INTO staff_workbook VALUES(1,?,"[]",7,"old","now")')->execute([json_encode($old)]);
foreach(['cancel_messages','return_messages','return_links','deleted_orders'] as $t){$db->exec('CREATE TABLE '.$t.'(id TEXT)');$db->exec('INSERT INTO '.$t.' VALUES("test")');}
$db->exec('CREATE TABLE marketing_private(value INTEGER);INSERT INTO marketing_private VALUES(42)');$backups=0;$backup=function()use(&$backups){$backups++;return 'fixture-backup';};
$m=[['id'=>'google-10','name'=>'24/11 NAM'],['id'=>'google-20','name'=>'24/11 NỮ','hidden'=>true]];
$headers=['','TIỀN','TÊN KHÁCH','SDT','ĐỊA CHỈ','MÃ ĐƠN HÀNG','NOTE','CHECK SIZE','TẠO ĐƠN','CAO/NẶNG','TÌNH TRẠNG','MÃ ĐƠN HÀNG','hoàn'];
$a=['id'=>'google-10','stamp'=>100,'values'=>[$headers,['',529000,'KH GIẢ','0320000000','Địa chỉ giả','E1 L','','','C20','','OK','001234']]];
$b=['id'=>'google-20','stamp'=>100,'values'=>[$headers]];
$r=gsync_receive($db,['stamp'=>100,'manifest'=>$m,'sheets'=>[$a]],$backup);gscheck($r['pending']===1&&wb_current($db)['version']===7&&$backups===0,'incomplete upload never replaces board or clears chats');
$r=gsync_receive($db,['stamp'=>100,'manifest'=>$m,'sheets'=>[$b]],$backup);$s=wb_current($db)['snapshot'];gscheck($r['active']&&$backups===1&&$r['version']===8,'complete source replaces once after backup');
gscheck(count($s['sheets'])===2&&!isset($s['sheets']['orders'])&&$s['sheets']['google-20']['hidden']===1,'remove old sheets and preserve hidden source');
$cells=$s['sheets']['google-10']['cellData'];gscheck($cells[0][5]['v']==='Sản phẩm / size'&&$cells[0][11]['v']==='Mã vận đơn'&&$cells[1][3]['v']==='0320000000'&&$cells[1][11]['v']==='001234'&&!isset($cells[1][0]),'map duplicate headers and preserve leading zeros without fabricated timestamps');
foreach(['cancel_messages','return_messages','return_links','deleted_orders'] as $t)gscheck((int)$db->query('SELECT count(*) FROM '.$t)->fetchColumn()===0,'clear test '.$t);
gscheck((int)$db->query('SELECT value FROM marketing_private')->fetchColumn()===42,'marketing data remains intact');
$r=gsync_receive($db,['stamp'=>100,'manifest'=>$m,'sheets'=>[$a,$b]],$backup);gscheck(!$r['changed']&&$r['version']===8&&$backups===1,'retries are idempotent');
$new=$a;$new['stamp']=200;$new['values'][1][1]=639000;$r=gsync_receive($db,['stamp'=>200,'manifest'=>$m,'sheets'=>[$new]],$backup);
gsync_receive($db,['stamp'=>100,'manifest'=>$m,'sheets'=>[$a]],$backup);gscheck(wb_current($db)['snapshot']['sheets']['google-10']['cellData'][1][1]['v']===639000,'late packet cannot overwrite newer cells');
$m[0]['name']='25/11 NAM';array_pop($m);$r=gsync_receive($db,['stamp'=>300,'manifest'=>$m,'sheets'=>[]],$backup);gscheck(count(wb_current($db)['snapshot']['sheets'])===1&&wb_current($db)['snapshot']['sheets']['google-10']['name']==='25/11 NAM','rename and delete source sheet without duplicating ID');
$before=wb_current($db);try{gsync_receive($db,['stamp'=>400,'manifest'=>$m,'sheets'=>[['id'=>'google-10','stamp'=>400,'values'=>[[['bad']]]]]],$backup);throw new LogicException('invalid accepted');}catch(RuntimeException $e){gscheck(wb_current($db)===$before,'invalid packet preserves last good board');}
echo "GOOGLE_SYNC_TESTS_PASSED\n";

$legacy=gsync_sheet(['id'=>'google-30','values'=>[['','TIỀN','TÊN KHÁCH','SDT','ĐỊA CHỈ','MÃ ĐƠN HÀNG','NOTE','CHECK SIZE','TẠO ĐƠN','TÌNH TRẠNG','MÃ ĐƠN HÀNG','hoàn']]]);gscheck($legacy['cellData'][0][10]['v']==='Mã vận đơn'&&$legacy['cellData'][0][11]['v']==='hoàn','historical tracking position is detected by header, not fixed column');

$wrong=gsync_sheet(['id'=>'google-40','values'=>[['','TIỀN','TÊN KHÁCH','SDT','ĐỊA CHỈ','NHÂN VIÊN GHI SAI TÊN','','','','','','SỐ GỬI'],[1,529000,'A','0900000000','Địa chỉ','E1 S','','','','','','842625221094'],[2,529000,'B','0900000001','Địa chỉ','E4 M','','','','','','842625221095'],[4,529000,'C','0900000002','Địa chỉ','E2 L','','','','','','842625221096']]]);
gscheck($wrong['cellData'][0][5]['v']==='Sản phẩm / size'&&$wrong['cellData'][0][11]['v']==='Mã vận đơn','source order layout and tracking content override mistyped header labels');gscheck($wrong['cellData'][0][0]['v']==='STT'&&$wrong['cellData'][3][0]['v']===4,'source serial numbers are not mislabeled as creation times');gscheck($wrong['cellData'][1][5]['v']==='E1 S'&&$wrong['cellData'][1][11]['v']==='842625221094','header normalization never changes products or tracking values');
echo "SOURCE_HEADER_TESTS_PASSED\n";
