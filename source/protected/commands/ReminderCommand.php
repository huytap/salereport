<?php
class ReminderCommand extends CConsoleCommand
{
    private function bit($value)
    {
        if (!in_array($value,array(0,1,'0','1'),true)) { throw new CHttpException(400,'Các cờ chỉ nhận 0 hoặc 1.'); } return (string)$value==='1';
    }
    public function actionDispatch($limit=200,$dryRun=0,$enableTest=0)
    {
        try {
            ReminderDispatchService::testDb(); $dry=$this->bit($dryRun);
            if ($this->bit($enableTest)) { $flags=Yii::app()->params['mvp']; $flags['reminders']=true; Yii::app()->params['mvp']=$flags; }
            $result=ReminderDispatchService::dispatch($limit,$dry); echo CJSON::encode($result)."\n";
            return !empty($result['error_count'])?1:0;
        } catch (Exception $e) { echo CJSON::encode(array('error_code'=>$e instanceof CHttpException?'REQUEST_REJECTED':'DISPATCH_FAILED','message'=>$e instanceof CHttpException?$e->getMessage():'Không thể chạy dispatch; kiểm tra kết nối và trạng thái worker.'))."\n"; return 2; }
    }
    public function actionCheck()
    {
        try { ReminderDispatchService::testDb(); echo CJSON::encode(ReminderDispatchService::check())."\n"; return 0; }
        catch (Exception $e) { echo CJSON::encode(array('error_code'=>'CHECK_FAILED','message'=>'Không thể đọc trạng thái worker trên DB test.'))."\n"; return 2; }
    }
}
