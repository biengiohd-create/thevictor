<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
ini_set('memory_limit','1024M');
require __DIR__.'/lib.php';require __DIR__.'/workbook-lib.php';
$file=$argv[1]??'';$mode=$argv[2]??'check';
if(!is_file($file)||!in_array($mode,['check','apply'],true))throw new RuntimeException('Usage: import-google-cli.php private-json check|apply');
$incoming=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
if(count($incoming['sheetOrder']??[])!==543)throw new RuntimeException('Expected verified 543-sheet import.');
$db=admin_db();wb_schema($db);$current=wb_current($db);if(!$current)throw new RuntimeException('No existing workbook.');
$snapshot=$current['snapshot'];$names=array_column($snapshot['sheets'],'name');$added=[];$skipped=[];
foreach($incoming['sheetOrder'] as $id){if(isset($snapshot['sheets'][$id])){$skipped[]=$id;continue;}$sheet=$incoming['sheets'][$id];if(in_array($sheet['name'],$names,true))throw new RuntimeException('Existing sheet name collision: '.$sheet['name']);$names[]=$sheet['name'];$snapshot['sheets'][$id]=$sheet;$added[]=$id;}
$snapshot['sheetOrder']=array_merge($snapshot['sheetOrder'],$added);$snapshot['styles']=array_merge($snapshot['styles']??[],$incoming['styles']);wb_validate($snapshot);
$bytes=strlen(json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));if($bytes>40000000)throw new RuntimeException('Import exceeds working headroom.');
echo json_encode(['mode'=>$mode,'sourceSheets'=>543,'added'=>count($added),'alreadyImported'=>count($skipped),'totalSheets'=>count($snapshot['sheetOrder']),'bytesBeforeTimestamps'=>$bytes,'sourceSHA256'=>hash_file('sha256',$file)],JSON_UNESCAPED_UNICODE).PHP_EOL;
if($mode==='apply'&&$added){$backup=admin_private().'/before-google-import-'.gmdate('Ymd-His').'.sqlite';$source=new SQLite3(admin_private().'/orders.sqlite');if(!$source->backup(new SQLite3($backup)))throw new RuntimeException('Backup failed');$saved=wb_store($db,$snapshot,$current['sources'],$current['version'],'admin · Nhập PHÚC 2026',null,[['command'=>'import.google-sheets.543','at'=>(int)(microtime(true)*1000)]]);echo 'IMPORTED '.count($added).' SHEETS VERSION '.$saved['version'].PHP_EOL;echo 'BACKUP '.$backup.PHP_EOL;}

