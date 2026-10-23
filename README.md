# VolunteerHub — hệ thống tổ chức hoạt động và phân công tình nguyện viên

VolunteerHub là bản triển khai Đề tài 6 bằng PHP, MySQLi, MySQL Server, HTML, CSS và JavaScript thuần. Dự án đã đi tiếp từ checkpoint demo ban đầu: các chức năng nghiệp vụ F01–F05, hai thuật toán phân công, snapshot/revision, báo vắng–thay người, thống kê và CSV đều đã nối với dữ liệu MySQL thật.

Mốc `0.4.0-demo` bổ sung lớp vận hành còn thiếu sau khi đối chiếu đề bài: chuyển phân công nguyên tử, kết thúc/mở lại yêu cầu không tìm được người thay, chặn phân lại người đã báo vắng, báo cáo từng ca, cặp đối chứng dùng cùng snapshot/hash, CSV đầy đủ hơn và kho/lịch sử hoạt động. Issue, review và bảng đóng góp thành viên vẫn phải dùng bằng chứng thật của nhóm, không được tạo giả.

## 1. Chức năng hiện có

- Giao diện tiếng Việt màu xanh lá, responsive, dùng Open Sans đóng gói cục bộ và dashboard đọc số liệu trực tiếp từ MySQL.
- Khách có thể tự tạo tài khoản tình nguyện viên; mật khẩu được băm, email/tên đăng nhập là duy nhất và biểu mẫu không cho tự chọn quyền điều phối.
- Quản lý thêm/sửa/đóng hoạt động và thêm/sửa/xóa ca an toàn; chặn thay đổi làm sai phân công đang hiệu lực; validation giờ và số người ở máy chủ.
- Hồ sơ tình nguyện viên, kỹ năng, lịch rảnh; điều phối xem hồ sơ bằng modal, chặn xóa dữ liệu đang làm phân công mất hợp lệ.
- Đăng ký, rút/tái đăng ký, lịch sử đăng ký và trang **Ca của tôi**.
- Bộ `EligibilityChecker` dùng chung: tài khoản hoạt động, đăng ký, đủ toàn bộ kỹ năng, lịch rảnh phủ ca, không trùng lịch và không vượt chỗ.
- Thuật toán đối chứng theo thứ tự đăng ký và thuật toán cải tiến ưu tiên ca khan hiếm/cân bằng tải.
- Lưu snapshot JSON, SHA-256, phiên bản thuật toán, cấu hình, chỉ số và danh sách chưa bố trí.
- Phương án nháp, `schedule_revision`, từ chối phương án cũ và công bố trong giao dịch.
- Phân công thủ công, chuyển/gỡ phân công, xác nhận, báo vắng, thay người hoặc ghi nhận chưa tìm được người; mọi đường đều dùng transaction, kiểm tra máy chủ và nhật ký audit.
- Thống kê hoạt động, tình trạng từng ca và cặp A/B cùng snapshot; kho/lịch sử dữ liệu đã đóng; xuất CSV UTF-8 có mã/liên hệ/kỹ năng/giờ Việt Nam và vô hiệu hóa tiền tố công thức bảng tính.
- Người đã báo vắng không thể bị thuật toán, thao tác tay hoặc luồng thay người đưa trở lại chính ca đó.
- Ba fixture `DU_NGUOI_V1`, `THIEU_NGUOI_V1`, `TRUNG_LICH_V1`, mỗi bộ đúng 30 người/12 ca/5 kỹ năng.
- Session chặt chẽ, khóa tạm sau nhiều lần đăng nhập sai, `password_hash`/`password_verify`, CSRF, role/ownership server-side, security headers, prepared statements, escaping HTML, khóa ngoại/unique/CHECK.
- Migration có checksum chống sửa lịch sử; kiểm thử ghi dữ liệu chỉ được chạy trên schema có hậu tố `_test`.
- Menu theo vai trò: điều phối thấy Tình nguyện viên/Phân công/Thống kê; tình nguyện viên thấy Hồ sơ/Ca của tôi. Route và action vẫn kiểm tra quyền ở máy chủ.

## 2. Thông tin môi trường của dự án

Các giá trị đang dùng trên máy hiện tại:

| Thành phần | Giá trị |
| --- | --- |
| Thư mục dự án | `/home/lynn/project_6` |
| Thư mục ứng dụng | `/home/lynn/project_6/app` |
| MySQL host | `127.0.0.1` |
| MySQL port | `3307` |
| Database/schema | `volunteer_app` |
| Địa chỉ website | `http://127.0.0.1:8106` |
| Múi giờ hiển thị | `Asia/Ho_Chi_Minh` |
| Múi giờ lưu trong MySQL | UTC |

Nếu port MySQL trên máy thay đổi, phải cập nhật cả cấu hình PHP và MySQL Workbench.

## 3. Phân biệt các tài khoản và mật khẩu

Trong quá trình cài đặt có ba loại tài khoản khác nhau:

| Loại | Ví dụ | Dùng để làm gì |
| --- | --- | --- |
| Tài khoản Ubuntu | `lynn` | Chạy lệnh `sudo`, khởi động dịch vụ MySQL |
| Tài khoản MySQL | `volunteer_migrator`, `volunteer_app` | Migration, seed và kết nối PHP với MySQL |
| Tài khoản website | `dieuphoi`, `tinhnguyen` | Đăng nhập giao diện VolunteerHub |

Lưu ý quan trọng:

- Khi terminal hiện `[sudo] password for ...`, nhập mật khẩu đăng nhập Ubuntu.
- Khi terminal hiện `Enter password:` sau lệnh `mysql ... -p`, nhập mật khẩu của tài khoản MySQL được ghi sau `-u`.
- Khi đăng nhập website, dùng tài khoản mẫu ở mục 10, không dùng tài khoản MySQL.
- Mật khẩu thường không hiển thị dấu `*` khi gõ. Đây là hành vi bình thường.
- Không gửi mật khẩu qua hội thoại và không ghi mật khẩu vào Git.

## 4. Kiểm tra MySQL Server trên port 3307

Kiểm tra công cụ đã cài:

```bash
mysql --version
php -v
php -m
```

Trong kết quả `php -m` phải có `mysqli` và `mysqlnd`.

Khởi động MySQL nếu server chưa chạy:

```bash
sudo service mysql start
```

Lệnh này yêu cầu mật khẩu Ubuntu. Nếu báo `sudo: Authentication failed`, kiểm tra lại bàn phím, bộ gõ và mật khẩu đăng nhập Ubuntu. Không nhập mật khẩu MySQL tại lời nhắc `sudo`.

Có thể kiểm tra MySQL trên port 3307 bằng:

```bash
mysqladmin --protocol=TCP -h127.0.0.1 -P3307 ping
```

Nếu lệnh trả về `mysqld is alive` thì server đang chạy. Nếu trả về `Access denied` thay vì lỗi kết nối, server cũng đã nhận được yêu cầu; khi đó vấn đề nằm ở tài khoản, không phải port.

## 5. Đăng nhập MySQL bằng quyền quản trị

Trên Ubuntu, tài khoản MySQL `root` thường dùng xác thực qua hệ điều hành. Vì vậy lệnh dưới đây có thể báo lỗi 1698:

```bash
mysql --protocol=TCP -h127.0.0.1 -P3307 -u root -p
```

Trong trường hợp đó, dùng:

```bash
sudo env MYSQL_HISTFILE=/dev/null mysql
```

`MYSQL_HISTFILE=/dev/null` giúp mật khẩu viết trong câu lệnh SQL không bị lưu vào lịch sử MySQL cục bộ.

Sau khi vào dấu nhắc `mysql>`, kiểm tra đúng server:

```sql
SELECT @@hostname, @@port, @@socket, @@server_uuid,
       CURRENT_USER(), VERSION();
```

`@@port` phải là `3307`. Nếu kết quả là `3306`, dừng lại vì bạn đang vào một MySQL Server khác.

Nếu không có quyền `sudo`, cần dùng một tài khoản quản trị MySQL trong Workbench hoặc nhờ người quản trị Ubuntu cấp quyền. Không có cách an toàn để bỏ qua xác thực quản trị.

## 6. Tạo database và tài khoản MySQL

Thực hiện phần này trong phiên `mysql>` có quyền quản trị.

### 6.1. Kiểm tra trước khi tạo

```sql
SHOW DATABASES LIKE 'volunteer_app';

SELECT User, Host, plugin, account_locked
FROM mysql.user
WHERE User IN ('volunteer_migrator', 'volunteer_app');
```

### 6.2. Tạo database

Lệnh `IF NOT EXISTS` không xóa hoặc reset database đã có:

```sql
CREATE DATABASE IF NOT EXISTS volunteer_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

Kiểm tra:

```sql
SHOW DATABASES LIKE 'volunteer_app';
```

Kết quả phải có một dòng `volunteer_app`.

### 6.3. Tạo tài khoản migration

Chọn một mật khẩu riêng, thay cho `<MAT_KHAU_MIGRATION>`. Hai câu lệnh phải dùng cùng một mật khẩu.

```sql
CREATE USER IF NOT EXISTS 'volunteer_migrator'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_MIGRATION>';

ALTER USER 'volunteer_migrator'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_MIGRATION>';

GRANT ALL PRIVILEGES ON volunteer_app.*
  TO 'volunteer_migrator'@'localhost';
```

Tài khoản này dùng cho migration và seed nên được phép tạo bảng và ghi dữ liệu trong riêng schema `volunteer_app`. Nó không được cấp quyền trên toàn bộ MySQL Server.

### 6.4. Tạo tài khoản chạy ứng dụng

Chọn mật khẩu khác, thay cho `<MAT_KHAU_UNG_DUNG>`:

```sql
CREATE USER IF NOT EXISTS 'volunteer_app'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_UNG_DUNG>';

ALTER USER 'volunteer_app'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_UNG_DUNG>';

GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_app.*
  TO 'volunteer_app'@'localhost';
```

Tài khoản website chỉ có quyền đọc và ghi dữ liệu nghiệp vụ; nó không cần quyền tạo hoặc xóa bảng.

### 6.5. Áp dụng và kiểm tra quyền

```sql
FLUSH PRIVILEGES;

SHOW GRANTS FOR 'volunteer_migrator'@'localhost';
SHOW GRANTS FOR 'volunteer_app'@'localhost';
```

Sau đó thoát:

```sql
EXIT;
```

MySQL trên máy hiện nhận các kết nối TCP cục bộ dưới dạng `user@localhost`, nên tài liệu dùng host tài khoản là `localhost` dù PHP kết nối tới địa chỉ `127.0.0.1`.

### 6.6. Tạo schema kiểm thử riêng

Không chạy integration/HTTP/concurrency test trên `volunteer_app`. Trong phiên MySQL có quyền quản trị, tạo schema và tài khoản chỉ dành cho test:

```sql
CREATE DATABASE IF NOT EXISTS volunteer_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'volunteer_test'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_TEST_RIENG>';
ALTER USER 'volunteer_test'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_TEST_RIENG>';
GRANT ALL PRIVILEGES ON volunteer_test.*
  TO 'volunteer_test'@'localhost';
FLUSH PRIVILEGES;
```

Tài khoản test cần DDL trong đúng `volunteer_test` vì bộ test tự chạy migration. Không cấp quyền của schema test cho tài khoản web production.

## 7. Kiểm tra hai tài khoản MySQL trước khi cấu hình PHP

Kiểm tra migration:

```bash
mysql --protocol=TCP -h127.0.0.1 -P3307 \
  -u volunteer_migrator -p volunteer_app \
  -e "SELECT CURRENT_USER(), @@port, DATABASE();"
```

Kết quả mong đợi gần giống:

```text
volunteer_migrator@localhost    3307    volunteer_app
```

Kiểm tra tài khoản ứng dụng:

```bash
mysql --protocol=TCP -h127.0.0.1 -P3307 \
  -u volunteer_app -p volunteer_app \
  -e "SELECT CURRENT_USER(), @@port, DATABASE();"
```

Chỉ chuyển sang bước tiếp theo khi cả hai lệnh đều thành công.

## 8. Cấu hình kết nối cho PHP

Chuyển vào thư mục ứng dụng:

```bash
cd /home/lynn/project_6/app
```

### 8.1. Cấu hình tài khoản migration

```bash
php tools/configure.php migration
```

Nhập lần lượt:

```text
Host [127.0.0.1]:          nhấn Enter
Port [3306]:               nhập 3307
Schema [volunteer_app]:    nhấn Enter
Tài khoản migration:       nhập volunteer_migrator
Mật khẩu:                  nhập mật khẩu migration
```

Kết quả thành công:

```text
Đã kiểm tra kết nối và lưu cấu hình riêng tại .../app/.local/migration-db.json.
```

Nếu kết nối thất bại, cấu hình cũ được giữ nguyên và migration chưa thể chạy.

### 8.2. Cấu hình tài khoản ứng dụng

```bash
php tools/configure.php app
```

Nhập:

```text
Host [127.0.0.1]:          nhấn Enter
Port [3306]:               nhập 3307
Schema [volunteer_app]:    nhấn Enter
Tài khoản ứng dụng:        nhập volunteer_app
Mật khẩu:                  nhập mật khẩu ứng dụng
```

Kết quả thành công:

```text
Đã kiểm tra kết nối và lưu cấu hình riêng tại .../app/.local/db.json.
```

Hai tệp trong `app/.local/` chứa mật khẩu cục bộ, đã được `.gitignore` loại khỏi Git. Không sao chép nội dung các tệp này vào báo cáo hoặc hội thoại.

`configure.php app` sẽ từ chối tài khoản thiếu `SELECT, INSERT, UPDATE, DELETE` hoặc có quyền `ALL`/DDL. Nếu cấu hình mới không đạt, tệp cấu hình cũ được giữ nguyên.

### 8.3. Cấu hình cơ sở dữ liệu kiểm thử

```bash
php tools/configure.php test
```

Nhập host `127.0.0.1`, port `3307`, schema `volunteer_test`, tài khoản `volunteer_test` và mật khẩu test đã tạo ở mục 6.6. Công cụ lưu vào `app/.local/test-db.json`; tệp này cũng không được đưa vào Git.

## 9. Chạy migration, seed và kiểm thử

Vẫn ở thư mục `/home/lynn/project_6/app`, chạy từng lệnh và kiểm tra kết quả trước khi sang lệnh tiếp theo:

```bash
bash lab.sh migrate
```

Kết quả lần đầu phải có năm migration:

```text
Đã áp dụng 001_demo_core.sql.
Đã áp dụng 002_full_project.sql.
Đã áp dụng 003_hardening.sql.
Đã áp dụng 004_workflow_reporting.sql.
Đã áp dụng 005_self_registration.sql.
Migration hoàn tất.
```

Chạy lại migration là an toàn; lần sau sẽ báo migration đã được áp dụng và checksum hợp lệ. Nếu một tệp migration cũ bị sửa sau khi áp dụng, lệnh sẽ dừng thay vì âm thầm chấp nhận lịch sử khác nhau.

Nạp dữ liệu mẫu:

```bash
bash lab.sh seed
```

Seed không xóa dữ liệu và không nhân bản tài khoản/hoạt động mẫu đã tồn tại.

Kiểm tra môi trường và kết nối:

```bash
bash lab.sh check
```

Chạy unit test:

```bash
bash lab.sh test
```

Chuẩn bị rồi chạy integration test trên `volunteer_test`:

```bash
bash lab.sh test-prepare
bash lab.sh integration
```

Kết quả hiện đã xác minh:

```text
Kết quả integration: 13 đạt, 0 lỗi.
```

Chạy toàn bộ nghiệp vụ không cần HTTP server:

```bash
bash lab.sh fixtures
bash lab.sh compare
bash lab.sh extend
```

`extend` chạy 21 unit, kiểm định 3 fixture, 13 integration, kiểm tra quản lý hoạt động/ca, phương án stale I04, hai tiến trình tranh chỗ I05 và workflow mở rộng.

`http-test` chạy 45 bước end-to-end, gồm đăng ký tài khoản, mật khẩu băm, email duy nhất, menu theo vai trò, modal hồ sơ, footer phiên bản, hồ sơ/lịch rảnh, A/B cùng snapshot, chuyển người, `unfilled`/mở lại, chặn phân lại người vắng, phân quyền CSV, CSV mở rộng và kho hoạt động. Cả hai lệnh chỉ dùng schema hậu tố `_test`.

## 10. Chạy website

Từ thư mục `app/`:

```bash
bash lab.sh run
```

Giữ terminal này đang chạy và mở trình duyệt tại:

<http://127.0.0.1:8106>

Tài khoản website mẫu:

| Vai trò | Tên đăng nhập | Mật khẩu |
| --- | --- | --- |
| Điều phối | `dieuphoi` | `Dieuphoi@123` |
| Tình nguyện viên | `tinhnguyen` | `Tinhnguyen@123` |
| Tình nguyện viên phụ | `tinhnguyen02` | `Tinhnguyen@123` |
| Tình nguyện viên phụ | `tinhnguyen03` | `Tinhnguyen@123` |

Seed có tổng cộng 30 tài khoản tình nguyện viên. Các tài khoản từ `tinhnguyen04` đến `tinhnguyen30` cùng dùng mật khẩu demo `Tinhnguyen@123`.

Các mật khẩu trên chỉ dành cho dữ liệu demo cục bộ.

Khách cũng có thể chọn **Đăng ký** trên thanh điều hướng để tạo tài khoản mới. Tài khoản tự đăng ký luôn là `volunteer`; quyền `coordinator` không xuất hiện trong biểu mẫu và không được nhận từ dữ liệu phía trình duyệt.

## 11. Kịch bản kiểm tra nhanh

1. Thử tạo một tài khoản tình nguyện viên tại **Đăng ký**, sau đó đăng nhập bằng tài khoản vừa tạo.
2. Đăng nhập `dieuphoi`, tạo hoạt động và các ca có kỹ năng/nhu cầu; mở **Tình nguyện viên** và xem một hồ sơ trong modal.
3. Đăng nhập `tinhnguyen`, mở **Hồ sơ** để kiểm tra kỹ năng/lịch rảnh rồi đăng ký hoạt động.
4. Đăng nhập lại `dieuphoi`, mở **Phân công**, chọn hoạt động và chạy **Cải tiến — ưu tiên khan hiếm & cân bằng**.
5. Mở phần giải thích ca thiếu/người 0 ca, sau đó công bố phương án.
6. Đăng nhập `tinhnguyen`, mở **Ca của tôi**, xác nhận rồi báo vắng một ca.
7. Đăng nhập `dieuphoi`, mở hàng đợi thay người, thử một người không hợp lệ rồi chọn người hợp lệ.
8. Mở **Thống kê**, đối chiếu chỉ số và xuất CSV; chạy `bash lab.sh compare` để trình bày sáu kết quả trên ba snapshot chuẩn.

Kịch bản trình diễn đầy đủ hơn nằm tại `docs/DEMO_SCRIPT.md`.

## 12. Kiểm tra dữ liệu bằng terminal

Kết nối bằng tài khoản ứng dụng:

```bash
mysql --protocol=TCP -h127.0.0.1 -P3307 \
  -u volunteer_app -p volunteer_app
```

Xem các bảng:

```sql
SHOW TABLES;
```

Kiểm tra số lượng:

```sql
SELECT COUNT(*) AS tong_tai_khoan FROM users;
SELECT COUNT(*) AS tong_hoat_dong FROM events;
SELECT COUNT(*) AS tong_ca FROM shifts;
SELECT COUNT(*) AS tong_ky_nang FROM skills;
SELECT COUNT(*) AS tong_dang_ky FROM registrations;
```

Xem hoạt động:

```sql
SELECT id, name, location, start_at, end_at, status
FROM events
ORDER BY start_at;
```

Xem ca và kỹ năng:

```sql
SELECT
    s.id,
    e.name AS hoat_dong,
    s.name AS ten_ca,
    s.start_at,
    s.end_at,
    s.required_count AS so_nguoi_can,
    GROUP_CONCAT(sk.name ORDER BY sk.name SEPARATOR ', ') AS ky_nang
FROM shifts s
JOIN events e ON e.id = s.event_id
LEFT JOIN shift_skills ss ON ss.shift_id = s.id
LEFT JOIN skills sk ON sk.id = ss.skill_id
GROUP BY s.id, e.name, s.name, s.start_at, s.end_at, s.required_count
ORDER BY s.start_at;
```

Xem đăng ký:

```sql
SELECT
    e.name AS hoat_dong,
    u.full_name AS tinh_nguyen_vien,
    u.username,
    r.status,
    r.registered_at
FROM registrations r
JOIN events e ON e.id = r.event_id
JOIN users u ON u.id = r.volunteer_id
ORDER BY r.registered_at;
```

Thoát MySQL:

```sql
EXIT;
```

## 13. Kết nối MySQL Workbench

Trước tiên, từ thư mục `app/` chạy:

```bash
bash lab.sh db-info
```

Ghi lại các giá trị `hostname`, `port`, `server_uuid`, `db_name`, `authenticated_user` và `version`.

Trong MySQL Workbench, tạo kết nối **Standard TCP/IP** với:

| Trường | Giá trị |
| --- | --- |
| Connection Name | `VolunteerHub Local` |
| Hostname | `127.0.0.1` |
| Port | `3307` |
| Username | `volunteer_app` |
| Default Schema | `volunteer_app` |

Chọn **Store in Vault/Keychain** nếu muốn Workbench lưu mật khẩu cục bộ. Không ghi mật khẩu vào README hoặc Git.

Sau khi Test Connection thành công, mở SQL Editor và chạy:

```sql
SELECT @@hostname, @@port, @@server_uuid,
       DATABASE(), CURRENT_USER(), VERSION();
```

So sánh `@@server_uuid`, port và database với kết quả `bash lab.sh db-info`. Các giá trị phải khớp để chắc chắn Workbench và PHP đang dùng cùng MySQL Server.

Để xem dữ liệu:

1. Mở bảng **Schemas** bên trái.
2. Nhấn nút Refresh.
3. Mở schema `volunteer_app`.
4. Mở mục **Tables**.
5. Nhấp phải vào bảng như `events` hoặc `registrations`.
6. Chọn **Select Rows - Limit 1000**.

## 14. Xử lý lỗi thường gặp

### Lỗi 1698: `Access denied for user 'root'@'localhost'`

Nguyên nhân: tài khoản MySQL `root` dùng xác thực Ubuntu, không nhận mật khẩu qua TCP.

Cách xử lý:

```bash
sudo env MYSQL_HISTFILE=/dev/null mysql
```

Nhập mật khẩu Ubuntu khi `sudo` hỏi.

### `sudo: Authentication failed`

Nguyên nhân: mật khẩu Ubuntu sai, bộ gõ/bàn phím không đúng hoặc tài khoản không có quyền sudo.

Cách xử lý:

```bash
whoami
sudo -k
sudo -v
```

Nhập mật khẩu đăng nhập Ubuntu của tài khoản được in bởi `whoami`. Nếu không có quyền sudo, cần dùng tài khoản quản trị hoặc nhờ người quản trị máy; không dùng mật khẩu MySQL cho lời nhắc này.

### Lỗi 1045 với `volunteer_migrator`

Ví dụ:

```text
Access denied for user 'volunteer_migrator'@'localhost'
```

Nguyên nhân thường gặp: chưa tạo tài khoản, sai mật khẩu hoặc tài khoản được tạo cho host khác.

Đăng nhập bằng quyền quản trị rồi chạy lại:

```sql
CREATE USER IF NOT EXISTS 'volunteer_migrator'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_MOI>';
ALTER USER 'volunteer_migrator'@'localhost'
  IDENTIFIED BY '<MAT_KHAU_MOI>';
GRANT ALL PRIVILEGES ON volunteer_app.*
  TO 'volunteer_migrator'@'localhost';
FLUSH PRIVILEGES;
```

Sau đó kiểm tra bằng lệnh ở mục 7 trước khi chạy `configure.php`.

### Lỗi 1049: `Unknown database 'volunteer_app'`

Tài khoản đã đăng nhập được nhưng database chưa tồn tại. Tạo database bằng quyền quản trị:

```sql
CREATE DATABASE IF NOT EXISTS volunteer_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

### `Access denied ... to database 'volunteer_app'`

Mật khẩu đã được chấp nhận nhưng tài khoản chưa có quyền trên database.

Cho tài khoản ứng dụng:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_app.*
  TO 'volunteer_app'@'localhost';
FLUSH PRIVILEGES;
```

Cho tài khoản migration:

```sql
GRANT ALL PRIVILEGES ON volunteer_app.*
  TO 'volunteer_migrator'@'localhost';
FLUSH PRIVILEGES;
```

### `Can't connect` hoặc `Connection refused`

Kiểm tra lần lượt:

```bash
sudo service mysql status
mysqladmin --protocol=TCP -h127.0.0.1 -P3307 ping
```

Đảm bảo cả PHP và Workbench đều dùng host `127.0.0.1`, port `3307`.

### Migration, seed và integration cùng báo thiếu cấu hình

Nguyên nhân: `configure.php` đã thất bại nên không tạo tệp trong `app/.local/`.

Kiểm tra:

```bash
find /home/lynn/project_6/app/.local -maxdepth 1 -type f -printf '%f\n'
```

Cần có:

```text
migration-db.json
db.json
```

Nếu thiếu, sửa kết nối MySQL rồi chạy lại:

```bash
cd /home/lynn/project_6/app
php tools/configure.php migration
php tools/configure.php app
```

## 15. Kiểm thử tách biệt và có thể lặp lại

Mọi test có ghi dữ liệu đều đọc `app/.local/test-db.json`. `TestDatabase` sẽ từ chối schema không có hậu tố `_test` và từ chối đích danh `volunteer_app`.

Sau khi cấu hình test ở mục 8.3, chạy:

```bash
cd /home/lynn/project_6/app
bash lab.sh test-prepare
bash lab.sh test
bash lab.sh integration
bash lab.sh http-test
```

`http-test` tự khởi động một PHP server test riêng tại `127.0.0.1:18106`, gắn `APP_ENV=test`, kiểm tra header môi trường và tự dừng. Nó không dùng server demo cổng 8106 và không chạm schema ứng dụng. Có thể đổi cổng bằng `TEST_HTTP_PORT`, hoặc đổi đường dẫn cấu hình bằng `TEST_DB_CONFIG`.

Các lệnh bổ sung:

```bash
bash lab.sh fixtures      # sinh và kiểm định đúng 30/12/5
bash lab.sh compare       # chạy 2 thuật toán × 3 fixture, ghi artifacts/comparison
bash lab.sh stale         # I04: từ chối phương án cũ
bash lab.sh concurrency   # I05: hai tiến trình tranh một chỗ
bash lab.sh extend        # toàn bộ kiểm tra nghiệp vụ không cần HTTP server
```

Test HTTP còn kiểm tra security headers, quyền hai vai trò, khóa đăng nhập, sửa hoạt động/ca, HTML không có form lồng nhau và lưu bền qua nhiều request.

Kết quả kiểm thử của quá trình triển khai nằm tại `docs/TEST_RESULTS.md`.

## 16. Cấu trúc chính của dự án

```text
app/
├── public/                       Front controller, view và tài nguyên cục bộ
├── src/Http/ActionController.php Xử lý và phân quyền các POST action
├── src/Allocation/               Snapshot, validator và hai thuật toán
├── src/                           Xác thực và repository nghiệp vụ
├── database/migrations/           Migration cộng dồn 001–005 có checksum
├── database/fixtures/generated/   Ba fixture chuẩn có hash
├── tools/                         Configure, migrate, seed, compare
├── tests/                         Unit, integration, HTTP, stale, concurrency
├── .local/                        Cấu hình bí mật cục bộ, không đưa vào Git
└── lab.sh                         Điểm chạy lệnh dự án
```

## 17. Tạo gói phát hành

Phiên bản nằm trong `VERSION`. Công cụ chỉ đóng gói đúng nội dung đã commit ở `HEAD`, tự loại `.git`, `app/.local` và `release` vì các đường dẫn này không được Git theo dõi:

```bash
cd /home/lynn/project_6
bash tools/package_release.sh
sha256sum -c release/volunteerhub-0.3.0-demo.tar.gz.sha256
tar -tzf release/volunteerhub-0.3.0-demo.tar.gz | head
```

Gói và checksum nằm trong `release/`, được `.gitignore` loại khỏi repository. Cài thử bằng cách giải nén vào thư mục mới, tạo schema test sạch, chạy `php tools/configure.php migration`, `php tools/configure.php app`, migration và seed theo các mục trên.

## 18. Việc còn cần người sở hữu dự án hoàn tất

- Ghi issue/review và bảng đóng góp theo thành viên thật; AI không tự tạo minh chứng đóng góp.
- Dùng quyền quản trị MySQL cấp đúng quyền cho `volunteer_app`, rồi chạy lại `php tools/configure.php app`. Cấu hình PHP hiện vẫn xác thực bằng `volunteer_migrator`, hoạt động nhưng rộng quyền hơn khuyến nghị.
- Mở Workbench và đối chiếu trực tiếp `@@server_uuid` với `bash lab.sh db-info`; server PHP hiện đã xác minh là cổng 3307/schema `volunteer_app`.
- Tạo `volunteer_test` thật trên MySQL cổng 3307 và chạy lại toàn bộ test ở mục 15 để đối chiếu môi trường cuối; gói release đã được cài sạch và kiểm chứng trên MySQL 8.4.11 cô lập nhưng tài khoản hiện tại chưa có quyền tạo schema mới trên server thật.

Trạng thái hiện tại nằm trong `docs/STATE.md`; ma trận yêu cầu nằm trong `docs/REQUIREMENTS.md`.
