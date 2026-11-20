# WP09 — Vận hành và chuẩn bị pilot

Ngày triển khai: 20/11/2026. Tài liệu này dùng cùng [kế hoạch MVP](ke-hoach-mvp-ho-so-khach-hang-task-reminder.md), [WP07](wp07-worker-reminder-thong-bao-va-giam-sat.md) và [WP08](wp08-cutover-csv-backfill-va-doi-soat.md).

## 1. Trạng thái bàn giao

Đã chuẩn bị bộ nghiệm thu tự động xuyên suốt, đo truy vấn bằng dữ liệu giả, migration chỉ mục, CLI preflight, diễn tập dump/restore và quy trình pilot. Chưa thực hiện UAT có người dùng nghiệp vụ xác nhận, tải đồng thời qua HTTP hoặc pilot production. Chưa bật cron, migrate DB vận hành, sửa quyền vận hành hoặc bật cờ vận hành.

Các service ghi/CLI hiện vẫn yêu cầu `IsolatedTestDbConnection`. Đạt bộ test **không** tự cho phép chạy production. Muốn mở production cần một thay đổi cấu hình/entry point riêng được kiểm tra, thay vì bỏ guard hoặc trỏ cấu hình test vào `sale_report`.

## 2. Các thay đổi kỹ thuật

- Task list lấy trang đã scope trước, sau đó tải Account/Hotel/assignee theo nhóm ID của đúng trang. Tránh N+1 và tránh phép join nhãn làm thay đổi kế hoạch LIMIT/ORDER BY. Không cache quyền qua request; action và Own/Hotel/All vẫn đọc mới.
- Calendar eager-load Account trên các task/sự kiện đã scope, giữ giới hạn 500 dòng mỗi làn và ranh giới thời gian WP05.
- Migration `m261120_110000_prepare_pilot_indexes` chỉ thêm `crm_account(status,name,id)`, `crm_task(kind,due_mode,due_date,id)`, `crm_task(deadline_at_utc,id)`. Có guard DB test, kiểm tra trước thay đổi và down từ chối xóa index bị sửa tay. Không sửa dữ liệu hay seed quyền.
- `yiic pilot check --actor=…` chỉ đọc: migration, định nghĩa index, engine các bảng giao dịch, flags, runtime, timezone, readiness và sức khỏe reminder. Cần All + action `customermove/cutover`. Output không chứa DSN/password/nội dung khách hàng; không cài hay kiểm tra cron của máy.
- `schema_ok=true` chỉ xác nhận cấu trúc. `cutover.ready`, flags và các tiêu chí UAT/vận hành là các điều kiện khác, không được bỏ qua.

## 3. Môi trường và chạy lại kiểm thử

Dùng bốn DB local riêng, chỉ DDL từ dump và fixture giả:

| Schema | Phạm vi |
|---|---|
| `sale_report_mvp_test_pilot` | Hồi quy, chín luồng UAT tự động, dump nguồn giả |
| `sale_report_mvp_test_pilot_restore` | Up/down/up chỉ mục, CLI preflight không ghi |
| `sale_report_mvp_test_performance` | 2.000 Account, 16.000 Task, 2.001 Report, 2.000 Notification giả đã commit, ANALYZE thống kê trước đo và dọn theo ID sau đo |
| `sale_report_mvp_test_reminder_pilot` | Inbox, dispatch, lỗi giữa giao dịch, hai worker và các race |

Chạy mỗi schema theo thứ tự: tạo DB rỗng có tên test → `prepare-isolated-db.php` → `yiic migrate --interactive=0`. Không nhập INSERT từ dump thật. Script prepare từ chối schema có dữ liệu ngoài fixture marker. Nên chạy bằng tài khoản DB test riêng; fixture và migration cần quyền DDL/trigger cho các bài lỗi có chủ đích.

Ví dụ chuẩn bị một schema từ thư mục repository (DB phải được tạo trước):

```sh
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot' /opt/homebrew/opt/php@7.4/bin/php source/protected/tests/prepare-isolated-db.php
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php migrate --interactive=0
```

Sau khi chuẩn bị đủ bốn schema:

```sh
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot' python3 source/protected/tests/run-mvp-acceptance.py
```

Runner chạy tuần tự, dừng khi có lỗi, ghi evidence ở `source/protected/runtime/wp09-acceptance.json`; có `--report /đường/dẫn/report.json` và `--php /đường/dẫn/php`. Không tự tạo cron hay bật flags ngoài process. Mỗi suite giới hạn 180 giây, timeout dừng cả process group gồm worker con, rồi yêu cầu kiểm tra fixture; không tự retry tác vụ chưa rõ kết quả. Không chạy runner đồng thời với người thử UI trên các DB này.

Bài backup chỉ nhận DSN local pilot đúng tên; tạo schema `sale_report_mvp_test_pilot_recovery_<nonce>` mới, restore, so hash mọi bảng, rồi xóa đúng schema test do chính bài đó tạo. File dump và file client chứa thông tin kết nối có mode 0600, được xóa trong finally; credentials không xuất hiện trong argv. Các fixture ghi thật dùng ID đã sở hữu để dọn; fixture benchmark commit rồi cập nhật thống kê và dọn theo ID; test MyISAM dùng bảng TEMPORARY hoặc user/role giả được dọn riêng.

Các suite migration cũ WP06/WP07/WP08 có tiền điều kiện phiên bản riêng. Không chạy `migrate down 1` của chúng trên DB hiện ở WP09 để giả định đang hạ đúng migration. WP09 dùng suite migration riêng trên `pilot_restore`.

## 4. Diễn tập UAT tự động và UAT nghiệp vụ

Cập nhật 20/11/2026: tác nhân kiểm thử đã thực hiện **9/9 luồng bằng trình duyệt trên DB giả riêng**, sửa lỗi và hồi quy 21/21 bước (955 assertions). [Kết quả, phạm vi và ảnh minh chứng](wp09-browser-uat-va-ket-qua-nghiem-thu.md). Cột ký xác nhận bên dưới vẫn chờ chủ nghiệp vụ; kết quả browser không tự điền thay người duyệt.

`mvp-pilot-acceptance.php` dùng user/role giả đã commit để worker process khác đọc được, đi xuyên service và một số controller/render HTML/CSRF. Có hai Contact cùng email, Report với ba Task, inbox người nhận, version conflict, bàn giao việc mở, URL/lookup/export ngoài quyền, replay nguồn cũ, worker tạm dừng/chạy lại, và cutover → closed → recovery. Tất cả dữ liệu thử được dọn; không phải một bộ dữ liệu demo được giữ để người thật đăng nhập.

Người nghiệp vụ dùng DB staging khác (ví dụ `sale_report_mvp_test_pilot_manual`) với snapshot đã ẩn danh hoặc fixture được đồng ý; không dùng chung DB tự động. Cấp người dùng test và Hotel/action theo chính sách thật. Mở qua `source/index-test.php` trên web server local/staging đã cấu hình, không mở entry point vận hành để thử ghi. Lưu ảnh/video, thời điểm, actor, Hotel, ID Account/Report/Task và mã lỗi cho mỗi tình huống; không đưa dữ liệu khách thật vào issue công khai.

| Mã | Người thực hiện | Bước và kết quả cần xác nhận | Evidence tự động | Kết quả người nghiệp vụ |
|---|---|---|---|---|
| UAT01 | Quản lý | Tạo Account, hai Contact, giao A tại H1; email chung hợp lệ | `mvp-pilot-acceptance` | Chờ thực hiện/ký xác nhận |
| UAT02 | Sales A | Report có gọi điện/gửi quote/hẹn khảo sát; lỗi dòng bất kỳ không lưu nửa giao dịch | Pilot + Report durability | Chờ |
| UAT03 | Sales A | Lịch đúng múi giờ, ngày/giờ đúng làn; thông báo chỉ tới A, đánh dấu đọc | Pilot + task/reminder smoke | Chờ |
| UAT04 | Sales A | Hoàn thành/hủy/đổi hạn có lý do; Report/snapshot giữ nguyên, audit đủ | Pilot + task/report smoke | Chờ |
| UAT05 | Sales A, hai cửa sổ | Sửa version cũ bị 409; tải lại rồi sửa thành công | Pilot + task durability | Chờ |
| UAT06 | Quản lý, A, B | Preview bàn giao; chỉ việc mở chuyển B, việc đóng giữ lịch sử; A mất Own tại H1 | Pilot + customer durability | Chờ |
| UAT07 | Sales ngoài Hotel, reservation | URL/lookup/export/count không lộ dữ liệu; reservation không có nút/đường ghi | Pilot + policy/management smoke | Chờ |
| UAT08 | Quản trị chuyển đổi | Trùng/nguồn mơ hồ không tự gộp; skip giữ archive; import lại không tăng số bản ghi | Pilot + import smoke/durability + WP08 | Chờ |
| UAT09 | Người vận hành, sales | Dừng worker vẫn thấy việc; chạy lại chỉ nhắc generation hiện tại, không trùng; stale heartbeat được báo | Pilot + reminder durability/client | Chờ |
| OPS01 | Người vận hành | Frozen/canonical/closed; closed giữ xem; không mở lại ghi cũ sau phát sinh MVP | Pilot + WP08 | Chờ |
| OPS02 | Người vận hành/DBA | Backup nhất quán, phục hồi vào DB mới, đối soát và đo RPO/RTO thực tế | Backup rehearsal local | Chờ staging thật |

Không điền PASS hay tên người duyệt thay người dùng. Các lỗi chặn phát hành: lộ dữ liệu/quyền, mất/nhân bản dữ liệu, giao dịch nửa chừng, sai owner/Hotel, sai ngày/giờ, nhắc sai người/generation, không đóng được ghi hoặc không phục hồi/đối soát được.

## 5. Trước và trong pilot

| Vai trò | Trách nhiệm | Người được giao |
|---|---|---|
| Chủ nghiệp vụ sales | Xác nhận mapping/owner, UAT và quyết định tiếp tục | Chờ chỉ định |
| Quản lý Hotel | Quyền Hotel, bàn giao, xác nhận lịch còn hiệu lực | Chờ chỉ định |
| Hai sales A/B | Thử quy trình hằng ngày và chuyển giao | Chờ chỉ định |
| Người vận hành/DBA | Backup/restore, migration, gate, worker/monitor, xử lý sự cố | Chờ chỉ định |
| Người phụ trách hỗ trợ | Ghi lỗi, mức độ, người xử lý và evidence | Chờ chỉ định |

Mặc định chuẩn bị pilot trên staging trước: một quản lý, hai sales, một reservation và một người ngoài Hotel để kiểm quyền. Account có thể chia sẻ nhiều Hotel, vì vậy chốt ghi phải áp dụng toàn bộ các đường liên quan; không cho một Hotel tiếp tục ghi Contact cũ trong khi Hotel khác dùng Account mới. Chưa chọn người thật, Hotel thật hoặc ngày mở pilot trong triển khai này.

Trước mở pilot phải có: UAT nghiệp vụ ký xác nhận; không còn lỗi chặn; mapping/owner hoặc cách ly có lý do; source hash/reconcile đạt; test action + Own/Hotel/All bằng các tài khoản thật của pilot; bản backup phục hồi được; mô tả phiên bản/config; người trực, kênh hỗ trợ nội bộ và cách đóng ghi. Các thông số thời lượng pilot, người trực và RPO/RTO cần chủ nghiệp vụ/người vận hành chốt theo lịch thực tế.

Ngưỡng theo kế hoạch là p95 khoảng 2 giây cho Account/Task/detail/summary và nhắc trong 2 phút khi scheduler khỏe. Kết quả local dùng service serial, không phải HTTP hay tải đồng thời, nên chưa chứng minh ngưỡng này trên production. Trước pilot cần đo trên staging cùng engine/cấu hình/dữ liệu/phân bố quyền gần thực tế, có nhiều phiên duyệt cùng lúc và ghi rõ máy, tải, số request, warm/cold cache. Theo dõi riêng truy vấn tìm chuỗi chứa `%q%`, trang sâu, Account nhiều lịch sử, lịch dày gần giới hạn và số người dùng/Hotel lớn.

Trong pilot ghi nhận mỗi ngày: lỗi chặn/quyền, số việc mở/quá hạn, owner chưa gán, lỗi import/đối soát, thời gian trang và backlog/heartbeat worker. Chỉ tăng phạm vi sau khi chủ nghiệp vụ xác nhận các tình huống thực tế và không còn vấn đề chặn. Việc cấp tài khoản/cấp quyền/đặt lịch worker trên môi trường vận hành chưa được thực hiện trong WP09 này.

## 6. Runbook kiểm tra và sự cố

Kiểm tra read-only trên DB test đã migrate:

```sh
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php pilot check --actor=1
```

CLI mặc định tắt ba cờ ghi/reminder; `FLAG_OFF_*`, `DISABLED`, `NEVER_COMPLETED` có thể là kết quả mong đợi trên fixture vừa dọn. Không sửa dữ liệu để làm dashboard xanh. Phân biệt lỗi schema/index/engine với trạng thái nghiệp vụ chưa cutover hay worker đang chủ động tắt. CLI không xác nhận cron của hệ điều hành đã được cài hoặc đang chạy.

| Triệu chứng | Kiểm tra | Hành động trong phạm vi đã được phép |
|---|---|---|
| Không có nhắc | Flag, gate, `reminder check`, due slot, recipient/action/grant/version | Dry-run, đối chiếu điều kiện; không tạo Notification tay, không đánh delivered để bỏ qua lỗi |
| `STALE_HEARTBEAT` / `STUCK_RUN` | Scheduler/process, lượt chạy và thời gian; `running` chưa kết thúc | Xác định process thật đã dừng hay còn chạy; không xóa run để qua readiness |
| `BACKLOG` / `ERRORS` | Pending quá 5 phút, last_error_code, deadlock/timeout | Xử lý nguyên nhân, chạy batch giới hạn sau khi gate/quyền đúng; unique key bảo vệ replay |
| 409 khi ghi | Version, request key/payload, preview bàn giao, mutex | Tải lại/xem trước lại; không đổi key chỉ để nhân bản thao tác chưa rõ kết quả |
| 403/404 | User active, action, Hotel grant và owner hiện tại | Kiểm tra bằng quản trị có thẩm quyền; không dùng session role hoặc tên Hotel để bỏ policy |
| Source drift / reconciliation fail | Fingerprint và quyết định đã duyệt | Giữ raw/audit, sửa mapping/preview hoặc cách ly có lý do; không gộp/tạo task hàng loạt để hết cảnh báo |
| Lỗi nghiêm trọng sau canonical | Gate, flag reminder, phạm vi ảnh hưởng | Chuyển closed, giữ đọc/log/audit; xử lý rồi readiness trước canonical; không mở legacy hoặc drop bảng |

Nếu sau này được mở scheduler, phải quản lý bằng quy trình môi trường riêng, dùng process giới hạn batch và giám sát lệnh exit/heartbeat. Không chạy vòng lặp tự phát trong request web. Tài liệu này không cài cron và không cung cấp lệnh tự bật lịch trên máy hiện tại.

## 7. Backup, restore và dữ liệu sau sự cố

Trước cutover chốt cửa sổ dừng ghi, định danh snapshot và lưu cấu hình/phiên bản. Các bảng users/roles và một số bảng cũ là MyISAM; `--single-transaction` chỉ bảo đảm bảng transactional, **không đủ** tạo snapshot nhất quán khi các bảng này còn bị ghi. Frozen của MVP không dừng sửa tài khoản/role: người vận hành phải có biện pháp bảo đảm toàn bộ nguồn liên quan yên trước backup hoặc dùng phương án backup nhất quán được DBA chọn. Diễn tập local dùng fixture yên, không thể suy ra bảo đảm này cho production.

Khôi phục vào DB mới, giữ original; kiểm đúng engine/schema, số lượng và hash nguồn/canonical, FK, Report snapshot, request/legacy key, audit và kế hoạch reminder. Giữ worker tắt khi restore. Đối chiếu các phát sinh sau thời điểm snapshot trước mọi quyết định mở ghi; phục hồi snapshot sau phát sinh mới không tự là rollback không mất dữ liệu. Chỉ mở lại sau người phụ trách dữ liệu xác nhận.

File dump, log và evidence thực tế phải có quyền truy cập và thời gian lưu theo chính sách đơn vị; hạn chế thông tin cá nhân trong log. CLI preflight không xuất password/DSN hay nội dung khách. Các số dump_ms/restore_ms của fixture chỉ là bằng chứng bài thử chạy được, không phải RPO/RTO cam kết.

## 8. Điều kiện kết thúc pilot

Chủ nghiệp vụ xác nhận chín UAT bằng trình duyệt và các ca thực tế; không có lỗi chặn; mapping/quyền/owner ổn; đối soát không drift; worker nhắc đúng người/generation; backup restore staging thành công; p95 và thời gian nhắc đạt trên tải đã thống nhất. Lưu người duyệt, ngày, phiên bản, cấu hình và evidence. Các ô người duyệt/ngày kết thúc hiện để chờ, chưa coi pilot đã được chấp nhận.
