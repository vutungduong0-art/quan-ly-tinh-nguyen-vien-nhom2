# VolunteerHub — hệ thống tổ chức hoạt động và phân công tình nguyện viên

VolunteerHub là bản triển khai Đề tài 6 bằng PHP, MySQLi, MySQL Server, HTML, CSS và JavaScript thuần. Dự án đã đi tiếp từ checkpoint demo ban đầu: các chức năng nghiệp vụ F01–F05, hai thuật toán phân công, snapshot/revision, báo vắng–thay người, thống kê và CSV đều đã nối với dữ liệu MySQL thật.

Phần chưa thể tự hoàn tất chỉ là thủ tục bàn giao bên ngoài mã nguồn: Git repository/commit/release, issue/review và bảng đóng góp thành viên chưa có dữ liệu thực tế để ghi. Xem `docs/STATE.md` và `docs/KNOWN_ISSUES.md`.

## 1. Chức năng hiện có

- Giao diện tiếng Việt màu xanh lá, responsive, dashboard đọc số liệu trực tiếp từ MySQL.
- Quản lý hoạt động, ca, nhu cầu và nhiều kỹ năng yêu cầu; validation giờ và số người ở máy chủ.
- Hồ sơ tình nguyện viên, kỹ năng, lịch rảnh; chặn xóa dữ liệu đang làm phân công mất hợp lệ.
- Đăng ký, rút/tái đăng ký, lịch sử đăng ký và trang **Ca của tôi**.
- Bộ `EligibilityChecker` dùng chung: tài khoản hoạt động, đăng ký, đủ toàn bộ kỹ năng, lịch rảnh phủ ca, không trùng lịch và không vượt chỗ.
- Thuật toán đối chứng theo thứ tự đăng ký và thuật toán cải tiến ưu tiên ca khan hiếm/cân bằng tải.
- Lưu snapshot JSON, SHA-256, phiên bản thuật toán, cấu hình, chỉ số và danh sách chưa bố trí.
- Phương án nháp, `schedule_revision`, từ chối phương án cũ và công bố trong giao dịch.
- Phân công thủ công, gỡ phân công, xác nhận, báo vắng, hàng đợi thay người và nhật ký audit.
- Thống kê hoạt động/lần chạy; xuất CSV UTF-8 và vô hiệu hóa tiền tố công thức bảng tính.
- Ba fixture `DU_NGUOI_V1`, `THIEU_NGUOI_V1`, `TRUNG_LICH_V1`, mỗi bộ đúng 30 người/12 ca/5 kỹ năng.
- Session, `password_hash`/`password_verify`, CSRF, role/ownership server-side, prepared statements, escaping HTML, khóa ngoại/unique/CHECK.

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

## 9. Chạy migration, seed và kiểm thử

Vẫn ở thư mục `/home/lynn/project_6/app`, chạy từng lệnh và kiểm tra kết quả trước khi sang lệnh tiếp theo:

```bash
bash lab.sh migrate
```

Kết quả lần đầu phải có cả hai migration:

```text
Đã áp dụng 001_demo_core.sql.
Đã áp dụng 002_full_project.sql.
Migration hoàn tất.
```

Chạy lại migration là an toàn; lần sau sẽ báo migration đã được áp dụng.

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

Chạy integration test với MySQL hiện tại:

```bash
bash lab.sh integration
```

Kết quả hiện đã xác minh:

```text
Kết quả integration: 9 đạt, 0 lỗi.
```

Chạy toàn bộ nghiệp vụ không cần HTTP server:

```bash
bash lab.sh fixtures
bash lab.sh compare
bash lab.sh extend
```

`extend` chạy 20 unit, kiểm định 3 fixture, 9 integration, kiểm tra phương án stale I04 và hai tiến trình tranh chỗ I05.

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

## 11. Kịch bản kiểm tra nhanh

1. Đăng nhập `dieuphoi`, tạo hoạt động và các ca có kỹ năng/nhu cầu.
2. Đăng nhập `tinhnguyen`, mở **Tình nguyện viên** để kiểm tra kỹ năng/lịch rảnh rồi đăng ký hoạt động.
3. Đăng nhập lại `dieuphoi`, mở **Phân công**, chọn hoạt động và chạy **Cải tiến — ưu tiên khan hiếm & cân bằng**.
4. Mở phần giải thích ca thiếu/người 0 ca, sau đó công bố phương án.
5. Đăng nhập `tinhnguyen`, mở **Ca của tôi**, xác nhận rồi báo vắng một ca.
6. Đăng nhập `dieuphoi`, mở hàng đợi thay người, thử một người không hợp lệ rồi chọn người hợp lệ.
7. Mở **Thống kê**, đối chiếu chỉ số và xuất CSV.
8. Chạy `bash lab.sh compare` để trình bày sáu kết quả trên cùng ba snapshot chuẩn.

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

## 15. Kiểm thử nâng cao

Các kiểm thử thông thường:

```bash
cd /home/lynn/project_6/app
bash lab.sh test
bash lab.sh integration
```

Khi website đang chạy, có thể chạy HTTP smoke test trên **schema thử nghiệm riêng**:

```bash
APP_DB_CONFIG=/duong/dan/db-test.json \
  bash lab.sh http-test http://127.0.0.1:8106
```

HTTP smoke test tạo một hoạt động tạm, kiểm tra trọn luồng hai vai trò rồi tự dọn đúng hoạt động tạm đó. Vẫn nên dùng schema test riêng khi phát triển nhóm.

Các lệnh bổ sung:

```bash
bash lab.sh fixtures      # sinh và kiểm định đúng 30/12/5
bash lab.sh compare       # chạy 2 thuật toán × 3 fixture, ghi artifacts/comparison
bash lab.sh stale         # I04: từ chối phương án cũ
bash lab.sh concurrency   # I05: hai tiến trình tranh một chỗ
bash lab.sh extend        # toàn bộ kiểm tra nghiệp vụ không cần HTTP server
```

Kết quả kiểm thử của quá trình triển khai nằm tại `docs/TEST_RESULTS.md`.

## 16. Cấu trúc chính của dự án

```text
app/
├── public/                     Giao diện, handler và tài nguyên cục bộ
├── src/Allocation/              Snapshot, validator và hai thuật toán
├── src/                         Xác thực và repository nghiệp vụ
├── database/migrations/         Migration cộng dồn 001–002
├── database/fixtures/generated/ Ba fixture chuẩn có hash
├── tools/                       Configure, migrate, seed, compare
├── tests/                       Unit, integration, HTTP, stale, concurrency
├── .local/                      Cấu hình bí mật cục bộ, không đưa vào Git
└── lab.sh                       Điểm chạy lệnh dự án
```

## 17. Việc còn cần người sở hữu dự án hoàn tất

- Tạo Git repository/commit và release thật; workspace hiện có `.git` rỗng nên không thể ghi commit giả.
- Ghi issue/review và bảng đóng góp theo thành viên thật; AI không tự tạo minh chứng đóng góp.
- Dùng quyền quản trị MySQL cấp đúng quyền cho `volunteer_app`, rồi chạy lại `php tools/configure.php app`. Cấu hình PHP hiện vẫn xác thực bằng `volunteer_migrator`, hoạt động nhưng rộng quyền hơn khuyến nghị.
- Mở Workbench và đối chiếu trực tiếp `@@server_uuid` với `bash lab.sh db-info`; server PHP hiện đã xác minh là cổng 3307/schema `volunteer_app`.
- Thử cài release trên schema sạch riêng khi có quyền tạo schema test; không reset schema hiện có.

Trạng thái hiện tại nằm trong `docs/STATE.md`; ma trận yêu cầu nằm trong `docs/REQUIREMENTS.md`.
