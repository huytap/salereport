<?php

class Task extends MvpActiveRecord
{
    protected $editableFields = array('title','description','kind','action_type','priority','due_mode','due_date','due_at_utc','timezone','starts_at_utc','ends_at_utc','location');
    protected $requiredFields = array('code','title','client_request_key');
    protected $enumFields = array(
        'kind'=>array('task','appointment'), 'action_type'=>array('call','visit','entertainment','site_inspection','send_quote','other'),
        'priority'=>array('normal','high'), 'due_mode'=>array('date','datetime'), 'status'=>array('open','in_progress','completed','cancelled'),
    );
    protected $referenceFields = array('account_id'=>'Account','contact_person_id'=>'ContactPerson','hotel_id'=>'Hotel','report_id'=>'Report');

    public function tableName() { return 'crm_task'; }

    public function relations()
    {
        return array(
            'account'=>array(self::BELONGS_TO,'Account',array('account_id'=>'id')),
            'contactPerson'=>array(self::BELONGS_TO,'ContactPerson',array('contact_person_id'=>'id')),
            'hotel'=>array(self::BELONGS_TO,'Hotel',array('hotel_id'=>'id')),
            'report'=>array(self::BELONGS_TO,'Report',array('report_id'=>'id')),
            'assignee'=>array(self::BELONGS_TO,'Users',array('assignee_user_id'=>'id')),
            'reminders'=>array(self::HAS_MANY,'TaskReminder',array('task_id'=>'id')),
        );
    }

    public function rules()
    {
        return array_merge(parent::rules(), array(array('account_id', 'validateContext', 'safe'=>false)));
    }

    protected function prepareValidation()
    {
        try { $tz = new DateTimeZone((string)$this->timezone); }
        catch (Exception $e) { $this->addError('timezone','Invalid timezone.'); return; }
        if ($this->due_mode === 'date') {
            if ($this->due_at_utc !== null) { $this->addError('due_at_utc','Date-only tasks must not contain an exact due time.'); }
            if (self::validDate($this->due_date, 'Y-m-d')) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $this->due_date, $tz)->modify('+1 day');
                $this->deadline_at_utc = $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            } else { $this->addError('due_date','A valid due date is required.'); }
        } elseif ($this->due_mode === 'datetime') {
            if ($this->due_date !== null) { $this->addError('due_date','Timed tasks must not contain a date-only deadline.'); }
            if (self::validDate($this->due_at_utc, 'Y-m-d H:i:s')) { $this->deadline_at_utc = $this->due_at_utc; }
            else { $this->addError('due_at_utc','A valid UTC due time is required.'); }
        }
        if ($this->kind === 'appointment') {
            if (!self::validDate($this->starts_at_utc,'Y-m-d H:i:s') || !self::validDate($this->ends_at_utc,'Y-m-d H:i:s')
                || $this->ends_at_utc <= $this->starts_at_utc || $this->due_mode !== 'datetime' || $this->due_at_utc !== $this->ends_at_utc) {
                $this->addError('starts_at_utc','Cuộc hẹn cần giờ bắt đầu trước giờ kết thúc; giờ đến hạn phải bằng giờ kết thúc.');
            }
        } elseif ($this->starts_at_utc !== null || $this->ends_at_utc !== null) {
            $this->addError('starts_at_utc','Only appointments have start/end times.');
        }
        if ($this->status === 'completed' && (!$this->completed_at_utc || !$this->completed_by || !trim((string)$this->completion_note))) {
            $this->addError('completion_note','Completed tasks require actor, timestamp and result.');
        }
        if ($this->status === 'cancelled' && (!$this->cancelled_at_utc || !trim((string)$this->cancel_reason))) {
            $this->addError('cancel_reason','Cancelled tasks require timestamp and reason.');
        }
        if ($this->status !== 'completed' && ($this->completed_at_utc !== null || $this->completed_by !== null)) {
            $this->addError('completed_at_utc','Completion metadata must match task status.');
        }
        if ($this->status !== 'cancelled' && $this->cancelled_at_utc !== null) {
            $this->addError('cancelled_at_utc','Cancellation metadata must match task status.');
        }
    }

    public function validateContext($attribute, $params)
    {
        if ($this->hasErrors()) { return; }
        $assignment = AccountHotel::model()->findByPk(array('account_id'=>$this->account_id,'hotel_id'=>$this->hotel_id));
        $open = in_array($this->status, array('open','in_progress'), true);
        if (!$assignment || ($open && ($assignment->status !== 'active' || (string)$assignment->owner_user_id !== (string)$this->assignee_user_id))) {
            $this->addError('assignee_user_id','Task must belong to an active Account/Hotel and its current owner.');
        }
        // Workflow validation must read current state, rather than AR's cached relations.
        $account=Account::model()->findByPk($this->account_id);
        $person=$this->contact_person_id?ContactPerson::model()->findByPk($this->contact_person_id):null;
        $report=$this->report_id?Report::model()->findByPk($this->report_id):null;
        $assignee=Users::model()->findByPk($this->assignee_user_id);
        if ($person && (string)$person->account_id !== (string)$this->account_id) {
            $this->addError('contact_person_id','Contact must belong to this account.');
        }
        if ($report && ((string)$report->account_id !== (string)$this->account_id || (string)$report->hotel_id !== (string)$this->hotel_id)) {
            $this->addError('report_id','Report must belong to this account and hotel.');
        }
        if ($open && (!$account || $account->status!=='active' || ($this->contact_person_id && (!$person || $person->status!=='active')))) { $this->addError('account_id','Open tasks require an active account and contact person.'); }
        if ($open && $assignee && $assignee->status !== 'A') { $this->addError('assignee_user_id','Assignee must be active.'); }
    }

    public function isOverdue($nowUtc = null)
    {
        return in_array($this->status,array('open','in_progress'),true) && $this->deadline_at_utc !== null
            && ($nowUtc ?: gmdate('Y-m-d H:i:s')) >= $this->deadline_at_utc;
    }
}
