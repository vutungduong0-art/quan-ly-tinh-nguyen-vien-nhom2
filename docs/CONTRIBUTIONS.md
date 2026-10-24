# Minh chứng đóng góp

Tệp này tổng hợp bằng chứng có thể kiểm tra từ lịch sử Git. Số commit không tự động tương đương mức đóng góp; nhóm cần xác nhận định danh Git nào thuộc thành viên nào trước khi nộp.

## Lịch sử đã xác minh ngày 24/10/2026

| Định danh Git | Email trong commit | Số commit trên toàn bộ các nhánh | Nội dung thể hiện trong lịch sử |
| --- | --- | ---: | --- |
| `Dương` | `trandaiduong.nguyen@gmail.com` | 3 | Lab 01–03: công cụ kiểm tra môi trường/quét mã, chính sách sức chứa–đăng ký và dữ liệu/giao diện quản lý hoạt động |
| `vutungduong0-art` | `vutungduong0@gmail.com` | 2 | Khởi tạo repository và Lab 04: quản lý bản nháp bằng Session/Cookie, tích hợp `DraftGuard` |
| `lynnwinters0211` | `nguyenhailam240806@gmail.com` | 6 | Ứng dụng VolunteerHub, bảo mật, migration/test, thuật toán, workflow 0.4, tài liệu và quy trình phát hành |
| `Nguyễn Hải Lâm` | `147834929+lynnwinters0211@users.noreply.github.com` | 1 | Rút gọn và làm rõ README cho người nhận mã nguồn |

Nguồn kiểm tra:

```bash
git shortlog -sne --all
git log --all --format='%h %an <%ae> %s'
git show --stat <commit>
```

Hai định danh cuối có dấu hiệu liên quan cùng một tài khoản GitHub, nhưng bảng giữ nguyên dữ liệu Git và không tự gộp danh tính. Thành viên thật cần xác nhận khi lập báo cáo.

## Bảng nhóm cần xác nhận trước khi nộp

| Họ tên/MSSV | Định danh Git | Phần chịu trách nhiệm | Commit hoặc PR minh chứng | Nội dung có thể giải thích/sửa khi vấn đáp |
| --- | --- | --- | --- | --- |
| Chưa cung cấp | Chưa xác nhận | Chưa xác nhận | Chưa xác nhận | Chưa xác nhận |

Không thay dòng trên bằng thông tin suy đoán. Mỗi thành viên nên cung cấp ít nhất một commit/PR có thay đổi mã và chọn một yêu cầu nhỏ có thể giải thích, chạy test và sửa trực tiếp khi giảng viên hỏi.

## Quy trình tạo minh chứng review thật

1. Tạo issue từ mẫu trong `.github/ISSUE_TEMPLATE/feature.md` và giao cho thành viên thực hiện.
2. Tạo nhánh riêng từ `lab05`, commit thay đổi và mở pull request về `lab05`.
3. Một thành viên khác đọc diff, chạy test và gửi review trên GitHub.
4. Chỉ merge sau khi yêu cầu và kết quả test được ghi rõ trong PR.
5. Ghi số issue/PR vào bảng trên; không tạo review bằng cùng một tài khoản rồi coi là review chéo.
