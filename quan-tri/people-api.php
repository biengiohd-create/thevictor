<?php require __DIR__.'/lib.php';admin_init();
function people_reply(array $v,int $code=200): never {http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
try{if(!admin_logged_in())throw new RuntimeException('Vui lòng đăng nhập lại.',401);$u=team_identity();$method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'';if(!$u['owner']&&!$u['profileComplete']&&$method==='GET'&&$action!=='profile')throw new RuntimeException('Cần hoàn thiện hồ sơ cá nhân.',403);
 if($method==='GET'){
  if($action==='profile')people_reply(['user'=>$u,'profile'=>people_profile_get($u)]);
  if($action==='recovery')people_reply(['rows'=>people_recovery_list()]);
  if($action==='policy'){people_require_owner();people_reply(['policy'=>payroll_policy()]);}
  if($action==='schedule'){[$from,$to]=people_month($_GET['month']??'');$uid=$u['owner']?(int)($_GET['user']??0):$u['id'];people_reply(['rows'=>payroll_schedule_list($uid,$_GET['month']),'owner'=>$u['owner'],'users'=>$u['owner']?team_users():[]]);}
  if($action==='settings'){people_require_owner();people_reply(['users'=>team_users(),'groups'=>team_groups(),'rights'=>array_diff_key(TEAM_RIGHTS,array_flip(['manage_staff','manage_groups']))]);}
  if($action==='audit_detail')people_reply(['details'=>people_audit_detail($_GET)]);
  if($action==='audit')people_reply(people_audit_list($_GET));
  if($action==='payroll'){people_require_owner();$month=$_GET['month']??'';$run=payroll_latest($month);$rows=$run['snapshot']['rows']??[];if(($_GET['user']??'')!=='')$rows=array_values(array_filter($rows,fn($x)=>(int)$x['user']['id']===(int)$_GET['user']));people_reply(['rows'=>$rows,'owner'=>true,'month'=>$month,'payrollReady'=>true,'run'=>$run?array_diff_key($run,['snapshot'=>true]):null,'notes'=>payroll_notes_list($month)]);}
  if($action==='attendance'){if(isset($_GET['user']))people_require_owner();[$from,$to]=people_month($_GET['month']??'');$users=$u['owner']&&($_GET['scope']??'')!=='my'?team_users():[array_merge(['id'=>$u['id'],'username'=>$u['username'],'name'=>$u['name']],people_profile_get($u))];if(isset($_GET['user'])&&$_GET['user']!=='')$users=array_values(array_filter($users,fn($x)=>(int)$x['id']===(int)$_GET['user']));$policy=payroll_policy();$data=[];foreach($users as $staff){$rows=people_effective_sessions((int)$staff['id'],$from,$to);$schedule=payroll_schedule_list((int)$staff['id'],$_GET['month']);$p=payroll_compute($rows,$schedule,$_GET['month'],$policy);$days=array_map(fn($d)=>array_intersect_key($d,array_flip(['date','seconds','hours','lunch','late','absent'])),$p['days']);foreach($rows as &$session)unset($session['activity']);unset($session);$data[]=['user'=>$staff,'sessions'=>$rows,'daily'=>['days'=>$days,'hours'=>$p['hours']]];}people_reply(['rows'=>$data,'owner'=>$u['owner'],'month'=>$_GET['month'],'payrollReady'=>true]);}
  throw new RuntimeException('Thao tác không hợp lệ.',400);
 }
 if($method!=='POST')throw new RuntimeException('Phương thức không hỗ trợ.',405);if(($_SERVER['HTTP_ORIGIN']??'')!=='https://thevictor.vn'||!str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))throw new RuntimeException('Nguồn yêu cầu không hợp lệ.',403);$raw=file_get_contents('php://input',false,null,0,100001);if(strlen($raw)>100000)throw new RuntimeException('Dữ liệu quá lớn.',413);$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$_POST['csrf']=$data['csrf']??null;admin_csrf();$action=$data['action']??'';if(!$u['owner']&&!$u['profileComplete']&&!in_array($action,['profile','activity','password'],true))throw new RuntimeException('Cần hoàn thiện hồ sơ cá nhân.',403);
 if($action==='password'){people_password_change($data);people_reply(['ok'=>true]);}
 if($action==='payroll_calculate'){people_reply(['ok'=>true]+payroll_generate((string)($data['month']??''),trim((string)($data['note']??''))));}
 if($action==='payroll_note'){payroll_note_save($data);people_reply(['ok'=>true]);}
 if($action==='reset_issue'){people_reply(['ok'=>true,'url'=>people_reset_issue((int)($data['id']??0))]);}
 if($action==='activity'){people_activity($data);people_reply(['ok'=>true]);}
 if($action==='schedule'){payroll_schedule($data);people_reply(['ok'=>true]);}
 if($action==='policy'){payroll_policy_save($data);people_reply(['ok'=>true]);}
 if($action==='profile')people_reply(['ok'=>true,'user'=>people_profile_save($data)]);
 if($action==='account'){people_require_owner();team_save($data);people_reply(['ok'=>true]);}
 if($action==='group'){people_require_owner();team_group_save($data);people_reply(['ok'=>true]);}
 if($action==='adjust'){people_adjust($data);people_reply(['ok'=>true]);}
 throw new RuntimeException('Thao tác không hợp lệ.',400);
}catch(Throwable $e){$code=in_array($e->getCode(),[400,401,403,404,405,409,413],true)?$e->getCode():500;if($e instanceof JsonException)$code=400;people_reply(['error'=>$code===500?'Chưa xử lý được yêu cầu. Vui lòng thử lại.':($e instanceof JsonException?'Dữ liệu không hợp lệ.':$e->getMessage())],$code);}
