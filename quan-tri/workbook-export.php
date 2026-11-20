<?php
declare(strict_types=1);
function wb_export_rows(array $snapshot,string $id): array {
 $sheet=$snapshot['sheets'][$id]??null;if(!$sheet)throw new RuntimeException('Không tìm thấy trang tính.',404);$cells=$sheet['cellData']??[];$lastRow=0;$lastCol=0;
 foreach($cells as $r=>$row)foreach($row as $c=>$cell)if(is_array($cell)&&(isset($cell['v'])||isset($cell['f'])||isset($cell['p']))){$lastRow=max($lastRow,(int)$r);$lastCol=max($lastCol,(int)$c);}
 if(($lastRow+1)*($lastCol+1)>150000)throw new RuntimeException('Vùng CSV vượt quá 150.000 ô.',400);$out=[];
 for($r=0;$r<=$lastRow;$r++){$row=[];for($c=0;$c<=$lastCol;$c++){$cell=$cells[$r][$c]??[];$value=(string)($cell['v']??rtrim($cell['p']['body']['dataStream']??'',"\r\n"));if(preg_match('/^[\s]*[=+@-]/u',$value))$value="'".$value;$row[]=$value;}$out[]=$row;}return $out;
}
function wb_export_csv(array $snapshot,string $id): never {
 $rows=wb_export_rows($snapshot,$id);header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="thevictor-bang-don.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");foreach($rows as $row)fputcsv($out,$row);fclose($out);exit;
}
