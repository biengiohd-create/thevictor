<?php
declare(strict_types=1);
require_once __DIR__.'/board-report.php';
function board_quality_allowed(): bool {$u=team_identity();return $u&&($u['owner']||($u['group']??'')==='manager');}
function board_quality_require(): void {if(!board_quality_allowed())throw new RuntimeException('Chỉ quản lý và quản trị viên được xem cảnh báo sản phẩm.',403);}
function board_quality(PDO $db): array {
 board_quality_require();$catalog=board_catalog($db);$version=(int)$db->query('SELECT version FROM staff_workbook WHERE id=1')->fetchColumn();$key=hash('sha256','quality-v3|'.$version.'|'.json_encode($catalog).'|'.filemtime(__DIR__.'/board-products.php'));$file=admin_private().'/board-quality-cache.json';$cached=is_file($file)?json_decode(file_get_contents($file),true):null;if(($cached['key']??'')===$key)return $cached['data'];
 $board=board_rows($db,$catalog);$data=['version'=>$board['version'],'issues'=>$board['issues'],'exchanges'=>array_values(array_filter($board['rows'],static fn($r)=>$r['exchange']))];$tmp=tempnam(admin_private(),'quality-');file_put_contents($tmp,json_encode(['key'=>$key,'data'=>$data],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));chmod($tmp,0600);rename($tmp,$file);return $data;
}
