<?php
declare(strict_types=1);
// The default type keeps all pre-existing stock and shipments. Types are optional per SKU.
function stock_variant_schema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS stock_variants(sku TEXT NOT NULL,label TEXT NOT NULL,priority INTEGER NOT NULL,PRIMARY KEY(sku,label));CREATE TABLE IF NOT EXISTS stock_variant_dispatch(identity TEXT NOT NULL,pair TEXT NOT NULL,allocation TEXT NOT NULL,updated_at TEXT NOT NULL,PRIMARY KEY(identity,pair))");
 foreach(['stock_movements','stock_batches'] as $table){$exists=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'")->fetchColumn();if($exists&&!in_array('variant',array_column($db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC),'name'),true))$db->exec("ALTER TABLE $table ADD COLUMN variant TEXT NOT NULL DEFAULT ''");}
}
function stock_variant_projection(PDO $db,array $balances,array $flow):array {
 stock_variant_schema($db);$types=[];foreach($db->query('SELECT * FROM stock_variants ORDER BY priority,label') as $v)$types[$v['sku']][]=$v;
 if(!$types)return ['balances'=>[],'types'=>[],'warnings'=>[]];
 $own=!$db->inTransaction();if($own)$db->exec('BEGIN IMMEDIATE');try{
 $pool=[];$ensure=static function($sku,$size,$variant)use(&$pool){$k=$sku.'|'.$size.'|'.$variant;$pool[$k]??=['sku'=>$sku,'size'=>$size,'variant'=>$variant,'balance'=>0,'sold'=>0,'returned'=>0];return $k;};
 foreach($db->query('SELECT sku,size,variant,SUM(quantity) qty FROM stock_movements GROUP BY sku,size,variant') as $m)if(isset($types[$m['sku']])){$k=$ensure($m['sku'],$m['size'],$m['variant']);$pool[$k]['balance']+=(int)$m['qty'];}
 $existing=[];foreach($db->query('SELECT * FROM stock_variant_dispatch') as $a)$existing[$a['identity']][$a['pair']]=json_decode($a['allocation'],true);
 $current=[];$returns=[];foreach($flow['outcomes'] as $o){foreach(stock_item_map($o['row']['items']) as $pair=>$qty){[$sku,$size]=explode('|',$pair,2);if(isset($types[$sku]))$current[$o['identity']][$pair]=$qty;}foreach($o['result']['received'] as $pair=>$qty)$returns[$o['identity']][$pair]=$qty;}
 // Reserve saved allocations first, so refreshing or a later restock cannot move earlier dispatches.
 $alloc=[];foreach($existing as $id=>$pairs)foreach($pairs as $pair=>$a){[$sku,$size]=explode('|',$pair,2);if(!isset($types[$sku]))continue;$left=$current[$id][$pair]??0;foreach($a as $v=>$qty){$take=min($left,(int)$qty);if($take>0){$alloc[$id][$pair][$v]=$take;$pool[$ensure($sku,$size,$v)]['balance']-=$take;$left-=$take;}}}
 $preReturns=[];foreach($alloc as $id=>$pairs)foreach($pairs as $pair=>$a){$ret=min(array_sum($a),(int)($returns[$id][$pair]??0));if(!$ret)continue;[$sku,$size]=explode('|',$pair,2);$v=count($a)===1?(string)array_key_first($a):'__return_review';$k=$ensure($sku,$size,$v);$pool[$k]['balance']+=$ret;$pool[$k]['returned']+=$ret;$preReturns[$id][$pair]=true;}
 foreach($current as $id=>$pairs)foreach($pairs as $pair=>$qty){[$sku,$size]=explode('|',$pair,2);$need=$qty-array_sum($alloc[$id][$pair]??[]);foreach(array_merge([''],array_column($types[$sku],'label')) as $v){$k=$ensure($sku,$size,$v);$take=min($need,max(0,$pool[$k]['balance']));if($take){$alloc[$id][$pair][$v]=($alloc[$id][$pair][$v]??0)+$take;$pool[$k]['balance']-=$take;$need-=$take;}}if($need>0){$alloc[$id][$pair]['']=($alloc[$id][$pair]['']??0)+$need;$pool[$ensure($sku,$size,'')]['balance']-=$need;}}
 $save=$db->prepare('INSERT INTO stock_variant_dispatch VALUES(?,?,?,?) ON CONFLICT(identity,pair) DO UPDATE SET allocation=excluded.allocation,updated_at=excluded.updated_at WHERE allocation<>excluded.allocation');$warnings=[];
 foreach($existing as $id=>$pairs)foreach($pairs as $pair=>$a)if(!isset($current[$id][$pair]))$db->prepare('DELETE FROM stock_variant_dispatch WHERE identity=? AND pair=?')->execute([$id,$pair]);
 foreach($alloc as $id=>$pairs)foreach($pairs as $pair=>$a){[$sku,$size]=explode('|',$pair,2);$save->execute([$id,$pair,json_encode($a,JSON_UNESCAPED_UNICODE),gmdate('c')]);foreach($a as $v=>$qty)$pool[$ensure($sku,$size,$v)]['sold']+=$qty;$ret=min(array_sum($a),(int)($returns[$id][$pair]??0));if($ret){if(count($a)===1){$v=(string)array_key_first($a);}else{$v='__return_review';$warnings[]='Vận đơn '.$id.' · '.$sku.' '.$size.': hàng hoàn từ nhiều loại, cần kiểm kê xác nhận loại thực nhận.';}$k=$ensure($sku,$size,$v);if(empty($preReturns[$id][$pair])){$pool[$k]['balance']+=$ret;$pool[$k]['returned']+=$ret;}}}
 foreach($types as $sku=>$vs)foreach($balances as $b)if($b['sku']===$sku)foreach(array_merge([''],array_column($vs,'label')) as $v)$ensure($sku,$b['size'],$v);
 if($own)$db->exec('COMMIT');return ['balances'=>array_values($pool),'types'=>$types,'warnings'=>array_values(array_unique($warnings))];
 }catch(Throwable $e){if($own&&$db->inTransaction())$db->exec('ROLLBACK');throw $e;}
}
function stock_variant_save(PDO $db,array $input,array $user):array {
 team_require_owner();stock_matrix_schema($db);stock_variant_schema($db);$sku=strtoupper(trim((string)($input['sku']??'')));$label=trim((string)($input['label']??''));$priority=filter_var($input['priority']??null,FILTER_VALIDATE_INT);$catalog=board_catalog($db);
 if(!isset($catalog[$sku])||$label===''||mb_strlen($label)>60||str_contains($label,'|')||str_starts_with($label,'__')||$priority===false||$priority<1||$priority>999)throw new RuntimeException('Chọn mã có sẵn, tên phân loại và thứ tự từ 1 đến 999.',400);
 $db->exec('BEGIN IMMEDIATE');try{
 // Capture current shipments in the default type before enabling the first optional type.
 $q=$db->prepare('SELECT COUNT(*) FROM stock_variants WHERE sku=?');$q->execute([$sku]);if(!(int)$q->fetchColumn()){$board=board_rows($db,$catalog);$flow=stock_flow($db,$board['rows']);$save=$db->prepare('INSERT OR IGNORE INTO stock_variant_dispatch VALUES(?,?,?,?)');foreach($flow['outcomes'] as $o)foreach(stock_item_map($o['row']['items']) as $pair=>$qty)if(explode('|',$pair,2)[0]===$sku)$save->execute([$o['identity'],$pair,json_encode([''=>$qty]),gmdate('c')]);}
 $db->prepare('INSERT INTO stock_variants VALUES(?,?,?) ON CONFLICT(sku,label) DO UPDATE SET priority=excluded.priority')->execute([$sku,$label,$priority]);people_audit('stock_variant',$sku,['label'=>$label,'priority'=>$priority],$db);$db->exec('COMMIT');return ['ok'=>true];
 }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
