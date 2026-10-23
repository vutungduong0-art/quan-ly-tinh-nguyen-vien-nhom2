# Trạng thái dự án

- Cập nhật lúc: 2026-10-23 16:45 UTC.
- Git: nhánh `lab05`, commit triển khai `4a1fe90`, remote `origin` do người dùng cấu hình. Mốc bàn giao được nhận diện bằng tag cục bộ `v0.3.0-demo`; không tự push.
- Giai đoạn: đã triển khai G00–G10 và chuẩn bị G11. Đây vẫn là bản demo học phần, không phải hệ thống production.
- Chức năng: F01–F05, hai thuật toán, snapshot/hash/revision, chỉnh tay, xác nhận, vắng/thay, thống kê, CSV và audit đều đã nối MySQL.
- Tám cải tiến mới: sửa/đóng hoạt động; sửa/xóa ca có bảo vệ; schema test tách biệt; kiểm tra quyền MySQL tối thiểu; sửa form lồng nhau; MIT/VERSION/đóng gói release; tách POST sang `ActionController`; gia cố đăng nhập/session/security headers.
- Migration: `001_demo_core.sql`, `002_full_project.sql`, `003_hardening.sql`; `schema_migrations` lưu SHA-256 và từ chối migration cũ bị sửa.
- MySQL ứng dụng đã xác minh: host `Lynn`, port `3307`, schema `volunteer_app`, server UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23`, MySQL `8.4.11`. Không xóa/reset schema này.
- Kiểm thử ghi dữ liệu chỉ chạy với cấu hình `app/.local/test-db.json`; guard từ chối `volunteer_app` và schema không có hậu tố `_test`. HTTP test tự mở server test cổng 18106.
- Kết quả gần nhất trên MySQL cô lập/schema `volunteer_test`: 20 unit, 3 fixture, 11 integration, quản lý hoạt động/ca, I04 stale và I05 concurrency đều đạt; HTTP 28/28 đạt; PHP lint sạch; migration/seed chạy lại idempotent.
- Release candidate tạo từ commit `4a1fe90` đã được giải nén/cài trên schema sạch `volunteer_release_test`; migration 001–003, seed, `extend` và HTTP 28/28 đều đạt. Gói cuối có SHA-256 trong tệp `.sha256` đi kèm.
- Kết quả kiểm tra server thật: mã/migration/PHP đạt; kết nối đúng server/schema; kiểm tra chủ động thất bại vì `app/.local/db.json` vẫn dùng tài khoản có `ALL PRIVILEGES`. Đây là lỗi cấu hình môi trường còn mở, không phải PASS.
- Cấu hình bí mật: `app/.local/db.json`, `migration-db.json`, `test-db.json`; đều bị ignore và không thuộc gói release.
- Việc còn cần thao tác quản trị: tạo/cấp đúng quyền cho `volunteer_app@localhost`, tạo `volunteer_test`, chạy `configure.php app|test`, rồi chạy lại `check`, `test-prepare`, `extend`, `http-test` trên MySQL cổng 3307.
- Việc còn cần bằng chứng con người: issue/review và bảng đóng góp thành viên thật; không tạo giả.
