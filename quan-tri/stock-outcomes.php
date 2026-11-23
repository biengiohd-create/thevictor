<?php
declare(strict_types=1);
function stock_kept_items(array $pool,string $text):array {
 $kept=[];preg_match_all('/(?:\bOK|NHẬN|GIỮ)\s*[:,-]?\s*([A-Z]{1,8}\d{1,6})(?![\p{L}\d])(?:\s*(?:SIZE\s*)?(4XL|3XL|2XL|XXXL|XXL|XS|XL|S|M|L)(?![\p{L}\d]))?(?:\s*[×x*]\s*(\d+))?/iu',$text,$matches,PREG_SET_ORDER);
 foreach($matches as $m){$sku=strtoupper($m[1]);$size=isset($m[2])?strtr(strtoupper($m[2]),['XXXL'=>'3XL','XXL'=>'2XL']):'';$keys=array_keys(array_filter($pool,static fn($qty,$key)=>str_starts_with($key,$sku.'|')&&($size===''||$key===$sku.'|'.$size),ARRAY_FILTER_USE_BOTH));if(count($keys)!==1)continue;$key=$keys[0];$qty=isset($m[3])&&$m[3]!==''?(int)$m[3]:$pool[$key];if($qty>0&&$qty<=$pool[$key])$kept[$key]=$qty;}
 return $kept;
}
function stock_resolve_outcome(array $row,?array $entry,bool $received,?array $manual):array {
 $out=stock_item_map($row['items']);$status=trim((string)($row['status']??''));$text=$status.' '.($entry['raw']??'');$kept=stock_kept_items($out,$text);$partial=false;
 // A mixed status explicitly naming a returned item is evidence even for imported sheets.
 if($kept&&preg_match('/CHẬT|RỘNG|NGẮN|DÀI|HOÀN|CÓ ĐỔI|KHÔNG ƯNG|K ƯNG/iu',$status))foreach($out as $key=>$qty){[$sku]=explode('|',$key,2);if(($kept[$key]??0)<$qty&&preg_match('/(?<![\p{L}\d])'.preg_quote($sku,'/').'(?![\p{L}\d])/iu',$status))$partial=true;}
 $ambiguousStatus=preg_match('/\bOK\s+[A-Z]{1,8}\d/iu',$status)&&preg_match('/CHẬT|RỘNG|NGẮN|DÀI|HOÀN|CÓ ĐỔI|KHÔNG ƯNG|K ƯNG/iu',$status);
 $returnEvent=$received||$entry!==null||$partial||$ambiguousStatus;$allocated=[];$error='';
 if($returnEvent){
  $a=stock_return_items($row,$entry,$manual);$allocated=$a['items'];$error=$a['error'];
  if($manual===null&&empty($row['quantityConflict'])&&$partial&&$kept&&($entry===null||$error!=='')){$allocated=[];$error='';foreach($out as $key=>$qty){if($qty<=($kept[$key]??0))continue;[$sku]=explode('|',$key,2);$same=array_filter(array_keys($out),static fn($k)=>str_starts_with($k,$sku.'|'));if(count($same)===1&&preg_match('/(?<![\p{L}\d])'.preg_quote($sku,'/').'(?![\p{L}\d])/iu',$status))$allocated[$key]=$qty-($kept[$key]??0);else $error='Chưa xác định đủ mã / size hàng hoàn.';}}
 }
 $delivered=[];$returning=[];$returned=[];$shipping=[];$unknown=[];
 foreach($out as $key=>$qty){$returnQty=min($qty,$allocated[$key]??0);$accepted=min($qty-$returnQty,$kept[$key]??0);
  if(!$returnEvent&&strtoupper($status)==='OK')$accepted=$qty;
  if($returnEvent&&$error==='')$accepted=$qty-$returnQty;
  if($accepted)$delivered[$key]=$accepted;
  if($returnQty){if($received)$returned[$key]=$returnQty;else $returning[$key]=$returnQty;}
  $left=$qty-$accepted-$returnQty;if($left>0){if($returnEvent)$unknown[$key]=$left;else $shipping[$key]=$left;}
 }
 foreach($allocated as $key=>$qty)if(!isset($out[$key])){if($received)$returned[$key]=$qty;else $returning[$key]=$qty;}
 return ['delivered'=>$delivered,'returning'=>$returning,'received'=>$returned,'shipping'=>$shipping,'unknown'=>$unknown,'items'=>$allocated,'error'=>$error,'returnEvent'=>$returnEvent,'state'=>$received?'received':'returning'];
}
