<?php

/** Scope predicates and object checks shared by lists, IDs, helpers and exports. */
class CustomerPolicy
{
    public static function ready()
    {
        foreach (array('crm_account_hotel','crm_user_hotel_access','crm_legacy_link') as $table) {
            if (Yii::app()->db->schema->getTable($table) === null) { return false; }
        }
        return true;
    }

    private static function alias($alias)
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D',$alias)) { throw new CException('Invalid policy alias.'); }
        return $alias;
    }

    public static function scopeCriteria($type, $alias = 't', $userId = null, $manage = false)
    {
        $alias = self::alias($alias);
        $criteria = new CDbCriteria;
        $user = ActionAccess::user($userId);
        if (!$user) { $criteria->addCondition('1=0'); return $criteria; }
        if (ActionAccess::isAll($user->id)) { return $criteria; }
        if (!self::ready()) { $criteria->addCondition('1=0'); return $criteria; }
        $uid = (int)$user->id;
        // SQL IDs come only from validated database records, never request text.
        $scope = "g.user_id=$uid AND ah.status='active' AND (g.scope_level='hotel'";
        if (!$manage) { $scope .= " OR (g.scope_level='own' AND ah.owner_user_id=$uid)"; }
        $scope .= ')';
        $join = ' FROM crm_account_hotel ah INNER JOIN crm_user_hotel_access g ON g.hotel_id=ah.hotel_id ';
        if ($type === 'account') {
            $where = "EXISTS (SELECT 1 $join WHERE ah.account_id=$alias.id AND $scope)";
        } elseif ($type === 'person') {
            $where = "EXISTS (SELECT 1 $join WHERE ah.account_id=$alias.account_id AND $scope)";
        } elseif (in_array($type,array('report','task'),true)) {
            $where = "EXISTS (SELECT 1 $join WHERE ah.account_id=$alias.account_id AND ah.hotel_id=$alias.hotel_id AND $scope)";
            if ($type === 'report') { $where .= " AND $alias.identity_state='linked'"; }
        } elseif ($type === 'contact') {
            // A mapping is authoritative only after review; managed_by/hotel text never grants access.
            $where = "EXISTS (SELECT 1 FROM crm_legacy_link ll INNER JOIN crm_account_hotel ah ON ah.account_id=ll.account_id INNER JOIN crm_user_hotel_access g ON g.hotel_id=ah.hotel_id WHERE ll.source_type='contact' AND ll.source_id=(CAST($alias.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) AND ll.resolution_state='linked' AND $scope)";
        } else { throw new CException('Unsupported policy object type.'); }
        $criteria->addCondition($where);
        return $criteria;
    }

    public static function criteria($type, $controller, $action, $alias = 't')
    {
        $criteria = self::scopeCriteria($type,$alias);
        if (!ActionAccess::can($controller,$action)) { $criteria->addCondition('1=0'); }
        return $criteria;
    }

    public static function assertRecord($type, $controller, $action, CActiveRecord $record, $manage = false)
    {
        $manage = $manage || in_array($action,array('assign','archive','reopen'),true)
            || ($type==='task' && in_array($action,array('complete','cancel'),true) && (string)$record->assignee_user_id!==(string)Yii::app()->user->id);
        $read = self::scopeCriteria($type);
        if (!$record->findByPk($record->primaryKey,$read)) { throw new CHttpException(404,'The requested record does not exist.'); }
        ActionAccess::requireAction($controller,$action);
        if ($manage && !$record->findByPk($record->primaryKey,self::scopeCriteria($type,'t',null,true))) {
            throw new CHttpException(403,'Hotel or All scope is required.');
        }
        if ($type === 'account' && $action === 'archive' && !ActionAccess::isAll()) {
            $unmanaged=Yii::app()->db->createCommand("SELECT COUNT(*) FROM crm_account_hotel ah WHERE ah.account_id=:account AND ah.status='active' AND NOT EXISTS (SELECT 1 FROM crm_user_hotel_access g WHERE g.hotel_id=ah.hotel_id AND g.user_id=:user AND g.scope_level='hotel')")
                ->queryScalar(array(':account'=>$record->id,':user'=>Yii::app()->user->id));
            if ($unmanaged) { throw new CHttpException(403,'Archive requires control of every active Hotel of this account.'); }
        }
    }

    public static function assertAccountHotel($accountId, $hotelId, $controller, $action, $manage = false)
    {
        $manage = $manage || in_array($action,array('assign','archive','reopen'),true);
        foreach (array($accountId,$hotelId) as $id) {
            if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1) { throw new CHttpException(404,'Account/Hotel not found.'); }
        }
        $user=ActionAccess::user();
        if (!$user) { throw new CHttpException(403,'Active account required.'); }
        $assignment=AccountHotel::model()->findByPk(array('account_id'=>$accountId,'hotel_id'=>$hotelId));
        if (!$assignment || $assignment->status!=='active') { throw new CHttpException(404,'Account/Hotel not found.'); }
        if (!ActionAccess::isAll()) {
            $grant=UserHotelAccess::model()->findByPk(array('user_id'=>$user->id,'hotel_id'=>$hotelId));
            if (!$grant || ($grant->scope_level==='own' && (string)$assignment->owner_user_id!==(string)$user->id)) { throw new CHttpException(404,'Account/Hotel not found.'); }
            if ($manage && $grant->scope_level!=='hotel') { throw new CHttpException(403,'Hotel scope is required.'); }
        }
        ActionAccess::requireAction($controller,$action);
    }

    public static function load($class, $type, $controller, $action, $id, $manage = false)
    {
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id < 1) { throw new CHttpException(404,'The requested record does not exist.'); }
        $record = $class::model()->findByPk($id,self::scopeCriteria($type));
        if (!$record) { throw new CHttpException(404,'The requested record does not exist.'); }
        self::assertRecord($type,$controller,$action,$record,$manage);
        return $record;
    }

    public static function requireLegacyWrite($controller, $action, $record = null)
    {
        ActionAccess::requireAction($controller,$action);
        if (!ActionAccess::isAll()) { throw new CHttpException(403,'Legacy writes require All scope until the scoped editor is available.'); }
        if (!empty(Yii::app()->params['mvp']['customerWrite'])) { throw new CHttpException(410,'Use the unified customer workflow.'); }
        if ($record instanceof Report && $record->hasAttribute('account_id') && $record->account_id !== null) {
            throw new CHttpException(409,'Linked reports must be changed through the MVP workflow.');
        }
        if ($record instanceof Contact && self::ready() && LegacyLink::model()->exists("source_type='contact' AND source_id=:id AND account_id IS NOT NULL",array(':id'=>(string)$record->id))) {
            throw new CHttpException(409,'Mapped contacts must be changed through the MVP workflow.');
        }
    }

    public static function visibleUsersCriteria($alias = 't')
    {
        $alias = self::alias($alias);
        $c = new CDbCriteria;
        $user = ActionAccess::user();
        if (!$user) { $c->addCondition('1=0'); }
        elseif (!ActionAccess::isAll()) {
            $uid = (int)$user->id;
            if (!self::ready()) { $c->addCondition("$alias.id=$uid"); }
            else { $c->addCondition("($alias.id=$uid OR EXISTS (SELECT 1 FROM crm_user_hotel_access mine INNER JOIN crm_user_hotel_access other ON other.hotel_id=mine.hotel_id WHERE mine.user_id=$uid AND mine.scope_level='hotel' AND other.user_id=$alias.id))"); }
        }
        return $c;
    }

    public static function notificationCriteria($alias = 't')
    {
        $alias = self::alias($alias);
        $c = new CDbCriteria;
        $user = ActionAccess::user();
        if (!$user || !self::ready() || !ActionAccess::can('reminder','admin')) { $c->addCondition('1=0'); return $c; }
        $c->compare($alias.'.recipient_user_id',$user->id);
        $scope = self::scopeCriteria('task','nt');
        $scopeSql = $scope->condition ?: '1=1';
        $c->addCondition("EXISTS (SELECT 1 FROM crm_task nt WHERE nt.id=$alias.task_id AND nt.status IN ('open','in_progress') AND nt.schedule_version=$alias.schedule_version AND nt.assignee_user_id=$alias.recipient_user_id AND $scopeSql)");
        return $c;
    }

    public static function hasUserReferences($userId)
    {
        if (!is_scalar($userId) || !ctype_digit((string)$userId)) { return true; }
        $fields=array('created_by','updated_by','owner_user_id','assignee_user_id','recipient_user_id','user_id','approved_by','reviewed_by','completed_by','actor_user_id');
        foreach (array('crm_account','crm_contact_person','crm_account_hotel','crm_user_hotel_access','crm_task','crm_task_reminder','crm_notification','crm_import_batch','crm_legacy_link','crm_audit_event') as $table) {
            $schema=Yii::app()->db->schema->getTable($table);
            if (!$schema) { continue; }
            $conditions=array();
            foreach ($fields as $field) { if (isset($schema->columns[$field])) { $conditions[]=$field.'='.(int)$userId; } }
            if ($conditions && Yii::app()->db->createCommand('SELECT COUNT(*) FROM '.$table.' WHERE '.implode(' OR ',$conditions))->queryScalar()) { return true; }
        }
        return false;
    }
}
