# VolunteerHub

Hệ thống tổ chức hoạt động và phân công tình nguyện viên, viết bằng PHP thuần, MySQLi, MySQL Server, HTML, CSS và JavaScript.

## Chức năng chính

- Quản lý hoạt động, ca và kỹ năng cần thiết.
- Đăng ký/rút đăng ký tham gia hoạt động.
- Quản lý hồ sơ, kỹ năng và lịch rảnh của tình nguyện viên.
- Phân công thủ công hoặc bằng hai thuật toán, kiểm tra trùng lịch và sức chứa.
- Xác nhận ca, báo vắng, thay người và lưu lịch sử thao tác.
- Thống kê, so sánh thuật toán và xuất CSV.
- Hai vai trò: điều phối viên và tình nguyện viên.

## 1. Yêu cầu

- Ubuntu hoặc môi trường Linux có Bash.
- PHP 8.1 trở lên với `mysqli` và `mysqlnd`.
- MySQL Server 8.0.16 trở lên.
- MySQL client và `curl`.

Kiểm tra nhanh:

```bash
php -v
php -m | grep -E 'mysqli|mysqlnd'
mysql --version
```

## 2. Chuẩn bị MySQL

Nếu đã có schema và hai tài khoản bên dưới, bỏ qua bước này. Đăng nhập MySQL bằng tài khoản quản trị rồi chạy:

```sql
CREATE DATABASE IF NOT EXISTS volunteer_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'volunteer_migrator'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_MIGRATION>';
ALTER USER 'volunteer_migrator'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_MIGRATION>';
GRANT ALL PRIVILEGES ON volunteer_app.*
  TO 'volunteer_migrator'@'localhost';

CREATE USER IF NOT EXISTS 'volunteer_app'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_UNG_DUNG>';
ALTER USER 'volunteer_app'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_UNG_DUNG>';
GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_app.*
  TO 'volunteer_app'@'localhost';

FLUSH PRIVILEGES;
```

Thay hai giá trị trong dấu `<...>` bằng mật khẩu riêng. Không ghi mật khẩu vào Git hoặc gửi trong hội thoại.

## 3. Cấu hình và khởi tạo dự án

Từ thư mục gốc của mã nguồn:

```bash
cd app
php tools/configure.php migration
php tools/configure.php app
bash lab.sh migrate
bash lab.sh seed
bash lab.sh check
```

Khi công cụ cấu hình hỏi thông tin, nhập đúng MySQL trên máy của bạn. Cấu hình hiện dùng:

| Trường | Giá trị thường dùng |
| --- | --- |
| Host | `127.0.0.1` |
| Port | `3307` trên máy dự án; máy khác có thể là `3306` |
| Schema | `volunteer_app` |
| Tài khoản migration | `volunteer_migrator` |
| Tài khoản ứng dụng | `volunteer_app` |

Mật khẩu được lưu cục bộ trong `app/.local/`; thư mục này đã bị loại khỏi Git.

## 4. Chạy website

Trong thư mục `app/`:

```bash
bash lab.sh run
```

Mở trình duyệt tại:

<http://127.0.0.1:8106>

Giữ terminal đang chạy server. Dùng `Ctrl+C` để dừng.

Nếu cổng `8106` đang được dùng, tìm tiến trình hiện tại trước khi chạy server khác:

```bash
ss -ltnp | grep 8106
```

## 5. Tài khoản website mẫu

| Vai trò | Tên đăng nhập | Mật khẩu |
| --- | --- | --- |
| Điều phối viên | `dieuphoi` | `Dieuphoi@123` |
| Tình nguyện viên | `tinhnguyen` | `Tinhnguyen@123` |
| Tình nguyện viên phụ | `tinhnguyen02` | `Tinhnguyen@123` |

Các mật khẩu này chỉ dành cho dữ liệu demo cục bộ. Người dùng mới cũng có thể chọn **Đăng ký** để tạo tài khoản tình nguyện viên.

## 6. Kiểm thử

Unit test không ghi vào database ứng dụng:

```bash
cd app
bash lab.sh test
```

Integration và HTTP test bắt buộc dùng schema riêng có hậu tố `_test`. Tạo schema/tài khoản test trong MySQL:

```sql
CREATE DATABASE IF NOT EXISTS volunteer_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'volunteer_test'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_TEST>';
ALTER USER 'volunteer_test'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_TEST>';
GRANT ALL PRIVILEGES ON volunteer_test.*
  TO 'volunteer_test'@'localhost';
FLUSH PRIVILEGES;
```

Sau đó chạy:

```bash
php tools/configure.php test
bash lab.sh test-prepare
bash lab.sh extend
bash lab.sh http-test
```

Không cấu hình test bằng schema `volunteer_app` vì test có thao tác ghi và dọn dữ liệu thử.

## 7. Kết nối MySQL Workbench

Tạo kết nối **Standard TCP/IP** với cùng thông tin đã nhập cho PHP:

| Trường | Giá trị trên máy dự án |
| --- | --- |
| Hostname | `127.0.0.1` |
| Port | `3307` |
| Username | `volunteer_app` |
| Default Schema | `volunteer_app` |

Chạy lệnh sau trong cả dự án và Workbench để chắc chắn hai bên dùng cùng server/schema:

```bash
cd app
bash lab.sh db-info
```

```sql
SELECT @@port, @@server_uuid, DATABASE(), CURRENT_USER(), VERSION();
```

## 8. Lỗi thường gặp

- `Unknown database 'volunteer_app'`: tạo schema ở bước 2 trước khi cấu hình PHP.
- `Access denied`: kiểm tra đúng tài khoản, mật khẩu, port và quyền trên `volunteer_app.*`.
- `root@localhost` báo lỗi 1698: trên Ubuntu, thử `sudo mysql`; lời nhắc `sudo` cần mật khẩu Ubuntu, không phải mật khẩu MySQL.
- Migration báo thiếu cấu hình: chạy lại `php tools/configure.php migration`.
- Website báo thiếu cấu hình: chạy lại `php tools/configure.php app`.
- Cổng `8106` đang được dùng: kiểm tra bằng `ss -ltnp | grep 8106`; không tự ý dừng tiến trình chưa xác định.

## 9. Cấu trúc thư mục

```text
app/
├── public/                 Giao diện và front controller
├── src/                    Xác thực, repository và nghiệp vụ
├── database/migrations/    Migration MySQL
├── database/fixtures/      Dữ liệu kiểm thử thuật toán
├── tests/                  Unit, integration và HTTP test
├── tools/                  Cấu hình, migration, seed, so sánh
├── .local/                 Cấu hình riêng, không đưa vào Git
└── lab.sh                  Các lệnh chạy dự án
```

Tài liệu chi tiết:

- [Kịch bản trình diễn](docs/DEMO_SCRIPT.md)
- [Trạng thái hiện tại](docs/STATE.md)
- [Kết quả kiểm thử](docs/TEST_RESULTS.md)
- [Yêu cầu và mức độ hoàn thành](docs/REQUIREMENTS.md)
- [Các vấn đề đã biết](docs/KNOWN_ISSUES.md)

## Giấy phép

Dự án sử dụng giấy phép MIT. Open Sans được đóng gói theo SIL Open Font License 1.1; xem `LICENSE` và `THIRD_PARTY_NOTICES.md`.
