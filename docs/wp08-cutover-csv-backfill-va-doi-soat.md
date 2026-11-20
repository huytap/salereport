# WP08 — Cutover, CSV hồ sơ và lịch cũ đã duyệt

Triển khai ngày 20/11/2026, sau WP07. Phạm vi hiện tại vẫn là DB test có dữ liệu giả. Chưa migrate DB vận hành, chưa bật cờ vận hành, chưa cài/bật cron. WP09 (UAT, tải dữ liệu thực tế, pilot và vận hành production) chưa thực hiện trong WP08.

## 1. Chốt chuyển đổi và rollback

Migration `m261120_090000_prepare_customer_cutover` thêm:

| Bảng | Mục đích |
|---|---|
| `crm_cutover` | Một chốt toàn bộ tập dữ liệu, trạng thái, latch canonical, version, người/lý do/thời điểm đổi |
| `crm_transfer_batch` | Batch CSV/lịch cũ, nguồn SHA-256, Hotel, người tạo/duyệt, digest phê duyệt |
| `crm_transfer_row` | Payload preview giữ nguyên, quyết định từng dòng, version, kết quả/lỗi |
| `crm_cutover_action_seed` | Journal quyền trước/sau để rollback không ghi đè quyền chỉnh song song |

Các bảng dùng InnoDB. Mặc định `preparing`, latch = 0. Không thay đổi bảng dữ liệu nguồn trong migration này. Chỉ seed action `customermove` cho các role All được cấu hình; các role Hotel/Own cần cấp action rõ ràng qua màn hình phân quyền.

| Chuyển trạng thái | Điều kiện và kết quả |
|---|---|
| preparing → frozen | All + action cutover + version/lý do; dừng Contact/Report cũ, ghi hồ sơ/task/report thông thường và dispatch |
| frozen → preparing | Chỉ trước latch và trước phát sinh request MVP/task; có phát sinh thì từ chối |
| frozen → canonical | Ba cờ ghi cùng bật trong process test; không còn nguồn chưa mapping/chưa có quyết định cách ly đã đối chiếu hash, báo cáo thiếu chứng cứ link/phân công, dòng chưa xử lý, owner thiếu/sai, lịch chưa duyệt, worker đang chạy hoặc drift nguồn/đích |
| canonical → closed | Đóng ghi và dispatch, giữ xem hồ sơ, inbox và đối soát |
| frozen → closed | Chọn giữ đóng ghi khi không thể trở lại luồng cũ |
| closed → canonical | Kiểm tra lại toàn bộ readiness, quay lại duy nhất luồng canonical |

Latch được giữ vĩnh viễn sau canonical. Tắt `customerWrite`/`reportWrite` không mở lại ghi Contact/Report cũ. Không có endpoint reset latch, xóa dữ liệu chuyển đổi hoặc tự khôi phục snapshot. Sau phát sinh mới, rollback nghiệp vụ là **đóng ghi và giữ dữ liệu**, không hứa khôi phục snapshot mà không mất phát sinh. Muốn phục hồi snapshot thật vẫn cần xuất/đối soát đầy đủ phát sinh, phương án nhập lại và người phụ trách dữ liệu duyệt như kế hoạch gốc.

`CutoverService` dùng cùng named mutex với import/ghi hồ sơ. Đường ghi legacy giữ mutex tới hết request. Chốt được đọc bằng current locking read, tránh snapshot cũ. Worker giữ shared lock trên chốt xuyên suốt giao dịch Task → Reminder → Notification; freeze phải chờ giao dịch đang gửi kết thúc. Hai worker vẫn chạy song song. Nếu freeze xuất hiện giữa batch dispatch, worker dừng lượt tiếp theo với `CUTOVER_PAUSED`, không đánh lỗi lên reminder chưa xử lý. Dry-run vẫn đọc được khi frozen/closed.

Trong cửa sổ frozen, chỉ All có action commit được thực thi batch đã duyệt qua maintenance context nội bộ; đây không phải endpoint mở ghi tự do. Closed chặn cả ghi hồ sơ/task qua maintenance.

## 2. CSV chuẩn

Màn hình `customermove/admin`: upload CSV, xuất theo Hotel/owner, danh sách batch được phép thấy. `customermove/view?id=…`: payload, quyết định từng dòng, duyệt, commit tối đa 50 dòng/lần và đối soát. POST có CSRF; action và Hotel được đọc lại khi sử dụng, không tin quyền lưu trong batch.

Xuất cần đồng thời `customermove/export` và `account/report`. Mỗi dòng là Account + một Contact đang hoạt động tại Hotel đã chọn; hồ sơ không có Contact vẫn có một dòng. Không xuất raw legacy snapshot. Có `account_id`, `account_code`, `contact_id`, `hotel_id`, `owner_user_id`, các trường `account_*`, `contact_*` và `account_markets` là JSON array. Chỉ xuất Account/Contact/phân công đang hoạt động, theo Own/Hotel/All và owner filter; đây không phải bản backup toàn bộ lịch sử. Query phân trang keyset 200 dòng, kiểm tra lại quyền từng trang. Các ô có thể thành công thức bảng tính được quote-prefix; apostrophe được escape đối xứng để xuất/đọc lại không mất ký tự.

CSV vào phải UTF-8 (chấp nhận BOM), tối đa 2 MB/500 dòng. Header phải duy nhất, thuộc schema chuẩn, có `account_name`. Có thể dùng file xuất làm mẫu hoặc file tối thiểu:

```csv
account_name,account_phone,contact_first_name,contact_email,account_markets
Công ty minh họa,+84900000000,Lan,lan@example.test,"[""VN""]"
```

Import cần Hotel hoặc All cùng action tương ứng. Own không được bulk import. ID trong file chỉ là gợi ý; ID ngoài Hotel/phạm vi bị từ chối trước khi lưu preview. Cùng bytes file + người tạo + Hotel trả lại batch cũ, không tự nhân bản.

Mapping có hai tầng:

- Account: create, link (ID + version hiện tại), hoặc skip có lý do.
- Contact: none, create hoặc link (đúng Account, ID + version hiện tại).

Create gọi `AccountService`; thêm Contact gọi `AccountService::person`, giữ quyền action, owner, validation, chống trùng và audit đang có. Link không ghi đè trường Account/Contact hoặc phân công từ nội dung CSV. Cần sửa thông tin có sẵn thì đối chiếu và dùng màn hình hồ sơ. Khi tạo mới, owner phải được người duyệt chỉ định, không lấy từ cột gợi ý. Tên trùng không tự gộp: ghi các dòng hợp lệ, giữ dòng lỗi, sửa mapping sang Account vừa tạo rồi duyệt lại. CSV nhiều người cùng công ty có thể làm theo quy trình này; chưa có cú pháp tham chiếu một dòng chưa được commit trong cùng file.

Mỗi dòng commit là một giao dịch riêng, kết quả và nhật ký cùng giao dịch với dữ liệu. Lỗi ở Contact rollback cả Account/phân công/request của dòng đó. Các dòng đã thành công được giữ; chạy lại bỏ qua chúng. Sửa mapping hủy phê duyệt cũ. Approval digest, version Account/Contact, scope và nguồn được kiểm tra lại trước ghi. Không retry mù lỗi quyền/gate; lỗi dòng hiển thị mã, thông báo an toàn và lỗi trường nếu có.

## 3. Backfill lịch cũ

Chỉ All được xem/duyệt nguồn lịch. Preview 1–150 Report nguồn cũ mỗi batch, tách ba dòng `ent`, `sales`, `site_ins`. Lưu ngày thô, tính hợp lệ/tương lai, Account/Hotel/Contact từ mapping WP03, fingerprint và khóa `report:{id}:field:{field}`. Không dùng `done` để suy ra trạng thái.

Tạo Task chỉ khi:

1. Report đã mapping đúng Account/Hotel, nguồn/fingerprint còn nguyên.
2. Ngày hợp lệ và **sau ngày hiện tại tại Asia/Ho_Chi_Minh** cả lúc duyệt và lúc commit. Lịch hôm nay/quá khứ/zero/empty cần rà soát, không tự sinh việc; có thể skip có lý do và tạo việc mới thủ công sau khi trao đổi khách.
3. Quản lý xác nhận còn hiệu lực, chỉ rõ owner hiện tại và version phân công. Không suy đoán owner từ `added_by`.
4. Có đủ action tạo Task/xem Report và các kiểm tra service Task hiện tại.

Do nguồn chỉ có ngày, Task là `kind=task`, `due_mode=date`, action tương ứng entertainment/visit/site_inspection. Không bịa giờ hẹn. Task giữ `report_id`, `contact_person_id`, legacy key duy nhất và audit source hash/lý do. Tạo lịch reminder pending bằng planner; không dispatch notification trong import.

Khóa nguồn và Account/Hotel/phân công được đọc lại khi ghi. Hai batch duyệt cùng legacy key chỉ link Task đã có khi chứng cứ nguồn/hash và kết quả batch trước khớp; nguồn đổi hoặc Task không có chứng cứ thì yêu cầu rà soát, không sửa ngầm lịch Task hiện hành. Lịch nguồn chưa mapping phải hoàn thành WP03 rồi preview lại. Preview trước mapping hoặc nguồn đã đổi có thể đóng bằng skip có lý do; payload cũ vẫn giữ và reconcile trả drift ở warnings cho dòng skipped. Những dòng này chỉ được tính là đã rà soát lịch hiện tại nếu cả fingerprint lẫn Account/Hotel/Contact còn khớp; nếu khác phải preview/duyệt lại nguồn hiện tại. Drift ở dòng đã tạo/link Task vẫn là lỗi chặn readiness. Sau canonical, đóng backfill nguồn cũ.

## 4. Route cũ và đối soát cuối

- Contact admin chuyển sang danh sách Account khi frozen/canonical/closed. Contact view đã mapping chuyển sang đúng Account, qua policy của cả nguồn và đích; ngoài phạm vi trả 404. Nguồn chưa mapping báo 410 và quản trị xem archive ở WP03.
- Contact create/update/delete/import/export cũ bị chặn; direct CSV entry point vẫn qua guard chung. Xuất mới dùng `customermove/export?hotel_id=…`.
- Sales Report dùng màn hình/service thống nhất WP06; legacy import/delete/create/update bị guard chung chặn ngay cả khi cờ ghi bị tắt sau latch.
- Reconcile trả tổng, số created/linked/skipped/error/needs_review và lỗi source drift, thiếu Account/Contact hoặc Task sai nguồn/Account/Hotel/key. Không kiểm tra ngày hiện tại của Task bằng ngày nguồn sau khi sales đã đổi hạn hợp lệ; chứng cứ nhập ban đầu được giữ ở payload/audit.
- Nguồn Contact/Report có quyết định skip đã commit ở WP03 được giữ nguyên trong archive, đếm riêng quarantined; chỉ công nhận cách ly khi fingerprint còn khớp. Báo cáo cách ly vẫn cần rà soát riêng các trường lịch, không tự tạo việc. Readiness kiểm tra toàn bộ nguồn, không chỉ một Hotel: tránh hai luồng ghi cho Account chia sẻ. Kết quả readiness tại activation được lưu kèm audit transition. Không tự bật bất kỳ cờ cấu hình hay cron nào.

## 5. CLI test và thứ tự diễn tập

CLI vẫn dùng `IsolatedTestDbConnection`; chỉ cho tên `sale_report_mvp_test…`, xác minh đúng database đang kết nối. Ví dụ từ thư mục repository:

```sh
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_cutover' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php customermove check --actor=1
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_cutover' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php customermove schedules --ids=1,2 --actor=1
SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_cutover' /opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php customermove commit --id=123 --actor=1 --enableTest=1
```

`check` là read-only; CLI mặc định ba cờ ghi tắt nên sẽ báo các `FLAG_OFF_*`, không đồng nghĩa dữ liệu sai. `--enableTest=1` của commit/transition chỉ bật ba cờ ghi trong process test, không bật reminder hay sửa file cấu hình. Giao diện test vào `source/index-test.php?r=customermove/admin` theo cách chạy web test hiện có; không dùng entry point vận hành để thử ghi. Mapping/duyệt dùng màn hình chung; CLI có thêm `csv --file=… --hotel=…`, `reconcile` và `transition --target=… --version=… --reason=… --actor=…`.

Diễn tập: chuẩn bị fixture/migrate → WP03 preview/map/duyệt → freeze → chụp nguồn cuối và kiểm tra drift → backfill lịch đã duyệt/đối soát → readiness → canonical đồng bộ ba luồng ghi → giả lập lỗi → closed giữ đọc/lịch sử → sửa và readiness lại → canonical. Chỉ worker test được bật tạm trong process kiểm thử; không có cron. Trước phát sinh MVP đã diễn tập frozen → preparing thành công; sau phát sinh đã xác nhận bị từ chối. Down migration từ chối khi có batch, latch, audit cutover hoặc quyền seed đã bị sửa.

## 6. Kết quả kiểm thử

DB mới, schema-only từ dump, chỉ fixture giả:

- `sale_report_mvp_test_cutover`: smoke/durability WP08 và hồi quy WP01–06.
- `sale_report_mvp_test_cutover_restore`: migration WP08 up/down/up.
- `sale_report_mvp_test_reminder_cutover`: hồi quy inbox/worker WP07 dưới schema WP08.

| Bộ kiểm tra | Assertions đạt |
|---|---:|
| WP08 smoke: CSV, quyền, mapping WP03→WP08, lịch, route cũ, gate/rollback, drift | 83 |
| WP08 durability: rollback lỗi con, resume, hai process thật, mutex, snapshot cũ, freeze chờ giao dịch và chặn worker | 29 |
| WP08 migration: up/down/up, nguồn/schema/rights nguyên vẹn, guard lịch sử | 24 |
| Hồi quy schema/policy/import/customer/task/report | 96 / 94 / 116 / 130 / 111 / 86 |
| Hồi quy reminder smoke/durability/client | 99 / 36 / 13 |

Tổng WP08: **136 assertions**; hồi quy liên quan: **781 assertions**. PHP lint 25 file mới/thay đổi đạt; diff whitespace đạt (giữ CRLF của config cũ). Kiểm thử có tạo thông báo giả trong DB reminder riêng rồi dọn theo ID fixture; không để worker nền chạy. Fixture sau chạy đã được rollback/dọn; gate test về preparing, không còn Account/Task/Notification/dispatch run thử nghiệm.

Checksum DB vận hành được đọc trước/sau để đối chiếu: Contact `1859318667`, Reports `1319172983`, Users `1097044357`, Roles `1675026945`, Incident `0`, Staff assistance `0`, Trackings `3337141388`, User history `2096163467`; chưa có bảng `crm_*` ở `sale_report`. Không sửa `config/main.php`, entry point vận hành, dữ liệu/quyền người dùng đang thử song song hoặc các cờ mặc định `mvp.php`.

Các kết quả này là kiểm thử fixture và diễn tập chuyển đổi. Chưa phải UAT trên dữ liệu thật, thử tải hay phê duyệt cutover production; các việc đó thuộc WP09.
