<?php
require __DIR__.'/workbook-time.php';
function wt_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$sheet=['cellData'=>[0=>[0=>['v'=>'THỜI GIAN'],1=>['v'=>'STT'],2=>['v'=>'Tiền'],3=>['v'=>'Tên khách'],4=>['v'=>'SĐT'],5=>['v'=>'Địa chỉ']],1=>[1=>['v'=>1],2=>['v'=>100],3=>['v'=>'A'],4=>['v'=>'0901234567'],5=>['v'=>'Address']]]];
$book=['sheetOrder'=>['s'],'sheets'=>['s'=>$sheet]];wb_time_apply($db,$book,null);$id=$book['sheets']['s']['cellData'][1][0]['custom']['victorRowId'];
$db->prepare('UPDATE workbook_row_times SET created_at=? WHERE id=?')->execute(['2020-01-01T00:00:00Z',$id]);$old=$book;$same=$book;$same['sheets']['s']['cellData'][1][0]['v']='FAKE';wb_time_apply($db,$same,$old);wt_check($same['sheets']['s']['cellData'][1][0]['v']==='07:00:00','unchanged identity keeps authoritative time');
$name=$same;$name['sheets']['s']['cellData'][1][3]['v']='B';wb_time_apply($db,$name,$same);wt_check($db->query('SELECT created_at FROM workbook_row_times')->fetchColumn()!=='2020-01-01T00:00:00Z','customer change refreshes authoritative time');
$db->prepare('UPDATE workbook_row_times SET created_at=? WHERE id=?')->execute(['2020-01-01T00:00:00Z',$id]);$phone=$name;$phone['sheets']['s']['cellData'][1][4]['v']='0900000000';wb_time_apply($db,$phone,$name);wt_check($db->query('SELECT created_at FROM workbook_row_times')->fetchColumn()!=='2020-01-01T00:00:00Z','phone change refreshes time and keeps leading zero');
$empty=$phone;$empty['sheets']['s']['cellData'][1]=[0=>$phone['sheets']['s']['cellData'][1][0],1=>['v'=>1]];$patches=wb_time_apply($db,$empty,$phone);wt_check($empty['sheets']['s']['cellData'][1][0]['v']===''&&!isset($empty['sheets']['s']['cellData'][1][0]['custom'])&&count($patches)===1,'clearing all business cells clears time even when STT remains');
$move=$phone;$move['sheets']['s']['cellData'][8]=$move['sheets']['s']['cellData'][1];unset($move['sheets']['s']['cellData'][1]);$db->prepare('UPDATE workbook_row_times SET created_at=? WHERE id=?')->execute(['2020-01-01T00:00:00Z',$id]);wb_time_apply($db,$move,$phone);wt_check($move['sheets']['s']['cellData'][8][0]['v']==='07:00:00','row movement does not masquerade as a customer update');
echo "TIMESTAMP LIFECYCLE PASSED\n";
