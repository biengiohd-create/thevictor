<?php
if(PHP_SAPI!=='cli')exit;require __DIR__.'/stock-flow.php';
function report_fold(string $v):string {return strtoupper(strtr($v,['Ậ'=>'A','Đ'=>'D','Ã'=>'A','À'=>'A','Ơ'=>'O','Ấ'=>'A','Ô'=>'O','Ư'=>'U']));}
function check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$order=['created'=>true,'sheetId'=>'s','sheet'=>'23/11 NAM','row'=>2,'tracking'=>'842600000001','text'=>'E1 S × 1 + E2 M × 2','status'=>'','items'=>[['sku'=>'E1','size'=>'S','qty'=>1],['sku'=>'E2','size'=>'M','qty'=>2]],'incoming'=>[]];
$a=stock_return_items($order,['raw'=>'E1 S + E2 M OK E1, E2 M × 1 842600000001'],null);check($a['error']!== '','ambiguous repeated quantity requires review');
$a=stock_return_items($order,['raw'=>'842600000001 E2 M × 1 RỘNG BỤNG'],null);check($a['items']===['E2|M'=>1],'partial return keeps exact SKU size and quantity');
$a=stock_return_items($order,null,null);check($a['error']!== '','file tracking alone does not return every product of a mixed order');
$a=stock_return_items($order,null,['E2|M'=>1]);check($a['error']===''&&$a['items']['E2|M']===1,'manual confirmation resolves partial receipt');
check(stock_return_items($order,null,['E2|M'=>3])['error']!=='','return quantity cannot exceed shipment');
$single=$order;$single['items']=[['sku'=>'E1','size'=>'S','qty'=>1]];check(stock_return_items($single,null,null)['items']===['E1|S'=>1],'single-item receipt can be matched automatically');
$pair=$order;$pair['items']=[['sku'=>'E1','size'=>'S','qty'=>1],['sku'=>'E2','size'=>'M','qty'=>1]];check(stock_return_items($pair,['raw'=>'E1 S + E2 M OK E1, E2 RỘNG'],null)['items']===['E2|M'=>1],'customer kept product is excluded');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec('CREATE TABLE return_messages(id INTEGER PRIMARY KEY,entries TEXT);CREATE TABLE return_receipts(tracking TEXT,state TEXT)');$entry=['state'=>'matched','tracking'=>'842600000001','raw'=>'E2 M × 1 RỘNG BỤNG 842600000001','reason'=>'RỘNG BỤNG'];$db->prepare('INSERT INTO return_messages VALUES(1,?)')->execute([json_encode([$entry])]);$f=stock_flow($db,[$order]);check($f['totals']['E2|M']['returning']===1&&$f['totals']['E2|M']['autoReturned']===0,'chat return stays outside available stock');$db->exec("INSERT INTO return_receipts VALUES('842600000001','matched'),('842600000001','matched')");$f=stock_flow($db,[$order,$order]);check($f['totals']['E2|M']['autoReturned']===1&&$f['totals']['E2|M']['returning']===0&&$f['totals']['E2|M']['sold']===2,'file receipt adds only actual returned quantity once, duplicate files/rows do not double count');
echo "STOCK FLOW FIXTURES PASSED\n";
