<?php
declare(strict_types=1);if(PHP_SAPI!=='cli')exit;
$root=sys_get_temp_dir().'/victor-team-'.bin2hex(random_bytes(5));mkdir($root,0700);define('VICTOR_ADMIN_TEST_DIR',$root);require __DIR__.'/lib.php';require __DIR__.'/workbook-export.php';session_save_path($root);session_start();ob_start();
function verify_team(bool $ok,string $label): void {if(!$ok)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
function denied_team(callable $fn,string $label): void {try{$fn();}catch(RuntimeException $e){verify_team($e->getCode()===403,$label);return;}throw new RuntimeException('FAIL '.$label);}
function fixture_login(string $username): void {$q=team_db()->prepare('SELECT id,version FROM staff WHERE username=?');$q->execute([$username]);$u=$q->fetch();$_SESSION=['admin'=>true,'staff_id'=>$u['id'],'auth_version'=>$u['version']];}
try{
 file_put_contents($root.'/admin-config.json',json_encode(['username'=>'owner','password_hash'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'version'=>'fixture']));$owner=['admin'=>true,'auth_version'=>'fixture'];$_SESSION=$owner;
 team_require_web_export();team_require_report();verify_team(true,'owner may view reports and export website source orders');
 verify_team(count(team_groups())===3&&array_values(TEAM_GROUPS)===['QUẢN LÝ','TRỰC PAGE','CARE ĐƠN'],'three requested groups initialized');
 $record=['id'=>0,'username'=>'fixture_page','name'=>'Fixture','password'=>bin2hex(random_bytes(16)),'rights'=>[],'group'=>'page','active'=>true];$fixturePassword=$record['password'];team_save($record);verify_team(admin_login($record['username'],$fixturePassword),'group member can log in through shared auth connection');fixture_login('fixture_page');verify_team(team_can('edit')&&team_can('board')&&team_can('inbox_status'),'group rights inherited');
 foreach(['export','history','restore','manage_staff','manage_groups'] as $right)denied_team(fn()=>team_require($right),'ungranted '.$right.' denied on server');denied_team(fn()=>team_save($record),'page cannot manage staff');
 $_SESSION=$owner;$u=team_users()[0];$record['id']=(int)$u['id'];$record['rights']=['export','manage_staff'];$record['password']='';team_save($record);verify_team(admin_login($record['username'],$fixturePassword),'group member can log in through shared auth connection');fixture_login('fixture_page');verify_team(team_can('export')&&team_can('manage_staff')&&!team_can('history'),'individual grants augment group without unrelated access');
 $target=$record;$target['id']=0;$target['username']='escalate';$target['password']=bin2hex(random_bytes(16));$target['group']='manager';denied_team(fn()=>team_save($target),'delegated manager cannot grant higher group');denied_team(fn()=>team_save($record),'delegated manager cannot edit own access');denied_team(fn()=>team_group_save(['id'=>'page','rights'=>array_keys(TEAM_RIGHTS)]),'group escalation denied');
 $previous=$_SESSION;$_SESSION=$owner;team_group_save(['id'=>'page','rights'=>['view','inbox_status','board']]);$_SESSION=$previous;verify_team(!admin_logged_in(),'group update revokes old member sessions');fixture_login('fixture_page');verify_team(!team_can('edit')&&team_can('export'),'new group rights applied and individual grants retained');denied_team(fn()=>team_require_owner(),'employee still cannot mutate source orders');
 $rows=wb_export_rows(['sheets'=>['orders'=>['cellData'=>[0=>[0=>['v'=>'SĐT'],1=>['v'=>'Ghi chú']],1=>[0=>['v'=>'0320000000'],1=>['v'=>'=DANGEROUS()']]]]]],'orders');verify_team($rows[1][0]==='0320000000'&&$rows[1][1]==="'=DANGEROUS()",'CSV preserves phone and neutralizes formula injection');
 $_SESSION=$owner;$manager=$record;$manager['id']=0;$manager['username']='fixture_manager';$manager['password']=bin2hex(random_bytes(16));$manager['group']='manager';$manager['rights']=[];team_save($manager);fixture_login('fixture_manager');verify_team(team_can('export'),'manager retains workbook CSV permission');denied_team(fn()=>team_require_web_export(),'manager with export rights cannot export website source orders');denied_team(fn()=>team_require_report(),'manager cannot access owner-only reports');
 echo "ALL TEAM TESTS PASSED\n";
}finally{session_write_close();ob_end_flush();foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);}



