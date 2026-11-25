<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/lib.php';require_once __DIR__.'/stock-matrix.php';
// CLI-only maintenance. The manifest stays in the private directory, never in source control.
$path=$argv[1]??'';$apply=in_array('--apply',$argv,true);if(!is_file($path))throw new RuntimeException('Private manifest required');
$manifest=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$db=admin_db();
stock_matrix_schema($db);stock_variant_schema($db);stock_checkpoint_schema($db);
if($db->query('SELECT COUNT(*) FROM stock_checkpoint')->fetchColumn())throw new RuntimeException('Checkpoint already exists; no changes applied');
$catalog=board_catalog($db);$board=board_rows($db,$catalog);$flow=stock_flow($db,$board['rows']);$orders=[];$corrections=[];$pieces=0;
foreach($manifest['orders'] as $o){$id=stock_tracking($o['tracking']);if(!$id||isset($orders[$id]))throw new RuntimeException('Duplicate/empty tracking');$text=$o['text'];$confirmed=$manifest['confirmedCorrections'][$id]??null;if($confirmed){if(($confirmed['before']??null)!==$text||!is_string($confirmed['after']??null))throw new RuntimeException('Confirmed correction does not match manifest');$text=$confirmed['after'];$corrections[]=['tracking'=>$id,'before'=>$o['text'],'after'=>$text];}$parsed=board_lines($text,$catalog);if($parsed['errors']||!$parsed['items'])throw new RuntimeException('Unresolved manifest product: '.$id.' '.$text.' '.json_encode($parsed['errors'],JSON_UNESCAPED_UNICODE));$orders[$id]=stock_item_map($parsed['items']);$pieces+=array_sum($orders[$id]);}
if(!$orders||(isset($manifest['expectedOrders'])&&count($orders)!==(int)$manifest['expectedOrders']))throw new RuntimeException('Manifest order count differs from confirmed input');
$batches=$db->query("SELECT * FROM stock_batches WHERE substr(created_at,1,10)='2026-11-25' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);$cells=[];foreach($batches as $b)foreach(json_decode($b['quantities'],true) as $size=>$qty)$cells[$b['sku'].'|'.$size.'|'.$b['variant']]=[$b['sku'],$size,$b['variant'],(int)$qty,(int)$b['id']];
if(!isset($cells['E1|M|']))throw new RuntimeException('E1 normal M baseline missing');
echo json_encode(['batches'=>count($batches),'cells'=>count($cells),'orders'=>count($orders),'pieces'=>$pieces,'corrections'=>$corrections,'apply'=>$apply],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
if(!$apply)exit;
$db->exec('BEGIN IMMEDIATE');try{
 $db->prepare('INSERT INTO stock_checkpoint VALUES(1,?,?,?)')->execute(['Kiểm tồn tối 24/11/2026, trước xuất hàng',(int)$db->query('SELECT COALESCE(MAX(id),0) FROM stock_movements')->fetchColumn(),gmdate('c')]);
 $save=$db->prepare('INSERT INTO stock_checkpoint_cells VALUES(?,?,?,?,?)');foreach($cells as $c)$save->execute($c);
 $save=$db->prepare('INSERT INTO stock_checkpoint_batches VALUES(?,?)');foreach($batches as $b){$save->execute([$b['id'],json_encode($b,JSON_UNESCAPED_UNICODE)]);$db->prepare("UPDATE stock_batches SET kind='stocktake',note=?,revision=revision+1 WHERE id=?")->execute([trim($b['note'].' · Số kiểm tồn tối 24/11 trước xuất hàng'),$b['id']]);}
 $seen=[];$save=$db->prepare('INSERT INTO stock_checkpoint_orders VALUES(?,?,?,?,?)');foreach($flow['outcomes'] as $o){$id=$o['identity'];$seen[$id]=true;$save->execute([$id,json_encode($orders[$id]??new stdClass()),json_encode($o['result']['received']),'pre-checkpoint',gmdate('c')]);}
 // Invalid old rows must not become new physical shipments merely because a spelling is repaired.
 foreach($board['rows'] as $r)if($r['created']){$id=stock_tracking($r['tracking'])?:$r['sheetId'].'|'.$r['row'];if(!isset($seen[$id])){$seen[$id]=true;$save->execute([$id,'{}','{}','pre-checkpoint',gmdate('c')]);}}
 $up=$db->prepare("INSERT INTO stock_checkpoint_orders VALUES(?,?,'{}','manifest',?) ON CONFLICT(identity) DO UPDATE SET items=excluded.items,initial_returns='{}',source='manifest'");foreach($orders as $id=>$items)$up->execute([(string)$id,json_encode($items),gmdate('c')]);
 $db->exec('COMMIT');echo "CHECKPOINT_IMPORTED\n";
}catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
