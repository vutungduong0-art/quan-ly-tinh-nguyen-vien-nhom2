# Nhật ký phiên

## 2026-10-23 — Triển khai demo tiến độ ban đầu

- Đọc yêu cầu mới nhất, toàn bộ kế hoạch đã cung cấp và `AGENT.md` (workspace không có `AGENTS.md`; đã thêm tệp điều hướng đúng tên).
- Khảo sát: workspace không phải Git repository hợp lệ; chỉ có tài liệu. PHP 8.5.4, mysqli/mysqlnd và MySQL client/server 8.4.11 đã cài. MySQL hệ thống chưa chạy; `sudo service mysql start` yêu cầu mật khẩu nên không nhập thay người dùng.
- Tạo cấu trúc `app/` theo kế hoạch, front controller, giao diện xanh responsive, migration, seed, công cụ cấu hình, kiểm tra môi trường và thông tin server.
- Hoàn thiện quản lý hoạt động, tạo ca/kỹ năng và đăng ký; thêm login, role server-side, session regeneration, CSRF, parameterized query, escaping và PRG.
- Dùng MySQL tạm/schema riêng để kiểm thử, không reset/xóa dữ liệu hiện có. Kết quả: unit 8/8, integration 6/6, HTTP 11/11, persistence sau restart đạt.
- Kiểm tra chạy lại: migration lần hai nhận đúng phiên bản đã áp dụng; seed lần hai không nhân bản (giữ 4 user/3 hoạt động/8 ca/5 kỹ năng/2 đăng ký).
- Đã dừng PHP/MySQL test và xóa `/tmp/volunteer-mysql.EyOEHV` cùng config test chứa mật khẩu thử. Không xóa dữ liệu người dùng.
- Bàn giao README, hướng dẫn Workbench, demo script, yêu cầu, quyết định, nguồn, lỗi còn lại và trạng thái.
- Viết lại README theo quá trình thiết lập thực tế: port 3307, phân biệt mật khẩu Ubuntu/MySQL/website, tài khoản `@localhost`, kiểm tra sau từng bước và hướng xử lý lỗi 1698/1045/1049/quyền database.

## 2026-10-23 — Hoàn thiện các phần còn lại G02–G10

- Đọc lại toàn bộ `AGENT.md`, kế hoạch 821 dòng và trạng thái; xác nhận workspace vẫn không có Git hợp lệ.
- Kết nối MySQL thật cổng 3307/schema `volunteer_app`; sửa alias `CURRENT_USER()` trong `db-info`; xác minh UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23` và MySQL 8.4.11.
- Thêm migration 002 gồm hồ sơ/kỹ năng/lịch rảnh, lịch sử đăng ký, schedule guard, snapshot/run, phân công, thay người và audit; áp dụng cộng dồn, không reset dữ liệu.
- Seed lên 30 tài khoản tình nguyện viên, 5 kỹ năng và lịch rảnh; giữ ba hoạt động mẫu.
- Hoàn thiện giao diện Hồ sơ, Phân công, Ca của tôi, Thống kê; rút/tái đăng ký, xác nhận, báo vắng/thay, gỡ, CSV.
- Xây `EligibilityChecker`, validator, baseline thứ tự đăng ký, heuristic khan hiếm/cân bằng và lưu/công bố snapshot có revision.
- Tạo đúng ba fixture 30/12/5 và sáu artifact so sánh. Tất cả kết quả 0 vi phạm; cải tiến giảm chênh tải xuống 1.
- Phát hiện và sửa race trong phân công thủ công: chuyển kiểm tra ứng viên/sức chứa vào sau khóa `schedule_guard`. I05 hai tiến trình đạt đúng một thành công.
- Phát hiện và sửa cảnh báo `unassigned_json` không có trong SELECT lịch sử run; kiểm tra server log lần cuối không còn warning.
- HTTP ban đầu lỗi do test chọn nhầm một ca cũ theo regex; sửa test định vị đúng hoạt động và kiểm chứng trạng thái từ DB. Lần cuối 27/27 đạt.
- Dọn đúng 5 hoạt động có tiền tố kiểm thử do các lượt HTTP trước tạo; các bài HTTP mới tự dọn hoạt động tạm của chính nó. Không xóa dữ liệu dự án khác.
- Xác minh cấu hình web hiện dùng `volunteer_migrator`; ghi thành vấn đề quyền tối thiểu cần người dùng sửa bằng quyền quản trị.

## 2026-10-23 — Tám cải tiến ổn định hóa mốc 0.3.0-demo

- Đọc lại `AGENT.md`, kế hoạch, trạng thái và kiểm tra Git/mã/MySQL trước khi sửa; giữ nguyên dữ liệu `volunteer_app`.
- Bổ sung sửa/đóng hoạt động và sửa/xóa ca. Mọi thao tác kiểm tra trạng thái, thời gian, phân công/lịch sử liên quan, khóa `schedule_guard`, tăng revision và ghi audit trong transaction.
- Chặn đăng ký vào hoạt động đã đóng/hết hạn; snapshot chỉ nhận hoạt động đang công bố.
- Tạo migration `003_hardening.sql` cho giới hạn đăng nhập; thêm checksum migration và từ chối migration đã áp dụng bị sửa.
- Gia cố đăng nhập: giới hạn 5 lần sai/15 phút theo hash tài khoản và IP, xoay session/CSRF, timeout nhàn rỗi 30 phút, kiểm tra lại role/trạng thái user mỗi request, cookie và security headers.
- Tách xử lý POST khỏi `public/index.php` sang `src/Http/ActionController.php`; sửa HTML lồng form ở phần lịch rảnh; thêm xác nhận thao tác nguy hiểm.
- Tách tuyệt đối test DB: `TestDatabase` chỉ cho phép schema hậu tố `_test`, `lab.sh http-test` tự chạy server cổng 18106 với header môi trường test, không dùng server demo/DB ứng dụng.
- Thêm kiểm tra quyền MySQL tối thiểu cho cấu hình app. Kiểm tra trên server thật xác nhận kết nối đúng cổng/schema nhưng chủ động FAIL vì tài khoản hiện có `ALL`; cấu hình cũ được giữ nguyên.
- Dựng MySQL cô lập cục bộ với `volunteer_test` để xác minh: 20 unit, 3 fixture, 11 integration, quản lý hoạt động/ca, stale và concurrency đạt; HTTP 28/28 đạt; lint sạch; migration/seed chạy lại không nhân dữ liệu.
- Xác nhận workspace hiện có repository thật trên nhánh `lab05`; thêm MIT `LICENSE`, `VERSION` và công cụ tạo tar.gz/checksum chỉ từ Git `HEAD`. Không push và không tạo issue/review/đóng góp giả.
- Tạo gói từ commit `4a1fe90`, xác minh checksum và không có `.git`, `.local`, `release`; giải nén vào thư mục sạch, chạy migration 001–003, seed, `extend` và HTTP 28/28 trên schema `volunteer_release_test`. Sau đó xóa đúng schema/thư mục/cấu hình tạm.
