<?php require __DIR__.'/lib.php';require_once __DIR__.'/staff-insights-lib.php';admin_init();
function people_reply(array $v,int $code=200): never {http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;}
try{if(!admin_logged_in())throw new RuntimeException('Vui lòng đăng nhập lại.',401);$u=team_identity();$method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'';if(!$u['owner']&&!$u['profileComplete']&&$method==='GET'&&$action!=='profile')throw new RuntimeException('Cần hoàn thiện hồ sơ cá nhân.',403);
 if($method==='GET'){
  if($action==='insights')people_reply(staff_insights($_GET));if($action==='profile')people_reply(['user'=>$u,'profile'=>people_profile_get($u)]);
  if($action==='recovery')people_reply(['rows'=>people_recovery_list()]);
  if($action==='policy'){people_require_owner();people_reply(['policy'=>payroll_policy()]);}
  if(in_array($action,['schedule','leave'],true))people_reply(leave_list($_GET));
  if($action==='directory')people_reply(['users'=>people_directory(($_GET['scope']??'')==='payroll'),'owner'=>$u['owner']]);
  if($action==='settings'){people_require_owner();people_reply(['users'=>team_users(),'groups'=>team_groups(),'rights'=>array_diff_key(TEAM_RIGHTS,array_flip(['manage_staff','manage_groups']))]);}
  if($action==='audit_detail')people_reply(['details'=>people_audit_detail($_GET)]);
  if($action==='audit')people_reply(people_audit_list($_GET));
  if($action==='payroll')people_reply(people_payroll_view($_GET));
  if($action==='attendance')people_reply(people_attendance_view($_GET));
  throw new RuntimeException('Thao tác không hợp lệ.',400);
 }
 if($method!=='POST')throw new RuntimeException('Phương thức không hỗ trợ.',405);if(($_SERVER['HTTP_ORIGIN']??'')!=='https://thevictor.vn'||!str_contains(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json'))throw new RuntimeException('Nguồn yêu cầu không hợp lệ.',403);$raw=file_get_contents('php://input',false,null,0,100001);if(strlen($raw)>100000)throw new RuntimeException('Dữ liệu quá lớn.',413);$data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);$_POST['csrf']=$data['csrf']??null;admin_csrf();$action=$data['action']??'';if(!$u['owner']&&!$u['profileComplete']&&!in_array($action,['profile','activity','password'],true))throw new RuntimeException('Cần hoàn thiện hồ sơ cá nhân.',403);
 if($action==='password'){people_password_change($data);people_reply(['ok'=>true]);}
 if($action==='payroll_calculate'){people_reply(['ok'=>true]+payroll_generate((string)($data['month']??''),trim((string)($data['note']??''))));}
 if($action==='payroll_note'){payroll_note_save($data);people_reply(['ok'=>true]);}
 if($action==='reset_issue'){people_reply(['ok'=>true,'url'=>people_reset_issue((int)($data['id']??0))]);}
 if($action==='activity'){people_activity($data);people_reply(['ok'=>true]);}
 if(in_array($action,['schedule','leave'],true))people_reply(['ok'=>true]+leave_save($data));
 if($action==='policy'){payroll_policy_save($data);people_reply(['ok'=>true]);}
 if($action==='profile')people_reply(['ok'=>true,'user'=>people_profile_save($data)]);
 if($action==='account'){people_require_owner();team_save($data);people_reply(['ok'=>true]);}
 if($action==='group'){people_require_owner();team_group_save($data);people_reply(['ok'=>true]);}
 if($action==='adjust'){people_adjust($data);people_reply(['ok'=>true]);}
 throw new RuntimeException('Thao tác không hợp lệ.',400);
}catch(Throwable $e){$code=in_array($e->getCode(),[400,401,403,404,405,409,413],true)?$e->getCode():500;if($e instanceof JsonException)$code=400;people_reply(['error'=>$code===500?'Chưa xử lý được yêu cầu. Vui lòng thử lại.':($e instanceof JsonException?'Dữ liệu không hợp lệ.':$e->getMessage())],$code);}
