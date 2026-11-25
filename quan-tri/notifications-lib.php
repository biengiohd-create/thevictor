<?php
function notifications_state(PDO $db,int $since): array {
 $db->exec('CREATE TABLE IF NOT EXISTS order_board_transfers(code TEXT PRIMARY KEY,sheet_id TEXT,row_number INTEGER,workbook_version INTEGER NOT NULL,actor TEXT NOT NULL,transferred_at TEXT NOT NULL)');
 $latest=(int)$db->query('SELECT COALESCE(max(rowid),0) FROM orders')->fetchColumn();$pending="o.status='pending_confirmation' AND NOT EXISTS(SELECT 1 FROM order_admin_trash t WHERE t.code=o.code) AND NOT EXISTS(SELECT 1 FROM order_board_transfers b WHERE b.code=o.code)";
 $count=(int)$db->query('SELECT count(*) FROM orders o WHERE '.$pending)->fetchColumn();$ids=array_map('intval',$db->query('SELECT rowid FROM orders o WHERE '.$pending.' ORDER BY rowid LIMIT 10000')->fetchAll(PDO::FETCH_COLUMN));$q=$db->prepare('SELECT rowid AS id FROM orders o WHERE rowid>? AND '.$pending.' ORDER BY rowid LIMIT 100');$q->execute([max(0,$since)]);
 $today=(new DateTimeImmutable('today',new DateTimeZone('Asia/Ho_Chi_Minh')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');$todayQuery=$db->prepare('SELECT count(*) FROM orders o WHERE created_at>=? AND NOT EXISTS(SELECT 1 FROM order_admin_trash t WHERE t.code=o.code)');$todayQuery->execute([$today]);return ['todayTotal'=>(int)$todayQuery->fetchColumn(),'latest'=>$latest,'pending'=>$count,'pendingIds'=>$ids,'new'=>array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN))];
}
