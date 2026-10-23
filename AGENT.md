# Quy tắc làm việc của AI cho dự án Đề tài 6

Áp dụng cho toàn bộ repository **Hệ thống tổ chức hoạt động và phân công tình nguyện viên**. Đặt tệp này ở gốc dự án, cùng cấp với `KE_HOACH_VIBECODING_DE_TAI_6.md`. Đây là quy tắc làm việc lâu dài; tiến độ thực tế lưu trong `docs/STATE.md` và nhật ký, không ghi trạng thái tạm thời vào đây.

## 1. Mục tiêu và nguồn yêu cầu

- Hoàn thành Đề tài 6 theo tài liệu gốc và kế hoạch; dùng PHP, MySQLi, MySQL Server, Ubuntu và Antigravity IDE. MySQL Workbench quản trị cùng server mà ứng dụng sử dụng.
- Kế hoạch chính: `KE_HOACH_VIBECODING_DE_TAI_6.md`. Nội dung đầy đủ của đề bài được chép tại Phụ lục A; dùng khi không đọc được DOCX và ghi rõ nguồn đã đọc.
- Kho kế thừa: <https://github.com/FIT-DNU/Open-Source-Software-Development>.
- Nền đã chọn: nhánh `lab5`, thư mục `Ma_nguon_MySQL`, giấy phép MIT. Commit nguồn được kế hoạch đối chiếu là `9a2f64648b698fb073509341c5326be471840b55`; phải kiểm tra và lưu commit thực tế khi khởi tạo.
- Mã phát triển nằm trong `app/`; bản nguồn `Ma_nguon_MySQL/` giữ để tham chiếu theo kế hoạch. Nếu dự án hiện có cấu trúc khác, khảo sát và ghi quyết định ánh xạ, không tự di chuyển hàng loạt.
- Nhánh `main` là ví dụ quản lý sinh viên; không coi đó là hệ thống tình nguyện đã hoàn thành và không mặc định áp dụng giấy phép của `lab5` cho mã `main`.
- Không tự đổi sang Laravel, React, Node, PostgreSQL, PDO hoặc kiến trúc khác. Không mở rộng sang bán vé, thanh toán, lịch ngoài hay dịch vụ mô hình ngôn ngữ khi chưa có yêu cầu.

Phạm vi toàn dự án gồm đủ năm nhóm chức năng, hai yêu cầu kỹ thuật, hai tình huống trình diễn và ba bộ dữ liệu. Nếu người dùng giao một giai đoạn hoặc bản demo, hoàn tất phần được giao và ghi rõ phần còn lại; không tự thu hẹp yêu cầu của toàn dự án.

## 2. Thứ tự đọc ngữ cảnh ở đầu mỗi phiên

Thực hiện theo thứ tự sau trước khi sửa mã:

1. Đọc yêu cầu mới nhất của người dùng để xác định mục tiêu, phạm vi và các thay đổi được chấp nhận.
2. Đọc `AGENTS.md` ở gốc và các `AGENTS.md` áp dụng trong thư mục sắp sửa; không bỏ qua quy tắc của repository đang có.
3. Đọc `docs/STATE.md`: giai đoạn hiện tại, việc đang làm, lỗi chặn, test gần nhất và `Next action`.
4. Đọc toàn bộ kế hoạch ở lần đầu làm việc; các phiên sau đọc lại phạm vi, tiêu chí nghiệm thu của giai đoạn hiện tại và phần liên quan. Đọc đề bài hoặc Phụ lục A trước khi kết luận yêu cầu nghiệp vụ.
5. Đọc `docs/DECISIONS.md` và `docs/REQUIREMENTS.md` để biết quyết định đã chốt, cách ánh xạ yêu cầu và phần còn thiếu.
6. Đọc `docs/KNOWN_ISSUES.md`, kết quả liên quan trong `docs/TEST_RESULTS.md` và mục mới nhất của `docs/SESSION_LOG.md`.
7. Đọc `docs/SOURCE_PROVENANCE.md`, README và tài liệu thiết lập khi đụng tới mã kế thừa, môi trường, migration hoặc cách chạy.
8. Kiểm tra thư mục, nhánh, thay đổi Git, mã và test thực tế trước khi chỉnh sửa. Xác định chênh lệch giữa tài liệu và hiện trạng.

Nếu thiếu tệp ngữ cảnh, tìm đúng tên trong repository bằng `rg --files`; không giả định mọi đường dẫn trong kế hoạch đã tồn tại. Tạo tài liệu tối thiểu từ bằng chứng hiện có nếu thuộc phạm vi công việc. Không tự ghi “đã hoàn thành”, “test đạt” hoặc tên commit chưa kiểm tra.

### Khi thông tin mâu thuẫn

- Về phạm vi: yêu cầu mới nhất của người dùng được ưu tiên; ghi lại thay đổi so với đề hoặc kế hoạch và ảnh hưởng nghiệm thu. Không âm thầm sửa yêu cầu gốc.
- Về nghiệp vụ: đề bài là căn cứ; các lựa chọn triển khai trong kế hoạch và `DECISIONS.md` phải phân biệt rõ với yêu cầu gốc. Dùng quyết định mới đã được chấp nhận, cập nhật tài liệu cũ khi cần.
- Về trạng thái: mã, cơ sở dữ liệu và kết quả chạy xác định điều gì thực sự đang hoạt động; tài liệu cũ không đủ để chứng minh đã đạt yêu cầu. Hiện trạng sai nghiệp vụ phải được sửa, không dùng nó làm lý do đổi yêu cầu.
- Lịch sử trò chuyện chỉ bổ sung ngữ cảnh; không thay trạng thái được lưu trong dự án.

## 3. Khởi động công việc và bảo vệ mã đang có

Chạy các kiểm tra chỉ đọc phù hợp trong terminal Ubuntu:

```bash
pwd
git status --short
git branch --show-current
git rev-parse HEAD
rg --files -g AGENTS.md -g '*.php' -g '*.md' -g '*.sql' -g 'lab.sh'
```

Nếu chưa có Git, ghi nhận và khảo sát cấu trúc trước khi khởi tạo; không tự nhận đã có repository. Nếu thiếu `rg`, dùng công cụ tương đương.

- Đọc tệp liên quan trước khi sửa; ưu tiên bổ sung hoặc chỉnh đúng phạm vi, không viết lại toàn dự án khi chỉ cần sửa một chức năng.
- Tôn trọng thay đổi chưa commit của người dùng; không ghi đè, hoàn tác hoặc tự dọn thay đổi không thuộc nhiệm vụ.
- Không clone lồng vào dự án hiện có; không tạo bản `app` thứ hai; không chạy sao chép nguồn nếu thư mục đích đã tồn tại.
- Không dùng `git reset --hard`, `git clean`, force push, reset database hay xóa dữ liệu để xử lý lỗi thông thường.
- Không đổi tên bảng, route, trường biểu mẫu hoặc hợp đồng hàm tùy tiện. Khi cần đổi, cập nhật toàn bộ nơi gọi, migration, test và tài liệu bị ảnh hưởng.
- Không đưa thư mục `.local/`, mật khẩu, token, log riêng hoặc dữ liệu cá nhân vào Git hay gói bàn giao.
- Giữ giấy phép và thông báo bản quyền nguồn; ghi nguồn và thay đổi vào `SOURCE_PROVENANCE.md` và `THIRD_PARTY_NOTICES.md`.

## 4. Cách thực hiện một nhiệm vụ

1. Nêu ngắn gọn mục tiêu, giai đoạn, phạm vi sửa và tiêu chí đạt.
2. Xác định luồng cần hoàn thiện từ giao diện đến handler, nghiệp vụ, dữ liệu và kết quả trả về.
3. Thực hiện phần việc đã được giao đến khi có kết quả kiểm chứng được; tự giải quyết lựa chọn thông thường đã có trong kế hoạch.
4. Với lỗi: tái hiện, tìm nguyên nhân từ mã/log, sửa nguyên nhân, thêm kiểm thử hồi quy nếu liên quan nghiệp vụ hoặc dữ liệu, rồi chạy lại phần bị ảnh hưởng.
5. Cập nhật trạng thái và bằng chứng trước khi kết thúc giai đoạn hoặc bàn giao phiên.

Không chỉ trả các đoạn code rời nếu đang được yêu cầu chỉnh trực tiếp dự án. Không kết luận hoàn thành từ ảnh giao diện hoặc việc trang mở được. Không dừng để hỏi lại lựa chọn đã được người dùng hoặc kế hoạch xác định.

Chỉ cần hỏi khi thiếu thông tin làm chặn thao tác đúng, có mâu thuẫn yêu cầu không thể tự giải quyết, hoặc hành động vượt phạm vi được giao. Nếu môi trường không cho nhập mật khẩu/sudo, chuẩn bị lệnh đúng thư mục và hướng dẫn thao tác; tiếp tục các phần độc lập, ghi rõ phần đang chờ. Không yêu cầu người dùng gửi mật khẩu vào chat hoặc tệp dự án.

## 5. Quy tắc môi trường và cơ sở dữ liệu

- Terminal thực thi dự án là Bash trong Ubuntu; kiểm tra `pwd` sau khi mở lại IDE hoặc đổi terminal. Phân biệt đường dẫn Windows và Ubuntu, chú ý chữ hoa/chữ thường.
- Đối chiếu phiên bản PHP thực tế và các extension `mysqli`, `mysqlnd`. Mức tương thích nền: PHP từ 8.1, MySQL từ 8.0.16; ưu tiên phiên bản còn được hỗ trợ theo môi trường thực tế.
- MySQL Workbench là client quản trị. Xác nhận PHP và Workbench kết nối cùng server bằng `@@server_uuid`, cổng và schema; không nhầm server Windows/XAMPP với server Ubuntu.
- Mặc định schema ứng dụng `volunteer_app`, schema kiểm thử `volunteer_test`. Chỉ dọn dữ liệu thử trong schema đã kiểm chứng là dành riêng cho test.
- Cấu hình riêng ở `app/.local/`, kế thừa `.local/db.json`; test có cấu hình riêng. Không xuất nội dung bí mật khi đọc log hoặc báo lỗi.
- Dùng tài khoản ứng dụng có quyền phù hợp trong schema; migration dùng tài khoản riêng có quyền thay đổi cấu trúc. Không chạy web bằng tài khoản quản trị toàn server.
- Kết nối dùng `utf8mb4`, truy vấn tham số `?` và InnoDB. Không trộn cú pháp PostgreSQL vào MySQL.
- Migration phải có phiên bản, seed có điều kiện rõ ràng; không reset dữ liệu nghiệp vụ để ép kết quả demo.
- Mặc định chạy web tại `127.0.0.1:8106`, document root `app/public`; một terminal giữ server, terminal thứ hai chạy test. Không tự tắt tiến trình không rõ nguồn khi trùng cổng.

Lệnh chạy trực tiếp từ gốc repository khi `app/public` và cấu hình đã sẵn sàng:

```bash
php -S 127.0.0.1:8106 -t app/public
```

Các lệnh `lab.sh`, migration, seed và `tools/compare.php` phải kiểm tra đã tồn tại và hiểu tác động trước khi chạy. Đường dẫn được đề xuất trong kế hoạch không phải bằng chứng công cụ đã được triển khai.

## 6. Quy tắc nghiệp vụ không được làm sai

- Hai vai trò: `volunteer` và `coordinator`. Quyền, quyền sở hữu và mọi thay đổi lịch đều kiểm tra ở máy chủ.
- Đăng ký theo hoạt động; lưu thời điểm và lịch sử rút/tái đăng ký để xác định thứ tự đối chứng.
- Người được phân phải còn hoạt động, đăng ký còn hiệu lực, đủ tất cả kỹ năng yêu cầu, rảnh toàn ca, không trùng lịch ở bất kỳ hoạt động nào, chưa có phân công trùng cặp, không báo vắng ca đó và ca còn chỗ.
- Hai khoảng trùng nhau khi `a.start < b.end AND b.start < a.end`. Ca kết thúc đúng lúc ca sau bắt đầu không trùng. Chuẩn hóa lịch rảnh; rảnh một phần không đủ điều kiện.
- Hiển thị giờ `Asia/Ho_Chi_Minh`, chuyển UTC khi lưu/tính toán; giờ bắt đầu phải nhỏ hơn giờ kết thúc, nhu cầu là số nguyên dương.
- Dùng cùng bộ kiểm tra điều kiện cho đề xuất tự động, sửa tay, thay người và công bố. Không chỉ kiểm tra bằng JavaScript hoặc ẩn nút.
- Phương án nháp không chiếm lịch chính thức. Công bố phải kiểm tra lại dữ liệu hiện hành và xung đột giữa các hoạt động.
- Mọi thay đổi ảnh hưởng lịch đi qua dịch vụ nghiệp vụ dùng chung, transaction và cơ chế khóa `schedule_guard` theo kế hoạch; tăng `schedule_revision` sau thay đổi liên quan.
- Từ chối phương án cũ bằng `STALE_PLAN` khi revision không còn khớp; không ghi đè lịch mới.
- Sửa giờ ca, kỹ năng, lịch rảnh, nhu cầu hoặc rút đăng ký không được để lại phân công sai điều kiện. Từ chối có lý do hoặc xử lý trọn giao dịch theo chính sách đã ghi.
- Chỉ `assigned` và `confirmed` tính vào số người đã bố trí; số xác nhận báo riêng. `absent`, `cancelled`, `replaced` không được tính lấp chỗ.
- Báo vắng và thay người phải giữ lịch sử. Không có người phù hợp thì báo thiếu; không tạo người giả hoặc khôi phục người vắng để làm đẹp số liệu.
- Lỗi giữa giao dịch phải rollback. Lỗi kết nối không được đổi thành thành công, “đã đủ người” hoặc danh sách rỗng.

## 7. Thuật toán và thí nghiệm

- Đối chứng `RegistrationOrderAllocator`: ca theo giờ bắt đầu/ID; người theo thời điểm đăng ký/ID; vẫn kiểm tra đủ ràng buộc.
- Cải tiến `ScarcityBalancedAllocator`: ưu tiên ca khan hiếm, người ít ca/ít cơ hội khác, sửa cục bộ có giới hạn theo mục 7 của kế hoạch. Không cam kết tối ưu toàn cục hoặc luôn tốt hơn đối chứng.
- Tách phần tính toán khỏi ghi dữ liệu. Kiểm định kết quả bằng validator độc lập trước khi lưu/công bố.
- Mỗi fixture có đúng 30 tình nguyện viên, 12 ca, 5 kỹ năng; tài khoản điều phối không tính trong 30 người. Có đủ `DU_NGUOI_v1`, `THIEU_NGUOI_v1`, `TRUNG_LICH_v1` và đáp án kiểm tra.
- Hai thuật toán dùng cùng snapshot, cùng phân công cố định, cùng quy tắc và cách đo; lịch tạm của mỗi lần chạy độc lập.
- Lưu snapshot không đổi, SHA-256, phiên bản dữ liệu, commit mã, tên/phiên bản thuật toán, cấu hình, seed nếu có, kết quả và lý do chưa bố trí.
- Báo đủ tỷ lệ ca đủ người, số vi phạm và chênh lệch số ca. Tính đủ cả ca 0 người và tình nguyện viên 0 ca; xử lý mẫu số 0.
- Chạy ít nhất 6 kết quả: hai thuật toán trên ba bộ dữ liệu. Thí nghiệm không tự thay đổi lịch công bố.
- Chỉ dùng số đo thực tế; giữ cả kết quả bằng nhau hoặc cải tiến kém hơn. Không chỉnh dữ liệu sau khi chạy một phương án để làm phương án kia tốt hơn.

## 8. Xác thực và an toàn dữ liệu

- Dùng `password_hash`, `password_verify`, tái tạo session ID sau đăng nhập. Không kế thừa phép so sánh mật khẩu rõ từ ví dụ `main`.
- Mọi thao tác thay đổi dữ liệu dùng POST và token chống giả mạo yêu cầu; kiểm tra đăng nhập, vai trò và sở hữu tại handler.
- Không tin `role` hoặc `user_id` người dùng gửi lên; dùng phiên đăng nhập và dữ liệu máy chủ.
- Dùng truy vấn tham số cho giá trị và danh sách cho phép khi chọn tên cột hoặc thứ tự sắp xếp.
- Escape dữ liệu khi xuất HTML; kiểm tra đầu vào ở máy chủ. Xuất CSV cần xử lý nguy cơ ô bị hiểu thành công thức.
- Giữ khóa ngoại, unique và CHECK; không tắt ràng buộc để chèn dữ liệu lỗi. Chỉ nhận mã lỗi trùng phù hợp là trùng, không nuốt mọi ngoại lệ.

## 9. Kiểm thử và tiêu chí hoàn thành

Đọc hướng dẫn test thực tế trước khi chạy. Dùng test schema riêng và lưu lệnh, môi trường, thời gian, mã thoát và kết quả. `check` chỉ xác minh môi trường; không thay thế test nghiệp vụ.

Năm phép thử bắt buộc:

1. Một người không được gán vào hai ca trùng giờ.
2. Người thiếu kỹ năng không được nhận ca có yêu cầu đó.
3. Sửa phân công làm vượt số người bị từ chối ở máy chủ.
4. Thiếu người phải báo rõ ca và số thiếu, không sinh thêm người giả.
5. So sánh dùng cùng dữ liệu, lưu cấu hình từng thuật toán.

Bổ sung test cho quyền, sở hữu, ca tiếp giáp, bao phủ lịch rảnh, trùng giữa hoạt động, đăng ký trùng, phương án cũ, thay người/rollback và hai request tranh chỗ. Chạy hồi quy theo ảnh hưởng của thay đổi; không lặp toàn bộ bộ test nếu không có thay đổi hay rủi ro mới.

Không sửa test để bỏ qua lỗi; không đổi đáp án chỉ để đạt. `SKIP`, `NOT_RUN`, chưa có môi trường hoặc chỉ đọc mã đều không phải PASS.

Một nhiệm vụ chỉ hoàn thành khi phần được giao đã tích hợp, dữ liệu lưu đúng, quyền và điều kiện được kiểm tra, tiêu chí nghiệm thu liên quan đã xác minh và tài liệu đã cập nhật. Nếu chưa chạy được một phép thử, ghi rõ giới hạn và bước tiếp theo, không nhận đã đạt.

## 10. Lưu ngữ cảnh sau mỗi giai đoạn và trước khi kết thúc phiên

| Tệp | Nội dung phải giữ |
| --- | --- |
| `docs/STATE.md` | Bản tóm tắt hiện hành, giai đoạn, việc đang làm, test mới nhất, lỗi chặn và một `Next action` cụ thể |
| `docs/SESSION_LOG.md` | Nhật ký nối tiếp theo phiên: yêu cầu, thay đổi chính, kiểm chứng, việc bàn giao; không xóa lịch sử cũ |
| `docs/DECISIONS.md` | Quyết định nghiệp vụ/kỹ thuật, lý do, ngày, ảnh hưởng và quyết định bị thay thế |
| `docs/REQUIREMENTS.md` | Trạng thái F01–F05, T01–T02, D01, S01–S02, Q01, H01, C01 và liên kết minh chứng |
| `docs/KNOWN_ISSUES.md` | Lỗi còn tồn tại, bước tái hiện, ảnh hưởng, hướng xử lý; đánh dấu lỗi đã giải quyết |
| `docs/TEST_RESULTS.md` | Lệnh, thời điểm, môi trường, dữ liệu/commit đã chạy, kết quả và phần chưa chạy |
| `docs/SOURCE_PROVENANCE.md` | Nguồn, commit, tệp kế thừa và thay đổi so với nguồn |
| `README.md` | Các lệnh cài, cấu hình, migrate, seed, chạy và test đã xác minh khi có thay đổi |

Không tạo tệp trống hàng loạt để đánh dấu hoàn thành. Nếu là phiên đầu tiên, tạo các tệp cần thiết và ghi trung thực “chưa khảo sát” hoặc “chưa chạy” cho phần chưa xác minh.

Mẫu tối thiểu của `docs/STATE.md`:

```markdown
# Trạng thái dự án

- Cập nhật lúc: [ngày giờ, múi giờ]
- Nhánh và commit: [đã kiểm tra; ghi thêm có thay đổi chưa commit]
- Giai đoạn hiện tại: [G00–G11]
- Phạm vi phiên hiện tại: [nhiệm vụ đang được giao]
- Đã hoàn thành và kiểm chứng: [nội dung, đường dẫn minh chứng]
- Đang làm: [tệp hoặc luồng cụ thể]
- Chưa làm hoặc chưa kiểm chứng: [nội dung]
- Test gần nhất: [lệnh, thời gian, kết quả, mã thoát]
- Cách chạy đã xác minh: [lệnh và thư mục]
- Cấu hình riêng: [đường dẫn, không ghi bí mật]
- Lỗi chặn hoặc điểm cần quyết định: [nội dung]
- Next action: [một việc cụ thể có thể bắt đầu ngay]
```

Ghi mốc checkpoint trước khi đổi nhiệm vụ, dừng phiên, bàn giao hoặc khi ngữ cảnh sắp hết. Nếu công việc bị ngắt giữa chừng, chỉ rõ phần sửa dở và lệnh chưa chạy để phiên sau không nhầm là hoàn thành.

## 11. Tiếp tục sau khi đóng IDE hoặc mất lịch sử

1. Thực hiện lại thứ tự đọc ở mục 2 và kiểm tra Git; không bắt đầu dự án từ đầu.
2. Xác minh `Next action` còn phù hợp với yêu cầu mới và mã hiện tại.
3. Nếu mã thay đổi sau lần ghi STATE, đọc diff và xác định phần đã làm; cập nhật trạng thái sau khi kiểm chứng.
4. Thực hiện việc tiếp theo ngay trong phạm vi đã giao, không yêu cầu người dùng kể lại toàn bộ lịch sử.

Nếu không có STATE và không đủ bằng chứng, tạo bản khảo sát hiện trạng, đánh dấu phần chưa xác minh và tiếp tục công việc độc lập. Không tự đoán đã xong giai đoạn nào.

## 12. Cách báo cáo với người dùng

- Dùng tiếng Việt rõ ràng, giải thích thuật ngữ khi cần; trình bày thao tác đúng môi trường và thư mục.
- Cập nhật ngắn gọn khi có phát hiện hoặc thay đổi hướng làm. Chỉ hỏi vấn đề thực sự chặn công việc.
- Cuối phiên nêu: đã làm gì, đã chạy kiểm tra nào và kết quả, còn giới hạn/lỗi gì, bước tiếp theo.
- Phân biệt kế hoạch, mã đã viết và chức năng đã chạy thành công. Không tự nhận toàn dự án xong chỉ vì giai đoạn hiện tại đã xong.
- Không bịa ảnh minh chứng, số liệu, issue, review, commit, bản phát hành hoặc đóng góp thành viên.
- Không công bố, triển khai ra ngoài, gửi thông tin cho người khác hoặc thay đổi môi trường ngoài phạm vi được người dùng giao.

**Nguyên tắc xuyên suốt:** đọc ngữ cảnh trước khi sửa; kế thừa đúng nguồn; kiểm chứng bằng kết quả thực tế; lưu tiến độ để phiên sau tiếp tục được.
