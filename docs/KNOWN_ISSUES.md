# Vấn đề còn tồn tại

## Đã giải quyết ngày 24/10/2026 — quyền và schema MySQL

- PHP hiện xác thực bằng `volunteer_app@localhost` chỉ có quyền dữ liệu trên `volunteer_app`; `bash lab.sh check` đạt toàn bộ.
- `volunteer_test` và tài khoản `volunteer_test@localhost` đã được tạo trên đúng MySQL cổng 3307. Migration 001–005, seed, `extend` và HTTP 45/45 đều đạt trên schema này.
- Guard test vẫn từ chối `volunteer_app` hoặc schema không có hậu tố `_test`; không chạy kiểm thử ghi dữ liệu trên database ứng dụng.

## Bàn giao cần bằng chứng của nhóm

- Workbench chưa được người dùng mở để đối chiếu trực tiếp UUID. Giá trị PHP cần đối chiếu: `0e47d81a-cb0f-11f1-8097-00155d38bf23`, port `3307`.
- GitHub public API được kiểm tra ngày 24/10/2026: repository chưa có issue hoặc pull request/review. Đã thêm mẫu issue/PR và `docs/CONTRIBUTIONS.md`, nhưng nhóm vẫn phải tạo issue/review bằng tài khoản thành viên thật.
- Lịch sử Git có bốn định danh tác giả; chưa có ánh xạ họ tên/MSSV do nhóm xác nhận. Không tự gộp định danh hoặc tạo dữ liệu giả để lấp chỗ trống này.
- Gói đã được cài sạch trên MySQL 8.4.11 cô lập; toàn bộ kiểm thử cũng đã chạy lại trên `volunteer_test` của đúng server cổng 3307.

## Giới hạn thuật toán đã biết

Thuật toán cải tiến là heuristic tham lam kèm sửa cục bộ tối đa 100 lượt, xác định nhưng không bảo đảm tối ưu toàn cục. Mọi kết quả vẫn được validator độc lập kiểm tra trước khi lưu/công bố.

## Phần bàn giao 0.4.0 còn cần đồng bộ

- Tag local `v0.4.0-demo` và gói/checksum đã được tạo từ commit `7bc0b1f`; bản giải nén chạy lại unit/fixture thành công. Terminal agent chưa có credential HTTPS nên commit/tag chưa đẩy được lên GitHub.
- `comparison_group` bảo đảm hai thuật toán dùng cùng snapshot trong ứng dụng; công cụ fixture vẫn sinh artifact độc lập và ghi phiên bản mã, không ghi commit Git khi cây làm việc chưa commit.

## Giới hạn đăng ký tài khoản trong bản demo

Tài khoản volunteer tự đăng ký được kích hoạt ngay để thuận tiện trình diễn. Bản demo chưa gửi email xác minh, CAPTCHA hoặc quy trình điều phối duyệt tài khoản. Nếu triển khai công khai, cần bổ sung xác minh email, chống đăng ký tự động và chính sách kích hoạt trước khi cho tham gia hoạt động.
