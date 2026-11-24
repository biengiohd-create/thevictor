<?php
ini_set('memory_limit','768M');require __DIR__.'/lib.php';require __DIR__.'/mkt-lib.php';require __DIR__.'/mkt-profit.php';require __DIR__.'/report-unified.php';admin_init();
function mkt_reply(array $data,int $code=200): never {http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
try{
 if(!admin_logged_in())throw new RuntimeException('Vui lòng đăng nhập.',401);people_require_owner();$user=team_identity();$db=admin_db();mkt_schema($db);
 if($_SERVER['REQUEST_METHOD']==='GET'){
  session_write_close();$action=$_GET['action']??'report';
  if($action==='revision')mkt_reply(['revision'=>hash('sha256',json_encode($db->query("SELECT id,status,summary FROM marketing_imports ORDER BY id DESC LIMIT 100")->fetchAll()))]);
  if($action==='sources')mkt_reply(['sources'=>$db->query("SELECT id,name,data_date,actor,created_at,status,summary FROM marketing_imports ORDER BY id DESC LIMIT 100")->fetchAll(),'catalog'=>array_values(board_catalog($db)),'policy'=>mkt_policy($db)]);
  if($action==='overview')mkt_reply(report_unified($db,$_GET));
  if($action==='profit')mkt_reply(mkt_profit_days($db,$_GET));
  if(in_array($action,['preview','download'],true)){$q=$db->prepare('SELECT * FROM marketing_imports WHERE id=?');$q->execute([(int)($_GET['id']??0)]);$source=$q->fetch();if(!$source)throw new RuntimeException('Không tìm thấy nguồn dữ liệu.',404);$base=realpath(admin_private().'/mkt-files');$path=realpath($source['path']);if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR))throw new RuntimeException('Không tìm thấy file gốc.',404);if($action==='download'){header('Content-Type: application/octet-stream');header("Content-Disposition: attachment; filename=marketing-data; filename*=UTF-8''".rawurlencode($source['name']));readfile($path);exit;}mkt_reply(['id'=>(int)$source['id'],'name'=>$source['name'],'date'=>$source['data_date'],'status'=>$source['status'],'tables'=>mkt_preview($source)]);}
  mkt_reply(mkt_report($db,$_GET));
 }
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Phương thức không hỗ trợ.',405);if(($_SERVER['HTTP_ORIGIN']??'')!=='https://thevictor.vn')throw new RuntimeException('Nguồn yêu cầu không hợp lệ.',403);
 if(str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'multipart/form-data')){
  admin_csrf();session_write_close();$token=(string)($_POST['requestKey']??'');$date=(string)($_POST['date']??'');if(!preg_match('/^[a-f0-9-]{36}$/D',$token)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||mkt_day($date,'')!==$date)throw new RuntimeException('Kiểm tra ngày dữ liệu.',400);$q=$db->prepare('SELECT id FROM marketing_imports WHERE token=?');$q->execute([$token]);if($id=$q->fetchColumn())mkt_reply(['id'=>(int)$id]);
  $file=$_FILES['file']??null;$text=trim((string)($_POST['text']??''));$folder=admin_private().'/mkt-files/'.$date;if(!is_dir($folder)&&!mkdir($folder,0700,true))throw new RuntimeException('Chưa tạo được nơi lưu dữ liệu.',500);
  if($file&&$file['error']===UPLOAD_ERR_OK){if(!is_uploaded_file($file['tmp_name'])||$file['size']>10000000)throw new RuntimeException('Chọn file tối đa 10 MB.',413);$name=basename(str_replace('\\','/',(string)$file['name']));$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));receipt_file_tables($file['tmp_name'],$ext);$path=$folder.'/'.$token.'.'.$ext;if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Chưa lưu được file nguồn.',500);}
  elseif($text!==''){if(strlen($text)>1000000)throw new RuntimeException('Dữ liệu dán tối đa 1 MB. Với bảng lớn, tải file Excel/CSV.',413);$name='Du-lieu-MKT-'.$date.'.tsv';$ext='tsv';$path=$folder.'/'.$token.'.tsv';file_put_contents($path,$text,LOCK_EX);}
  else throw new RuntimeException('Dán bảng dữ liệu hoặc chọn file Excel/CSV.',400);chmod($path,0600);
  $db->prepare('INSERT INTO marketing_imports(token,name,path,extension,data_date,actor,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$token,mb_substr($name,0,200),$path,$ext,$date,$user['username'],gmdate('c')]);mkt_reply(['id'=>(int)$db->lastInsertId()]);
 }
 if(!str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))throw new RuntimeException('Dữ liệu không hợp lệ.',400);$raw=file_get_contents('php://input',false,null,0,30001);if(strlen($raw)>30000)throw new RuntimeException('Nội dung quá lớn.',413);$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$_POST['csrf']=$data['csrf']??null;admin_csrf();session_write_close();$action=$data['action']??'';
 
 if($action==='cost_save')mkt_reply(mkt_cost_save($db,$data,$user));
 $id=(int)($data['id']??0);$config=$data['config']??null;if(!is_array($config)||!is_array($config['map']??null)||strlen((string)($config['account']??''))>200)throw new RuntimeException('Kiểm tra các cột dữ liệu.',400);foreach($config['map'] as $value)if(!is_int($value)||$value<0||$value>511)throw new RuntimeException('Chỉ số cột không hợp lệ.',400);
 if($action==='inspect'){$q=$db->prepare('SELECT * FROM marketing_imports WHERE id=?');$q->execute([$id]);$source=$q->fetch();if(!$source)throw new RuntimeException('Không tìm thấy nguồn dữ liệu.',404);$result=mkt_parse(mkt_tables($source),$config,board_catalog($db));$result['count']=count($result['rows']);$result['rows']=array_slice($result['rows'],0,100);mkt_reply($result);}
 if($action==='import')mkt_reply(mkt_import($db,$id,$config,$user));throw new RuntimeException('Thao tác không hợp lệ.',400);
}catch(Throwable $e){$code=in_array($e->getCode(),[400,401,403,404,405,409,413],true)?$e->getCode():500;mkt_reply(['error'=>$code===500?'Chưa xử lý được dữ liệu MKT. Nội dung nguồn vẫn được giữ để kiểm tra.':$e->getMessage()],$code);}
