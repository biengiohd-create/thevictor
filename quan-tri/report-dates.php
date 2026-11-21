<?php
declare(strict_types=1);
function report_range(array $input,?DateTimeImmutable $now=null): array {
 $now=($now??new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->setTime(0,0);$preset=(string)($input['period']??'today');$from=$now;$to=$now;
 switch($preset){case 'today':break;case 'yesterday':$from=$to=$now->modify('-1 day');break;case 'this_week':$from=$now->modify('-'.((int)$now->format('N')-1).' days');$to=$from->modify('+6 days');break;case 'last_week':$from=$now->modify('-'.((int)$now->format('N')+6).' days');$to=$from->modify('+6 days');break;case 'this_month':$from=$now->modify('first day of this month');$to=$now->modify('last day of this month');break;case 'last_month':$from=$now->modify('first day of last month');$to=$now->modify('last day of last month');break;case 'custom':foreach(['from','to'] as $key){$raw=$input[$key]??'';if(!is_string($raw))throw new RuntimeException('Ngày không hợp lệ.',400);$d=DateTimeImmutable::createFromFormat('!Y-m-d',$raw,$now->getTimezone());if(!$d||$d->format('Y-m-d')!==$raw)throw new RuntimeException('Vui lòng chọn đủ ngày bắt đầu và kết thúc.',400);$$key=$d;}break;default:throw new RuntimeException('Mốc thời gian không hợp lệ.',400);}
 if($from>$to)throw new RuntimeException('Ngày bắt đầu phải trước ngày kết thúc.',400);return ['period'=>$preset,'from'=>$from->format('Y-m-d'),'to'=>$to->format('Y-m-d')];
}
function report_sheet_info(string $name,?int $year=null): array {
 $gender=preg_match('/(?:^|[^\pL])(NAM)(?:$|[^\pL])/iu',$name)?'Nam':(preg_match('/(?:^|[^\pL])(NỮ|NU)(?:$|[^\pL])/iu',$name)?'Nữ':'');$day='';
 if(preg_match('/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/u',$name,$m)){$y=(int)$m[1];$mo=(int)$m[2];$d=(int)$m[3];}elseif(preg_match('/(\d{1,2})[-\/.](\d{1,2})[-\/.](20\d{2})/u',$name,$m)){$d=(int)$m[1];$mo=(int)$m[2];$y=(int)$m[3];}elseif(preg_match('/(?<!\d)(\d{1,2})\/(\d{1,2})(?![\d\/])/u',$name,$m)){$d=(int)$m[1];$mo=(int)$m[2];$y=$year??(int)(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y');}else return ['gender'=>$gender,'date'=>''];
 if(checkdate($mo,$d,$y))$day=sprintf('%04d-%02d-%02d',$y,$mo,$d);return ['gender'=>$gender,'date'=>$day];
}
