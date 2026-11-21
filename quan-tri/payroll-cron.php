<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require is_file(__DIR__.'/lib.php')?__DIR__.'/lib.php':'/home/twjmlulv/public_html/quan-tri/lib.php';$_SESSION=[];$month=payroll_due_month();if($month===null)exit;
try{$result=payroll_generate($month,'Tự động tính ngày 20 cho tháng công trước.',true);if(!$result['already'])echo gmdate('c').' PAYROLL '.$month.' RUN '.$result['id'].PHP_EOL;}catch(Throwable $e){fwrite(STDERR,gmdate('c').' PAYROLL FAILED: '.$e->getMessage().PHP_EOL);exit(1);}
