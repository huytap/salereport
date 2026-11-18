<?php

class CustomerImportBatch extends MvpActiveRecord
{
    protected $requiredFields = array('source_type', 'source_key', 'mapping_version');
    protected $enumFields = array('status'=>array('preview', 'approved', 'running', 'completed', 'failed'));
    protected $jsonFields = array('counts_json');

    public function tableName() { return 'crm_import_batch'; }

    public function relations()
    {
        return array(
            'rows' => array(self::HAS_MANY, 'CustomerImportRow', array('batch_id'=>'id')),
        );
    }

}
