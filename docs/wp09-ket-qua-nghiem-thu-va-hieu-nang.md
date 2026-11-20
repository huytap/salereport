# WP09 — Kết quả nghiệm thu kỹ thuật và đo truy vấn

Cập nhật sau sửa replay mở lại task (20/11/2026): **toàn bộ 21/21 bước đạt, 962 assertions** trên bốn DB test riêng. Đã đối soát checksum vận hành và dọn fixture. [Bàn giao và kết quả mới nhất](wp09-ban-giao-sau-sua-replay.md), [evidence đầy đủ](wp09-replay-acceptance-evidence.json). Lượt này chạy tự động; kết quả browser trước đó và chữ ký nghiệp vụ được theo dõi riêng.

Ngày 20/11/2026. Hoàn thành phần kỹ thuật và chuẩn bị UAT/pilot trong phạm vi DB test. **Chưa có UAT nghiệp vụ ký xác nhận hoặc pilot production**. [Runbook và bảng nghiệm thu](wp09-van-hanh-va-ke-hoach-pilot.md) ghi rõ người phụ trách và các điều kiện còn chờ.

Cập nhật sau UAT bằng trình duyệt: **9/9 kịch bản đạt**, gồm import/replay/skip archive và worker/inbox/stale heartbeat. Đã sửa lỗi phát hiện, hồi quy **21/21 bước, 955 assertions**. Xem [biên bản browser UAT và ảnh](wp09-browser-uat-va-ket-qua-nghiem-thu.md). Các số 949 và benchmark bên dưới là mốc nghiệm thu kỹ thuật trước lượt browser; chữ ký nghiệp vụ vẫn chờ.

## 1. Kết quả bộ nghiệm thu

- Runner đạt **21/21 bước**, với **949 assertions có đếm số**, cùng các suite model/management/access và benchmark. Evidence: [JSON nghiệm thu](wp09-acceptance-evidence.json).
- WP09 mới có 47 assertions cho 9 luồng UAT tự động và incident/recovery, 10 cho migration/preflight, 7 cho dump/restore. Các assertions đếm số còn lại là hồi quy WP01–07; không cộng các bước nghiệp vụ chưa được người dùng ký.
- Hồi quy gồm quyền Own/Hotel/All, CSRF, scope stale, transaction/rollback/retry, lock nhiều connection, import/handover, Report nhiều task, 7 ngày khóa sửa, timezone/DST, hai worker, inbox và client polling.
- Thử đủ lỗi con trong transaction và process worker riêng. Chỉ process CLI test được bật tạm; không cài hay bật cron.
- PHP lint 8 file mới/thay đổi và Python AST đạt; diff whitespace đạt. Mốc này chưa chạy trình duyệt; lượt browser bằng actor giả được ghi trong biên bản cập nhật ở trên. Chưa có người dùng nghiệp vụ ký hoặc tải HTTP đồng thời.

## 2. Thay đổi truy vấn và bằng chứng

Task list phân trang theo query đã scope, rồi tải nhãn theo nhóm ID của trang; calendar eager-load Account. Migration `m261120_110000_prepare_pilot_indexes` thêm ba index có đo EXPLAIN: `crm_account(status,name,id)`, `crm_task(kind,due_mode,due_date,id)`, `crm_task(deadline_at_utc,id)`. Up/down/up không đổi dữ liệu; down/preflight phát hiện index bị sửa định nghĩa.

Fixture đã commit và cập nhật thống kê bằng ANALYZE trên DB performance riêng: 2.000 Account, 16.000 Task, 2.001 Report và 2.000 Notification giả. Mốc số lượng vận hành chỉ được đọc để so quy mô: Contact 1.260, Report 155. Không sao chép nội dung khách hàng vào benchmark.

Mỗi đường đọc có 21 mẫu warm serial, tổng 315 mẫu; task list có đọc các quan hệ mà bảng HTML cần. Dùng bộ đếm thực thi command nhẹ, không giữ log profiler cho từng SQL. Baseline lấy đúng TaskReadService WP08 đã commit và bộ index trước WP09; sau đo luôn áp lại index WP09. Dữ liệu giả được dọn theo ID sau mỗi lần chạy. [JSON trước/sau và EXPLAIN](wp09-performance-evidence.json).

| Đường đọc | Số SQL trước → sau | p95 local trước → sau (ms) |
|---|---:|---:|
| Own/accounts | 13 → 13 | 3.753 → 3.939 |
| Own/tasks_render_data | 86 → 14 | 20.702 → 16.258 |
| Own/calendar | 164 → 6 | 33.491 → 12.19 |
| Own/account_detail | 17 → 17 | 1.587 → 2.293 |
| Own/summary | 17 → 17 | 12.287 → 13.727 |
| Hotel/accounts | 13 → 13 | 4.365 → 5.604 |
| Hotel/tasks_render_data | 86 → 14 | 23.711 → 16.11 |
| Hotel/calendar | 164 → 6 | 35.624 → 12.901 |
| Hotel/account_detail | 17 → 17 | 1.624 → 2.243 |
| Hotel/summary | 17 → 17 | 2.899 → 1.747 |
| All/accounts | 11 → 11 | 3.274 → 3.016 |
| All/tasks_render_data | 85 → 13 | 17.591 → 1.907 |
| All/calendar | 313 → 5 | 50.71 → 5.548 |
| All/account_detail | 15 → 15 | 1.274 → 1.179 |
| All/summary | 20 → 20 | 7.064 → 6.203 |

EXPLAIN chuyển Account list từ scan/filesort sang `ix_account_pilot_list`; lịch ngày từ scan 16.000 dòng sang range `ix_task_pilot_dates` (ước lượng khoảng 269 dòng); trang Task toàn hệ thống dùng `ix_task_pilot_due` với limit 25. Chính sách và ranh giới dữ liệu vẫn được kiểm tra bởi các suite tích hợp.

Các đường đọc được kiểm tra p95 dưới 2.000 ms trên fixture local; Task list/calendar có budget tối đa 50 SQL để bắt N+1. Không phải mọi p95 đều giảm: giữ nguyên các số đo trong bảng để đánh giá. Đây là thời gian service serial, chưa gồm browser/network hay tải nhiều phiên; chưa đủ để cam kết p95 2 giây và nhắc trong 2 phút trên production. Cần đo staging có phân bố quyền, tìm kiếm chuỗi, trang sâu, Account nhiều lịch sử và lịch dày theo runbook.

Môi trường báo PHP 7.4.33, MySQL 26.7.0 / Homebrew, kiến trúc arm64. Bảng reports dùng InnoDB, users/roles dùng MyISAM, đã đối chiếu cùng engine với môi trường vận hành hiện tại. Không coi benchmark local là tải production đại diện.

## 3. Vận hành và phục hồi

`yiic pilot check --actor=…` chỉ đọc schema/index/engine/flags/runtime/readiness/worker health; không sửa dữ liệu và không tự quản lý scheduler. Test snapshot toàn bộ rows trước/sau preflight không thay đổi. `schema_ok=true` không đồng nghĩa đủ quyền mở ghi hoặc đã hoàn thành UAT.

Bài dump/restore tạo Account/Contact/Report/Task Unicode (có emoji) và reminder giả, dump sang file mode 0600, restore vào schema test mới có nonce. Đối chiếu hash **37 bảng** và Report snapshot, reminder plans, không phát Notification. Đích test được xóa sau đối soát; nguồn được dọn và so lại fixture ban đầu. Lần nghiệm thu ghi dump 69762 bytes, dump 59 ms, restore 134 ms; các số này không phải RPO/RTO cam kết.

MyISAM yêu cầu cửa sổ không ghi hoặc biện pháp backup nhất quán do người vận hành chọn. Frozen MVP không tự dừng sửa Users/Role. Runbook nêu điều kiện backup, phục hồi DB mới, kiểm phát sinh sau snapshot, đóng ghi sau sự cố và giữ latch; không có endpoint reset latch hoặc rollback dữ liệu production tự động.

## 4. Bảo toàn môi trường vận hành và việc còn chờ

Checksum trước/sau không đổi: Contact `1859318667`, Reports `1319172983`, Users `1097044357`, Roles `1675026945`, Incident `0`, Staff assistance `0`, Trackings `3337141388`, User history `2096163467`. DB `sale_report` vẫn có 0 bảng `crm_*`; không chạy migration ở đó. `mvp.php`, `config/main.php` và entry point vận hành không thay đổi; năm cờ MVP mặc định vẫn false.

Sau kiểm thử: các DB pilot/performance/reminder không còn Account/Task/Notification thử; không còn import batch hoặc dispatch run thử trên schema tương ứng. Gate pilot về preparing/latch 0/version 1 của fixture. Không còn schema recovery có nonce. Việc reset gate này chỉ là teardown fixture có tên DB và marker được kiểm tra trong script test, không có đường reset qua sản phẩm.

Các việc cần chủ nghiệp vụ/người vận hành thực hiện trước mở pilot: chọn người thật và Hotel, kiểm lại các ca thực tế và ký xác nhận nghiệp vụ từng ca, kiểm dữ liệu/quyền trên staging, đo HTTP đồng thời, chốt RPO/RTO và người trực, rồi quyết định phạm vi/ngày mở. Tất cả đang được chuẩn bị trong runbook, chưa đánh dấu đã thực hiện.
