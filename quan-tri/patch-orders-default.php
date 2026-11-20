<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$path=$argv[1]??'';$source=file_get_contents($path);
$old="$"."unknown?'pending_quote':'pending_confirmation'";
if(substr_count($source,$old)!==1)throw new RuntimeException('Unexpected website order default; refusing patch.');
if(file_put_contents($path,str_replace($old,"'pending_confirmation'",$source))===false)exit(1);
echo "Website order default: Chờ xác nhận\n";
