# Kết quả kiểm thử

## Phiên 23/10/2026 UTC — hoàn thiện G00–G10

Môi trường thực: PHP 8.5.4 với `mysqli`/`mysqlnd`; MySQL 8.4.11; host `Lynn`; port `3307`; schema `volunteer_app`; server UUID `0e47d81a-cb0f-11f1-8097-00155d38bf23`.

| Lệnh/nhóm | Kết quả thực tế | Mã thoát |
| --- | --- | --- |
| PHP lint toàn bộ `app/src`, `public`, `tools`, `tests` | Không có lỗi cú pháp | 0 |
| `bash lab.sh migrate` | 001 đã có; áp dụng 002; chạy lại không nhân migration | 0 |
| `bash lab.sh seed` | 30 tình nguyện viên, hồ sơ/kỹ năng/lịch rảnh và 3 hoạt động mẫu; không ghi đè mật khẩu | 0 |
| `bash lab.sh check` | PHP/extension/cấu trúc/MySQL/schema/UUID đạt | 0 |
| `bash lab.sh test` | 20 đạt, 0 lỗi | 0 |
| `bash lab.sh fixtures` | 3/3 fixture đúng 30/12/5 và tính chất expected | 0 |
| `bash lab.sh integration` | 9 đạt, 0 lỗi | 0 |
| `bash lab.sh stale` | I04: phương án stale bị từ chối, lịch không đổi | 0 |
| `bash lab.sh concurrency` | I05: 2 tiến trình tranh 1 chỗ, đúng 1 thành công | 0 |
| `bash lab.sh extend` | 20 unit + 3 fixture + 9 integration + I04 + I05 đạt | 0 |
| `bash lab.sh http-test http://127.0.0.1:8106` | 27 đạt, 0 lỗi; dữ liệu tạm tự dọn | 0 |
| `bash lab.sh compare` | 6 kết quả, cả hai thuật toán × 3 snapshot | 0 |

HTTP đã đi qua: login hai vai trò; tạo hoạt động/ca; dữ liệu sai; đăng ký/trùng/rút; hồ sơ; chặn role; sinh/công bố; xác nhận; chặn rút khi có ca; tự báo vắng; thay người; phân công thủ công; chặn người thứ ba vào ca cần hai; CSV; CSRF; kiểm tra persistence qua nhiều lượt tải.

## Kết quả thí nghiệm

| Fixture | SHA-256 | Baseline: đủ ca / vi phạm / chênh tải | Cải tiến: đủ ca / vi phạm / chênh tải |
| --- | --- | --- | --- |
| `DU_NGUOI_V1` | `2971c5a0002f4c757d65b60243bd60ed0ee6bbb742e520577743ae3ebabf832c` | 100% / 0 / 12 | 100% / 0 / 1 |
| `THIEU_NGUOI_V1` | `d3c3faa267ad613b7ffd866d3ca2f8d09a013e2ee11b0fc0c32ee7f69ca1ea82` | 91.67% / 0 / 12 | 91.67% / 0 / 1 |
| `TRUNG_LICH_V1` | `f2daf31e5dae63cbbe161e62c78c447f09c2bb7b9dd288fc3dd5b55292514462` | 100% / 0 / 6 | 100% / 0 / 1 |

Tỷ lệ lấp vị trí tương ứng: 100%, 94.44%, 100% ở cả hai thuật toán. Bộ thiếu người giữ đúng 30 người và ca 12 thiếu tối thiểu 2 người. Chi tiết JSON ở `artifacts/comparison/`.

## Chưa được ghi là PASS

- Chưa thử cài từ release vào schema sạch riêng vì chưa có Git/release và quyền tạo schema test.
- Chưa đối chiếu trực tiếp trong giao diện Workbench; chỉ đã có UUID/port/schema phía PHP để người dùng so sánh.
- Tài khoản DB của web vẫn là migrator; xem `KNOWN_ISSUES.md`.
