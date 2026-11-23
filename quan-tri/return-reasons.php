<?php
declare(strict_types=1);
function return_reason_groups(array $orders):array {
 $groups=[];foreach($orders as $order){$reason=preg_replace('/\s+/u',' ',trim((string)($order['reason']??'')));$reason=$reason!==''?mb_strtoupper($reason,'UTF-8'):'CHƯA GHI LÝ DO';$groups[$reason]??=['reason'=>$reason,'count'=>0,'orders'=>[]];$groups[$reason]['count']++;$groups[$reason]['orders'][]=$order;}
 $groups=array_values($groups);usort($groups,static fn($a,$b)=>$b['count']<=>$a['count']?:strcmp($a['reason'],$b['reason']));return $groups;
}
function return_result_counts(array $result,array $keys):array {
 $counts=[];foreach(['delivered','returning','received','shipping','unknown'] as $state)$counts[$state]=count(array_intersect_key(array_filter($result[$state]??[],static fn($qty)=>$qty>0),array_fill_keys($keys,true)))>0?1:0;
 $counts['closed']=$counts['delivered']+$counts['returning']+$counts['received'];$counts['returned']=$counts['returning']+$counts['received'];$counts['rate']=$counts['closed']?round($counts['returned']*100/$counts['closed'],2):0;return $counts;
}
function receipt_reason_report(PDO $db,array $input):array {
 team_require_owner();$range=report_range($input);$gender=(string)($input['gender']??'');$sku=mb_strtoupper(trim((string)($input['sku']??'')),'UTF-8');if(!in_array($gender,['','Nam','Nữ'],true))throw new RuntimeException('Nhóm không hợp lệ.',400);
 $catalog=board_catalog($db);$board=board_rows($db,$catalog);$flow=stock_flow($db,$board['rows']);$cases=[];foreach($flow['cases'] as $case)$cases[$case['tracking']]=$case;
 $total=array_fill_keys(['delivered','returning','received','shipping','unknown'],0);$products=[];$orders=[];
 foreach($flow['outcomes'] as $outcome){$row=$outcome['row'];$result=$outcome['result'];if(!$row['date']||$row['date']<$range['from']||$row['date']>$range['to'])continue;$bySku=[];foreach(stock_item_map($row['items']) as $key=>$qty){[$code]=explode('|',$key,2);if(($sku!==''&&$sku!==$code)||($gender!==''&&($catalog[$code]['gender']??$row['gender'])!==$gender))continue;$bySku[$code][]=$key;}
  $keys=[];foreach($bySku as $code=>$productKeys){$keys=array_merge($keys,$productKeys);$counts=return_result_counts($result,$productKeys);$products[$code]??=['sku'=>$code]+array_fill_keys(array_keys($total),0);foreach($total as $state=>$unused)$products[$code][$state]+=$counts[$state];}if(!$keys)continue;$counts=return_result_counts($result,$keys);foreach($total as $state=>$unused)$total[$state]+=$counts[$state];if(!$counts['returned'])continue;
  $tracking=stock_tracking($row['tracking']);$case=$cases[$tracking]??null;$reason=trim((string)($row['status']??''));if(in_array(strtoupper($reason),['','OK','HH'],true))$reason=$case['reason']??'';$returnItems=[];foreach($keys as $key){$qty=($result['returning'][$key]??0)+($result['received'][$key]??0);if($qty)$returnItems[]=str_replace('|',' ',$key).' × '.$qty;}
  $orders[]=['reason'=>$reason,'tracking'=>$tracking,'date'=>$row['date'],'sheet'=>$row['sheet'],'row'=>$row['row'],'customer'=>$row['customer']??'','phone'=>$row['phone']??'','products'=>implode(' + ',$returnItems),'state'=>$counts['received']?'received':'returning'];
 }
 foreach($products as &$product){$product['closed']=$product['delivered']+$product['returning']+$product['received'];$product['returnRate']=$product['closed']?round(($product['returning']+$product['received'])*100/$product['closed'],2):0;}unset($product);ksort($products);
 $closed=$total['delivered']+$total['returning']+$total['received'];$returned=$total['returning']+$total['received'];return ['range'=>$range,'created'=>$closed,'returned'=>$returned,'returnRate'=>$closed?round($returned*100/$closed,2):0,'counts'=>$total,'products'=>array_values($products),'catalog'=>array_keys($catalog),'reasons'=>return_reason_groups($orders)];
}
