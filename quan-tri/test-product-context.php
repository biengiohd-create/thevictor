<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/board-products.php';require __DIR__.'/stock-flow.php';
function context_check($v,$label){if(!$v)throw new RuntimeException('FAIL '.$label);echo 'PASS '.$label.PHP_EOL;}
$catalog=['E1'=>['name'=>'E1','gender'=>'Nam','sizes'=>['S','M','L']], 'DTQ26'=>['name'=>'DTQ26','gender'=>'Nam','sizes'=>['M','L','XL']], 'G01'=>['name'=>'G01','gender'=>'Nữ','sizes'=>['S','M']], 'KBH77'=>['name'=>'KBH77','gender'=>'Nam','sizes'=>['M']], 'KGH90'=>['name'=>'KGH90','gender'=>'Nam','sizes'=>['M']]];
$p=board_lines('E1L',$catalog);context_check(!$p['items']&&str_contains(implode(' ',$p['errors']),'ghi liền'),'joined SKU and size warns; never silently corrected');
context_check(board_lines_in_sheet('KGH77 M',$catalog,'TELE KBH77')['errors']!==[],'a confirmed typo on one order creates no global alias');
context_check(board_lines_in_sheet('E1L',$catalog,'TELE E1')['errors']!==[],'sheet context cannot hide malformed product field');
$p=board_lines_in_sheet('M + L + XL',$catalog,'DTQ26 M + L + XL');context_check(!$p['errors']&&count($p['items'])===3&&$p['items'][0]['sku']==='DTQ26','one explicit sheet SKU supplies size-only product fields');
context_check(board_lines_in_sheet('L',$catalog,'DTQ26 + E1')['errors']!==[],'ambiguous sheet codes never guessed');
context_check(report_sheet_info('TELE G01')['date']===''&&report_sheet_info('DTQ26 M + L + XL')['date']==='','product sheets remain undated and excluded by report date filter');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$row=['created'=>true,'valid'=>true,'returned'=>false,'status'=>'','tracking'=>'12345','sheetId'=>'tele','sheet'=>'TELE G01','date'=>'','row'=>2,'text'=>'G01 M','items'=>[['sku'=>'G01','size'=>'M','qty'=>1]],'incoming'=>[],'measurements'=>'','sizeCharts'=>[],'money'=>100];$flow=stock_flow($db,[$row]);context_check($flow['totals']['G01|M']['sold']===1,'undated created order still deducts inventory');$row['status']='HH';$flow=stock_flow($db,[$row]);context_check(empty($flow['totals']['G01|M']['sold'])&&$flow['totals']['G01|M']['outOfStock']===1,'HH order is shortage only and never deducts stock');
echo "PRODUCT_CONTEXT_TESTS_COMPLETE\n";
