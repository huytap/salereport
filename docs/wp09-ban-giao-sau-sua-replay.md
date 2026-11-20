# WP09 — Bàn giao sau sửa replay mở lại task

Lượt chạy ngày 20/11/2026, từ **08:14:43 đến 08:15:07 UTC** (15:14:43–15:15:07 giờ Việt Nam). **21/21 bước đạt, 962 assertions có đếm số**. [Evidence đầy đủ](wp09-replay-acceptance-evidence.json) lưu output, thời gian từng bước, đối soát trước/sau và kết quả preflight.

## Thay đổi và phạm vi xác nhận

`TaskController::edit()` chỉ chặn form mở lại task chưa đóng đối với request không phải POST. POST đi tới `TaskService` để tra journal theo request key trước khi kiểm tra chuyển trạng thái mới. Vì thế gửi lại yêu cầu mở lại đã thành công trả về kết quả đã lưu, dù task hiện là `open`.

Bổ sung 7 assertions vào `mvp-task-smoke.php`: replay JSON trả HTTP 200 và đúng kết quả cũ; replay HTML chuyển tới trang task; dữ liệu/version/generation/audit/reminder không đổi; cùng key nhưng payload khác bị 409; key mới mở lại task đang mở bị 409; GET form trên task mở vẫn 409; replay thiếu CSRF vẫn 400. Kiểm thử mới đã thất bại trước sửa và đạt sau sửa. Task smoke hiện có 121 assertions, tăng từ 114; toàn bộ bộ nghiệm thu tăng từ 955 lên 962.

Lượt này chạy toàn bộ runner tự động trên DB riêng. Kết quả 9/9 luồng trình duyệt và ảnh ngày 20/11 được giữ trong [biên bản browser UAT](wp09-browser-uat-va-ket-qua-nghiem-thu.md); **không chạy lại trình duyệt trong lượt hồi quy này**, không ký thay chủ nghiệp vụ và không mở pilot production.

## Kết quả toàn bộ bộ nghiệm thu

| Bước | Kết quả | Assertions có đếm số |
|---|---|---|
| `models-smoke.php` | Đạt | Không đếm assertions |
| `management-smoke.php` | Đạt | Không đếm assertions |
| `access-smoke.php` | Đạt | Không đếm assertions |
| `mvp-schema-smoke.php` | Đạt | 96 |
| `mvp-policy-smoke.php` | Đạt | 94 |
| `mvp-import-smoke.php` | Đạt | 118 |
| `mvp-import-durability.php` | Đạt | 12 |
| `mvp-customer-smoke.php` | Đạt | 130 |
| `mvp-customer-durability.php` | Đạt | 20 |
| `mvp-task-smoke.php` | Đạt | 121 |
| `mvp-task-durability.php` | Đạt | 33 |
| `mvp-report-smoke.php` | Đạt | 87 |
| `mvp-report-durability.php` | Đạt | 39 |
| `mvp-pilot-acceptance.php` | Đạt | 47 |
| `mvp-backup-recovery.php` | Đạt | 7 |
| `mvp-pilot-migration.php` | Đạt | 10 |
| `mvp-reminder-smoke.php` | Đạt | 99 |
| `mvp-reminder-durability.php` | Đạt | 36 |
| `mvp-reminder-client-smoke.js` | Đạt | 13 |
| `mvp-performance.php` | Đạt | Không đếm assertions |
| `yiic.php` | Đạt | Không đếm assertions |

Ngoài 962 assertions, các suite model/management/access, benchmark và preflight cũng đạt. Không cộng số đo benchmark hoặc số luồng UAT vào số assertions.

Benchmark vẫn dùng dữ liệu giả đã commit/ANALYZE, 21 mẫu warm serial mỗi đường đọc, 15 đường đọc (315 mẫu). p95 lớn nhất lượt này là **30.528 ms**, dưới budget local 2.000 ms. Task list/calendar có số SQL tối đa 14, dưới budget 50; đây là phép đo service local, chưa gồm HTTP/network hoặc tải đồng thời và không phải cam kết SLA production.

| Đường đọc | p95 local (ms) | SQL tối đa |
|---|---:|---:|
| Own/accounts | 7.464 | 13 |
| Own/tasks_render_data | 30.219 | 14 |
| Own/calendar | 9.699 | 6 |
| Own/account_detail | 4.011 | 17 |
| Own/summary | 26.06 | 17 |
| Hotel/accounts | 9.407 | 13 |
| Hotel/tasks_render_data | 30.528 | 14 |
| Hotel/calendar | 10.68 | 6 |
| Hotel/account_detail | 3.805 | 17 |
| Hotel/summary | 2.999 | 17 |
| All/accounts | 5.013 | 11 |
| All/tasks_render_data | 3.482 | 13 |
| All/calendar | 11.408 | 5 |
| All/account_detail | 3.391 | 15 |
| All/summary | 13.84 | 20 |

Dump/restore đối chiếu **37 bảng**, dump 69.783 bytes, dump 131 ms, restore 275 ms; schema recovery tạm đã xóa. Các số này chỉ chứng minh bài diễn tập local đạt, chưa phải RPO/RTO thực tế.

Preflight cuối lượt chạy: `schema_ok=true`, migration/index WP09 đúng; gate fixture `preparing`, latch 0, version 1; `cutover.ready=false`, worker tắt, không có dispatch đang chạy. Các cảnh báo `FLAG_OFF_*`, nguồn giả chưa mapping, `DISABLED` và `NEVER_COMPLETED` là trạng thái fixture chưa mở pilot, không phải chứng nhận sẵn sàng vận hành. Runner không kiểm tra hay sửa scheduler hệ điều hành.

## Cô lập dữ liệu và dọn fixture

Bốn schema dùng cho runner:

- `sale_report_mvp_test_pilot`: hồi quy, tích hợp và nguồn backup giả.
- `sale_report_mvp_test_pilot_restore`: migration/preflight.
- `sale_report_mvp_test_reminder_pilot`: worker/inbox/race.
- `sale_report_mvp_test_performance`: benchmark giả.

Số bản ghi của mọi bảng `crm_*` trên bốn schema trở lại đúng mốc trước chạy; không còn schema `sale_report_mvp_test_pilot_recovery_*`. DB browser UAT giữ nguyên bộ dữ liệu giả của lượt trước, không dùng chung với runner. Không bật server web mới; worker test chỉ chạy process CLI một lần rồi thoát, không cài/bật cron.

Checksum tám bảng `sale_report` trước/sau không đổi: Contact `1859318667`, Reports `1319172983`, Users `1097044357`, Roles `1675026945`, Incident `0`, Staff assistance `0`, Trackings `3337141388`, User history `2096163467`. DB vận hành vẫn 0 bảng `crm_*`; không migration hay ghi dữ liệu vận hành. `source/index.php`, `config/main.php`, `config/mvp.php` không sửa; năm cờ MVP trong cấu hình mặc định vẫn false. Cấu hình CLI/test tách riêng và không suy ra trạng thái cờ vận hành từ preflight test.

PHP lint hai tệp PHP sửa đạt; Python AST runner và `git diff --check` đạt.

## Chạy lại và việc còn chờ

Trên bốn DB test đã chuẩn bị theo [runbook](wp09-van-hanh-va-ke-hoach-pilot.md), chạy tuần tự, không chạy cùng người thử UI trên cùng schema:

```sh
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_pilot' python3 source/protected/tests/run-mvp-acceptance.py --report /private/tmp/wp09-acceptance-rerun.json
```

Không trỏ DSN test vào DB vận hành. Người nghiệp vụ vẫn cần ký xác nhận các ca thực tế; người vận hành cần xác nhận staging, tải HTTP đồng thời, backup nhất quán, RPO/RTO, người trực và phạm vi/ngày pilot theo runbook. Bộ test đạt không tự cho phép đổi entry point, cấp quyền vận hành hay bật cron.

Các evidence 949 assertions ban đầu và 955 assertions sau browser được giữ làm mốc lịch sử. Evidence 962 assertions ở tài liệu này là mốc hồi quy mới nhất sau sửa replay.
