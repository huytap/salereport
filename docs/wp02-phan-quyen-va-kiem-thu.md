# WP02: Phân quyền Own/Hotel/All và bảo vệ đường truy cập cũ

Ngày thực hiện: 18/11/2026. Tiếp nối WP00–WP01; chỉ áp dụng migration và kiểm thử trên DB riêng.

## 1. Đã triển khai

### Quyền action và danh tính hiện tại

`ActionAccess` đọc user Active, roles và JSON quyền trực tiếp từ DB. Chuỗi `type`/`hotel` trong session không quyết định quyền. Thay role, thu hồi grant hoặc chuyển user sang Inactive có hiệu lực ở lần kiểm tra kế tiếp, không cần đăng nhập lại.

- Quyền action chỉ nhận giá trị trong catalog `source/confgs/roles.php`.
- Role được chuẩn hóa chữ hoa/thường và khoảng trắng; hỗ trợ nhiều role phân tách bởi dấu phẩy, kể cả tên có khoảng trắng như Contact DB.
- User ID 1 giữ ngoại lệ quản trị hiện có, nhưng cũng phải Active.
- All khác được cấp qua allowlist `params.mvp.globalScopeRoles`, hiện chỉ có role superadmin đã tồn tại trong bảng roles. Role admin không mặc nhiên có All.
- `RoleHelper::GetRole()` dùng cùng nguồn quyền. Khi không có action, trả sentinel thay vì mảng rỗng vì Yii xem mảng actions rỗng trong allow rule là cho phép mọi action.
- Menu và các kiểm tra quản trị đang dùng trong trang Contact/Report/Users chuyển sang helper mới. Đã loại bỏ phương thức WebUser có debug và so sánh sai kiểu.

### Phạm vi dữ liệu

`CustomerPolicy` cung cấp criteria SQL và kiểm tra bản ghi, dùng chung cho danh sách, ID, dropdown, lookup và export:

| Scope | Điều kiện |
|---|---|
| Own | Có grant Own đúng Hotel và là owner hiện tại của Account/Hotel đó |
| Hotel | Có grant Hotel đúng Hotel; vẫn phải có action phù hợp |
| All | Quản trị được cấp rõ ràng; vẫn phải có action phù hợp, trừ ngoại lệ user 1 theo catalog |

- Hồ sơ doanh nghiệp/người liên hệ dùng chung giữa các Hotel của Account; Report/Task giới hạn theo đúng Account + Hotel của bản ghi.
- Việc xem lịch sử đi theo người đang phụ trách, không chỉ `added_by` của Report.
- Role quản lý ở Hotel A kết hợp grant Own ở B không tạo quyền quản lý ở B. Các thao tác assign/archive/reopen đòi hỏi Hotel hoặc All.
- Archive toàn Account đòi quyền trên tất cả Hotel đang liên quan. Kiểm tra công việc mở khi archive sẽ thuộc service nghiệp vụ ở WP04–05.
- Notification chỉ được truy vấn cho người nhận hiện tại, Task còn mở, schedule_version còn đúng và vẫn nằm trong phạm vi đọc. WP02 chưa triển khai worker hoặc màn hình notification.
- Khi thiếu các bảng scope/mapping, tài khoản không phải All bị chặn dữ liệu theo nguyên tắc từ chối mặc định. Không suy quyền từ tên người phụ trách hoặc chuỗi tên khách sạn cũ.

## 2. Cấp quyền action bằng migration

Migration mới: `m261118_180000_seed_mvp_actions`, nằm trong thư mục migration MVP đã tách ở WP00.

- Merge action theo role, giữ các action tùy chỉnh đã có; action không có trong catalog không được thực thi.
- superadmin: catalog đầy đủ.
- admin: đọc Contact/Report cũ; quản lý Account, Task, Reminder và các action chuẩn bị cho import MVP. Các action cũ đã cấp trong DB vẫn được giữ nhưng phải qua kiểm tra scope/luồng chuyển tiếp.
- users: đọc Contact/Report cũ; Account cơ bản, Task cá nhân và Reminder.
- reservation: đọc hồ sơ khách hàng, không có action quản lý Task hoặc Sales Report mới từ seed.
- Contact DB: quyền đọc hồ sơ. fo không được tự cấp quyền mới ngoài phần đã có.
- Role còn thiếu được tạo với các quyền trong cấu hình seed. Không tự cấp Hotel grant cho bất kỳ user nào.

Schema thêm `crm_role_action_seed` để lưu trước/sau. Bảng roles cũ là MyISAM nên không thể dựa vào transaction để rollback seed; journal được ghi trước mỗi thay đổi. Nếu có gián đoạn giữa seed cần kiểm tra journal, không tự chạy lại up trực tiếp.

Rollback từ chối ghi đè khi quyền đã được sửa sau seed; cũng từ chối xóa role mới nếu đang có user sử dụng. Việc up/down này chỉ chạy qua connection DB test được bảo vệ; chưa có đường chạy migration production trong đợt này.

## 3. Bảo vệ các luồng cũ

| Đường truy cập | Hành vi sau WP02 |
|---|---|
| Contact danh sách/AJAX/xem ID | Dùng mapping LegacyLink đã duyệt và scope; chưa mapping chỉ All |
| Report danh sách/xem ID | Report linked phải đúng Account/Hotel; lịch sử chưa mapping chỉ All |
| Dropdown công ty và người dùng | Lọc theo scope; không đọc mọi công ty từ lịch sử Report |
| Contact export | Action `contact/report` riêng và cùng scope cho preview/XLSX |
| `report/report`, `export_excel()` cũ | Kiểm tra lại quyền Contact export; chỉ POST; xuất qua ContactExcel có scope, không còn exporter toàn bộ Contact dùng PHPExcel cũ |
| Contact create/update/delete/import cũ | Chỉ All trong giai đoạn chuyển tiếp; bản ghi đã mapping không được sửa/xóa qua editor cũ |
| Report create/import/company-copy cũ | Chỉ All; tránh tạo bản ghi không có định danh từ form cũ |
| Report update/delete cũ | Kiểm tra ID/scope; Report đã liên kết MVP không được thay đổi qua editor cũ, kể cả All |
| Tracking/shipping trong ReportController | Loại bỏ logic không thuộc nghiệp vụ và trả 410 nếu action được gọi trực tiếp; action không có trong catalog không được cấp qua URL |
| AJAX reminder/comment cũ | Ngừng gọi các backend trống; bỏ nút lưu comment không có luồng xử lý; reminder mới thuộc WP07 |
| Users quản lý tài khoản | Không phải All chỉ xem/sửa chính mình nếu có action; không được gán role/hotel/group/status/username để nâng quyền |
| Users delete/deactivate | Chặn xóa khi có tham chiếu MVP; yêu cầu bàn giao khách và Task mở trước Inactive |
| Dashboard | Các số đếm Contact/Report áp dụng scope và action; không đếm toàn DB cho tài khoản Hotel/Own |

Những hạn chế ghi dữ liệu cũ là chủ đích trong giai đoạn này: editor Account/Task/Report có định danh và service bàn giao sẽ hoàn thiện ở WP04–06. Không bật lại việc sửa bảng Contact cũ cho dòng đã mapping để tránh dữ liệu chuẩn và dữ liệu cũ lệch nhau.

### CSRF và HTTP method

- Bật CSRF ở request web và kiểm tra ở base Controller đối với POST, kể cả trường hợp controller được chạy trực tiếp trong test.
- CActiveForm/CHtml sinh token; bổ sung token cho form POST viết tay và AJAX chọn công ty còn dùng.
- Xóa và logout chỉ nhận POST. Request POST thiếu/sai token trả 400.
- Bản ghi ngoài phạm vi đọc trả 404; thiếu action hoặc scope quản lý trả 403; luồng cũ xung đột hồ sơ chuẩn trả 409.

## 4. Các endpoint nền đã có

### Lookup hồ sơ

- `GET /account/lookup?q=...&hotel_id=...`: tối đa 20 kết quả, chỉ ID/code/name; nếu truyền Hotel thì kiểm tra scope ở chính Hotel được yêu cầu, không mượn quyền từ Hotel khác của Account dùng chung.
- `GET /account/contacts?id=...`: chỉ Contact đang active của Account được phép đọc. Thông tin Contact là dữ liệu chung của Account theo thiết kế MVP.

Đây là endpoint đọc phục vụ bước tiếp theo, chưa phải bộ màn hình Account hoàn chỉnh.

### Quản lý Hotel grants

- `GET /users/hotelaccess/{id}`: đọc grant của một user Active, yêu cầu action `users/hotelaccess` và All.
- `POST /users/hotelaccess/{id}`: thay toàn bộ danh sách grant, yêu cầu thêm CSRF và lý do.

Payload form gồm:

```text
grants[0][hotel_id]=<Hotel ID>
grants[0][scope_level]=own hoặc hotel
reason=<lý do>
YII_CSRF_TOKEN=<token hợp lệ do ứng dụng sinh>
```

Không gửi `grants` nghĩa là yêu cầu thu hồi toàn bộ. Scope `all` không được nhận qua payload này. Grant trùng, Hotel không active hoặc dữ liệu sai bị từ chối.

`HotelAccessService` kiểm tra quyền, khóa Hotel theo thứ tự cố định, ghi grant và audit trong cùng giao dịch; hỗ trợ savepoint khi được gọi trong transaction lớn hơn. Không cho thu hồi Hotel khi user còn là owner hoặc có Task mở tại Hotel đó. Service tạo Account/Task ở các WP sau cần phối hợp quy ước khóa trước khi tạo dữ liệu để kiểm tra thu hồi và tạo mới không chạy đua.

Màn hình chỉnh grant trong Users theo UI12 sẽ bổ sung ở WP04. Hiện endpoint JSON dùng được cho tích hợp và được kiểm thử qua controller thật.

## 5. Kiểm thử đã chạy

| Bộ kiểm tra | Kết quả |
|---|---|
| `mvp-policy-smoke.php` | 94 assertions đạt trên `sale_report_mvp_test` |
| Cùng suite policy trên `sale_report_mvp_test_restore` | 94 assertions đạt |
| `mvp-schema-smoke.php` | 96 assertions đạt |
| `models-smoke.php` | Đạt |
| `management-smoke.php` | Đạt, bao gồm form/POST bật CSRF |
| `access-smoke.php` | Đạt |
| Migration WP02 down → up trên DB test | Đạt; journal khôi phục quyền trước seed |
| Migrate up lần nữa | Không có migration mới |
| PHP 7.4 lint các file first-party thay đổi/thêm mới | 38 file đạt |
| Kiểm tra diff với line ending CRLF có sẵn | Đạt |

Suite policy cố ý cấp dư action cho các role test để chứng minh action không vượt scope. Kiểm tra các trường hợp: đổi ID khách/Hotel; Account chia sẻ nhưng Report thuộc Hotel khác; giả session superadmin; manager A nhưng Own B; reservation chỉ đọc; export có action riêng; export qua URL cũ; dropdown/lookup không lộ khách ẩn; thông báo hết phiên bản; grant bị thu hồi; thiếu CSRF; đổi quyền không cần login lại; user Inactive; xóa/vô hiệu hóa user còn phụ trách; audit grant và rollback role không ghi đè chỉnh sửa sau seed.

User/role/contact giả nằm trong bảng TEMPORARY theo connection. Các dữ liệu MVP/Report kiểm thử được tạo trong transaction rồi rollback. Không nhập khách hàng thật và không chạy fixture trên DB vận hành.

Khi quét bổ sung toàn bộ thư viện cũ, có lỗi cú pháp PHP 7.4 sẵn có trong `protected/libs/phpexcel/Classes/PHPExcel/Calculation/Functions.php` (`break` ngoài loop/switch). Không sửa vendor này trong WP02; tuyến export đã chuyển sang ContactExcel và các kiểm thử XLSX đều đạt. Kết quả lint trong bảng chỉ áp dụng cho mã first-party đã thay đổi/thêm mới.

### Xác nhận dữ liệu vận hành

Checksum đọc trước/sau của contact, reports, users, roles, incident, staff_asst, trackings và user_history đều khớp. Schema `sale_report` vẫn có 132 cột và 0 bảng `crm_*`. Migration cấp quyền chỉ chạy trên hai schema test, không cập nhật role/grant của tài khoản vận hành.

## 6. Chạy lại

Tại thư mục gốc dự án, dùng PHP 7.4 và cấu hình DB test của WP00:

```sh
php source/protected/yiic.php migrate up --interactive=0
php source/protected/tests/mvp-policy-smoke.php
php source/protected/tests/mvp-schema-smoke.php
php source/protected/tests/models-smoke.php
php source/protected/tests/management-smoke.php
php source/protected/tests/access-smoke.php
```

DB test mới cần chạy `prepare-isolated-db.php` theo hướng dẫn WP00–WP01 trước migration. Không dùng DSN vận hành; connection test và migration có chặn riêng.

## 7. Điều kiện trước khi áp dụng cho người dùng thật

- Hoàn thành mapping Account/Hotel/owner và cấp Hotel grants đã được duyệt. Chuỗi `users.hotel` cũ không tự chuyển thành quyền mới.
- Quyết định rõ danh sách người được All và người chỉ Hotel/Own; review export riêng.
- Chưa triển khai migration/schema/action seed lên vận hành trong lần này. Nếu đưa riêng code policy vào môi trường chưa có schema/grants, tài khoản không All sẽ bị từ chối dữ liệu theo nguyên tắc mặc định.
- WP03 làm preview/mapping; WP04–06 bổ sung các màn hình và luồng ghi chuẩn. Cờ customerWrite/taskWrite/reminders vẫn false; không có worker nhắc việc đang chạy.
