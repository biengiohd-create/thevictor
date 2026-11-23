<?php
require __DIR__.'/workbook-response.php';
$snapshot=['name'=>'Tên có dấu " và xuống dòng' . "\n",'sheets'=>['a'=>['cellData'=>[0=>[0=>['v'=>'0900123456','f'=>'=SUM(C2:C9)']]]]]];
$row=['snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'version'=>'12','actor'=>'staff','updated_at'=>'2026-11-23'];$user=['username'=>'staff','owner'=>false,'rights'=>['view']];
$actual=json_decode(wb_stored_response($row,$user),true,512,JSON_THROW_ON_ERROR);
if($actual!==['snapshot'=>$snapshot,'version'=>12,'actor'=>'staff','updatedAt'=>'2026-11-23','user'=>$user])throw new RuntimeException('Stored workbook response changed data or identity');
if(array_key_exists('sources',$actual))throw new RuntimeException('Private sources must not be exposed');
echo "PASS stored JSON response preserves formulas, Unicode, phone numbers and current permissions\n";
