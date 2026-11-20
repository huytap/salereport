<?php
/** Human labels for HTML forms; JSON field keys stay unchanged. */
class FormErrorLabels
{
    public static function label($field)
    {
        $prefix='';
        if (preg_match('/^tasks\.(\d+)\.(.+)$/D',$field,$m)) { $prefix='Việc mới '.((int)$m[1]+1).' — '; $field=$m[2]; }
        $field=preg_replace('/^(Report|Account|Person|new_contact)\./','',$field);
        $labels=array('title'=>'Tiêu đề','description'=>'Nội dung','name'=>'Tên doanh nghiệp','first_name'=>'Tên','last_name'=>'Họ','email'=>'Email','phone'=>'Điện thoại','mobile'=>'Di động','dob'=>'Ngày sinh','position'=>'Chức vụ','contact_role'=>'Vai trò liên hệ','notes'=>'Ghi chú','date'=>'Ngày tiếp xúc','report'=>'Nội dung cuộc tiếp xúc','sales_type'=>'Loại tiếp xúc','objective'=>'Mục đích','expected_result'=>'Nhóm nhu cầu','due_date'=>'Ngày đến hạn','due_at_utc'=>'Giờ đến hạn','due_at_local'=>'Giờ đến hạn','starts_at_utc'=>'Bắt đầu cuộc hẹn','starts_at_local'=>'Bắt đầu cuộc hẹn','ends_at_utc'=>'Kết thúc cuộc hẹn','ends_at_local'=>'Kết thúc cuộc hẹn','kind'=>'Loại công việc','due_mode'=>'Kiểu hạn','timezone'=>'Múi giờ','priority'=>'Ưu tiên','action_type'=>'Hoạt động','contact_person_id'=>'Người liên hệ','owner_user_id'=>'Người phụ trách','assignee_user_id'=>'Người nhận','reason'=>'Lý do','allow_overdue'=>'Xác nhận quá hạn','conflict'=>'Xung đột cập nhật');
        return $prefix.(isset($labels[$field])?$labels[$field]:'Thông tin');
    }
}
