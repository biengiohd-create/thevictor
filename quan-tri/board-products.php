<?php
declare(strict_types=1);
require_once __DIR__.'/report-dates.php';
function board_size(string $size): string {return str_replace(['XXXL','XXL',' '],['3XL','2XL',''],report_fold($size));}
function board_parse_side(string $text,array $catalog): array {
 $out=[];$errors=[];$parts=preg_split('/\s*\+\s*|[;\r\n]+/u',mb_strtoupper(trim($text),'UTF-8'),-1,PREG_SPLIT_NO_EMPTY);if(!$parts)return ['items'=>[],'errors'=>['Chưa có sản phẩm']];
 foreach($parts as $part){$found=[];foreach($catalog as $sku=>$p)if(preg_match('/(?<![\pL\pN])'.preg_quote((string)$sku,'/').'(?![\pL\pN])/u',$part))$found[]=(string)$sku;
  if(!$found){$plain=report_fold($part);foreach($catalog as $sku=>$p){$name=report_fold((string)$p['name']);if(mb_strlen($name)>5&&str_contains($plain,$name))$found[]=(string)$sku;}}
  if(count($found)!==1){$hint=[];if(preg_match('/(?<![A-Z0-9])([A-Z]{1,8}\d{1,6})(?![A-Z0-9])/u',$part,$token)){foreach(array_keys($catalog) as $candidate){$distance=levenshtein($token[1],(string)$candidate);if($distance<=1)$hint[]=(string)$candidate;}}$errors[]='Mã sản phẩm chưa khớp danh mục website: '.$part.($hint?' · Kiểm tra '.implode(' / ',array_slice($hint,0,3)):'');continue;}
  $sku=$found[0];$without=preg_replace('/(?<![\pL\pN])'.preg_quote($sku,'/').'(?![\pL\pN])/u',' ',$part);$size='';
  if(preg_match('/(?:SIZE\s*[:\-]?\s*)?(?<![\pL\pN])(ONE\s*SIZE|FREE\s*SIZE|FREESIZE|XS|S|M|L|XL|[2-9]XL|XXXL|XXL)(?![\pL\pN])/u',$without,$m))$size=board_size($m[1]);elseif(preg_match('/SIZE\s*[:\-]?\s*(\d{2})(?!\d)/u',$without,$m))$size=$m[1];elseif(preg_match('/^\s*(\d{2})\s*$/u',$without,$m))$size=$m[1];
  if($size===''){$errors[]='Thiếu hoặc sai size: '.$part;continue;}$qty=1;$amounts=[];if(preg_match('/[×X]\s*(\d+)\b/u',$without,$m))$amounts[]=(int)$m[1];if(preg_match('/^\s*(\d+)\s+'.preg_quote($sku,'/').'\b/u',$part,$m))$amounts[]=(int)$m[1];if(preg_match('/\(\s*(\d+)\s*(?:CHIẾC|CÁI|SP)\s*\)/u',$part,$m))$amounts[]=(int)$m[1];$amounts=array_unique($amounts);if(count($amounts)>1){$errors[]='Số lượng không thống nhất: '.$part;continue;}if($amounts)$qty=reset($amounts);if($qty<1||$qty>10000){$errors[]='Số lượng không hợp lệ: '.$part;continue;}
  $sizes=array_map('board_size',$catalog[$sku]['sizes']??[]);if($sizes&&!in_array($size,$sizes,true))$errors[]=$sku.' không có size '.$size.' trên website ('.implode(', ',$sizes).')';
  $out[]=['sku'=>$sku,'size'=>$size,'qty'=>$qty,'name'=>$catalog[$sku]['name'],'gender'=>$catalog[$sku]['gender']];
 }return ['items'=>$out,'errors'=>array_values(array_unique($errors))];
}
function board_lines(string $text,array $catalog): array {
 $sides=preg_split('/\s*(?:=\s*>|-\s*>|→|⇒)\s*/u',trim($text));if(count($sides)===1)return board_parse_side($text,$catalog)+['incoming'=>[],'exchange'=>false];
 if(count($sides)!==2||trim($sides[0])===''||trim($sides[1])==='')return ['items'=>[],'incoming'=>[],'exchange'=>true,'errors'=>['Đơn đổi cần đúng dạng sản phẩm thu về => sản phẩm gửi đi: '.$text]];
 $incoming=board_parse_side($sides[0],$catalog);$outgoing=board_parse_side($sides[1],$catalog);return ['items'=>$outgoing['items'],'incoming'=>$incoming['items'],'exchange'=>true,'errors'=>array_merge(array_map(static fn($e)=>'Hàng thu về: '.$e,$incoming['errors']),array_map(static fn($e)=>'Hàng gửi đi: '.$e,$outgoing['errors']))];
}
function board_gender_errors(array $parsed,string $sheetGender): array {
 if($sheetGender==='')return [];$errors=[];foreach(['items'=>'Gửi đi','incoming'=>'Thu về'] as $key=>$label)foreach($parsed[$key]??[] as $item)if($item['gender']!==''&&$item['gender']!==$sheetGender)$errors[]=$label.': '.$item['sku'].' — '.$item['name'].' là sản phẩm '.$item['gender'].' trên website, nhưng nằm trong sheet '.$sheetGender;return array_values(array_unique($errors));
}
