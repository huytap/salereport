# WP05 — Task, trạng thái, đổi hạn và lịch tuần

Triển khai ngày 19/11/2026 trên nền WP00–WP04. Ghi task chỉ bật trên cấu hình DB test; chưa bật tính năng vận hành hoặc worker nhắc việc.

## Chức năng đã có

| Màn hình / route | Nội dung |
|---|---|
| `task/admin` | Của tôi, Hôm nay, Sắp tới, Quá hạn, Đã hoàn thành, Tất cả; lọc Hotel/sales/trạng thái/ưu tiên/tiêu đề hoặc mã; 25 dòng/trang |
| `task/create` | Chọn Hotel và khách hàng, Contact tùy chọn, nội dung/hoạt động/ưu tiên/địa điểm, hạn ngày hoặc hạn có giờ/cuộc hẹn |
| `task/view&id=…` | Thông tin, nguồn Report, người phụ trách, thời gian theo timezone, kết quả/lý do hủy, lịch sử phân trang |
| `task/update&id=…` | Chỉ sửa nội dung, hoạt động, ưu tiên, địa điểm và Contact; không nhận trạng thái/owner/lịch |
| `task/reschedule&id=…` | Đổi hạn/loại việc/lịch/timezone, bắt buộc lý do và phiên bản hiện tại |
| `task/reopen&id=…` | Quản lý mở lại việc đã đóng, chọn lịch hợp lệ và lý do |
| `task/calendar` | Lịch 1–7 ngày, mặc định tuần hiện tại từ thứ Hai; cuộc hẹn theo ngày/giờ và danh sách riêng cho việc hạn ngày |

Menu Công việc gồm danh sách, thêm việc và lịch tuần. Tab Công việc trong hồ sơ khách hàng có nút tạo việc và link tới từng task. Các trang tái sử dụng Yii/Bootstrap đang có. Form giữ dữ liệu và request key sau lỗi validation; server vẫn kiểm tra mọi trường khi JavaScript không chạy.

Lịch JSON: `index.php?r=task/calendar&from=2030-01-07&to=2030-01-14&timezone=Asia/Ho_Chi_Minh&format=json`. Ngày cuối không bao gồm. Các route khác dùng cùng kiểu query routing Yii.

## Quy tắc nghiệp vụ

Task gắn cố định Account/Hotel, người liên hệ và nguồn Report tùy chọn. Service lấy người nhận từ **owner hiện tại** dưới khóa AccountHotel; không cho gán tùy ý sales khác. Muốn đổi owner dùng bàn giao WP04. Khách hàng/Contact/Hotel phải hoạt động khi tạo, sửa lịch hoặc mở lại. Owner phải Active và có quyền Hotel thích hợp; All được kiểm tra theo policy đang có. Không cho tạo task cho khách chưa phân công.

Task từ Report cũ không phụ thuộc giới hạn sửa Report 7 ngày. Khi gắn nguồn, service kiểm tra Report đã linked và đúng Account/Hotel, đồng thời kiểm tra action xem Report. Tạo/sửa/đóng task không thay nội dung, ngày hoặc audit Report.

| Thao tác | Trạng thái và điều kiện | Lịch nhắc |
|---|---|---|
| Tạo | `open`, field hợp lệ và owner hiện tại | Lập lịch phiên bản 1 |
| Bắt đầu | `open → in_progress` | Giữ lịch, chỉ tăng lock_version |
| Sửa nội dung | Chỉ `open/in_progress` | Giữ schedule_version |
| Đổi hạn/lịch | Chỉ `open/in_progress`, có lý do và lịch đầy đủ hợp lệ | Tăng schedule_version, hủy pending cũ, lập lịch mới |
| Hoàn thành | `open/in_progress → completed`, bắt buộc kết quả | Server đặt completed_by/at; tăng schedule_version, hủy pending |
| Hủy | `open/in_progress → cancelled`, bắt buộc lý do | Không ghi nhận hoàn thành; tăng schedule_version, hủy pending |
| Mở lại | `completed/cancelled → open`, chỉ Hotel/All quản lý đúng Hotel | Dùng owner hiện tại, xóa metadata đóng khỏi bản ghi hiện tại, giữ toàn bộ trong audit, tăng schedule_version và lập lịch mới |

Mọi sửa đổi đều tăng lock_version và ghi audit trước/sau. Gửi phiên bản cũ trả 409. Replay cùng key/nội dung trả kết quả yêu cầu ban đầu với `replayed=true`, không thực hiện lại; giao diện chuyển tới chi tiết để đọc trạng thái mới nhất.

Sales chỉ thao tác việc được giao trong phạm vi Own. Quản lý phải có Hotel của **đúng Task**, không dùng quyền ở Hotel khác của cùng Account. Action và scope được kiểm tra lại khi ghi/replay. Mở lại luôn cần quyền quản lý, kể cả task trước đó do chính quản lý thực hiện.

## Ngày giờ và lịch tuần

- Task hạn ngày: chỉ `due_date`; deadline là đầu ngày kế tiếp theo timezone Task, lưu UTC. Không tạo cuộc hẹn giả lúc 09:00.
- Task có giờ: `due_at_utc`, không có due_date/start/end.
- Appointment: start/end hợp lệ, end > start, `due_mode=datetime` và hạn bằng end.
- Form nhận giờ địa phương, chuyển sang UTC phía server. Có trợ giúp nhập khi chọn loại việc/kiểu hạn; không kéo thả lịch.
- API có thể gửi UTC rõ ràng hoặc các field `*_local`; không được gửi đồng thời hai dạng cho cùng mốc.
- Timezone không hợp lệ, ngày không có thật, giờ bị thiếu hoặc lặp do DST đều bị từ chối. API có thể gửi UTC để biểu đạt rõ mốc trong giờ DST lặp.
- Tạo/đổi lịch/mở lại với hạn đã qua cần `allow_overdue=1` và lý do. Chỉ lập một nhắc quá hạn ngay, không phát lại mốc nhắc trước đó.
- Calendar lấy appointment theo khoảng giao `start < cuối khoảng AND end > đầu khoảng`, gồm cuộc hẹn vắt qua ranh giới tuần/ngày. Hạn ngày được trả riêng ở `date_tasks` với ngày/timezone gốc.
- Calendar chỉ nhận 1–7 ngày; tối đa 500 appointment và 500 việc hạn ngày. Vượt giới hạn yêu cầu lọc thêm, không cắt dữ liệu âm thầm.
- Hôm nay/Sắp tới dựa trên ngày hiển thị và hạn công việc; Quá hạn so sánh deadline UTC với thời gian hiện tại. Calendar và các tab ngày mặc định chỉ lấy việc đang mở; có bộ lọc trạng thái để xem lịch sử.

Planner dùng lại WP04: trước hạn hoặc bắt đầu 30 phút, hạn ngày 09:00 địa phương, quá hạn 09:00 địa phương tiếp theo sau deadline. Notification đã phát giữ lịch sử; predicate WP02 không hiển thị thông báo khi task đã đóng, lịch đổi phiên bản, bàn giao hoặc quyền mất hiệu lực. WP05 chỉ tạo/hủy lịch pending; chưa có dispatch/cron WP07.

## Service, khóa và chống gửi trùng

Các lớp mới:

- `TaskService`: whitelist, phân quyền, current owner, trạng thái, version, transaction, audit và lịch nhắc.
- `TaskReadService`: danh sách, detail/audit phân trang, lựa chọn Account theo Hotel và calendar có scope.
- `TaskSchedule`: nhập local/UTC, kiểm tra timezone và DST, định dạng hiển thị.
- `TaskController` và `views/task/*`: form/HTML, các POST và calendar JSON. Mutation JSON có error_code, message và field_errors; không trả stack trace/SQL.

Dùng mutex chung WP03/WP04 và thứ tự Hotel → Account → AccountHotel → Task → Reminder. Khi có nguồn Report hoặc Contact, khóa và kiểm tra bản ghi liên quan trong cùng giao dịch trước khi lưu. Worker tương lai phải chỉ khóa Task → Reminder, không lấy AccountHotel sau Task.

Quyền Hotel, assignment, owner và Contact dùng locking read ở bước ghi, tránh chấp nhận scope cũ từ snapshot REPEATABLE READ của một giao dịch bao ngoài. Đã kiểm thử thu hồi grant và bàn giao bằng kết nối khác trước khi request ghi lấy khóa.

Dùng `crm_customer_request` WP04, operation `task_create/update/start/reschedule/complete/cancel/reopen`, khóa `(actor_user_id, request_key)`, hash payload và kết quả. Generic helper bổ sung replay guard tùy chọn để task còn kiểm tra scope/action của chính task trước khi trả kết quả cũ. Các caller WP04 vẫn giữ hợp đồng cũ.

Deadlock 1213 hoặc lock timeout 1205 được thử lại tối đa **3 lần tổng cộng** khi TaskService tự sở hữu transaction, dùng cùng request key. Khi nằm trong transaction của caller, service không tự thử lại một phần; caller phải rollback và xử lý lại toàn bộ giao dịch. Mutex bận trả 409 để client thử lại cùng key.

## Hợp đồng ghi

Tất cả POST cần đăng nhập và CSRF của Yii. `request_key` gồm 16–64 ký tự chữ/số/`_`/`-`; phân biệt hoa/thường. Mọi sửa đổi thêm `expected_version`.

| POST route | Dữ liệu chính |
|---|---|
| `task/create` | `account_id`, `hotel_id`, `Task`, `request_key`; `report_id`, `assignee_user_id` tùy chọn nhưng phải khớp nguồn/owner hiện tại |
| `task/update&id=…` | `Task`: title, description, action_type, priority, location, contact_person_id |
| `task/reschedule&id=…` | Lịch mới đầy đủ trong Task, reason, allow_overdue nếu cần |
| `task/start&id=…` | expected_version, request_key |
| `task/complete&id=…` | expected_version, request_key, note là kết quả |
| `task/cancel&id=…` | expected_version, request_key, note là lý do |
| `task/reopen&id=…` | Lịch mới đầy đủ trong Task, expected_version, request_key, reason |

Ví dụ lịch hạn ngày đầy đủ cho reschedule/reopen:

```json
{
  "kind": "task",
  "due_mode": "date",
  "due_date": "2030-01-08",
  "due_at_utc": null,
  "starts_at_utc": null,
  "ends_at_utc": null,
  "timezone": "Asia/Ho_Chi_Minh"
}
```

Gửi `format=json` để nhận mutation result `{account_id, task_id, version, schedule_version, status}` hoặc lỗi 400/403/404/405/409/422/503. GET reschedule/reopen chỉ hiển thị form; start/complete/cancel từ chối GET. Các field hệ thống, đổi Account/Hotel/owner/status qua mảng Task bị từ chối.

## Migration và cấu hình

Migration `m261119_090000_prepare_task_workflows` chỉ chạy qua `IsolatedTestDbConnection`, kiểm tra DB thực tế và WP04 đã có:

1. Đổi collation `crm_task.client_request_key` sang `utf8mb4_bin` để index chống trùng phân biệt hoa/thường giống request journal; giữ nguyên giá trị key.
2. Tạo `crm_task_action_seed` lưu quyền trước/sau; bổ sung `start` và `reschedule` cho role đã có `task/update`. Không tự cấp reopen hoặc mở rộng scope Hotel.
3. Down từ chối khi đã có task request, quyền chỉnh sau seed hoặc dữ liệu key sẽ trùng ở collation cũ; schema test trống đã chạy lên/xuống/lên thành công.

`test.php` bật `taskWrite=true` trên DB test. `mvp.php`/web main và console mặc định vẫn `taskWrite=false`, `reminders=false`. Không đổi entry/vhost, DB vận hành, dữ liệu cũ hay quyền mà người dùng đang thử song song. Service còn kiểm tra class kết nối và schema test thực tế, không chỉ feature flag.

Chạy từ repository với PHP 7.4/Yii đang có:

```sh
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php migrate up --interactive=0
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-task-smoke.php
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-task-durability.php
```

DB mặc định `sale_report_mvp_test`; schema thứ hai `sale_report_mvp_test_restore` được chọn qua `SALEREPORT_TEST_DSN`. Để thử giao diện, dùng entry/vhost riêng nạp `protected/config/test.php`; không tự chuyển entry vận hành sang test.

## Kết quả kiểm thử

| Bộ kiểm tra | Kết quả |
|---|---|
| WP05 task smoke | 111 assertions: quyền/scope, CRUD, version/replay, trạng thái, mở lại sau bàn giao, reminder invalidation, local/UTC/DST, ranh giới tuần, việc hạn ngày riêng, các form/POST, JSON/CSRF/XSS, key khác hoa/thường, task gắn Report hơn 7 ngày không đổi Report |
| WP05 durability | 33 assertions: commit thực qua hai kết nối, lỗi ghi Reminder rollback nguyên khối, retry deadlock có giới hạn, khóa Task thật từ kết nối khác, reconnect/replay, hoàn thành đua request đổi hạn cũ, snapshot cũ sau thu hồi quyền/bàn giao |
| WP01 schema / WP02 policy | 96 / 94 assertions đạt |
| WP04 customer / durability | 130 / 20 assertions đạt |
| WP03 import / durability | 116 / 12 assertions đạt |
| Models / management / access | Tất cả đạt |
| Migration test_restore | Lên/xuống/lên và chạy task smoke đạt trên DB thứ hai |
| PHP lint / whitespace | 21 file PHP WP05 đạt; diff whitespace đạt |

Smoke dùng transaction và TEMPORARY users/roles để không làm thay đổi fixture MyISAM. Durability commit dữ liệu giả có mã riêng, gây lỗi bằng trigger chỉ trong DB test, rồi xóa trigger và đúng dữ liệu của lượt chạy trong finally. Sau test không còn Task/request/trigger WP05 phát sinh. Hiện kiểm tra HTML render và POST qua controller thực; chưa QA trực quan trên trình duyệt hoặc benchmark tải pilot.

## Đối chiếu dữ liệu vận hành

Chỉ đọc CHECKSUM/schema `sale_report` trước/sau, không chạy DDL/import hoặc ghi tại đây:

| Bảng | Checksum trước = sau |
|---|---:|
| contact | 1859318667 |
| reports | 1319172983 |
| users | 1097044357 |
| roles | 1675026945 |
| incident | 0 |
| staff_asst | 0 |
| trackings | 3337141388 |
| user_history | 2096163467 |

Schema vận hành giữ 132 cột và 0 bảng `crm_*`. Dữ liệu và cấu hình vận hành chưa thay đổi.

## Điểm nối WP06–WP07

WP06 tích hợp Report + nhiều Task trong cùng transaction; dùng cùng khóa/owner, request key con ổn định cho mỗi Task và rollback toàn bộ khi một Task lỗi. TaskService không tự retry một phần khi caller đang có transaction. Chưa mở ghi Report mới ở WP05.

WP07 bổ sung worker dispatch với khóa Task → Reminder, kiểm tra trạng thái, recipient và schedule_version trước khi phát; không dùng calendar hoặc GET để gửi nhắc. Không cấu hình cron/bật reminders trước bước đó.
