# WP06 — Sales Report gắn hồ sơ khách hàng và nhiều Task

Triển khai ngày 19/11/2026, tiếp nối WP00–WP05. Luồng mới được bật và kiểm thử trên DB riêng; cấu hình và dữ liệu vận hành giữ nguyên.

## Phần đã triển khai

| Màn hình / đường truy cập Yii | Nội dung |
|---|---|
| `report/create` | Chọn một Hotel, Account và Contact bằng ID; xem thông tin từ hồ sơ chuẩn; nhập báo cáo; thêm/bỏ tối đa 20 việc mới |
| `report/update&id=…` | Sửa nội dung trong thời hạn cho phép và có thể thêm nhiều task mới trong cùng giao dịch; không đổi snapshot/định danh hay sửa task hiện có |
| `report/view&id=…` | Snapshot lịch sử, nội dung, Task liên quan và lịch sử thay đổi có phân trang; thông báo khóa sửa sau 7 ngày |
| Tab Sales Report trong hồ sơ | Link lập Report theo Account/Hotel; các báo cáo liên quan vẫn lọc đúng phạm vi |
| Menu Sales Report | Mở form mới theo action thực tế; import cũ không hiện khi bật luồng Report mới |

Ví dụ URL: `/index.php?r=report/create&account_id=123&hotel_id=1`. Tái sử dụng layout Yii/Bootstrap và danh sách Report đang có. Nội dung tự do được escape tại trang chi tiết và grid, giữ xuống dòng an toàn.

Form thêm/bỏ dòng Task bằng POST có CSRF, giữ nội dung và request key, chưa ghi dữ liệu khi chỉ chỉnh danh sách dòng. Lỗi task có key theo dòng và hiện ngay trong khối việc tương ứng; lỗi validation giữ Report, các dòng task và liên hệ mới đã nhập.

## Luồng lưu nguyên khối

1. Kiểm tra action, user Active, Account/Hotel và scope Own/Hotel/All; nhận DTO có whitelist, không nhận snapshot/audit/status/ID hệ thống qua mảng Report.
2. Lấy mutex dùng chung với import/hồ sơ/task, khóa Hotel theo thứ tự → Account → AccountHotel. Kiểm tra lại scope/owner hiện tại bằng locking read.
3. Lấy thông tin Contact hiện có hoặc kiểm tra liên hệ mới. Đọc market hiện tại dưới khóa và chuẩn bị snapshot từ dữ liệu chuẩn.
4. Validate Report và **tất cả** Task trước khi insert; gom lỗi theo `Report.field`, `new_contact.field`, `tasks.0.field`… Không ghi một phần khi dòng thứ hai hoặc cuối cùng sai.
5. Khi dữ liệu hợp lệ: lưu Contact mới nếu có → Report → các Task, lịch nhắc và audit → request journal. Tất cả trong cùng transaction.
6. Bất kỳ bước ghi nào lỗi đều rollback cả Contact, Report, Task trước đó, reminder, audit và các request con. Không sửa dữ liệu `users`/`roles` MyISAM trong giao dịch nghiệp vụ.
7. Gửi lại cùng request key/payload trả kết quả cũ nếu quyền hiện tại vẫn hợp lệ. Key cũ với nội dung khác trả 409. Key phân biệt hoa/thường.

Task luôn giao cho owner hiện tại của đúng Account/Hotel. Sales Own nhận việc cho chính mình; quản lý không tùy ý đổi assignee qua form Report. Muốn đổi sales phải bàn giao WP04. Hồ sơ chưa có owner vẫn lập được Report không kèm task; nếu thêm task thì bị từ chối.

Thêm nhanh Contact chỉ có ở form tạo Report, cần quyền `account/contactcreate` và cờ customerWrite; không đồng thời chọn Contact hiện có và tạo mới. Liên hệ mới mặc định được các task kế thừa, trừ khi từng dòng chọn Contact khác hoặc không chọn. Kiểm tra trùng họ tên trong cùng Account, không tự merge.

## Snapshot và lịch sử

`reports` tiếp tục là nguồn hoạt động sales, không thêm bảng Activity song song. Các cột cũ như company_name, first/last_name, position, phone, email, company_address, market, hotels… được điền bởi server lúc tạo.

`snapshot_json` lưu phiên bản 1, thời điểm UTC, thông tin Account cần cho báo cáo, Contact, Hotel và owner tại lúc lập. Không sao chép ghi chú/DOB hoặc dữ liệu thô không cần thiết vào snapshot. Snapshot Contact có ưu tiên điện thoại/email liên hệ, dự phòng bằng thông tin chung của Account.

Sửa Account/Contact/market hoặc bàn giao về sau không làm đổi snapshot hoặc các cột lịch sử trong Report. Sửa Report chỉ nhận các trường nội dung:

`date`, `sales_type`, `objective`, `expected_result`, `report`, `follow_up`, `action`, `new_account`, `comments`, `highlight`.

Ngày tiếp xúc và nội dung là bắt buộc khi tạo; danh mục loại tiếp xúc/mục đích/nhóm nhu cầu dùng cấu hình hiện có. Khi sửa Report legacy, giá trị danh mục cũ không bị ép thay chỉ vì sửa một trường khác. Các trường được gửi sửa phải hợp lệ.

Nội dung sửa có audit before/after, actor, thời gian UTC; có thể đọc lịch sử trên trang chi tiết. Người tạo và thời điểm tạo gốc giữ nguyên. Update có điều kiện theo lock_version; request dùng phiên bản cũ nhận 409.

Report đã lập không đổi Account/Hotel/Contact hoặc snapshot qua form sửa. Muốn sửa hồ sơ chuẩn dùng màn hình hồ sơ; muốn xử lý task đã có dùng TaskService WP05. Thêm task mới khi sửa Report không cập nhật hoặc làm mất trạng thái/kết quả của task trước đó.

## Quy tắc 7 ngày và dữ liệu cũ

Giữ cách tính của luồng cũ: cho sửa đến hết ngày lịch `ngày tạo + 7` theo Asia/Ho_Chi_Minh, khóa từ 00:00 ngày tiếp theo. Không tính từ ngày tiếp xúc do người dùng nhập. Report mới dùng created_at_utc để xác định ngày tạo; Report legacy dùng added_date hiện có. Thiếu/không hợp lệ/ngày tạo tương lai thì không mở quyền sửa. All cũng không bỏ qua khóa này.

Report quá hạn vẫn xem được lịch sử, task liên quan và mở luồng tạo task độc lập WP05. Thay đổi trạng thái/hạn hoặc hoàn thành một task không sửa nội dung Report và không phụ thuộc tuổi của Report.

Không tự suy task từ `ent`, `sales`, `site_ins`, `follow_up`, `action` hay `done`. Report mới để ba ngày lịch cũ và done là NULL; follow_up/action là văn bản tóm tắt. Update Report legacy không gửi lại các cột zero-date không thay đổi, không tạo ngày giả.

Report chưa mapping Account/Hotel vẫn cần chuyển đổi WP03 trước khi sửa bằng luồng mới. Khi bật reportWrite, đường sao chép theo tên công ty, import và xóa cũ bị chặn, kể cả All; cờ này bảo vệ Report độc lập với customerWrite.

`LegacyImportSource::hash()` bỏ qua ba cột kỹ thuật mới để việc thêm schema không làm hỏng fingerprint đã lưu của WP03. Snapshot nguồn import và LegacyLink không bị viết lại. Nếu được phép sửa nội dung một Report legacy sau import, đối soát có thể báo SOURCE_DRIFT đúng với thay đổi đã thực hiện; kiểm tra audit thay vì sửa fingerprint cũ để che mất lịch sử.

## Dữ liệu và migration

Migration `m261119_110000_prepare_report_workflows` chỉ chạy qua `IsolatedTestDbConnection`, xác minh đúng schema test và WP05 đã có:

- Thêm `reports.snapshot_json`, `created_at_utc`, `updated_at_utc`, nullable cho dữ liệu legacy.
- Chuyển Report sang utf8mb4 để lưu Unicode 4 byte; mở rộng company_name lên 255, last_name/position lên 128, email 200, company_address 1024 và market 1300. Không cắt dữ liệu canonical dài để vừa cột cũ.
- Cho sales/site_ins NULL; ent đã nullable. Không đổi giá trị ngày hoặc văn bản đang có.
- Đổi riêng client_request_key sang utf8mb4_bin sau bước chuyển charset, giữ tính phân biệt hoa/thường giống request journal.
- `crm_report_schema_seed` lưu metadata cấu trúc cũ; `crm_report_action_seed` lưu quyền trước/sau. Role đã có Report/view và Account/create hoặc update được bổ sung action Report tương ứng; không mở scope Hotel và không cấp ghi cho Reservation đọc.
- Down từ chối khi đã có dữ liệu workflow, quyền chỉnh sau seed hoặc dữ liệu không thể khôi phục an toàn vào cột/charset/nullability cũ. Không ép truncate, thay NULL hoặc sửa nguồn để rollback.

DDL tạm bỏ NO_ZERO_DATE/NO_ZERO_IN_DATE trong chính session migration và khôi phục SQL mode bằng finally. Đã kiểm tra vòng lên/xuống/lên bảo toàn zero-date, toàn bộ hàng nguồn, fingerprint, cấu trúc cột và JSON quyền gốc.

## Service và transaction

`ReportService` quản lý snapshot, whitelist, quyền, thời hạn sửa, optimistic version, Contact nhanh và nhiều Task. `TaskService` tách `prepareCreate()`/`persistPrepared()` cho giao dịch tổng; đường tạo task riêng WP05 dùng cùng logic. API nội bộ này yêu cầu transaction đang hoạt động.

Request cấp Report dùng `crm_customer_request` với operation report_create/report_update. Mỗi task có client_request_key con ổn định từ actor, operation, key cha và vị trí dòng; liên hệ nhanh cũng có key con. Chống gửi trùng ở cấp Report, không commit riêng từng task.

Deadlock 1213 hoặc lock timeout 1205 được thử lại tối đa 3 lần tổng cộng khi ReportService sở hữu transaction. Thử lại **toàn bộ** Report và các task, không chỉ dòng gặp lỗi. Nếu caller đã mở transaction, caller phải rollback/xử lý lại toàn giao dịch. Helper savepoint không che mất lỗi gốc khi MySQL đã tự rollback giao dịch.

Task/reminder đã giao hoặc phát vẫn dùng status, recipient và schedule_version của WP05. WP06 chỉ lập lịch pending; không dispatch hoặc bật cron.

## Hợp đồng POST

Các route cần đăng nhập, CSRF Yii và `request_key` 16–64 ký tự chữ/số/`_`/`-`. Gửi `format=json` để nhận kết quả hoặc error_code/message/field_errors; không trả SQL hoặc stack trace.

Tạo Report nhận các field gốc: account_id, hotel_id, contact_person_id tùy chọn, Report, tasks, request_key, new_contact tùy chọn.

Ví dụ phần `tasks`:

```json
[
  {
    "fields": {
      "title": "Gửi báo giá",
      "kind": "task",
      "due_mode": "date",
      "due_date": "2030-01-08",
      "due_at_utc": null,
      "starts_at_utc": null,
      "ends_at_utc": null,
      "timezone": "Asia/Ho_Chi_Minh"
    },
    "allow_overdue": false,
    "reason": ""
  }
]
```

Tối đa 20 dòng, index liên tục từ 0. `fields` dùng DTO Task WP05; có thể gửi UTC hoặc các mốc `_local` có timezone, không gửi đồng thời hai dạng. Không gửi contact_person_id trong một dòng thì kế thừa Contact của Report; gửi NULL/chuỗi rỗng để không gắn Contact. Overdue cần xác nhận và lý do theo WP05.

Update nhận Report nội dung, tasks **mới**, expected_version, request_key. Không nhận field gốc account_id/hotel_id/contact_person_id hoặc new_contact để đổi lịch sử.

Kết quả có account_id, report_id, version, contact_id và tasks chứa task_id/version/schedule_version/status. Replay thêm `replayed=true`; frontend nên mở lại chi tiết để đọc trạng thái hiện tại.

## Cấu hình và cách kiểm thử

`params.mvp.reportWrite` mặc định false. Chỉ `test.php` bật true trên DB bị guard; web main/console mặc định vẫn tắt, các cờ customerWrite/taskWrite/reminders của vận hành giữ nguyên. Chưa thay entry/vhost hoặc cấu hình DB vận hành.

Từ thư mục repository, dùng PHP 7.4/Yii hiện có:

```sh
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php migrate up --interactive=0
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-report-smoke.php
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-report-durability.php
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_restore' /opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-report-migration.php
```

DB mặc định sale_report_mvp_test; fixture phải mang marker wp01-v1 và chỉ có dữ liệu giả. Test migration chỉ chấp nhận sale_report_mvp_test_restore. Muốn thử giao diện dùng entry/vhost test nạp protected/config/test.php, không đổi entry vận hành.

## Kết quả

| Bộ kiểm tra | Kết quả |
|---|---|
| WP06 Report smoke | 86 assertions: snapshot dài/Unicode/bất biến, nhiều task và lỗi từng dòng, prevalidation/rollback, Contact nhanh, key/version, quyền/bàn giao, 7 ngày, legacy zero-date, fingerprint, HTML/JSON/CSRF/XSS, chặn route cũ |
| WP06 Report durability | 39 assertions: lỗi reminder ở task cuối rollback cả Contact/Report/task trước; retry toàn bộ tối đa 3 lần; commit độc lập/reconnect/replay; append không đổi task cũ; khóa thật từ peer; snapshot scope cũ sau revoke/handover |
| WP06 migration | 13 assertions: history lên/xuống/lên, cấu trúc/quyền/hàng nguồn chính xác, zero-date, fingerprint, SQL mode, binary key |
| WP01 schema / WP02 policy | 96 / 94 assertions đạt |
| WP03 import / durability | 116 / 12 assertions đạt |
| WP04 customer / durability | 130 / 20 assertions đạt |
| WP05 task / durability | 111 / 33 assertions đạt |
| Models / management / access | Tất cả đạt |
| PHP lint / whitespace | 22 file PHP WP06 đạt; diff whitespace đạt |
| DB test thứ hai | Report smoke 86 assertions đạt trên test_restore sau migration |

Smoke dùng transaction và TEMPORARY users/roles để giữ nguyên fixture MyISAM. Durability commit dữ liệu giả có mã riêng và trigger lỗi tạm trong DB test; finally dọn đúng dữ liệu/trigger của lượt chạy. Không reset hoặc truncate schema chung. Kiểm tra form qua controller render/POST thực; chưa QA trực quan trên trình duyệt hoặc benchmark tải pilot.

## Đối chiếu vận hành

Chỉ đọc CHECKSUM/schema sale_report trước/sau, không chạy migration/import hoặc ghi tại đây:

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

Schema vận hành giữ 132 cột, 0 bảng crm_*. Không bật gửi nhắc hoặc cờ ghi MVP trên vận hành. WP07 tiếp theo là worker reminder, thông báo trong ứng dụng và giám sát dispatch.
