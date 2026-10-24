# Theo dõi yêu cầu

Trạng thái ngày 23/10/2026 dựa trên mã và kiểm thử thực tế, không dựa vào tuyên bố giao diện.

| Mã | Trạng thái | Minh chứng/ghi chú |
| --- | --- | --- |
| F01 | Đạt | Thêm/sửa/đóng hoạt động; thêm/sửa/xóa ca/kỹ năng an toàn; tự đăng ký tài khoản volunteer; hồ sơ, kỹ năng và lịch rảnh; điều phối xem hồ sơ bằng modal |
| F02 | Đạt | Đăng ký, rút/tái đăng ký, lịch sử và Ca của tôi; chặn rút khi còn phân công |
| F03 | Đạt | Hai thuật toán, ca thiếu/người 0 ca và lý do, nháp/công bố trên giao diện |
| F04 | Đạt | Thêm/gỡ/thay/chuyển nguyên tử và phân công thủ công đều kiểm tra máy chủ; người đã báo vắng không thể bị phân lại cùng ca |
| F05 | Đạt | Xác nhận, tự báo vắng, hàng đợi thay người có `unfilled`/mở lại, thống kê từng ca, lịch sử và CSV UTF-8 |
| T01 | Đạt | `EligibilityChecker` và `ScheduleValidator` dùng cho tự động, thủ công, thay người, công bố |
| T02 | Đạt | `lab.sh compare`, 2 thuật toán × 3 snapshot; đủ tỷ lệ ca, vi phạm, chênh tải |
| D01 | Đạt | Ba fixture đúng 30 người/12 ca/5 kỹ năng, có `expected`, phiên bản, seed, hash |
| S01 | Đạt | `dataset_versions`, snapshot JSON, SHA-256, config/version thuật toán, phiên bản mã, nhóm A/B cùng snapshot và run assignments |
| S02 | Đạt | Role/ownership/CSRF/giao dịch, rate limit, session/header đạt; tự đăng ký ép role volunteer, mật khẩu băm/email unique; server thật dùng `volunteer_app` chỉ có SELECT/INSERT/UPDATE/DELETE |
| Q01 | Đạt trên server đích | MySQL cổng 3307/schema `volunteer_test`: 21 unit, 3 fixture, 13 integration, event/shift, I04, I05, workflow mở rộng và 45 HTTP đều đạt; guard test DB đạt |
| H01 | Đạt kỹ thuật, thiếu review thật | Git/remote, MIT, VERSION, tài liệu, tar.gz/checksum và cài sạch release đã đạt; đã thêm mẫu issue/PR nhưng GitHub hiện chưa có issue hoặc pull request/review thật |
| C01 | Có bằng chứng Git, chờ nhóm xác nhận | `docs/CONTRIBUTIONS.md` tổng hợp định danh, commit và phần việc từ lịch sử Git; còn cần ánh xạ họ tên/MSSV và xác nhận phần vấn đáp của thành viên thật |

Năm phép thử bắt buộc B01–B05 đều có minh chứng trong `tests/run.php`, `tests/http_smoke.php`, `tests/concurrency.php`, `tests/stale_plan.php` và `artifacts/comparison/`.
