<?php
declare(strict_types=1);
function admin_drafts(PDO $db,array $input,int $now): array {
 $where=['updated_at>?','NOT EXISTS(SELECT 1 FROM orders o WHERE o.owner=analytics_drafts.owner AND o.request_key=analytics_drafts.request_key)'];$params=[$now-30*86400];
 $term=$input['q']??'';if(!is_string($term)||mb_strlen($term)>100)throw new RuntimeException('Từ khóa tìm kiếm quá dài.',400);
 if(trim($term)!==''){$where[]="(json_extract(payload,'$.customerName') LIKE ? OR json_extract(payload,'$.phone') LIKE ? OR json_extract(payload,'$.address') LIKE ? OR json_extract(payload,'$.note') LIKE ?)";for($i=0;$i<4;$i++)$params[]='%'.trim($term).'%';}
 foreach(['from','to'] as $key){$value=$input[$key]??'';if($value==='')continue;if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$value))throw new RuntimeException('Ngày lọc không hợp lệ.',400);$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Asia/Ho_Chi_Minh'));if(!$date||$date->format('Y-m-d')!==$value)throw new RuntimeException('Ngày lọc không hợp lệ.',400);if($key==='to')$date=$date->modify('+1 day');$where[]='updated_at'.($key==='from'?'>=':'<').'?';$params[]=$date->getTimestamp();}
 $sql=' WHERE '.implode(' AND ',$where);$q=$db->prepare('SELECT count(*) FROM analytics_drafts'.$sql);$q->execute($params);$total=(int)$q->fetchColumn();$pages=max(1,(int)ceil($total/20));$page=max(1,min($pages,(int)($input['page']??1)));
 $q=$db->prepare('SELECT updated_at,source,payload FROM analytics_drafts'.$sql.' ORDER BY updated_at DESC,owner LIMIT 20 OFFSET '.(($page-1)*20));$q->execute($params);$drafts=[];
 foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row){$payload=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR);$drafts[]=['updatedAt'=>gmdate('c',(int)$row['updated_at']),'source'=>$row['source'],'customerName'=>(string)($payload['customerName']??''),'phone'=>(string)($payload['phone']??''),'address'=>(string)($payload['address']??''),'note'=>(string)($payload['note']??''),'items'=>$payload['items']??[]];}
 return ['drafts'=>$drafts,'total'=>$total,'page'=>$page,'pages'=>$pages];
}
