<?php
declare(strict_types=1);
// A physical count is a new inventory origin, independent of earlier order history.
function stock_checkpoint_schema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS stock_checkpoint(id INTEGER PRIMARY KEY CHECK(id=1),label TEXT NOT NULL,movement_id INTEGER NOT NULL,created_at TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS stock_checkpoint_cells(sku TEXT NOT NULL,size TEXT NOT NULL,variant TEXT NOT NULL,quantity INTEGER NOT NULL,batch_id INTEGER NOT NULL,PRIMARY KEY(sku,size,variant));
 CREATE TABLE IF NOT EXISTS stock_checkpoint_orders(identity TEXT PRIMARY KEY,items TEXT NOT NULL,initial_returns TEXT NOT NULL,source TEXT NOT NULL,created_at TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS stock_checkpoint_allocations(identity TEXT NOT NULL,pair TEXT NOT NULL,allocation TEXT NOT NULL,PRIMARY KEY(identity,pair));
 CREATE TABLE IF NOT EXISTS stock_checkpoint_batches(batch_id INTEGER PRIMARY KEY,original TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS stock_checkpoint_returns(identity TEXT NOT NULL,pair TEXT NOT NULL,quantity INTEGER NOT NULL,PRIMARY KEY(identity,pair))");
}
function stock_checkpoint_projection(PDO $db,array $flow):?array {
 stock_checkpoint_schema($db);$checkpoint=$db->query('SELECT * FROM stock_checkpoint WHERE id=1')->fetch(PDO::FETCH_ASSOC);if(!$checkpoint)return null;
 $db->exec('SAVEPOINT physical_stock');try{
 $types=[];foreach($db->query('SELECT * FROM stock_variants ORDER BY priority,label') as $v)$types[$v['sku']][]=$v;
 $pool=[];$known=[];$warnings=[];
 $ensure=static function($sku,$size,$variant)use(&$pool){$k=$sku.'|'.$size.'|'.$variant;$pool[$k]??=['sku'=>$sku,'size'=>$size,'variant'=>$variant,'balance'=>0];return $k;};
 foreach($db->query('SELECT * FROM stock_checkpoint_cells') as $c){$k=$ensure($c['sku'],$c['size'],$c['variant']);$pool[$k]['balance']=(int)$c['quantity'];$known[$k]=true;}
 $q=$db->prepare('SELECT sku,size,variant,SUM(quantity) qty FROM stock_movements WHERE id>? GROUP BY sku,size,variant');$q->execute([$checkpoint['movement_id']]);foreach($q as $m){$k=$ensure($m['sku'],$m['size'],$m['variant']);$pool[$k]['balance']+=(int)$m['qty'];}
 $current=[];foreach($flow['outcomes'] as $o)$current[$o['identity']]=$o;
 $orders=[];foreach($db->query('SELECT * FROM stock_checkpoint_orders ORDER BY created_at,identity') as $o)$orders[$o['identity']]=$o;
 $insert=$db->prepare('INSERT INTO stock_checkpoint_orders VALUES(?,?,?,?,?)');
 foreach($current as $id=>$o)if(!isset($orders[$id])){$items=stock_item_map($o['row']['items']);$orders[$id]=['identity'=>$id,'items'=>json_encode($items),'initial_returns'=>'{}','source'=>'board','created_at'=>gmdate('c')];$insert->execute(array_values($orders[$id]));}
 $saved=[];foreach($db->query('SELECT * FROM stock_checkpoint_allocations') as $a)$saved[$a['identity']][$a['pair']]=json_decode($a['allocation'],true);
 // Preserve the type actually dispatched, even after restock, refresh or source deletion.
 $alloc=[];foreach($orders as $id=>$o)foreach(json_decode($o['items'],true) as $pair=>$qty){[$sku,$size]=explode('|',$pair,2);if(isset($saved[$id][$pair])){$alloc[$id][$pair]=$saved[$id][$pair];foreach($alloc[$id][$pair] as $v=>$n)$pool[$ensure($sku,$size,(string)$v)]['balance']-=$n;}}
 $save=$db->prepare('INSERT INTO stock_checkpoint_allocations VALUES(?,?,?)');
 foreach($orders as $id=>$o)foreach(json_decode($o['items'],true) as $pair=>$qty){if(isset($alloc[$id][$pair]))continue;[$sku,$size]=explode('|',$pair,2);$need=$qty;$a=[];foreach(array_merge([''],array_column($types[$sku]??[],'label')) as $v){$k=$ensure($sku,$size,$v);$take=min($need,max(0,$pool[$k]['balance']));if($take){$a[$v]=$take;$pool[$k]['balance']-=$take;$need-=$take;}}if($need){$a['']=($a['']??0)+$need;$pool[$ensure($sku,$size,'')]['balance']-=$need;}$alloc[$id][$pair]=$a;$save->execute([$id,$pair,json_encode($a,JSON_UNESCAPED_UNICODE)]);}
 $observed=[];foreach($db->query('SELECT * FROM stock_checkpoint_returns') as $r)$observed[$r['identity']][$r['pair']]=(int)$r['quantity'];$saveReturn=$db->prepare('INSERT INTO stock_checkpoint_returns VALUES(?,?,?) ON CONFLICT(identity,pair) DO UPDATE SET quantity=MAX(quantity,excluded.quantity)');
 foreach($orders as $id=>$o){$initial=json_decode($o['initial_returns'],true);$returns=$observed[$id]??[];foreach($current[$id]['result']['received']??[] as $pair=>$qty){$returns[$pair]=max($returns[$pair]??0,$qty);$saveReturn->execute([(string)$id,$pair,$returns[$pair]]);}foreach($returns as $pair=>$qty){$delta=max(0,$qty-($initial[$pair]??0));if(!$delta)continue;[$sku,$size]=explode('|',$pair,2);$a=$alloc[$id][$pair]??[];$v=count($a)===1?(string)array_key_first($a):(empty($a)?'':'__return_review');if(count($a)>1)$warnings[]='Vận đơn '.$id.' · '.$sku.' '.$size.': hoàn từ nhiều loại hàng, cần xác nhận phân loại.';$pool[$ensure($sku,$size,$v)]['balance']+=$delta;}}
 $hasBatches=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='stock_batches'")->fetchColumn();if($hasBatches)foreach($db->query("SELECT sku,variant,quantities FROM stock_batches WHERE kind='stocktake' AND id NOT IN(SELECT batch_id FROM stock_checkpoint_batches)") as $batch)foreach(json_decode($batch['quantities'],true) as $size=>$qty)$known[$batch['sku'].'|'.$size.'|'.$batch['variant']]=true;foreach($pool as $k=>&$b)$b['counted']=isset($known[$k]);unset($b);
 $db->exec('RELEASE SAVEPOINT physical_stock');return ['label'=>$checkpoint['label'],'balances'=>array_values($pool),'warnings'=>array_values(array_unique($warnings)),'orders'=>count(array_filter($orders,static fn($o)=>(bool)json_decode($o['items'],true)))];
 }catch(Throwable $e){$db->exec('ROLLBACK TO SAVEPOINT physical_stock');$db->exec('RELEASE SAVEPOINT physical_stock');throw $e;}
}
