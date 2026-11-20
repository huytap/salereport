# WP07 — Worker reminder, thông báo trong ứng dụng và giám sát

Triển khai ngày 19/11/2026 trên nền WP00–WP06. Worker chỉ được chạy thủ công trên DB test. Không cài đặt/bật cron, không thêm automation và không thay đổi dữ liệu vận hành.

## Chức năng đã có

| Phần | Đường truy cập / lớp | Nội dung |
|---|---|---|
| Worker | `ReminderDispatchService`, `ReminderCommand` | Batch pending đã đến hạn, kiểm tra lại điều kiện, tạo notification một lần trong DB |
| Dry-run/check | CLI `reminder dispatch --dryRun=1`, `reminder check` | Chỉ đọc; không ghi notification, state, attempt hoặc heartbeat |
| Inbox | `reminder/admin` | Chưa đọc/Đã đọc/Tất cả, chỉ thông báo của chính người nhận còn hợp lệ, 25 dòng/trang |
| Chuông | `reminder/summary` | Unread count, tối đa 5 thông báo; số việc của tôi quá hạn là chỉ số riêng |
| Đã đọc | POST `reminder/read&id=…` | Có CSRF, recipient-only, gọi lại giữ nguyên thời gian đọc đầu tiên |
| Giám sát | `reminder/monitor` | Chỉ All + action monitor; heartbeat, queue, backlog, lỗi, thời gian và 20 lượt chạy gần nhất |
| URL cũ | `ajax/reminderdaily`, `ajax/reminder`, `ajax/reminder1` | Trả 410 cho người đã đăng nhập, không suy task hoặc phát nhắc từ ngày Report cũ |

Tái sử dụng Yii/Bootstrap và menu đang có. Action reminder có nhãn trong danh mục cấu hình quyền. Cấp action monitor không tự cấp All; Hotel manager vẫn bị từ chối giám sát toàn hệ thống.

## Worker và tính nhất quán

1. Xác minh PHP CLI, kết nối `IsolatedTestDbConnection`, tên DB thực tế, schema WP07 và cờ reminders.
2. Đọc tối đa 200 reminder pending đến hạn theo mặc định; limit hợp lệ 1–500. Không đọc các cột lịch Report cũ để tạo thêm reminder.
3. **Mỗi reminder một transaction độc lập**: khóa Task → Reminder → Notification. Không lấy khóa Hotel/AccountHotel/grant sau Task, tránh đảo thứ tự với TaskService/AssignmentService.
4. Dưới khóa Task, kiểm tra task còn mở; schedule_version, recipient, owner, Account/Hotel/Contact đang hoạt động; user Active, quyền xem Task/inbox và Hotel grant hiện tại. All hợp lệ không cần grant Hotel riêng. Worker là tác vụ hệ thống, không mượn quyền hoặc giả actor user 1.
5. Slot không còn hợp lệ chuyển cancelled với mã nguyên nhân, không tạo notification. Không gửi nhắc trước hạn/hạn ngày đã hết ý nghĩa nếu worker trễ qua deadline; reminder overdue chỉ hợp lệ khi task đã quá hạn.
6. Slot hợp lệ: insert Notification theo UNIQUE(reminder_id), rồi đánh dấu delivered và thời gian UTC trong **cùng transaction**. Nếu đã có notification cùng context, chỉ khôi phục trạng thái delivered, giữ nguyên notification/read history. Context xung đột thì hủy slot với NOTIFICATION_CONFLICT.
7. Lỗi sau insert notification nhưng trước cập nhật delivered sẽ rollback cả hai. Reminder còn pending để thử lại; ghi attempt/last_error_code trong transaction riêng sau rollback, cùng thứ tự khóa. Một reminder lỗi không làm mất các reminder khác đã commit.
8. Hai process cùng chọn một slot: một process tạo notification; process còn lại nhận khóa sau và bỏ qua slot đã delivered. Không dùng mutex toàn hệ thống để tuần tự hóa hai worker.

Deadlock 1213 được thử tối đa 3 lần tổng cộng cho một reminder. Lock timeout chỉ ghi nhận/bỏ qua để lần sau thử lại. Worker tạm dùng lock wait timeout 2 giây và khôi phục cấu hình session sau chạy. Có ngân sách xử lý 20 giây, kiểm tra trước mỗi slot; slot đang chờ khóa/ghi lỗi có thể kéo dài thêm vài giây. Batch có thể còn việc cho lượt kế tiếp.

`attempt_count` là số lượt xử lý slot được ghi nhận (thành công, hủy hoặc lỗi); retry SQL nội bộ trong cùng lượt không tăng riêng. Thành công xóa last_error_code. Các mã lỗi vận hành được lưu dạng DB_WRITE_FAILED/DEADLOCK/LOCK_TIMEOUT, không lưu SQL, stack trace hay nội dung khách hàng.

Các mã hủy gồm TASK_CLOSED, STALE_SCHEDULE, STALE_RECIPIENT, INACTIVE_USER, ACTION_REVOKED, INACTIVE_CONTEXT, OWNER_CHANGED, HOTEL_ACCESS_REVOKED, INACTIVE_CONTACT, INVALID_KIND, MISSED_DEADLINE, INVALID_OVERDUE_SLOT và NOTIFICATION_CONFLICT.

Đảm bảo chống trùng ở **notification trong DB**, chưa mở rộng sang email/push/SMS/Zalo.

## Đọc thông báo và phân quyền

`CustomerPolicy::notificationCriteria()` dùng chung cho list/count/summary: recipient hiện tại, reminder đã delivered và đúng kind; task còn mở, đúng phiên bản/người nhận; owner và Account/Hotel/Contact còn hợp lệ, scope và quyền xem Task còn hiệu lực.

- All không được đọc/đánh dấu hộ inbox người khác; monitor toàn hệ thống là quyền riêng, không mở quyền inbox của sales.
- Đổi hạn, hoàn thành, hủy, bàn giao, thu hồi grant hoặc mất quyền xem Task/inbox làm thông báo cũ mất hiệu lực ngay ở truy vấn tiếp theo. Task link kiểm tra lại quyền khi mở.
- Notification đã phát giữ dữ liệu lịch sử. Tab Tất cả là tất cả **thông báo còn hợp lệ**, không phải kho lịch sử của việc đã đóng.
- GET summary/inbox không phát nhắc hoặc đánh dấu đã đọc. Mở Task không tự đánh dấu notification; dùng nút POST “Đánh dấu đã đọc”. Read chỉ cập nhật read_at_utc, không hoàn thành Task hoặc thay lịch.
- Đánh dấu đã đọc khóa Task → Reminder → Notification rồi kiểm tra lại context/quyền. Read không ghép vào transaction nghiệp vụ khác, tránh dùng snapshot quyền cũ.
- Cờ reminders điều khiển **dispatch**. Tắt dispatch không xóa thông báo và không cấm đọc/đánh dấu thông báo hợp lệ trên schema test đã có.

Thông báo chỉ tham chiếu Task; không sao chép email, điện thoại, nội dung Report hoặc thông tin liên hệ vào notification. UI dùng text/HTML encoding cho tiêu đề và thông tin hiển thị.

## Polling giao diện

`source/js/mvp-reminders.js` gọi GET summary bất đồng bộ, timeout 8 giây, khoảng 60 giây khi tab đang hiển thị. Không tạo request chồng nhau; dừng timer khi tab ẩn, refresh khi quay lại. Lỗi quyền/mạng/JSON xóa dữ liệu preview cũ và hiện trạng thái chưa tải được, không giữ tiêu đề task cũ sau lỗi quyền.

Chuông đếm notification chưa đọc; “Việc của tôi quá hạn” đếm task độc lập. Polling không dispatch, không tạo notification và không đánh dấu đã đọc. Độ trễ cập nhật DOM tối đa theo chu kỳ polling; server kiểm tra scope lại ngay mỗi lần fetch hoặc mở Task.

Quản trị All thấy banner khi worker chưa chạy, heartbeat cũ, có lượt mắc kẹt, backlog hoặc lỗi. Banner/monitor là phép quan sát dữ liệu, không chứng minh cron đã được cài hoặc process đang chạy liên tục.

## Telemetry và giám sát

Migration `m261119_130000_prepare_reminder_dispatch` chỉ nhận schema test đã có WP06:

- `crm_reminder_dispatch_run`: run_key, start/heartbeat/finish UTC, status running/completed/partial/failed, selected/processed/created/recovered/cancelled/skipped/error counts, duration_ms, error_code; dùng InnoDB.
- `crm_reminder_action_seed`: JSON quyền trước/sau; thêm monitor cho role All trong allowlist đã có reminder/admin. Không tự thêm monitor cho Hotel admin, không đổi Hotel grants.
- Không đổi cấu trúc hoặc dữ liệu Task/Reminder/Notification và bảng legacy trong migration.
- Down từ chối khi có dispatch history hoặc quyền bị sửa sau seed, không xóa lịch sử hoặc ghi đè chỉnh sửa của quản trị.

Lượt chạy live ghi heartbeat khi bắt đầu, sau từng slot và khi kết thúc. Lượt hoàn tất không có lỗi, kể cả queue trống hoặc chỉ hủy slot cũ, cập nhật lần thành công. Lượt có lỗi là partial, lỗi hạ tầng là failed; process bị dừng giữa chừng có thể để lại running để cảnh báo heartbeat cũ. Các slot đã commit vẫn giữ nguyên và lượt sau tiếp tục phần pending.

Các cảnh báo:

| Mã | Điều kiện |
|---|---|
| DISABLED | Cờ dispatch của cấu hình/process hiện tại tắt |
| NEVER_COMPLETED | Chưa có lượt completed |
| STALE_HEARTBEAT | Lần completed gần nhất quá 3 phút |
| STUCK_RUN | Một lượt running không cập nhật heartbeat quá 3 phút |
| BACKLOG | Pending quá mốc notify_at hơn 5 phút |
| ERRORS | Pending có last_error_code hoặc lượt gần nhất partial/failed |

Queue hiển thị tổng pending, đã đến hạn, trễ hơn 5 phút, pending lỗi và mốc pending sớm nhất. Monitor hiển thị lượt gần nhất và 20 lượt gần nhất, thời gian xử lý, thông báo tạo mới/khôi phục, số hủy/lỗi. Chỉ có chỉ số vận hành; không lộ tên/tiêu đề task của Hotel khác qua endpoint monitor.

Không coi pending hoặc task quá hạn là “đã nhắc”. Chỉ notification đã được commit và reminder delivered mới hiện ở inbox.

## CLI an toàn và cách chạy thủ công

Web main/`mvp.php` và console mặc định vẫn `reminders=false`. Chỉ `test.php` bật cho kiểm thử. `--enableTest=1` kiểm tra đúng DB test rồi bật **riêng process CLI hiện tại**, không viết file config, không sửa cờ DB, không cài cron.

Ví dụ từ repository, PHP 7.4/Yii hiện có, schema worker chuyên biệt:

```sh
# Các lệnh dưới đây chỉ đọc, không ghi heartbeat hoặc attempt.
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_reminder' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php reminder dispatch --dryRun=1 --limit=200
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_reminder' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php reminder check

# Chạy một batch thật trên DB test, chỉ khi có chủ đích kiểm thử.
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_reminder' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php reminder dispatch --enableTest=1 --limit=200
```

Không có opt-in thì dispatch live bị từ chối. Exit code: 0 hoàn tất/dry-run/check thành công; 1 batch partial có lỗi; 2 lỗi guard/tham số/hạ tầng. JSON không chứa SQL/stack trace. `reminder check` trong console mặc định sẽ báo DISABLED kể cả từng chạy manual có enableTest; đó là trạng thái cờ hiện tại của process check, không phải thao tác tắt/bật cờ lưu trữ.

`check`/dry-run không ghi lịch sử và không làm heartbeat trở nên khỏe giả. Dry-run chỉ dự báo tại lúc đọc, live worker vẫn phải kiểm tra lại dưới khóa.

Chưa thêm command dispatch qua HTTP; URL cũ trả 410. Không có lệnh cài cron trong đợt này.

## Kiểm thử

Đã tạo thêm schema `sale_report_mvp_test_reminder` chỉ cho worker, tách khỏi schema đang dùng thử UI. Chuẩn bị bằng `prepare-isolated-db.php` chỉ lấy DDL trong dump rồi tạo fixture giả; không nạp INSERT của khách hàng thật. Marker phải là wp01-v1.

Hai bài test worker yêu cầu schema `sale_report_mvp_test_reminder` hoặc hậu tố tương ứng sạch. Nếu có dữ liệu khác, test từ chối thay vì reset/truncate. Fixtures commit thật để kiểm chứng bằng kết nối/process khác, rồi xóa đúng ID của lượt chạy. Users/roles dùng shadow table cho smoke; recipient thật được tạo trong durability cũng là dữ liệu giả có ID riêng và được dọn.

```sh
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_reminder' /opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-reminder-smoke.php
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_reminder' /opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-reminder-durability.php
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_restore' /opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-reminder-migration.php
node source/protected/tests/mvp-reminder-client-smoke.js
```

| Bộ kiểm tra | Kết quả |
|---|---|
| WP07 smoke | 99 assertions: dry-run/check không ghi, guard DB trước PDO, eligibility/cancel reason, hạn ngày/quá hạn, recovery/conflict, recipient-only read, scope/action/All, thay lịch/đóng/bàn giao, health/banner, HTML/JSON/CSRF/XSS, URL cũ và nhãn quyền |
| WP07 durability | 36 assertions: lỗi sau insert notification rollback, retry tối đa 3 lần, reconnect/recovery, hai process cùng chọn một slot chỉ tạo một notification, đóng task/revoke trước worker claim, CLI read-only/disabled, cleanup |
| WP07 migration | 23 assertions: lên/xuống/lên, nguồn/task/notification/grant/cột không đổi, khôi phục quyền, All-only monitor và rollback guards |
| WP07 client | 13 assertions: async GET, không dispatch/chồng request, pause/resume khi ẩn tab, text node chống XSS, xóa dữ liệu cũ sau lỗi quyền/JSON |
| WP01 / WP02 | 96 / 94 assertions đạt |
| WP03 import / durability | 116 / 12 assertions đạt |
| WP04 customer / durability | 130 / 20 assertions đạt |
| WP05 task / durability | 111 / 33 assertions đạt |
| WP06 Report / durability | 86 / 39 assertions đạt |
| Models / management / access | Tất cả đạt |
| PHP lint / JS syntax / whitespace | 21 file PHP WP07 đạt, JS syntax và diff whitespace đạt |

Các process test được chờ kết thúc và dọn trước cleanup. Trigger gây lỗi chỉ nằm trong schema worker test, được xóa sau chạy. Giám sát không lưu query hoặc nội dung khách hàng vào error code. Kiểm tra giao diện qua controller render/POST và mô phỏng DOM/XHR; chưa QA trực quan trên browser hoặc benchmark tải pilot.

## Đối chiếu vận hành

Chỉ đọc checksum và schema sale_report trước/sau, không chạy migration/dispatch/import hoặc ghi tại đây:

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

Schema vận hành giữ 132 cột và 0 bảng crm_*. Entry/vhost và cấu hình vận hành không thay đổi. Không bật cron, không để lại process worker nền hoặc dữ liệu thông báo của bài test.

Xác minh cuối ngày 20/11/2026: checksum 8 bảng vận hành tiếp tục khớp bảng trên; schema vẫn 132 cột và 0 bảng crm_*. Schema worker test không còn Account/Task/Reminder/Notification/dispatch run hoặc trigger WP07 của các lượt thử; users chỉ còn tài khoản fixture ban đầu. Dispatch history ở schema test giao diện cũng bằng 0. Cờ reminders trong cấu hình vận hành vẫn false.
