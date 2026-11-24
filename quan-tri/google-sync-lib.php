<?php
declare(strict_types=1);
require_once __DIR__.'/workbook-lib.php';
function gsync_schema(PDO $db):void {
 $db->exec('CREATE TABLE IF NOT EXISTS google_sync_state(id INTEGER PRIMARY KEY CHECK(id=1),manifest TEXT NOT NULL,stamp INTEGER NOT NULL,initialized INTEGER NOT NULL DEFAULT 0,last_at TEXT NOT NULL DEFAULT "");CREATE TABLE IF NOT EXISTS google_sync_sheets(id TEXT PRIMARY KEY,stamp INTEGER NOT NULL,payload TEXT NOT NULL)');
}
function gsync_config():?array {$p=admin_private().'/google-sync.json';return is_file($p)?json_decode(file_get_contents($p),true,512,JSON_THROW_ON_ERROR):null;}
function gsync_backup():string {
 $dir=admin_private().'/backups';if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Không tạo được thư mục sao lưu.');
 $p=$dir.'/before-google-sync-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sqlite';$src=new SQLite3(admin_private().'/orders.sqlite');$dst=new SQLite3($p);
 if(!$src->backup($dst))throw new RuntimeException('Sao lưu thất bại; chưa thay dữ liệu.');$dst->close();$src->close();chmod($p,0600);return $p;
}
function gsync_validate_manifest(array $m):void {
 if(!array_is_list($m)||count($m)<1||count($m)>800)throw new RuntimeException('Danh sách sheet không hợp lệ.',400);$seen=[];
 foreach($m as $s){$id=$s['id']??'';if(!is_string($id)||!preg_match('/^google-[0-9]{1,20}$/D',$id)||isset($seen[$id])||!is_string($s['name']??null)||trim($s['name'])===''||mb_strlen($s['name'])>150)throw new RuntimeException('Mã hoặc tên sheet không hợp lệ.',400);$seen[$id]=true;}
}
function gsync_sheet(array $p):array {
 $id=$p['id']??'';$rows=$p['values']??null;
 if(!is_string($id)||!preg_match('/^google-[0-9]{1,20}$/D',$id)||!is_array($rows)||!array_is_list($rows)||count($rows)>50000)throw new RuntimeException('Dữ liệu sheet không hợp lệ.',400);
 $data=[];$cols=13;$count=0;
 foreach($rows as $r=>$row){if(!is_array($row)||!array_is_list($row)||count($row)>512)throw new RuntimeException('Hàng không hợp lệ.',400);$cols=max($cols,count($row));foreach($row as $c=>$v){if(!is_scalar($v)&&$v!==null)throw new RuntimeException('Giá trị ô không hợp lệ.',400);if($v===null||$v==='')continue;if(++$count>500000)throw new RuntimeException('Sheet quá lớn.',413);$data[$r][$c]=['v'=>$v,'t'=>is_int($v)||is_float($v)?2:1];}}
 // This source uses F for products and L for carrier tracking, with identical headers.
 $head=&$data[0];if(!is_array($head))$head=[];
 if(mb_strtoupper(trim((string)($head[5]['v']??'')),'UTF-8')==='MÃ ĐƠN HÀNG')$head[5]=['v'=>'Sản phẩm / size','t'=>1];
 if(mb_strtoupper(trim((string)($head[11]['v']??'')),'UTF-8')==='MÃ ĐƠN HÀNG')$head[11]=['v'=>'Mã vận đơn','t'=>1];
 foreach($head as &$cell){$v=mb_strtoupper(trim((string)($cell['v']??'')),'UTF-8');if($v==='CAO/NẶNG')$cell['v']='Chiều cao / cân nặng';$cell['s']='header';}unset($cell);
 // A is blank in the order source. Do not invent creation times for historical rows.
 if(trim((string)($head[0]['v']??''))==='')$head[0]=['v'=>'THỜI GIAN','t'=>1,'s'=>'header'];unset($head);
 return ['id'=>$id,'name'=>$id,'rowCount'=>max(50,count($rows)+10),'columnCount'=>$cols,'defaultRowHeight'=>32,'defaultColumnWidth'=>140,'freeze'=>['xSplit'=>0,'ySplit'=>1,'startRow'=>1,'startColumn'=>0],'cellData'=>$data,'columnData'=>[0=>['w'=>110],1=>['w'=>140],2=>['w'=>200],3=>['w'=>150],4=>['w'=>420],5=>['w'=>300],6=>['w'=>260]]];
}
function gsync_receive(PDO $db,array $input,?callable $backup=null):array {
 $stamp=$input['stamp']??null;$manifest=$input['manifest']??null;$incoming=$input['sheets']??null;
 if(!is_int($stamp)||$stamp<1||!is_array($manifest)||!is_array($incoming)||!array_is_list($incoming)||count($incoming)>50)throw new RuntimeException('Gói đồng bộ không hợp lệ.',400);
 gsync_validate_manifest($manifest);$known=array_fill_keys(array_column($manifest,'id'),true);$parsed=[];
 foreach($incoming as $sheet){if(!is_array($sheet)||!isset($known[$sheet['id']??''])||!is_int($sheet['stamp']??null)||$sheet['stamp']<1)throw new RuntimeException('Sheet ngoài nguồn.',400);$parsed[]=['id'=>$sheet['id'],'stamp'=>$sheet['stamp'],'json'=>json_encode(gsync_sheet($sheet),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];}
 gsync_schema($db);$state=$db->query('SELECT * FROM google_sync_state WHERE id=1')->fetch();
 // Backup the entire private database before the first replacement, outside a write lock.
 $ready=array_fill_keys($db->query('SELECT id FROM google_sync_sheets')->fetchAll(PDO::FETCH_COLUMN),true);foreach($parsed as $s)$ready[$s['id']]=true;
 $complete=true;foreach($manifest as $m)if(!isset($ready[$m['id']]))$complete=false;
 $backupPath=null;if((!$state||!$state['initialized'])&&$complete)$backupPath=($backup??'gsync_backup')();
 $db->exec('BEGIN IMMEDIATE');try {
  $state=$db->query('SELECT * FROM google_sync_state WHERE id=1')->fetch();
  if(!$state||$stamp>=(int)$state['stamp']){$db->prepare('INSERT INTO google_sync_state(id,manifest,stamp) VALUES(1,?,?) ON CONFLICT(id) DO UPDATE SET manifest=excluded.manifest,stamp=excluded.stamp')->execute([json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$stamp]);}else $manifest=json_decode($state['manifest'],true,512,JSON_THROW_ON_ERROR);
  $up=$db->prepare('INSERT INTO google_sync_sheets(id,stamp,payload) VALUES(?,?,?) ON CONFLICT(id) DO UPDATE SET stamp=excluded.stamp,payload=excluded.payload WHERE excluded.stamp>google_sync_sheets.stamp');foreach($parsed as $s)$up->execute([$s['id'],$s['stamp'],$s['json']]);
  $snapshot=wb_empty();$snapshot['name']='PHÚC 2026 · Đồng bộ Google Sheets';$snapshot['sheetOrder']=[];$snapshot['sheets']=[];$cache=[];foreach($db->query('SELECT id,payload FROM google_sync_sheets') as $r)$cache[$r['id']]=$r['payload'];$missing=0;
  foreach($manifest as $m){if(!isset($cache[$m['id']])){$missing++;continue;}$s=json_decode($cache[$m['id']],true,512,JSON_THROW_ON_ERROR);$s['name']=$m['name'];$s['hidden']=!empty($m['hidden'])?1:0;$snapshot['sheetOrder'][]=$m['id'];$snapshot['sheets'][$m['id']]=$s;}
  if($missing){$db->exec('COMMIT');return ['ok'=>true,'pending'=>$missing,'received'=>count($parsed),'active'=>false];}
  wb_validate($snapshot);$json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(strlen($json)>48000000)throw new RuntimeException('Nguồn vượt 48 MB; giữ nguyên bảng đang dùng.',413);
  $old=wb_current($db);$first=!$state||!$state['initialized'];$changed=$first||$snapshot!==($old['snapshot']??null);$version=$old['version']??0;
  if($changed){
   if($first){foreach(['cancel_messages','deleted_orders','return_messages','return_requests','return_edits','return_links','workbook_row_times','workbook_sheet_years','workbook_cell_events','workbook_input_issues','workbook_product_checks','staff_workbook_history'] as $table){$q=$db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");$q->execute([$table]);if($q->fetchColumn())$db->exec('DELETE FROM "'.$table.'"');}}
   $now=gmdate('c');$db->prepare('INSERT INTO staff_workbook(id,snapshot,sources,version,actor,updated_at) VALUES(1,?,?,?,?,?) ON CONFLICT(id) DO UPDATE SET snapshot=excluded.snapshot,version=excluded.version,actor=excluded.actor,updated_at=excluded.updated_at')->execute([$json,json_encode($old['sources']??[]),++$version,'Google Sheets',$now]);
  }
  $db->prepare('UPDATE google_sync_state SET initialized=1,last_at=? WHERE id=1')->execute([gmdate('c')]);$keep=array_column($manifest,'id');$del=$db->prepare('DELETE FROM google_sync_sheets WHERE id=?');foreach(array_keys($cache) as $id)if(!in_array($id,$keep,true))$del->execute([$id]);
  $db->exec('COMMIT');return ['ok'=>true,'active'=>true,'pending'=>0,'version'=>$version,'sheets'=>count($manifest),'changed'=>$changed];
 }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
