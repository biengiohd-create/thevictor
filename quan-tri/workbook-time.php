<?php
declare(strict_types=1);
function wb_time_column(array $sheet): ?int {foreach(($sheet['cellData'][0]??[]) as $c=>$cell)if(mb_strtoupper(trim((string)($cell['v']??'')),'UTF-8')==='THỜI GIAN')return (int)$c;return null;}
function wb_time_apply(PDO $db,array &$snapshot,?array $old): array {
 $db->exec('CREATE TABLE IF NOT EXISTS workbook_row_times(id TEXT PRIMARY KEY,created_at TEXT NOT NULL)');$patches=[];$seen=[];$find=$db->prepare('SELECT created_at FROM workbook_row_times WHERE id=?');$insert=$db->prepare('INSERT INTO workbook_row_times(id,created_at) VALUES(?,?)');
 foreach($snapshot['sheetOrder'] as $sid){$sheet=&$snapshot['sheets'][$sid];$col=wb_time_column($sheet);if($col===null)continue;if($col!==0)throw new RuntimeException('THỜI GIAN là cột cố định đầu bảng.',403);$before=$old['sheets'][$sid]??null;$legacy=$before&&wb_time_column($before)===null;
  foreach(($sheet['cellData']??[]) as $r=>$cells){if((int)$r===0)continue;$occupied=false;foreach($cells as $c=>$cell){$header=mb_strtoupper(trim((string)($sheet['cellData'][0][$c]['v']??'')),'UTF-8');if(in_array($header,['STT','THỜI GIAN'],true))continue;if(is_array($cell)&&(!empty($cell['f'])||!empty($cell['p'])||(isset($cell['v'])&&$cell['v']!==''))){$occupied=true;break;}}if(!$occupied)continue;
   $cell=$cells[0]??[];$id=$cell['custom']['victorRowId']??'';if(!is_string($id)||!preg_match('/^[a-f0-9-]{36}$/D',$id)||isset($seen[$id]))$id=substr(bin2hex(random_bytes(18)),0,36);$seen[$id]=true;$find->execute([$id]);$time=$find->fetchColumn();if($time===false){$time=gmdate('c');if($legacy&&!empty($before['cellData'][$r]))$time='';$insert->execute([$id,$time]);}$value=$time===''?'':(new DateTimeImmutable($time))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('d/m/Y H:i:s');$fixed=['v'=>$value,'t'=>1,'custom'=>['victorRowId'=>$id,'victorTime'=>true]];if(($cell['s']??null)!==null)$fixed['s']=$cell['s'];$sheet['cellData'][$r][0]=$fixed;$patches[]=['sheet'=>$sid,'row'=>(int)$r,'id'=>$cell['custom']['victorRowId']??null,'cell'=>$fixed];
  }unset($sheet);
 }return $patches;
}
