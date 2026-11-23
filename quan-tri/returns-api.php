<?php
ini_set('memory_limit','768M');
require __DIR__.'/lib.php';require __DIR__.'/returns-lib.php';admin_init();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try{
 if(!admin_logged_in())throw new RuntimeException('Phiên đăng nhập hết hạn.',401);team_require('view');$user=team_identity();$db=admin_db();wb_schema($db);returns_schema($db);
 if($_SERVER['REQUEST_METHOD']==='GET'){$before=max(0,(int)($_GET['before']??0));$cursor=max(0,(int)($_GET['cursor']??0));$latest=(int)$db->query('SELECT COALESCE(MAX(id),0) FROM return_edits')->fetchColumn();$messages=returns_list($db,$before);if(!$before&&$cursor){$q=$db->prepare('SELECT * FROM return_messages WHERE id IN(SELECT message_id FROM return_edits WHERE id>? AND id<=?)');$q->execute([$cursor,$latest]);$messages=array_merge($messages,array_map('returns_message',$q->fetchAll()));}echo json_encode(['messages'=>$messages,'cursor'=>$latest,'canEdit'=>team_can('edit')],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new RuntimeException('Phương thức không hỗ trợ.',405);team_require('edit');if(($_SERVER['HTTP_ORIGIN']??'')!=='https://thevictor.vn'||!str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))throw new RuntimeException('Nguồn yêu cầu không hợp lệ.',403);
 $raw=file_get_contents('php://input',false,null,0,30001);if(strlen($raw)>30000)throw new RuntimeException('Tin nhắn quá dài.',413);$input=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$_POST['csrf']=$input['csrf']??null;admin_csrf();echo json_encode(returns_write($db,$input,$user),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$code=in_array($e->getCode(),[400,401,403,404,405,409,413],true)?$e->getCode():500;http_response_code($code);echo json_encode(['error'=>$code===500?'Chưa lưu được tin. Nội dung nhập vẫn được giữ; hãy thử lại.':$e->getMessage()],JSON_UNESCAPED_UNICODE);}
