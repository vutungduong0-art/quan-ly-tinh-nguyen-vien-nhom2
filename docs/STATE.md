# Trạng thái dự án

- Cập nhật lúc: 2026-10-24 01:29 UTC.
- Nhánh và commit: `lab05`; mốc mã đã kiểm chứng `3b09bcf` khớp `origin/lab05` trước cập nhật bàn giao này. Tag GitHub `v0.4.0-demo` giữ nguyên ở release commit `7bc0b1f`; tệp `Đề tài 6.docx` vẫn untracked và không bị sửa.
- Giai đoạn hiện tại: đã triển khai G00–G10 và chuẩn bị bàn giao G11. Đây là demo học phần, không phải production.
- Phạm vi phiên: hoàn thiện các phần bàn giao ngoài năm chức năng Mục 2: môi trường MySQL, kiểm thử cuối, release và minh chứng quy trình nhóm.
- Đã hoàn thành: tự đăng ký với validation/password hash/role cố định; unique email; menu riêng cho khách/volunteer/coordinator; route điều phối giữ kiểm tra 403; hồ sơ điều phối trong modal; footer có phiên bản/năm/múi giờ; toàn bộ giao diện dùng Open Sans cục bộ. Các luồng vận hành 0.4 trước đó vẫn giữ nguyên.
- Migration: `001_demo_core.sql` đến `005_self_registration.sql`; migration 005 thêm unique email cho hồ sơ. Đã áp dụng 005 lên `volunteer_app` theo kiểu cộng dồn, không reset/xóa dữ liệu.
- Kiểm thử gần nhất: MySQL 8.4.11 thật tại `127.0.0.1:3307`, schema `volunteer_test`; migration 001–005/seed, 21 unit, 3 fixture, 13 integration, event/shift, I04 stale, I05 concurrency, workflow mở rộng và HTTP 45/45 đều đạt.
- Kiểm thử workflow mới xác nhận: rollback chuyển người sai; người vắng bị chặn phân lại; `unfilled` có thể mở/kết thúc lại nhưng không mở sau khi đóng hoạt động; cặp A/B không đổi lịch công bố; lịch sử/audit đầy đủ. HTTP đi thật qua hồ sơ/lịch rảnh, đối chứng, chuyển người, unfilled/mở lại, hai CSV và kho hoạt động.
- Cấu hình ứng dụng/test ở `app/.local/db.json` và `app/.local/test-db.json`; bí mật bị ignore. Test guard xác nhận đúng schema hậu tố `_test`.
- MySQL ứng dụng: `127.0.0.1:3307`, schema `volunteer_app`; migration 001–005 đã áp dụng.
- Bàn giao GitHub: issue [#1](https://github.com/vutungduong0-art/quan-ly-tinh-nguyen-vien-nhom2/issues/1), PR [#2](https://github.com/vutungduong0-art/quan-ly-tinh-nguyen-vien-nhom2/pull/2) từ `lab05` vào `main` và pre-release [`v0.4.0-demo`](https://github.com/vutungduong0-art/quan-ly-tinh-nguyen-vien-nhom2/releases/tag/v0.4.0-demo) đã được tạo. Release có tar.gz 872.705 byte và checksum 126 byte; PR mergeable và đang chờ review thật từ `DngOsea` hoặc `vutungduong0-art`.
- Lỗi/công việc ngoài mã: quyền MySQL và schema test đã giải quyết; web dùng `volunteer_app@localhost` với quyền tối thiểu. Người dùng báo thao tác cấu hình/kết nối đã thành công. Vẫn thiếu review chéo, merge PR và ánh xạ họ tên/MSSV thật trong hồ sơ đóng góp.
- Cách chạy sau khi cấu hình: `cd /home/lynn/project_6/app && bash lab.sh migrate && bash lab.sh seed && bash lab.sh check && bash lab.sh run`; website `http://127.0.0.1:8106`.
- Next action: một trong hai reviewer được yêu cầu review PR #2; chỉ merge sau khi review đạt, sau đó cập nhật họ tên/MSSV thật trong `docs/CONTRIBUTIONS.md`.
