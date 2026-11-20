<?php
/** Seeds only disposable browser actors/Hotels; business actions are performed in the browser. */
class BrowseruatCommand extends CConsoleCommand
{
    public function actionSeed()
    {
        $db=CutoverService::testDb();
        if ($db->createCommand('SELECT DATABASE()')->queryScalar()!=='sale_report_mvp_test_browser_uat' || $db->createCommand('SELECT version FROM mvp_test_fixture')->queryScalar()!=='wp01-v1' || Account::model()->count() || $db->createCommand("SELECT COUNT(*) FROM users WHERE username LIKE 'browser_uat_%'")->queryScalar()) { throw new CException('Fresh dedicated browser UAT fixture required; existing rows were not changed.'); }
        Yii::app()->setComponent('user',new BrowserUatConsoleActor);
        foreach (array('sales'=>'users','manager'=>'admin','reader'=>'reservation') as $name=>$base) {
            $r=$db->createCommand('SELECT * FROM roles WHERE role_name=:name')->queryRow(true,array(':name'=>$base)); $rights=json_decode($r['access_rights'],true);
            if ($name!=='reader') { $rights['account']=array_values(array_unique(array_merge($rights['account'],array('report')))); $rights['customermove']=$name==='manager'?array('admin','view','preview','map','approve','commit','reconcile','export'):array('export'); }
            $r['role_name']='browser_uat_'.$name; $r['access_rights']=json_encode($rights); $db->createCommand()->insert('roles',$r);
        }
        $hotels=array(); foreach (array('h1','h2') as $name) { $h=new Hotel; $h->code='BROWSER-'.strtoupper($name); $h->name='UAT '.strtoupper($name).' — dữ liệu giả'; CustomerWriteSupport::save($h); $hotels[$name]=(int)$h->id; }
        $actors=array(); foreach (array('manager'=>'manager','sales_a'=>'sales','sales_b'=>'sales','outside'=>'sales','reader'=>'reader') as $name=>$role) {
            $u=new Users('create'); $u->attributes=array('username'=>'browser_uat_'.$name,'fullname'=>'UAT '.strtoupper($name),'roles'=>'browser_uat_'.$role,'status'=>'A','password'=>'Browser-UAT-only-2026!','last_login_date'=>gmdate('Y-m-d H:i:s')); CustomerWriteSupport::save($u); $actors[$name]=(int)$u->id;
            $g=new UserHotelAccess; $g->setAttributes(array('user_id'=>$u->id,'hotel_id'=>$hotels[$name==='outside'?'h2':'h1'],'scope_level'=>in_array($name,array('manager','reader'),true)?'hotel':'own'),false); CustomerWriteSupport::save($g);
        }
        $source=$db->createCommand('SELECT * FROM contact WHERE id=1')->queryRow(); unset($source['id']);
        $source['company']='UAT Trình duyệt – Công ty Sen 2026'; $source['first_name']='Lan'; $source['last_name']='Đỗ'; $source['email']='lienhe@example.test';
        $db->createCommand()->insert('contact',$source); $sourceId=(int)$db->lastInsertID;
        echo CJSON::encode(array('database'=>'sale_report_mvp_test_browser_uat','hotels'=>$hotels,'actors'=>$actors,'ambiguous_contact_id'=>$sourceId,'usernames'=>array_map(function($name){return 'browser_uat_'.$name;},array_keys($actors)),'note'=>'Only synthetic actors/Hotels and one raw ambiguous source seeded; perform all nine business journeys through the browser.'))."\n";
    }
}

class BrowserUatConsoleActor extends CApplicationComponent
{
    public $id=1;
    public function getIsGuest() { return false; }
}
