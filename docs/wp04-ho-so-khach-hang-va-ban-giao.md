# WP04 — Hồ sơ khách hàng, người liên hệ và bàn giao theo khách sạn

Triển khai ngày 18/11/2026, tiếp nối WP00–WP03. Mã nguồn đã có màn hình và service WP04; việc ghi mới chỉ được bật bằng cấu hình test, chưa cutover vận hành.

## Phần đã triển khai

| Màn hình | Đường truy cập | Chức năng |
|---|---|---|
| UI01 Danh sách | `account/admin` | Tìm tên/mã/email/điện thoại doanh nghiệp hoặc người liên hệ; lọc khách sạn, owner, trạng thái, phân khúc; 25 dòng/trang |
| UI02 Hồ sơ | `account/view&id=…` | Tổng quan, thị trường, phân công; tab Người liên hệ, Sales Report, Công việc; nguồn cũ và audit dành cho All |
| UI03 Tạo/sửa | `account/create`, `account/update&id=…` | Thông tin doanh nghiệp, thị trường, người liên hệ đầu tiên tùy chọn; kiểm tra trùng, version và request key |
| UI04 Người liên hệ | `account/contactcreate&id=…`, `account/contactupdate&id=…&person_id=…` | Tạo/sửa nhiều người liên hệ, lưu trữ có lý do; giữ lịch sử, không xóa cứng |
| UI10 Phân công/bàn giao | `account/assignpreview&id=…&hotel_id=…` | Chọn khách sạn, xem owner và toàn bộ việc mở, chọn người nhận, xác nhận bàn giao |
| UI12 Quyền khách sạn | `users/hotelaccess&id=…&format=html` | All cấp Own/Hotel theo từng khách sạn; kiểm tra form cũ trước khi thay thế; có link từ trang chi tiết User |

Menu Khách hàng dùng action thực tế của user; ẩn khi schema hồ sơ chưa sẵn sàng. Menu tạo/import Contact và Report cũ ẩn khi `customerWrite=true`; endpoint cũ tiếp tục bị policy từ chối ghi. Các lookup `account/lookup`, `account/contacts` của WP02 được giữ nguyên.

Các đường dẫn trên dùng Yii query routing, ví dụ `/index.php?r=account/view&id=123`.

## Quy tắc hồ sơ và người liên hệ

- Quyền action và phạm vi Own/Hotel/All dùng policy WP02, đọc user/role hiện tại từ DB. Không tin role/hotel trong session hoặc field audit do client gửi.
- Sales Own tạo hồ sơ gắn chính mình tại khách sạn được cấp. Hotel/All được chọn thành viên đang Active hoặc để chưa phân công. Chỉ All có thể chọn người nhận All không có grant khách sạn riêng.
- Hồ sơ và người liên hệ là thông tin chung của doanh nghiệp. Phân công, Report và Task có phạm vi từng khách sạn. Một hồ sơ đa Hotel không làm lộ assignment hoặc nội dung Report/Task của Hotel khác qua tab, bộ lọc hay preview bàn giao.
- Tạo/cập nhật không nhận trực tiếp owner/status/code/audit/version qua mảng `Account` hoặc `Person`. Owner được xử lý riêng tại luồng tạo/phân công. Mảng field ngoài whitelist bị từ chối.
- Tên doanh nghiệp được đối chiếu toàn bộ kho theo tên chuẩn hóa, kể cả hồ sơ ngoài phạm vi và đã lưu trữ. Thông báo trùng không trả ID/thông tin hồ sơ ngoài quyền. All có thể xác nhận doanh nghiệp khác cùng tên bằng lựa chọn riêng và lý do; không tự merge.
- Người liên hệ trùng họ tên chuẩn hóa trong cùng doanh nghiệp cần đối chiếu; cho phép dùng chung email giữa các người khác tên. Sửa ghi chú của người liên hệ đã có không bị chặn chỉ vì dữ liệu nhập trước đó có người trùng tên.
- Mã thị trường được trim, viết hoa, loại trùng và sắp xếp; tối đa 20 mã, mỗi mã 64 ký tự. Dữ liệu nguồn cũ vẫn giữ nguyên trong snapshot WP03.
- Update cần `expected_version` hiện tại. Request cũ bị trả 409; validation 422 giữ nội dung và request key trên form HTML.
- Archive cần lý do và quản lý tất cả Hotel đang liên kết, không còn Task mở tương ứng. Archive Contact cũng cần quyền toàn bộ Hotel vì thông tin liên hệ dùng chung. Archive Account giữ AccountHotel để đọc lịch sử; chặn sửa/thêm Contact/Task mở/thêm Hotel mới. Vẫn cho bàn giao liên kết Hotel hiện có khi cần chuyển trách nhiệm lịch sử.
- Model Task đọc lại trạng thái Account/Contact/assignee hiện tại khi validation, tránh dùng quan hệ AR đã cache trước khi hồ sơ được lưu trữ.

## Bàn giao nguyên khối

1. `AssignmentService::preview()` kiểm tra quyền quản lý đúng Hotel; trả owner, version AccountHotel (0 nếu chưa liên kết), danh sách Task open/in_progress và hash của kế hoạch.
2. POST `account/assign&id=…` gửi `hotel_id`, `owner_user_id`, `expected_version`, `plan_hash`, `reason`, `request_key` và CSRF.
3. Service kiểm tra lại quyền, người nhận Active và grant; lấy mutex dùng chung với importer, khóa Hotel → Account → AccountHotel → Task theo ID tăng dần → Reminder.
4. Kiểm tra lại version và hash của tập Task. Task vừa thêm, sửa hoặc đóng sau preview đều làm kế hoạch cũ mất hiệu lực (409).
5. Đổi owner; chuyển **tất cả Task đang mở của đúng Account/Hotel**; tăng lock_version và schedule_version; hủy reminder pending cũ; tạo lịch mới cho người nhận; ghi audit trong cùng giao dịch.
6. Task completed/cancelled giữ người phụ trách, người hoàn thành và dữ liệu lịch sử. Report cũ và `added_by` không bị sửa. Thông báo cũ mất hiệu lực theo version/recipient và scope WP02.

Không cho bỏ owner khi còn việc mở. Nếu bất kỳ Task/Reminder/audit nào lưu lỗi, toàn bộ bàn giao rollback; có thể dùng lại request key sau khi sửa nguyên nhân. Thu hồi grant/vô hiệu hóa User vẫn yêu cầu bàn giao trách nhiệm trước.

`TaskReminderPlanner` chỉ lập lịch: trước hạn/bắt đầu 30 phút; hạn ngày lúc 09:00 địa phương; quá hạn lúc 09:00 địa phương kế tiếp sau deadline; nếu đã quá hạn thì chỉ lên lịch quá hạn ngay. Thời gian lưu UTC, timezone theo Task. Chưa có worker gửi thông báo, chưa bật cron hoặc cờ reminders. Tab Công việc hiện là màn hình đọc; quản lý trạng thái/lịch thuộc WP05.

## Dữ liệu và phân quyền bổ sung

Migration `m261118_200000_prepare_customer_workflows` chỉ nhận `IsolatedTestDbConnection` và xác minh schema thực tế:

- `crm_customer_request`: khóa `(actor_user_id, request_key)` phân biệt hoa/thường, operation, hash payload chuẩn hóa, Account FK, kết quả và thời gian UTC. Replay cùng request không tạo lại hồ sơ/audit/lịch; dùng key cũ với nội dung khác trả 409. Quyền/phạm vi hiện tại vẫn phải hợp lệ khi replay, gồm đúng Hotel của bàn giao.
- `crm_customer_action_seed`: lưu JSON quyền trước/sau. Bổ sung `contactcreate` từ `create`, `contactupdate` từ `update`, `contactarchive` từ `archive`, `assignpreview` từ `assign`; không cấp thêm phạm vi khách sạn.
- Migration down từ chối khi có request đã ghi hoặc quyền đã được chỉnh sau seed, tránh mất lịch sử hoặc ghi đè chỉnh sửa của quản trị.

Không đổi cấu trúc Contact/Report cũ ở WP04. Các bảng Account, ContactPerson, AccountHotel, Task, Reminder và Audit tiếp tục dùng schema/version WP01. Service dùng chung mutex WP03 để tránh tạo trùng tên giữa form và import.

Form cấp quyền Hotel gửi hash tập grant ban đầu, đối chiếu sau khi khóa Hotel để ngăn hai form quản trị ghi đè nhau. JSON endpoint WP02 vẫn giữ hợp đồng cũ; form HTML dùng `ui=1` và `expected_hash`. Grant Hotel đã archive được giữ nguyên nếu không đổi scope; cấp mới hoặc đổi scope tại Hotel không hoạt động bị chặn.

## Cấu hình và kiểm thử

`main.php`/`mvp.php` vẫn giữ `customerWrite=false`, `taskWrite=false`, `reminders=false`. `test.php` dùng DB riêng, bật `customerWrite=true`. Console vẫn mặc định tắt customerWrite; test commit thực bật riêng trong process. Service ghi hồ sơ còn kiểm tra đúng class kết nối test và tên DB đã được guard, không chỉ dựa vào feature flag.

Không thay entry `source/index.php` mà người dùng đang thử song song. Muốn thử giao diện cần dùng entry/vhost test với `protected/config/test.php` và DB test đã migrate; các route không tự chuyển DB. `customerWrite=true` chặn ghi cả Report cũ trong giai đoạn tích hợp; editor Report mới thuộc WP06.

Lệnh chạy từ thư mục repository, với PHP 7.4 và Yii đã có trong workspace:

```sh
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php migrate up --interactive=0
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-customer-smoke.php
/opt/homebrew/opt/php@7.4/bin/php source/protected/tests/mvp-customer-durability.php
```

DB mặc định `sale_report_mvp_test`; `SALEREPORT_TEST_DSN` chỉ chấp nhận schema `sale_report_mvp_test` hoặc hậu tố test hợp lệ. Fixture phải có marker `wp01-v1`, dữ liệu giả; các bài test không tải dữ liệu khách hàng vận hành.

Kết quả đã chạy:

| Bộ kiểm tra | Kết quả |
|---|---|
| WP04 customer smoke | 130 assertions: các trang HTML, POST, CSRF, XSS, quyền action/scope, hồ sơ đa Hotel, Contact/Report/Task, trùng, archive, stale version/preview, replay sau thu hồi quyền, rollback nguyên khối |
| WP04 customer durability | 20 assertions: commit thực, kết nối độc lập, lỗi sau khi đổi owner phải rollback, lịch nhắc/version, reconnect cả hai kết nối, retry không tạo audit trùng, dọn dữ liệu của lượt chạy |
| WP01 schema | 96 assertions đạt |
| WP02 policy | 94 assertions đạt |
| WP03 import | 116 assertions đạt |
| WP03 durability | 12 assertions đạt |
| Model/management/access legacy | Tất cả đạt |
| PHP lint | 31 file WP04 đạt; diff whitespace đạt |
| Migration restore DB | Up → down → up thành công trên `sale_report_mvp_test_restore`; customer smoke 130 assertions đạt |

Smoke dùng transaction và shadow TEMPORARY users/roles do bảng legacy MyISAM. Durability dùng commit thực với dữ liệu giả mang mã riêng; xóa đúng các bản ghi của lượt chạy trong finally. Test xác nhận legacy fixture và users/roles được giữ nguyên, hai DB không còn request journal phát sinh sau khi dọn. Chưa thực hiện QA trực quan trên trình duyệt hoặc benchmark tải pilot; hiện có kiểm tra HTML được render qua controller thực.

## Đối chiếu vận hành

Chỉ đọc schema và checksum `sale_report` trước/sau; không chạy migration/import hay ghi dữ liệu tại đây:

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

Schema vận hành vẫn 132 cột, 0 bảng `crm_*`. Giữ nguyên thay đổi entry/config và quyền mà người dùng đã xác nhận đang thử song song.

## Điểm nối WP05

TaskService cần dùng cùng trật tự khóa Hotel/Account/AccountHotel → Task → Reminder, kiểm tra owner/trạng thái ngay trong giao dịch, không ghi Task cho owner cũ sau bàn giao. Tái sử dụng planner cho tạo/đổi hạn/mở lại; tăng schedule_version khi đổi lịch/người nhận, và hủy lịch khi đóng việc. Bổ sung màn hình Task, lịch tuần và trạng thái theo kế hoạch MVP. Chưa mở cờ ghi hoặc reminder của môi trường vận hành.
