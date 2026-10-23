# Trạng thái dự án

- Cập nhật lúc: 2026-10-23 17:22 UTC.
- Nhánh và commit: `lab05`, HEAD `3bcad54`; đang có thay đổi 0.4.0-demo chưa commit. Tệp `Đề tài 6.docx` của người dùng vẫn untracked và không bị sửa.
- Giai đoạn hiện tại: đã triển khai G00–G10 và chuẩn bị bàn giao G11. Đây là demo học phần, không phải production.
- Phạm vi phiên: hoàn thiện các thiếu sót nghiệp vụ 0.4.0-demo và bổ sung kiểm thử hồi quy/HTTP cho toàn bộ luồng mới.
- Đã hoàn thành: chặn phân lại người đã báo vắng; chuyển phân công nguyên tử; trạng thái thay người `unfilled` và mở lại; báo cáo trực tiếp từng ca; cặp A/B cùng snapshot/hash/config/phiên bản mã; CSV phân công/tình trạng ca; kho hoạt động và lịch sử phân công/thay người/ca cá nhân.
- Migration: `001_demo_core.sql` đến `004_workflow_reporting.sql`; migration 004 thêm ghi chú xử lý thay người, nhóm đối chứng và phiên bản mã nguồn. Không reset/xóa `volunteer_app`.
- Kiểm thử gần nhất: MySQL 8.4.11 tạm tại `127.0.0.1:33307`, schema `volunteer_test`; 21 unit, 3 fixture, 13 integration, event/shift, I04 stale, I05 concurrency, workflow mở rộng và HTTP 39/39 đều đạt; PHP lint và `git diff --check` sạch.
- Kiểm thử workflow mới xác nhận: rollback chuyển người sai; người vắng bị chặn phân lại; `unfilled` có thể mở/kết thúc lại nhưng không mở sau khi đóng hoạt động; cặp A/B không đổi lịch công bố; lịch sử/audit đầy đủ. HTTP đi thật qua hồ sơ/lịch rảnh, đối chứng, chuyển người, unfilled/mở lại, hai CSV và kho hoạt động.
- Cấu hình ứng dụng thực vẫn ở `app/.local/db.json`; bí mật bị ignore. Cấu hình/test server tạm đã được dọn sau kiểm thử.
- MySQL ứng dụng đã biết: `127.0.0.1:3307`, schema `volunteer_app`; phiên IDE hiện không kết nối được nên migration 004 chưa được áp dụng lên DB thật.
- Lỗi/công việc ngoài mã: tài khoản web trên server thật trước đó còn `ALL PRIVILEGES`; cần đổi sang quyền tối thiểu. Workbench/UUID, issue/review và bảng đóng góp thành viên cần bằng chứng thao tác thật của người dùng/nhóm.
- Cách chạy sau khi cấu hình: `cd /home/lynn/project_6/app && bash lab.sh migrate && bash lab.sh seed && bash lab.sh check && bash lab.sh run`; website `http://127.0.0.1:8106`.
- Next action: khi MySQL cổng 3307 hoạt động, áp dụng migration 004 bằng tài khoản migration, chạy `check`, rồi trình diễn ba tình huống trong `docs/DEMO_SCRIPT.md`.
