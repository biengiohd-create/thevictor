<?php
declare(strict_types=1);
// Snapshots are validated when saved. Send their stored JSON without expanding the full workbook in PHP.
function wb_stored_response(array $row,array $user): string {
 $meta=json_encode(['version'=>(int)$row['version'],'actor'=>$row['actor'],'updatedAt'=>$row['updated_at'],'user'=>$user],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
 return '{"snapshot":'.$row['snapshot'].','.substr($meta,1);
}
