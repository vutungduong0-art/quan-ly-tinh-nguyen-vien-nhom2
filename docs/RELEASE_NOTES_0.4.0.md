# VolunteerHub 0.4.0-demo

Đây là bản demo học phần của hệ thống tổ chức hoạt động và phân công tình nguyện viên, không phải cấu hình production công khai.

## Nội dung chính

- Quản lý hoạt động, ca, kỹ năng, hồ sơ và lịch rảnh.
- Đăng ký/rút đăng ký, xem và xác nhận ca cá nhân.
- Phân công thủ công, baseline theo đăng ký và thuật toán cải tiến khan hiếm/cân bằng.
- Snapshot, revision, công bố phương án và từ chối phương án cũ.
- Báo vắng, chuyển/thay người, ghi nhận chưa tìm được người và lưu lịch sử.
- Thống kê tổng hợp/từng ca, so sánh cùng snapshot và xuất CSV.
- Tự đăng ký tài khoản volunteer, menu theo vai trò, modal hồ sơ và Open Sans cục bộ.

## Kiểm chứng gần nhất

- 21 unit test và 3 fixture chuẩn đạt ngày 24/10/2026.
- Bộ kiểm thử MySQL cô lập gần nhất: 13 integration, quản lý hoạt động/ca, stale plan, concurrency và workflow mở rộng đạt.
- HTTP end-to-end gần nhất: 45/45 bước đạt trên MySQL 8.4.11/schema `volunteer_test` cô lập.

Chi tiết và giới hạn môi trường nằm trong `docs/TEST_RESULTS.md` và `docs/KNOWN_ISSUES.md`.

## Cài đặt

Giải nén gói, làm theo `README.md`, tạo cấu hình riêng trong `app/.local/`, sau đó chạy:

```bash
cd app
bash lab.sh migrate
bash lab.sh seed
bash lab.sh check
bash lab.sh run
```

Không đưa mật khẩu hoặc nội dung `app/.local/` vào Git hay gói bàn giao.
