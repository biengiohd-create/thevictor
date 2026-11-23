<?php
declare(strict_types=1);
require_once __DIR__.'/board-report.php';

function returns_schema(PDO $db): void {
 $db->exec("CREATE TABLE IF NOT EXISTS return_messages(id INTEGER PRIMARY KEY AUTOINCREMENT,actor TEXT NOT NULL,actor_name TEXT NOT NULL,body TEXT NOT NULL,entries TEXT NOT NULL,revision INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,updated_at TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS return_requests(request_key TEXT PRIMARY KEY,payload TEXT NOT NULL,result TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS return_edits(id INTEGER PRIMARY KEY AUTOINCREMENT,message_id INTEGER NOT NULL,actor TEXT NOT NULL,before_body TEXT NOT NULL,after_body TEXT NOT NULL,created_at TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS return_links(sheet_id TEXT NOT NULL,row_key TEXT NOT NULL,message_id INTEGER NOT NULL,tracking TEXT NOT NULL,col INTEGER NOT NULL,reason TEXT NOT NULL,before_cell TEXT,PRIMARY KEY(sheet_id,row_key));");
}
function returns_text(?array $cell): string {
 if(isset($cell['p']['body']['dataStream']))return trim((string)$cell['p']['body']['dataStream']);
 return trim((string)($cell['v']??''));
}
function returns_parse(string $body,array $catalog): array {
 $entries=[];$skus=array_keys($catalog);usort($skus,static fn($a,$b)=>strlen($b)<=>strlen($a));
 // Recognize complete SKU/size pairs anywhere. Repeated SKU mentions in the reason
 // ("OK E4, KBH77 K ỨNG CHẤT") are retained unless they also include a size.
 $skuPattern=$skus?implode('|',array_map(static fn($s)=>preg_quote($s,'~'),$skus)):'(?!)';
 $product='~(?<![\p{L}\d])('.$skuPattern.'|[A-Z]{1,6}\d{1,5})\s*(?:[/,:-]\s*)?(?:SIZE\s*)?(\d{1,2}XL|XXXL|XXL|XXS|XS|XL|S|M|L)(?![\p{L}\d])~iu';
 $lines=preg_split('/\R/u',$body);preg_match_all('/(?<!\d)\d{8,20}(?!\d)/u',$body,$allCodes);
 if(count($allCodes[0])===1)$lines=[implode(' ',array_filter($lines,static fn($v)=>!preg_match('/^\s*\d{1,2}:\d{2}(?::\d{2})?\s*$/D',$v)))];
 foreach($lines as $line){$line=trim($line);if($line==='')continue;
  preg_match_all('/(?<!\d)\d{8,20}(?!\d)/u',$line,$codes,PREG_OFFSET_CAPTURE);
  if(!$codes[0]){if(preg_match($product,$line)||preg_match('/\b(?:MÃ|MA|VẬN|VAN)\b/iu',$line))$entries[]=['raw'=>$line,'tracking'=>'','products'=>[],'reason'=>'','state'=>'incomplete','matches'=>[]];continue;}
  $products=[];$reason=preg_replace_callback($product,static function($m)use(&$products){$products[]=['sku'=>mb_strtoupper($m[1],'UTF-8'),'size'=>mb_strtoupper($m[2],'UTF-8')];return ' ';},$line);
  $reason=preg_replace('/(?<!\d)\d{8,20}(?!\d)/u',' ',$reason);
  $reason=preg_replace('/(?:mã\s*vận\s*đơn|ma\s*van\s*don|tracking|vận\s*đơn|sản\s*phẩm|san\s*pham|lý\s*do(?:\s*hoàn)?|ly\s*do(?:\s*hoan)?|size)\s*[:=]?/iu',' ',$reason);
  $reason=trim(preg_replace('/\s+/u',' ',$reason)," \t|:;,+=-");
  // More than one tracking number on a line is ambiguous: do not broadcast one
  // reason to several customers. The employee can split the lines and resubmit.
  foreach($codes[0] as $code)$entries[]=['raw'=>$line,'tracking'=>$code[0],'products'=>$products,'reason'=>$reason,'state'=>count($codes[0])===1&&$reason!==''?'ready':'incomplete','matches'=>[]];
 }
 if(count($entries)>100)throw new RuntimeException('Mỗi tin tối đa 100 đơn. Chia thành nhiều tin để xử lý.',400);
 return $entries;
}
function returns_columns(array $sheet): array {
 $map=[];foreach(($sheet['cellData'][0]??[]) as $c=>$cell)$map[report_fold(returns_text($cell))]=(int)$c;
 $status=$map['TINH TRANG']??null;$tracking=$map['MA VAN DON']??$map['VAN DON']??$map['MA DON VAN CHUYEN']??null;
 if($tracking===null&&$status!==null&&isset($map['MA DON HANG'])&&$map['MA DON HANG']>$status)$tracking=$map['MA DON HANG'];
 return [$tracking,$status];
}
function returns_message(array $m): array {$m['id']=(int)$m['id'];$m['revision']=(int)$m['revision'];$m['entries']=json_decode($m['entries'],true,512,JSON_THROW_ON_ERROR);return $m;}
function returns_list(PDO $db,int $before=0): array {
 $q=$db->prepare('SELECT * FROM return_messages WHERE (?=0 OR id<?) ORDER BY id DESC LIMIT 50');$q->execute([$before,$before]);return array_map('returns_message',$q->fetchAll());
}
function returns_write(PDO $db,array $input,array $user): array {
 $body=trim((string)($input['body']??''));$key=(string)($input['requestKey']??'');$id=(int)($input['id']??0);
 if($body===''||strlen($body)>25000||!preg_match('/^[a-f0-9-]{36}$/D',$key))throw new RuntimeException('Nhập nội dung tối đa 25.000 ký tự và thử lại.',400);
 $fingerprint=hash('sha256',json_encode([$id,$input['revision']??0,$body,$user['username']],JSON_UNESCAPED_UNICODE));
 $db->exec('BEGIN IMMEDIATE');try{
  $q=$db->prepare('SELECT * FROM return_requests WHERE request_key=?');$q->execute([$key]);if($prior=$q->fetch()){if($prior['payload']!==$fingerprint)throw new RuntimeException('Yêu cầu đã dùng cho nội dung khác.',409);$db->exec('COMMIT');return json_decode($prior['result'],true,512,JSON_THROW_ON_ERROR);}
  $old=null;if($id){$q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new RuntimeException('Không tìm thấy tin nhắn.',404);if((int)$old['revision']!==(int)($input['revision']??0))throw new RuntimeException('Tin nhắn vừa được người khác sửa. Mở lại tin trước khi sửa.',409);}
  $entries=returns_parse($body,board_catalog($db));$current=wb_current($db);if(!$current)throw new RuntimeException('Chưa có bảng đơn.',409);$snapshot=$current['snapshot'];$patches=[];$now=gmdate('c');
  if(!$id){$db->prepare('INSERT INTO return_messages(actor,actor_name,body,entries,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$user['username'],$user['name']??$user['username'],$body,'[]',$now,$now]);$id=(int)$db->lastInsertId();}
  $index=[];foreach($snapshot['sheetOrder'] as $sid){$sheet=$snapshot['sheets'][$sid];[$tc,$sc]=returns_columns($sheet);if($tc===null||$sc===null)continue;foreach(($sheet['cellData']??[]) as $r=>$cells){if((int)$r<1)continue;$tracking=preg_replace('/\s+/u','',returns_text($cells[$tc]??null));if($tracking==='')continue;$rowKey=(string)($cells[0]['custom']['victorRowId']??('row:'.$r));$index[$tracking][]=['sheet'=>$sid,'row'=>(int)$r,'col'=>$sc,'rowKey'=>$rowKey,'name'=>$sheet['name']];}}
  // Revert this message's previous assignment only while it still owns the cell
  // and no employee has changed that cell since. Never overwrite a later notice.
  if($old){$q=$db->prepare('SELECT * FROM return_links WHERE message_id=?');$q->execute([$id]);foreach($q as $link){foreach(($index[$link['tracking']]??[]) as $loc){if($loc['sheet']!==$link['sheet_id']||$loc['rowKey']!==$link['row_key']||$loc['col']!==(int)$link['col'])continue;$cell=$snapshot['sheets'][$loc['sheet']]['cellData'][$loc['row']][$loc['col']]??null;if(returns_text($cell)!==$link['reason'])continue;$before=json_decode($link['before_cell']??'null',true);$snapshot['sheets'][$loc['sheet']]['cellData'][$loc['row']][$loc['col']]=$before;$patches[$loc['sheet'].'|'.$loc['row'].'|'.$loc['col']]=$loc+['cell'=>$before];}}$db->prepare('DELETE FROM return_links WHERE message_id=?')->execute([$id]);}
  foreach($entries as &$entry){$locations=$index[$entry['tracking']]??[];if(!$locations&&$entry['tracking']!==''){$entry['state']='missing';continue;}if($entry['state']!=='ready')continue;$entry['state']='matched';foreach($locations as $loc){$sid=$loc['sheet'];$r=$loc['row'];$c=$loc['col'];$before=$snapshot['sheets'][$sid]['cellData'][$r][$c]??null;$cell=$before??[];unset($cell['f'],$cell['si'],$cell['p']);$cell['v']=$entry['reason'];$cell['t']=1;$snapshot['sheets'][$sid]['cellData'][$r][$c]=$cell;$patches[$sid.'|'.$r.'|'.$c]=$loc+['cell'=>$cell];$db->prepare('INSERT INTO return_links(sheet_id,row_key,message_id,tracking,col,reason,before_cell) VALUES(?,?,?,?,?,?,?) ON CONFLICT(sheet_id,row_key) DO UPDATE SET message_id=excluded.message_id,tracking=excluded.tracking,col=excluded.col,reason=excluded.reason,before_cell=excluded.before_cell')->execute([$sid,$loc['rowKey'],$id,$entry['tracking'],$c,$entry['reason'],json_encode($before,JSON_UNESCAPED_UNICODE)]);$entry['matches'][]=['sheet'=>$loc['name'],'row'=>$r+1];}}unset($entry);
  $version=$current['version'];if($patches){$saved=wb_store($db,$snapshot,$current['sources'],$version,$user['username'],null,[['command'=>'returns_chat.apply','at'=>(int)(microtime(true)*1000)]],true);$version=$saved['version'];}
  $db->prepare('UPDATE return_messages SET body=?,entries=?,revision=?,updated_at=? WHERE id=?')->execute([$body,json_encode($entries,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),($old?(int)$old['revision']:0)+1,$now,$id]);
  $db->prepare('INSERT INTO return_edits(message_id,actor,before_body,after_body,created_at) VALUES(?,?,?,?,?)')->execute([$id,$user['username'],$old['body']??'',$body,$now]);
  if(function_exists('people_audit'))people_audit('returns_chat',$id,['before'=>$old['body']??'','after'=>$body,'entries'=>$entries],$db);
  $q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$result=['ok'=>true,'message'=>returns_message($q->fetch()),'baseVersion'=>$current['version'],'version'=>$version,'patches'=>array_values($patches)];
  $db->prepare('INSERT INTO return_requests VALUES(?,?,?)')->execute([$key,$fingerprint,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$db->exec('COMMIT');return $result;
 }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
