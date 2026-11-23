<?php
declare(strict_types=1);
function return_reason_groups(array $orders):array {
 $groups=[];foreach($orders as $order){$reason=preg_replace('/\s+/u',' ',trim((string)($order['reason']??'')));$reason=$reason!==''?mb_strtoupper($reason,'UTF-8'):'CHƯA GHI LÝ DO';$groups[$reason]??=['reason'=>$reason,'count'=>0,'orders'=>[]];$groups[$reason]['count']++;$groups[$reason]['orders'][]=$order;}
 $groups=array_values($groups);usort($groups,static fn($a,$b)=>$b['count']<=>$a['count']?:strcmp($a['reason'],$b['reason']));return $groups;
}
function receipt_reason_report(PDO $db,array $input):array {
 team_require_owner();$range=report_range($input);$gender=(string)($input['gender']??'');if(!in_array($gender,['','Nam','Nữ'],true))throw new RuntimeException('Nhóm không hợp lệ.',400);
 $board=board_rows($db,board_catalog($db));$flow=stock_flow($db,$board['rows']);$cases=[];foreach($flow['cases'] as $case)$cases[$case['tracking']]=$case;
 $created=0;$orders=[];$seen=[];foreach(stock_group_rows(array_values(array_filter($board['rows'],static fn($r)=>!stock_is_shortage($r)))) as $row){if(!$row['created']||!$row['date']||$row['date']<$range['from']||$row['date']>$range['to']||($gender!==''&&$row['gender']!==$gender))continue;$code=stock_tracking($row['tracking']);$id=$code!==''?$code:$row['sheetId'].'|'.$row['row'];if(isset($seen[$id]))continue;$seen[$id]=true;$created++;$case=$cases[$code]??null;if(!$row['returned']&&!$case)continue;$reason=trim((string)($row['status']??''));if(in_array(strtoupper($reason),['','OK','HH'],true))$reason=$case['reason']??'';$orders[]=['reason'=>$reason,'tracking'=>$code,'date'=>$row['date'],'sheet'=>$row['sheet'],'row'=>$row['row'],'customer'=>$row['customer']??'','phone'=>$row['phone']??'','products'=>$row['text'],'state'=>$case['state']??'received'];}
 $returned=count($orders);return ['range'=>$range,'created'=>$created,'returned'=>$returned,'returnRate'=>$created?round($returned*100/$created,2):0,'reasons'=>return_reason_groups($orders)];
}
