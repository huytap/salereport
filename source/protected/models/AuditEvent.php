<?php

class AuditEvent extends MvpActiveRecord
{
    protected $requiredFields = array('entity_type', 'entity_id', 'event_type', 'request_id');
    protected $referenceFields = array('import_batch_id'=>'CustomerImportBatch');
    protected $jsonFields = array('before_json', 'after_json');

    public function tableName() { return 'crm_audit_event'; }

    protected function beforeSave()
    {
        if (!$this->isNewRecord) { throw new CDbException('Audit events are append-only.'); }
        return parent::beforeSave();
    }

    public function relations()
    {
        return array(
            'actor' => array(self::BELONGS_TO, 'Users', array('actor_user_id'=>'id')),
            'batch' => array(self::BELONGS_TO, 'CustomerImportBatch', array('import_batch_id'=>'id')),
        );
    }

}
