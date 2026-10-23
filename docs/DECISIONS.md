# Quyết định dự án

## 2026-10-23 — Mốc demo ban đầu

- Đây là checkpoint giao diện và ba luồng chạy thật, không phải tuyên bố hoàn tất G00–G11.
- Giữ stack PHP, MySQLi, MySQL Server, HTML/CSS/JavaScript thuần; không bổ sung framework hoặc CDN.
- Hai vai trò `coordinator` và `volunteer`; tài khoản demo được tạo bằng seed, mật khẩu băm bằng `password_hash`.
- Giờ nhập/hiển thị dùng `Asia/Ho_Chi_Minh`, chuyển UTC trước khi lưu MySQL.
- Hoạt động demo có thời gian bắt đầu/kết thúc. Ca phải nằm hoàn toàn trong khoảng thời gian hoạt động.
- Một ca có thể gắn nhiều kỹ năng; mốc này chỉ lưu và hiển thị yêu cầu kỹ năng, chưa chạy thuật toán xét điều kiện.
- Cấu hình ứng dụng ở `app/.local/db.json`, cấu hình migration ở `app/.local/migration-db.json`; cả hai nằm ngoài Git.
- Seed là idempotent theo tài khoản/tên hoạt động/tên ca và không đặt lại mật khẩu tài khoản đã tồn tại.
- Các quyết định nghiệp vụ đầy đủ trong kế hoạch vẫn có hiệu lực cho giai đoạn sau.

## 2026-10-23 — Hoàn thiện nghiệp vụ và thuật toán

- Dùng một `EligibilityChecker` thuần cho tự động, thủ công, thay người và kiểm định công bố; sức chứa được khóa/kiểm tra trong transaction ở lớp ghi dữ liệu.
- Lưu UTC, hiển thị `Asia/Ho_Chi_Minh`; khoảng thời gian là nửa mở nên hai ca chạm biên được phép.
- Kỹ năng ca dùng quy tắc đủ tất cả; các khoảng lịch rảnh tiếp giáp được hợp nhất khi đánh giá bao phủ.
- Phân công hiệu lực chỉ gồm `assigned` và `confirmed`; `absent`, `cancelled`, `replaced` giải phóng chỗ nhưng giữ lịch sử.
- Thay người dùng hàng đợi `replacement_requests`; nếu ứng viên sai điều kiện hoặc ca đã đủ thì giữ yêu cầu mở và rollback.
- Mọi thay đổi ảnh hưởng lịch khóa singleton `schedule_guard` và tăng revision. Công bố nháp phải khớp revision, nếu không trả thông báo stale.
- Baseline là `registration_order` v1.0.0; cải tiến là `scarcity_balanced` v1.0.0, tối đa 100 lượt sửa cục bộ, xác định và không cam kết tối ưu.
- Ba mã fixture chuẩn dùng chữ hoa trong tên tệp: `DU_NGUOI_V1`, `THIEU_NGUOI_V1`, `TRUNG_LICH_V1`.
- CSV thêm UTF-8 BOM và tiền tố `'` cho ô bắt đầu bằng `=`, `+`, `-`, `@`.

## 2026-10-23 — Ổn định hóa, bảo mật và phát hành demo

- Đóng hoạt động là thao tác một chiều trong UI; từ chối nếu còn phân công hiệu lực hoặc yêu cầu thay người mở. Các nháp liên quan được chuyển `superseded`.
- Không cho thay giờ/nhu cầu/kỹ năng của ca đang có phân công hiệu lực; vẫn cho đổi tên. Chỉ xóa ca chưa có bất kỳ lịch sử phân công/run assignment nào.
- Mọi integration/HTTP/concurrency test có ghi dữ liệu bắt buộc dùng schema hậu tố `_test`; `volunteer_app` luôn bị guard từ chối dù biến môi trường cấu hình sai.
- Tài khoản web chỉ được có `SELECT, INSERT, UPDATE, DELETE`; migration/test dùng tài khoản riêng có DDL trong đúng schema của nó.
- Khóa đăng nhập dùng cửa sổ 15 phút, tối đa 5 lần sai; chỉ lưu hash định danh/IP trong bảng, không lưu mật khẩu hoặc username thử sai.
- Migration đã áp dụng là bất biến; SHA-256 được lưu và so sánh ở các lần chạy sau.
- `public/index.php` giữ vai trò điều phối/render; toàn bộ POST action, validation và redirect nằm trong `ActionController` để giảm front controller đơn khối.
- Phiên bản demo là `0.3.0-demo`; gói bàn giao được tạo từ Git `HEAD`, không lấy file bí mật/không theo dõi trong working tree.
