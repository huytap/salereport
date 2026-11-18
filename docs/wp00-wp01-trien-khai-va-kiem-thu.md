# WP00–WP01: Cấu hình DB riêng, schema và model MVP

Ngày thực hiện: 18/11/2026.

## 1. Kết quả đã triển khai

### WP00 — Cấu hình và môi trường kiểm thử

- Web tiếp tục sử dụng `source/confgs/dbconfgs.php`; không thay DSN hoặc dữ liệu vận hành.
- `config/db-test.php` dùng các biến môi trường `SALEREPORT_TEST_DSN`, `SALEREPORT_TEST_USER`, `SALEREPORT_TEST_PASSWORD`. Mặc định DSN là `mysql:host=127.0.0.1;dbname=sale_report_mvp_test`, user root và mật khẩu rỗng cho môi trường local hiện tại. Có thể cấp một tài khoản chỉ có quyền trên schema test rồi truyền qua biến môi trường.
- `IsolatedTestDbConnection` kiểm tra DSN trước khi mở PDO: chỉ cho tên `sale_report_mvp_test` hoặc hậu tố `_...` gồm chữ thường/số; từ chối dbname trùng, rỗng, SQLite và tùy chọn DSN không hỗ trợ. Sau kết nối kiểm tra lại `SELECT DATABASE()`.
- `config/test.php` thay DB web bằng connection được bảo vệ trước khi khởi tạo ứng dụng. Ba smoke test cũ chuyển sang config test.
- `config/console.php` dùng cùng DB test, không còn mặc định SQLite mẫu và không tự trỏ vào DB web. Trong đợt này CLI/migration chỉ hỗ trợ DB test; chưa bổ sung đường chạy production.
- CLI dùng riêng thư mục `migrations/mvp` và bảng lịch sử `crm_migration`, tránh tự chạy migration cũ không liên quan.
- Các cờ `customerWrite`, `taskWrite`, `reminders` mặc định false trong `config/mvp.php`. Chưa có controller/UI MVP hoặc worker được kích hoạt.
- Đã kiểm tra PHP 7.4.33, Yii 1.1.33-dev và MySQL 26.7.0 tại máy hiện tại. Hỗ trợ đổi đường dẫn framework qua `YII_FRAMEWORK_PATH` cho smoke test, `YII_TEST_FRAMEWORK_PATH` cho PHPUnit bootstrap, `YII_CONSOLE_PATH` cho CLI.

### WP01 — Migration và model

Migration: `m261118_170000_create_mvp_schema`.

| Nhóm | Bảng/model mới |
|---|---|
| Hồ sơ và phạm vi | crm_hotel/Hotel, crm_account/Account, crm_contact_person/ContactPerson, crm_account_hotel/AccountHotel, crm_account_market/AccountMarket, crm_user_hotel_access/UserHotelAccess, crm_entity_alias/EntityAlias |
| Công việc và nhắc | crm_task/Task, crm_task_reminder/TaskReminder, crm_notification/Notification |
| Nguồn và lịch sử | crm_import_batch/CustomerImportBatch, crm_import_row/CustomerImportRow, crm_legacy_link/LegacyLink, crm_audit_event/AuditEvent |

- 14 bảng nghiệp vụ mới dùng InnoDB/utf8mb4, có index và 20 khóa ngoại. Tổng số bảng `crm_*` trong DB test là 15 khi tính cả `crm_migration`.
- `reports` có thêm account_id, contact_person_id, hotel_id, identity_state, lock_version, client_request_key và các index cần thiết. Báo cáo cũ nhận trạng thái legacy_only, không tự mapping khách hàng hoặc sinh việc.
- Có khóa ghép chống gắn Contact/Report sai Account hoặc Hotel; Notification phải khớp Task, người nhận và phiên bản Reminder.
- Có unique key cho mã hồ sơ/task, yêu cầu tạo Task, lịch nguồn cũ, lịch nhắc, notification và batch nhập.
- Không tạo FK tới `users` vì engine cũ là MyISAM; model kiểm tra user tồn tại. Chính sách thu hồi quyền/xóa user và giao dịch bàn giao thuộc WP02/WP04–05.
- `MvpActiveRecord` kế thừa CActiveRecord riêng, có whitelist input, kiểm tra kiểu/độ dài/ngày/enum/JSON/user references; timestamp mới dùng UTC. Trường ID hệ thống, actor, status, version và scope không nhận qua mass assignment.
- Các model có lock_version hỗ trợ optimistic locking; bản ghi stale trả CDbException mã 409. Refresh đồng bộ lại version. Xóa bằng instance bị chặn; AuditEvent chỉ cho insert qua model. Đây là lớp bảo vệ persistence, không thay thế policy/service và không chặn SQL trực tiếp của tài khoản DB.
- Task hỗ trợ hạn ngày, hạn giờ, appointment, quy đổi deadline sang UTC và kiểm tra bối cảnh Account/Hotel/Contact/Report. Task đã đóng giữ được người thực hiện lịch sử sau khi owner thay đổi.
- Report thêm quan hệ và bảo vệ các trường mới khỏi mass assignment, có thể chạy khi DB web chưa có cột MVP. Các quan hệ mới chỉ được sử dụng sau khi migration đã áp dụng tại DB đích.

## 2. Chạy lại kiểm thử

Các lệnh dưới đây chạy tại thư mục gốc dự án và dùng PHP 7.4. Trên máy hiện tại, executable tương ứng là `/opt/homebrew/opt/php@7.4/bin/php`.

### Chuẩn bị một schema test trống

Tạo schema `sale_report_mvp_test` bằng công cụ quản trị MySQL hoặc đổi sang tên test có hậu tố qua biến môi trường. Không dùng `sale_report` làm DB test.

Ví dụ cấu hình tùy chọn:

```sh
export SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test'
export SALEREPORT_TEST_USER='root'
```

Mật khẩu nếu có truyền qua `SALEREPORT_TEST_PASSWORD`, không đưa vào mã nguồn. Các lệnh test không tự tạo database hoặc tự xóa dữ liệu/schema có sẵn.

### Tạo fixture và chạy migration

```sh
php source/protected/tests/prepare-isolated-db.php
php source/protected/yiic.php migrate up --interactive=0
```

Script chuẩn bị chỉ đọc phần CREATE/ALTER từ dump để tạo schema legacy, sau đó tạo dữ liệu giả. Không nạp INSERT khách hàng hay tài khoản thật. Script từ chối schema không trống trừ khi đã có marker fixture `wp01-v1`; nếu đã chuẩn bị thì không reset dữ liệu.

### Kiểm thử

```sh
php source/protected/tests/mvp-schema-smoke.php
php source/protected/tests/models-smoke.php
php source/protected/tests/management-smoke.php
php source/protected/tests/access-smoke.php
```

Suite MVP kiểm tra trên các bảng thật của schema test và rollback dữ liệu sau mỗi lượt chạy. Model/management smoke cũ dùng bảng TEMPORARY theo connection; chỉ bỏ FK trong bản sao tạm vì MySQL không hỗ trợ FK ở temporary table. Khóa ngoại thực tế được suite MVP kiểm tra riêng.

### Kiểm tra migration lùi/tiến

```sh
php source/protected/yiic.php migrate down 1 --interactive=0
php source/protected/yiic.php migrate up --interactive=0
php source/protected/yiic.php migrate up --interactive=0
```

Migration down từ chối nếu bất kỳ bảng nghiệp vụ MVP nào có dữ liệu hoặc Report đã dùng các trường MVP. Chạy lại up qua migration history không tạo thêm bảng/cột. Gọi up trực tiếp khi đã có đối tượng sẽ bị chặn, không phải phương thức sửa migration chạy dở.

MySQL DDL tự commit: up có preflight và dọn các đối tượng do chính lượt gọi tạo khi gặp lỗi SQL thông thường. Nếu tiến trình bị kill hoặc máy dừng giữa DDL, không có bảo đảm rollback nguyên khối; kiểm tra schema/history trên bản sao và phục hồi từ backup trước khi thử lại. Không dùng down để xóa dữ liệu MVP đã nhập.

## 3. Bằng chứng kiểm thử đã thực hiện

| Kiểm tra | Kết quả |
|---|---|
| Migration up → down → up trên `sale_report_mvp_test` | Thành công |
| Chạy lại migrate up | Không còn migration mới |
| Suite MVP | 96 assertions đạt |
| Models smoke cũ | Đạt trên schema trước và sau MVP |
| Management smoke cũ | Đạt trên schema trước và sau MVP |
| Access smoke | Đạt trên DB test |
| Sao lưu DB test, phục hồi vào `sale_report_mvp_test_restore` | Thành công; 96 assertions tiếp tục đạt trên DB phục hồi |
| PHP 7.4 syntax checks cuối cùng | 56 file PHP thuộc model/component/config/test/migration và CLI entry đạt |
| git diff --check | Đạt |

Các tình huống được kiểm tra có: DSN vận hành bị từ chối trước PDO, migration từ chối connection thường, nhiều người liên hệ dùng chung email, chuẩn hóa tên tiếng Việt, FK chặn gắn nhầm Contact/Hotel, unique chặn trùng lịch/yêu cầu, ngày không hợp lệ, mốc quá hạn 00:00 giờ Việt Nam, appointment đảo giờ, metadata hoàn thành thiếu, JSON lỗi, mass assignment, update stale/refresh, audit append-only và rollback có dữ liệu bị chặn.

Đây chưa phải kiểm thử hai worker hoặc các service workflow đồng thời: các phần đó chưa được triển khai trong WP00–WP01.

### Xác nhận DB vận hành giữ nguyên

Chỉ đọc checksum của các bảng vận hành trước/sau, không chạy migration hoặc fixture trên `sale_report`:

| Bảng | Checksum trước và sau |
|---|---:|
| contact | 1859318667 |
| reports | 1319172983 |
| users | 1097044357 |
| roles | 3625884759 |
| incident | 0 |
| staff_asst | 0 |
| trackings | 3337141388 |
| user_history | 2096163467 |

Tổng số cột trong schema vận hành vẫn là 132; số bảng `crm_*` vẫn là 0. Checksum là bằng chứng tại hai thời điểm kiểm tra, không thay thế hệ thống theo dõi thay đổi vận hành lâu dài.

## 4. Phần việc tiếp theo

- WP02: CustomerPolicy, kiểm soát scope Hotel/Own/All, action seed và bảo vệ các đường truy cập cũ.
- WP03: service preview/mapping/commit và đối soát nguồn thật. WP01 chỉ tạo cấu trúc dữ liệu hỗ trợ import, chưa nhập Contact/Report thật.
- WP04–05: service Account/Assignment/Task, audit sự kiện nghiệp vụ trong giao dịch, cấp mã và xử lý trạng thái/bàn giao.
- WP06–07: form Report chuẩn và worker tạo nhắc; chưa có cron, gửi thông báo hay UI mới trong lần triển khai này.

Các giả định một owner mỗi Account/Hotel, chia sẻ hồ sơ chung giữa Hotel và một Hotel cho Report mới vẫn giữ theo kế hoạch. Cần xác nhận với người phụ trách nghiệp vụ trước khi chốt UI và cấp quyền pilot. Chưa diễn tập phục hồi DB vận hành thật hoặc triển khai production; phần phục hồi đã thử chỉ dùng DB có dữ liệu giả.
