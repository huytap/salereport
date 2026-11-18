# WP03: Preview, mapping, import và đối soát Contact/Report cũ

Ngày thực hiện: 18/11/2026. Phạm vi: chuyển dữ liệu từ các bảng Contact/Report cũ trong DB test sang hồ sơ chuẩn; chưa nhập dữ liệu thật hoặc chạy migration trên DB vận hành.

## 1. Kết quả đã triển khai

- Giao diện chọn bản ghi nguồn, tạo snapshot, duyệt mapping từng dòng, xác nhận cả đợt, import theo từng phần và xem đối soát.
- Dịch vụ đọc nguồn/gợi ý: `LegacyImportSource`.
- Dịch vụ điều phối: `CustomerImportService`.
- Controller HTML/JSON: `CustomerimportController`.
- CLI `customerbackfill` cho preview, commit đợt đã duyệt và đối soát.
- Migration metadata `m261118_190000_prepare_legacy_import` và các kiểm thử import/độ bền dữ liệu.

Tính năng có hai lớp chặn: `legacyImportEnabled` mặc định false ở cấu hình chung; service/migration chỉ chấp nhận `IsolatedTestDbConnection` và kiểm tra schema thực tế. Cấu hình test/CLI bật riêng tính năng này. Việc cấp thêm action ở DB vận hành không tự mở luồng import.

WP03 hiện nhận nguồn DB `contact` và `reports`. Không nhận bảng tùy ý, không tự đọc CSV/XLSX hoặc SQL dump chứa dữ liệu thật. Có thể dùng bản sao dữ liệu đã được chuẩn bị trong schema test phù hợp ở bước xác nhận nghiệp vụ tiếp theo.

## 2. Luồng sử dụng

### Bước 1 — Chọn nguồn và tạo preview

Trong môi trường web nạp `config/test.php`, mở `/customerimport/admin` bằng tài khoản Active có All và action tương ứng.

1. Chọn Contact DB hoặc Sales Report.
2. Chọn checkbox từ danh sách nguồn phân trang 50 dòng, hoặc nhập các mã nguồn. Tối đa 500 bản ghi/đợt.
3. Tạo bản xem trước. Hệ thống lưu nguyên bản ghi vào staging cùng fingerprint, chưa tạo Account/Contact/Assignment/LegacyLink và chưa cập nhật Report.
4. Xem các dòng cùng tên, Account có tên tương tự/alias đã duyệt, người liên hệ trùng tên/email, khách sạn và người phụ trách có thể phù hợp.

Các gợi ý chưa phải quyết định nhập. Bản ghi nguồn không tồn tại sẽ làm yêu cầu preview bị từ chối thay vì âm thầm bỏ bớt dòng.

### Bước 2 — Duyệt mapping từng dòng

Mỗi dòng cần một quyết định có lý do:

| Quyết định | Hành vi |
|---|---|
| Nhập/nối hồ sơ | Chọn tạo Account mới, nối Account có sẵn hoặc dùng Account của một dòng trước trong cùng đợt |
| Giữ mapping đã nhập | Chỉ dùng khi nguồn đã có LegacyLink và fingerprint được kiểm chứng từ lần nhập trước |
| Bỏ qua | Ghi nhận quyết định bỏ qua; không xóa hoặc thay đổi mapping đã có |

Khi nhập/nối hồ sơ:

- Người liên hệ có thể tạo mới, nối người có sẵn, dùng người của dòng trước hoặc không tạo nếu thiếu tên/không cần.
- Chọn Hotel và owner. Report phải chọn đúng một Hotel; Contact có thể có nhiều Hotel.
- “Giữ người phụ trách hiện có” không tự bàn giao. Nếu chưa có Assignment thì để chưa phân công khi không chọn owner.
- Người được giao phải Active và có quyền Hotel hoặc All. Không tự cấp Hotel grant để làm cho mapping hợp lệ.
- Có thể hiệu chỉnh các trường hồ sơ khi **tạo mới**, ví dụ email không hợp lệ hoặc ngày sinh chưa rõ. Không dùng import để ghi đè trường của Account/Contact đã có.
- Thị trường được nhập sau khi xác nhận danh sách gợi ý. Các trường giá/hợp đồng cũ vẫn giữ ở nguồn và snapshot, không chọn ngẫu nhiên thành một giá chung.

Mỗi dòng có version chống ghi đè giữa hai cửa sổ. Một dòng đã tạo/liên kết/bỏ qua thành công không được sửa mapping lại qua luồng này.

### Bước 3 — Duyệt cả đợt

Tất cả dòng phải có quyết định rõ ràng, kể cả bỏ qua. Hệ thống chốt hash của fingerprint nguồn và toàn bộ quyết định mapping.

Sửa một mapping chưa hoàn tất sẽ hủy duyệt hiện tại. Cần duyệt lại trước khi nhập tiếp; kết quả thành công của các dòng trước vẫn giữ nguyên. Người duyệt và người thực thi import được ghi riêng, không giả định là cùng tài khoản.

### Bước 4 — Commit và tiếp tục

Nút nhập xử lý tối đa 100 dòng/lượt; API/CLI hỗ trợ giới hạn 1–500 dòng.

- Mỗi dòng được ghi trong một giao dịch, gồm Account/Contact/Assignment/Market/Alias nếu có, LegacyLink, liên kết Report, kết quả dòng và audit.
- Dòng lỗi rollback toàn bộ phần ghi của chính dòng đó. Dòng khác có thể thành công và được giữ.
- Có thể chạy tiếp sau khi ngắt kết nối/lượt xử lý dừng giữa đợt.
- Dòng đã hoàn tất không chạy lại. Dòng lỗi có thể sửa mapping, duyệt lại và thử tiếp.
- Nếu snapshot nguồn đã lỗi thời, phải tạo preview mới từ nguồn hiện tại; không chỉnh snapshot cũ để làm cho fingerprint khớp.

Import có advisory lock theo tên schema thực tế. Một lượt import khác trên kết nối thứ hai nhận 409 và có thể thử lại sau. Khóa này bảo vệ các lượt importer; các service chỉnh hồ sơ ở WP04 cần phối hợp quy ước khóa nếu cùng tạo dữ liệu đồng thời.

### Bước 5 — Đối soát

Trang đối soát hiển thị:

- Tổng dòng nguồn = tạo doanh nghiệp mới + liên kết + bỏ qua + lỗi + chưa có kết quả cuối.
- Số Account, Contact và Assignment thực sự tạo; số Report đã liên kết.
- Sai lệch giữa kết quả dòng và LegacyLink/Account/Contact/Report.
- Nguồn bị mất hoặc thay đổi sau khi nhập.

`created` là dòng tạo **Account** mới, không phải số người liên hệ. Số đối tượng tạo được tính riêng từ audit, không cộng trùng khi retry. `consistent` chỉ phản ánh kiểm soát các dòng đã thành công; phải đọc thêm số lỗi và số dòng chờ để biết đợt đã xử lý xong hay chưa.

## 3. Quy tắc xử lý trùng

1. Chuẩn hóa tên doanh nghiệp theo chữ hoa/thường và khoảng trắng để gợi ý. So khớp tên/alias không tự gộp.
2. Nhiều dòng cùng doanh nghiệp có thể dùng Account của dòng đầu, đồng thời tạo nhiều người liên hệ.
3. Email dùng chung không có nghĩa là cùng một người. Gợi ý email chung yêu cầu đối chiếu tên và bối cảnh.
4. Tạo Account trùng tên đang có hoặc Contact cùng họ tên trong cùng Account sẽ bị đưa vào lỗi cần duyệt, trừ khi người duyệt xác nhận rõ là đối tượng khác và có lý do.
5. Dùng lại dòng trước tạo quan hệ phụ thuộc có thứ tự; không được trỏ về chính mình hoặc dòng phía sau.
6. Một bản ghi nguồn có khóa LegacyLink duy nhất theo loại nguồn + ID. Đổi lựa chọn đợt hoặc nhập lại không tạo thêm hồ sơ từ nguồn đó.
7. Alias chỉ được ghi khi được chọn rõ ràng; alias trỏ sang Account khác bị từ chối, không tự đổi đích.
8. Mapping cũ không có fingerprint không tự được công nhận là đã kiểm chứng bởi WP03. Giữ để kiểm tra hoặc bỏ qua, không tự viết lại/nhân đôi hồ sơ.

## 4. Bảo toàn dữ liệu và xử lý thay đổi

- Preview lưu snapshot nguyên gốc và hash ổn định. Chọn lại cùng tập ID với cùng nội dung trả về cùng đợt, kể cả thứ tự ID khác nhau.
- Hash Report tính từ nội dung legacy, tách các cột MVP để việc thêm ID liên kết không làm preview bị nhân đôi.
- Commit đọc lại và khóa nguồn; nếu nội dung đã đổi từ preview, dòng bị từ chối.
- Khi chọn Account/Contact có sẵn, version đích được chốt khi lưu mapping; commit khóa và đọc lại bản ghi hiện tại, từ chối nếu version/status đã đổi.
- Kiểm tra lại quyền người commit và người duyệt. Người duyệt mất quyền/Inactive thì phải duyệt lại bởi người có quyền.
- Không đổi owner đang có bằng import. Mâu thuẫn owner hoặc Assignment bị archive phải được xử lý qua luồng quản trị/bàn giao.
- Contact nguồn không bị chỉnh sửa. Report chỉ bổ sung các cột Account/Contact/Hotel ID, identity_state và version MVP; không ghi lại nội dung, ngày, người lập/sửa hay các trường lịch cũ.
- Zero-date trong Report cũ được giữ nguyên khi cập nhật metadata dưới strict mode. Không tạo ngày giả để làm dữ liệu “hợp lệ”.
- Không chuyển `Done=Yes` hoặc các lịch ENT/Sales/Site Inspection cũ thành task/reminder trong WP03.

Khi chuyển đổi dữ liệu thật sau này, vẫn cần cửa sổ dừng ghi các luồng legacy hoặc cơ chế đồng bộ thay đổi đã được chốt ở kế hoạch cutover. Row lock/fingerprint của importer không thay thế một chiến lược chuyển đổi cho mọi editor cũ đang chạy đồng thời.

## 5. Thay đổi schema và quyền

| Đối tượng | Metadata thêm |
|---|---|
| crm_import_batch | approval_hash, lock_version |
| crm_import_row | source_hash, decision_json, result_json, lock_version |
| crm_legacy_link | source_hash |
| crm_import_action_seed | Journal trước/sau khi thêm action map/approve/reconcile cho các role đã có commit |

Model CustomerImportRow bổ sung validation JSON cho decision/result. Các trường trên không nhận mass assignment từ form.

Migration có preflight, chỉ chấp nhận connection test. Down bị chặn khi có snapshot/fingerprint hoặc quyền đã được chỉnh sau seed. MySQL DDL và bảng roles MyISAM không có rollback nguyên khối; nếu máy/tiến trình dừng giữa migration cần kiểm tra schema/history/journal trên bản sao, không tự chạy lại up trực tiếp.

Quyền vào nguồn/batch WP03 yêu cầu **All + action tương ứng**. Hotel/Own không được xem nguồn legacy chưa mapping, kể cả khi đoán đúng ID batch hoặc có riêng action import. Đây là giới hạn an toàn của đợt chuyển đổi dữ liệu cũ, không tự mở quyền ngoài Hotel cho quản lý sales.

## 6. Endpoint và CLI

| Method/route | Mục đích |
|---|---|
| GET /customerimport/admin | Chọn nguồn và danh sách đợt; `format=json` trả danh sách batch |
| POST /customerimport/preview | source_type=contact/report, source_ids dạng mảng hoặc danh sách mã |
| GET /customerimport/view/{id} | Xem snapshot/mapping; `row` chọn dòng, `format=json` cho tích hợp |
| POST /customerimport/map/{id}?row=... | decision và expected_version của dòng |
| POST /customerimport/approve/{id} | expected_version của batch |
| POST /customerimport/commit/{id} | limit, mặc định 100 |
| GET /customerimport/reconcile/{id} | HTML; `format=json` trả kiểm soát chi tiết |

Tất cả mutation nhận CSRF khi chạy qua web. API không nhận actor/approved_by do client tự đặt. Ví dụ quyết định tạo mới:

```json
{
  "mode": "map",
  "account_kind": "create",
  "contact_kind": "create",
  "hotels": [{"hotel_id": 1, "owner_user_id": null}],
  "markets": ["EU"],
  "reason": "Đã đối chiếu hồ sơ và xác nhận nguồn"
}
```

`account_kind=link` dùng account_id; `account_kind=row` dùng account_row_no của dòng trước. Contact tương ứng dùng contact_person_id hoặc contact_row_no. Với hồ sơ đã có, bỏ owner_user_id để giữ owner; truyền null nghĩa là xác nhận chưa phân công và sẽ bị từ chối nếu làm thay đổi owner đang có.

CLI chạy tại thư mục gốc, bằng PHP 7.4, trên cấu hình CLI test đã tách:

```sh
php source/protected/yiic.php migrate up --interactive=0
php source/protected/yiic.php customerbackfill preview --source=contact --ids=1 --actor=1
```

Sau khi duyệt mapping qua giao diện/API, có thể dùng action CLI `commit` với tham số `id`, `actor`, `limit`; action `reconcile` nhận `id`, `actor`. CLI kiểm tra actor Active/All từ DB, không lấy quyền từ chuỗi truyền vào.

Đã thử CLI preview/reconcile trên `sale_report_mvp_test_restore`: đợt demo #28 chứa duy nhất Contact fixture giả #1, ở trạng thái preview, chưa duyệt và chưa tạo hồ sơ chuẩn. ID này là kết quả của lượt kiểm thử hiện tại, không phải ID cố định để tích hợp.

## 7. Kiểm thử đã thực hiện

| Kiểm tra | Kết quả |
|---|---|
| mvp-import-smoke.php | 116 assertions đạt trên DB test chính |
| mvp-import-durability.php | 12 assertions đạt với commit thật, hai kết nối và reconnect/resume |
| Suite import trên DB test phục hồi | Đã chạy đạt; các ca bổ sung cuối cùng tiếp tục được xác nhận trên DB test chính |
| mvp-schema-smoke.php | 96 assertions đạt |
| mvp-policy-smoke.php | 94 assertions đạt |
| models-smoke / management-smoke / access-smoke | Đạt |
| Migration WP03 up → down → up | Đạt trên DB test chính |
| Migration trên DB test phục hồi | Đạt |
| CLI preview/reconcile | Đạt, chỉ tạo snapshot fixture |
| PHP 7.4 lint các file thay đổi/thêm mới | Đạt |

Các tình huống import gồm: preview không tạo hồ sơ; cùng snapshot trả cùng đợt; tạo một công ty nhiều người liên hệ; email chung; trùng người cần duyệt; dùng lại dòng trước; retry không nhân đôi; lỗi sau khi tạo Account không để lại orphan/audit sai; source/Account/Contact thay đổi sau mapping; owner Inactive/không có Hotel grant; mâu thuẫn owner; Report nhiều Hotel; bảo toàn nội dung và zero-date; mapping cũ thiếu fingerprint; alias xung đột; fingerprint/Hotel của Report bị đổi; quyền người duyệt bị thu hồi; chặn POST thiếu CSRF; giao diện escape dữ liệu nguồn; advisory lock giữa hai connection.

Suite độ bền không dùng transaction bọc toàn bài: kết nối thứ hai xác nhận thấy dòng đã commit; đóng/mở lại connection chính rồi tiếp tục; sửa dòng lỗi và chạy lại mà không nhân đôi sự kiện. Test dọn đúng các record giả do chính lượt chạy tạo, không reset/truncate schema hoặc xóa fixture nền.

Các suite khác sử dụng user/role giả trong bảng tạm và rollback dữ liệu nghiệp vụ. Chưa nạp hay kiểm thử import trên một bản sao dữ liệu khách hàng thật.

### Đối chiếu DB vận hành và thay đổi song song

Trong phiên này chỉ chạy các truy vấn kiểm tra chỉ đọc trên `sale_report`; các migration/import chạy trên `sale_report_mvp_test` và `sale_report_mvp_test_restore`.

- Checksum contact, reports, users, incident, staff_asst, trackings và user_history khớp mốc đầu.
- Bảng roles đổi checksum từ 3625884759 sang 1675026945; người dùng xác nhận đang cấp quyền/thử giao diện song song. Giữ nguyên thay đổi này, không hoàn nguyên.
- Chỉnh sửa errorAction ở config/main.php cũng là thay đổi của người dùng trong lúc thử, không thuộc triển khai WP03 và được giữ nguyên.
- Schema vận hành vẫn có 132 cột và 0 bảng crm_*. Không có journal/schema/migration WP03 được tạo ở vận hành.

## 8. Chạy lại kiểm thử và bước tiếp theo

```sh
php source/protected/tests/mvp-import-smoke.php
php source/protected/tests/mvp-import-durability.php
php source/protected/tests/mvp-schema-smoke.php
php source/protected/tests/mvp-policy-smoke.php
php source/protected/tests/models-smoke.php
php source/protected/tests/management-smoke.php
php source/protected/tests/access-smoke.php
```

DB test mới cần fixture/schema WP00–WP02 và migration WP03 trước khi chạy. Giao diện cần nạp cấu hình test/staging có connection test; không đổi DB hoặc mở cờ import trên cấu hình main để thử trực tiếp dữ liệu vận hành.

Trước khi import bản sao dữ liệu thật cần duyệt danh mục Hotel, alias, owner/grant và danh sách xử lý trùng. Các test tự tạo Hotel/owner giả rồi dọn; chúng không tự seed danh mục khách sạn vận hành. WP04 tiếp tục xây màn hình hồ sơ và bàn giao, thay vì dùng importer để sửa hồ sơ đã có.
