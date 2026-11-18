# Kế hoạch MVP: Hồ sơ khách hàng thống nhất và Task/Reminder

Ngày lập: 18/11/2026. Nền tảng mục tiêu: ứng dụng PHP/Yii 1 hiện có.

Tài liệu triển khai tiếp từ [phân tích nghiệp vụ](phan-tich-nghiep-vu-va-dinh-huong-phat-trien.md). Đây là thiết kế và backlog đề xuất, chưa phải thay đổi đã thực hiện trên ứng dụng hoặc DB. Các mặc định nghiệp vụ dưới đây cho phép bắt đầu đặc tả và phát triển; cần xác nhận trong buổi chốt phạm vi đầu tiên.

## 1. Đầu ra MVP và giới hạn phạm vi

Sau MVP, sales có thể mở một hồ sơ doanh nghiệp, xem nhiều người liên hệ, các Sales Report liên quan và các việc cần làm; tạo báo cáo kèm nhiều việc tiếp theo; xem lịch tuần; nhận nhắc việc và hoàn thành từng việc độc lập. Quản lý có thể phân công theo khách sạn, bàn giao và xem việc quá hạn trong phạm vi phụ trách.

### Bắt buộc có trong đợt đầu

1. Account là hồ sơ doanh nghiệp/đại lý có ID ổn định; mỗi Account có nhiều người liên hệ.
2. Account được phục vụ tại một hoặc nhiều khách sạn, có một sales chính tại mỗi khách sạn; cho phép trạng thái chờ phân công.
3. Giữ `reports` làm nhật ký hoạt động, bổ sung Account/Contact/Hotel ID, giữ snapshot và lịch sử cũ.
4. Task độc lập với Report: chủ việc, thời hạn, trạng thái, kết quả, nguồn phát sinh và audit.
5. Lịch tuần sử dụng chính Task loại cuộc hẹn; danh sách hôm nay, sắp tới và quá hạn sử dụng cùng nguồn dữ liệu.
6. Nhắc trong ứng dụng, có lệnh nền tạo thông báo, chống tạo trùng; đọc thông báo không đồng nghĩa hoàn thành việc.
7. Quyền thao tác và phạm vi khách sạn được kiểm tra trên máy chủ ở mọi đầu vào.
8. Công cụ mapping/import có xem trước, hàng lỗi và đối soát; chuyển luồng ghi có kiểm soát.

### Để sau MVP

- Opportunity, báo giá, booking/doanh thu, Incident workflow.
- Email/SMS/Zalo/push, đồng bộ Google/Outlook/PMS, AI.
- Task lặp, nhiều người cùng chịu trách nhiệm, chia công việc thành subtask, tệp đính kèm và ứng dụng di động riêng.
- Phân quyền theo cây tổ chức/đội phức tạp, phê duyệt kế hoạch tuần, công cụ gộp Account tự phục vụ có hoàn tác tự động.
- Hợp đồng giá chuẩn hóa. MVP giữ khả năng xem các trường giá/hạn cũ từ nguồn, không biến chúng thành một giá chung cho Account.

## 2. Các quyết định thiết kế để triển khai được ngay

| Chủ đề | Quyết định đề xuất cho MVP | Lý do |
|---|---|---|
| Chiến lược mã nguồn | Bổ sung module trong Yii 1, giữ layout/Bootstrap và widget hiện có | Giảm thay đổi đồng thời và hỗ trợ chuyển đổi từng bước |
| Contact cũ | Không đổi ngay ý nghĩa bảng `contact`; tạo bảng chuẩn mới | Một dòng cũ trộn doanh nghiệp, người liên hệ, thương mại |
| Activity | Giữ `Report`/`reports`, không tạo bảng Activity song song | Tránh hai nguồn lưu nhật ký sales |
| Người phụ trách | Một owner/Account/Hotel, task chỉ giao cho owner hiện tại | Quy tắc đơn giản, nhất quán về truy cập; cộng tác nhiều sales làm sau |
| Phạm vi task | Mỗi task thuộc đúng một Account và một Hotel | Phân công và quyền rõ ràng; chưa nhận việc cá nhân ngoài Account |
| Phạm vi Report mới | Một khách sạn chính/báo cáo | Phù hợp phạm vi quyền; báo cáo cũ có nhiều khách sạn cần rà soát, không tùy tiện chọn một |
| Kế hoạch | Task loại appointment chính là lịch; task khác là danh sách việc | Không tạo thêm bảng kế hoạch lặp cùng dữ liệu |
| Reminder | Chỉ trong ứng dụng; tác vụ nền mỗi phút, giao diện tải bất đồng bộ | Có thể bảo đảm không tạo trùng bằng giao dịch DB |
| Thời gian | Dữ liệu thời gian mới lưu UTC, hiển thị Asia/Ho_Chi_Minh | Giờ hiển thị và thời điểm nhắc thống nhất giữa web và CLI |
| Khóa Report | Giữ khóa sửa nội dung 7 ngày như hiện tại trong MVP | Không làm thay đổi chính sách cũ; task vẫn cập nhật độc lập |
| Xóa | Archive Account/Contact, cancel Task; không xóa cứng đối tượng mới | Giữ quan hệ và lịch sử |
| Team scope | Chưa có trong MVP; dùng Own/Hotel/All | `users.group` hiện chưa phải cây đội/quan hệ quản lý |

Mặc định một owner/Account/Hotel cần được chốt trước khi xây UI. Nếu doanh nghiệp cần nhiều sales cùng chăm một Account tại cùng Hotel ngay từ đầu, phải bổ sung bảng cộng tác và kiểm thử lại quy tắc giao việc, bàn giao, đọc lịch sử.

## 3. Các điểm phải sửa trên nền hiện tại

| Điểm hiện tại đã đối chiếu | Tác động tới MVP | Việc cần làm |
|---|---|---|
| `ContactController` trực tiếp ghi bảng `contact`; CSV import luôn tạo dòng | Có thể tiếp tục tạo dữ liệu lệch sau chuyển đổi | Chặn luồng ghi cũ khi bật MVP; chuyển form/import sang service mới |
| `Report::getByCompany()` lấy dữ liệu từ Report gần nhất theo tên | Tiếp tục nhân bản tên/số điện thoại sai | Chọn Account ID và Contact ID; điền dữ liệu từ hồ sơ chuẩn |
| `ManagementController::loadModel()` và Report load theo PK | Có quyền vào action chưa đủ ngăn truy cập hồ sơ ngoài phạm vi | Thêm policy ở tìm kiếm, load theo ID, lookup, mutation và export |
| `RoleHelper` đọc action từ bảng `roles`; menu còn nhánh riêng cho user 1 | Chỉ sửa file danh mục không cấp đủ quyền | Migration seed action vào JSON roles, đồng bộ menu theo helper mới |
| `CrmActiveRecord` tự sinh safe rule cho gần hết cột | Field mới có thể bị gán trực tiếp từ POST | Model mới dùng base có whitelist; Report dùng FormModel/DTO cho trường nhập và service cho ID hệ thống |
| `CrmActiveRecord::update()` chỉ cập nhật phần đổi, không có optimistic lock | Hai cửa sổ có thể ghi đè trạng thái hoặc thời hạn | Service có update điều kiện theo `lock_version`, không dựa vào `save()` chung cho mutation cần khóa |
| Footer gọi đồng bộ `/ajax/reminderDaily`, nhưng `AjaxController` trống | Reminder chưa có backend hoạt động trong phiên bản này | Thay bằng `ReminderController`, endpoint JSON và gọi bất đồng bộ |
| `console.php` trỏ SQLite `testdrive.db` | Cron/migration có thể chạy nhầm DB | Tạo cấu hình CLI MVP dùng DB cấu hình web, import model/service và timezone thống nhất |
| `users`/`roles` là MyISAM trong dump, bảng nghiệp vụ chủ yếu InnoDB | Không thể đặt FK mới tới MyISAM hoặc giả định mọi thao tác có rollback | Bảng MVP dùng InnoDB; FK tới bảng cũ chỉ khi tương thích; chưa cập nhật users/roles trong giao dịch nghiệp vụ |
| Test smoke hiện nạp `main.php`; config test chưa chỉ định DB test riêng | Không phù hợp chạy migration trên dữ liệu thật | DB test tách biệt, kiểm tra DSN/schema trước khi ghi; kế thừa cách kiểm thử controller hiện có |

Phạm vi này không yêu cầu viết lại framework, thay toàn bộ hệ thống quyền hoặc sửa mọi module cũ. Tuy nhiên những đường cũ còn dẫn tới dữ liệu MVP phải được bảo vệ hoặc khóa ghi trước khi phát hành.

## 4. Thiết kế dữ liệu

### 4.1. Quy ước

- Bảng mới có prefix `crm_`, engine InnoDB, Unicode phù hợp DB đã kiểm tra; ID nghiệp vụ mới dùng `INT UNSIGNED` tự tăng. FK sang `reports.id` phải giữ kiểu `INT` signed như schema thực tế, sang `users.id` giữ unsigned.
- Trường thời gian mới đặt tên rõ `_at_utc`, kiểu DATETIME; trường ngày nghiệp vụ dùng DATE. Không đổi timezone toàn bộ ứng dụng cũ trong MVP.
- Bảng chính có `created_by`, `updated_by`, `created_at_utc`, `updated_at_utc`; Account, Contact person, AccountHotel, Task và Report tham gia chỉnh sửa có `lock_version INT NOT NULL DEFAULT 1`.
- Status/type dùng VARCHAR với allowlist trong model/service; ID, actor, scope và các version do server đặt. Không mass-assign các trường này.
- Tạo FK giữa các bảng mới và với `reports` khi đã đối chiếu engine/kiểu; các tham chiếu `users` kiểm tra tồn tại, trạng thái và quyền bằng service vì bảng hiện là MyISAM. Vô hiệu hóa xóa cứng user đang được tham chiếu; dùng Inactive và bàn giao.
- Các tên và độ dài dưới đây là đặc tả đề xuất; đối chiếu dữ liệu dài nhất trước khi chốt migration. Mọi index/FK phải được diễn tập trên DB bản sao.

### 4.2. Hồ sơ doanh nghiệp và người liên hệ

| Bảng | Cột chính | Ràng buộc và index |
|---|---|---|
| `crm_hotel` | id, code VARCHAR(32), name VARCHAR(255), timezone VARCHAR(64), status | UNIQUE(code), tên không làm khóa; timezone MVP là Asia/Ho_Chi_Minh |
| `crm_account` | id, code VARCHAR(32), name VARCHAR(255), normalized_name VARCHAR(255), segment VARCHAR(32), biz_scope VARCHAR(128), website VARCHAR(765), phone VARCHAR(64), email VARCHAR(200), building VARCHAR(128), address_1/2/3 VARCHAR(255), city/country VARCHAR(128), source VARCHAR(128), fit_series VARCHAR(128), dmc VARCHAR(128), notes TEXT, status, audit/version | UNIQUE(code), INDEX(normalized_name), INDEX(status, updated_at_utc); tên chuẩn hóa không unique |
| `crm_contact_person` | id, account_id, title VARCHAR(32), first_name/last_name VARCHAR(128), position VARCHAR(128), email VARCHAR(200), phone/mobile VARCHAR(64), dob DATE NULL, contact_role VARCHAR(64), notes TEXT, status, audit/version | INDEX(account_id, status), INDEX(email); không unique email vì có email dùng chung |
| `crm_account_hotel` | account_id, hotel_id, owner_user_id NULL, status active/archived, audit/version | PK(account_id, hotel_id), INDEX(hotel_id, owner_user_id, status); owner NULL nghĩa chờ phân công |
| `crm_account_market` | account_id, market_code VARCHAR(64) | PK(account_id, market_code); MVP dùng danh mục market hiện có, chuẩn hóa alias khi nhập |
| `crm_user_hotel_access` | user_id, hotel_id, scope_level own/hotel, updated_by, updated_at_utc | PK(user_id, hotel_id); grant cụ thể tại từng khách sạn |
| `crm_entity_alias` | id, entity_type account/hotel/user, entity_id, normalized_alias VARCHAR(255), source VARCHAR(128) | UNIQUE(entity_type, entity_id, normalized_alias), INDEX(entity_type, normalized_alias); alias có thể trùng cho nhiều đối tượng và khi đó phải xử lý mơ hồ |

`crm_account.segment` nhận LEISURE/CORPORATE/OTA và “chưa phân loại”. MP/WP/BP của Report được giữ ở Report, không đổ vào segment. `remarks` cũ có thể là vai trò người liên hệ hoặc giá trị lịch sử chưa rõ; chỉ chuyển sang `contact_role` khi ánh xạ hợp lệ, còn lại giữ ở dữ liệu nguồn.

Rate code, remark_for_rate và validity từ các dòng cũ không được chọn ngẫu nhiên thành giá trị đại diện Account. MVP hiển thị phần “Thông tin thương mại cũ” theo từng dòng nguồn có quyền truy cập; quản trị xử lý sai lệch riêng. Nếu phát sinh nhu cầu sửa thỏa thuận giá thường xuyên, bổ sung module hợp đồng giá sau MVP.

Account đa khách sạn dùng chung thông tin doanh nghiệp/người liên hệ. Sales sở hữu Account ở một khách sạn được xem phần hồ sơ chung; hoạt động và task vẫn lọc theo từng khách sạn. Nếu không muốn chia sẻ người liên hệ giữa khách sạn thì mô hình này cần thêm phạm vi cho Contact trước phát hành.

### 4.3. Bổ sung vào `reports`

| Cột mới | Kiểu/ý nghĩa |
|---|---|
| `account_id` | INT UNSIGNED NULL; bắt buộc đối với báo cáo tạo mới qua MVP |
| `contact_person_id` | INT UNSIGNED NULL; khi có phải thuộc đúng Account |
| `hotel_id` | INT UNSIGNED NULL; bắt buộc cho báo cáo mới; legacy nhiều Hotel chưa rà soát để NULL |
| `identity_state` | VARCHAR(24): linked/pending_review/legacy_only |
| `lock_version` | INT DEFAULT 1; kiểm soát sửa đồng thời |
| `client_request_key` | VARCHAR(64) NULL; khóa yêu cầu do server cấp/kiểm tra để chống tạo Report lặp |

Index: `(account_id, hotel_id, date, id)`, `(hotel_id, added_by, date)`, `(identity_state, id)`; UNIQUE(added_by, client_request_key). Report legacy để client_request_key NULL. Gửi lại một key chỉ trả lại kết quả nếu người dùng còn quyền và payload khớp; key cũ với nội dung khác trả lỗi xung đột.

Giữ nguyên `company_name`, first/last_name, phone, email, company_address... làm snapshot tại thời điểm tiếp xúc. Sửa hồ sơ Account không thay nội dung báo cáo đã lập. Trường `hotels` cũ giữ nguyên cho lịch sử; báo cáo MVP mới chỉ nhận một hotel_id và service tự điền nhãn tương ứng vào trường cũ để grid cũ còn hiển thị được.

Các trường `ent`, `sales`, `site_ins`, `done`, `follow_up`, `action` cũ vẫn tồn tại. Sau cutover, ba trường ngày không còn là nguồn sinh reminder cho bản ghi mới; form thay bằng danh sách Task. `done` không đồng bộ với trạng thái Task. Có thể ghi nội dung follow_up/action như tóm tắt, nhưng không dùng chúng để tính số việc mở.

Report cũ chưa mapping đủ hoặc có nhiều khách sạn: chỉ quản trị chuyển đổi được xem ở hàng chờ, không cho sales xem bằng cách suy đoán Hotel. Nếu thực tế một lần tiếp xúc phải phục vụ nhiều Hotel trong báo cáo mới, tách thành các hoạt động có phạm vi rõ ràng hoặc nâng thiết kế trong đợt tiếp; không âm thầm sao chép báo cáo cũ thành nhiều dòng.

### 4.4. Task và cuộc hẹn

`crm_task` có các trường sau:

| Nhóm | Cột |
|---|---|
| Định danh | id, code VARCHAR(32) unique, account_id, contact_person_id NULL, hotel_id, report_id NULL |
| Nội dung | title VARCHAR(255), description TEXT, kind task/appointment, action_type call/visit/entertainment/site_inspection/send_quote/other, priority normal/high |
| Phân công | assignee_user_id, created_by, updated_by |
| Hạn | due_mode date/datetime, due_date DATE NULL, due_at_utc DATETIME NULL, deadline_at_utc DATETIME, timezone VARCHAR(64) |
| Lịch hẹn | starts_at_utc/ends_at_utc DATETIME NULL, location VARCHAR(255) NULL |
| Vòng đời | status open/in_progress/completed/cancelled, completed_at_utc NULL, completed_by NULL, completion_note TEXT NULL, cancelled_at_utc NULL, cancel_reason TEXT NULL |
| Kỹ thuật | lock_version, schedule_version INT DEFAULT 1, client_request_key VARCHAR(64), legacy_key VARCHAR(100) NULL, created_at_utc, updated_at_utc |

Index/ràng buộc:

- UNIQUE(created_by, client_request_key) chống tạo hai Task khi gửi lại cùng yêu cầu; UNIQUE(legacy_key) chống backfill một lịch nguồn nhiều lần. Nhiều NULL được phép ở legacy_key.
- INDEX(assignee_user_id, status, deadline_at_utc, id) cho việc cá nhân/quá hạn.
- INDEX(hotel_id, status, deadline_at_utc, id), INDEX(account_id, hotel_id, status), INDEX(report_id).
- INDEX(hotel_id, kind, starts_at_utc) cho lịch tuần.
- Composite FK `(account_id, hotel_id)` tới `crm_account_hotel`; Contact và Report phải cùng Account/Hotel qua service, không chỉ kiểm tra từng ID tồn tại.

Quy tắc thời gian:

1. Task hạn ngày: `due_date` có giá trị, `due_at_utc` NULL. `deadline_at_utc` là 00:00 ngày kế tiếp ở timezone đã chọn, chuyển sang UTC. Ví dụ hạn 20/11 giờ Việt Nam thì quá hạn từ 00:00 ngày 21/11.
2. Task có giờ: `due_at_utc` có giá trị, `due_date` NULL; deadline bằng due_at.
3. Appointment: bắt buộc start/end và end > start; due_mode=datetime, due_at=ends_at, nhắc trước starts_at. Quá hạn sau khi hết lịch hẹn, không ngay khi bắt đầu.
4. `deadline_at_utc` là giá trị dẫn xuất do service tính, không nhận trực tiếp từ browser. SQL lọc nhanh theo cột này.
5. Quá hạn là điều kiện `status IN (open,in_progress)` và `now >= deadline_at_utc`; không phải một status lưu riêng.
6. Một Report có nhiều Task; Task thủ công có thể chưa gắn Report. Khi hoàn thành cuộc gặp, người dùng có thể tạo Report và liên kết Task trong cùng giao dịch.

### 4.5. Lịch nhắc, thông báo và audit

| Bảng | Cột và mục đích | Ràng buộc |
|---|---|---|
| `crm_task_reminder` | id, task_id, recipient_user_id, schedule_version, reminder_kind before/due_day/overdue, notify_at_utc, state pending/delivered/cancelled, delivered_at_utc, attempt_count, last_error_code | UNIQUE(task_id, recipient_user_id, schedule_version, reminder_kind); INDEX(state, notify_at_utc, id) |
| `crm_notification` | id, reminder_id, recipient_user_id, task_id, schedule_version, kind, created_at_utc, read_at_utc NULL | UNIQUE(reminder_id), INDEX(recipient_user_id, read_at_utc, id) |
| `crm_audit_event` | id, entity_type, entity_id, event_type, actor_user_id NULL cho worker, occurred_at_utc, before_json TEXT, after_json TEXT, reason TEXT, request_id VARCHAR(64), import_batch_id NULL | INDEX(entity_type, entity_id, id), INDEX(request_id); nội dung JSON do encoder tạo, không ghép chuỗi thủ công |

Thông báo lưu tham chiếu thay vì sao chép toàn bộ dữ liệu liên hệ. Khi đọc danh sách/count hoặc mở link phải kiểm tra quyền hiện tại, phiên bản lịch và trạng thái task. Không hiển thị thông báo cũ sau bàn giao, hoàn thành, hủy hoặc thu hồi quyền. Trạng thái đã đọc chỉ cập nhật `read_at_utc`.

Audit ghi cả sự kiện phân công, đổi hạn, hoàn thành, hủy/mở lại, archive và migration. Đổi owner nằm trong audit của AccountHotel; không sửa `created_by` lịch sử. Việc migrate dùng actor loại hệ thống và batch, không giả làm người đã tạo dữ liệu cũ.

### 4.6. Truy xuất nguồn và chuyển đổi

| Bảng | Cột chính | Mục đích |
|---|---|---|
| `crm_import_batch` | id, source_type, source_key/file_hash, mapping_version, status preview/approved/running/completed/failed, created_by, approved_by, timestamps, counts_json | Quản lý một lần import/backfill; cùng nguồn đã hoàn thành trả lại kết quả cũ, không tự tạo batch mới |
| `crm_import_row` | batch_id, row_no, source_table/source_id NULL, raw_json, proposed_json, result_state, error_code, result_account_id/contact_id/task_id NULL | PK(batch_id,row_no); xem trước, duyệt, chạy lại dòng lỗi và đối soát |
| `crm_legacy_link` | source_type, source_id, account_id NULL, contact_person_id NULL, resolution_state, import_batch_id, reviewed_by, reviewed_at_utc | PK(source_type,source_id); không mất liên kết về Contact/Report cũ |

`result_state` phân biệt created/linked/skipped/error/needs_review. Bản ghi doanh nghiệp không có tên người liên hệ vẫn tạo được Account nhưng không tạo người liên hệ rỗng. Dữ liệu nguồn giữ đủ để tra lại thông tin thương mại; truy cập raw_json chỉ cho quản trị chuyển đổi.

Batch có UNIQUE(source_type, source_key, mapping_version); source_key là mã snapshot bất biến hoặc hash file đầy đủ, không chỉ tên file. Duyệt lại với mapping_version mới phải đối chiếu LegacyLink/mã đích trước khi tạo, không coi đó là nguồn khách mới. Mỗi dòng commit trong một giao dịch gồm hồ sơ, link nguồn, audit và kết quả dòng; khóa batch/dòng khi claim để hai lượt commit không ghi cùng hàng.

Không gộp chỉ vì tên công ty/email giống nhau. Mapping chính xác một alias đã được duyệt có thể tự nối; tên gần giống chỉ tạo đề xuất. Nếu nhiều Account ứng viên hoặc trường quan trọng mâu thuẫn thì giữ needs_review.

## 5. Chính sách phân quyền MVP

### 5.1. Cách xác định quyền

Quyền thực tế = **action được phép từ roles** kết hợp **grant đúng Hotel** kết hợp **quan hệ với đối tượng**. Tài khoản Inactive không được thao tác dù session cũ còn tồn tại. Quyền đọc thông báo phải đánh giá lại khi fetch.

- `own`: thấy Account mình là owner ở Hotel được cấp; thấy Report và Task trong Account/Hotel đó, bao gồm lịch sử từ sales trước để bàn giao có ý nghĩa.
- `hotel`: thấy đối tượng trong Hotel được cấp; chỉ làm các action role cho phép. Reservation có thể được xem hồ sơ nhưng không sửa/giao Task.
- `all`: chỉ quản trị hệ thống được cấp rõ ràng; không suy ra chỉ từ tên nhóm hoặc từ một Hotel bất kỳ.
- Có role quản lý ở Hotel A không mở quyền quản lý ở Hotel B. Các scope được tính trên từng grant; nhiều role không được ghép thành quyền ngoài phạm vi.

Nguồn cấp `all` cho MVP: allowlist `params.mvp.globalScopeRoles`, khởi tạo chỉ `superadmin`, cùng ngoại lệ user ID 1 hiện có được mô tả và kiểm thử; không áp dụng cho role `admin` mặc định. Mở rộng allowlist phải là thay đổi quản trị được ghi nhận. Với assign/archive/reopen/làm thay người khác, policy bắt buộc scope Hotel hoặc All, không chỉ kiểm tra có action rồi cho qua với scope Own.

| Hành động | Sales owner | Quản lý Hotel | Reservation đọc | Quản trị |
|---|---|---|---|---|
| Xem Account/Contact | Trong Own | Trong Hotel | Trong Hotel được cấp | All |
| Tạo khách | Trong Hotel được cấp, tự làm owner | Trong Hotel, chọn owner hợp lệ | Không | Có |
| Sửa thông tin chung Account/Contact | Được ở Account mình sở hữu; ghi audit | Được trong Hotel liên quan | Không | Có |
| Tạo/sửa nội dung Task | Việc trong Own, giao cho chính mình | Trong Hotel | Không | Có |
| Đổi owner/bàn giao | Không | Trong Hotel | Không | Có |
| Complete/cancel Task | Task được giao cho mình | Trong Hotel, bắt buộc lý do khi làm thay | Không | Có |
| Reopen/archive | Không | Có, có lý do và kiểm tra phụ thuộc | Không | Có |
| Export | Tắt mặc định; cấp action riêng nếu cần | Cấp riêng và vẫn lọc Hotel | Tắt | Có theo action |
| Mapping/import dữ liệu cũ | Không | Duyệt hàng thuộc Hotel nếu được cấp | Không | Có |

Sales mới có thể tạo Account nhưng trước khi trả kết quả phải tạo luôn AccountHotel owner=self trong giao dịch. Lookup tìm nghi trùng chỉ trả chi tiết trong phạm vi đọc; nếu khách đã tồn tại ngoài phạm vi, hệ thống yêu cầu quản lý hỗ trợ, không trả dữ liệu liên hệ hoặc tự nối để vượt quyền.

Account thuộc nhiều Hotel dùng chung hồ sơ chung: khi sửa hiển thị rõ phạm vi chia sẻ và ghi audit. Archive toàn Account chỉ quản trị hoặc người có quyền trên tất cả Hotel liên quan, và chỉ khi không còn Task mở. Quản lý Hotel chỉ archive quan hệ AccountHotel trong Hotel mình quản lý sau khi xử lý Task mở.

### 5.2. Các đường truy cập phải bao phủ

Danh sách, chi tiết, tạo/sửa, thao tác trạng thái, tìm kiếm tự động, dropdown người nhận, badge reminder, API JSON, export, import preview/commit, URL cũ và endpoint tạo Report theo company. Request giả `account_id`, `hotel_id`, `assignee_user_id` đều phải bị từ chối khi không hợp lệ.

Với bản ghi không thuộc phạm vi đọc, trả 404 thống nhất; mutation thiếu quyền action trả 403. Trường hợp đã biết bản ghi nhưng version cũ trả 409, không ghi đè. Các POST thay đổi dữ liệu có CSRF; GET không được đổi trạng thái.

## 6. Luồng nghiệp vụ chi tiết

### F01 — Tạo hoặc chọn khách hàng

1. Sales chọn Hotel trong phạm vi được cấp, tìm theo tên/mã/điện thoại/email ở phạm vi được phép.
2. Nếu đã có Account, mở hồ sơ hoặc nhờ quản lý cấp quan hệ chăm sóc khi chưa được phụ trách.
3. Nếu tạo mới, nhập tên công ty, phân khúc tùy chọn, thông tin liên hệ doanh nghiệp; người liên hệ là tùy chọn.
4. Server chuẩn hóa để tìm nghi trùng. Nếu không có quyền xem ứng viên, chỉ trả thông báo cần quản lý kiểm tra.
5. Khi lưu, transaction tạo Account, AccountHotel, Contact nếu có, market và audit. Sales không được tự đặt owner khác hoặc thêm Hotel ngoài quyền.
6. Chuyển tới hồ sơ Account; có nút “Ghi Sales Report”, “Thêm việc”, “Thêm người liên hệ”.

Trường bắt buộc tối thiểu: tên doanh nghiệp, Hotel, owner hợp lệ. Cho phép quản lý tạo hồ sơ chờ phân công nhưng không tạo Task cho đến khi có owner.

### F02 — Ghi Sales Report và nhiều việc tiếp theo

1. Chọn Account và một Hotel, sau đó chọn người liên hệ thuộc Account hoặc thêm nhanh.
2. Form lấy thông tin chuẩn để hiển thị và tạo snapshot, không lấy từ báo cáo gần nhất theo tên.
3. Nhập nội dung cuộc tiếp xúc, mục đích, loại tiếp xúc, nhóm nhu cầu và ngày thực hiện theo nghiệp vụ cũ.
4. Ở phần “Việc tiếp theo”, thêm 0..n Task, mỗi việc có tiêu đề, loại, hạn hoặc lịch hẹn. Với sales, assignee mặc định và bắt buộc là mình.
5. Server kiểm tra toàn bộ Report và tất cả Task trước khi ghi. Một Task sai thì hiển thị lỗi đúng dòng, không lưu Report một phần.
6. Transaction lưu Report, Task, lịch nhắc và audit; khóa chống gửi lại ở cấp yêu cầu giúp double-click không tạo thêm Report/Task.

Report quá 7 ngày vẫn hiển thị task liên quan và cho thao tác task theo quyền. Không chỉnh nội dung Report để đổi lịch, hoàn thành hoặc ghi nhận bàn giao Task.

### F03 — Tạo việc trực tiếp và xem kế hoạch

1. Từ hồ sơ hoặc “Công việc của tôi”, chọn Account/Hotel, nội dung và hạn.
2. Task thường hỗ trợ hạn ngày hoặc giờ; appointment bắt buộc start/end, địa điểm tùy chọn.
3. Lịch tuần chỉ hiển thị appointment theo khoảng giao với tuần: start < cuối tuần và end > đầu tuần. Task hạn ngày hiển thị ở danh sách hạn ngày bên cạnh, không tạo cuộc hẹn giả lúc 09:00.
4. Chọn một mục để xem chi tiết, sửa lịch hoặc ghi Report sau cuộc gặp.

MVP không cần kéo thả lịch. Nếu bổ sung kéo thả, phải dùng cùng service đổi lịch, version và audit như form, không cập nhật trực tiếp bằng JavaScript.

### F04 — Hoàn thành, hủy, đổi hạn và mở lại

| Từ trạng thái | Sang trạng thái | Điều kiện |
|---|---|---|
| open | in_progress | Người được giao hoặc quản lý hợp lệ |
| open/in_progress | completed | Có kết quả ngắn; server đặt completed_at/by, dừng lịch nhắc |
| open/in_progress | cancelled | Có lý do, không tính là đã hoàn thành |
| completed/cancelled | open | Chỉ quản lý; lý do, hạn mới hợp lệ và owner hiện tại còn quyền |

Đổi hạn khi còn mở yêu cầu lý do và version hiện tại. Lưu cả hạn cũ/mới trong audit; `schedule_version` tăng, hủy lịch nhắc cũ và tạo lịch mới. Hoãn nhắc không âm thầm đổi ngày hẹn: MVP chỉ có thao tác “Đổi hạn/lịch”, chưa có snooze riêng.

Khi hai người cùng sửa một việc, người lưu sau nhận thông báo dữ liệu đã thay đổi và tải lại. Không ghi đè phần cập nhật của người trước. Tạo Report và hoàn thành một appointment có thể là một thao tác giao dịch; không bắt buộc phải tạo Report cho mọi Task như gửi tài liệu.

### F05 — Bàn giao khách và việc

1. Quản lý chọn Account/Hotel và sales mới đang Active, có quyền Hotel.
2. Hiển thị danh sách Task mở sẽ chuyển, người cũ/người mới và số lượng. Task hoàn thành/hủy giữ nguyên người thực hiện lịch sử.
3. Transaction khóa AccountHotel và Task liên quan, đổi owner, chuyển toàn bộ Task mở sang owner mới, tăng version, hủy lịch nhắc cũ/tạo mới, ghi audit.
4. Thông báo cũ không còn hiện cho người cũ; người mới xem được lịch sử hoạt động trong Account/Hotel vừa nhận.

Không cho đổi owner mà bỏ lại task mở cho người không còn quyền. Khi cần thu hồi Hotel grant hoặc vô hiệu hóa user, kiểm tra Account/Task còn phụ trách và yêu cầu bàn giao trước; không sửa dữ liệu `added_by` lịch sử.

Luồng tạo Task cũng phải khóa AccountHotel trước khi kiểm tra owner và ghi Task, để không sinh việc cho owner cũ đúng lúc đang bàn giao. Các giao dịch liên quan đồng thời AccountHotel/Task/Reminder thống nhất thứ tự khóa AccountHotel → Task theo ID tăng dần → Reminder; worker chỉ cần Task → Reminder và không lấy khóa AccountHotel sau đó. Retry có giới hạn khi DB báo deadlock, sử dụng lại request_key để không tạo trùng.

### F06 — Chuyển dữ liệu lịch cũ thành Task

- Đọc riêng từng `reports.ent`, `sales`, `site_ins` có ngày hợp lệ; loại zero-date/rỗng và giữ lỗi vào hàng chờ.
- `legacy_key = report:{id}:field:{ent|sales|site_ins}`. Đọc lại cùng nguồn không tạo thêm task.
- Task nguồn gắn Report, Account đã mapping, Hotel đã duyệt và owner được quản lý xác nhận. Không dùng `added_by` như owner hiện tại một cách mặc định.
- Không tự suy trạng thái Task từ `reports.done` vì toàn bộ mẫu dump đang là Yes và nghĩa trường chưa xác nhận.
- Lịch đã qua ở dữ liệu cũ chỉ đưa vào preview để xử lý; không tạo hàng loạt việc quá hạn/thông báo. Lịch tương lai cũng cần được duyệt là còn hiệu lực trước khi kích hoạt.
- Báo cáo chưa mapping Account/Hotel hoặc lịch không rõ nghĩa giữ needs_review. Không gán khách sạn đầu tiên trong chuỗi để vượt bước xác nhận.

## 7. Quy tắc và cách chạy reminder

### 7.1. Chính sách mặc định cho pilot

| Loại việc | Mốc nhắc trong ứng dụng |
|---|---|
| Task có giờ | Một lần trước hạn 30 phút |
| Task hạn ngày | Một lần lúc 09:00 ngày đến hạn theo timezone |
| Appointment | Một lần trước bắt đầu 30 phút |
| Việc đang quá hạn | Một lần tại 09:00 địa phương kế tiếp sau deadline; không nhắc lặp hằng ngày trong MVP |

Tạo việc khi mốc nhắc đã qua nhưng chưa đến hạn: lên lịch nhắc ngay. Tạo việc đã quá hạn phải được người dùng xác nhận và có lý do, chỉ tạo một nhắc quá hạn ngay; không phát lại tất cả mốc trước đó. Không áp dụng quy tắc này cho dữ liệu backfill chưa duyệt.

Trong pilot, các mốc là cấu hình hệ thống, không có màn hình mỗi user tùy chỉnh. Task vẫn hiện trong “Hôm nay/Quá hạn” dù chưa tới mốc nhắc hoặc cron gặp lỗi.

### 7.2. Thuật toán worker

1. CLI nạp cấu hình MVP, xác minh đúng DB và lấy `now` UTC; đọc theo batch các reminder pending có notify_at <= now.
2. Trong transaction, khóa Task trước rồi Reminder theo thứ tự ổn định. Kiểm tra status còn mở, schedule_version, recipient, owner, user Active và Hotel grant hiện tại.
3. Nếu không còn hợp lệ, chuyển reminder cancelled; không tạo notification.
4. Nếu hợp lệ, insert Notification với UNIQUE(reminder_id), đánh dấu delivered trong cùng transaction; commit.
5. Worker lỗi giữa chừng sẽ rollback, lần sau xử lý lại. Hai worker cùng chạy vẫn chỉ tạo một notification nhờ khóa/unique. Batch nhỏ để việc hoàn thành/đổi lịch không phải chờ lâu.
6. Khi cập nhật Task, service cũng khóa theo thứ tự Task → Reminder; sau khi hoàn thành hoặc đổi lịch, notification phiên bản cũ bị loại ngay ở truy vấn hiển thị.

Không cần Redis/queue bên ngoài cho MVP. Cơ chế này bảo đảm không tạo trùng thông báo **trong DB**; không mở rộng kết luận sang email/push vốn có quy tắc giao nhận khác.

Lệnh dự kiến: `php source/protected/yiic.php reminder dispatch --limit=200`. Đây là hợp đồng CLI đề xuất, chỉ cấu hình cron sau khi command/config đã triển khai và chạy dry-run trên staging. Tham số check/dry-run không ghi dữ liệu.

Theo dõi: heartbeat lần chạy thành công, số pending trễ quá 5 phút, số lỗi và thời gian xử lý. Quản trị thấy banner nếu lịch nền chưa chạy hoặc backlog tăng. Không hiển thị thông báo “đã nhắc” chỉ vì đã tạo Task.

## 8. Các màn hình cần có

Tái sử dụng layout Yii/Bootstrap và CActiveForm/grid hiện có. Form có lỗi theo trường, giữ dữ liệu sau lỗi; danh sách có phân trang và lọc phía server. Không cần thay giao diện toàn ứng dụng để đưa MVP vào sử dụng.

| Mã | Màn hình/route đề xuất | Nội dung và thao tác chính |
|---|---|---|
| UI01 | Khách hàng `/account/admin` | Tìm mã/tên/người liên hệ, lọc Hotel/owner/segment/status; cột liên hệ chính hoặc tóm tắt, lần tiếp xúc gần nhất trong phạm vi, số việc mở/quá hạn; tạo khách |
| UI02 | Tạo/sửa Account `/account/create`, `/account/update/{id}` | Thông tin doanh nghiệp, người liên hệ ban đầu, market, Hotel; cảnh báo nghi trùng; version khi sửa |
| UI03 | Hồ sơ Account `/account/view/{id}` | Header, danh sách Hotel/owner trong phạm vi; tab Tổng quan, Người liên hệ, Sales Report, Công việc, Thông tin nguồn; tạo report/task, xem audit theo quyền |
| UI04 | Người liên hệ | Form con trong hồ sơ: họ tên, chức vụ, vai trò, email, điện thoại, ngày sinh tùy chọn, active/archive; chống gắn Contact sai Account |
| UI05 | Report mới/sửa `/report/create`, `/report/update/{id}` | Chọn Account/Hotel/Contact bằng ID; snapshot, nội dung cũ; danh sách thêm nhiều task; Report cũ khóa sửa hiển thị lý do và task riêng |
| UI06 | Công việc `/task/admin` | Tab Của tôi/Hôm nay/Sắp tới/Quá hạn/Đã hoàn thành; quản lý có lọc Hotel/sales; cột Account, hạn, owner, ưu tiên, trạng thái |
| UI07 | Chi tiết/tạo/sửa Task `/task/view/{id}`, `/task/create`, `/task/update/{id}` | Nội dung, nguồn Report, lịch/hạn, kết quả, lịch sử; complete/cancel/reschedule theo quyền; xử lý version cũ |
| UI08 | Lịch tuần `/task/calendar` | Appointment theo ngày/giờ; bộ lọc Hotel/sales; danh sách task hạn ngày; chọn mục mở chi tiết, thêm cuộc hẹn |
| UI09 | Chuông và danh sách nhắc `/reminder/admin` | Chưa đọc/đã đọc, link Task, đánh dấu đã đọc; badge chỉ đếm notification hợp lệ chưa đọc; số việc quá hạn là chỉ số riêng |
| UI10 | Phân công/bàn giao `/account/assign/{id}` | Owner theo Hotel, preview Task mở sẽ chuyển, lý do, xác nhận giao dịch và version |
| UI11 | Import/mapping `/customerimport/admin`, `/customerimport/view/{id}` | Tải CSV/đọc nguồn cũ, xem trước, đề xuất nối Account/Contact/Hotel/owner, chọn xử lý mơ hồ, commit, hàng lỗi và đối soát |
| UI12 | Quyền Hotel trong Users `/users/update/{id}` | Grant từng Hotel và Own/Hotel; chỉ quản trị có quyền sửa, kiểm tra trước khi thu hồi |

UI04/UI10 là form hoặc tab con, không cần phát triển thành trang độc lập. UI11 chỉ cần mapping hàng và thống kê, chưa có công cụ kéo thả/gộp nâng cao. Export MVP trả bảng phẳng Account + Contact theo dòng và phạm vi quyền, giữ các cột định danh để hỗ trợ nhập lại; không đưa dữ liệu nguồn chưa duyệt vào export cho sales.

Trạng thái rỗng cần thể hiện rõ: chưa có Contact, chưa có owner, chưa có Activity hoặc chưa có Task. Hàng legacy chưa mapping không được tính thành “không có lịch sử”. Sau khi nối thành công phải thể hiện số bản ghi đã khôi phục liên kết.

## 9. Tổ chức mã nguồn và hợp đồng endpoint

### 9.1. Các lớp mới

Trong MVP, thêm `application.services.*` vào import của cả web và cấu hình CLI mới; không sử dụng autoload tùy biến.

| Vị trí dưới `source/protected/` | Trách nhiệm |
|---|---|
| `models/Account.php`, `ContactPerson.php`, `AccountHotel.php`, `Hotel.php`, `UserHotelAccess.php` | Mapping và validation hồ sơ/phân công |
| `models/Task.php`, `TaskReminder.php`, `Notification.php`, `AuditEvent.php` | Mapping dữ liệu công việc/nhắc/lịch sử |
| `models/CustomerImportBatch.php`, `CustomerImportRow.php`, `LegacyLink.php`, `EntityAlias.php`, `AccountMarket.php` | Import, đối soát, quan hệ phụ |
| `components/MvpActiveRecord.php` | Base riêng kế thừa CActiveRecord, quy tắc trường cho phép, UTC/audit cơ bản; không kế thừa blanket safe rules của CrmActiveRecord |
| `components/ScopedController.php` | Load theo policy, trả JSON thống nhất, bảo vệ POST/CSRF và version |
| `services/CustomerPolicy.php` | Phạm vi Hotel/Own/All và action, dùng chung ở controller/service/lookup/export |
| `services/AccountService.php`, `AssignmentService.php` | Tạo/sửa Account/Contact, archive, bàn giao nguyên khối |
| `services/ReportService.php` | Snapshot, liên kết ID, lưu Report + nhiều Task, khóa sửa và idempotency yêu cầu |
| `services/TaskService.php`, `ReminderService.php` | Mutation có khóa/version, tính hạn, tạo/hủy lịch nhắc, dispatch |
| `services/CustomerImportService.php` | Preview, mapping, commit từng dòng đã duyệt và đối soát |
| `models/forms/*` hoặc các FormModel tên riêng trong `models/` | Whitelist trường nhập cho Account/Report/Task và thao tác trạng thái; nếu dùng thư mục con phải thêm import rõ ràng |
| `controllers/AccountController.php`, `TaskController.php`, `ReminderController.php`, `CustomerimportController.php` | Route web, gọi policy/service; không đặt logic trạng thái trong view |
| `commands/ReminderCommand.php`, `CustomerbackfillCommand.php` | Command chạy nền và dry-run chuyển đổi |

Service dùng transaction rõ ràng. Những hành động có trạng thái/version không đi qua `CrmActiveRecord::update()`; ví dụ cập nhật Task bằng điều kiện `WHERE id=:id AND lock_version=:expected`, tăng version và kiểm tra số dòng ảnh hưởng. Không thay toàn bộ `CrmActiveRecord` trong cùng đợt để tránh tác động Users/Incident/StaffAsst ngoài phạm vi.

### 9.2. Endpoint tối thiểu

| Method/route | Contract chính |
|---|---|
| GET `/account/lookup?q=...&hotel_id=...` | Chỉ trả Account trong phạm vi; phân trang và giới hạn số kết quả |
| GET `/account/contacts?id=...&hotel_id=...` | Chỉ Contact thuộc Account hợp lệ |
| POST `/account/create`, `/account/update/{id}` | DTO trường cho phép; sửa gửi expected_version |
| POST `/account/assign/{id}` | hotel_id, new_owner_id, expected_version, reason, request_key |
| POST `/report/create` | Account/Contact/Hotel ID, snapshot từ server, nội dung, tasks[], request_key |
| POST `/task/create`, `/task/update/{id}` | DTO, request_key khi tạo, expected_version khi sửa |
| POST `/task/complete/{id}`, `/task/cancel/{id}`, `/task/reopen/{id}` | expected_version, note/reason; server đặt actor và timestamp |
| GET `/task/calendar?from=...&to=...&hotel_id=...` | JSON các appointment được phép xem, hạn chế khoảng truy vấn |
| GET `/reminder/summary` | unread_count và danh sách tóm tắt hợp lệ; không thay đổi trạng thái |
| POST `/reminder/read/{id}` | Chỉ recipient, có CSRF, gọi lại an toàn |
| POST `/customerimport/preview`, `/customerimport/commit/{id}` | Commit chỉ batch đã duyệt; kiểm tra lại quyền/mapping, không tin preview cũ |

HTML và AJAX dùng cùng service. Lỗi JSON có error_code, field_errors và message; không trả stack trace hoặc dữ liệu ngoài phạm vi. Khoảng ngày và ID đầu vào được validate và bind parameter.

### 9.3. Các file cũ sẽ sửa

| File/nhóm | Thay đổi cụ thể |
|---|---|
| `config/main.php` | Import service, cấu hình feature flags và timezone MVP; bảo đảm CSRF cho các route thay đổi dữ liệu và điều chỉnh form/AJAX liên quan |
| `config/console.php` hoặc `config/console-mvp.php`, `yiic.php`/entry CLI riêng | Chọn cấu hình MVP có DB thực tế được chỉ định rõ; không để CLI mặc định SQLite mẫu |
| `config/test.php`, bootstrap của smoke test | DB test riêng và guard chống nạp DB vận hành khi có ghi/migration |
| `models/Report.php` | Relations Account/Contact/Hotel/Task, search theo policy, bỏ điền từ báo cáo cùng tên khi tạo qua MVP |
| `controllers/ReportController.php` | Gọi ReportService, kiểm tra object scope trong loadModel và mutation, thay nhánh POST company, bảo vệ CSV import cũ; vô hiệu hóa hoặc chuyển `actionReport()/export_excel()` cũ đang xuất Contact không có scope |
| `views/report/_form.php`, `_grid.php`, `_grid_admin.php`, `view.php` | Lookup ID, nguồn snapshot, task rows/tab, link hồ sơ chuẩn, legacy mapping state |
| `controllers/ContactController.php`, `components/ContactCsv.php`, `ContactExcel.php` | Route cũ vào trang mới hoặc chế độ archive có policy; chặn create/update/delete/import cũ sau cutover; export mới qua query đã scope |
| `components/ManagementController.php` | Không mở rộng load chung thiếu scope cho model MVP; các đường Contact legacy còn giữ phải gọi policy hoặc giới hạn quản trị |
| `controllers/UsersController.php`, `views/users/_form.php` | Quản lý Hotel grants, chặn xóa user có tham chiếu, kiểm tra bàn giao trước Inactive/thu hồi grant |
| `helpers/RoleHelper.php`, `confgs/roles.php`, `module.php`, `menu.php`, `icon.php` | Cấp action mới, normalize tên role và nhiều role; menu không suy quyền từ một chuỗi type |
| `components/views/header.php`, `footer.php` | Menu Khách hàng/Công việc/Lịch, chuông từ JSON; bỏ AJAX đồng bộ reminder cũ; dùng polling nhẹ khi trang đang mở |
| `controllers/AjaxController.php`, `views/ajax/reminder*.php` | Ngừng dùng cho reminder mới; nếu cần giữ URL cũ thì adapter đọc service mới, không có thuật toán riêng |
| `migrations/` | Migration có thứ tự, cột nullable cho legacy, index và seed có kiểm soát |

Không dùng `WebUser::isAdmin()/isUser()` hiện tại cho policy mới: đã thấy logic so sánh kiểu và đoạn debug trong các phương thức này. Policy mới cần nguồn user/role được nạp và kiểm thử rõ ràng.

## 10. Thứ tự thực hiện và backlog có thể giao việc

Ước lượng sơ bộ cho **một lập trình viên quen PHP/Yii 1**, QA tham gia bán thời gian và quản lý nghiệp vụ phản hồi trong ngày: khoảng **33–45 ngày công phát triển**, chưa gồm thời gian chờ quyết định hoặc thay đổi lớn về dữ liệu. QA/UAT cần thêm khoảng 6–10 ngày công, có thể xen kẽ. Tương đương khoảng 8–10 tuần lịch khi có dự phòng; cần hiệu chỉnh sau WP00. Đây không phải cam kết giao hàng.

| Gói | Nội dung và thứ tự sửa | Phụ thuộc | Ước lượng dev | Điều kiện hoàn thành |
|---|---|---|---:|---|
| WP00 | Chốt mặc định, khảo sát runtime/DB đang chạy, thử mẫu migration, tách cấu hình test/CLI | Không | 2–3 ngày | DB đích xác định, backup/restore thử, phạm vi và quyền được chốt |
| WP01 | Migration bảng hồ sơ, task, reminder/audit/import và cột Report; model mới với validation | WP00 | 3–4 ngày | DDL chạy trên bản sao, legacy vẫn đọc được, ràng buộc được kiểm tra |
| WP02 | CustomerPolicy, action seed, Hotel grants, bảo vệ đường legacy; test scope nền | WP01 | 3–4 ngày | Không truy cập ngoài quyền qua URL/lookup/export hoặc role ghép |
| WP03 | Import/backfill preview, alias/mapping, đối soát Account/Contact/Hotel/owner trên bản sao | WP01–02 | 4–6 ngày | Không gộp mơ hồ, chạy lại không thêm đối tượng, có danh sách needs_review |
| WP04 | Account/Contact service và UI01–04, owner/bàn giao UI10, grant UI12 | WP02–03 | 4–5 ngày | Tạo/sửa/xem, phân công có audit/version, hồ sơ đa Hotel đúng quyền |
| WP05 | TaskService và UI06–08, trạng thái, lịch tuần, chuyển việc khi bàn giao | WP04 | 4–5 ngày | Các trạng thái/hạn ngày/appointment và xung đột cập nhật đúng |
| WP06 | ReportService, DTO, lookup ID, Report + tasks nguyên khối, khóa 7 ngày | WP05 | 3–4 ngày | Không nhân bản theo company, không lưu một phần, task độc lập khóa Report |
| WP07 | ReminderService, worker, UI09, header/footer, heartbeat/backlog | WP05–06 | 3–4 ngày | Cron chạy thật ở staging, hai worker không tạo trùng, không nhắc lịch cũ |
| WP08 | Cutover gates, import/export mới, chuyển route cũ, backfill lịch đã duyệt và đối soát cuối | WP03–07 | 3–4 ngày | Chỉ còn một luồng ghi chuẩn, không mất dữ liệu nguồn, rollback diễn tập |
| WP09 | Sửa lỗi tích hợp/UAT, chỉ mục truy vấn trọng yếu, tài liệu vận hành và pilot | WP08 | 4–6 ngày | Bộ nghiệm thu đạt, người dùng pilot xác nhận quy trình |

Trình tự bắt buộc: **cấu hình môi trường → schema → policy → mapping → hồ sơ → task → Report tích hợp → reminder → cutover → pilot**. Thiết kế màn hình có thể chuẩn bị sớm, nhưng không mở tính năng cho người dùng khi policy và migration chưa hoàn tất.

### Chuỗi thay đổi nên chia thành PR/commit để dễ review

1. Cấu hình DB test/CLI và cờ tính năng, chưa đổi luồng người dùng.
2. Schema + model/DTO + migration tests.
3. Policy + quyền/action seeds + kiểm tra phạm vi đường legacy.
4. Mapping/import dry-run + kết quả đối soát trên dữ liệu bản sao.
5. Account/Contact UI và service.
6. Task, lịch, trạng thái và bàn giao.
7. Liên kết Report và tạo nhiều Task.
8. Worker, notifications và chuông.
9. Cutover import/export/URL cũ và runbook.
10. Sửa lỗi UAT và cấu hình phát hành pilot.

Mỗi PR chứa test cho hành vi nghiệp vụ được thêm. Không cần một PR lớn đồng thời thay toàn bộ Contact, Report và hệ thống đăng nhập.

## 11. Chuyển đổi và phát hành trên dữ liệu hiện có

### 11.1. Quy tắc chuyển dữ liệu

1. Snapshot DB tại thời điểm rõ ràng và kiểm tra phục hồi trên bản sao. Không dùng AUTO_INCREMENT làm số lượng bản ghi.
2. Tạo danh mục Hotel chuẩn và mapping tên; danh sách Hotel trong config và trường `hotel_produced` chỉ là đầu vào đề xuất, không tự cấp quyền hoặc xác nhận Hotel đang hoạt động.
3. Phân tích từng Contact cũ thành Account candidate + Contact person candidate. Nhóm cùng tên là đề xuất, người duyệt giải quyết khác biệt địa chỉ, người quản lý và tên miền.
4. Mapping owner theo user ID hợp lệ hoặc alias đã duyệt. Tên trùng/không có tài khoản/người nghỉ việc giữ chờ phân công.
5. Report: nối Account theo mapping duyệt, nối Contact khi xác định chắc, xác định Hotel; giữ snapshot và người tạo cũ. Chưa đủ bằng chứng giữ pending_review, không bỏ dữ liệu.
6. Lịch cũ thành Task chỉ sau bước F06 và quyết định kích hoạt. Không chuyển mọi lịch cũ thành reminder hoạt động.
7. Reconcile: mỗi dòng nguồn có trạng thái kết quả; tổng created+linked+skipped+needs_review+error phải bằng tổng đầu vào, mỗi Report nguồn còn tồn tại và không bị nhân bản.

Sau khi gộp Account, lưu tất cả legacy links thay vì xóa bớt dòng nguồn. Gộp nhiều dòng Contact thành một người liên hệ cần người duyệt, không lấy email chung làm khóa duy nhất.

### 11.2. Phát hành không tạo hai nguồn dữ liệu cùng sửa

- Giai đoạn chuẩn bị: module mới tắt ghi cho người dùng; chạy preview trên bản sao, không dual-write trong production.
- Trước cutover: chốt mapping; đặt **cửa sổ dừng ghi ngắn cho các luồng Contact/Report bị ảnh hưởng** để chụp dữ liệu cuối, tạo/backfill và đối soát. Không chỉ tắt nút trên giao diện; controller/import/API cũ đều phải chặn ghi.
- Cutover: bật `customer_mvp_write`, `report_identity_required`, `task_mvp`, bật policy và route mới cho toàn bộ người ghi trong phạm vi dữ liệu chuyển đổi. Contact cũ chỉ đọc có quyền hoặc redirect, không tiếp tục sửa độc lập.
- Reminder bật sau khi worker và dữ liệu task pilot được kiểm tra; không kích hoạt thông báo trên task nguồn chưa duyệt.
- Pilot có thể giới hạn một Hotel chỉ khi Account chia sẻ và tất cả đường ghi liên quan vẫn đi qua cùng service. Nếu không bảo đảm được, pilot trên staging, rồi cutover đồng thời các luồng Contact/Report production.

### 11.3. Rollback

Trước khi có bản ghi MVP mới: có thể tắt tính năng và quay luồng cũ sau khi xác minh dữ liệu cũ nguyên vẹn. Không drop bảng mới trong lúc xử lý sự cố.

Sau khi đã có bản ghi MVP mới: ưu tiên tắt reminder/đóng ghi các luồng bị lỗi, giữ khả năng xem và sửa lỗi tiếp. **Không bật lại ghi Contact cũ** vì những hồ sơ mới không tự tồn tại ở schema cũ. Nếu buộc khôi phục snapshot, phải xuất và đối soát toàn bộ phát sinh sau cutover, xác định cách nhập lại và được người phụ trách dữ liệu duyệt; không gọi đây là rollback không mất dữ liệu.

## 12. Kiểm thử và tiêu chí nghiệm thu

Test dùng DB riêng và engine giống production. Khóa đồng thời/unique/worker cần integration test nhiều kết nối; temporary table theo connection như smoke test cũ không đủ chứng minh hai worker cùng chạy an toàn. Không chạy test ghi với cấu hình web production.

| Nhóm | Tình huống bắt buộc |
|---|---|
| Hồ sơ | Một Account nhiều Contact; không tạo Contact rỗng; email chung hợp lệ; tên trùng không tự gộp; archive vẫn giữ Report |
| Dữ liệu lịch sử | Dòng Contact mâu thuẫn, owner tên cũ/ID, Hotel nhiều tên; Report chưa mapping vẫn tồn tại; rate/hạn cũ còn tra được |
| Giao dịch | Task thứ hai lỗi thì Report và Task thứ nhất không lưu; lỗi audit/lịch nhắc làm rollback giao dịch liên quan |
| Task | Task độc lập Report; Done Report không đóng Task; đủ chuyển trạng thái, lý do bắt buộc; complete 2 lần không tạo hai sự kiện hoàn thành |
| Thời gian | Hạn ngày đúng ranh giới 00:00 địa phương, giờ UTC qua ngày, appointment qua ngày/tuần; không nhầm ngày hiển thị với UTC |
| Đồng thời | Hai cửa sổ sửa cùng Task; complete đua reschedule; worker đua complete; hai worker; bàn giao đua tạo Task |
| Reminder | Tạo sát hạn, mốc nhắc đã qua, lịch quá hạn, đổi người/đổi hạn, cancel/reopen, user Inactive, quyền bị thu hồi, cron ngừng rồi chạy lại |
| Quyền | Sales khác Account/Hotel; quản lý A nhưng sales B; reservation chỉ đọc; nhiều role; giả ID/owner trong POST; URL cũ; export và count không lộ dữ liệu |
| Import | Preview không ghi hồ sơ chuẩn; commit cùng batch hai lần; crash giữa batch và resume; dòng sai không mất kết quả dòng đã commit; mapping thay đổi bị kiểm tra lại |
| Hồi quy | Contact archive/redirect, Report danh sách/tạo/sửa khóa 7 ngày, Users/đổi mật khẩu/lịch sử đăng nhập vẫn hoạt động trong phạm vi thay đổi |
| Vận hành | CLI đúng DB, dry-run không ghi, backup phục hồi, worker heartbeat, feature gate, thử đóng ghi và xử lý rollback |

Bộ kịch bản UAT tối thiểu:

1. Quản lý tạo Account có hai Contact, giao sales A tại Hotel H1.
2. Sales A lập Report và ba Task: gọi, gửi tài liệu, hẹn khảo sát.
3. Kiểm tra lịch tuần, danh sách hạn ngày và reminder tới đúng sales.
4. Hoàn thành một việc, hủy một việc, đổi lịch một việc; Report không đổi và audit ghi đủ.
5. Mở hai cửa sổ sửa cùng việc để xác nhận cảnh báo version.
6. Quản lý bàn giao Account cho sales B; việc mở chuyển, việc đã xong giữ lịch sử, A không còn quyền Own tại H1.
7. Sales ngoài phạm vi thử mở URL, lookup và export, đều không lấy được dữ liệu.
8. Tải lại cùng batch/import nguồn cũ; số hồ sơ/task không tăng ngoài các hàng được duyệt tạo mới.
9. Tạm dừng worker, tạo việc, kiểm tra danh sách vẫn đúng; khởi động lại không tạo trùng thông báo.

Ngưỡng hiệu năng pilot đề xuất: danh sách Account/Task, Account detail và reminder summary trả trong khoảng 2 giây ở phân vị 95 trên staging với dữ liệu ít nhất bằng bản sao production, có phân trang; reminder được tạo trong 2 phút khi cron khỏe. Cần ghi lại cấu hình máy, dữ liệu và tải thử trước khi coi các ngưỡng này là cam kết vận hành.

## 13. Điều kiện sẵn sàng phát hành và các điểm cần xác nhận

MVP đủ điều kiện phát hành khi: mapping/account ownership của nhóm pilot được duyệt; dữ liệu chưa rõ được cách ly có thể tra cứu; quyền action và Hotel đã cấp đúng; toàn bộ luồng ghi cũ liên quan đã chuyển hoặc chặn; worker chạy được; backup/đối soát và kịch bản đóng ghi đã diễn tập; các kịch bản UAT đạt.

Các điểm cần chốt ở WP00, với mặc định đã dùng trong kế hoạch:

1. Một owner mỗi Account/Hotel có đủ không? Mặc định: có.
2. Account/Contact có được dùng chung giữa các Hotel liên quan không? Mặc định: thông tin chung được dùng chung, hoạt động/task theo Hotel.
3. Report mới một Hotel có phù hợp không? Mặc định: một Hotel; legacy nhiều Hotel cần rà soát.
4. Sales có được tự tạo khách và sửa thông tin chung không? Mặc định: được trong Own, có audit và cảnh báo chia sẻ.
5. Reminder MVP chỉ trong ứng dụng có đáp ứng đợt đầu không? Mặc định: có; không coi đây là nhắc ngoài web qua email/push.
6. Ai duyệt mapping và xác nhận lịch cũ còn hiệu lực? Mặc định: quản trị chuyển đổi phối hợp quản lý sales.
7. Có chấp nhận cửa sổ dừng ghi Contact/Report ngắn khi cutover không? Mặc định: có; nếu không thì cần kế hoạch đồng bộ thay đổi riêng và ước lượng lại.

## 14. Mã nguồn tham chiếu đã kiểm tra

- [Contact model](../source/protected/models/Contact.php), [ContactController](../source/protected/controllers/ContactController.php), [ContactCsv](../source/protected/components/ContactCsv.php), [ContactExcel](../source/protected/components/ContactExcel.php).
- [Report model](../source/protected/models/Report.php), [ReportController](../source/protected/controllers/ReportController.php), [form Report](../source/protected/views/report/_form.php).
- [CrmActiveRecord](../source/protected/components/CrmActiveRecord.php), [ManagementController](../source/protected/components/ManagementController.php), [RoleHelper](../source/protected/helpers/RoleHelper.php), [WebUser](../source/protected/components/WebUser.php).
- [Main config](../source/protected/config/main.php), [Console config](../source/protected/config/console.php), [test config](../source/protected/config/test.php), [CLI entry](../source/protected/yiic.php).
- [UsersController](../source/protected/controllers/UsersController.php), [footer](../source/protected/components/views/footer.php), [header](../source/protected/components/views/header.php), [AjaxController](../source/protected/controllers/AjaxController.php).
- [Dump schema và dữ liệu](../source/_db/hotramre_app.sql), [migration ngày tùy chọn](../source/protected/migrations/m261118_090000_restore_optional_dates.php), [management smoke tests](../source/protected/tests/management-smoke.php), [model smoke tests](../source/protected/tests/models-smoke.php).
