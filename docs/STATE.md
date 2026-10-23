# Trạng thái dự án

- Cập nhật lúc: 2026-10-23 11:43 UTC.
- Nhánh và commit: workspace chưa phải Git repository hợp lệ; `.git` rỗng, không có commit để ghi nhận.
- Giai đoạn hiện tại: đã triển khai và kiểm chứng phần mã G00–G10; G11 hoàn tất tài liệu chạy/demo nhưng chưa thể tạo release/commit/issue/review hoặc đóng góp thành viên thật.
- Phạm vi phiên: hoàn thiện các phần còn lại của Đề tài 6 từ checkpoint demo, không clone hoặc reset dữ liệu hiện có.
- Đã hoàn thành: F01–F05, T01–T02, D01, S01 và kiểm tra quyền ứng dụng của S02; migration `002_full_project.sql`; 30 tài khoản tình nguyện viên; hai thuật toán; snapshot/hash/revision; chỉnh tay; xác nhận; vắng/thay; thống kê; CSV; audit; giao diện responsive.
- MySQL đã xác minh: host `Lynn`, port `3307`, schema `volunteer_app`, server UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23`, MySQL `8.4.11`; migration 001–002 áp dụng và seed chạy thành công.
- Kết quả thuật toán: xem `artifacts/comparison/`; cả 6 lượt có 0 vi phạm. Cải tiến có chênh tải 1 trên cả ba fixture; baseline lần lượt 12, 12 và 6.
- Test gần nhất: `bash lab.sh extend` đạt 20 unit + 3 fixture + 9 integration + I04 + I05; HTTP `27 đạt, 0 lỗi`; PHP lint sạch; mã thoát đều 0. Dữ liệu HTTP tạm đã tự dọn.
- Cách chạy đã xác minh: `cd /home/lynn/project_6/app && bash lab.sh run`; URL `http://127.0.0.1:8106`.
- Cấu hình riêng: `app/.local/db.json` và `app/.local/migration-db.json`, đã ignore; không ghi nội dung bí mật vào tài liệu.
- Vấn đề còn lại: PHP hiện đăng nhập MySQL bằng `volunteer_migrator` có ALL trên riêng schema; cần quản trị viên cấp quyền và cấu hình lại `volunteer_app` để đạt quyền tối thiểu. Workbench chưa được người dùng đối chiếu trực tiếp. Chưa có Git/release/schema sạch/issue-review/dữ liệu đóng góp thật.
- Next action: người dùng cấp `SELECT, INSERT, UPDATE, DELETE` trên `volunteer_app.*` cho `'volunteer_app'@'localhost'`, chạy `php tools/configure.php app`, rồi xác nhận `bash lab.sh db-info` in ra `authenticated_user = volunteer_app@localhost` và đối chiếu cùng UUID trong Workbench.
