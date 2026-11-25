<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/stock-checkpoint.php';
function stock_item_map(array $items):array {$r=[];foreach($items as $i)$r[$i['sku'].'|'.$i['size']]=($r[$i['sku'].'|'.$i['size']]??0)+$i['qty'];return $r;}
function ck($v,$label){if(!$v)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec("CREATE TABLE stock_variants(sku TEXT,label TEXT,priority INTEGER);CREATE TABLE stock_movements(id INTEGER PRIMARY KEY,sku TEXT,size TEXT,variant TEXT,quantity INTEGER);INSERT INTO stock_variants VALUES('E1','Xấu',1);INSERT INTO stock_movements VALUES(1,'E1','M','',999)");stock_checkpoint_schema($db);$db->exec("INSERT INTO stock_checkpoint VALUES(1,'Fixture',1,'now');INSERT INTO stock_checkpoint_cells VALUES('E1','M','',3,1);INSERT INTO stock_checkpoint_cells VALUES('E1','M','Xấu',5,2);INSERT INTO stock_checkpoint_orders VALUES('old','{}','{}','old','0');INSERT INTO stock_checkpoint_orders VALUES('file','{\"E1|M\":4}','{}','manifest','1')");
$o=static fn($id,$qty,$returned=0)=>['identity'=>$id,'row'=>['items'=>[['sku'=>'E1','size'=>'M','qty'=>$qty]]],'result'=>['received'=>$returned?['E1|M'=>$returned]:[]]];
$flow=['outcomes'=>[$o('old',900),$o('file',4)]];
$get=static function($flow)use($db){$r=stock_checkpoint_projection($db,$flow);$b=[];foreach($r['balances'] as $c)$b[$c['variant']]=$c['balance'];return $b;};
ck($get($flow)===[''=>0,'Xấu'=>4],'count replaces old movements and excludes historic orders; normal then optional type');
ck($get($flow)===[''=>0,'Xấu'=>4],'refresh does not deduct manifest again');
$flow['outcomes'][0]=$o('old',1900);ck($get($flow)['Xấu']===4,'old sheet edits do not change physical count');
$db->exec("INSERT INTO stock_movements VALUES(2,'E1','M','',7)");ck($get($flow)===[''=>7,'Xấu'=>4],'restock does not move already dispatched type');
$flow['outcomes'][]=$o('new',2);ck($get($flow)===[''=>5,'Xấu'=>4],'new dispatch deducts once');ck($get($flow)['']===5,'new dispatch deduplicated');
$flow['outcomes']=[];ck($get($flow)['']===5,'source deletion does not undo physical dispatch');
$flow['outcomes']=[$o('old',900,1)];ck($get($flow)['']===6,'post-count return from historical shipment credits once');$flow['outcomes']=[];ck($get($flow)['']===6,'credited return survives source deletion');
$db->exec("INSERT INTO stock_movements VALUES(3,'E1','M','',4)");ck($get($flow)['']===10,'later absolute-stocktake delta applies');
echo "STOCK_CHECKPOINT_TESTS_COMPLETE\n";
