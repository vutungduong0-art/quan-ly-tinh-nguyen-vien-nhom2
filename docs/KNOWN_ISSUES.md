# Vấn đề còn tồn tại

## Đã giải quyết ngày 24/10/2026 — quyền và schema MySQL

- PHP hiện xác thực bằng `volunteer_app@localhost` chỉ có quyền dữ liệu trên `volunteer_app`; `bash lab.sh check` đạt toàn bộ.
- `volunteer_test` và tài khoản `volunteer_test@localhost` đã được tạo trên đúng MySQL cổng 3307. Migration 001–005, seed, `extend` và HTTP 45/45 đều đạt trên schema này.
- Guard test vẫn từ chối `volunteer_app` hoặc schema không có hậu tố `_test`; không chạy kiểm thử ghi dữ liệu trên database ứng dụng.

## Bàn giao cần bằng chứng của nhóm

- Người dùng đã báo cấu hình/kết nối thành công; nếu cần ảnh minh chứng Workbench khi nộp, đối chiếu UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23` và port `3307` với trang thông tin DB của PHP.
- Issue #1 và PR #2 đã được tạo thật ngày 24/10/2026. PR mergeable, đang chờ review từ `DngOsea` hoặc `vutungduong0-art`; chưa merge vì chưa có review chéo thực tế.
- Lịch sử Git có bốn định danh tác giả; chưa có ánh xạ họ tên/MSSV do nhóm xác nhận. Không tự gộp định danh hoặc tạo dữ liệu giả để lấp chỗ trống này.
- Gói đã được cài sạch trên MySQL 8.4.11 cô lập; toàn bộ kiểm thử cũng đã chạy lại trên `volunteer_test` của đúng server cổng 3307.

## Giới hạn thuật toán đã biết

Thuật toán cải tiến là heuristic tham lam kèm sửa cục bộ tối đa 100 lượt, xác định nhưng không bảo đảm tối ưu toàn cục. Mọi kết quả vẫn được validator độc lập kiểm tra trước khi lưu/công bố.

## Phần bàn giao 0.4.0 còn cần đồng bộ

- Tag `v0.4.0-demo` và gói/checksum được tạo từ commit `7bc0b1f`; bản giải nén chạy lại unit/fixture thành công. GitHub pre-release đã phát hành với tar.gz và tệp SHA-256. PR #2 chỉ được merge sau review thật.
- `comparison_group` bảo đảm hai thuật toán dùng cùng snapshot trong ứng dụng; công cụ fixture vẫn sinh artifact độc lập và ghi phiên bản mã, không ghi commit Git khi cây làm việc chưa commit.

## Giới hạn đăng ký tài khoản trong bản demo

Tài khoản volunteer tự đăng ký được kích hoạt ngay để thuận tiện trình diễn. Bản demo chưa gửi email xác minh, CAPTCHA hoặc quy trình điều phối duyệt tài khoản. Nếu triển khai công khai, cần bổ sung xác minh email, chống đăng ký tự động và chính sách kích hoạt trước khi cho tham gia hoạt động.
