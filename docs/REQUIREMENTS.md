# Theo dõi yêu cầu

Trạng thái ngày 23/10/2026 dựa trên mã và kiểm thử thực tế, không dựa vào tuyên bố giao diện.

| Mã | Trạng thái | Minh chứng/ghi chú |
| --- | --- | --- |
| F01 | Đạt | Hoạt động/ca/kỹ năng; hồ sơ, kỹ năng và lịch rảnh; kiểm tra sửa/xóa ảnh hưởng lịch |
| F02 | Đạt | Đăng ký, rút/tái đăng ký, lịch sử và Ca của tôi; chặn rút khi còn phân công |
| F03 | Đạt | Hai thuật toán, ca thiếu/người 0 ca và lý do, nháp/công bố trên giao diện |
| F04 | Đạt | Thêm/gỡ/thay và phân công thủ công đều kiểm tra máy chủ; khóa sức chứa toàn cục |
| F05 | Đạt | Xác nhận, tự báo vắng, hàng đợi thay người, thống kê và CSV UTF-8 |
| T01 | Đạt | `EligibilityChecker` và `ScheduleValidator` dùng cho tự động, thủ công, thay người, công bố |
| T02 | Đạt | `lab.sh compare`, 2 thuật toán × 3 snapshot; đủ tỷ lệ ca, vi phạm, chênh tải |
| D01 | Đạt | Ba fixture đúng 30 người/12 ca/5 kỹ năng, có `expected`, phiên bản, seed, hash |
| S01 | Đạt | `dataset_versions`, snapshot JSON, SHA-256, config/version thuật toán và run assignments |
| S02 | Đạt một phần | Role/ownership/CSRF/giao dịch đạt; tài khoản DB web còn dùng migrator thay vì tài khoản tối thiểu |
| Q01 | Đạt | 20 unit, 3 fixture, 9 integration, I04, I05 và 27 HTTP đều đạt |
| H01 | Một phần | README/docs/fixture/artifact đầy đủ; chưa có Git/release sạch vì workspace không có repo và quyền schema test |
| C01 | Chưa đánh giá | Chưa có thông tin thành viên/đóng góp thật; không tạo minh chứng giả |

Năm phép thử bắt buộc B01–B05 đều có minh chứng trong `tests/run.php`, `tests/http_smoke.php`, `tests/concurrency.php`, `tests/stale_plan.php` và `artifacts/comparison/`.
