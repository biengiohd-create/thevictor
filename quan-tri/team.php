<?php
declare(strict_types=1);
const TEAM_RIGHTS=['view'=>'Xem đơn hàng','create'=>'Tạo đơn','edit'=>'Sửa đơn','export'=>'Xuất dữ liệu','trash'=>'Xóa / khôi phục đơn','analytics'=>'Xem thống kê'];
function team_db(): PDO {
 $db=new PDO('sqlite:'.admin_private().'/admin-auth.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
 $db->exec('PRAGMA busy_timeout=5000; CREATE TABLE IF NOT EXISTS staff(id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, name TEXT NOT NULL, password_hash TEXT NOT NULL, rights TEXT NOT NULL, active INTEGER NOT NULL DEFAULT 1, version TEXT NOT NULL, created_at TEXT NOT NULL)');return $db;
}
function team_identity(): ?array {
 $config=admin_config();if(!$config||empty($_SESSION['admin']))return null;
 if(!isset($_SESSION['staff_id']))return hash_equals($config['version'],(string)($_SESSION['auth_version']??''))?['id'=>0,'username'=>$config['username'],'name'=>'Chủ cửa hàng','rights'=>array_keys(TEAM_RIGHTS),'owner'=>true]:null;
 $q=team_db()->prepare('SELECT * FROM staff WHERE id=? AND active=1');$q->execute([$_SESSION['staff_id']]);$u=$q->fetch();if(!$u||!hash_equals($u['version'],(string)($_SESSION['auth_version']??'')))return null;
 return ['id'=>(int)$u['id'],'username'=>$u['username'],'name'=>$u['name'],'rights'=>json_decode($u['rights'],true),'owner'=>false];
}
function team_can(string $right): bool {$u=team_identity();return $u&&($u['owner']||in_array($right,$u['rights'],true));}
function team_require(string $right): void {if(!team_can($right))throw new RuntimeException('Bạn chưa được cấp quyền thực hiện thao tác này.',403);}
function team_users(): array {$u=team_identity();if(!$u||!$u['owner'])throw new RuntimeException('Chỉ chủ cửa hàng được quản lý nhân viên.',403);return team_db()->query('SELECT id,username,name,rights,active,created_at FROM staff ORDER BY id')->fetchAll();}
function team_save(array $data): void {
 team_users();$db=team_db();$id=$data['id']??0;if(!is_int($id)||$id<0)throw new RuntimeException('Tài khoản không hợp lệ.',400);
 $name=trim((string)($data['name']??''));$username=trim((string)($data['username']??''));$rights=$data['rights']??[];$active=$data['active']??true;$password=$data['password']??'';
 if(!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$username)||$name===''||mb_strlen($name)>100||!is_bool($active)||!is_array($rights)||!array_is_list($rights)||array_diff($rights,array_keys(TEAM_RIGHTS))||!in_array('view',$rights,true)||strcasecmp($username,admin_config()['username'])===0)throw new RuntimeException('Kiểm tra tên đăng nhập, tên nhân viên và quyền xem đơn.',400);
 if(!is_string($password)||(($id===0||$password!=='')&&(strlen($password)<12||strlen($password)>72)))throw new RuntimeException('Mật khẩu cần từ 12 đến 72 ký tự.',400);
 $args=[$username,$name,json_encode(array_values(array_unique($rights))),$active?1:0,bin2hex(random_bytes(16))];
 try{if($id){$q=$db->prepare('SELECT id FROM staff WHERE id=?');$q->execute([$id]);if(!$q->fetch())throw new RuntimeException('Không tìm thấy nhân viên.',404);$sql='UPDATE staff SET username=?,name=?,rights=?,active=?,version=?';if($password!==''){$sql.=',password_hash=?';$args[]=password_hash($password,PASSWORD_DEFAULT);}$sql.=' WHERE id=?';$args[]=$id;}else{$sql='INSERT INTO staff(username,name,rights,active,version,password_hash,created_at) VALUES(?,?,?,?,?,?,?)';$args[]=password_hash($password,PASSWORD_DEFAULT);$args[]=gmdate('c');}$db->prepare($sql)->execute($args);}catch(PDOException $e){if((string)$e->getCode()==='23000')throw new RuntimeException('Tên đăng nhập đã tồn tại.',409);throw $e;}
}
