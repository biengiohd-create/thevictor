<?php
declare(strict_types=1);
function product_modes_schema(PDO $db):void {$db->exec("CREATE TABLE IF NOT EXISTS product_modes(sku TEXT NOT NULL,effective TEXT NOT NULL,kind TEXT NOT NULL,gender TEXT NOT NULL,actor TEXT NOT NULL,updated_at TEXT NOT NULL,PRIMARY KEY(sku,effective))");}
function product_mode_at(array $product,string $day):string {foreach($product['modes']??[] as $m)if($m['effective']<=$day)return $m['kind'];return $product['defaultKind']??'normal';}
function product_sku_kind(array $catalog,string $sku,string $day):string {
 if($sku==='E1234'){$types=[];foreach(['E1','E2','E3','E4'] as $s)$types[]=product_mode_at($catalog[$s]??[],$day);return count(array_unique($types))===1?$types[0]:'mixed';}
 return isset($catalog[$sku])?product_mode_at($catalog[$sku],$day):'unclassified';
}
function product_row_kind(array $catalog,array $row):string {
 if(empty($row['valid'])||!$row['items'])return 'unclassified';$types=[];foreach($row['items'] as $i)$types[]=product_sku_kind($catalog,$i['sku'],$row['date']?:'9999-12-31');$types=array_unique($types);return count($types)===1?reset($types):'mixed';
}
function product_modes_extend(PDO $db,array $catalog):array {
 product_modes_schema($db);$tables=array_column($db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_ASSOC),'name');
 $revision=in_array('staff_workbook',$tables,true)?(string)$db->query('SELECT version FROM staff_workbook WHERE id=1')->fetchColumn():'0';
 $key=spl_object_id($db).'|'.$revision.'|'.hash('sha256',json_encode(array_keys($catalog))).'|'.$db->query('SELECT count(*)||":"||coalesce(max(updated_at),"") FROM product_modes')->fetchColumn();
 static $cache=[];if(isset($cache[$key]))return $cache[$key];$found=[];
 if(in_array('staff_workbook',$tables,true)){$raw=$db->query('SELECT snapshot FROM staff_workbook WHERE id=1')->fetchColumn();$snapshot=$raw?json_decode($raw,true):null;unset($raw);
  foreach($snapshot['sheets']??[] as $sheet){$col=null;foreach($sheet['cellData'][0]??[] as $c=>$h)if(in_array(report_fold((string)($h['v']??'')),['SAN PHAM / SIZE','SAN PHAM'],true))$col=$c;if($col===null)continue;$gender=report_sheet_info((string)$sheet['name'])['gender'];
   foreach($sheet['cellData']??[] as $r=>$cells){if((int)$r<1)continue;$text=mb_strtoupper((string)($cells[$col]['v']??''),'UTF-8');preg_match_all('/(?<![A-Z0-9])([A-Z]{1,8}\d{1,6})(?![A-Z0-9])\s*(?:SIZE\s*)?(?:[2-9]XL|XXXL|XXL|XS|XL|S|M|L|ONE\s*SIZE|FREE\s*SIZE)(?![A-Z0-9])/u',$text,$ms);foreach($ms[1] as $sku)if(!isset($catalog[$sku])&&$sku!=='E1234'){$found[$sku]??=[];if($gender!=='')$found[$sku][$gender]=true;}}
  }unset($snapshot);
 }
 if(in_array('marketing_rows',$tables,true))foreach($db->query('SELECT DISTINCT sku,gender FROM marketing_rows WHERE active=1') as $r){$sku=trim($r['sku']);if($sku!=='E1234'&&!isset($catalog[$sku])&&preg_match('/^[A-Z]{1,8}\d{1,6}$/D',$sku)){$found[$sku]??=[];if(in_array($r['gender'],['Nam','Nữ'],true))$found[$sku][$r['gender']]=true;}}
 $insert=$db->prepare('INSERT OR IGNORE INTO product_modes VALUES(?,"0001-01-01","test",?,"auto",?)');foreach($found as $sku=>$g)$insert->execute([$sku,count($g)===1?array_key_first($g):'',gmdate('c')]);
 foreach($db->query('SELECT * FROM product_modes ORDER BY sku,effective DESC') as $m){$sku=$m['sku'];if(!isset($catalog[$sku]))$catalog[$sku]=['sku'=>$sku,'name'=>$sku,'gender'=>$m['gender'],'defaultKind'=>'test'];$catalog[$sku]['modes'][]=$m;if($m['gender']!==''&&empty($catalog[$sku]['modeGenderSet'])){$catalog[$sku]['gender']=$m['gender'];$catalog[$sku]['modeGenderSet']=true;}}
 $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');foreach($catalog as &$p){$p['kind']=product_mode_at($p,$today);$p['isTest']=$p['kind']==='test';}unset($p);ksort($catalog);$cache[$key]=$catalog;return $catalog;
}
function product_mode_save(PDO $db,array $input,array $user):array {
 team_require_owner();product_modes_schema($db);$sku=strtoupper(trim((string)($input['sku']??'')));$kind=(string)($input['kind']??'');$gender=(string)($input['gender']??'');$day=(string)($input['effective']??'');
 if(!preg_match('/^[A-Z][A-Z0-9_-]{0,39}$/D',$sku)||!in_array($kind,['test','normal'],true)||!in_array($gender,['Nam','Nữ',''],true)||!size_valid_date($day))throw new RuntimeException('Kiểm tra mã, nhóm và ngày hiệu lực.',400);
 $db->prepare('INSERT INTO product_modes VALUES(?,?,?,?,?,?) ON CONFLICT(sku,effective) DO UPDATE SET kind=excluded.kind,gender=excluded.gender,actor=excluded.actor,updated_at=excluded.updated_at')->execute([$sku,$day,$kind,$gender,$user['username'],gmdate('c')]);return ['ok'=>true];
}
