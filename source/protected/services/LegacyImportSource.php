<?php

/** Read-only extraction and suggestions. Never authorizes a merge. */
class LegacyImportSource
{
    public static function normalize($value)
    {
        return mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)$value)),'UTF-8');
    }
    public static function json($value)
    {
        $json=json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if ($json===false) { throw new LegacyImportException('INVALID_ENCODING'); }
        return $json;
    }
    public static function table($type)
    {
        if (!in_array($type,array('contact','report'),true)) { throw new CHttpException(400,'Expected Contact or Report source.'); }
        return $type==='contact'?'contact':'reports';
    }
    public static function hash(array $raw, $type)
    {
        if ($type==='report') {
            foreach (array('account_id','contact_person_id','hotel_id','identity_state','lock_version','client_request_key','snapshot_json','created_at_utc','updated_at_utc') as $key) { unset($raw[$key]); }
        }
        ksort($raw);
        foreach ($raw as &$value) { if ($value!==null) { $value=(string)$value; } } unset($value);
        return hash('sha256',self::json($raw));
    }
    public static function values(array $raw,$type)
    {
        $account=array('name'=>(string)$raw[$type==='contact'?'company':'company_name']);
        $fields=$type==='contact'?array('biz_scope'=>'biz_scope','website'=>'website','phone'=>'tel','building'=>'building','address_1'=>'address_1','address_2'=>'address_2','address_3'=>'address_3','city'=>'city','country'=>'country','source'=>'source','fit_series'=>'fit_series','dmc'=>'dmc','notes'=>'comment')
            :array('biz_scope'=>'biz_scope','phone'=>'phone','address_1'=>'company_address','city'=>'city','country'=>'country');
        foreach ($fields as $target=>$source) { $account[$target]=isset($raw[$source])?(string)$raw[$source]:''; }
        $segment=$type==='contact'?strtoupper(trim((string)$raw['account_type'])):'';
        $account['segment']=in_array($segment,array('LEISURE','CORPORATE','OTA'),true)?$segment:'';
        $person=array();
        foreach (array('first_name','last_name','position','email') as $field) { $person[$field]=isset($raw[$field])?(string)$raw[$field]:''; }
        $person['title']=isset($raw[$type==='contact'?'sal':'title'])?(string)$raw[$type==='contact'?'sal':'title']:'';
        $person['mobile']=$type==='contact'?(string)$raw['cell_phone']:'';
        $person['dob']=null;
        if ($type==='contact' && !empty($raw['dob'])) {
            foreach (array('Y-m-d','d-M-Y','d-m-Y','d/m/Y') as $format) {
                $date=DateTimeImmutable::createFromFormat('!'.$format,(string)$raw['dob']);
                if ($date && $date->format($format)===$raw['dob']) { $person['dob']=$date->format('Y-m-d'); break; }
            }
        }
        return array('account'=>$account,'contact'=>$person);
    }
    public static function proposal(array $raw,$type,array $sameNames)
    {
        $values=self::values($raw,$type); $normalized=self::normalize($values['account']['name']);
        $warnings=array();
        if ($normalized==='') { $warnings[]='MISSING_COMPANY'; }
        if (count($sameNames)>1) { $warnings[]='SAME_NAME_IN_SNAPSHOT'; }
        $candidateIds=array();
        foreach (Account::model()->findAll('normalized_name=:name',array(':name'=>$normalized)) as $a) { $candidateIds[(int)$a->id]='same_name'; }
        foreach (EntityAlias::model()->findAll("entity_type='account' AND normalized_alias=:name",array(':name'=>$normalized)) as $alias) { $candidateIds[(int)$alias->entity_id]='reviewed_alias'; }
        $candidates=array();
        foreach ($candidateIds as $id=>$reason) {
            $a=Account::model()->findByPk($id);
            if ($a) { $candidates[]=array('id'=>$a->id,'code'=>$a->code,'name'=>$a->name,'status'=>$a->status,'version'=>$a->lock_version,'reason'=>$reason); }
        }
        if ($candidates) { $warnings[]='ACCOUNT_MATCH_REQUIRES_REVIEW'; }
        $contactCandidates=array();
        $sourceName=self::normalize($values['contact']['first_name'].' '.$values['contact']['last_name']);
        foreach ($candidates as $candidate) {
            foreach (ContactPerson::model()->findAll("account_id=:id AND status='active'",array(':id'=>$candidate['id'])) as $person) {
                $sameName=$sourceName!=='' && $sourceName===self::normalize($person->first_name.' '.$person->last_name);
                $sameEmail=trim($values['contact']['email'])!=='' && self::normalize($values['contact']['email'])===self::normalize($person->email);
                if ($sameName || $sameEmail) { $contactCandidates[]=array('id'=>$person->id,'account_id'=>$person->account_id,'name'=>trim($person->first_name.' '.$person->last_name),'email'=>$person->email,'reason'=>$sameName?'same_name':'shared_email_verify_person'); }
            }
        }
        if ($contactCandidates) { $warnings[]='CONTACT_MATCH_REQUIRES_REVIEW'; }
        $hotels=array(); $hotelText=isset($raw[$type==='contact'?'hotel_produced':'hotels'])?$raw[$type==='contact'?'hotel_produced':'hotels']:'';
        foreach (array_filter(array_map('trim',explode(',',(string)$hotelText)),'strlen') as $label) {
            $matches=array();
            foreach (Hotel::model()->findAll("status='active'") as $h) { if (self::normalize($h->name)===self::normalize($label) || self::normalize($h->code)===self::normalize($label)) { $matches[(int)$h->id]=$h->name; } }
            foreach (EntityAlias::model()->findAll("entity_type='hotel' AND normalized_alias=:name",array(':name'=>self::normalize($label))) as $a) { $h=Hotel::model()->findByPk($a->entity_id); if ($h && $h->status==='active') { $matches[(int)$h->id]=$h->name; } }
            $hotels[]=array('source'=>$label,'candidates'=>$matches);
            if (count($matches)!==1) { $warnings[]='HOTEL_UNRESOLVED'; }
        }
        if (!$hotels) { $warnings[]='HOTEL_UNRESOLVED'; }
        if ($type==='report' && count($hotels)>1) { $warnings[]='REPORT_MULTIPLE_HOTELS'; }
        $owners=array();
        if ($type==='contact' && trim((string)$raw['managed_by'])!=='') {
            $label=self::normalize($raw['managed_by']);
            foreach (Users::model()->findAll("status='A'") as $u) {
                if ((string)$u->id===$label || self::normalize($u->username)===$label || self::normalize($u->fullname)===$label) { $owners[(int)$u->id]=$u->fullname; }
            }
            foreach (EntityAlias::model()->findAll("entity_type='user' AND normalized_alias=:name",array(':name'=>$label)) as $alias) { $u=ActionAccess::user($alias->entity_id); if ($u) { $owners[(int)$u->id]=$u->fullname; } }
        }
        if (!$owners) { $warnings[]=$type==='report'?'REPORT_CREATOR_IS_NOT_OWNER':'OWNER_UNRESOLVED'; }
        if ($type==='contact' && !empty($raw['dob']) && $values['contact']['dob']===null) { $warnings[]='INVALID_DOB_REQUIRES_REVIEW'; }
        $markets=isset($raw['market'])?array_values(array_filter(array_map('trim',explode(',',(string)$raw['market'])),'strlen')):array();
        if ($markets) { $warnings[]='MARKET_REQUIRES_REVIEW'; }
        if ($values['contact']['email']!=='' && !filter_var($values['contact']['email'],FILTER_VALIDATE_EMAIL)) { $warnings[]='INVALID_EMAIL_REQUIRES_REVIEW'; }
        $hasName=self::normalize($values['contact']['first_name'].' '.$values['contact']['last_name'])!=='';
        if (!$hasName) { $warnings[]='NO_CONTACT_PERSON_NAME'; }
        $existing=LegacyLink::model()->findByPk(array('source_type'=>$type,'source_id'=>(string)$raw['id']));
        if ($existing) { $warnings[]='EXISTING_SOURCE_LINK'; }
        return array('values'=>$values,'account_candidates'=>$candidates,'contact_candidates'=>$contactCandidates,'market_suggestions'=>$markets,'same_name_rows'=>$sameNames,'hotel_candidates'=>$hotels,'owner_candidates'=>$owners,
            'existing_link'=>$existing?array('account_id'=>$existing->account_id,'contact_person_id'=>$existing->contact_person_id,'source_hash'=>$existing->source_hash,'resolution_state'=>$existing->resolution_state):null,
            'warnings'=>array_values(array_unique($warnings)));
    }
}
