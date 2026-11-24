<?php
declare(strict_types=1);
function stock_status(string $text):string {return trim(preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u',' ',mb_strtoupper($text,'UTF-8')));}
function stock_delivery_money($value):?int {
 if(is_int($value)||is_float($value))return is_finite((float)$value)&&$value>=0?(int)round($value):null;
 $s=preg_replace('/[\s\x{00A0}₫đĐ]/u','',trim((string)$value));if($s===''||!preg_match('/^(?:\d+|\d{1,3}(?:[.,]\d{3})+)$/D',$s))return null;return (int)str_replace(['.',','],'',$s);
}
function stock_full_delivery(string $status):bool {
 $s=stock_status($status);
 // Money annotations are common in this source. OK HOÀN is a return marker, not delivery.
 return (bool)preg_match('/^OK(?:\s+[0-9][0-9.,]*(?:\s*(?:K|TRIỆU|TRIEU|TR|Đ|D|VND))?)?[.!]?$/u',$s);
}
function stock_kept_items(array $pool,string $text):array {
 $text=stock_status($text);
 $kept=[];preg_match_all('/(?:\bOK|NHẬN|GIỮ)\s*[:,-]?\s*([A-Z]{1,8}\d{1,6})(?![\p{L}\d])(?:\s*(?:SIZE\s*)?(4XL|3XL|2XL|XXXL|XXL|XS|XL|S|M|L)(?![\p{L}\d]))?(?:\s*[×x*]\s*(\d+))?/iu',$text,$matches,PREG_SET_ORDER);
 foreach($matches as $m){$sku=strtoupper($m[1]);$size=isset($m[2])?strtr(strtoupper($m[2]),['XXXL'=>'3XL','XXL'=>'2XL']):'';$keys=array_keys(array_filter($pool,static fn($qty,$key)=>str_starts_with($key,$sku.'|')&&($size===''||$key===$sku.'|'.$size),ARRAY_FILTER_USE_BOTH));if(count($keys)!==1)continue;$key=$keys[0];$qty=isset($m[3])&&$m[3]!==''?(int)$m[3]:$pool[$key];if($qty>0&&$qty<=$pool[$key])$kept[$key]=$qty;}
 if(!$kept&&count($pool)===1&&preg_match('/^(?:NHẬN|GIỮ|OK)\s+(\d+)(?:\s+(?:QUẦN|ÁO|CHIẾC))?$/u',$text,$n)){ $key=array_key_first($pool);if((int)$n[1]>0&&(int)$n[1]<=$pool[$key])$kept[$key]=(int)$n[1];}
 return $kept;
}
function stock_resolve_outcome(array $row,?array $entry,bool $received,?array $manual):array {
 $out=stock_item_map($row['items']);$status=stock_status((string)($row['status']??''));$kept=stock_kept_items($out,$status);if(!$kept&&$entry)$kept=stock_kept_items($out,(string)($entry['raw']??''));$partial=false;
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
  if(!$returnEvent&&stock_full_delivery($status))$accepted=$qty;
  if($returnEvent&&$error==='')$accepted=$qty-$returnQty;
  if($accepted)$delivered[$key]=$accepted;
  if($returnQty){if($received)$returned[$key]=$returnQty;else $returning[$key]=$returnQty;}
  $left=$qty-$accepted-$returnQty;if($left>0){if($returnEvent)$unknown[$key]=$left;else $shipping[$key]=$left;}
 }
 foreach($allocated as $key=>$qty)if(!isset($out[$key])){if($received)$returned[$key]=$qty;else $returning[$key]=$qty;}
 return ['delivered'=>$delivered,'returning'=>$returning,'received'=>$returned,'shipping'=>$shipping,'unknown'=>$unknown,'items'=>$allocated,'error'=>$error,'returnEvent'=>$returnEvent,'state'=>$received?'received':'returning'];
}
