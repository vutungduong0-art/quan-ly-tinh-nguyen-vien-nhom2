# Kế hoạch vibecoding dự án tổ chức hoạt động và phân công tình nguyện viên

Tài liệu giao việc cho AI trong Antigravity IDE. Mục tiêu là triển khai đầy đủ Đề tài 6 bằng PHP trên Ubuntu, lưu dữ liệu trong MySQL Server và quản trị cùng cơ sở dữ liệu bằng MySQL Workbench. AI phải thực hiện từng giai đoạn, kiểm chứng kết quả và lưu ngữ cảnh để tiếp tục khi đóng IDE.

Ngày đối chiếu nguồn: 23/10/2026. Đây là kế hoạch triển khai, chưa phải mã nguồn ứng dụng và chưa có kết quả kiểm thử dự án.

## 1. Nguồn yêu cầu và nguyên tắc thực hiện

### 1.1. Tài liệu làm căn cứ

- Đã đọc toàn bộ 2 trang của tệp **Đề tài 6(4).docx** do người dùng cung cấp. Bản chép nội dung đầy đủ nằm ở Phụ lục A để AI có thể đọc độc lập.
- Kho mã nguồn được yêu cầu kế thừa: <https://github.com/FIT-DNU/Open-Source-Software-Development>.
- Nhánh `main` được kiểm tra tại commit `071801d017732fb7d0748c23fcc9f06be497bb06`.
- Nhánh `lab5` được kiểm tra tại commit `9a2f64648b698fb073509341c5326be471840b55`.
- Nếu kho được cập nhật, ghi nhận commit thực tế và đánh giá khác biệt trước khi áp dụng; không tự nhận mã mới giống hoàn toàn bản đã đối chiếu.

**Thứ tự ưu tiên:** yêu cầu hiện tại của người dùng → nội dung Đề tài 6 → quyết định nghiệp vụ ghi trong tài liệu này → cấu trúc và ví dụ từ kho mẫu. Gặp mâu thuẫn, giữ yêu cầu của đề và ghi rõ cách điều chỉnh mã mẫu.

### 1.2. Phạm vi bắt buộc

Triển khai đủ 5 nhóm chức năng, 2 yêu cầu kỹ thuật, 2 tình huống trình diễn, 3 bộ dữ liệu và các phép thử bắt buộc. Không tự thu hẹp thành giao diện demo. Giao diện demo chỉ là mốc trung gian của dự án đầy đủ.

Đề không yêu cầu tối ưu toàn cục, kết nối lịch bên ngoài, thanh toán, bán vé, gửi thư, ứng dụng di động hay dịch vụ trí tuệ nhân tạo. Không thêm các phần này vào đường triển khai bắt buộc. Thuật toán phân công chạy trực tiếp bằng PHP, không cần gọi mô hình ngôn ngữ.

### 1.3. Phân biệt yêu cầu gốc và quyết định thiết kế

Các lựa chọn sau là đề xuất triển khai để làm rõ điểm đề chưa quy định, không phải câu chữ bắt buộc của đề:

1. Hai vai trò `volunteer` và `coordinator`; tạo tài khoản thử bằng seed, chưa cần luồng đăng ký tài khoản công khai.
2. Đăng ký theo hoạt động. Lịch rảnh xác định những ca trong hoạt động mà người đó có thể tham gia. Thời điểm đăng ký hoạt động là thứ tự dùng cho thuật toán đối chứng.
3. Một ca có thể yêu cầu nhiều kỹ năng; mỗi người được phân vào ca phải có đủ tất cả kỹ năng được yêu cầu. Nếu muốn mô hình mỗi vị trí một kỹ năng, cần thay thiết kế và dữ liệu một cách nhất quán trước khi code thuật toán.
4. Khoảng thời gian dùng dạng nửa mở `[bắt đầu, kết thúc)`: hai ca liền nhau, một ca kết thúc đúng lúc ca kia bắt đầu, không trùng lịch.
5. Một người chỉ làm tối đa một vị trí trong cùng một ca; số ca mỗi người không có trần riêng ngoài ràng buộc thời gian. Cân bằng số ca là mục tiêu mềm.
6. Người chưa được phân có thể rút đăng ký. Người đang có phân công phải đi qua luồng báo vắng hoặc điều phối gỡ phân công trong giao dịch; không để phân công mồ côi.
7. Nhập và hiển thị giờ Việt Nam `Asia/Ho_Chi_Minh`; chuyển sang UTC để lưu và tính toán, ghi rõ múi giờ trong tệp dữ liệu và báo cáo.
8. Đếm đủ người bằng các phân công còn hiệu lực; báo riêng số đã xác nhận để không đánh đồng “đã phân công” với “đã xác nhận”.

AI ghi các lựa chọn này vào `docs/DECISIONS.md`. Chỉ hỏi lại khi có yêu cầu thực tế mâu thuẫn, không dừng công việc để hỏi những lựa chọn đã xác định ở đây.

## 2. Kết quả kiểm tra kho mẫu và phương án kế thừa

### 2.1. Những gì thực sự có trong kho

| Nguồn | Quan sát từ mã và tài liệu | Cách sử dụng trong dự án |
| --- | --- | --- |
| `main/index.php`, `views/`, `functions/`, `handle/` | Ứng dụng quản lý sinh viên, học phần, điểm; PHP và Bootstrap | Tham khảo luồng biểu mẫu và bố cục; không coi là hệ thống tình nguyện đã hoàn thành |
| `main/functions/student_functions.php`, `grade_functions.php` | Có truy vấn tham số và thao tác thêm, xem, sửa, xóa | Tham khảo cách tổ chức thao tác dữ liệu; quan hệ tình nguyện phải thiết kế lại |
| `main/functions/auth.php` | So sánh trực tiếp mật khẩu nhập với dữ liệu lưu | Không sao chép cách xác thực này; dùng `password_hash` và `password_verify` |
| `main/functions/db_connection.php` | Thông tin kết nối ghi trực tiếp, dùng tài khoản quản trị, charset `utf8` | Thay bằng cấu hình riêng, tài khoản ứng dụng và `utf8mb4` |
| `main/handle/student_process.php` | Có xóa bằng GET; chưa thấy kiểm tra quyền trong chính handler đã đọc | Không kế thừa cách thay đổi dữ liệu này; dùng POST, quyền ở máy chủ và token chống giả mạo yêu cầu |
| `lab5/Ma_nguon_MySQL` | PHP từ 8.1, MySQL từ 8.0.16, MySQLi/mysqlnd; có `public`, `src`, `database`, `tests`, `tools`, `lab.sh` | Chọn làm nền mã nguồn chính |
| `lab5/.../src/Database.php` | Cấu hình `.local/db.json`, truy vấn `?`, `utf8mb4`, UTC, chế độ SQL nghiêm ngặt | Kế thừa và bổ sung hỗ trợ giao dịch, đường dẫn cấu hình kiểm thử |
| `lab5/.../src/RegistrationRepository.php` | Lưu đăng ký, kiểm tra đầu vào, chỉ nhận mã 1062 là trùng | Kế thừa kỹ thuật truy vấn và phân loại lỗi; thay mô hình đăng ký theo tài khoản |
| `lab5/.../database/schema.sql` | Hai bảng `events`, `registrations`; chưa có người dùng, kỹ năng hoặc phân công | Dùng làm điểm khởi đầu, bổ sung migration cho mô hình Đề tài 6 |
| `lab5/.../src/RegistrationStats.php` | Còn TODO, trả mảng rỗng | Phải triển khai thống kê thực; không nhận là tính năng đã có |
| `lab5/.../public/ui-demo.php`, `ticket-preview.php` | Khu trình diễn có dữ liệu hư cấu, vé và ghế thử | Không đưa bán vé hoặc ghế vào nghiệp vụ tình nguyện |
| `lab5/Ma_nguon_MySQL/LICENSE` | MIT, bản quyền FIT4111 Teaching Examples Contributors năm 2026 | Giữ nguyên giấy phép và thông báo bản quyền khi kế thừa |

Nhánh `main` không có tệp LICENSE ở cây gốc đã kiểm tra; không tự gán giấy phép MIT của `lab5` cho toàn bộ mã `main`. Phương án mặc định kế thừa mã từ `lab5/Ma_nguon_MySQL` có giấy phép rõ ràng, ghi nguồn đầy đủ. Không cần sao chép mã `main` để hoàn thành đề tài.

### 2.2. Cách tạo dự án kế thừa

Nếu thư mục làm việc đã có mã, trước tiên đọc `AGENTS.md`, chạy `pwd`, `git status`, xem cấu trúc và kiểm tra nguồn; không clone lồng hoặc ghi đè. Nếu bắt đầu từ thư mục mới, dùng nhánh MySQL của cùng kho:

```bash
git clone --branch lab5 https://github.com/FIT-DNU/Open-Source-Software-Development.git de-tai-6
cd de-tai-6
git rev-parse HEAD
git switch -c project/de-tai-6
cp -a Ma_nguon_MySQL app
```

Chỉ chạy `cp` khi `app` chưa tồn tại và nguồn là bản clone sạch, không chứa `.local` hay dữ liệu cá nhân. Nếu nguồn đã được cấu hình, loại trừ `.local`, log và dữ liệu riêng khi tạo bản làm việc. Giữ `Ma_nguon_MySQL` làm bản tham chiếu; mọi phát triển dự án nằm trong `app`. Không phải chép nguyên toàn bộ bài lab vào sản phẩm phát hành.

Lưu commit nguồn, danh sách tệp kế thừa, các thay đổi và giấy phép trong `docs/SOURCE_PROVENANCE.md` và `THIRD_PARTY_NOTICES.md`. Đọc lại mã thực tế trước khi sửa; các đường dẫn mới được đề xuất trong kế hoạch chưa tồn tại cho đến khi được tạo.

### 2.3. Cấu trúc dự kiến sau triển khai

| Đường dẫn | Nội dung |
| --- | --- |
| `AGENTS.md` | Quy tắc làm việc và thứ tự đọc ngữ cảnh |
| `KE_HOACH_VIBECODING_DE_TAI_6.md` | Tài liệu này, đặt ở gốc repository |
| `docs/REQUIREMENTS.md` | Ma trận yêu cầu và tiêu chí nghiệm thu |
| `docs/DECISIONS.md`, `docs/STATE.md` | Quyết định nghiệp vụ và trạng thái hiện tại |
| `docs/SESSION_LOG.md`, `docs/KNOWN_ISSUES.md` | Nhật ký phiên, lỗi còn tồn tại và cách tái hiện |
| `docs/SOURCE_PROVENANCE.md` | Nguồn, commit, tệp kế thừa, thay đổi |
| `docs/TEST_RESULTS.md`, `docs/DEMO_SCRIPT.md` | Kết quả thực tế và kịch bản trình diễn |
| `app/public/` | Điểm vào web; đăng nhập, dashboard, quản lý, lịch cá nhân, báo cáo |
| `app/public/assets/` | CSS, JavaScript nhỏ và tài nguyên giao diện |
| `app/src/` | Xác thực, phân quyền, kiểm tra dữ liệu, repository, nghiệp vụ |
| `app/src/Allocation/` | Kiểm tra điều kiện, thuật toán đối chứng, thuật toán cải tiến, đo chỉ số |
| `app/database/migrations/` | Thay đổi cơ sở dữ liệu có phiên bản |
| `app/database/fixtures/` | Ba bộ dữ liệu cố định và đáp án kiểm tra |
| `app/tests/` | Kiểm thử đơn vị, tích hợp, HTTP và hồi quy |
| `app/tools/` | Cấu hình, migrate, seed, chạy so sánh, kiểm tra tính hợp lệ |
| `app/.local/` | Cấu hình và log cục bộ; loại khỏi Git và bản phát hành |
| `artifacts/comparison/` | Snapshot, cấu hình thuật toán và kết quả so sánh dùng dữ liệu giả lập |
| `README.md`, `LICENSE`, `THIRD_PARTY_NOTICES.md` | Hướng dẫn cài, phạm vi giấy phép, nguồn sử dụng |

Giữ một kiến trúc PHP thống nhất theo bản MySQL. Không đồng thời tạo Laravel, React, Node hoặc hai lớp kết nối MySQLi và PDO cho cùng nhiệm vụ. Chỉ bổ sung phụ thuộc khi có nhu cầu rõ ràng, ghi cách cài lại.

## 3. Ma trận yêu cầu và nghiệm thu

| Mã | Yêu cầu từ đề | Sản phẩm cần có | Điều kiện nghiệm thu |
| --- | --- | --- | --- |
| F01 | Hồ sơ, kỹ năng, lịch rảnh, hoạt động, nhu cầu từng ca | Biểu mẫu và lưu dữ liệu liên kết | Sửa, tải lại còn dữ liệu; thời gian hợp lệ; ca thuộc đúng hoạt động |
| F02 | Đăng ký, rút đăng ký, xem ca được phân | Danh sách hoạt động và lịch cá nhân | Người dùng chỉ sửa đăng ký của mình; rút đúng chính sách; không trùng bản ghi |
| F03 | Đề xuất theo điều kiện, ưu tiên; nêu người hoặc ca chưa bố trí | Màn hình chạy thuật toán và lý do loại | Xét đủ kỹ năng, thời gian, số người; hiển thị tất cả ca và người chưa được bố trí |
| F04 | Điều phối sửa phân công vẫn kiểm tra điều kiện | Thêm, chuyển, gỡ, thay người | Máy chủ từ chối trùng lịch, thiếu kỹ năng, vượt chỗ; không chỉ khóa nút trên giao diện |
| F05 | Xác nhận, thay người vắng, thống kê, xuất danh sách | Xác nhận cá nhân, hàng đợi báo vắng, báo cáo và CSV | Thay thế hợp lệ; ca trống hiển thị đúng; tệp xuất khớp cơ sở dữ liệu |
| T01 | Xét đồng thời kỹ năng, thời gian và số người | Bộ kiểm tra điều kiện dùng chung | Cả chạy tự động và chỉnh tay đều qua cùng quy tắc |
| T02 | So sánh với thứ tự đăng ký trên cùng dữ liệu | Lệnh so sánh và trang kết quả | Có tỷ lệ ca đủ, số vi phạm, chênh lệch số ca; lưu cấu hình và phiên bản dữ liệu |
| D01 | 30 tình nguyện viên, 12 ca, 5 kỹ năng; 3 kịch bản | Ba fixture có phiên bản | Mỗi fixture có đúng các số lượng này; đủ, thiếu, trùng lịch có tính chất được kiểm chứng |
| S01 | Lưu đúng người, ca, kỹ năng, phiên bản đầu vào | Phương án và snapshot không đổi | Truy ngược được đầu vào đã dùng dù hồ sơ hiện tại đã sửa |
| S02 | Quyền điều phối và mọi thay đổi lịch kiểm tra ở máy chủ | Kiểm tra quyền, sở hữu, giao dịch | Gọi URL trực tiếp, sửa ID hoặc gửi POST giả cũng bị chặn |
| Q01 | Phép thử, tự động, tích hợp, hồi quy | Bộ kiểm thử và log chạy thật | Không bỏ qua lỗi; có dữ liệu và đáp án; sửa lỗi xong chạy lại ca liên quan |
| H01 | Giấy phép, nguồn, tài liệu, issue, review, bản phát hành | Bộ bàn giao và lịch sử công việc | Cài lại từ bản phát hành trên môi trường sạch thành công |
| C01 | Đóng góp và vấn đáp từng thành viên | Bảng đóng góp và bài luyện giải thích | Mỗi người nắm phần mình làm và sửa được một yêu cầu nhỏ |

## 4. Môi trường Ubuntu và kết nối MySQL Workbench

### 4.1. Vai trò của từng công cụ

- Ubuntu hoặc Ubuntu WSL chạy PHP, MySQL Server, Git và kiểm thử.
- Antigravity IDE mở đúng thư mục dự án trên Ubuntu; terminal dùng Bash Ubuntu. Sau khi mở lại IDE, kiểm tra `pwd`, `php -v`, `git status`, không mặc định phiên làm việc cũ vẫn còn.
- MySQL Server mới là nơi chạy và lưu cơ sở dữ liệu. MySQL Workbench là phần mềm kết nối để quản trị, xem bảng và chạy SQL.
- Trình duyệt Windows truy cập PHP đang chạy trong Ubuntu. Hai terminal Ubuntu: terminal 1 giữ máy chủ; terminal 2 chạy lệnh, SQL và kiểm thử.

### 4.2. Kiểm tra trước khi cài

```bash
pwd
uname -a
php -v
git --version
mysql --version
php -m
ss -ltn
```

Ghi thiếu công cụ nào rồi mới cài. Mục tiêu tương thích mã mẫu: PHP từ 8.1, MySQL từ 8.0.16, extension `mysqli` và `mysqlnd`. Kiểm tra phiên bản server bằng `SELECT VERSION()`; phiên bản client không chứng minh phiên bản server. Chọn phiên bản PHP và MySQL còn được hỗ trợ trên bản Ubuntu thực tế, không hạ phiên bản đang hoạt động chỉ để giống ví dụ.

Nếu chưa có công cụ:

```bash
sudo apt update
sudo apt install git unzip php-cli php-mysql mysql-server mysql-client
sudo service mysql start
```

Nếu máy dùng systemd, có thể quản lý bằng `systemctl`; chọn một cách phù hợp rồi ghi vào README. Nếu đã dùng MySQL Server trên Windows, xác định rõ có tiếp tục dùng server đó không trước khi cài thêm server Ubuntu. Phương án mặc định của kế hoạch là server trong Ubuntu.

### 4.3. Tạo cơ sở dữ liệu và tài khoản riêng

Kiểm tra tên `volunteer_app`, `volunteer_test` và tài khoản trước khi tạo. Không xóa hoặc đặt lại mật khẩu của tài khoản đang được dự án khác sử dụng. Dùng tài khoản quản trị chỉ khi khởi tạo schema hoặc migration; tài khoản chạy ứng dụng không cần quyền quản trị toàn server.

Ví dụ cho server Ubuntu mới, thao tác trong client quản trị cục bộ:

```bash
sudo env MYSQL_HISTFILE=/dev/null mysql
```

```sql
SELECT VERSION();
SHOW DATABASES LIKE 'volunteer_app';
SHOW DATABASES LIKE 'volunteer_test';
SELECT User, Host FROM mysql.user WHERE User IN ('volunteer_app','volunteer_test');
```

Nếu các tên chưa tồn tại:

```sql
CREATE DATABASE volunteer_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE volunteer_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'volunteer_app'@'127.0.0.1' IDENTIFIED BY '<MAT_KHAU_RIENG>';
CREATE USER 'volunteer_test'@'127.0.0.1' IDENTIFIED BY '<MAT_KHAU_TEST_RIENG>';
GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_app.* TO 'volunteer_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON volunteer_test.* TO 'volunteer_test'@'127.0.0.1';
```

Thay placeholder khi nhập cục bộ; không đưa mật khẩu thật vào Markdown hoặc Git. Migration được thực thi bằng tài khoản có quyền tạo/sửa bảng trong đúng schema. Nếu công cụ kiểm thử cần DDL, dùng cấu hình migration riêng; không cấp quyền xóa schema cho tài khoản web.

### 4.4. Cấu hình ứng dụng

Kế thừa cơ chế `.local/db.json` của mã mẫu. Công cụ cấu hình phải thử kết nối trước khi lưu, nhập mật khẩu ẩn, không ghi đè cấu hình tốt khi xác thực thất bại. Dùng host TCP rõ ràng và tệp cấu hình test riêng.

Ví dụ cấu trúc minh họa, không chứa bí mật thật:

```json
{
  "host": "127.0.0.1",
  "port": "3306",
  "database": "volunteer_app",
  "user": "volunteer_app",
  "password": "<NHAP_CUC_BO>"
}
```

Thêm `.local/` vào `.gitignore`; chỉ cung cấp `config.example.json` không có bí mật. Mỗi môi trường dùng đúng schema, kiểm thử phải từ chối chạy thao tác dọn dữ liệu nếu không phải `volunteer_test`.

### 4.5. Kết nối Workbench với đúng server

Tạo kết nối Workbench trên Windows: phương thức `Standard TCP/IP`, host `127.0.0.1`, cổng MySQL Ubuntu thực tế, người dùng được cấp quyền. Thử kết nối rồi chạy trên cả Workbench và client Ubuntu:

```sql
SELECT @@hostname, @@port, @@server_uuid, DATABASE(), CURRENT_USER(), VERSION();
```

So sánh `@@server_uuid` để chắc chắn hai bên vào cùng server, rồi chọn schema `volunteer_app`. Tránh nhầm với MySQL Windows hoặc XAMPP cùng cổng 3306.

Nếu localhost chưa hoạt động, kiểm tra dịch vụ, `ss -ltn`, mạng WSL và cổng đang được Windows sử dụng. Có thể dùng cổng riêng, ví dụ 3307, nếu cổng mặc định bị trùng; cập nhật cả PHP và Workbench. Không mặc định mở MySQL ra mọi địa chỉ hoặc cấp tài khoản host `%`. Nếu buộc kết nối trực tiếp qua IP WSL, cấu hình bind, firewall và host tài khoản theo địa chỉ thực tế, giới hạn trong máy phát triển; ghi lại thay đổi vì IP có thể đổi sau khởi động.

### 4.6. Chạy ứng dụng

Chỉ sau khi cấu hình và migrate thành công, tại gốc repository:

```bash
php -S 127.0.0.1:8106 -t app/public
```

Mở <http://127.0.0.1:8106>. Cổng 8106 dành cho dự án để tránh trùng các lab. Bổ sung lệnh `app/lab.sh run` tương đương khi tạo script dự án; không nói lệnh mới đã có sẵn trong mẫu.

## 5. Thiết kế nghiệp vụ và phân quyền

### 5.1. Quyền của hai vai trò

| Thao tác | Tình nguyện viên | Điều phối |
| --- | --- | --- |
| Hồ sơ, kỹ năng, lịch rảnh | Xem và sửa của mình | Quản lý phục vụ điều phối |
| Hoạt động và ca | Xem hoạt động được công bố | Tạo, sửa, đóng hoạt động và ca |
| Đăng ký, rút đăng ký | Thực hiện cho mình | Xem và hỗ trợ theo chính sách |
| Phương án đề xuất và phân công toàn hoạt động | Xem phần cá nhân đã công bố | Chạy, duyệt, chỉnh và công bố |
| Xác nhận hoặc báo vắng | Chỉ phân công của mình | Theo dõi, xử lý và thay người |
| Thống kê, xuất danh sách toàn hoạt động | Chỉ xem lịch cá nhân | Được phép |
| Chạy so sánh thuật toán, xem snapshot | Không | Được phép |

Mọi handler kiểm tra đăng nhập, quyền và quyền sở hữu dữ liệu tại máy chủ. Không nhận `user_id` hay `role` từ biểu mẫu làm căn cứ tin cậy. Phân công cá nhân lấy từ tài khoản đăng nhập.

### 5.2. Trạng thái cần tách biệt

- Đăng ký: `registered`, `withdrawn`. Tái đăng ký có thời điểm mới và lưu lịch sử để thứ tự đối chứng rõ ràng.
- Phương án: `draft`, `published`, `superseded`. Chỉ một phiên bản công bố hiện hành trong cùng phạm vi hoạt động.
- Phân công hiệu lực: `assigned`, `confirmed`. Không tính `absent`, `cancelled`, `replaced` vào số chỗ đã lấp.
- Yêu cầu thay người: `open`, `resolved`, `unfilled`; tách lý do và người xử lý.
- Dùng lịch sử sự kiện hoặc audit để giữ người cũ, người mới, lý do, thời điểm. Không xóa dấu vết người vắng khi thay.

Đề xuất chưa công bố chỉ là bản nháp; không tự chiếm lịch chính thức của tình nguyện viên. Khi công bố phải kiểm tra lại với phân công hiệu lực ở mọi hoạt động.

### 5.3. Một bộ quy tắc dùng cho mọi đường ghi

Một người chỉ được gán vào ca nếu: tài khoản hoạt động; đăng ký hoạt động còn hiệu lực; có đủ kỹ năng; lịch rảnh bao phủ toàn bộ ca; không có ca hiệu lực trùng giờ ở bất kỳ hoạt động nào; chưa được gán chính ca đó; ca còn chỗ; người đó không đang báo vắng cho ca này.

Kiểm tra giao nhau của hai khoảng thời gian:

```text
overlap(a,b) = a.start < b.end AND b.start < a.end
```

Chuẩn hóa các khoảng rảnh trùng hoặc tiếp giáp trước khi xét bao phủ. Không kết luận rảnh cả ca chỉ vì có giao nhau một phần. Giờ bắt đầu phải nhỏ hơn giờ kết thúc; nhu cầu là số nguyên dương; ca không có yêu cầu kỹ năng vẫn phải kiểm tra các điều kiện còn lại.

Nếu đổi giờ ca, giảm nhu cầu, xóa kỹ năng hoặc sửa lịch rảnh làm phân công hiện hành không hợp lệ, từ chối thay đổi và trả danh sách ảnh hưởng, hoặc yêu cầu điều phối xử lý qua giao dịch có tái phân công. Không âm thầm lưu thay đổi khiến dữ liệu vi phạm.

### 5.4. Chỉnh tay, thay người và truy cập đồng thời

Tạo một dịch vụ ghi phân công dùng chung; mọi thao tác tự động, chỉnh tay, xác nhận, báo vắng, rút đăng ký và cập nhật lịch có liên quan đều đi qua dịch vụ này.

Thiết kế giao dịch đơn giản cho quy mô bài tập: có một hàng khóa lịch toàn cục trong `schedule_guard`; lấy `SELECT ... FOR UPDATE` trước mọi thay đổi có thể ảnh hưởng lịch, rồi đọc lại dữ liệu hiện hành, kiểm tra điều kiện, ghi phân công, audit và tăng `schedule_revision`. Dùng InnoDB, cùng kết nối và cùng transaction. Cách khóa này ưu tiên dễ chứng minh đúng ở quy mô 30 người; có thể tách khóa theo người/ca sau nếu cần.

Khi lưu một phương án, so sánh revision đầu vào với revision hiện tại. Nếu dữ liệu thay đổi, trả `STALE_PLAN`, không ghi đè; tạo lại đề xuất. Mọi luồng sửa hồ sơ/kỹ năng/lịch rảnh/đăng ký ảnh hưởng phương án cũng phải tăng revision.

Nếu người cũ đã báo vắng và chưa có người thay hợp lệ, giữ trạng thái vắng, ghi rõ ca thiếu; không phục hồi người vắng để làm đẹp thống kê. Khi đã tìm người thay, trong một transaction: kiểm tra lại → thêm người mới → liên kết lịch sử thay → cập nhật trạng thái và thống kê. Gặp lỗi phải rollback, không để một nửa thay đổi được lưu.

## 6. Thiết kế dữ liệu đề xuất

### 6.1. Các bảng và quan hệ

| Bảng | Các trường chính và ý nghĩa |
| --- | --- |
| `users` | `id`, `username`, `password_hash VARCHAR(255)`, `role`, `active` |
| `volunteer_profiles` | `user_id` duy nhất, mã tình nguyện viên, tên và thông tin liên hệ giả lập |
| `skills` | `id`, `code` duy nhất, tên; 5 kỹ năng trong bộ thử |
| `volunteer_skills` | Khóa ghép `volunteer_id, skill_id` |
| `availability` | `id`, `volunteer_id`, `start_at`, `end_at` |
| `events` | Kế thừa và mở rộng hoạt động: tên, mô tả, địa điểm, trạng thái |
| `shifts` | `id`, `event_id`, tên ca, `start_at`, `end_at`, `required_count` |
| `shift_skills` | Khóa ghép `shift_id, skill_id` |
| `registrations` | Chuyển sang tài khoản: `event_id`, `volunteer_id`, trạng thái, `registered_at`; duy nhất theo cặp |
| `registration_history` | Các lần đăng ký/rút/tái đăng ký và thời điểm tương ứng |
| `dataset_versions` | Mã bộ dữ liệu, phiên bản, SHA-256, seed, thời điểm và đường dẫn snapshot |
| `allocation_runs` | Thuật toán, phiên bản mã, cấu hình JSON, dataset version, revision, phạm vi, trạng thái và tác giả |
| `run_assignments` | Các cặp ca–người của kết quả thử/đề xuất, liên kết `run_id`; không tự tạo phân công công bố |
| `assignments` | Sổ phân công nghiệp vụ và trạng thái; liên kết run nguồn nếu có; có phiên bản để phát hiện sửa cạnh tranh |
| `replacement_requests` | Phân công vắng, lý do, trạng thái, phân công thay thế và người xử lý |
| `audit_logs` | Người thực hiện, hành động, đối tượng, giá trị trước/sau cần thiết, thời điểm |
| `schedule_guard` | Một hàng khóa và `schedule_revision` phục vụ giao dịch lịch |
| `schema_migrations` | Phiên bản migration đã áp dụng |

Khóa ngoại phải giữ liên kết đúng người, ca và hoạt động. Dùng `CHECK(start_at < end_at)`, `CHECK(required_count > 0)` và unique phù hợp. Không xóa cascade làm mất snapshot hoặc lịch sử; ưu tiên đóng/ngừng hoạt động với đối tượng đã được sử dụng.

Để cho phép giữ lịch sử người rời ca rồi được gán lại mà vẫn chống phân công trùng hiệu lực, thiết kế một bản ghi hiện hành duy nhất cho cặp ca–người, lịch sử ở `audit_logs`; hoặc dùng khóa duy nhất có cột hiệu lực sinh tự động. Chọn một cách, viết migration và test cụ thể. Không đặt unique thiếu cân nhắc lên toàn lịch sử rồi gặp lỗi khi tái phân công.

Ràng buộc SQL không tự giải quyết trùng khoảng thời gian hoặc tổng số người của ca; phải có bộ kiểm tra và khóa giao dịch ở phần nghiệp vụ.

### 6.2. Migration từ bản mẫu

Giữ schema nguồn để tham chiếu. Tạo migration rõ ràng cho schema dự án; ánh xạ `events` sang hoạt động, thay đăng ký tên/email thành đăng ký gắn tài khoản. Dữ liệu lab cũ không tự chuyển thành tình nguyện viên giả. Nếu cần nhập dữ liệu cũ, viết công cụ chuyển riêng, có báo cáo trường chưa ánh xạ.

Seed chỉ nạp vào schema mới hoặc schema thử nghiệm xác định; không chạy reset tùy tiện. Migration phải chạy lại an toàn theo bảng phiên bản và báo lỗi khi cấu trúc không khớp. Ghi trong README thứ tự tạo schema → migrate → seed → cấu hình → chạy.

### 6.3. Snapshot phục vụ thuật toán và chấm điểm

Snapshot JSON phải chứa danh sách người, kỹ năng và quan hệ kỹ năng, lịch rảnh, đăng ký và thứ tự, hoạt động, ca, nhu cầu, yêu cầu kỹ năng, phân công đã khóa từ phạm vi khác, múi giờ và quy tắc nghiệp vụ.

Sắp xếp khóa và danh sách theo ID trước khi tính hash; lưu byte snapshot đã dùng, không chỉ lưu đường dẫn đến dữ liệu có thể bị thay. Mỗi lần chạy lưu mã thuật toán, commit dự án, cấu hình, hash snapshot, kết quả, nguyên nhân chưa phân công và chỉ số. Không dựng lại đầu vào cũ bằng cách join hồ sơ hiện tại đã thay đổi.

## 7. Thuật toán và cách so sánh

### 7.1. Bộ kiểm tra điều kiện dùng chung

Đề xuất tách các thành phần PHP:

- `EligibilityChecker`: đánh giá kỹ năng, bao phủ thời gian, đăng ký và trạng thái.
- `ScheduleValidator`: phát hiện trùng ca, trùng cặp, vượt số người và phân công không hợp lệ.
- `RegistrationOrderAllocator`: phương án đối chứng.
- `ScarcityBalancedAllocator`: phương án cải tiến.
- `AllocationService`: lưu/công bố có kiểm tra revision và transaction.
- `ComparisonService`: chạy trên snapshot giống nhau và xuất kết quả.

Tên lớp là thiết kế đề xuất. Hai thuật toán tính trên dữ liệu đầu vào, không tự ghi database trong vòng lặp. Kết quả gồm danh sách phân công, ca thiếu, người chưa được bố trí, lý do, cấu hình và chỉ số.

### 7.2. Phương án đối chứng theo thứ tự đăng ký

Định nghĩa đối chứng an toàn và tái lập được:

1. Sắp ca theo `start_at`, rồi `shift_id`.
2. Với từng ca, lấy người đăng ký còn hiệu lực theo `registered_at`, rồi `volunteer_id`.
3. Gán người đầu tiên hợp lệ, cập nhật lịch tạm thời, đến khi đủ người hoặc hết ứng viên.
4. Vẫn kiểm tra mọi ràng buộc như phương án cải tiến. Không cố tình tạo vi phạm để làm đối chứng kém đi.

Nếu giảng viên yêu cầu thêm đối chứng gán mù không kiểm tra điều kiện, chạy riêng như phương án thứ ba và gắn nhãn rõ; kết quả đó không được công bố làm lịch thật.

### 7.3. Phương án cải tiến ưu tiên ca khan hiếm và cân bằng số ca

Mục tiêu theo thứ tự: giữ các ràng buộc cứng → tăng số ca đủ người → tăng số vị trí được lấp → giảm chênh lệch số ca. Không cam kết luôn tốt hơn đối chứng.

Quy trình:

1. Khởi tạo từ snapshot, giữ phân công cố định ngoài phạm vi đang tối ưu. Nếu lập lại cả hoạt động, loại bản công bố cũ của hoạt động đó khỏi phần cố định, chỉ thay nó khi công bố mới thành công.
2. Xác định ứng viên hợp lệ ban đầu cho từng ca. Ca không có ứng viên phải có lý do cụ thể.
3. Ở mỗi bước, chọn ca còn thiếu có tỷ số ứng viên hiện có/số chỗ còn thiếu nhỏ nhất; hòa thì chọn số ứng viên ít hơn, giờ bắt đầu sớm hơn, ID nhỏ hơn. Cập nhật lại số ứng viên khi đã gán người.
4. Trong ca đó, chọn người theo số ca đang được gán ít nhất, số cơ hội tham gia ca khác ít nhất, thời điểm đăng ký sớm nhất, rồi ID. Số ca phải tính cả phân công cố định trong khoảng đánh giá đã xác định.
5. Gán một người, cập nhật lịch và tập ứng viên. Ca không còn ứng viên được ghi nhận tạm thời là thiếu; không lặp vô hạn trên cùng ca.
6. Thử sửa cục bộ có giới hạn: tìm người B thay người A ở một ca đang có A, để A chuyển sang ca thiếu; chỉ giữ thay đổi nếu mọi điều kiện hợp lệ và mục tiêu được cải thiện theo thứ tự trên. Giới hạn mặc định 100 lượt, không vượt quá 2 lần chuyển trong một phép thử; có thể cấu hình và lưu lại.
7. Kiểm định độc lập toàn bộ kết quả. Có vi phạm thì không cho lưu công bố.

```text
allocate(snapshot, config):
    validateInput(snapshot)
    schedule = fixedAssignments(snapshot)
    pending = allTargetShifts(snapshot)
    while pending is not empty:
        s = chooseMostConstrainedShift(pending, schedule)
        candidates = eligibleCandidates(s, snapshot, schedule)
        if candidates is empty:
            markUnfilled(s)
            remove s from pending
            continue
        v = chooseLeastLoadedAndLeastFlexible(candidates, schedule)
        addAssignment(schedule, s, v)
        if s is full: remove s from pending
    schedule = boundedRepair(schedule, snapshot, config)
    return validateAndExplain(schedule, snapshot)
```

Tính xác định là mặc định; nếu thêm ngẫu nhiên, seed phải cố định trong cấu hình. Ghi giới hạn thuật toán tham lam và bước sửa: không bảo đảm tối ưu, có thể còn ca thiếu dù tồn tại cách sắp khác.

### 7.4. Chỉ số so sánh bắt buộc

Gọi `S` là toàn bộ 12 ca mục tiêu, `V` là toàn bộ 30 người, `d(s)` là số người cần và `a(s)` là số người phân công hiệu lực trong kết quả.

| Chỉ số | Cách tính | Lưu ý |
| --- | --- | --- |
| Tỷ lệ ca đủ người | `100 × số ca có a(s) = d(s) / số ca` | Với dữ liệu hợp lệ không được có a(s) lớn hơn d(s); ca 0 người vẫn nằm trong mẫu số |
| Số vi phạm | Tổng chi tiết các nhóm kiểm tra độc lập | Báo từng nhóm: trùng lịch, thiếu kỹ năng, không rảnh, quá chỗ, chưa đăng ký, trùng cặp |
| Chênh lệch số ca | `max(c(v)) - min(c(v))` | Tính trên cả 30 người, kể cả người có 0 ca; cùng khoảng đánh giá và phân công cố định |
| Tỷ lệ lấp vị trí bổ sung | `100 × tổng min(a(s),d(s)) / tổng d(s)` | Chỉ số giải thích thêm, không thay tỷ lệ ca đủ |
| Ca thiếu và số người còn thiếu | Với mỗi ca: `max(0,d(s)-a(s))` | Hiển thị tên ca, thời gian, kỹ năng và nguyên nhân |

Quy ước đếm vi phạm: mỗi cặp ca trùng của một người tính một; mỗi cặp ca–người thiếu kỹ năng tính một dù thiếu nhiều kỹ năng, đồng thời lưu danh sách kỹ năng thiếu; mỗi ca vượt chỗ tính một và báo số người vượt. Một phân công có thể vi phạm nhiều nhóm nên ghi rõ tổng là tổng theo nhóm. Nếu không có ca, tỷ lệ là `N/A`, không chia cho 0.

Hai phương án hợp lệ thường đều có 0 vi phạm. Khi đó phân tích tỷ lệ đủ người và phân bố số ca, không xem “0 vi phạm ở cả hai” là lỗi thí nghiệm.

### 7.5. Quy trình thí nghiệm công bằng

1. Đóng băng từng fixture và hash một lần. Cả hai thuật toán đọc đúng byte snapshot đó.
2. Khởi tạo lịch tạm độc lập cho mỗi lần chạy; không dùng kết quả thuật toán thứ nhất làm đầu vào thuật toán thứ hai.
3. Dùng cùng điều kiện hợp lệ, phạm vi, phân công cố định và cách tính chỉ số. Chỉ khác thứ tự chọn ca/người và bước sửa.
4. Lưu cấu hình JSON riêng, gồm thứ tự hòa, mục tiêu, giới hạn sửa, seed và phiên bản thuật toán.
5. Chạy cả hai trên đủ 3 fixture, ít nhất 6 kết quả; nếu có ngẫu nhiên phải ghi số lần chạy và toàn bộ seed.
6. Kiểm định bằng validator độc lập, xuất chi tiết CSV/JSON và bảng tổng hợp. Kết quả thí nghiệm không tự tác động lịch đang công bố.
7. Chỉ điền số đo sau khi chạy thật. Báo cả trường hợp bằng nhau hoặc cải tiến kém hơn và giải thích nguyên nhân.

## 8. Thiết kế ba bộ dữ liệu và đáp án

Mỗi bộ có đúng 30 tình nguyện viên, 12 ca, 5 kỹ năng; người điều phối là tài khoản bổ sung, không được tính vào 30 tình nguyện viên. Các tên và liên hệ đều giả lập.

| Bộ | Thiết kế | Đáp án và tính chất cần chứng minh |
| --- | --- | --- |
| `DU_NGUOI_v1` | 12 ca, mặc định 3 người/ca, đủ lịch rảnh và kỹ năng | Có một phương án chứng kiến điền đủ 36 vị trí, validator xác nhận không vi phạm |
| `THIEU_NGUOI_v1` | Giữ 30/12/5; bố trí một ca cần 3 người nhưng chỉ có đúng 1 người đăng ký vừa đủ kỹ năng và thời gian | Ca đó không thể đủ; xuất thiếu ít nhất 2 người, không tạo thêm tài khoản để lấp |
| `TRUNG_LICH_v1` | Giữ 30/12/5; nhiều ca giao nhau, có trùng giữa hoạt động, kỹ năng khan hiếm | Có danh sách cặp trùng dự kiến và kiểm tra mọi kết quả hợp lệ không gán trùng |

Gợi ý 5 kỹ năng: sơ cứu, hậu cần, truyền thông, hướng dẫn, công nghệ thông tin. Đây là dữ liệu mẫu, có thể đổi tên nhưng giữ đúng số lượng và các tình huống kiểm chứng.

Tệp fixture phải có phiên bản, múi giờ, ngày cụ thể, ID ổn định, seed sinh dữ liệu nếu có, mô tả mục đích và đáp án kiểm tra. Tránh dùng `now()` khiến dữ liệu đổi mỗi lần chạy. Kiểm tra tự động đúng 30/12/5, thời gian hợp lệ, đăng ký tồn tại, tính chất đủ/thiếu/trùng và snapshot tái lập được.

Ví dụ nhỏ để giải thích ưu tiên khan hiếm: A có sơ cứu và hậu cần; B chỉ có hậu cần. Hai ca cùng giờ, mỗi ca cần một người: ca 1 cần hậu cần, ca 2 cần sơ cứu. A đăng ký trước B. Đối chứng có thể gán A vào ca 1 rồi thiếu ca 2; cải tiến xét ca sơ cứu trước, gán A vào ca 2 và B vào ca 1. Đây là ví dụ minh họa thuật toán, không thay bộ dữ liệu 30 người và không phải số đo dự án.

## 9. Giao diện và luồng sử dụng

Giao diện tiếng Việt, responsive bằng HTML/CSS và các thành phần đã chọn từ mã nền. Ưu tiên nhãn rõ, thông báo nguyên nhân, dữ liệu thực từ MySQL và trạng thái rỗng; không cần trang trí làm tăng phụ thuộc.

| Màn hình | Nội dung chính |
| --- | --- |
| Đăng nhập | Tài khoản thử, lỗi chung, chuyển theo vai trò |
| Dashboard điều phối | Hoạt động, tổng ca, ca đủ/thiếu, số đã xác nhận, yêu cầu báo vắng |
| Hồ sơ và kỹ năng | Danh sách, chi tiết, kỹ năng gắn người; lịch rảnh có ngày giờ |
| Hoạt động và ca | Nhu cầu từng ca, yêu cầu kỹ năng, trạng thái công bố |
| Đăng ký hoạt động | Danh sách, đăng ký/rút, tình trạng và thông báo liên quan phân công |
| Đề xuất phân công | Chọn phạm vi, chạy, xem lý do chưa gán, lưu nháp và công bố |
| Chỉnh phân công | Ứng viên, lý do không hợp lệ, kiểm tra sau POST |
| Ca của tôi | Ngày giờ, hoạt động, xác nhận, báo vắng và lịch sử |
| Thay người | Người vắng, người thay hợp lệ, trạng thái chưa tìm được người |
| Báo cáo và so sánh | Chỉ số thực tế, cấu hình, dataset version, xuất CSV/JSON |

Không hiển thị dữ liệu demo vé/ghế như dữ liệu nghiệp vụ. Kiểm tra menu, đường dẫn, thông báo, navbar trên màn hình nhỏ và các thao tác tải lại. Tài nguyên cần cho bản phát hành nên có bản cục bộ kèm nguồn/giấy phép để chạy lại không phụ thuộc CDN.

## 10. Kế hoạch triển khai theo giai đoạn

Thứ tự dưới đây là thứ tự phụ thuộc, không phải cam kết ngày hoàn thành. Chưa có hạn nộp hoặc số thành viên nên không gán thời lượng cứng. Sau G00, AI ước lượng theo hiện trạng và chia phần việc đủ nhỏ để kiểm tra trong mỗi phiên.

### G00 Khảo sát dự án và tạo ngữ cảnh

**Thực hiện:** đọc đề, tài liệu này, `AGENTS.md` hiện có; xem git status; kiểm tra Ubuntu/PHP/MySQL/IDE; đối chiếu nhánh nguồn. Nếu thư mục trống, tạo bản kế thừa theo mục 2.2. Tạo `docs/REQUIREMENTS.md`, `SOURCE_PROVENANCE.md`, `DECISIONS.md`, `STATE.md` và nhật ký.

**Đầu ra:** danh sách hiện có/thiếu, cấu trúc app, nguồn và commit, ma trận yêu cầu. Ghi rõ chưa kiểm thử phần nào.

**Nghiệm thu:** không mất mã người dùng; chọn đúng MySQL; mọi yêu cầu F01–C01 có nơi theo dõi; có bước tiếp theo cụ thể.

### G01 Chạy được nền mã và kết nối cơ sở dữ liệu

**Thực hiện:** cấu hình riêng, kiểm tra PHP extension, kết nối server, xác định Workbench vào cùng server. Chạy bản lab tham chiếu theo hướng dẫn gốc trên schema lab riêng nếu cần kiểm tra nền; không nạp dữ liệu lab vào `volunteer_app`.

**Đầu ra:** hướng dẫn môi trường thực tế; cấu hình mẫu không bí mật; lệnh check và run của app. Từ đây, mở terminal thứ hai để kiểm tra khi server đang chạy.

**Nghiệm thu:** trang nền chạy, PHP đọc/ghi được schema đúng, Workbench xác nhận cùng server; lỗi kết nối được báo rõ, không giả thành dữ liệu rỗng.

### G02 Schema, migration và fixture

**Thực hiện:** tạo mô hình mục 6; chuyển schema đăng ký; tạo dữ liệu 30/12/5 cho cả 3 kịch bản; thêm snapshot/hash; kiểm tra ràng buộc và phương án chứng kiến bộ đủ người.

**Đầu ra:** migration, công cụ seed, fixture và đáp án; sơ đồ hoặc bảng quan hệ; tài khoản điều phối và tình nguyện viên thử.

**Nghiệm thu:** cài mới được, migrate lần hai không nhân bản; đủ số lượng; lỗi khóa ngoại/CHECK không bị nuốt; test dùng schema riêng.

### G03 Đăng nhập, vai trò và bảo vệ thao tác

**Thực hiện:** dùng password hash, tái tạo session ID sau đăng nhập, kiểm tra vai trò/sở hữu tại handler, token chống giả mạo yêu cầu, POST cho mọi thay đổi, escape HTML. Seed tài khoản thử bằng hash.

**Đầu ra:** đăng nhập/đăng xuất, middleware/hàm kiểm tra quyền và bài thử HTTP cho truy cập trái phép.

**Nghiệm thu:** tình nguyện viên không gọi được hành động điều phối, không sửa dữ liệu người khác; POST thiếu token bị từ chối; không còn so sánh mật khẩu rõ.

### G04 Quản lý hồ sơ, kỹ năng, lịch rảnh, hoạt động và ca

**Thực hiện:** hoàn thiện F01 từ dữ liệu đến giao diện; tạo ca nhiều kỹ năng; kiểm tra giờ, nhu cầu, liên kết và chính sách sửa làm ảnh hưởng lịch.

**Đầu ra:** các trang quản lý và repository tương ứng; dashboard đọc số thực.

**Nghiệm thu:** thêm/sửa rồi tải lại đúng; không nhận ca giờ đảo hoặc nhu cầu âm; xóa/sửa đối tượng đang dùng có xử lý rõ; người dùng nhập HTML không làm trang thực thi script.

### G05 Đăng ký, rút đăng ký và xem lịch cá nhân

**Thực hiện:** hoàn thiện F02; chống trùng; lưu thứ tự đăng ký và lịch sử tái đăng ký; rút đăng ký theo chính sách; trang ca cá nhân chỉ đọc phân công đã công bố.

**Đầu ra:** luồng hoạt động → đăng ký → lịch cá nhân; kiểm thử sở hữu và lưu bền vững.

**Nghiệm thu:** khởi động lại PHP vẫn còn dữ liệu; không tạo đăng ký đôi; rút không để phân công hiệu lực sai điều kiện.

### G06 Bộ quy tắc và thuật toán đối chứng

**Thực hiện:** viết EligibilityChecker và validator độc lập; kiểm thử thời gian, kỹ năng, chỗ, đăng ký và trùng giữa hoạt động; viết RegistrationOrderAllocator trên snapshot.

**Đầu ra:** thuật toán xác định, cấu hình lưu được, báo cáo ca thiếu và người chưa bố trí.

**Nghiệm thu:** đối chứng giữ mọi ràng buộc; cùng snapshot cho cùng kết quả; đếm đủ cả ca không có người và người 0 ca.

### G07 Thuật toán cải tiến và lưu phương án

**Thực hiện:** ưu tiên ca khan hiếm, cân bằng, sửa cục bộ có giới hạn; nối giao diện xem nháp và công bố; kiểm tra revision, giao dịch và sổ phân công.

**Đầu ra:** chạy được F03, có giải thích lý do, snapshot và cấu hình, công bố an toàn.

**Nghiệm thu:** ví dụ nhỏ mục 8 cho đúng hành vi mong đợi; đầu vào thay đổi thì từ chối phương án cũ; kết quả vi phạm không được công bố.

### G08 Chỉnh tay, xác nhận, báo vắng và thay người

**Thực hiện:** F04 và phần xác nhận/thay của F05; tái dùng quy tắc và giao dịch; nhật ký thay đổi; xử lý không có người thay.

**Đầu ra:** hoàn chỉnh cả hai tình huống sử dụng của đề.

**Nghiệm thu:** sửa làm vượt chỗ, thiếu kỹ năng, trùng lịch đều bị chặn; báo vắng làm thống kê thiếu đúng; thay hợp lệ cập nhật đúng; giao dịch lỗi không lưu dở dang.

### G09 Báo cáo, xuất danh sách và thí nghiệm so sánh

**Thực hiện:** thống kê qua LEFT JOIN để giữ ca 0 người; tách số đã phân, xác nhận, thiếu; xuất CSV có tiếng Việt và xử lý ô bắt đầu bằng ký tự công thức; chạy hai thuật toán trên 3 snapshot.

**Đầu ra:** bảng so sánh ít nhất 6 lượt chạy, CSV/JSON, cấu hình, hash dữ liệu, phiên bản mã và nhận xét từ số thực.

**Nghiệm thu:** kiểm tra chéo số liệu với SQL; cùng đầu vào; báo đủ 3 chỉ số bắt buộc; chạy so sánh không thay đổi lịch công bố.

### G10 Kiểm thử tích hợp, cạnh tranh và hồi quy

**Thực hiện:** hoàn thành toàn bộ mục 11; thực hiện luồng web bằng hai vai trò; hai request tranh chỗ; đổi dữ liệu sau khi tạo nháp; khởi động lại; sửa lỗi và chạy lại phần bị ảnh hưởng.

**Đầu ra:** log có lệnh, thời điểm, môi trường, số test, mã thoát; issue đã sửa; trạng thái rõ phần chưa chạy.

**Nghiệm thu:** đủ 5 phép thử bắt buộc và kiểm thử quyền máy chủ; không có `SKIP`/`NOT_RUN` được ghi là PASS; không sửa đáp án để hợp thức hóa lỗi.

### G11 Bàn giao và chuẩn bị trình diễn

**Thực hiện:** hoàn thiện README cài lại, nguồn/giấy phép, issue/review, phân công đóng góp, kịch bản demo; tạo bản phát hành có phiên bản và thử cài từ bản đó trên schema mới.

**Đầu ra:** mã nguồn, migration, fixture, tài liệu, kết quả thử và so sánh, hướng dẫn chạy; không có bí mật hoặc log cá nhân.

**Nghiệm thu:** người khác làm theo README chạy lại được; trình diễn đủ hai tình huống; mỗi thành viên giải thích được thuật toán và sửa một yêu cầu nhỏ.

## 11. Danh sách kiểm thử cần triển khai

Các phép thử cốt lõi cần tự động vì liên quan trực tiếp thuật toán, dữ liệu và điểm chấm. Có thể dùng runner PHP nhẹ kế thừa từ mẫu hoặc PHPUnit nếu nhóm đã dùng; chọn một, tài liệu hóa. Kiểm thử giao diện thủ công bổ sung không thay kiểm thử nghiệp vụ.

| Mã | Dữ liệu hoặc thao tác | Kết quả phải kiểm chứng |
| --- | --- | --- |
| B01 bắt buộc | Một người có ca 08:00–10:00, gán thêm 09:00–11:00 | Từ chối; không thêm bản ghi hiệu lực |
| B02 bắt buộc | Ca yêu cầu sơ cứu, người không có sơ cứu | Từ chối; nêu kỹ năng thiếu |
| B03 bắt buộc | Ca cần 2 người đã có 2; thêm người thứ 3 bằng POST | Từ chối tại máy chủ; vẫn có đúng 2 người |
| B04 bắt buộc | Chạy bộ thiếu người | Báo chính xác ca và số thiếu; giữ đúng 30 tình nguyện viên |
| B05 bắt buộc | So sánh hai thuật toán | Cùng hash đầu vào; mỗi run lưu cấu hình và phiên bản riêng |
| U01 | Ca 08:00–10:00 và ca 10:00–12:00 | Có thể gán cùng người nếu các điều kiện khác đạt |
| U02 | Rảnh 08:00–09:00, ca 08:00–10:00 | Không hợp lệ vì không bao phủ cả ca |
| U03 | Hai khoảng rảnh tiếp giáp phủ cả ca | Chuẩn hóa rồi đánh giá nhất quán với quyết định mục 5 |
| U04 | Ca cần hai kỹ năng, người chỉ có một | Không hợp lệ theo quy tắc đủ tất cả kỹ năng |
| U05 | Ca không yêu cầu kỹ năng | Không tự loại vì danh sách kỹ năng rỗng; vẫn kiểm tra thời gian/chỗ |
| U06 | Một người đăng ký hai hoạt động có ca giao nhau | Không được công bố cả hai phân công trùng |
| U07 | Một người không có ca, một ca không có ai | Vẫn xuất hiện trong thống kê chênh lệch và mẫu số ca đủ |
| U08 | Gọi thuật toán lặp trên một snapshot | Kết quả, lý do và chỉ số tái lập được |
| U09 | Ví dụ A/B và hai kỹ năng ở mục 8 | Cải tiến đạt đủ 2 ca, đối chứng theo cấu hình đạt 1 ca |
| I01 | Đăng ký cùng hoạt động hai lần | Một đăng ký hiện hành, thông báo phù hợp |
| I02 | Rút đăng ký khi còn phân công | Từ chối hoặc xử lý trọn giao dịch theo chính sách; không để trạng thái sai |
| I03 | Đổi giờ, lịch rảnh hoặc kỹ năng gây xung đột | Bị chặn hoặc tái phân công hợp lệ; tăng revision |
| I04 | Công bố nháp sau khi dữ liệu đã sửa | `STALE_PLAN`, không đè lịch mới |
| I05 | Hai request đồng thời tranh một chỗ cuối | Chỉ một request thành công; số người không vượt nhu cầu |
| I06 | Hai request gán cùng người vào ca trùng | Chỉ một phương án hợp lệ được lưu |
| I07 | Báo vắng, không có người thay | Ca thiếu rõ ràng; người vắng không bị tính là đang làm |
| I08 | Thay người hợp lệ, rồi cố gây lỗi giữa transaction | Trường hợp đúng lưu đủ lịch sử; trường hợp lỗi rollback toàn bộ |
| I09 | Khởi động lại PHP và mở Workbench | Dữ liệu đã ghi vẫn tồn tại và khớp giao diện |
| I10 | Mất kết nối khi lưu | Không báo thành công; không biến lỗi thành “đã đủ người” hay danh sách rỗng |
| S01 | Tình nguyện viên gọi handler điều phối trực tiếp | HTTP 403 hoặc cơ chế từ chối tương đương, dữ liệu không đổi |
| S02 | Sửa ID phân công/hồ sơ của người khác | Từ chối theo quyền sở hữu |
| S03 | POST thiếu/sai token chống giả mạo | Từ chối, không thay đổi dữ liệu |
| S04 | Tên có dấu nháy, HTML và chuỗi giống SQL | Lưu dữ liệu hợp lệ, hiển thị an toàn, cấu trúc SQL không thay đổi |
| S05 | Xuất CSV với chuỗi bắt đầu bằng `=`, `+`, `-`, `@` | Không thực thi như công thức khi mở bằng phần mềm bảng tính |
| R01 | Sửa lỗi thuật toán hoặc handler | Test tái hiện lỗi và các luồng cũ liên quan đều được chạy lại |
| H01 | Cài từ bản phát hành vào schema trống | Migrate, seed, đăng nhập và hai tình huống đều chạy được |

Các lệnh dự kiến cần được AI tạo và kiểm chứng trước khi ghi là dùng được:

```bash
cd app
bash lab.sh check
bash lab.sh test
bash lab.sh extend
php tools/compare.php --dataset DU_NGUOI_v1
php tools/compare.php --dataset THIEU_NGUOI_v1
php tools/compare.php --dataset TRUNG_LICH_v1
```

`check` chỉ kiểm tra môi trường; `test` chạy nhóm nền và tích hợp; `extend` chạy nghiệp vụ phân công của dự án. Cấu hình so sánh cần tùy chọn chạy thuần fixture không ghi cơ sở dữ liệu nghiệp vụ. Quét cú pháp PHP bằng `find ... -print0` kết hợp `xargs -0` hoặc script tương đương để xử lý đúng đường dẫn.

## 12. Quy trình làm việc với AI trong Antigravity IDE

### 12.1. Prompt mở đầu dự án

Dán prompt sau sau khi đặt tài liệu này và tệp đề bài trong thư mục dự án:

```text
Bạn là AI lập trình trực tiếp trong Antigravity IDE đã kết nối Ubuntu.
Hãy thực hiện dự án Đề tài 6: hệ thống tổ chức hoạt động và phân công tình nguyện viên.

Đọc toàn bộ AGENTS.md nếu có, KE_HOACH_VIBECODING_DE_TAI_6.md và tệp đề bài.
Nếu không đọc được DOCX, dùng bản chép đầy đủ ở Phụ lục A, ghi rõ nguồn đã dùng.
Kiểm tra thư mục hiện tại, git status, mã nguồn hiện có và môi trường thực tế trước khi sửa.

Phải kế thừa kho FIT-DNU/Open-Source-Software-Development theo mục 2 của kế hoạch,
ưu tiên lab5/Ma_nguon_MySQL có giấy phép MIT và ghi đúng commit nguồn.
Ngôn ngữ PHP, MySQLi và MySQL Server; Ubuntu chạy ứng dụng;
MySQL Workbench quản trị cùng server; không tự chuyển framework hoặc hệ quản trị.

Bắt đầu G00, rồi thực hiện các giai đoạn theo phụ thuộc.
Hãy viết và chạy mã, không chỉ trả lời kế hoạch hoặc các đoạn code rời.
Hoàn tất phần được giao, tự sửa lỗi trong phạm vi và xác minh bằng kết quả thực tế.
Không giả dữ liệu thống kê, không tự nhận test đạt khi chưa chạy.
Không bỏ kiểm tra quyền, kỹ năng, thời gian, số người ở máy chủ.
Không ghi đè mã hiện có, xóa cơ sở dữ liệu hoặc đưa mật khẩu vào Git.

Trước khi sửa, nói ngắn gọn phạm vi và tiêu chí đạt.
Cuối mỗi giai đoạn cập nhật docs/STATE.md, SESSION_LOG.md, TEST_RESULTS.md
và ma trận yêu cầu; báo đã làm, đã kiểm tra, còn lỗi gì và bước kế tiếp.
Chỉ hỏi thông tin thật sự thiếu làm chặn công việc; những lựa chọn đã có trong kế hoạch
thì tự thực hiện. Nếu cần thao tác sudo hoặc nhập mật khẩu tương tác, chuẩn bị đầy đủ
lệnh và hướng dẫn tôi thao tác rồi tiếp tục xác minh kết quả.
```

### 12.2. Prompt giao từng giai đoạn

Dùng cùng khung sau, thay mã giai đoạn theo bảng:

```text
Đọc AGENTS.md, KE_HOACH_VIBECODING_DE_TAI_6.md, docs/STATE.md,
docs/DECISIONS.md, docs/KNOWN_ISSUES.md và xem git status.
Thực hiện trọn giai đoạn [Gxx] theo kế hoạch, kế thừa mã đã có.
Nếu phần này đã làm một phần, kiểm tra và bổ sung, không viết lại toàn bộ.
Triển khai cả dữ liệu, nghiệp vụ, giao diện cần thiết và kiểm thử có ý nghĩa.
Chạy tiêu chí nghiệm thu của giai đoạn, sửa lỗi, kiểm tra lại luồng cũ bị ảnh hưởng.
Cập nhật trạng thái từng yêu cầu và ghi lệnh/kết quả thực tế trước khi kết thúc.
Không báo hoàn thành nếu mới có giao diện hoặc dữ liệu giả trên trình duyệt.
```

| Lượt giao | Nội dung nhấn mạnh thêm |
| --- | --- |
| G00 | Khảo sát và tạo bộ nhớ dự án, xác định đúng nguồn và nhánh |
| G01 | Chứng minh PHP và Workbench vào cùng MySQL Server |
| G02 | Hoàn thành schema và 3 fixture đúng 30/12/5 cùng đáp án |
| G03 | Quyền máy chủ, sở hữu dữ liệu, password hash, session, POST và token |
| G04 | Hồ sơ, kỹ năng, lịch rảnh, hoạt động, ca; dữ liệu lưu thật |
| G05 | Đăng ký/rút/xem ca; lưu đúng thứ tự đăng ký |
| G06 | Bộ quy tắc dùng chung và đối chứng hợp lệ theo thứ tự đăng ký |
| G07 | Thuật toán khan hiếm/cân bằng, nháp, revision và công bố |
| G08 | Chỉnh tay, xác nhận, vắng/thay; xử lý thiếu và giao dịch |
| G09 | Chỉ số bắt buộc, 6 lượt so sánh cùng đầu vào, xuất danh sách |
| G10 | Đủ test bắt buộc, tích hợp HTTP, cạnh tranh và hồi quy |
| G11 | Bản phát hành cài lại được, tài liệu, nguồn và đóng góp |

### 12.3. Prompt tiếp tục khi mất lịch sử trò chuyện

```text
Tiếp tục dự án Đề tài 6 trong thư mục hiện tại.
Đọc AGENTS.md, KE_HOACH_VIBECODING_DE_TAI_6.md, docs/STATE.md,
docs/SESSION_LOG.md phần mới nhất, docs/DECISIONS.md và docs/KNOWN_ISSUES.md.
Kiểm tra git status và mã thực tế; không dựa vào trí nhớ hội thoại để kết luận đã làm.
Tóm tắt ngắn: giai đoạn hiện tại, việc đã xác minh, việc còn dang dở và lệnh chạy.
Sau đó thực hiện ngay Next action trong STATE.md, giữ nguyên mã đang hoạt động.
Nếu trạng thái ghi trong tài liệu khác mã thực tế, xác minh rồi cập nhật trước khi tiếp tục.
```

### 12.4. Prompt xử lý lỗi

```text
Tái hiện lỗi sau trên dữ liệu thử nghiệm: [mô tả, màn hình, bước gây lỗi].
Đọc trạng thái dự án và xác định nguyên nhân từ mã/log, không sửa theo phỏng đoán.
Sửa đúng phạm vi, thêm ca hồi quy nếu liên quan nghiệp vụ, quyền hoặc dữ liệu.
Chạy lại test lỗi và các chức năng cũ bị ảnh hưởng.
Ghi nguyên nhân, tệp thay đổi, kết quả thực tế và vấn đề còn lại vào tài liệu dự án.
Không bỏ validation, tắt test, nới quyền hoặc xóa dữ liệu để làm mất lỗi.
```

### 12.5. Nội dung tối thiểu của bộ nhớ dự án

`AGENTS.md` do G00 tạo phải chỉ dẫn AI đọc tài liệu ở đầu phiên; ghi stack, nguồn, phạm vi bắt buộc, quy tắc dữ liệu, nơi cấu hình riêng, tiêu chí hoàn thành và nghĩa vụ cập nhật trạng thái. Nếu đã có AGENTS.md, bổ sung có kiểm soát, không thay mất quy tắc hiện hành.

Mẫu `docs/STATE.md`:

```markdown
# Trạng thái dự án

- Cập nhật lúc: [ngày giờ và múi giờ]
- Nhánh và commit hiện tại: [giá trị thực tế]
- Giai đoạn hiện tại: [Gxx]
- Đã hoàn thành và có bằng chứng: [nội dung]
- Đang làm: [nội dung]
- Chưa làm hoặc chưa xác minh: [nội dung]
- Test gần nhất: [lệnh, thời gian, kết quả, mã thoát]
- Cách chạy ứng dụng: [lệnh đã xác minh]
- Cấu hình: [đường dẫn, không ghi mật khẩu]
- Lỗi còn tồn tại: [issue hoặc bước tái hiện]
- Next action: [một việc cụ thể có thể bắt đầu ngay]
```

Không dùng lịch sử chat làm nơi lưu tiến độ duy nhất. Khi đổi phạm vi hoặc quy tắc, cập nhật quyết định, test và trạng thái cùng lúc; không chỉ ghi trong hội thoại.

## 13. Kịch bản trình diễn và bàn giao

### 13.1. Tình huống 1 của đề

1. Điều phối tạo hoạt động, ca, nhu cầu và kỹ năng.
2. Tình nguyện viên khai báo kỹ năng, lịch rảnh và đăng ký hoạt động.
3. Điều phối chạy đề xuất, xem ca thiếu/người chưa bố trí và lý do.
4. Điều phối kiểm tra rồi công bố phương án; thử một chỉnh sửa không hợp lệ để thấy máy chủ từ chối.
5. Tình nguyện viên mở ca của mình, xác nhận; điều phối thấy số xác nhận tăng.

### 13.2. Tình huống 2 của đề

1. Tình nguyện viên báo vắng một ca đã phân.
2. Dashboard phản ánh ca thiếu người; bản ghi cũ có lịch sử.
3. Điều phối xem người có thể thay, thử chọn người thiếu kỹ năng hoặc trùng lịch để thấy bị chặn.
4. Chọn người hợp lệ; cập nhật phân công và thống kê trong giao dịch.
5. Xuất danh sách cuối; nếu không có người phù hợp thì hiển thị thiếu thực tế.

### 13.3. Bảng bám rubric 10 điểm

| Tiêu chí | Điểm tối đa | Minh chứng chuẩn bị |
| --- | --- | --- |
| Năm chức năng | 3,0 | F01–F05 tích hợp, trình diễn hai tình huống |
| Kỹ thuật 1 | 1,0 | Giải thích và test đồng thời kỹ năng, thời gian, nhu cầu |
| Kỹ thuật 2 | 1,0 | Bảng so sánh cùng dữ liệu, đủ ba chỉ số |
| Dữ liệu, an toàn | 1,0 | Snapshot và phiên bản 0,5; quyền và kiểm tra máy chủ 0,5 |
| Kiểm thử, sửa lỗi | 1,5 | Dữ liệu/đáp án 0,5; tự động/tích hợp 0,5; sửa và hồi quy 0,5 |
| Mã nguồn mở, bàn giao | 1,5 | Nguồn/giấy phép 0,5; tài liệu/issue/review 0,5; phát hành chạy lại 0,5 |
| Đóng góp cá nhân | 1,0 | Phần lập trình 0,5; giải thích và sửa nhỏ khi vấn đáp 0,5 |
| Tổng | 10,0 | Chỉ đánh dấu đạt khi có bằng chứng thực tế |

Theo dõi issue và review thực tế bằng GitHub hoặc tài liệu có người/ngày/nội dung nếu nhóm chưa dùng quy trình trực tuyến. Không tạo issue, review hay đóng góp giả. AI có thể chuẩn bị nội dung; việc công bố hoặc gửi cho người khác thực hiện theo quyền người dùng cấp.

Bản bàn giao gồm mã dự án `app`, migration, fixture, giấy phép/thông báo nguồn, hướng dẫn cấu hình không bí mật, lệnh chạy/test, snapshot và kết quả thí nghiệm giả lập, kịch bản demo, danh sách lỗi còn lại và bảng đóng góp. Loại `.local`, mật khẩu, dữ liệu cá nhân, thư mục Git nội bộ và tài nguyên không được dùng.

## 14. Điều kiện hoàn thành toàn dự án

- [ ] F01–F05 có dữ liệu thật, phân quyền và luồng web tích hợp.
- [ ] Thuật toán xét đồng thời kỹ năng, thời gian, số người; chỉnh tay dùng cùng quy tắc.
- [ ] Ba fixture mỗi bộ đúng 30 tình nguyện viên, 12 ca, 5 kỹ năng.
- [ ] Đã chạy hai thuật toán trên cùng từng snapshot, lưu cấu hình và kết quả thực tế.
- [ ] Báo đủ tỷ lệ ca đủ, số vi phạm, chênh lệch số ca; nêu người và ca chưa bố trí.
- [ ] Cả 5 phép thử bắt buộc đạt; có kiểm thử tự động, tích hợp, quyền và hồi quy.
- [ ] Phương án giữ đúng người, ca, kỹ năng và phiên bản dữ liệu đầu vào.
- [ ] Mọi thay đổi ảnh hưởng lịch được kiểm tra ở máy chủ và bảo vệ bằng giao dịch.
- [ ] Hai tình huống trình diễn chạy trọn vẹn; khởi động lại không mất dữ liệu.
- [ ] Nguồn, giấy phép, issue/review, đóng góp và hướng dẫn cài lại đầy đủ.
- [ ] Bản phát hành cài và chạy lại được từ môi trường sạch.
- [ ] STATE và các tài liệu phản ánh đúng mã, test và các hạn chế còn tồn tại.

## Phụ lục A Nội dung đầy đủ của Đề tài 6

Phần dưới chép lại toàn bộ nội dung văn bản đã đọc từ 2 trang tài liệu gốc, chuẩn hóa xuống dòng và trình bày lại bảng; không bổ sung yêu cầu vào nội dung gốc.

### Đề tài 6 Xây dựng hệ thống tổ chức hoạt động và phân công tình nguyện viên

**1. Bối cảnh và người sử dụng**

Mỗi hoạt động cần người có kỹ năng phù hợp và có thể tham gia đúng giờ. Ứng dụng tiếp nhận đăng ký, đề xuất phân công theo từng ca và hỗ trợ thay người khi có thành viên báo vắng hoặc đổi lịch.

Người sử dụng: Tình nguyện viên và người điều phối hoạt động.

**2. Các chức năng cần hoàn thành**

1. Quản lý hồ sơ, kỹ năng, lịch rảnh, hoạt động và số người cần cho từng ca.
2. Cho tình nguyện viên đăng ký, rút đăng ký và xem ca được phân công.
3. Đề xuất phân công theo các điều kiện và thứ tự ưu tiên; chỉ rõ người hoặc ca chưa bố trí được.
4. Cho người điều phối sửa phân công; vẫn kiểm tra trùng lịch, thiếu kỹ năng và vượt số người mỗi ca.
5. Xác nhận tham gia, thay người vắng; thống kê ca đủ hoặc thiếu người và xuất danh sách.

**3. Hai yêu cầu kỹ thuật**

Yêu cầu 1: Thuật toán phải xét đồng thời kỹ năng, thời gian và số người mỗi ca. Không gán người theo thứ tự mà bỏ qua các điều kiện này.

Yêu cầu 2: So sánh phương án của nhóm với cách gán theo thứ tự đăng ký trên cùng dữ liệu. Báo tỷ lệ ca đủ người, số vi phạm và mức chênh lệch số ca giữa các tình nguyện viên.

**4. Hai tình huống sử dụng**

Tình huống 1: Tạo hoạt động → đăng ký lịch, kỹ năng → đề xuất phân công → xác nhận.

Tình huống 2: Báo vắng → chọn người thay → kiểm tra điều kiện → cập nhật thống kê.

**5. Dữ liệu và phạm vi hỗ trợ**

Chuẩn bị 30 tình nguyện viên, 12 ca và 5 kỹ năng. Có ít nhất 3 bộ dữ liệu: đủ người, thiếu người và nhiều trường hợp trùng lịch.

Không yêu cầu tìm phương án tốt nhất trong mọi trường hợp hoặc kết nối lịch bên ngoài. Nhóm phải giải thích và kiểm thử thuật toán cải tiến cùng các điều kiện phân công.

**6. Các phép thử bắt buộc**

1. Một người không được phân vào hai ca trùng giờ.
2. Ca có yêu cầu kỹ năng không nhận người thiếu kỹ năng đó.
3. Sửa phân công làm vượt số người của ca phải bị từ chối.
4. Khi thiếu người, báo rõ các ca chưa đủ; không tạo thêm người giả để lấp chỗ.
5. Báo cáo so sánh dùng cùng dữ liệu và lưu cấu hình của từng thuật toán.

**7. Rubric riêng**

| Tiêu chí | Điểm | Nội dung đánh giá |
| --- | --- | --- |
| Năm chức năng | 3,0 | Mục 2, mỗi chức năng 0,6 điểm khi đủ và tích hợp; 0,3 nếu một phần; 0 nếu chưa làm. Trình diễn hai tình huống ở mục 4. |
| Kỹ thuật 1 | 1,0 | Thuật toán phải xét đồng thời kỹ năng, thời gian và số người mỗi ca. Đối chiếu toàn bộ yêu cầu 1, mục 3. |
| Kỹ thuật 2 | 1,0 | So sánh phương án của nhóm với cách gán theo thứ tự đăng ký trên cùng dữ liệu. Đối chiếu toàn bộ yêu cầu 2, mục 3. |
| Dữ liệu, an toàn | 1,0 | 0,5: Phương án đã lưu giữ đúng người, ca, kỹ năng và phiên bản dữ liệu đầu vào. 0,5: Kiểm tra quyền điều phối và mọi thay đổi lịch ở máy chủ. |
| Kiểm thử, sửa lỗi | 1,5 | 0,5: dữ liệu và đáp án, gồm các phép thử mục 6; 0,5: tự động và tích hợp; 0,5: sửa lỗi, chạy lại chức năng cũ. Đối chiếu các phép thử của đề tài. |
| Mã nguồn mở, bàn giao | 1,5 | 0,5: giấy phép và nguồn sử dụng; 0,5: tài liệu, issue và review; 0,5: bản phát hành cài và chạy lại được. |
| Đóng góp cá nhân | 1,0 | 0,5: phần lập trình của từng người; 0,5: giải thích và sửa một yêu cầu nhỏ khi vấn đáp. |

## Phụ lục B Đường dẫn nguồn và tài liệu kỹ thuật

- Kho nguồn: <https://github.com/FIT-DNU/Open-Source-Software-Development>.
- README nguồn đã đối chiếu: <https://github.com/FIT-DNU/Open-Source-Software-Development/blob/071801d017732fb7d0748c23fcc9f06be497bb06/README.md>.
- Nền MySQL: <https://github.com/FIT-DNU/Open-Source-Software-Development/tree/9a2f64648b698fb073509341c5326be471840b55/Ma_nguon_MySQL>.
- Hướng dẫn MySQL của mẫu: <https://github.com/FIT-DNU/Open-Source-Software-Development/blob/9a2f64648b698fb073509341c5326be471840b55/HUONG_DAN_MYSQL.md>.
- Giấy phép nền MySQL: <https://github.com/FIT-DNU/Open-Source-Software-Development/blob/9a2f64648b698fb073509341c5326be471840b55/Ma_nguon_MySQL/LICENSE>.
- Mạng Windows và WSL: <https://learn.microsoft.com/en-us/windows/wsl/networking>.
- PHP MySQLi: <https://www.php.net/manual/en/book.mysqli.php>.
- Giao dịch MySQLi: <https://www.php.net/manual/en/mysqli.begin-transaction.php>.
- Hàm băm mật khẩu PHP: <https://www.php.net/manual/en/ref.password.php>.

Các phần kiến trúc, tên bảng/lớp mới, chính sách đăng ký và thuật toán trong kế hoạch là đề xuất triển khai được xây dựng từ yêu cầu đề bài. Chúng chưa tồn tại đầy đủ trong mã mẫu; AI phải viết, kiểm thử và ghi bằng chứng khi thực hiện.
