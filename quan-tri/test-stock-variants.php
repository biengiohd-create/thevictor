<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/stock-variants.php';
function stock_item_map(array $items):array {$r=[];foreach($items as $i)$r[$i['sku'].'|'.$i['size']]=($r[$i['sku'].'|'.$i['size']]??0)+$i['qty'];return $r;}
function ck($v,$label){if(!$v)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);$db->exec("CREATE TABLE stock_movements(id INTEGER PRIMARY KEY,sku TEXT,size TEXT,quantity INTEGER);CREATE TABLE stock_batches(id INTEGER PRIMARY KEY)");stock_variant_schema($db);
ck(stock_variant_projection($db,[],['outcomes'=>[]])['balances']===[],'unconfigured products unchanged');
$db->exec("INSERT INTO stock_variants VALUES('E1','Xấu',1);INSERT INTO stock_movements(sku,size,quantity,variant) VALUES('E1','L',1,''),('E1','L',5,'Xấu'),('E1','M',8,'')");
$flow=['outcomes'=>[['identity'=>'one','row'=>['items'=>[['sku'=>'E1','size'=>'L','qty'=>3]]],'result'=>['received'=>[]]]]];
function vb($r,$size,$type){foreach($r['balances'] as $b)if($b['size']===$size&&$b['variant']===$type)return $b['balance'];return 0;}
$r=stock_variant_projection($db,[],$flow);ck(vb($r,'L','')===0&&vb($r,'L','Xấu')===3,'split dispatch normal then secondary same size');ck(vb($r,'M','')===8,'other sizes untouched');
$a=json_decode($db->query('SELECT allocation FROM stock_variant_dispatch')->fetchColumn(),true);ck($a===[''=>1,'Xấu'=>2],'dispatch allocation persisted');
$db->exec("INSERT INTO stock_movements(sku,size,quantity,variant) VALUES('E1','L',5,'')");$r=stock_variant_projection($db,[],$flow);ck(vb($r,'L','')===5&&vb($r,'L','Xấu')===3,'restock does not reassign old dispatch');
$r2=stock_variant_projection($db,[],$flow);ck($r===$r2,'refresh idempotent');
$flow['outcomes'][0]['result']['received']=['E1|L'=>1];$r=stock_variant_projection($db,[],$flow);ck(vb($r,'L','__return_review')===1&&count($r['warnings'])===1,'mixed type return held for classification');
$flow['outcomes'][]=['identity'=>'two','row'=>['items'=>[['sku'=>'E1','size'=>'L','qty'=>6]]],'result'=>['received'=>[]]];$r=stock_variant_projection($db,[],$flow);ck(vb($r,'L','')===0&&vb($r,'L','Xấu')===2,'new order uses normal restock first');
$flow['outcomes'][0]['row']['items'][0]['qty']=1;$flow['outcomes'][0]['result']['received']=[];$r=stock_variant_projection($db,[],$flow);ck(vb($r,'L','Xấu')===4,'quantity correction releases original type');
$flow=['outcomes'=>[]];$r=stock_variant_projection($db,[],$flow);ck(vb($r,'L','')===6&&vb($r,'L','Xấu')===5,'removed dispatch releases saved allocations once');
$db->beginTransaction();stock_variant_projection($db,[],$flow);ck($db->inTransaction(),'caller transaction preserved');$db->rollBack();
echo "STOCK_VARIANTS_TESTS_COMPLETE\n";
