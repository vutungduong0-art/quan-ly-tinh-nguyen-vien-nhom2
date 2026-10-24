# Nguồn và xuất xứ

- Kế hoạch chỉ định nguồn tham chiếu: `FIT-DNU/Open-Source-Software-Development`, nhánh `lab5`, commit đã đối chiếu trong kế hoạch `9a2f64648b698fb073509341c5326be471840b55`, thư mục `Ma_nguon_MySQL` (MIT).
- Khi bắt đầu phiên 23/10/2026, workspace chỉ có `KE_HOACH_VIBECODING_DE_TAI_6.md`, `AGENT.md` và chưa có cây mã nguồn mẫu để sao chép/kế thừa trực tiếp. Theo yêu cầu “không clone lại”, không thực hiện clone hoặc tạo repository lồng.
- Mã trong `app/` của checkpoint này được viết mới, nhưng giữ kiến trúc và kỹ thuật đã chọn trong kế hoạch: PHP/MySQLi, `public/`, `src/`, `database/`, `tools/`, `tests/`, cấu hình `.local/db.json`, truy vấn tham số và `utf8mb4`.
- Không dùng thư viện frontend hoặc ảnh bên thứ ba. Open Sans được lấy từ thư mục `ofl/opensans` của kho Google Fonts, gồm hai variable TTF và giấy phép OFL; ứng dụng phục vụ các tệp này cùng origin, không gọi CDN khi chạy.
- Phần hoàn thiện thuật toán, migration 002, repository, kiểm thử và giao diện tiếp tục được viết trực tiếp trong workspace, không tải thêm mã hoặc tài nguyên ngoài.
- Dự án hiện có Git repository/remote thật do người dùng cấu hình, dùng giấy phép MIT trong `LICENSE`; `THIRD_PARTY_NOTICES.md` ghi nguồn tham chiếu và phạm vi mã tự viết.
