<?php
declare(strict_types=1);
require_once __DIR__.'/workbook-lib.php';
require_once __DIR__.'/inbox-daily.php';
function inbox_board_schema(PDO $db): void {
 wb_schema($db);$db->exec("CREATE TABLE IF NOT EXISTS order_sheet(code TEXT PRIMARY KEY,check_size TEXT NOT NULL DEFAULT '',shipping_created TEXT NOT NULL DEFAULT '',measurements TEXT NOT NULL DEFAULT '',tracking TEXT NOT NULL DEFAULT '',returned TEXT NOT NULL DEFAULT '')");$db->exec('CREATE TABLE IF NOT EXISTS order_board_transfers(code TEXT PRIMARY KEY, sheet_id TEXT, row_number INTEGER, workbook_version INTEGER NOT NULL, actor TEXT NOT NULL, transferred_at TEXT NOT NULL)');
 if(!in_array('targets',array_column($db->query('PRAGMA table_info(order_board_transfers)')->fetchAll(),'name'),true))$db->exec("ALTER TABLE order_board_transfers ADD COLUMN targets TEXT NOT NULL DEFAULT '[]'");$db->exec('CREATE TABLE IF NOT EXISTS inbox_board_migrations(id INTEGER PRIMARY KEY)');if($db->query('SELECT 1 FROM inbox_board_migrations WHERE id=1')->fetchColumn())return;
 $current=wb_current($db);if($current){$q=$db->prepare('INSERT OR IGNORE INTO order_board_transfers(code,sheet_id,row_number,workbook_version,actor,transferred_at) VALUES(?,NULL,NULL,?,?,?)');foreach($current['sources'] as $code)$q->execute([$code,$current['version'],$current['actor'],$current['updatedAt']]);}$db->exec('INSERT OR IGNORE INTO inbox_board_migrations VALUES(1)');
}
function inbox_enrich(PDO $db,array $orders): array {inbox_board_schema($db);$q=$db->prepare('SELECT 1 FROM order_board_transfers WHERE code=?');foreach($orders as &$o){$q->execute([$o['code']]);$o['onBoard']=(bool)$q->fetchColumn();}return $orders;}
function inbox_status(PDO $db,array $input,string $actor): array {
 if(array_diff(array_keys($input),['action','csrf','code','version','status']))throw new RuntimeException('Chỉ được cập nhật trạng thái tại khu nhận đơn.',403);
 $code=$input['code']??'';$status=$input['status']??'';if(!is_string($code)||!preg_match('/^TV-[A-F0-9]{12}$/D',$code)||!is_string($status)||!isset(ADMIN_STATUSES[$status]))throw new RuntimeException('Mã đơn hoặc trạng thái không hợp lệ.',400);
 $q=$db->prepare('SELECT note FROM order_admin_meta WHERE code=?');$q->execute([$code]);$note=$q->fetchColumn();return admin_update($db,['code'=>$code,'status'=>$status,'version'=>$input['version']??null,'internalNote'=>$note===false?'':$note],$actor);
}
function inbox_target(array $snapshot,string $sheetId='orders'): array {
 if(!isset($snapshot['sheets'][$sheetId])){if($sheetId!=='orders')throw new RuntimeException('Không tìm thấy sheet nhận đơn.',400);$base=wb_empty();$snapshot['sheets']['orders']=$base['sheets']['orders'];$snapshot['sheetOrder'][]='orders';$snapshot['styles']=array_merge($base['styles'],$snapshot['styles']??[]);}
 $sheet=&$snapshot['sheets'][$sheetId];$cells=$sheet['cellData']??[];$headers=$cells[0]??[];$map=[];$last=-1;
 foreach($headers as $c=>$cell){$last=max($last,(int)$c);$label=mb_strtolower(trim((string)($cell['v']??'')),'UTF-8');$map[$label]=(int)$c;}
 $labels=array_merge(WB_COLUMNS,['Mã đơn website','Thời gian đặt','Tuổi','Ghi chú nội bộ','Phí vận chuyển']);$cols=[];
 foreach($labels as $label){$key=mb_strtolower($label,'UTF-8');if($label==='Tuổi')$cols[]=null;elseif(isset($map[$key]))$cols[]=$map[$key];elseif(in_array($label,['Tuổi','Phí vận chuyển'],true))$cols[]=null;else{$last++;$cols[]=$last;$cells[0][$last]=['v'=>$label,'t'=>1,'s'=>['bg'=>['rgb'=>'#00e500'],'bl'=>1]];$sheet['columnData'][$last]=['w'=>180];$map[$key]=$last;}}
 $sheet['cellData']=$cells;$sheet['columnCount']=max($sheet['columnCount']??17,$last+1);return [$snapshot,$cols];
}
function inbox_place(array $snapshot,array $order,string $sheetId='orders'): array {
 [$snapshot,$columns]=inbox_target($snapshot,$sheetId);$sheet=&$snapshot['sheets'][$sheetId];$row=1;
 // A row containing a value, formula or rich text anywhere is occupied. Formatting alone is empty.
 $capacity=min(50000,$sheet['rowCount']??200);while($row<$capacity){$occupied=false;foreach(($sheet['cellData'][$row]??[]) as $c=>$cell){if(!is_array($cell))continue;if(mb_strtoupper(trim((string)($sheet['cellData'][0][$c]['v']??'')),'UTF-8')==='STT'&&!empty($cell['f']))continue;if((array_key_exists('v',$cell)&&$cell['v']!==null&&$cell['v']!=='')||!empty($cell['f'])||!empty($cell['p'])){$occupied=true;break;}}if(!$occupied)break;$row++;}
 if($row>=$capacity)throw new RuntimeException('Sheet đã hết dòng trống. Thêm dòng trong BẢNG ĐƠN rồi thử lại.',400);
 foreach(($sheet['cellData'][0]??[]) as $c=>$header)if(report_fold((string)($header['v']??''))==='THOI GIAN')unset($sheet['cellData'][$row][$c]);
 foreach($order['values'] as $i=>$value){if($i===9)continue;$c=$columns[$i];if($c===null)continue;$style=$sheet['cellData'][$row][$c]['s']??null;$cell=['v'=>$value??'','t'=>$i===2?4:($i===0&&is_numeric($value)?2:1)];if($style)$cell['s']=$style;if($i===2)$cell['s']=['n'=>['pattern'=>'@']];if($i===1)$cell['custom']=['websiteOrder'=>$order['code']];$sheet['cellData'][$row][$c]=$cell;}
 foreach(($sheet['cellData'][0]??[]) as $c=>$header)if(mb_strtoupper(trim((string)($header['v']??'')),'UTF-8')==='STT')$sheet['cellData'][$row][$c]=['v'=>$row,'t'=>2,'f'=>'=ROW()-1'];
 $sheet['rowCount']=min(50000,max($sheet['rowCount']??200,$row+1));return [$snapshot,$row+1];
}
function inbox_transfer(PDO $db,array $input,string $actor,?DateTimeImmutable $now=null): array {
 $code=$input['code']??'';if(!is_string($code)||!preg_match('/^TV-[A-F0-9]{12}$/D',$code))throw new RuntimeException('Mã đơn không hợp lệ.',400);if(array_diff(array_keys($input),['action','csrf','code']))throw new RuntimeException('Dữ liệu chuyển đơn không hợp lệ.',400);inbox_board_schema($db);
 for($attempt=0;$attempt<3;$attempt++){
 $q=$db->prepare('SELECT row_number,targets FROM order_board_transfers WHERE code=?');$q->execute([$code]);$existing=$q->fetch();$n=$db->prepare('SELECT rowid FROM orders WHERE code=?');$n->execute([$code]);$notificationId=(int)$n->fetchColumn();if($existing)return ['already'=>true,'row'=>$existing['row_number'],'targets'=>json_decode($existing['targets'],true),'notificationId'=>$notificationId,'version'=>wb_current($db)['version']??0];
 $q=$db->prepare('SELECT o.*,m.note AS admin_note,m.version AS admin_version,s.check_size,s.shipping_created,s.measurements,s.tracking,s.returned FROM orders o LEFT JOIN order_admin_meta m ON m.code=o.code LEFT JOIN order_sheet s ON s.code=o.code WHERE o.code=? AND NOT EXISTS(SELECT 1 FROM order_admin_trash t WHERE t.code=o.code)');$q->execute([$code]);$r=$q->fetch();if(!$r)throw new RuntimeException('Không tìm thấy đơn hàng.',404);$o=admin_order($r);$date=(new DateTimeImmutable($o['createdAt']))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');
 $values=[$o['total'],$o['customerName'],$o['phone'],$o['address'],implode(' + ',array_map(static fn($i)=>($i['sku']??$i['name']).' · Size '.($i['size']??'').' × '.($i['qty']??1),$o['items'])),$o['note'],$r['check_size']??'',$r['shipping_created']??'',$r['measurements']??trim(($o['height']?'Cao '.$o['height'].' cm ':'').($o['weight']?'Nặng '.$o['weight'].' kg':'')),'',$r['tracking']??'',$r['returned']??'',$code,$date,$o['age']??'',$o['internalNote'],$o['shippingFee']];
 $current=wb_current($db);$snapshot=$current['snapshot']??wb_empty();$targets=[];$now??=new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh'));foreach(inbox_gender_groups($o,board_catalog($db)) as $group){[$snapshot,$sid]=inbox_daily_target($db,$snapshot,$group['gender'],$now);$groupValues=$values;$groupValues[0]=$group['total'];$groupValues[4]=implode(' + ',array_map(static fn($i)=>($i['sku']??$i['name']).' · Size '.($i['size']??'').' × '.($i['qty']??1),$group['items']));[$snapshot,$row]=inbox_place($snapshot,['code'=>$code,'values'=>$groupValues],$sid);$targets[]=['sheet'=>$sid,'name'=>$snapshot['sheets'][$sid]['name'],'row'=>$row,'gender'=>$group['gender'],'total'=>$group['total']];}$sources=$current['sources']??[];$sources[]=$code;$row=$targets[0]['row'];
 try{$saved=wb_store($db,$snapshot,$sources,$current['version']??0,$actor,['code'=>$code,'row'=>$row,'sheet'=>$targets[0]['sheet'],'targets'=>$targets]);return ['already'=>false,'row'=>$row,'targets'=>$targets,'notificationId'=>$notificationId]+$saved;}catch(RuntimeException $e){if($e->getCode()!==409||$attempt===2)throw $e;}
 }throw new RuntimeException('Bảng đang được cập nhật. Vui lòng thử lại.',409);
}


