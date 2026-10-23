# Kịch bản trình diễn hoàn chỉnh

## Chuẩn bị

```bash
cd /home/lynn/project_6/app
bash lab.sh check
bash lab.sh run
```

Mở <http://127.0.0.1:8106>. Terminal thứ hai có thể chạy `bash lab.sh compare`.

## Tình huống 1 — lập và công bố lịch

1. Đăng nhập `dieuphoi` / `Dieuphoi@123`; giới thiệu dashboard đọc số liệu MySQL.
2. Tạo hoạt động và hai ca. Một ca yêu cầu **Sơ cứu**, ca còn lại yêu cầu **Hậu cần**; thử số người `0` hoặc giờ đảo để thấy máy chủ từ chối.
3. Đăng nhập `tinhnguyen`; mở **Tình nguyện viên** để xem hồ sơ, kỹ năng, lịch rảnh; đăng ký hoạt động.
4. Dùng thêm `tinhnguyen02` nếu cần ứng viên thứ hai.
5. Đăng nhập điều phối; mở **Phân công**, chọn hoạt động, chạy thuật toán cải tiến. Mở phần giải thích ca thiếu/người 0 ca.
6. Công bố phương án. Thử thêm người vào ca đã đủ để trình diễn kiểm tra sức chứa phía máy chủ.
7. Đăng nhập tình nguyện viên; mở **Ca của tôi** và xác nhận.

## Tình huống 2 — báo vắng và thay người

1. Từ **Ca của tôi**, nhập lý do và chọn **Báo vắng**.
2. Đăng nhập điều phối; mở **Phân công** và xem yêu cầu đang chờ. Người vắng không còn được tính là đang làm.
3. Thử chọn người thiếu kỹ năng/trùng lịch để thấy lý do từ chối.
4. Chọn người phù hợp; hệ thống tạo phân công thay, đóng yêu cầu và giữ bản ghi cũ ở trạng thái `replaced` trong một giao dịch.
5. Mở **Thống kê**, đối chiếu số cần/đã phân và tải CSV.

## Trình bày thuật toán

Chạy `bash lab.sh compare`, nêu rõ cả sáu kết quả dùng đúng từng snapshot/hash. Baseline ưu tiên thứ tự đăng ký; cải tiến ưu tiên ca khan hiếm và cân bằng tải. Cả hai có 0 vi phạm; cải tiến giảm chênh tải xuống 1 nhưng là heuristic, không cam kết tối ưu toàn cục.
