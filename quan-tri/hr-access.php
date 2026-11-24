<?php
declare(strict_types=1);
function people_can_view_inout(array $user): bool {return !empty($user['owner'])||($user['group']??'')==='manager';}
function people_directory(bool $payrollOnly=false): array {
 $u=team_identity();if(!$u)throw new RuntimeException('Vui lòng đăng nhập.',401);$sql='SELECT id,username,name,full_name,department,group_id FROM staff';$args=[];if($payrollOnly&&!$u['owner']){$sql.=' WHERE id=?';$args[]=$u['id'];}$q=team_db()->prepare($sql.' ORDER BY id');$q->execute($args);return $q->fetchAll();
}
function people_attendance_view(array $input): array {
 $u=team_identity();if(!$u)throw new RuntimeException('Vui lòng đăng nhập.',401);[$from,$to]=people_month($input['month']??'');$users=people_directory();$uid=(int)($input['user']??0);if(($input['scope']??'')==='my')$uid=$u['owner']?-1:$u['id'];if($uid)$users=array_values(array_filter($users,fn($s)=>(int)$s['id']===$uid));$rows=[];$policy=payroll_policy();$canViewIO=people_can_view_inout($u);
 foreach($users as $staff){$sessions=people_effective_sessions((int)$staff['id'],$from,$to);$p=payroll_compute($sessions,payroll_schedule_list((int)$staff['id'],$input['month']),$input['month'],$policy);$days=array_map(fn($d)=>array_intersect_key($d,array_flip(['date','seconds','hours','meal','late','absent'])),$p['days']);foreach($sessions as &$s)unset($s['activity']);unset($s);$rows[]=['user'=>$staff,'sessions'=>$canViewIO?$sessions:[],'daily'=>['days'=>$days,'hours'=>$p['hours'],'meal'=>$p['meal']]];}
 return ['rows'=>$rows,'owner'=>$u['owner'],'month'=>$input['month'],'canViewIO'=>$canViewIO,'payrollReady'=>true];
}
function people_payroll_view(array $input): array {
 $u=team_identity();if(!$u)throw new RuntimeException('Vui lòng đăng nhập.',401);$month=$input['month']??'';people_month($month);$target=(int)($input['user']??0);if(!$u['owner']&&$target!==0&&$target!==$u['id'])throw new RuntimeException('Bạn chỉ được xem bảng lương của chính mình.',403);
 $q=payroll_run_schema()->prepare('SELECT * FROM payroll_runs WHERE month=? ORDER BY id DESC LIMIT 1');$q->execute([$month]);$run=$q->fetch();$snapshot=$run?json_decode($run['snapshot'],true,512,JSON_THROW_ON_ERROR):[];$rows=$snapshot['rows']??[];if(!$u['owner'])$target=$u['id'];if($target)$rows=array_values(array_filter($rows,fn($r)=>(int)$r['user']['id']===$target));
 if(!people_can_view_inout($u))foreach($rows as &$row)$row['sessions']=[];unset($row);
 $meta=$run?array_intersect_key($run,array_flip($u['owner']?['id','month','created_at','pay_date','actor','note','run_kind','note_count']:['id','month','created_at','pay_date'])):null;if(!$u['owner']&&!$rows)$meta=null;
 return ['rows'=>$rows,'owner'=>$u['owner'],'month'=>$month,'payrollReady'=>true,'run'=>$meta,'notes'=>$u['owner']?payroll_notes_list($month):[]];
}
