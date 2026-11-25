# Pilot staging — nghiệm thu nghiệp vụ

Chuẩn bị ngày 25/11/2026. Môi trường local riêng, không phải pilot production. DB `sale_report_mvp_test_staging_pilot`, migration WP09. Không chạy bộ nghiệm thu tự động trên DB này vì cần giữ kết quả thao tác của người dùng.

## Truy cập

URL: http://127.0.0.1:8098/site/login (chỉ truy cập trên máy đang chạy server).

| Tài khoản | Vai trò/phạm vi |
|---|---|
| `fixture_admin` | Quản trị All; mật khẩu `synthetic-test-only` |
| `staging_pilot_manager` | Quản lý Hotel H1; phân công, bàn giao, duyệt import |
| `staging_pilot_sales_a` | Own H1; đang phụ trách hồ sơ Sen |
| `staging_pilot_sales_b` | Own H1; nhận bàn giao từ A |
| `staging_pilot_outside` | Own H2; thử chặn truy cập H1 |
| `staging_pilot_reader` | Hotel H1; chỉ xem |

Năm tài khoản staging dùng mật khẩu test `Staging-pilot-only-2026!`. Đây chỉ là tài khoản giả trong DB local, không dùng cho dữ liệu thật hoặc mở server ra mạng. Không bật lưu đăng nhập; cookie phiên và CSRF tách riêng. Entry point vận hành giữ nguyên.

Dữ liệu đầu vào: H1 #1/H2 #2; Account #1 “STAGING — Công ty Sen giả” thuộc A #3, hai Contact Lan/Minh dùng `shared@example.test`, Task #1 “STAGING — Gọi khách giả” có hạn tương đối ngày seed +2 ngày. Contact nguồn #2 trùng tên hồ sơ để review; Contact #1 và Report nguồn #1 là fixture giả chưa chuyển đổi. Ngày task không tự chạy theo thời gian: khi nghiệm thu muộn, đổi hạn bằng UI và ghi lý do.

Cập nhật kiểm thử 25/11/2026: đã thực hiện chín luồng trên staging bằng các vai trò; [kết quả và ảnh](staging-uat-ket-qua.md). Riêng replay POST mở lại được kiểm tra bổ sung bằng controller, chưa chứng minh bằng trình duyệt. Các ô ký nghiệp vụ vẫn chờ, không điền thay người duyệt. Dữ liệu sau thử: Account #2 do B phụ trách, Task #2 completed/#3 cancelled/#4 open; seed Account #1 giữ nguyên.

## Checklist người nghiệp vụ

Điền kết quả Đạt/Không đạt, người thử, thời điểm và link ảnh cho từng dòng; chưa có dòng nào được ký thay người dùng. Dùng tên dữ liệu thử riêng theo người để tránh ghi đè lẫn nhau.

| Mã | Vai trò và thao tác | Kết quả mong đợi | Kết quả/người thử/thời điểm/ảnh |
|---|---|---|---|
| UAT01 | Manager tạo hồ sơ mới, thêm hai liên hệ cùng email, giao A tại H1 | Lưu đúng hồ sơ/liên hệ/Hotel/owner | Chờ |
| UAT02 | A tạo Report với ba task: gọi điện, báo giá, hẹn khảo sát; thử một dòng sai trước | Lỗi giữ form, không lưu nửa giao dịch; sửa xong lưu đủ ba task | Chờ |
| UAT03 | A mở danh sách/lịch tuần, đối chiếu ngày và giờ Asia/Ho_Chi_Minh | Cuộc hẹn và việc theo ngày ở đúng làn; nhắc tới đúng A khi chạy worker thủ công | Chờ |
| UAT04 | A hoàn thành/hủy/đổi hạn, nhập lý do | Trạng thái/audit đúng, Report gốc không đổi | Chờ |
| UAT05 | Hai cửa sổ sửa cùng task; manager mở lại task đóng và gửi lại cùng yêu cầu | Cửa sổ cũ bị 409, giữ dữ liệu nhập; replay mở lại không tăng version/audit/reminder | Chờ |
| UAT06 | Manager preview rồi bàn giao A→B | Chỉ việc mở chuyển B, việc đóng giữ lịch sử; A mất Own, B thấy hồ sơ | Chờ |
| UAT07 | Outside mở URL Account/Task H1, lookup/export; reader thử đường ghi | Không lộ dữ liệu ngoài quyền; reader không sửa được | Chờ |
| UAT08 | Admin preview nguồn Contact/Report, review mapping, duyệt, nhập, đối soát, nhập lại; thử skip nguồn trùng | Không tự gộp, không nhân bản; skip vẫn giữ snapshot; đối soát cân bằng | Chờ |
| UAT09 | Không chạy worker, kiểm tra task; người vận hành chạy dispatch test hai lần | Task vẫn thấy khi worker tắt; một nhắc đúng người/generation, lần hai không trùng; đánh dấu đọc đúng | Chờ |

UAT05 replay nên giữ nguyên request key/payload khi thử lại; không tạo yêu cầu mới để giả lập retry. Có hồi quy controller tự động riêng cho ca này; người nghiệp vụ có thể nhờ kỹ thuật hỗ trợ gửi lại request.

Ghi lỗi: mã UAT, tài khoản, Hotel, ID bản ghi, bước tái hiện, kết quả mong đợi/thực tế, thời điểm và ảnh. Khi có lỗi lộ quyền, mất dữ liệu, giao dịch nửa chừng hoặc nhắc sai người: dừng nghiệm thu ca liên quan và giữ evidence, không tự reset DB.

## Vận hành local

Từ thư mục repo, mở server bằng:

```sh
SALEREPORT_STAGING_PILOT=1 SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_staging_pilot' /opt/homebrew/opt/php@7.4/bin/php -S 127.0.0.1:8098 -t source source/protected/tests/support/staging-router.php
```

Dừng bằng Ctrl-C tại terminal chạy server. Không bind 0.0.0.0. Nếu cần staging cho nhiều máy/người dùng đồng thời, cần cấu hình máy chủ riêng trước; URL local hiện tại không chia sẻ được qua mạng.

Worker mặc định không chạy. Khi tới UAT03/UAT09, người vận hành đặt lịch đủ gần (ví dụ task hẹn sau 20 phút), kiểm tra dry-run rồi chạy một lần trên đúng DB:

```sh
export SALEREPORT_TEST_DSN='mysql:host=127.0.0.1;dbname=sale_report_mvp_test_staging_pilot'
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php reminder dispatch --dryRun=1
/opt/homebrew/opt/php@7.4/bin/php source/protected/yiic.php reminder dispatch --enableTest=1
```

Chưa thực hiện dispatch trong lúc chuẩn bị; không cài/bật cron. Cờ reminder chỉ bật cho process test ở lệnh trên. Cảnh báo worker tắt/chưa chạy là trạng thái mong đợi trước ca reminder.

Seed `yiic stagingpilot seed` chỉ chấp nhận schema staging đúng tên và fixture marker, từ chối khi đã có Account/user staging. Không chạy lại prepare/migrate/seed để reset kết quả nghiệm thu. Backup và thay đổi cutover/đóng ghi chỉ diễn tập sau khi thống nhất, không coi checklist này là lệnh mở production.

## Ký xác nhận

Chủ nghiệp vụ: …; quản lý Hotel: …; người vận hành: …; ngày nghiệm thu: …; phiên bản: …; kết luận/phạm vi chấp nhận: …; lỗi tồn đọng: … . Hiện tất cả chờ xác nhận. Không sửa DB vận hành, không bật cron hoặc mở pilot production trong bước chuẩn bị này.
