<?php
declare(strict_types=1);
require_once __DIR__.'/board-report.php';

function returns_schema(PDO $db): void {
 $db->exec("CREATE TABLE IF NOT EXISTS return_messages(id INTEGER PRIMARY KEY AUTOINCREMENT,actor TEXT NOT NULL,actor_name TEXT NOT NULL,body TEXT NOT NULL,entries TEXT NOT NULL,revision INTEGER NOT NULL DEFAULT 1,created_at TEXT NOT NULL,updated_at TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS return_requests(request_key TEXT PRIMARY KEY,payload TEXT NOT NULL,result TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS return_edits(id INTEGER PRIMARY KEY AUTOINCREMENT,message_id INTEGER NOT NULL,actor TEXT NOT NULL,before_body TEXT NOT NULL,after_body TEXT NOT NULL,created_at TEXT NOT NULL);
 CREATE TABLE IF NOT EXISTS return_links(sheet_id TEXT NOT NULL,row_key TEXT NOT NULL,message_id INTEGER NOT NULL,tracking TEXT NOT NULL,col INTEGER NOT NULL,reason TEXT NOT NULL,before_cell TEXT,PRIMARY KEY(sheet_id,row_key));");
 $columns=array_column($db->query('PRAGMA table_info(return_messages)')->fetchAll(),'name');
 foreach(['processing'=>"TEXT NOT NULL DEFAULT 'done'",'manual_approvals'=>"TEXT NOT NULL DEFAULT '{}'" ] as $column=>$definition)if(!in_array($column,$columns,true))$db->exec('ALTER TABLE return_messages ADD COLUMN '.$column.' '.$definition);
}
function returns_can_manage(array $user): bool {return !empty($user['owner'])||($user['group']??'')==='manager';}
function returns_require_manage(array $user): void {if(!returns_can_manage($user))throw new RuntimeException('Chỉ quản lý và quản trị viên được sửa hoặc duyệt thông báo hoàn.',403);}
function returns_text(?array $cell): string {
 if(isset($cell['p']['body']['dataStream']))return trim((string)$cell['p']['body']['dataStream']);
 return trim((string)($cell['v']??''));
}
function returns_parse(string $body,array $catalog): array {
 $entries=[];$skus=array_keys($catalog);usort($skus,static fn($a,$b)=>strlen($b)<=>strlen($a));
 // Recognize complete SKU/size pairs anywhere. Repeated SKU mentions in the reason
 // ("OK E4, KBH77 K ỨNG CHẤT") are retained unless they also include a size.
 $skuPattern=$skus?implode('|',array_map(static fn($s)=>preg_quote($s,'~'),$skus)):'(?!)';
 $product='~(?<![\p{L}\d])('.$skuPattern.'|[A-Z]{1,6}\d{1,5})\s*(?:[/,:-]\s*)?(?:SIZE\s*[:=-]?\s*)?(\d{1,2}XL|XXXL|XXL|XXS|XS|XL|S|M|L)(?![\p{L}\d])~iu';
 $lines=preg_split('/\R/u',$body);preg_match_all('/(?<!\d)\d{8,20}(?!\d)/u',$body,$allCodes);
 if(count($allCodes[0])===1)$lines=[implode(' ',array_filter($lines,static fn($v)=>!preg_match('/^\s*\d{1,2}:\d{2}(?::\d{2})?\s*$/D',$v)))];
 foreach($lines as $line){$line=trim($line);if($line==='')continue;
  preg_match_all('/(?<!\d)\d{8,20}(?!\d)/u',$line,$codes,PREG_OFFSET_CAPTURE);
  if(!$codes[0]){if(preg_match($product,$line)||preg_match('/\b(?:MÃ|MA|VẬN|VAN)\b/iu',$line))$entries[]=['raw'=>$line,'tracking'=>'','products'=>[],'reason'=>'','state'=>'incomplete','matches'=>[]];continue;}
  $recognized=$line;$named=$catalog;uasort($named,static fn($a,$b)=>strlen($b['name']??'')<=>strlen($a['name']??''));foreach($named as $sku=>$item){$name=trim((string)($item['name']??''));if(mb_strlen($name)<5||$name===$sku)continue;$recognized=preg_replace('~(?<![\p{L}\d])'.preg_quote($name,'~').'(?![\p{L}\d])~iu',$sku.' ',$recognized);}
  $products=[];$reason=preg_replace_callback($product,static function($m)use(&$products){$products[]=['sku'=>mb_strtoupper($m[1],'UTF-8'),'size'=>mb_strtoupper($m[2],'UTF-8')];return ' ';},$recognized);
  if(!$products&&$skus){preg_match_all('~(?<![\p{L}\d])('.$skuPattern.')(?![\p{L}\d])~iu',$reason,$skuOnly);preg_match_all('~(?<![\p{L}\d])(\d{1,2}XL|XXXL|XXL|XXS|XS|XL|S|M|L)(?![\p{L}\d])~iu',$reason,$sizeOnly);if(count($skuOnly[0])===1&&count($sizeOnly[0])===1){$products[]=['sku'=>mb_strtoupper($skuOnly[1][0],'UTF-8'),'size'=>mb_strtoupper($sizeOnly[1][0],'UTF-8')];$reason=preg_replace('~(?<![\p{L}\d])(?:'.preg_quote($skuOnly[0][0],'~').'|'.preg_quote($sizeOnly[0][0],'~').')(?![\p{L}\d])~iu',' ',$reason);}}
  $reason=preg_replace('/(?<!\d)\d{8,20}(?!\d)/u',' ',$reason);
  $reason=preg_replace('/(?:mã\s*vận\s*đơn|ma\s*van\s*don|tracking|vận\s*đơn|mã\s*sản\s*phẩm|sản\s*phẩm|san\s*pham|sku|lý\s*do(?:\s*hoàn)?|ly\s*do(?:\s*hoan)?|size)\s*[:=]/iu',' ',$reason);$reason=preg_replace('/\s*\|+\s*/u',' ',$reason);
  $reason=trim(preg_replace('/\s+/u',' ',$reason)," \t|:;,+=-");
  // More than one tracking number on a line is ambiguous: do not broadcast one
  // reason to several customers. The employee can split the lines and resubmit.
  foreach($codes[0] as $code)$entries[]=['raw'=>$line,'tracking'=>$code[0],'products'=>$products,'reason'=>$reason,'state'=>count($codes[0])===1&&$reason!==''?'ready':'incomplete','matches'=>[]];
 }
 if(count($entries)>100)throw new RuntimeException('Mỗi tin tối đa 100 đơn. Chia thành nhiều tin để xử lý.',400);
 $counts=array_count_values(array_column($entries,'tracking'));foreach($entries as &$entry)if($entry['tracking']!==''&&($counts[$entry['tracking']]??0)>1)$entry['state']='incomplete';unset($entry);return $entries;
}
function returns_columns(array $sheet): array {
 $map=[];foreach(($sheet['cellData'][0]??[]) as $c=>$cell)$map[report_fold(returns_text($cell))]=(int)$c;
 $status=$map['TINH TRANG']??null;$tracking=$map['MA VAN DON']??$map['VAN DON']??$map['MA DON VAN CHUYEN']??null;
 if($tracking===null&&$status!==null&&isset($map['MA DON HANG'])&&$map['MA DON HANG']>$status)$tracking=$map['MA DON HANG'];
 return [$tracking,$status];
}
function returns_message(array $m): array {$m['id']=(int)$m['id'];$m['revision']=(int)$m['revision'];$m['entries']=json_decode($m['entries'],true,512,JSON_THROW_ON_ERROR);$m['manual_approvals']=json_decode($m['manual_approvals']??'{}',true,512,JSON_THROW_ON_ERROR);return $m;}
function returns_list(PDO $db,int $before=0): array {
 $q=$db->prepare('SELECT * FROM return_messages'.($before?' WHERE id<?':'').' ORDER BY id DESC LIMIT 50');$q->execute($before?[$before]:[]);return array_map('returns_message',$q->fetchAll());
}
function returns_write(PDO $db,array $input,array $user,bool $processing=false): array {
 if(!empty($input['id'])&&!$processing)returns_require_manage($user);
 if(!is_string($input['body']??null)||!is_string($input['requestKey']??null)||(isset($input['id'])&&!is_int($input['id']))||(isset($input['revision'])&&!is_int($input['revision'])))throw new RuntimeException('Nội dung yêu cầu không hợp lệ.',400);
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
  foreach($patches as $patchKey=>$patch)if(($current['snapshot']['sheets'][$patch['sheet']]['cellData'][$patch['row']][$patch['col']]??null)===$patch['cell'])unset($patches[$patchKey]);
  $version=$current['version'];if($patches){$saved=wb_store($db,$snapshot,$current['sources'],$version,$user['username'],null,[['command'=>'returns_chat.apply','at'=>(int)(microtime(true)*1000)]],true);$version=$saved['version'];}
  $db->prepare("UPDATE return_messages SET processing='done',manual_approvals='{}',body=?,entries=?,revision=?,updated_at=? WHERE id=?")->execute([$body,json_encode($entries,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$processing?(int)$old['revision']:(($old?(int)$old['revision']:0)+1),$now,$id]);
  $db->prepare('INSERT INTO return_edits(message_id,actor,before_body,after_body,created_at) VALUES(?,?,?,?,?)')->execute([$id,$user['username'],$old['body']??'',$body,$now]);
  if(function_exists('people_audit'))people_audit('returns_chat',(string)$id,['before'=>$old['body']??'','after'=>$body,'entries'=>$entries],$db);
  $q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$result=['ok'=>true,'message'=>returns_message($q->fetch()),'baseVersion'=>$current['version'],'version'=>$version,'patches'=>array_values($patches)];
  $db->prepare('INSERT INTO return_requests VALUES(?,?,?)')->execute([$key,$fingerprint,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$db->exec('COMMIT');return $result;
 }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}

// Save a durable message before the expensive workbook scan. Any connected client
// can resume pending revisions; clients cannot supply or replace the queued body.
function returns_enqueue(PDO $db,array $input,array $user): array {
 if(!is_string($input['body']??null)||!is_string($input['requestKey']??null)||!is_int($input['id']??0)||!is_int($input['revision']??0))throw new RuntimeException('Nội dung yêu cầu không hợp lệ.',400);
 $body=trim($input['body']);$key=$input['requestKey'];$id=$input['id']??0;
 if($body===''||strlen($body)>25000||!preg_match('/^[a-f0-9-]{36}$/D',$key))throw new RuntimeException('Nhập nội dung tối đa 25.000 ký tự và thử lại.',400);
 returns_parse($body,[]);
 if($id)returns_require_manage($user);
 $fingerprint=hash('sha256',json_encode(['enqueue',$id,$input['revision']??0,$body,$user['username']],JSON_UNESCAPED_UNICODE));
 $db->exec('BEGIN IMMEDIATE');try{
  $q=$db->prepare('SELECT * FROM return_requests WHERE request_key=?');$q->execute([$key]);if($prior=$q->fetch()){if($prior['payload']!==$fingerprint)throw new RuntimeException('Yêu cầu đã dùng cho nội dung khác.',409);$db->exec('COMMIT');return json_decode($prior['result'],true,512,JSON_THROW_ON_ERROR);}
  $old=null;$now=gmdate('c');
  if($id){$q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new RuntimeException('Không tìm thấy tin nhắn.',404);if((int)$old['revision']!==($input['revision']??0))throw new RuntimeException('Tin vừa được sửa. Mở lại tin trước khi sửa.',409);
   $db->prepare("UPDATE return_messages SET body=?,entries='[]',processing='pending',manual_approvals='{}',revision=revision+1,updated_at=? WHERE id=?")->execute([$body,$now,$id]);
  }else{$db->prepare("INSERT INTO return_messages(actor,actor_name,body,entries,processing,created_at,updated_at) VALUES(?,?,?,'[]','pending',?,?)")->execute([$user['username'],$user['name']?:$user['username'],$body,$now,$now]);$id=(int)$db->lastInsertId();}
  $db->prepare('INSERT INTO return_edits(message_id,actor,before_body,after_body,created_at) VALUES(?,?,?,?,?)')->execute([$id,$user['username'],$old['body']??'',$body,$now]);
  if(function_exists('people_audit'))people_audit('returns_chat',(string)$id,['before'=>$old['body']??'','after'=>$body,'queued'=>true],$db);
  $q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$result=['ok'=>true,'message'=>returns_message($q->fetch())];
  $db->prepare('INSERT INTO return_requests VALUES(?,?,?)')->execute([$key,$fingerprint,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);$db->exec('COMMIT');return $result;
 }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
function returns_process(PDO $db,array $input): array {
 $id=$input['id']??0;$revision=$input['revision']??0;if(!is_int($id)||!is_int($revision)||$id<1)throw new RuntimeException('Tin nhắn không hợp lệ.',400);
 $q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$m=$q->fetch();if(!$m)throw new RuntimeException('Không tìm thấy tin nhắn.',404);
 if((int)$m['revision']!==$revision||$m['processing']!=='pending')return ['ok'=>true,'message'=>returns_message($m)];
 $hash=hash('sha256','return-process-'.$id.'-'.$revision);$key=substr($hash,0,8).'-'.substr($hash,8,4).'-'.substr($hash,12,4).'-'.substr($hash,16,4).'-'.substr($hash,20,12);
 return returns_write($db,['id'=>$id,'revision'=>$revision,'body'=>$m['body'],'requestKey'=>$key],['username'=>$m['actor'],'name'=>$m['actor_name']],true);
}
function returns_approve(PDO $db,array $input,array $user): array {
 returns_require_manage($user);$id=$input['id']??0;$revision=$input['revision']??0;$line=$input['line']??-1;
 if(!is_int($id)||!is_int($revision)||!is_int($line)||$id<1||$line<0)throw new RuntimeException('Dòng cần duyệt không hợp lệ.',400);
 $db->exec('BEGIN IMMEDIATE');try{
  $q=$db->prepare('SELECT * FROM return_messages WHERE id=?');$q->execute([$id]);$m=$q->fetch();if(!$m)throw new RuntimeException('Không tìm thấy tin nhắn.',404);
  if((int)$m['revision']!==$revision||$m['processing']==='pending')throw new RuntimeException('Tin đang đối chiếu hoặc vừa thay đổi. Vui lòng mở lại kết quả.',409);
  $lines=preg_split('/\R/u',$m['body']);if(!isset($lines[$line])||trim($lines[$line])==='')throw new RuntimeException('Dòng cần duyệt không hợp lệ.',400);
  $approvals=json_decode($m['manual_approvals'],true)?:[];$now=gmdate('c');$approvals[(string)$line]=['actor'=>$user['username'],'at'=>$now];
  $db->prepare('UPDATE return_messages SET manual_approvals=?,revision=revision+1,updated_at=? WHERE id=?')->execute([json_encode((object)$approvals,JSON_UNESCAPED_UNICODE),$now,$id]);
  $db->prepare('INSERT INTO return_edits(message_id,actor,before_body,after_body,created_at) VALUES(?,?,?,?,?)')->execute([$id,$user['username'],$m['body'],$m['body'],$now]);
  if(function_exists('people_audit'))people_audit('returns_manual_approve',(string)$id,['line'=>$line,'text'=>$lines[$line],'actor'=>$user['username']],$db);
  $q->execute([$id]);$result=['ok'=>true,'message'=>returns_message($q->fetch())];$db->exec('COMMIT');return $result;
 }catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
}
