# Kết quả kiểm thử

## Phiên 24/10/2026 01:14 UTC — xác minh trên MySQL server đích

Môi trường: PHP 8.5.4; MySQL 8.4.11 tại `127.0.0.1:3307`; UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23`; ứng dụng dùng `volunteer_app`, kiểm thử dùng schema/user `volunteer_test` riêng.

| Lệnh/nhóm | Kết quả thực tế | Mã thoát |
| --- | --- | --- |
| `bash lab.sh db-info` | `volunteer_app@localhost`, schema `volunteer_app`, đúng cổng/UUID | 0 |
| `bash lab.sh check` | PHP, extension, migration, thuật toán, fixture, MySQL và quyền tối thiểu đều đạt | 0 |
| `bash lab.sh test-prepare` | Áp dụng migration 001–005 và seed trên `volunteer_test` | 0 |
| `bash lab.sh extend` | 21 unit + 3 fixture + 13 integration + event/shift + I04 + I05 + workflow mở rộng đạt | 0 |
| `bash lab.sh http-test` | 45 đạt, 0 lỗi; dữ liệu HTTP tạm tự dọn | 0 |

Không có test ghi dữ liệu nào chạy trên `volunteer_app`.

## Phiên 24/10/2026 00:58 UTC — rà soát bàn giao

- `php app/tests/run.php`: 21 đạt, 0 lỗi; mã thoát 0.
- `php app/tools/validate_fixtures.php`: cả ba fixture `DU_NGUOI_V1`, `THIEU_NGUOI_V1`, `TRUNG_LICH_V1` đạt và giữ nguyên SHA-256; mã thoát 0.
- `php app/tools/db_info.php` ngoài sandbox: kết nối đúng MySQL 8.4.11, cổng 3307, schema `volunteer_app`, UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23`.
- `php app/tools/check.php`: phần mã/migration/thuật toán/fixture và kết nối đạt, nhưng tổng thể trả mã 1 đúng thiết kế vì tài khoản web đang có DDL/ALL.
- Chưa chạy integration/HTTP trên server 3307 vì `volunteer_test` chưa tồn tại và tài khoản hiện tại không có quyền tạo schema; không chạy test ghi dữ liệu trên `volunteer_app`.
- Gói `release/volunteerhub-0.4.0-demo.tar.gz` tạo từ commit `7bc0b1f`: checksum `2afcef9499233b9cc03fcec33b4333250374cfdea71fa5da0f128126fdf0ce69`; kiểm tra cấu trúc đạt, bản giải nén chạy 21 unit và 3 fixture đạt.

## Phiên 23/10/2026 18:45 UTC — Open Sans cục bộ

- Hai variable TTF tải từ nguồn chính thức được `file` nhận đúng là TrueType có chữ ký và metadata Open Sans; bản normal 532.636 byte, italic 583.992 byte.
- Server demo trả HTTP 200, `Content-Type: font/ttf` và đúng `Content-Length` cho cả hai tệp. CSS đang chạy chứa hai `@font-face`; không còn khai báo Inter hoặc Georgia trong giao diện.
- Giấy phép OFL được giữ tại `app/public/assets/fonts/open-sans/OFL.txt` và nguồn được ghi trong `THIRD_PARTY_NOTICES.md`/`SOURCE_PROVENANCE.md`.

## Phiên 23/10/2026 18:38 UTC — đăng ký tài khoản và giao diện theo vai trò

Môi trường test: PHP 8.5.4; MySQL 8.4.11 tạm tại `127.0.0.1:33307`; schema guard `volunteer_test`. Migration 005 cũng đã được áp dụng cộng dồn lên `volunteer_app` sau khi xác nhận không có email trùng; không reset dữ liệu.

| Lệnh/nhóm | Kết quả thực tế | Mã thoát |
| --- | --- | --- |
| PHP lint toàn bộ `app/**/*.php`, Bash lint, `git diff --check` | Sạch; máy không cài Node nên không chạy `node --check` | 0 cho các kiểm tra đã chạy |
| `php app/tests/run.php` | 21 đạt, 0 lỗi | 0 |
| `bash app/lab.sh test-prepare` | Áp dụng migration 001–005 và seed trên schema sạch | 0 |
| `bash app/lab.sh extend` | 21 unit + 3 fixture + 13 integration + event/shift + I04 + I05 + workflow mở rộng đạt | 0 |
| `bash app/lab.sh http-test` | 45 đạt, 0 lỗi; dữ liệu HTTP tự dọn | 0 |

HTTP mới xác nhận: validation đăng ký; role luôn là volunteer; mật khẩu `password_hash`/`password_verify`; sinh hồ sơ/mã TNV; email trùng bị unique chặn; menu khách/volunteer/coordinator đúng phạm vi; assignments/stats vẫn trả 403; hồ sơ điều phối nằm trong `dialog` không có form lồng; footer lấy đúng phiên bản.

## Phiên 23/10/2026 17:22 UTC — workflow vận hành và kiểm thử bổ sung 0.4.0-demo

Môi trường test: PHP 8.5.4; MySQL 8.4.11 tạm tại `127.0.0.1:33307`; schema có guard `volunteer_test`. Không chạy test ghi dữ liệu trên `volunteer_app`.

| Lệnh/nhóm | Kết quả thực tế | Mã thoát |
| --- | --- | --- |
| PHP lint toàn bộ `app/**/*.php`, `bash -n app/lab.sh`, `git diff --check` | Sạch | 0 |
| `php app/tests/run.php` | 21 đạt, 0 lỗi | 0 |
| `php app/tools/validate_fixtures.php` | 3 fixture đạt | 0 |
| Migration/seed schema sạch | Áp dụng 001–004; seed thành công | 0 |
| `bash app/lab.sh extend` | 21 unit + 3 fixture + 13 integration + event/shift + I04 + I05 + workflow mở rộng đạt | 0 |
| `bash app/lab.sh http-test` | 39 đạt, 0 lỗi; dữ liệu HTTP tự dọn | 0 |
| `php app/tools/check.php` với cấu hình DB thật | Kiểm tra mã đạt; kết nối MySQL thật thất bại trong phiên IDE | 1, chưa đạt môi trường |

Workflow mới kiểm chứng: rollback chuyển người không hợp lệ; chặn phân lại người đã vắng; `unfilled`/mở lại/kết thúc lại và chặn mở sau khi đóng; cặp baseline/cải tiến chung dataset/group không đổi lịch công bố; lưu phiên bản mã; báo cáo từng ca; lịch sử và audit. HTTP đi thật qua lưu hồ sơ/kỹ năng/lịch rảnh, role của action mới, A/B, chuyển người, unfilled/mở lại, CSV mở rộng và kho hoạt động.

## Phiên 23/10/2026 16:41 UTC — tám cải tiến

Môi trường test: PHP 8.5.4 với `mysqli`/`mysqlnd`; MySQL 8.4.11 cô lập tại `127.0.0.1:33307`; schema bắt buộc `volunteer_test`. Cấu hình tạm và data directory nằm trong `app/.local/`, không được theo dõi/đóng gói và được dọn sau khi kiểm tra.

| Lệnh/nhóm | Kết quả thực tế | Mã thoát |
| --- | --- | --- |
| PHP lint toàn bộ `app/src`, `public`, `tools`, `tests` | Không có lỗi cú pháp | 0 |
| `bash lab.sh test-prepare` chạy hai lần | 001–003 có checksum hợp lệ; seed idempotent | 0 |
| `bash lab.sh extend` | 20 unit + 3 fixture + 11 integration + quản lý event/shift + I04 + I05 đạt | 0 |
| `bash lab.sh http-test` | 28 đạt, 0 lỗi; server test riêng 18106; dữ liệu tạm tự dọn | 0 |
| `bash lab.sh integration` khi thiếu `test-db.json` | Từ chối chạy, không rơi về DB ứng dụng | 1 mong đợi |
| `bash lab.sh check` trên MySQL thật 3307 | Mã/PHP/migration/server/schema đạt; từ chối tài khoản web có DDL/ALL | 1 mong đợi |
| Cài gói `volunteerhub-0.3.0-demo.tar.gz` vào schema sạch `volunteer_release_test` | Migration 001–003 + seed + `extend` + HTTP 28/28 đều đạt | 0 |

Các kiểm thử mới bao phủ: sửa/đóng hoạt động; sửa/xóa ca an toàn; chặn thao tác khi có phân công; audit/revision; khóa đăng nhập sau 5 lần sai; tài khoản bị vô hiệu hóa làm session cũ mất hiệu lực; security header/môi trường; không còn form HTML lồng nhau; test DB fail-closed.

Gói release candidate được tạo từ commit `4a1fe90`, giải nén tại thư mục tạm, cấu hình bằng schema sạch trên MySQL cô lập rồi kiểm tra từ chính cây đã giải nén. Bản đóng gói cuối có checksum đi kèm tại `release/volunteerhub-0.3.0-demo.tar.gz.sha256`. Schema, thư mục và cấu hình test tạm được dọn sau khi hoàn tất.

Kết quả `check` trên server thật không được ghi là PASS: cần đổi `app/.local/db.json` sang `volunteer_app@localhost` chỉ có `SELECT, INSERT, UPDATE, DELETE`.

## Phiên 23/10/2026 UTC — hoàn thiện G00–G10

Môi trường thực: PHP 8.5.4 với `mysqli`/`mysqlnd`; MySQL 8.4.11; host `Lynn`; port `3307`; schema `volunteer_app`; server UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23`.

| Lệnh/nhóm | Kết quả thực tế | Mã thoát |
| --- | --- | --- |
| PHP lint toàn bộ `app/src`, `public`, `tools`, `tests` | Không có lỗi cú pháp | 0 |
| `bash lab.sh migrate` | 001 đã có; áp dụng 002; chạy lại không nhân migration | 0 |
| `bash lab.sh seed` | 30 tình nguyện viên, hồ sơ/kỹ năng/lịch rảnh và 3 hoạt động mẫu; không ghi đè mật khẩu | 0 |
| `bash lab.sh check` | PHP/extension/cấu trúc/MySQL/schema/UUID đạt | 0 |
| `bash lab.sh test` | 20 đạt, 0 lỗi | 0 |
| `bash lab.sh fixtures` | 3/3 fixture đúng 30/12/5 và tính chất expected | 0 |
| `bash lab.sh integration` | 9 đạt, 0 lỗi | 0 |
| `bash lab.sh stale` | I04: phương án stale bị từ chối, lịch không đổi | 0 |
| `bash lab.sh concurrency` | I05: 2 tiến trình tranh 1 chỗ, đúng 1 thành công | 0 |
| `bash lab.sh extend` | 20 unit + 3 fixture + 9 integration + I04 + I05 đạt | 0 |
| `bash lab.sh http-test http://127.0.0.1:8106` | 27 đạt, 0 lỗi; dữ liệu tạm tự dọn | 0 |
| `bash lab.sh compare` | 6 kết quả, cả hai thuật toán × 3 snapshot | 0 |

HTTP đã đi qua: login hai vai trò; tạo hoạt động/ca; dữ liệu sai; đăng ký/trùng/rút; hồ sơ; chặn role; sinh/công bố; xác nhận; chặn rút khi có ca; tự báo vắng; thay người; phân công thủ công; chặn người thứ ba vào ca cần hai; CSV; CSRF; kiểm tra persistence qua nhiều lượt tải.

## Kết quả thí nghiệm

| Fixture | SHA-256 | Baseline: đủ ca / vi phạm / chênh tải | Cải tiến: đủ ca / vi phạm / chênh tải |
| --- | --- | --- | --- |
| `DU_NGUOI_V1` | `2971c5a0002f4c757d65b60243bd60ed0ee6bbb742e520577743ae3ebabf832c` | 100% / 0 / 12 | 100% / 0 / 1 |
| `THIEU_NGUOI_V1` | `d3c3faa267ad613b7ffd866d3ca2f8d09a013e2ee11b0fc0c32ee7f69ca1ea82` | 91.67% / 0 / 12 | 91.67% / 0 / 1 |
| `TRUNG_LICH_V1` | `f2daf31e5dae63cbbe161e62c78c447f09c2bb7b9dd288fc3dd5b55292514462` | 100% / 0 / 6 | 100% / 0 / 1 |

Tỷ lệ lấp vị trí tương ứng: 100%, 94.44%, 100% ở cả hai thuật toán. Bộ thiếu người giữ đúng 30 người và ca 12 thiếu tối thiểu 2 người. Chi tiết JSON ở `artifacts/comparison/`.

## Chưa được ghi là PASS

- Chưa chạy bộ test ghi dữ liệu trên `volunteer_test` của MySQL server thật cổng 3307 vì tài khoản hiện tại chưa có quyền tạo schema đó; gói release đã được cài sạch và kiểm chứng trên MySQL cô lập cùng phiên bản 8.4.11.
- Chưa đối chiếu trực tiếp trong giao diện Workbench; chỉ đã có UUID/port/schema phía PHP để người dùng so sánh.
- Tài khoản DB của web vẫn là migrator; xem `KNOWN_ISSUES.md`.
