<?php
declare(strict_types=1);
require_once __DIR__.'/report-dates.php';
function receipt_xml(string $text): SimpleXMLElement {
 if(stripos($text,'<!DOCTYPE')!==false||stripos($text,'<!ENTITY')!==false)throw new RuntimeException('File XML không hợp lệ.',400);
 $old=libxml_use_internal_errors(true);try{$x=simplexml_load_string($text,SimpleXMLElement::class,LIBXML_NONET|LIBXML_COMPACT);if(!$x)throw new RuntimeException('Không đọc được nội dung Excel.',400);return $x;}finally{libxml_clear_errors();libxml_use_internal_errors($old);}
}
function receipt_zip_part(ZipArchive $zip,string $path,int $max=20000000): string {
 $stat=$zip->statName($path);if(!$stat||$stat['size']>$max)throw new RuntimeException('Thành phần Excel thiếu hoặc quá lớn.',400);$text=$zip->getFromName($path);if($text===false)throw new RuntimeException('Không đọc được file Excel.',400);return $text;
}
function receipt_file_tables(string $path,string $extension): array {
 if($extension==='csv'){$f=fopen($path,'rb');$first=fgets($f);rewind($f);$separator=substr_count((string)$first,';')>substr_count((string)$first,',')?';':',';$rows=[];$cells=0;while(($row=fgetcsv($f,200000,$separator))!==false){$cells+=count($row);if(count($rows)>=50000||count($row)>512||$cells>500000){fclose($f);throw new RuntimeException('File tối đa 50.000 dòng, 512 cột và 500.000 ô.',413);}$rows[]=$row;}fclose($f);return ['CSV'=>$rows];}
 if($extension!=='xlsx')throw new RuntimeException('Chọn file Excel .xlsx hoặc CSV. Với .xls cũ, lưu thành .xlsx trước khi tải.',400);
 if(!class_exists('ZipArchive'))throw new RuntimeException('Máy chủ chưa có bộ đọc Excel.',500);$zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('File Excel không hợp lệ hoặc được đặt mật khẩu.',400);
 try{
  if($zip->numFiles>3000)throw new RuntimeException('File Excel có quá nhiều thành phần.',413);$total=0;for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$total+=$s['size'];if($total>60000000)throw new RuntimeException('Nội dung Excel vượt 60 MB sau giải nén.',413);}
  $strings=[];if($zip->locateName('xl/sharedStrings.xml')!==false){$xml=receipt_xml(receipt_zip_part($zip,'xl/sharedStrings.xml'));foreach($xml->xpath('//*[local-name()="si"]') as $si){$value='';foreach($si->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;$strings[]=$value;if(count($strings)>500000)throw new RuntimeException('Quá nhiều ô văn bản.',413);}}
  $rels=receipt_xml(receipt_zip_part($zip,'xl/_rels/workbook.xml.rels'));$targets=[];foreach($rels->xpath('//*[local-name()="Relationship"]') as $r)if((string)$r['TargetMode']!=='External'){$target=(string)$r['Target'];if(str_contains($target,'..')||str_contains($target,'\\')||str_contains($target,':'))continue;$targets[(string)$r['Id']]=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;}
  $book=receipt_xml(receipt_zip_part($zip,'xl/workbook.xml'));$tables=[];$cellCount=0;foreach($book->xpath('//*[local-name()="sheet"]') as $sheet){if(count($tables)>=50)throw new RuntimeException('Mỗi file tối đa 50 sheet.',413);$attrs=$sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');$target=$targets[(string)$attrs['id']]??null;if(!$target)continue;$xml=receipt_xml(receipt_zip_part($zip,$target));$rows=[];foreach($xml->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row){$r=(int)$row['r'];if($r<1||$r>50000)throw new RuntimeException('File tối đa 50.000 dòng mỗi sheet.',413);$values=[];foreach($row->xpath('./*[local-name()="c"]') as $c){if(++$cellCount>500000)throw new RuntimeException('File tối đa 500.000 ô.',413);$ref=(string)$c['r'];if(!preg_match('/^([A-Z]{1,3})\d+$/D',$ref,$m))continue;$col=0;foreach(str_split($m[1]) as $letter)$col=$col*26+ord($letter)-64;if($col>512)continue;$v=$c->xpath('./*[local-name()="v"]');$value=isset($v[0])?(string)$v[0]:'';$type=(string)$c['t'];if($type==='s')$value=$strings[(int)$value]??'';elseif($type==='inlineStr'){$value='';foreach($c->xpath('.//*[local-name()="t"]') as $t)$value.=(string)$t;}$values[$col-1]=$value;}$rows[$r-1]=$values;}$tables[(string)$sheet['name']]=$rows;}
  return $tables;
 }finally{$zip->close();}
}
function receipt_extract(array $tables): array {
 $entries=[];foreach($tables as $sheet=>$rows){$columns=[];$headerRow=-1;foreach($rows as $r=>$cells){if((int)$r>30)break;foreach($cells as $c=>$value){$h=report_fold(trim((string)$value,"\xEF\xBB\xBF \t\r\n"));if(in_array($h,['MA VAN DON','VAN DON','MA DON VAN CHUYEN','TRACKING','TRACKING NUMBER','TRACKING NO','BILL CODE','MA BUU GUI'],true)){$columns[]=$c;$headerRow=(int)$r;}}if($columns)break;}
  foreach($rows as $r=>$cells){if((int)$r<=$headerRow)continue;$found=[];foreach($cells as $c=>$value){if($columns&&!in_array($c,$columns,true))continue;$value=trim((string)$value);if($value==='')continue;if($columns){$codes=preg_split('/[\s,;]+/u',$value);foreach($codes as $code)if($code!=='')$found[]=$code;}elseif(preg_match('/^\d{11,20}$/D',$value))$found[]=$value;}
   foreach(array_unique($found) as $code){$entries[]=['original'=>$code,'tracking'=>$code,'sheet'=>$sheet,'row'=>(int)$r+1];if(count($entries)>10000)throw new RuntimeException('Mỗi file tối đa 10.000 mã vận đơn.',413);}
  }
 }if(!$entries)throw new RuntimeException('Không tìm thấy mã vận đơn. Đặt tên cột là Mã vận đơn và kiểm tra các mã trong file.',400);return $entries;
}
