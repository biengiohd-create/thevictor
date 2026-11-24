<?php
declare(strict_types=1);
function report_range(array $input,?DateTimeImmutable $now=null): array {
 $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0,0);$preset=(string)($input['period']??'today');$from=$now;$to=$now;
 switch($preset){case 'today':break;case 'yesterday':$from=$to=$now->modify('-1 day');break;case 'day_before_yesterday':$from=$to=$now->modify('-2 days');break;case 'day_after_tomorrow':$from=$to=$now->modify('+2 days');break;case 'this_week':$from=$now->modify('-'.((int)$now->format('N')-1).' days');$to=$from->modify('+6 days');break;case 'last_week':$from=$now->modify('-'.((int)$now->format('N')+6).' days');$to=$from->modify('+6 days');break;case 'this_month':$from=$now->modify('first day of this month');$to=$now->modify('last day of this month');break;case 'last_month':$from=$now->modify('first day of last month');$to=$now->modify('last day of last month');break;case 'custom':foreach(['from','to'] as $key){$raw=$input[$key]??'';if(!is_string($raw))throw new RuntimeException('Ngày không hợp lệ.',400);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$raw,$now->getTimezone());if(!$d||$d->format('Y-m-d')!==$raw)throw new RuntimeException('Vui lòng chọn đủ ngày bắt đầu và kết thúc.',400);$$key=$d;}break;default:throw new RuntimeException('Mốc thời gian không hợp lệ.',400);}
 if($from>$to)throw new RuntimeException('Ngày bắt đầu phải trước ngày kết thúc.',400);return ['period'=>$preset,'from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d')];
}
function report_fold(string $text): string {
 $text=mb_strtoupper($text,'UTF-8');$text=preg_replace('/\p{M}+/u','',$text);$text=strtr($text,['Ữ'=>'U','Ừ'=>'U','Ứ'=>'U','Ử'=>'U','Ự'=>'U','Ư'=>'U','Ũ'=>'U','Ú'=>'U','Ù'=>'U','Ụ'=>'U','Ủ'=>'U','Ễ'=>'E','Ề'=>'E','Ế'=>'E','Ể'=>'E','Ệ'=>'E','Ê'=>'E','Ẽ'=>'E','É'=>'E','È'=>'E','Ẹ'=>'E','Ẻ'=>'E','Ã'=>'A','Á'=>'A','À'=>'A','Ạ'=>'A','Ả'=>'A','Ă'=>'A','Ằ'=>'A','Ắ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A','Â'=>'A','Ầ'=>'A','Ấ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A','Õ'=>'O','Ó'=>'O','Ò'=>'O','Ọ'=>'O','Ỏ'=>'O','Ô'=>'O','Ồ'=>'O','Ố'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O','Ơ'=>'O','Ờ'=>'O','Ớ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O','Ĩ'=>'I','Í'=>'I','Ì'=>'I','Ị'=>'I','Ỉ'=>'I','Ỹ'=>'Y','Ý'=>'Y','Ỳ'=>'Y','Ỵ'=>'Y','Ỷ'=>'Y','Đ'=>'D']);return trim(preg_replace('/\s+/u',' ',$text));
}
function report_sheet_info(string $name,?int $year=null): array {
 $name=report_fold($name);
 $gender=preg_match('/(?:^|[^\pL])(NAM)(?:$|[^\pL])/iu',$name)?'Nam':(preg_match('/(?:^|[^\pL])(NỮ|NU)(?:$|[^\pL])/iu',$name)?'Nữ':'');$day='';
 if(preg_match('/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/u',$name,$m)){$y=(int)$m[1];$mo=(int)$m[2];$d=(int)$m[3];}elseif(preg_match('/(\d{1,2})[-\/.](\d{1,2})[-\/.](20\d{2})/u',$name,$m)){$d=(int)$m[1];$mo=(int)$m[2];$y=(int)$m[3];}elseif(preg_match('/(?<!\d)(\d{1,2})\/(\d{1,2})(?![\d\/])/u',$name,$m)){$d=(int)$m[1];$mo=(int)$m[2];$y=$year??(int)(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y');}else return ['gender'=>$gender,'date'=>''];
 if(checkdate($mo,$d,$y))$day=sprintf('%04d-%02d-%02d',$y,$mo,$d);return ['gender'=>$gender,'date'=>$day];
}
