<?php
declare(strict_types=1);
function board_history_columns(array $sheet,array $map):array {
 $measure=$map['CHIEU CAO / CAN NANG']??$map['CHIEU CAO/CAN NANG']??null;$order=$map['MA DON HANG']??null;$tracking=$map['MA VAN DON']??null;
 if(isset($map['TINH TRANG'])||$measure===null||$order===null||$tracking===null)return ['map'=>$map,'legacy'=>false];
 $nonempty=0;$statusLike=0;$codes=0;foreach($sheet['cellData']??[] as $r=>$cells){if((int)$r<1)continue;$value=report_fold((string)($cells[$measure]['v']??''));if($value!==''){$nonempty++;if(preg_match('/^(?:\d+K\s*)?(?:OK|HH|NK|DG|KNM|BOM|CHAT|RONG|NGAN|DAI|HOAN|K UNG|KHONG|GUI LAI|DOI|DI |GIUC |CHO )\b/u',$value))$statusLike++;}if(preg_match('/^\d{10,15}$/',trim((string)($cells[$order]['v']??''))))$codes++;}
 if(!$codes||!$nonempty||$statusLike/max(1,$nonempty)<0.5)return ['map'=>$map,'legacy'=>false];
 $map['TINH TRANG']=$measure;$map['MA VAN DON']=$order;$map['HOAN']=$tracking;unset($map['CHIEU CAO / CAN NANG'],$map['CHIEU CAO/CAN NANG']);return ['map'=>$map,'legacy'=>true];
}
function board_historical_return_reason(string $status):bool {
 $status=report_fold($status);if(in_array($status,['','OK','HH','NK','DG'],true))return false;
 return (bool)preg_match('/(?:^|[^A-Z])(?:BOM|KNM|CHAT|RONG|NGAN|DAI|HOAN|K UNG|KHONG UNG|KHONG NHAN|KO NHAN|KHONG LAY|KO LAY|TU CHOI|DOI SIZE)(?:$|[^A-Z])/u',$status);
}
