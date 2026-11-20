<?php
/** One row per scoped Account/Hotel/Contact; raw legacy snapshots never leave this path. */
class CustomerTransferCsv
{
    public static function columns()
    {
        $out=array('account_id','account_code','contact_id','hotel_id','owner_user_id','account_markets');
        foreach (AccountService::accountFields() as $f) { $out[]='account_'.$f; }
        foreach (AccountService::personFields() as $f) { $out[]='contact_'.$f; }
        return $out;
    }
    public static function export($stream,$hotelId,$ownerId=null)
    {
        ActionAccess::requireAction('customermove','export'); ActionAccess::requireAction('account','report');
        $hotelId=CustomerWriteSupport::id($hotelId); CustomerWriteSupport::hotel($hotelId);
        $ownerId=$ownerId===null || $ownerId===''?null:CustomerWriteSupport::id($ownerId);
        $uid=(int)Yii::app()->user->id; $params=array(':hotel'=>$hotelId); $where="ah.hotel_id=:hotel AND ah.status='active' AND a.status='active'";
        if ($ownerId!==null) { $where.=' AND ah.owner_user_id=:owner'; $params[':owner']=$ownerId; }
        $columns=self::columns(); fputcsv($stream,$columns); $lastAccount=0; $lastPerson=0; $count=0;
        // Keyset pagination keeps memory bounded. Each page rechecks current grants and ownership.
        do {
            if (!ActionAccess::can('customermove','export') || !ActionAccess::can('account','report')) { throw new CHttpException(403,'Quyền xuất đã thay đổi.'); }
            $pageWhere=$where;
            if (!ActionAccess::isAll()) { $pageWhere.=" AND EXISTS (SELECT 1 FROM crm_user_hotel_access g WHERE g.hotel_id=ah.hotel_id AND g.user_id=$uid AND (g.scope_level='hotel' OR (g.scope_level='own' AND ah.owner_user_id=$uid)))"; }
            $rows=Yii::app()->db->createCommand("SELECT a.*, p.id AS person_id, ah.owner_user_id AS export_owner FROM crm_account a JOIN crm_account_hotel ah ON ah.account_id=a.id LEFT JOIN crm_contact_person p ON p.account_id=a.id AND p.status='active' WHERE $pageWhere AND (a.id>:last OR (a.id=:same AND COALESCE(p.id,0)>:person)) ORDER BY a.id,COALESCE(p.id,0) LIMIT 200")->queryAll(true,$params+array(':last'=>$lastAccount,':same'=>$lastAccount,':person'=>$lastPerson));
            foreach ($rows as $a) {
                $p=$a['person_id']?ContactPerson::model()->findByPk($a['person_id']):null; $row=array('account_id'=>$a['id'],'account_code'=>$a['code'],'contact_id'=>$a['person_id'],'hotel_id'=>$hotelId,'owner_user_id'=>$a['export_owner']);
                $row['account_markets']=LegacyImportSource::json(Yii::app()->db->createCommand('SELECT market_code FROM crm_account_market WHERE account_id=:id ORDER BY market_code')->queryColumn(array(':id'=>$a['id'])));
                foreach (AccountService::accountFields() as $f) { $row['account_'.$f]=$a[$f]; }
                foreach (AccountService::personFields() as $f) { $row['contact_'.$f]=$p?$p->$f:''; }
                $cells=array(); foreach ($columns as $c) { $cells[]=self::safeCell(isset($row[$c])?$row[$c]:''); } fputcsv($stream,$cells); $count++;
                $lastAccount=(int)$a['id']; $lastPerson=(int)$a['person_id'];
            }
        } while (count($rows)===200);
        return $count;
    }
    private static function safeCell($v)
    {
        $v=(string)$v;
        // Quote-prefix formula/leading control text; a leading quote is escaped too for lossless roundtrip.
        return preg_match('/^(?:[\x00-\x20]*[=+@-]|[\t\r\n]|\')/u',$v)?"'".$v:$v;
    }
    private static function decodeCell($v)
    {
        if (substr($v,0,1)==="'" && self::safeCell(substr($v,1))===$v) { return substr($v,1); } return $v;
    }
    public static function read($stream)
    {
        if (!is_resource($stream)) { throw new CHttpException(400,'Thiếu CSV.'); }
        $bytes=stream_get_contents($stream,2097153); if (strlen($bytes)>2097152 || !mb_check_encoding($bytes,'UTF-8') || strpos($bytes,"\0")!==false) { throw new CHttpException(400,'CSV phải UTF-8, tối đa 2 MB, không chứa NUL.'); }
        $bytes=preg_replace('/^\xEF\xBB\xBF/','',$bytes); $s=fopen('php://temp','w+'); fwrite($s,$bytes); rewind($s);
        try {
            $header=fgetcsv($s); if (!$header || count(array_unique($header))!==count($header) || array_diff($header,self::columns()) || !in_array('account_name',$header,true)) { throw new CHttpException(400,'Header CSV sai/trùng/không hỗ trợ. Cần account_name.'); }
            $rows=array(); while (($cells=fgetcsv($s))!==false) {
                if ($cells===array(null)) { continue; }
                if (count($cells)!==count($header) || count($rows)>=500) { throw new CHttpException(400,'CSV sai số cột hoặc vượt 500 dòng.'); }
                $r=array_combine($header,array_map(array(__CLASS__,'decodeCell'),$cells)); $a=array(); $p=array();
                foreach (AccountService::accountFields() as $f) { if (isset($r['account_'.$f])) { $a[$f]=$r['account_'.$f]; } }
                foreach (AccountService::personFields() as $f) { if (isset($r['contact_'.$f])) { $p[$f]=$r['contact_'.$f]; } }
                if (isset($p['dob']) && $p['dob']==='') { $p['dob']=null; }
                if (!empty($r['account_markets'])) { $markets=json_decode($r['account_markets'],true); if (!is_array($markets) || count($markets)>20) { throw new CHttpException(400,'account_markets phải là JSON array tối đa 20 mã.'); } } else { $markets=array(); }
                $rows[]=array('account'=>$a,'contact'=>$p,'markets'=>$markets,'ids'=>array_intersect_key($r,array_flip(array('account_id','account_code','contact_id','hotel_id','owner_user_id'))));
            }
            if (!$rows) { throw new CHttpException(400,'CSV rỗng.'); } return array('hash'=>hash('sha256',$bytes),'rows'=>$rows);
        } finally { fclose($s); }
    }
}
