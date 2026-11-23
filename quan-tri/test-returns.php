<?php
if(PHP_SAPI!=='cli')exit;
$root=sys_get_temp_dir().'/victor-returns-'.bin2hex(random_bytes(6));mkdir($root,0700);define('VICTOR_ADMIN_TEST_DIR',$root);require __DIR__.'/lib.php';session_save_path($root);session_start();file_put_contents($root.'/admin-config.json',json_encode(['username'=>'fixture','version'=>'fixture','password_hash'=>'unused']));$_SESSION=['admin'=>true,'auth_version'=>'fixture'];
register_shutdown_function(static function()use($root){session_write_close();foreach(glob($root.'/*') as $file)if(is_file($file))unlink($file);rmdir($root);});
require __DIR__.'/returns-lib.php';
function rt_assert(bool $ok,string $name): void {if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$catalog=array_fill_keys(['E1','E2','E4','KBH77','SM72','DTQ26','SG01','KGH90','B52'],[]);
$examples=[
 'KBH77 M K ỨNG CHẤT 990625039571'=>'K ỨNG CHẤT',
 'SM72 L RỘNG CÓ ĐỔI 990625119921'=>'RỘNG CÓ ĐỔI',
 'DTQ26 2XL CHẬT BỤNG 10CM 990624613918'=>'CHẬT BỤNG 10CM',
 'E4 S DÀI 990624627905'=>'DÀI',
 'SG01 M NGẮN 3-4CM 990624915278'=>'NGẮN 3-4CM',
 'E1L K ỨNG CHẤT, RỘNG CÓ ĐỔI990625535466'=>'K ỨNG CHẤT, RỘNG CÓ ĐỔI',
 'KBH77 S+ E4 S OK E4, KBH77 K ỨNG CHẤT 990622753446'=>'OK E4, KBH77 K ỨNG CHẤT',
 'KGH90 XL + KBH77 XL RỘNG BỤNG + ỐNG 990625413340'=>'RỘNG BỤNG + ỐNG',
 '990625039571 K ỨNG CHẤT KBH77 M'=>'K ỨNG CHẤT',
 'K ỨNG CHẤT 990625039571 KBH77 M'=>'K ỨNG CHẤT',
 'Lý do: CHẬT BỤNG | Mã vận đơn: 990624613918 | DTQ26 2XL'=>'CHẬT BỤNG',
 'SG01 M 30K RỘNG BỤNG 990624993843'=>'30K RỘNG BỤNG',
 '990624993843 SG01 M sản phẩm lỗi, không đúng size'=>'sản phẩm lỗi, không đúng size',
 "DTQ26 2XL\n990624613918\nCHẬT BỤNG 10CM"=>'CHẬT BỤNG 10CM',
 'KBH77 CHẬT 990625039571 M'=>'CHẬT',
];
foreach($examples as $text=>$reason){$p=returns_parse($text,$catalog);rt_assert(count($p)===1&&$p[0]['reason']===$reason&&$p[0]['state']==='ready','parse flexible syntax: '.$text);}
rt_assert(returns_parse('990625039571 Áo sơ mi nam size: M CHẬT',['SM72'=>['name'=>'Áo sơ mi nam']])[0]['reason']==='CHẬT','recognize full website product names');
rt_assert(returns_parse('E1 S lý do 990625039571 990624613918',$catalog)[0]['state']==='incomplete','multiple tracking numbers never broadcast a reason');
rt_assert(returns_parse("Linh\n09:37\nTin nhắn đã được thu hồi",$catalog)===[],'ignore copied chat metadata');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);wb_schema($db);returns_schema($db);stock_schema($db);$db->exec("INSERT INTO stock_products VALUES('E1','Fixture','Nam')");
$s=wb_empty();$s['sheetOrder']=['a','b'];$template=$s['sheets']['orders'];unset($s['sheets']['orders']);foreach(['a','b'] as $id){$sheet=$template;$sheet['id']=$id;$sheet['name']=$id;$sheet['cellData']=[0=>[0=>['v'=>'THỜI GIAN'],1=>['v'=>'Mã vận đơn'],2=>['v'=>'Tình trạng']],1=>[0=>['v'=>'07:00:00','custom'=>['victorRowId'=>'row-'.$id]],1=>['v'=>'990625039571'],2=>['v'=>'Cũ','s'=>'header']]];$s['sheets'][$id]=$sheet;}
wb_store($db,$s,[],0,'fixture');$u=['username'=>'fixture','name'=>'Kiểm thử'];$input=['body'=>'990625039571 E1 S CHẬT','requestKey'=>'11111111-1111-4111-a111-111111111111'];$r=returns_write($db,$input,$u);rt_assert(count($r['message']['entries'][0]['matches'])===2,'search all sheets by exact tracking');rt_assert(count($r['patches'])===2&&$r['patches'][0]['cell']['s']==='header','preserve cell formatting');rt_assert(returns_write($db,$input,$u)===$r,'retry is idempotent');
$edit=['id'=>$r['message']['id'],'revision'=>1,'body'=>'E1 S RỘNG 990625039571','requestKey'=>'22222222-2222-4222-a222-222222222222'];$r2=returns_write($db,$edit,$u);rt_assert($r2['message']['revision']===2&&$r2['patches'][0]['cell']['v']==='RỘNG','manual chat edit updates reason');
$cur=wb_current($db);$cur['snapshot']['sheets']['a']['cellData'][1][2]['v']='Nhân viên sửa';wb_store($db,$cur['snapshot'],[],$cur['version'],'fixture');
$r3=returns_write($db,['id'=>$r2['message']['id'],'revision'=>2,'body'=>'E1 S DÀI 999999999999','requestKey'=>'33333333-3333-4333-a333-333333333333'],$u);$cur=wb_current($db);rt_assert($r3['message']['entries'][0]['state']==='missing','missing tracking red');rt_assert($cur['snapshot']['sheets']['a']['cellData'][1][2]['v']==='Nhân viên sửa','editing chat never reverts a subsequent manual cell edit');rt_assert($cur['snapshot']['sheets']['b']['cellData'][1][2]['v']==='Cũ','correcting tracking restores prior owned cell');
try{returns_write($db,$edit+[],$u);rt_assert(true,'idempotent old edit remains stable');}catch(Throwable $e){throw $e;}
$stale=$edit;$stale['requestKey']='44444444-4444-4444-a444-444444444444';try{returns_write($db,$stale,$u);throw new RuntimeException('stale revision accepted');}catch(RuntimeException $e){rt_assert($e->getCode()===409,'reject stale message edit');}
rt_assert(count(returns_list($db))===1,'shared history persists');rt_assert((int)$db->query("SELECT COUNT(*) FROM people_audit WHERE event='returns_chat'")->fetchColumn()===3,'each create and edit is audited');echo "RETURNS TESTS PASSED\n";
