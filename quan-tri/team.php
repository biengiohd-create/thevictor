<?php
declare(strict_types=1);
const TEAM_RIGHTS=['view'=>'Xem ĐƠN WEB / BẢNG ĐƠN','edit'=>'Thêm, sửa, xóa trong BẢNG ĐƠN','inbox_status'=>'Đổi trạng thái ĐƠN WEB','board'=>'LÊN BẢNG','export'=>'Xuất CSV','history'=>'Xem lịch sử','restore'=>'Khôi phục BẢNG ĐƠN','manage_staff'=>'Quản lý nhân viên','manage_groups'=>'Cấu hình nhóm quyền'];
const TEAM_GROUPS=['manager'=>'QUẢN LÝ','page'=>'TRỰC PAGE','care'=>'CARE ĐƠN'];
function team_db(): PDO {
 static $db=null;if($db instanceof PDO)return $db;
 $db=new PDO('sqlite:'.admin_private().'/admin-auth.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000; CREATE TABLE IF NOT EXISTS staff(id INTEGER PRIMARY KEY AUTOINCREMENT,username TEXT UNIQUE NOT NULL,name TEXT NOT NULL,password_hash TEXT NOT NULL,rights TEXT NOT NULL,active INTEGER NOT NULL DEFAULT 1,version TEXT NOT NULL,created_at TEXT NOT NULL);CREATE TABLE IF NOT EXISTS staff_groups(id TEXT PRIMARY KEY,rights TEXT NOT NULL)');
 if(!in_array('group_id',array_column($db->query('PRAGMA table_info(staff)')->fetchAll(),'name'),true))$db->exec("ALTER TABLE staff ADD COLUMN group_id TEXT NOT NULL DEFAULT ''");
 if(!in_array('department',array_column($db->query('PRAGMA table_info(staff)')->fetchAll(),'name'),true))$db->exec("ALTER TABLE staff ADD COLUMN department TEXT NOT NULL DEFAULT ''");
 $defaults=['manager'=>array_keys(TEAM_RIGHTS),'page'=>['view','edit','inbox_status','board'],'care'=>['view','edit','inbox_status','board','history']];$q=$db->prepare('INSERT OR IGNORE INTO staff_groups(id,rights) VALUES(?,?)');foreach($defaults as $id=>$rights)$q->execute([$id,json_encode($rights)]);return $db;
}
function team_groups(): array {$out=[];foreach(team_db()->query('SELECT * FROM staff_groups') as $g)$out[]=['id'=>$g['id'],'name'=>TEAM_GROUPS[$g['id']],'rights'=>json_decode($g['rights'],true)];return $out;}
function team_effective(array $u): array {$rights=json_decode($u['rights'],true)?:[];if($u['group_id']??''){$q=team_db()->prepare('SELECT rights FROM staff_groups WHERE id=?');$q->execute([$u['group_id']]);$rights=array_merge($rights,json_decode($q->fetchColumn()?:'[]',true));}return array_values(array_intersect(array_keys(TEAM_RIGHTS),array_unique($rights)));}
function team_identity(): ?array {
 $config=admin_config();if(!$config||empty($_SESSION['admin']))return null;
 if(!isset($_SESSION['staff_id']))return hash_equals($config['version'],(string)($_SESSION['auth_version']??''))?['id'=>0,'username'=>$config['username'],'name'=>people_setting('owner_name',''),'rights'=>array_keys(TEAM_RIGHTS),'owner'=>true,'group'=>'owner','department'=>'QUẢN TRỊ']:null;
 $q=team_db()->prepare('SELECT * FROM staff WHERE id=? AND active=1');$q->execute([$_SESSION['staff_id']]);$u=$q->fetch();if(!$u||!hash_equals($u['version'],(string)($_SESSION['auth_version']??'')))return null;
 return ['id'=>(int)$u['id'],'username'=>$u['username'],'name'=>$u['name'],'rights'=>team_effective($u),'owner'=>false,'group'=>$u['group_id'],'department'=>trim($u['department']??'')?: (TEAM_GROUPS[$u['group_id']]??'Chưa phân bộ phận')];
}
function team_can(string $right): bool {$u=team_identity();return $u&&($u['owner']||in_array($right,$u['rights'],true));}
function team_require(string $right): void {if(!team_can($right))throw new RuntimeException('Bạn chưa được cấp quyền thực hiện thao tác này.',403);}
function team_require_owner(): void {$u=team_identity();if(!$u||!$u['owner'])throw new RuntimeException('Nhân viên không được chỉnh sửa hoặc xóa thông tin đơn website gốc.',403);}
function team_users(): array {people_require_owner();$rows=team_db()->query('SELECT id,username,name,department,rights,group_id,active,created_at FROM staff ORDER BY id')->fetchAll();foreach($rows as &$r)$r['effectiveRights']=team_effective($r);return $rows;}
function team_assignable(array $rights): void {$u=team_identity();if(!$u['owner']&&array_diff($rights,$u['rights']))throw new RuntimeException('Không được cấp hoặc quản lý quyền cao hơn quyền của bạn.',403);}
function team_group_save(array $data): void {
 people_require_owner();$id=$data['id']??'';$rights=$data['rights']??null;if(!is_string($id)||!isset(TEAM_GROUPS[$id])||!is_array($rights)||!array_is_list($rights)||array_diff($rights,array_keys(TEAM_RIGHTS))||!in_array('view',$rights,true))throw new RuntimeException('Nhóm quyền không hợp lệ.',400);
 $db=team_db();$q=$db->prepare('SELECT rights FROM staff_groups WHERE id=?');$q->execute([$id]);team_assignable(json_decode($q->fetchColumn(),true));team_assignable($rights);$u=team_identity();if(!$u['owner']&&$u['group']===$id)throw new RuntimeException('Không được tự sửa nhóm quyền của mình.',403);
 $db->beginTransaction();try{$db->prepare('UPDATE staff_groups SET rights=? WHERE id=?')->execute([json_encode(array_values(array_unique($rights))),$id]);$db->prepare('UPDATE staff SET version=? WHERE group_id=?')->execute([bin2hex(random_bytes(16)),$id]);people_audit('group_update',TEAM_GROUPS[$id],['rights'=>$rights]);$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
}
function team_save(array $data): void {
 people_require_owner();$db=team_db();$id=$data['id']??0;$group=$data['group']??'';if(!is_int($id)||$id<0||!is_string($group)||($group!==''&&!isset(TEAM_GROUPS[$group])))throw new RuntimeException('Tài khoản hoặc nhóm không hợp lệ.',400);
 $name=people_name($data['name']??'');$username=trim((string)($data['username']??''));$rights=$data['rights']??[];$active=$data['active']??true;$password=$data['password']??'';
 if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$username)||mb_strlen($name)>100||preg_match('/[\p{C}]/u',$name)||!is_bool($active)||!is_array($rights)||!array_is_list($rights)||array_diff($rights,array_keys(TEAM_RIGHTS))||strcasecmp($username,admin_config()['username'])===0)throw new RuntimeException('Kiểm tra tên đăng nhập, họ tên và quyền.',400);
 $effective=team_effective(['rights'=>json_encode($rights),'group_id'=>$group]);if(!in_array('view',$effective,true))throw new RuntimeException('Cần quyền xem.',400);team_assignable($effective);
 if(!is_string($password)||(($id===0||$password!=='')&&(strlen($password)<12||strlen($password)>72)))throw new RuntimeException('Mật khẩu cần từ 12–72 ký tự.',400);
 if($id){$q=$db->prepare('SELECT * FROM staff WHERE id=?');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new RuntimeException('Không tìm thấy nhân viên.',404);team_assignable(team_effective($old));if(!team_identity()['owner']&&team_identity()['id']===$id)throw new RuntimeException('Không được tự sửa tài khoản của mình.',403);}
 $department=people_name($data['department']??'');$args=[$username,$name,json_encode(array_values(array_unique($rights))),$group,$active?1:0,bin2hex(random_bytes(16))];
 try{if($id){$sql='UPDATE staff SET username=?,name=?,rights=?,group_id=?,active=?,version=?';if($password!==''){$sql.=',password_hash=?';$args[]=password_hash($password,PASSWORD_DEFAULT);}$sql.=' WHERE id=?';$args[]=$id;}else{$sql='INSERT INTO staff(username,name,rights,group_id,active,version,password_hash,created_at) VALUES(?,?,?,?,?,?,?,?)';$args[]=password_hash($password,PASSWORD_DEFAULT);$args[]=gmdate('c');}$db->beginTransaction();try{$db->prepare($sql)->execute($args);$savedId=$id?: (int)$db->lastInsertId();$db->prepare('UPDATE staff SET department=? WHERE id=?')->execute([$department,$savedId]);people_audit($id?'account_update':'account_create',$username,['id'=>$savedId,'before'=>isset($old)?array_intersect_key($old,array_flip(['username','name','department','rights','group_id','active'])):null,'after'=>['username'=>$username,'name'=>$name,'department'=>$department,'rights'=>$rights,'group'=>$group,'active'=>$active],'passwordChanged'=>$password!=='']);$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}} catch(PDOException $e){if((string)$e->getCode()==='23000')throw new RuntimeException('Tên đăng nhập đã tồn tại.',409);throw $e;}
}


function team_require_web_export(): void {if(!team_identity()||!team_identity()['owner'])throw new RuntimeException('Chỉ quản trị viên gốc được xuất dữ liệu ĐƠN WEB.',403);}

function team_require_report(): void {if(!team_identity()||!team_identity()['owner'])throw new RuntimeException('Chỉ quản trị viên gốc được xem Báo cáo.',403);}
