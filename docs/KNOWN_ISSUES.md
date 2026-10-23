# Vấn đề còn tồn tại

## Quyền tài khoản MySQL ứng dụng

`bash lab.sh check` xác nhận PHP kết nối đúng MySQL 8.4.11/schema `volunteer_app`, nhưng chủ động trả mã 1 vì tài khoản hiện dùng có `ALL PRIVILEGES`. Ứng dụng chạy được nhưng chưa đạt nguyên tắc quyền tối thiểu.

Cần đăng nhập MySQL bằng quyền quản trị và chạy:

```sql
CREATE USER IF NOT EXISTS 'volunteer_app'@'localhost' IDENTIFIED BY '<MAT_KHAU_RIENG>';
ALTER USER 'volunteer_app'@'localhost' IDENTIFIED BY '<MAT_KHAU_RIENG>';
GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_app.* TO 'volunteer_app'@'localhost';
FLUSH PRIVILEGES;
```

Sau đó chạy `php tools/configure.php app`; không gửi mật khẩu vào hội thoại.

## Chưa có `volunteer_test` trên server thật

Tài khoản migration hiện tại không có quyền tạo schema mới, đúng với phạm vi đã cấp. Vì vậy bộ kiểm thử ghi dữ liệu đã được chạy trên một MySQL cô lập tạm thời thay vì `volunteer_app`.

Quản trị viên cần tạo `volunteer_test`, cấp DDL trong đúng schema này cho tài khoản test, sau đó chạy:

```bash
cd /home/lynn/project_6/app
php tools/configure.php test
bash lab.sh test-prepare
bash lab.sh extend
bash lab.sh http-test
```

Guard của test sẽ từ chối chạy nếu cấu hình trỏ nhầm `volunteer_app` hoặc schema không kết thúc bằng `_test`.

## Bàn giao cần bằng chứng của nhóm

- Workbench chưa được người dùng mở để đối chiếu trực tiếp UUID. Giá trị PHP cần đối chiếu: `0e47d81a-cb0f-11f1-8097-00155d38bf23`, port `3307`.
- Chưa có issue, review hoặc bảng đóng góp thành viên thật nên C01 và phần quy trình H01 chưa thể đánh giá. Không tạo dữ liệu giả để lấp chỗ trống này.
- Gói đã được cài sạch trên MySQL 8.4.11 cô lập. Vẫn nên chạy lại trên `volunteer_test` của đúng server cổng 3307 sau khi quyền được cấp để đối chiếu môi trường cuối.

## Giới hạn thuật toán đã biết

Thuật toán cải tiến là heuristic tham lam kèm sửa cục bộ tối đa 100 lượt, xác định nhưng không bảo đảm tối ưu toàn cục. Mọi kết quả vẫn được validator độc lập kiểm tra trước khi lưu/công bố.

## Phần bàn giao 0.4.0 chưa tự động hóa

- Chưa tạo tag/gói release `0.4.0-demo`; cây làm việc hiện còn thay đổi để người dùng xem trước và commit sau khi chấp nhận.
- `comparison_group` bảo đảm hai thuật toán dùng cùng snapshot trong ứng dụng; công cụ fixture vẫn sinh artifact độc lập và ghi phiên bản mã, không ghi commit Git khi cây làm việc chưa commit.

## Giới hạn đăng ký tài khoản trong bản demo

Tài khoản volunteer tự đăng ký được kích hoạt ngay để thuận tiện trình diễn. Bản demo chưa gửi email xác minh, CAPTCHA hoặc quy trình điều phối duyệt tài khoản. Nếu triển khai công khai, cần bổ sung xác minh email, chống đăng ký tự động và chính sách kích hoạt trước khi cho tham gia hoạt động.
