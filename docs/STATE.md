# Trạng thái dự án

- Cập nhật lúc: 2026-10-23 18:45 UTC.
- Nhánh và commit: `lab05`, HEAD `e7e193b`; đang có thay đổi đăng ký tài khoản/giao diện chưa commit. Tệp `Đề tài 6.docx` của người dùng vẫn untracked và không bị sửa.
- Giai đoạn hiện tại: đã triển khai G00–G10 và chuẩn bị bàn giao G11. Đây là demo học phần, không phải production.
- Phạm vi phiên: bổ sung tự đăng ký volunteer, menu đúng vai trò, modal hồ sơ, footer và chuẩn hóa typography Open Sans.
- Đã hoàn thành: tự đăng ký với validation/password hash/role cố định; unique email; menu riêng cho khách/volunteer/coordinator; route điều phối giữ kiểm tra 403; hồ sơ điều phối trong modal; footer có phiên bản/năm/múi giờ; toàn bộ giao diện dùng Open Sans cục bộ. Các luồng vận hành 0.4 trước đó vẫn giữ nguyên.
- Migration: `001_demo_core.sql` đến `005_self_registration.sql`; migration 005 thêm unique email cho hồ sơ. Đã áp dụng 005 lên `volunteer_app` theo kiểu cộng dồn, không reset/xóa dữ liệu.
- Kiểm thử gần nhất: MySQL 8.4.11 tạm tại `127.0.0.1:33307`, schema `volunteer_test`; 21 unit, 3 fixture, 13 integration, event/shift, I04 stale, I05 concurrency, workflow mở rộng và HTTP 45/45 đều đạt; PHP lint và `git diff --check` sạch.
- Kiểm thử workflow mới xác nhận: rollback chuyển người sai; người vắng bị chặn phân lại; `unfilled` có thể mở/kết thúc lại nhưng không mở sau khi đóng hoạt động; cặp A/B không đổi lịch công bố; lịch sử/audit đầy đủ. HTTP đi thật qua hồ sơ/lịch rảnh, đối chứng, chuyển người, unfilled/mở lại, hai CSV và kho hoạt động.
- Cấu hình ứng dụng thực vẫn ở `app/.local/db.json`; bí mật bị ignore. Cấu hình/test server tạm đã được dọn sau kiểm thử.
- MySQL ứng dụng: `127.0.0.1:3307`, schema `volunteer_app`; migration 001–005 đã áp dụng.
- Lỗi/công việc ngoài mã: tài khoản web trên server thật trước đó còn `ALL PRIVILEGES`; cần đổi sang quyền tối thiểu. Workbench/UUID, issue/review và bảng đóng góp thành viên cần bằng chứng thao tác thật của người dùng/nhóm.
- Cách chạy sau khi cấu hình: `cd /home/lynn/project_6/app && bash lab.sh migrate && bash lab.sh seed && bash lab.sh check && bash lab.sh run`; website `http://127.0.0.1:8106`.
- Next action: người dùng xem thử luồng đăng ký, menu theo vai trò, modal và Open Sans trên `http://127.0.0.1:8106`; sau khi chấp nhận thì commit/push thay đổi và tạo lại gói phát hành 0.4.0-demo.
