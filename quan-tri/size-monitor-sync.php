<?php
declare(strict_types=1);if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('memory_limit','768M');require __DIR__.'/lib.php';require __DIR__.'/size-insights-lib.php';
$db=admin_db();size_monitor_schema($db);$board=board_rows($db,board_catalog($db));if(in_array('--start',$argv,true))size_monitor_start($db,$board['rows']);
try{$file=admin_private().'/size-monitor-source.json';if(!is_file($file))throw new RuntimeException('Chưa cấu hình nguồn bảng size');$config=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$result=size_monitor_source_sync($db,$config,$board['rows']);echo json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;
}catch(Throwable $e){$db->prepare('UPDATE size_monitor_state SET source_error=? WHERE id=1')->execute([$e->getMessage()]);fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
