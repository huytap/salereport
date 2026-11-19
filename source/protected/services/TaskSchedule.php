<?php

/** Explicit local wall-clock input; reject DST gaps and ambiguous repeated times. */
class TaskSchedule
{
    public static function timezone($value)
    {
        if (!is_string($value) || strlen($value)>64) { throw new CustomerValidationException(array('timezone'=>array('Timezone không hợp lệ.'))); }
        try { return new DateTimeZone($value); }
        catch (Exception $e) { throw new CustomerValidationException(array('timezone'=>array('Timezone không hợp lệ.'))); }
    }
    public static function localToUtc($value,$timezone,$field)
    {
        if ($value===null || $value==='') { return null; }
        if (!is_string($value)) { throw new CHttpException(400,'Ngày giờ không hợp lệ.'); }
        $text=str_replace('T',' ',$value); if (strlen($text)===16) { $text.=':00'; }
        if (!MvpActiveRecord::validDate($text,'Y-m-d H:i:s')) { throw new CustomerValidationException(array($field=>array('Cần ngày giờ hợp lệ (YYYY-MM-DD HH:mm).'))); }
        $tz=self::timezone($timezone); $wall=new DateTimeImmutable($text,new DateTimeZone('UTC')); $seconds=$wall->getTimestamp();
        $offsets=array($tz->getOffset($wall)); $transitions=$tz->getTransitions($seconds-172800,$seconds+172800);
        if ($transitions) { foreach ($transitions as $transition) { $offsets[]=$transition['offset']; } }
        $matches=array();
        foreach (array_unique($offsets) as $offset) { $candidate=$wall->setTimestamp($seconds-$offset); if ($candidate->setTimezone($tz)->format('Y-m-d H:i:s')===$text) { $matches[]=$candidate->format('Y-m-d H:i:s'); } }
        if (count($matches)!==1) { throw new CustomerValidationException(array($field=>array('Giờ không tồn tại hoặc bị lặp do đổi giờ mùa hè. Hãy chọn giờ khác hoặc gửi UTC rõ ràng.'))); }
        return $matches[0];
    }
    public static function input(array $fields)
    {
        foreach (array('due_at','starts_at','ends_at') as $base) {
            $local=$base.'_local'; $utc=$base.'_utc';
            if (array_key_exists($local,$fields)) {
                if (array_key_exists($utc,$fields)) { throw new CHttpException(400,'Không gửi đồng thời giờ địa phương và UTC.'); }
                $fields[$utc]=self::localToUtc($fields[$local],isset($fields['timezone'])?$fields['timezone']:'Asia/Ho_Chi_Minh',$local); unset($fields[$local]);
            }
        }
        foreach (array('due_date','due_at_utc','starts_at_utc','ends_at_utc') as $name) { if (isset($fields[$name]) && $fields[$name]==='') { $fields[$name]=null; } }
        return $fields;
    }
    public static function local($utc,$timezone)
    {
        return $utc?(new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(self::timezone($timezone))->format('Y-m-d\TH:i:s'):'';
    }
}
