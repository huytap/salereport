# Phân tích nghiệp vụ và định hướng phát triển Sales Report

Ngày khảo sát: 18/11/2026.

## 1. Kết luận và hướng sản phẩm

Phần mềm có bản chất là **CRM cho đội sales khách sạn/resort**, quản lý quan hệ với doanh nghiệp, đại lý du lịch và người đặt phòng, đồng thời ghi nhận hoạt động phát triển khách hàng cho nhiều khách sạn. Contact DB lưu hồ sơ và thông tin thương mại; Sales Report ghi lại từng lần tiếp xúc; Reminder hướng tới việc tiếp theo; Incident hướng tới sự cố trong quá trình khách sử dụng dịch vụ.

Định hướng phù hợp là phát triển thành hệ thống **quản lý khách hàng và điều hành bán hàng khách sạn**, kết nối trọn vòng đời:

**Khách hàng → Kế hoạch chăm sóc → Hoạt động sales → Cơ hội/nhu cầu → Báo giá → Booking → Kết quả doanh thu → Phản hồi và chăm sóc lại.**

Ưu tiên đầu tiên là thống nhất định danh khách hàng và quản lý công việc có người phụ trách, thời hạn, trạng thái. Sau đó bổ sung cơ hội bán hàng và kết quả booking/doanh thu. Incident cần thành quy trình xử lý phản hồi có trách nhiệm rõ ràng. AI và các tự động hóa nâng cao nên đến sau khi các dữ liệu này đủ tin cậy.

### Phạm vi và mức độ xác nhận

- Đã đọc toàn bộ 7 sheet trong `docs/sale_report.xlsx`, bao gồm 4 sheet ẩn; khảo sát schema và bản ghi trong `source/_db/hotramre_app.sql`; đối chiếu model, controller, form, menu, phân quyền, phần giao diện reminder.
- Đây là khảo sát tĩnh từ tài liệu và mã nguồn trong thư mục làm việc. Chưa chạy kiểm thử nghiệp vụ trên ứng dụng hay kiểm tra cơ sở dữ liệu đang vận hành. Các số lượng dưới đây thuộc bản dump, không đại diện mặc nhiên cho dữ liệu hiện tại.
- Các mục “đề xuất” mô tả hành vi tương lai. Các nhận định về khoảng trống chỉ áp dụng cho phiên bản mã nguồn được cung cấp.
- Chưa xác định ý nghĩa chính thức của MP/WP/BP, cách tính doanh thu, chính sách phân chia khách hàng và cam kết thời gian xử lý sự cố. Những điểm này cần được chốt với đơn vị sử dụng.

## 2. Bối cảnh kinh doanh và người sử dụng

### Đối tượng khách hàng

Hệ thống phục vụ nhiều lớp quan hệ, cần phân biệt khi mở rộng:

| Đối tượng | Ý nghĩa nghiệp vụ | Ví dụ nhu cầu |
|---|---|---|
| Doanh nghiệp/đại lý (Account) | Đơn vị có quan hệ thương mại hoặc gửi khách | Giá hợp đồng, khách công tác, đoàn, hội nghị |
| Người liên hệ (Contact person) | Cá nhân làm việc với sales | Booker, người quyết định, người ảnh hưởng |
| Khách lưu trú (Guest) | Người thực tế dùng dịch vụ | Check-in, phòng ở, yêu cầu và phản hồi |
| Khách sạn (Hotel/Property) | Đơn vị cung cấp dịch vụ | Báo giá, nhận booking, phục vụ khách |

Một doanh nghiệp có thể có nhiều người liên hệ, gửi nhiều khách, phát sinh nhiều booking tại nhiều khách sạn. Người đặt phòng có thể khác người lưu trú. Việc gộp tất cả thành một “khách hàng” sẽ gây khó khi phân quyền, thống kê và xử lý phản hồi.

Các danh mục hiện có phản ánh ngành khách sạn: LEISURE/CORPORATE/OTA, Individuals/Groups/MICE, FIT/Series, market, rate code, khách sạn, hoạt động Tele/Visit/INSPT/ENT. Không nên đồng nhất market với địa chỉ doanh nghiệp: một công ty tại Việt Nam có thể chuyên khai thác thị trường châu Âu. [S2, S3, S9]

### Vai trò sử dụng

| Vai trò nghiệp vụ | Công việc cần hỗ trợ | Dấu vết hiện tại |
|---|---|---|
| Nhân viên sales | Quản lý danh sách khách, tiếp xúc, báo cáo, làm việc tiếp theo | Role `users`, Sales Report, Contact |
| Quản lý sales | Phân công, xem kế hoạch, nhận xét, theo dõi kết quả | `comments`, `highlight`, lọc theo sales; Staff Assessment dạng sơ khởi |
| Reservation | Tra cứu thông tin khách, tiếp nhận booking | Role `reservation` có quyền đọc Contact trong dump |
| FO và bộ phận vận hành | Tiếp nhận phản hồi, phối hợp xử lý | Role `fo`, schema Incident, danh mục HSK/FO/FB |
| Ban giám đốc/quản lý khách sạn | Xem kết quả theo khách sạn, thị trường, sales | Danh mục nhóm và khách sạn; chưa thấy dashboard kết quả kinh doanh đầy đủ |
| Quản trị hệ thống | Quản lý tài khoản, quyền, danh mục | Users, Roles, lịch sử đăng nhập |

Vai trò nghiệp vụ trong bảng là cách tổ chức được suy ra và đề xuất; chưa khẳng định cơ cấu tổ chức thực tế. `group` đang là danh sách, chưa thể hiện cây quản lý và cấp báo cáo.

## 3. Nghiệp vụ hiện có theo phân hệ

### 3.1. Contact DB: hồ sơ quan hệ thương mại

Một dòng `contact` đang chứa đồng thời thông tin doanh nghiệp, một người liên hệ, người quản lý khách và thông tin thương mại:

- Doanh nghiệp: company, ngành/lĩnh vực, địa chỉ, thành phố, quốc gia, điện thoại, website, nguồn khách.
- Người liên hệ: danh xưng, họ tên, chức vụ, email, di động, ngày sinh, ghi chú vai trò.
- Thương mại: phân khúc khách, thị trường, khách sạn có giao dịch/được ghi nhận, FIT/Series, DMC, rate code, thời hạn hiệu lực, ghi chú giá.
- Quản lý: `managed_by`, thời điểm cập nhật, người cập nhật.

Đã có luồng thêm/sửa/xóa, tìm kiếm, nhập CSV theo tên cột và xuất XLSX. Import Contact hiện tạo mới từng dòng; không thấy cơ chế đối soát để cập nhật bản ghi cũ hay hợp nhất dữ liệu trùng trong luồng này. `hotel_produced` là danh sách tên khách sạn, **không phải số liệu sản lượng hoặc doanh thu đã đối soát**. [S2, S4]

Điểm cần xử lý khi mở rộng:

- Tách doanh nghiệp và người liên hệ để quản lý quan hệ một-nhiều.
- `managed_by` trong dump có tên dạng văn bản, trong khi form hiện lấy danh sách người dùng với ID làm giá trị. Cần mapping người phụ trách cũ sang tài khoản, xử lý người đã nghỉ việc và giữ lịch sử bàn giao.
- Rate code và thời hạn nằm trên dòng Contact nên khó biểu diễn một công ty có nhiều hợp đồng theo khách sạn, mùa, loại phòng.

### 3.2. Sales Report: nhật ký tiếp xúc và nội dung chăm sóc

Đơn vị nghiệp vụ chủ yếu là **một lần sales làm việc với một công ty/người liên hệ vào một ngày**, không phải một giao dịch doanh thu.

Một báo cáo bao gồm:

1. Công ty và người liên hệ: tên, địa chỉ, điện thoại/email, chức vụ, lĩnh vực, khách sạn, thành phố/quốc gia.
2. Phân loại: `account_type` là MP/WP/BP; loại tiếp xúc; mục đích Qualifying/Promoting/Negotiating/Closing/Follow up; nhóm nhu cầu Individuals/Groups/MICE; market.
3. Nội dung: báo cáo diễn biến, follow-up, action, Done, khách mới, nhận xét và đánh dấu nổi bật.
4. Lịch tiếp theo: `ent`, `sales`, `site_ins`.
5. Người lập, người sửa và thời điểm tạo/cập nhật.

Khi chọn tên công ty, hệ thống lấy thông tin từ **báo cáo gần nhất cùng tên công ty**, rồi tạo báo cáo mới. Đây chưa phải việc chọn khách hàng bằng `account_id` từ Contact DB. `reports` không có `contact_id` hay `account_id`. [S2, S5]

Quy tắc đáng chú ý:

- Luồng cập nhật hiện giới hạn theo ngày tạo báo cáo cộng 7 ngày. Cần xác nhận đây là chính sách được mong muốn hay ràng buộc cũ; chưa thấy quy trình xin mở lại sau khóa.
- Role chính xác bằng `users` được lọc danh sách báo cáo theo `added_by`. Không thể suy từ đó rằng mọi thao tác đọc/sửa/xóa theo ID hoặc tài khoản nhiều role đã được giới hạn tương tự.
- Mục đích “Closing” chỉ là nhãn hoạt động. Nó chưa tạo một cơ hội thắng hay booking được xác nhận.
- `expected_result` hiện là nhóm Individuals/Groups/MICE, chưa phải số phòng, room nights hay giá trị dự kiến.
- Có nhập CSV theo vị trí cột. Luồng xuất trong `ReportController` dùng dữ liệu Contact và giao diện cũng liên kết tới xuất Contact; không nên coi đây là báo cáo xuất Sales Report hoàn chỉnh nếu chưa kiểm thử và hoàn thiện.

### 3.3. Incident: phản hồi gắn với lần lưu trú và xử lý dịch vụ

Schema cho thấy Incident gần với **nhật ký sự cố/phản hồi của khách lưu trú và cách khách sạn xử lý**, rộng hơn một ô ghi chú feedback:

| Nhóm thông tin | Trường hiện có | Ý nghĩa |
|---|---|---|
| Khách và lần lưu trú | guest_name, checkin, checkout, room, nationality | Xác định bối cảnh sự cố |
| Nguồn và đơn vị liên quan | ta_co, hotels, dept, received, time | Đại lý/công ty, khách sạn, bộ phận và thông tin tiếp nhận; ý nghĩa chi tiết của `received` cần xác nhận |
| Phân tích và xử lý | explanation_why, action_taken | Giải thích nguyên nhân và việc đã làm |
| Theo dõi | follow_up_by, follow_yes_no, handle_time, remarks | Ai theo tiếp, trạng thái dạng cũ, thời gian xử lý và ghi chú |

Chưa có định danh liên kết booking/account/contact, trạng thái vòng đời rõ ràng, mức độ ưu tiên, hạn phản hồi/hạn xử lý, lịch sử trao đổi hay xác nhận đóng của khách. Trường mô tả sự việc độc lập cũng chưa rõ ràng; không nên mặc định `explanation_why` bao trùm cả phản ánh ban đầu và nguyên nhân đã xác minh.

**Trạng thái phiên bản này:** có model và bảng dữ liệu, bảng không có bản ghi trong dump; không tìm thấy `IncidentController` và bộ view Incident, menu bị comment. Vì vậy chưa thể kết luận phân hệ đang hoạt động đầy đủ, dù người dùng cho biết có nghiệp vụ này. [S2, S6, S9]

### 3.4. Users và phân quyền

Có tài khoản, trạng thái Active/Inactive/Pending, nhiều role, nhóm và khách sạn; có đổi mật khẩu và lịch sử đăng nhập/đăng xuất. Role lưu danh sách action dưới dạng JSON. Các role trong dump gồm `superadmin`, `admin`, `users`, `reservation`, `fo`, `Contact DB`. Quyền thực thi có đọc bảng `roles`, nên cấu hình tĩnh `access_rights.php` không phải nguồn duy nhất để diễn giải quyền. [S2, S8]

Khoảng trống nghiệp vụ là tách **quyền thao tác** và **phạm vi dữ liệu**: ai được sửa, sửa dữ liệu của ai, trong đội nào, ở khách sạn nào, có quyền xuất dữ liệu hay không. Sự tồn tại của trường `hotel` chưa chứng minh dữ liệu luôn được giới hạn theo khách sạn.

`RoleController` hiện trống; không nên mặc định người quản trị đã có luồng quản lý role đầy đủ chỉ vì có model/view. Mã đăng nhập có nhánh LDAP dự phòng, nhưng chưa kiểm tra việc tích hợp thực tế.

### 3.5. Reminder: lịch phát sinh từ báo cáo

Reminder hiện được thiết kế quanh ba ngày `ent`, `sales`, `site_ins` của Sales Report. Giao diện chia nhóm ENT, Sales, Site Inspection; footer gọi `/ajax/reminderDaily`. Không thấy bảng task/reminder độc lập. [S5, S7]

**Giới hạn xác minh:** `AjaxController.php` hiện trống, do đó không xác định được thuật toán lấy lịch, khoảng thời gian nhắc, người nhận hay xử lý quá hạn từ bản mã nguồn này. Chưa có bằng chứng về gửi email, nhắc nền hoặc nhắc khi không mở web.

Về mô hình, ba trường ngày sẽ khó biểu diễn nhiều việc khác nhau sau cùng một cuộc gặp, lịch lặp, đổi người phụ trách, hoãn việc, hoàn thành từng việc hoặc lưu lịch sử thay đổi.

### 3.6. Staff Assessment và dashboard

`staff_asst` có user, tuần/tháng/năm và nhận xét. Menu đang ẩn, controller trống, dump không có dữ liệu. Đây là dấu vết của nhu cầu quản lý hiệu quả làm việc, chưa phải hệ thống KPI có công thức và mục tiêu. Trang tổng quan hiện chủ yếu đếm Contact, Report và Assessment cho một số tài khoản quản trị. [S2, S10]

## 4. Dữ liệu thô trong docs cho biết điều gì?

### 4.1. Ba nhu cầu đang hiện rõ trong workbook

| Sheet hiện | Nội dung quan sát được | Hướng phát triển tương ứng |
|---|---|---|
| Sales Report | Nhật ký làm việc theo tuần, thông tin người liên hệ, nhu cầu, hành động tiếp theo | Chuẩn hóa Activity và kết quả sau mỗi lần tiếp xúc |
| Revenue Report | Công ty, người liên hệ, check-in/out, loại phòng, số phòng, giá, room nights, revenue, khách sạn | Booking/Production và đối soát kết quả kinh doanh |
| Sales Plan | Lịch tuần theo ngày, sáng/chiều, khung giờ, công ty định gặp và công việc nội bộ | Kế hoạch tuần, lịch và task |

Ngoài ra có 4 sheet ẩn: Plan Sept 13-17, Feb 14-18, 31 Mar-4 Apr, 7-11 Apr. Đây là các mẫu/lịch sử thuộc nhiều giai đoạn, không nên nhập tất cả như dữ liệu hiện hành cùng một kỳ. [S1]

Tài liệu cho thấy doanh nghiệp đã có nhu cầu **lập kế hoạch, ghi nhận hoạt động và theo dõi kết quả thương mại**. Mã nguồn hiện tập trung vào hồ sơ và nhật ký, chưa biểu diễn đủ kế hoạch và kết quả booking.

### 4.2. Những lưu ý thực tế khi số hóa workbook

- Sales Report ghi kỳ tại C2 là 02–06/11/2015, nhưng các ngày chi tiết ở A7:A17 thuộc 16–20/11/2015. Khi nhập cần giữ cả thông tin nguồn và đánh dấu mâu thuẫn kỳ.
- Revenue Report có 28 dòng chi tiết từ dòng 6 đến 33. Tổng các giá trị lưu sẵn là 100 room nights và 9.175 đơn vị tiền; ARR theo bảng là 91,75. Chưa có trường tiền tệ đủ rõ để gán các giá trị này thành USD hay VND.
- D7:E7 ghi 22–27/11/2015, chênh lệch 5 đêm, nhưng F7 lưu 4; J7 và K7 tính theo 4 đêm. Cần người nghiệp vụ xác nhận, không tự sửa thành số liệu đúng chỉ theo công thức.
- Kỳ Revenue Report là 16–20/11/2015 nhưng nhiều ngày lưu trú nằm trong tương lai so với kỳ báo cáo. Vì vậy đây có thể là kết quả booking được ghi nhận trong tuần, chưa đủ căn cứ gọi là doanh thu thực hiện trong tuần.
- Một số dòng không lặp lại công ty/người liên hệ. Khi nhập phải xét vùng gộp và nhóm dữ liệu, không tự động kế thừa mọi ô trống trên toàn sheet.
- Có công thức và giá trị lưu sẵn cùng tồn tại, có liên kết workbook ngoài và tên vùng chứa `#REF!`. Các giá trị nêu trên được đối chiếu từ XML/giá trị lưu sẵn, không phải kết quả tính lại trong Excel. Workbook theo chuẩn Strict OOXML khiến công cụ đọc ban đầu không mở trực tiếp được; khảo sát dùng nội dung XML trong file, không chỉnh sửa nguồn.
- Tên khách sạn trong mẫu Excel khác danh mục hiện tại trong mã nguồn. Cần ánh xạ theo đúng đơn vị và giai đoạn sử dụng, không gom máy móc thành một danh mục khách sạn đang hoạt động.

## 5. Chất lượng dữ liệu và tác động đến nghiệp vụ

Thống kê bằng cách đọc các bản ghi INSERT trong dump, không dùng số AUTO_INCREMENT để ước lượng số dòng. [S2]

| Quan sát từ dump | Kết quả | Ý nghĩa và giới hạn |
|---|---:|---|
| Contact | 1.260 dòng | Là số dòng hồ sơ, chưa phải số doanh nghiệp duy nhất |
| Sales Report | 155 dòng | Ngày hoạt động từ 09/09/2016 đến 07/01/2017 |
| Incident / Staff Assessment | 0 / 0 dòng | Không đủ dữ liệu để suy ra quy trình thực tế |
| Users / Roles | 8 / 6 dòng | Không tương đương số nhân sự đang làm việc hoặc người dùng đang hoạt động |
| Trackings / User History | 8 / 1.351 dòng | Có dấu vết ghi nhận lịch sử; chưa chứng minh audit phủ hết thao tác |
| Nhóm trùng tên công ty sau chuẩn hóa chữ và khoảng trắng | 160 nhóm, 185 dòng vượt quá một dòng/nhóm | Có thể là nhiều người liên hệ hợp lệ; phải xem xét trước khi gộp |
| Sales Report khớp tên công ty với Contact theo cùng phép chuẩn hóa | 29/155 | 126 dòng còn lại chưa nối được bằng cách so tên đơn giản; có thể do tên khác hoặc chưa có trong Contact |
| Sales Report có Done = Yes | 155/155 | Không dùng tỷ lệ này làm tỷ lệ thắng hay hiệu quả chăm sóc |
| Lịch ENT / Sales / Site Inspection có ngày khác rỗng, NULL, 0, zero-date | 6 / 58 / 0 báo cáo | Chỉ là độ đầy đủ trường lịch; không phải số cuộc hẹn đã thực hiện |
| Contact thiếu `managed_by` / `account_type` | 31 / 198 dòng | Cần phân công và phân loại trước khi dùng dashboard theo chủ sở hữu/phân khúc |

Định nghĩa “thiếu” trong bảng thống kê: chuỗi rỗng, NULL, `0` hoặc `0000-00-00`; chưa tính trường hợp có giá trị nhưng sai nghiệp vụ. Dữ liệu Contact được cập nhật trong các năm 2016–2020. Không nên lấy các con số này làm baseline vận hành năm 2026 nếu chưa kiểm tra dữ liệu thực tế.

Các điểm cần chốt lại trong từ điển dữ liệu:

1. Contact dùng `account_type` cho LEISURE/CORPORATE/OTA; Report dùng cùng tên trường cho MP/WP/BP. Đề xuất tách thành “phân khúc” và “mức độ tiềm năng/nhóm tài khoản”, tên chính thức của nhóm sau chờ xác nhận.
2. `objective` là mục đích của lần tiếp xúc, tách khỏi stage của cơ hội bán hàng.
3. `done` của báo cáo tách khỏi trạng thái task, trạng thái booking và kết quả thắng/thua.
4. `managed_by`, `added_by`, `updated_by` là ba vai trò khác nhau: người sở hữu quan hệ, người lập và người sửa.
5. Thời hạn giá cần phân biệt ngày được đặt, ngày lưu trú và thời gian hiệu lực hợp đồng.

## 6. Các khoảng trống quan trọng nhất

| Khoảng trống | Hệ quả trong công việc | Cách mở rộng |
|---|---|---|
| Contact và Report chưa có khóa khách hàng chung | Khó xem toàn bộ lịch sử, dễ nhập lại khách | Account/Contact ID, công cụ nối dữ liệu cũ |
| Chưa có công việc độc lập | Follow-up nằm trong văn bản, khó biết ai đang trễ | Task có hạn, chủ việc, trạng thái, lịch sử |
| Chưa có đối tượng cơ hội | Không quản lý rõ từng nhu cầu, giá trị, xác suất và ngày chốt | Opportunity và các nhu cầu/phương án theo khách sạn |
| Kết quả doanh thu nằm ngoài hệ thống | Chưa biết hoạt động nào mang lại booking và room nights | Booking/Production, mã nguồn, đối soát |
| Incident chưa thành quy trình đầy đủ | Khó phân công, chuyển bộ phận, kiểm soát thời hạn | Case và các mốc tiếp nhận, phản hồi, giải quyết, đóng |
| Phân quyền dữ liệu chưa đồng nhất | Khó mở cho nhiều đội/khách sạn | Phạm vi Own/Team/Hotel/All áp dụng phía máy chủ |
| Danh mục và dữ liệu cũ chưa chuẩn | Dashboard có thể chia nhầm một khách thành nhiều khách | Danh mục có mã, alias, kiểm tra nhập và đối soát |

## 7. Danh mục mở rộng theo ưu tiên

P0 là nền tảng cần có trước khi mở rộng sử dụng. P1 là chức năng tạo vòng quản lý thương mại đầy đủ. P2 triển khai sau khi dữ liệu và cách vận hành ổn định. Thứ tự là đề xuất dựa trên tài liệu này, chưa phải cam kết phát hành.

| Ưu tiên | Hạng mục | Phạm vi cụ thể | Giá trị mang lại |
|---|---|---|---|
| P0 | Hồ sơ khách hàng thống nhất | Account, nhiều Contact, chủ sở hữu, alias, liên kết hoạt động, lịch sử bàn giao | Tra cứu lịch sử và tránh thất thoát quan hệ khi đổi sales |
| P0 | Sales Plan và Task/Reminder | Lịch ngày/tuần, việc quá hạn, người phụ trách, nhắc trước hạn, hoãn/hủy/hoàn thành | Biến follow-up thành việc có thể quản lý |
| P0 | Phạm vi dữ liệu và audit | Quyền theo thao tác và Own/Team/Hotel/All; quyền xuất riêng; nhật ký thay đổi | Đủ nền tảng để nhiều bộ phận dùng chung |
| P0 | Chuẩn hóa và phục hồi luồng hiện có | Kiểm tra Incident/reminder thiếu backend, chuẩn hóa import, ngày, danh mục | Tránh xây chức năng mới trên luồng chưa hoàn chỉnh |
| P1 | Cơ hội và nhu cầu khách | Sản phẩm, khách sạn, ngày dự kiến, số phòng/đêm, giá trị, stage, lý do thua | Quản lý pipeline thay vì chỉ đếm báo cáo |
| P1 | Báo giá và hợp đồng giá | Phiên bản báo giá, phòng/gói, tiền tệ, điều kiện, hiệu lực, phê duyệt ngoại lệ | Theo dõi từ tư vấn đến thỏa thuận thương mại |
| P1 | Booking/Production | Import booking có mã nguồn, trạng thái, phòng/đêm, tiền tệ, doanh thu dự kiến/thực hiện | Đánh giá kết quả theo account/sales/hotel |
| P1 | Incident/Feedback workflow | Phân loại, độ ưu tiên, chủ xử lý, bộ phận, hạn phản hồi/giải quyết, trao đổi, mở lại | Giảm phản hồi bị bỏ sót, hỗ trợ giữ khách |
| P1 | Dashboard và review tuần | Kế hoạch so thực hiện, task quá hạn, pipeline, room nights, doanh thu, phản hồi mở | Quản lý ra quyết định từ cùng một nguồn |
| P2 | Chăm sóc chủ động | Nhắc gia hạn hợp đồng, khách ngưng gửi booking, chăm sóc sau lưu trú | Tăng tần suất và chất lượng chăm sóc |
| P2 | Đồng bộ lịch và hệ thống khách sạn | Lịch cá nhân, email; PMS/reservation tùy API và hệ thống thực tế | Giảm nhập liệu lặp sau khi đã chuẩn hóa quy trình |
| P2 | Hỗ trợ AI | Tóm tắt lịch sử, trích việc từ báo cáo, soạn nháp báo cáo tuần | Tiết kiệm thao tác; người dùng duyệt việc và thông điệp trước khi thực hiện |

Các đầu nối email/lịch/PMS ở đây mới là hướng sản phẩm, chưa xác định nhà cung cấp, giao thức hay khả năng tích hợp thực tế.

### 7.1. Hồ sơ khách hàng thống nhất

Trang Account nên có: doanh nghiệp, người liên hệ và vai trò, sales phụ trách theo khách sạn, lịch sử hoạt động, việc mở, cơ hội, hợp đồng giá, booking/production và phản hồi liên quan.

Không tự hợp nhất hai người liên hệ chỉ vì cùng công ty/email chung. Không xem tên giống là chứng cứ đủ để gộp hai doanh nghiệp. Khi gộp phải lưu mã cũ, lịch sử nguồn và cách hoàn tác. Khi sales nghỉ việc phải có bàn giao account, task và opportunity, không thay `added_by` của các hoạt động quá khứ.

### 7.2. Sales Plan, Activity và Task

Tách ba khái niệm:

- **Kế hoạch:** dự định làm gì, khi nào, với khách nào.
- **Activity:** điều đã thực hiện và kết quả trao đổi.
- **Task:** việc còn phải làm, người chịu trách nhiệm và hạn hoàn thành.

Một cuộc gặp có thể sinh nhiều task. Ví dụ: gửi báo giá hôm nay, xin lịch khảo sát ngày mai và gọi xác nhận sau ba ngày. Hoàn thành báo cáo cuộc gặp không tự hoàn thành ba việc này.

Task tối thiểu cần account, contact tùy chọn, activity/opportunity/case nguồn, loại việc, chủ việc, người phối hợp, thời hạn có múi giờ, ưu tiên, trạng thái và kết quả hoàn thành. Khi lập lịch gặp cần thêm giờ bắt đầu/kết thúc và địa điểm. Quản lý trạng thái Open/In progress/Done/Cancelled; quá hạn là điều kiện tính từ hạn và trạng thái. Lịch lặp có thể làm sau.

Nhắc việc cần lưu lịch sử gửi, chống gửi lặp cùng mốc, cập nhật khi đổi hạn và ngừng nhắc khi hoàn thành/hủy. Chọn kênh nhắc theo môi trường sử dụng thực tế; MVP có thể bắt đầu bằng trong ứng dụng, bổ sung email khi kênh gửi đã được cấu hình và kiểm tra.

### 7.3. Cơ hội bán hàng, báo giá và booking

Một account có thể đồng thời có nhu cầu khách lẻ, đoàn và MICE. Mỗi nhu cầu cần cơ hội riêng, có sales owner, khách sạn/phương án, ngày lưu trú/sự kiện, phòng/khách dự kiến, budget, giá trị, tiền tệ, stage, bước tiếp theo và ngày chốt dự kiến.

Stage đề xuất: Tiếp nhận → Xác minh nhu cầu → Đã báo giá → Đàm phán → Thắng/Thua/Hủy. Bắt buộc định nghĩa điều kiện chuyển stage; “đã gọi” không có nghĩa “đã xác minh”, “đã báo giá” không có nghĩa booking đã chắc chắn.

Một cơ hội có thể có nhiều phương án hoặc booking ở các khách sạn. Không cộng toàn bộ giá trị các báo giá thay thế vào pipeline. Cần xác định cách chọn phương án dự kiến và cách phân bổ khi một nhu cầu thực sự chia cho nhiều khách sạn.

Báo giá nên có phiên bản, khách sạn, loại phòng, số lượng, ngày, giá/đêm hoặc giá gói, tiền tệ, điều kiện thuế/phí, phụ thu, hủy và hiệu lực. Phê duyệt khi vượt hạn mức giảm giá được doanh nghiệp quy định. Hợp đồng giá tách khỏi Contact, có thể áp dụng cho account và từng khách sạn.

Booking cần mã tham chiếu, nguồn, trạng thái Tentative/Confirmed/Cancelled/No-show/Stayed theo quy ước được chốt, liên kết account/contact/opportunity và chủ sở hữu doanh số. Lưu riêng giá trị dự kiến, kết quả lưu trú và điều chỉnh/hủy. Import theo khóa nguồn để tải lại cùng file không tạo thêm booking.

### 7.4. Incident và phục hồi trải nghiệm khách hàng

Luồng đề xuất: Tiếp nhận → Phân loại và đánh giá mức độ → Giao chủ xử lý → Phối hợp bộ phận → Xử lý → Xác nhận kết quả → Đóng hoặc mở lại.

Case cần mô tả ban đầu, thời điểm xảy ra/tiếp nhận, khách sạn, nguồn phản hồi, khách/booking nếu biết, account/đại lý liên quan nếu xác định được, loại phản hồi, độ ưu tiên, người chịu trách nhiệm chính, bộ phận phối hợp, các mốc hạn và lịch sử xử lý. Cho phép feedback tích cực, yêu cầu và khiếu nại; không mặc định mọi feedback là sự cố.

Tách “đã xử lý nội bộ” và “khách đã xác nhận”, cũng như ghi chú nội bộ và thông tin được phép chia sẻ cho sales. SLA phải được cấu hình theo loại/mức độ và giờ phục vụ, chưa gán cam kết thời gian khi chưa có chính sách.

Ví dụ: khách do đại lý gửi phàn nàn về phòng → FO tiếp nhận và giao bộ phận xử lý → ghi kết quả → sales phụ trách account nhận task hỏi thăm đại lý. Liên kết này giúp xử lý dịch vụ và chăm sóc thương mại nối với nhau, đồng thời giới hạn thông tin theo vai trò.

## 8. Mô hình dữ liệu mục tiêu

Đây là mô hình khái niệm để định hướng, chưa phải thiết kế bảng cuối cùng.

| Thực thể | Vai trò | Quan hệ chính |
|---|---|---|
| Account | Doanh nghiệp/đại lý | Nhiều Contact, Opportunity, Agreement và Activity |
| Contact | Người liên hệ | Thuộc Account; liên kết hoạt động/cơ hội/booking |
| Hotel | Khách sạn | Phạm vi quyền, phương án báo giá, booking và incident |
| AccountAssignment | Phân công chăm sóc | Account + User + Hotel/phạm vi + thời gian hiệu lực |
| Activity | Lần tiếp xúc thực tế | Account, contact tùy chọn, sales, cơ hội và task liên quan |
| Task/Appointment | Việc và lịch phải thực hiện | Chủ việc, thời hạn, đối tượng nguồn, lịch sử trạng thái |
| Opportunity | Một nhu cầu thương mại | Account, nhiều phương án khách sạn và phiên bản báo giá |
| Quote / QuoteLine | Báo giá và các hạng mục | Opportunity, hotel, phòng/gói, điều kiện giá |
| RateAgreement | Thỏa thuận/hợp đồng giá | Account, Hotel, hiệu lực và điều kiện |
| Booking / BookingLine | Đặt dịch vụ và chi tiết | Mã nguồn duy nhất, account, hotel, opportunity tùy trường hợp |
| Production | Kết quả phục vụ/doanh thu | Chi tiết theo booking, ngày dịch vụ, tiền tệ, điều chỉnh |
| Guest / Stay reference | Khách lưu trú hoặc tham chiếu PMS | Dữ liệu tối thiểu cần cho booking và case |
| Case / CaseEvent | Phản hồi và tiến trình xử lý | Hotel, booking/guest/account khi biết; chủ xử lý và bộ phận |
| Role / Permission / Scope | Quyền và phạm vi | User, đội, khách sạn; quyền xuất dữ liệu riêng |
| AuditEvent / ImportBatch | Lịch sử và truy xuất nguồn | Ai làm gì, khi nào, dữ liệu nguồn, kết quả đối soát |

Trong MVP, tập trung Account, Contact, Assignment, Activity, Task, Hotel và Scope. Chưa cần triển khai tất cả thực thể cùng lúc. Giữ snapshot thông tin trong báo cáo cũ nếu cần phản ánh đúng bối cảnh lịch sử, đồng thời thêm ID tham chiếu về dữ liệu chuẩn.

## 9. Báo cáo và chỉ số nên bổ sung

| Câu hỏi quản lý | Chỉ số/định nghĩa đề xuất | Điều kiện dữ liệu |
|---|---|---|
| Sales có thực hiện kế hoạch? | Việc kế hoạch đã hoàn thành / việc kế hoạch đến hạn trong kỳ; tách hủy và dời lịch | Kế hoạch và task có lịch sử thay đổi |
| Khách nào bị bỏ quên? | Account đang phụ trách không có tương tác trong ngưỡng cấu hình, không có bước tiếp theo | Chủ sở hữu, thời điểm tương tác và task |
| Follow-up có đúng hạn? | Task hoàn thành trước/đúng hạn / task đã hoàn thành; hiển thị thêm số việc đang quá hạn | Hạn gốc, thay đổi hạn và completed_at |
| Chất lượng cơ hội thế nào? | Giá trị pipeline theo stage, tuổi cơ hội, số cơ hội thiếu bước tiếp theo | Opportunity, stage và lịch sử |
| Tỷ lệ thắng ra sao? | Số cơ hội thắng / (thắng + thua) trong kỳ đóng; tách hủy | Kết quả và closed_at, không dùng Done của Report |
| Khách và sales tạo ra bao nhiêu kết quả? | Room nights, doanh thu theo account/hotel/sales và kỳ | Booking, kết quả phục vụ, quy tắc ghi nhận doanh số |
| Giá phòng bình quân? | Doanh thu phòng phù hợp / room nights phù hợp | Cùng kỳ, tiền tệ và cơ sở thuế/phí; xác định mẫu số 0 |
| Chất lượng xử lý phản hồi? | Thời gian phản hồi đầu, thời gian giải quyết, tỷ lệ đúng hạn, mở lại | Mốc thời gian case và lịch SLA |

Các nguyên tắc bắt buộc khi làm báo cáo:

- Tách kỳ tạo booking, kỳ lưu trú và kỳ ghi nhận doanh thu. Một booking qua tháng có thể cần phân bổ theo ngày dịch vụ.
- Tách dự kiến và thực hiện; phản ánh hủy, no-show, đổi giá và điều chỉnh.
- Không cộng các tiền tệ khác nhau; quy đổi phải có tỷ giá, ngày và nguồn tỷ giá được chốt.
- Room nights đơn giản bằng số phòng × số đêm chỉ khi cả kỳ có cùng số lượng phòng. Dữ liệu thay đổi theo ngày cần tính chi tiết từng ngày.
- Với doanh thu theo sales, phải chốt sales tại thời điểm nào được hưởng ghi nhận và cách chia khi nhiều người tham gia; không gán lại doanh thu lịch sử theo người đang phụ trách hiện tại.
- Chưa có dữ liệu phòng khả dụng để tính công suất phòng/RevPAR toàn khách sạn từ bộ dữ liệu này. Không đưa các chỉ số đó lên dashboard chỉ bằng dữ liệu sales.

## 10. Lộ trình triển khai đề xuất

Chia theo đầu ra và điều kiện chuyển giai đoạn. Chưa ước lượng số tuần hoặc chi phí vì chưa biết đội triển khai, hệ thống đang chạy và phạm vi tích hợp.

### Giai đoạn 0 — Chốt nghiệp vụ và rà soát phiên bản vận hành

Đầu ra: từ điển dữ liệu, ma trận quyền, nguồn dữ liệu chuẩn, sơ đồ luồng được xác nhận, baseline dữ liệu thực tế.

- Xác nhận Incident/reminder có backend ở phiên bản khác hay cần khôi phục.
- Chốt MP/WP/BP, sales owner, công ty và người liên hệ, chính sách khóa 7 ngày.
- Chốt các trạng thái task/cơ hội/booking, kỳ doanh thu và quyền theo khách sạn.
- Kiểm tra khả năng cung cấp booking từ reservation/PMS; nếu chưa có thì chuẩn bị mẫu import.

Điều kiện chuyển: các định nghĩa và dữ liệu mẫu được sales, quản lý và người phụ trách booking thống nhất.

### Giai đoạn 1 — MVP: hồ sơ thống nhất và không bỏ sót follow-up

Phạm vi: Account/Contact, mapping dữ liệu cũ, chủ sở hữu, Activity liên kết ID, Task, lịch tuần, reminder, quyền theo phạm vi và audit cho các luồng này. Giữ khả năng ghi báo cáo quen thuộc để chuyển đổi dễ hơn.

Các tiêu chí nghiệm thu chính:

1. Chọn một Account xem được người liên hệ, hoạt động cũ đã mapping và các việc đang mở; bản ghi chưa mapping được hiển thị để xử lý, không bị bỏ.
2. Một Activity tạo được nhiều Task; mỗi Task có chủ việc và hạn; hoàn thành Activity không tự đóng Task.
3. Người được giao nhìn thấy việc hôm nay/quá hạn. Đổi hạn, giao lại, hoàn thành hay hủy đều có lịch sử; nhắc đúng người và không gửi trùng cùng mốc.
4. Phân quyền được áp dụng cho danh sách, xem/sửa theo ID, import và export. Sales/đội/khách sạn ngoài phạm vi không truy cập được chỉ bằng đổi URL.
5. Import có xem trước, thông báo lỗi theo dòng, báo cáo đối soát và cơ chế chống nhập lặp theo chính sách đã chốt.
6. Dữ liệu cũ được đối chiếu số dòng và tổng số đối tượng đã mapping; có bản sao nguồn và khả năng quay về phiên bản trước khi chuyển đổi.

### Giai đoạn 2 — Quản lý cơ hội và kết quả booking

Phạm vi: Opportunity, báo giá cơ bản, thỏa thuận giá, import Booking/Production, dashboard kế hoạch–hoạt động–kết quả.

Điều kiện nghiệm thu: đi được từ hoạt động sang cơ hội, báo giá và booking; tải lại cùng nguồn không cộng trùng; tổng room nights/doanh thu khớp nguồn theo tiền tệ và kỳ đã định nghĩa; theo dõi được các trường hợp hủy và điều chỉnh.

### Giai đoạn 3 — Phối hợp chăm sóc khách và mở rộng tích hợp

Phạm vi: Case/Incident đầy đủ, SLA, chăm sóc sau xử lý, cảnh báo gia hạn và khách giảm sản lượng; đồng bộ hệ thống/lịch khi có nhu cầu và khả năng kỹ thuật thực tế.

Nếu bộ phận vận hành đang dùng Incident hằng ngày hoặc có nhiều phản hồi bỏ sót, đưa Case tối thiểu lên chạy song song giai đoạn 1. Không cần chờ hoàn thành Revenue để sửa luồng tiếp nhận và giao xử lý.

AI triển khai sau: thử trước việc tóm tắt báo cáo và đề xuất task có người duyệt. Đánh giá độ đúng của trích xuất, quyền truy cập tài liệu và mức thời gian tiết kiệm trước khi mở tự động hóa hành động.

## 11. Định hướng kỹ thuật và chuyển đổi dữ liệu

Đề xuất nâng cấp theo từng phần, giữ nguyên dữ liệu nguồn và cách làm việc có giá trị. Chưa có căn cứ để quyết định viết lại toàn bộ hay chọn framework mới chỉ từ khảo sát nghiệp vụ.

Các việc kỹ thuật gắn trực tiếp với mở rộng:

- Dùng ID và quan hệ dữ liệu rõ ràng thay cho nối bằng tên, chuẩn hóa quan hệ nhiều khách sạn/market/role. Trước khi đặt ràng buộc phải làm sạch và giải quyết dữ liệu mồ côi.
- Chuyển danh mục trong file PHP thành dữ liệu quản trị được ở những nơi nghiệp vụ cần thay đổi. Dùng mã ổn định, có trạng thái ngừng sử dụng và alias tên cũ.
- Chuẩn hóa Unicode và các trường ngày/giờ. Schema Incident trong dump là latin1; không chuyển mã hàng loạt trước khi kiểm tra encoding thực tế. Có migration về ngày tùy chọn trong mã nguồn, chưa xác nhận đã áp dụng lên DB vận hành.
- Áp dụng chính sách truy cập chung ở máy chủ. Đặc biệt kiểm tra Contact, truy cập Report theo ID, tài khoản nhiều role và quyền export; tránh phụ thuộc vào việc ẩn menu.
- Tách dữ liệu báo cáo đã chốt và dữ liệu lịch/task còn tiếp tục thay đổi. Nếu giữ khóa sửa 7 ngày, bổ sung quyền mở lại có lý do và audit.
- Có worker/lịch nền khi cần nhắc ngoài phiên truy cập web; lưu kết quả gửi, retry và chống gửi lặp.
- Chuẩn hóa mật khẩu và chính sách xác thực trước khi mở rộng tài khoản/tích hợp. Mã hiện có băm `sha1(md5(...))`; lập phương án di chuyển sang cơ chế băm mật khẩu phù hợp và xử lý nhánh LDAP, giữ quy trình đăng nhập ổn định trong chuyển đổi. Đây là quan sát mã nguồn, chưa phải kiểm toán bảo mật đầy đủ.
- Schema đang trộn InnoDB và MyISAM, chưa thấy khóa ngoại trong dump. Nếu cần giao dịch nguyên khối cho tạo cơ hội/booking hoặc import nhiều bảng, phải chuẩn bị cơ chế lưu trữ và giao dịch phù hợp.
- Audit cần lưu người làm, đối tượng, trước/sau, thời điểm, lý do và import batch. Sự tồn tại của bảng `trackings` chưa đủ để bảo đảm mọi thay đổi đều được ghi.

Quy trình chuyển đổi dữ liệu đề xuất:

1. Chụp bản sao dữ liệu vận hành, lưu nguồn và phiên bản mapping.
2. Tách doanh nghiệp và người liên hệ; tạo danh sách nghi trùng để người phụ trách duyệt.
3. Mapping `managed_by`, tên khách sạn, market, phân khúc; không đoán thay cho giá trị chưa biết.
4. Thêm ID tham chiếu cho Report/Task, giữ nội dung và người lập lịch sử; đưa bản ghi chưa khớp vào danh sách xử lý riêng.
5. Chuẩn hóa ngày, ô gộp và các sai lệch trong Excel; xác nhận tiền tệ và chính sách doanh thu trước khi nhập Revenue.
6. Chạy thử với một đội/khách sạn; đối chiếu bản ghi và các tổng kiểm soát, kiểm tra quyền, lấy phản hồi rồi mới mở rộng.

## 12. Các quyết định cần chốt trước khi đặc tả chi tiết

1. Phần mềm hiện phục vụ khách sạn/đội nào? Mẫu Excel là tham chiếu nghiệp vụ hay dữ liệu cần nhập thật?
2. MP/WP/BP nghĩa chính thức là gì, ai được đổi và dựa trên tiêu chí nào?
3. Một account có một sales chung hay nhiều sales theo khách sạn/thị trường? Bàn giao và chia doanh số thế nào?
4. Contact DB có chủ yếu là doanh nghiệp/đại lý hay gồm cả khách cá nhân? Có cần quản lý Guest riêng hay chỉ tham chiếu PMS?
5. Reminder nhắc những loại việc nào, trước hạn bao lâu, ở kênh nào, ai nhận việc quá hạn? Có cần quản lý duyệt kế hoạch tuần không?
6. Có giữ chính sách khóa Report sau 7 ngày không? Nhận xét của quản lý được cập nhật thế nào sau khi khóa?
7. Thế nào là cơ hội thắng: đồng ý báo giá, có booking xác nhận, đã đặt cọc hay đã lưu trú?
8. Revenue Report theo tuần đặt booking hay tuần lưu trú? Tiền tệ, thuế/phí, hủy, no-show, điều chỉnh và cách gán doanh số cho sales được tính thế nào?
9. Nguồn booking chuẩn ở đâu? Có mã account/booking ổn định để mapping và chống trùng không?
10. Incident gồm khiếu nại, yêu cầu, khen ngợi hay cả sự cố nội bộ? Đơn vị nào nhận chính, khi nào phải phản hồi và ai được đóng/mở lại?
11. Sales có được đọc toàn bộ phản hồi của khách lưu trú hay chỉ thông tin cần cho chăm sóc account?
12. Nhu cầu quan trọng nhất trong đợt đầu là giảm nhập liệu, kiểm soát follow-up, đo doanh thu hay điều phối phản hồi? Dùng câu trả lời để chốt phạm vi MVP và tiêu chí thành công.

## 13. Nguồn đối chiếu

Các liên kết dưới đây trỏ đến dữ liệu và mã nguồn trong cùng dự án. Không sao chép dữ liệu liên hệ hay thông tin xác thực vào tài liệu phân tích.

| Mã | Nguồn | Nội dung sử dụng |
|---|---|---|
| S1 | [sale_report.xlsx](sale_report.xlsx) | 7 sheet, mẫu Sales Report, Revenue Report, Sales Plan, công thức và các điểm bất nhất |
| S2 | [Dump dữ liệu](../source/_db/hotramre_app.sql) | Schema 9 bảng, thống kê bản ghi, danh mục role, đặc điểm dữ liệu cũ |
| S3 | [Danh mục](../source/confgs/) | hotels, account_type, account_type2, sales_type, objective, expected_result, market, dept, remarks, user_group |
| S4 | [Contact model](../source/protected/models/Contact.php), [controller](../source/protected/controllers/ContactController.php), [form](../source/protected/views/contact/_form.php), [CSV](../source/protected/components/ContactCsv.php), [XLSX](../source/protected/components/ContactExcel.php) | Hồ sơ, chủ sở hữu, nhập/xuất |
| S5 | [Report model](../source/protected/models/Report.php), [controller](../source/protected/controllers/ReportController.php), [form](../source/protected/views/report/_form.php), [danh sách](../source/protected/views/report/admin.php) | Hoạt động, sao chép công ty, lọc, khóa sửa, lịch hẹn và luồng export |
| S6 | [Incident model](../source/protected/models/Incident.php) | Tên bảng, trường bắt buộc, liên kết người cập nhật |
| S7 | [Footer](../source/protected/components/views/footer.php), [Reminder](../source/protected/views/ajax/reminder.php), [AjaxController](../source/protected/controllers/AjaxController.php) | Điểm gọi nhắc việc, giao diện, backend chưa có nội dung |
| S8 | [Users](../source/protected/models/Users.php), [UsersController](../source/protected/controllers/UsersController.php), [Role](../source/protected/models/Role.php), [RoleHelper](../source/protected/helpers/RoleHelper.php), [ManagementController](../source/protected/components/ManagementController.php), [UserIdentityAm](../source/protected/components/UserIdentityAm.php) | Tài khoản, vai trò, quyền thao tác, xác thực |
| S9 | [Module](../source/confgs/module.php), [menu](../source/confgs/menu.php), [header](../source/protected/components/views/header.php), [role actions](../source/confgs/roles.php) | Phân hệ được hiển thị, phần đang bị ẩn |
| S10 | [StaffAsst](../source/protected/models/StaffAsst.php), [SiteController](../source/protected/controllers/SiteController.php), [migration ngày tùy chọn](../source/protected/migrations/m261118_090000_restore_optional_dates.php) | Đánh giá nhân viên, tổng quan, khác biệt giữa schema dump và hướng cập nhật |
