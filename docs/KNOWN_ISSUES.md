# Vấn đề còn tồn tại

## Quyền tài khoản MySQL ứng dụng

`bash lab.sh db-info` và `SHOW GRANTS FOR CURRENT_USER()` xác nhận PHP hiện dùng `volunteer_migrator@localhost`, có `ALL PRIVILEGES` trên riêng `volunteer_app.*`. Ứng dụng chạy đúng nhưng chưa đạt nguyên tắc quyền tối thiểu.

Cần đăng nhập MySQL bằng quyền quản trị và chạy:

```sql
CREATE USER IF NOT EXISTS 'volunteer_app'@'localhost' IDENTIFIED BY '<MAT_KHAU_RIENG>';
ALTER USER 'volunteer_app'@'localhost' IDENTIFIED BY '<MAT_KHAU_RIENG>';
GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_app.* TO 'volunteer_app'@'localhost';
FLUSH PRIVILEGES;
```

Sau đó chạy `php tools/configure.php app`; không gửi mật khẩu vào hội thoại.

## Bàn giao bên ngoài mã

- Workspace không phải Git repository hợp lệ nên chưa có nhánh, commit, tag hoặc release để kiểm chứng.
- Chưa có quyền tạo schema sạch riêng để thử cài release từ đầu; không reset `volunteer_app` đang dùng.
- Workbench chưa được người dùng mở để đối chiếu trực tiếp UUID. Giá trị PHP cần đối chiếu: `0e47d81a-cb0f-11f1-8097-00155d38bf23`, port `3307`.
- Chưa có thông tin thành viên, issue hoặc review thật nên C01 và phần quy trình H01 chưa thể đánh giá.

## Giới hạn thuật toán đã biết

Thuật toán cải tiến là heuristic tham lam kèm sửa cục bộ tối đa 100 lượt, xác định nhưng không bảo đảm tối ưu toàn cục. Mọi kết quả vẫn được validator độc lập kiểm tra trước khi lưu/công bố.
