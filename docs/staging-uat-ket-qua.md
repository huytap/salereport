# Kết quả kiểm thử staging theo vai trò — 25/11/2026

Đã kiểm thử chín luồng trên staging local bằng tài khoản giả theo vai trò. Các bước trình duyệt đạt, **riêng replay cùng POST mở lại task được xác nhận bổ sung bằng controller, chưa xác nhận gửi lại POST qua trình duyệt**. Không thay kết quả này cho chữ ký chủ nghiệp vụ. [Evidence JSON](staging-uat-evidence.json).

Môi trường: `http://127.0.0.1:8098`, DB `sale_report_mvp_test_staging_pilot`; quản trị All #1, quản lý H1 #2, A #3, B #4, outside H2 #5, reader H1 #6. Không reset fixture cũ; tạo riêng hồ sơ “UAT STAGING 25-11 — Công ty Mai giả” #2 cho lượt thử.

| Mã | Kết quả kỹ thuật | Kết quả quan sát | Ảnh |
|---|---|---|---|
| UAT01 | Đạt | Manager tạo Account #2, Contact #3/#4 chung email và giao A #3 tại H1. | [01](staging-uat/01-contacts.jpg) |
| UAT02 | Đạt | Report #2 và Task #2–4; lịch sai không lưu dữ liệu, sửa xong lưu đủ ba task. | [02](staging-uat/02-validation.jpg), [03](staging-uat/03-report-tasks.jpg) |
| UAT03 | Đạt | Lịch tuần 23–29/11 đúng 14:00–15:00 và hạn ngày; A nhận một nhắc, đánh dấu đọc về 0. | [04](staging-uat/04-calendar.jpg), [05](staging-uat/05-a-inbox.jpg) |
| UAT04 | Đạt | Task #2 completed, #3 cancelled, #4 đổi lịch; Report giữ version 1 và một sự kiện created. | [07](staging-uat/07-report-unchanged.jpg) |
| UAT05 | Đạt (có kiểm thử bổ sung) | Hai cửa sổ: lưu bản cũ bị 409, giữ dữ liệu và khóa Save; mở bản mới thấy lịch hiện hành. Manager mở lại task đóng thành công. Replay cùng POST được kiểm tra bổ sung bằng controller trên DB hồi quy, không xác nhận qua browser. | [06](staging-uat/06-conflict.jpg) |
| UAT06 | Đạt | Preview một việc mở #4, bàn giao B #4; #2/#3 vẫn A, A truy cập hồ sơ bị 404. | [08](staging-uat/08-handover-preview.jpg), [09](staging-uat/09-handover-result.jpg), [10](staging-uat/10-a-denied.jpg) |
| UAT07 | Đạt | Outside H2: URL Account/Task 404, lookup [], export 404, list 0; reader xem được nhưng update/create 403. | [11](staging-uat/11-outside-empty.jpg), [12](staging-uat/12-reader.jpg) |
| UAT08 | Đạt | Batch #1/#2 linked, nhập lại cùng batch không tăng bản ghi; batch #3 nguồn trùng cần review, skip vẫn giữ snapshot. | [13](staging-uat/13-contact-reconcile.jpg), [14](staging-uat/14-report-import.jpg), [15](staging-uat/15-ambiguous-source.jpg), [16](staging-uat/16-skip-archive.jpg) |
| UAT09 | Đạt | B thấy task khi worker không chạy; mặc định từ chối dispatch, dry-run 1, dispatch tạo 1, chạy lại 0; B đánh dấu đọc, A không thấy nhắc B. | [17](staging-uat/17-b-before-worker.jpg), [18](staging-uat/18-b-notification.jpg), [19](staging-uat/19-replay-single-notice.jpg), [20](staging-uat/20-a-no-b-notice.jpg) |

## Giới hạn và lỗi phát hiện

Không phát hiện lỗi chức năng mới trong phạm vi đã thử, nên không sửa mã ứng dụng để tạo thay đổi không cần thiết. Lỗi form cuộc hẹn được chủ động nhập sai để xác nhận validation/rollback, không phải lỗi phần mềm. Hai cửa sổ có xung đột đúng như thiết kế, không ghi đè lịch hiện hành.

UAT05: Manager mở lại task #3 từ cancelled thành open version 3 qua UI. Back tải GET form và bị 409 vì task đang mở; không thể dùng thao tác này để chứng minh replay POST. Đã chạy bổ sung `mvp-task-smoke.php` trên **DB khác** `sale_report_mvp_test_pilot`: 121 assertions đạt, gồm JSON/HTML replay cùng key/payload, không tăng version/generation/audit/reminder, payload khác 409 và CSRF. Không ghi là replay đã đạt qua trình duyệt. Sau đó manager hủy lại #3 (version 4) để ca bàn giao vẫn có một việc mở và hai việc đóng.

## Đối soát dữ liệu

Tổng DB staging: 2 Account, 4 Contact, 4 Task, 3 import batch completed và 2 Notification lịch sử. Một Account/hai Contact/một Task là seed ban đầu; lượt UI thêm một Account/hai Contact/ba Task. Import không tạo thêm hồ sơ/liên hệ/task. Hai notification thuộc Task #2→A/generation 1 và Task #4→B/generation 4, mỗi tổ hợp một dòng; không phải hai bản nhắc trùng. Snapshot của nguồn skip ở batch #3 vẫn giữ 604 bytes. Report #2 version 1, không chỉnh nội dung sau tạo.

Worker UAT09: dispatch mặc định exit 2; dry-run chọn 1, không ghi; chỉ process `--enableTest=1` tạo một nhắc; process chạy lại tạo 0. Trước đó UAT03 đã chạy một process cho A. Không cài/bật cron, không bật worker nền. Server staging local đang giữ để người nghiệp vụ tiếp tục xem, fixture/audit không dọn hay reset.

Checksum tám bảng vận hành trước/sau trùng nhau, lưu trong evidence: Contact 1859318667, Reports 1319172983, Users 1097044357, Roles 1675026945, Incident 0, Staff assistance 0, Trackings 3337141388, User history 2096163467. Không ghi/migrate/cấp quyền trên DB vận hành. `source/index.php` có thay đổi sẵn trước lượt thử; giữ nguyên, không dùng nó để chạy staging. Cấu hình staging dùng entry riêng và DSN riêng.

## Bàn giao

Giữ [checklist và tài khoản](pilot-staging-nghiem-thu-nghiep-vu.md) để chủ nghiệp vụ tự thử/ký xác nhận. Khi thử lại nên tạo tên hồ sơ mới; Account #2 hiện do B phụ trách, task #4 đã đổi lịch gần thời điểm thử. Không coi dữ liệu sau thử là trạng thái seed ban đầu. Người duyệt, ngày ký và quyết định pilot production vẫn chờ.
