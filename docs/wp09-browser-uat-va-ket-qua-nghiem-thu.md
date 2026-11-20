# WP09 — Nghiệm thu bằng trình duyệt trên dữ liệu giả

Ngày 20/11/2026. **9/9 kịch bản đạt qua trình duyệt**, gồm hoàn tất UAT08–09 và kiểm tra lại lỗi phát hiện trong lượt UAT. Người thực hiện: tác nhân kiểm thử Codex; **chưa thay chữ ký xác nhận của chủ nghiệp vụ**, chưa mở pilot production. [Evidence chi tiết JSON](wp09-browser-uat-evidence.json).

## Môi trường và kết quả

Trình duyệt in-app, URL local `http://127.0.0.1:8099`, DB riêng `sale_report_mvp_test_browser_uat`, fixture `wp01-v1`, migration WP09. Toàn bộ doanh nghiệp, liên hệ, tài khoản, Hotel và nguồn import đều giả; không sao chép nội dung khách thật. Thao tác nghiệp vụ thực hiện bằng giao diện; CLI chỉ chuẩn bị fixture, chạy worker một lần và đọc đối soát. Session/CSRF riêng với ứng dụng thường.

| Mã | Kết quả kiểm thử | Kết quả quan sát | Ảnh |
|---|---|---|---|
| UAT01 | Đạt | Tạo hồ sơ, hai liên hệ dùng chung email, phân công A tại H1 | [01](uat-browser/01-contacts.jpg) |
| UAT02 | Đạt | Report và ba task trong một giao dịch; lỗi cuộc hẹn không lưu từng phần, sửa rồi lưu thành công | [03](uat-browser/03-report-three-tasks.jpg), [19](uat-browser/19-validation-fixed.jpg) |
| UAT03 | Đạt | Lịch tuần đúng giờ/ngày; inbox A nhận một nhắc và đánh dấu đọc | [04](uat-browser/04-week-calendar.jpg), [05](uat-browser/05-sales-a-reminder.jpg) |
| UAT04 | Đạt | Hoàn thành, hủy, đổi hạn có lý do; Report và snapshot giữ nguyên | [07](uat-browser/07-report-preserved.jpg) |
| UAT05 | Đạt | Hai cửa sổ sửa cùng version; cửa sổ cũ nhận 409, giữ dữ liệu nhập và mở phiên bản mới | [06](uat-browser/06-stale-conflict-fixed.jpg) |
| UAT06 | Đạt | Preview và bàn giao chỉ việc mở A→B tại H1; việc đóng giữ A, A mất quyền Own | [08](uat-browser/08-handover-preview.jpg), [09](uat-browser/09-handover-history.jpg), [10](uat-browser/10-former-owner-denied.jpg) |
| UAT07 | Đạt | Ngoài H2 không đọc URL/lookup/export; reader xem được nhưng không có quyền ghi | [11](uat-browser/11-outside-list-empty.jpg), [12](uat-browser/12-reader-read-only.jpg) |
| UAT08 | Đạt | Preview/map/approve/import Contact và Report, đối soát và replay; nguồn mơ hồ không tự gộp, skip vẫn giữ archive | [13](uat-browser/13-import-reconciled.jpg), [14](uat-browser/14-report-import-reconciled.jpg), [20](uat-browser/20-completed-source-warnings-fixed.jpg), [21](uat-browser/21-source-selection-fixed.jpg), [23](uat-browser/23-ambiguous-preview.jpg), [24](uat-browser/24-skipped-source-archive.jpg) |
| UAT09 | Đạt | Worker tắt vẫn thấy việc; dispatch test nhắc đúng B/generation, replay không trùng, A không nhận, stale heartbeat có cảnh báo | [15](uat-browser/15-b-before-worker.jpg), [16](uat-browser/16-b-after-worker.jpg), [17](uat-browser/17-b-replay-no-duplicate.jpg), [18](uat-browser/18-a-no-b-reminder.jpg), [22](uat-browser/22-worker-stale-warning.jpg) |

## UAT08 — Import và nguồn trùng/mơ hồ

Contact cũ #1 và Report cũ #1 được chọn trên danh sách nguồn, xem trước, map có chủ đích vào Account #1/Contact #2/Hotel #3, giữ owner B #5, duyệt rồi nhập. Hai batch #1–2 hoàn tất, mỗi batch liên kết một dòng, không tạo thêm Account/Contact/Task. Đối soát báo khớp, không lỗi. Chọn lại cùng nguồn trả về batch đã hoàn tất và khóa nút nhập; số bản ghi không tăng.

Bổ sung nguồn Contact giả #2 trùng tên Account và tên/email liên hệ. Batch #3 chỉ gợi ý Account #1 và hai Contact cùng email, trạng thái `needs_review`, không tự gộp. Chọn “Bỏ qua dòng này”, ghi lý do, duyệt và nhập: kết quả `completed`, 1 skipped, 0 lỗi/chờ; đối soát cân bằng. Mở “Đối chiếu bản ghi nguồn” vẫn thấy nguyên snapshot (614 bytes), bản Contact nguồn #2 còn nguyên. Tổng cuối: **1 Account, 2 Contact, 3 Task, 3 batch**.

## UAT09 — Worker và thông báo

Sau bàn giao A→B, B đổi lịch Task #3 sang 15:02–15:32 ngày 20/11/2026, múi giờ Asia/Ho_Chi_Minh; `schedule_version=5`. Khi chưa chạy worker, việc vẫn hiển thị và inbox B chưa có nhắc mới. Dispatch mặc định bị từ chối (exit 2); dry-run chỉ chọn một nhắc, không ghi. Chỉ process test `--enableTest=1` được chạy thủ công: tạo đúng một notification cho B #5, version 5, loại `before`; inbox hiện một dòng. Chạy lại tạo 0, reload không trùng; đánh dấu đọc về 0. Đăng nhập A không thấy nhắc của B. Khi không có lượt worker hoàn tất hơn ba phút, admin thấy cảnh báo “Worker không hoàn tất trong hơn 3 phút.”

DB có **2 notification lịch sử**: một nhắc cũ của A cho Task #1 đã hoàn thành và một nhắc hiện hành của B cho Task #3; không coi notification lịch sử là bản nhắc trùng. Worker chỉ là các process CLI chạy một lần rồi thoát, không có cron/scheduler.

## Lỗi đã sửa và kiểm tra lại

| Lỗi quan sát | Sửa và kiểm tra lại |
|---|---|
| Entry point test không tìm thấy Yii ở cấu trúc thư mục hiện tại | Sửa đường dẫn mặc định, hỗ trợ `YII_FRAMEWORK_PATH`; cấu hình browser chỉ bật khi có biến môi trường và đúng tên DB riêng. |
| Lưu form từ cửa sổ cũ hiện trang exception/debug | HTML 409 giữ giá trị/version/key đã gửi, vô hiệu nút lưu và có liên kết mở bản mới để đối chiếu. Kiểm lại bằng hai cửa sổ thật; JSON vẫn giữ mã lỗi cũ. |
| Lỗi task trong Report hiện tên trường kỹ thuật và thông báo cuộc hẹn tiếng Anh | HTML dùng nhãn tiếng Việt kèm thứ tự việc; lỗi lịch được dịch. Form vẫn giữ các dòng đã nhập; JSON không đổi key. |
| Các checkbox nguồn có ID trùng nhau | Mỗi checkbox có ID/nhãn riêng theo mã nguồn; kiểm lại trên danh sách nhiều dòng. |
| Batch đã hoàn tất vẫn ghi “Cần rà soát” như còn chờ | Đổi thành ghi chú lịch sử tại thời điểm xem trước; tổng kết quả/đối soát không đổi. |

Hồi quy sau sửa: **21/21 bước, 955 assertions có đếm số**, gồm transaction/rollback, quyền, concurrency, import, task, Report, hai worker, client reminder, migration, backup và performance. [Output đầy đủ từng bước](wp09-browser-regression-evidence.json). Các suite model/management/access và benchmark cũng đạt; không cộng chúng thành assertions nghiệp vụ mới.

## Bảo toàn môi trường và bàn giao

Checksum tám bảng vận hành trước/sau trùng nhau: Contact `1859318667`, Reports `1319172983`, Users `1097044357`, Roles `1675026945`, Incident `0`, Staff assistance `0`, Trackings `3337141388`, User history `2096163467`. DB `sale_report` vẫn 0 bảng `crm_*`. Không migrate, cấp quyền hay ghi dữ liệu ở DB đó. `source/index.php`, `config/main.php`, `config/mvp.php` không sửa; năm cờ MVP mặc định vẫn false.

Web server UAT đã dừng. Giữ fixture giả trên DB browser riêng để tra cứu audit/đối soát; các DB hồi quy được suite dọn theo ID sở hữu. Chưa ký thay chủ nghiệp vụ, chưa kiểm tải HTTP đồng thời và chưa triển khai pilot production; các bước đó theo [runbook](wp09-van-hanh-va-ke-hoach-pilot.md).

## Chạy lại môi trường browser

Dùng schema riêng đúng tên `sale_report_mvp_test_browser_uat`, chuẩn bị DB rỗng theo `prepare-isolated-db.php` rồi migrate như runbook. Không dùng DB vận hành hoặc DB hồi quy đang chạy. Lệnh seed từ chối nếu đã có Account hoặc user browser UAT; không tự xóa/reset dữ liệu đã thử.

```sh
export SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_browser_uat'
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/prepare-isolated-db.php
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php migrate --interactive=0
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php browseruat seed
SALEREPORT_BROWSER_UAT=1 /opt/homebrew/opt/php@7.4/bin/php -S 127.0.0.1:8099 -t source source/protected/tests/support/browser-router.php
```

Seed tạo actor/Hotel giả và nguồn Contact mơ hồ; tạo Account với tên `UAT Trình duyệt – Công ty Sen 2026` và hai Contact dùng email `lienhe@example.test` qua UI để diễn tập lại các gợi ý trùng. Trên DB hiện giữ lại, chỉ mở lại server để xem kết quả; không chạy seed lần nữa. Router chỉ nhận loopback trong process PHP development server, cấu hình browser kiểm tra đúng tên DB, dùng cookie phiên và CSRF riêng. Các account seed chỉ dùng local test, không đưa sang vận hành. Worker test chạy thủ công với DSN này, không bật cron hoặc cờ cấu hình thường.
