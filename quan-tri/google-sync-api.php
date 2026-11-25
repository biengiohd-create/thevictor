<?php
declare(strict_types=1);
ini_set('memory_limit','768M');require __DIR__.'/lib.php';require __DIR__.'/google-sync-lib.php';admin_headers();header('Content-Type: application/json; charset=utf-8');
try{
 if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'||($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException('HTTPS POST required.',405);
 $config=gsync_config();$auth=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
 if(!$config||!is_string($config['secret']??null)||strlen($config['secret'])<40||!hash_equals('Bearer '.$config['secret'],$auth))throw new RuntimeException('Unauthorized.',401);
 $raw=file_get_contents('php://input',false,null,0,8000001);if(strlen($raw)>8000000)throw new RuntimeException('Payload too large.',413);$input=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
 if(!is_array($input)||($input['fileId']??null)!==$config['fileId'])throw new RuntimeException('Wrong source.',403);
 $db=admin_db();wb_schema($db);$reply=gsync_receive($db,$input);echo json_encode($reply,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(Throwable $e){$status=in_array($e->getCode(),[400,401,403,405,413],true)?$e->getCode():500;http_response_code($status);if($status===500)error_log('google-sync: '.$e->getMessage());echo json_encode(['ok'=>false,'error'=>$status===500?'Đồng bộ chưa thành công; dữ liệu đang dùng được giữ nguyên.':$e->getMessage()],JSON_UNESCAPED_UNICODE);}
