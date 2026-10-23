# Kịch bản trình diễn hoàn chỉnh

## Chuẩn bị

```bash
cd /home/lynn/project_6/app
bash lab.sh check
bash lab.sh run
```

Mở <http://127.0.0.1:8106>. Terminal thứ hai có thể chạy `bash lab.sh compare`.

`check` phải đạt hoàn toàn trước buổi demo. Nếu báo tài khoản web có DDL/ALL, cấu hình lại `volunteer_app` theo README; không bỏ qua cảnh báo này.

## Tình huống 1 — lập và công bố lịch

1. Đăng nhập `dieuphoi` / `Dieuphoi@123`; giới thiệu dashboard đọc số liệu MySQL.
2. Tạo hoạt động và hai ca. Một ca yêu cầu **Sơ cứu**, ca còn lại yêu cầu **Hậu cần**; thử số người `0` hoặc giờ đảo để thấy máy chủ từ chối. Sửa tên/giờ của một ca để trình diễn quản lý đầy đủ.
3. Đăng nhập `tinhnguyen`; mở **Tình nguyện viên** để xem hồ sơ, kỹ năng, lịch rảnh; đăng ký hoạt động.
4. Dùng thêm `tinhnguyen02` nếu cần ứng viên thứ hai.
5. Đăng nhập điều phối; mở **Phân công**, chọn hoạt động, chạy thuật toán cải tiến. Mở phần giải thích ca thiếu/người 0 ca.
6. Công bố phương án. Thử thêm người vào ca đã đủ để trình diễn kiểm tra sức chứa phía máy chủ.
7. Đăng nhập tình nguyện viên; mở **Ca của tôi** và xác nhận.
8. Trở lại vai trò điều phối, thử xóa ca/đóng hoạt động đang có phân công để thấy máy chủ từ chối an toàn. Tạo một hoạt động chưa có phân công để trình diễn đóng thành công.

## Tình huống 2 — báo vắng và thay người

1. Từ **Ca của tôi**, nhập lý do và chọn **Báo vắng**.
2. Đăng nhập điều phối; mở **Phân công** và xem yêu cầu đang chờ. Người vắng không còn được tính là đang làm.
3. Thử chọn người thiếu kỹ năng/trùng lịch để thấy lý do từ chối.
4. Chọn người phù hợp; hệ thống tạo phân công thay, đóng yêu cầu và giữ bản ghi cũ ở trạng thái `replaced` trong một giao dịch.
5. Mở **Thống kê**, đối chiếu số cần/đã phân và tải CSV.

6. Tạo thêm một yêu cầu vắng nhưng không có ứng viên phù hợp, chọn **Kết thúc chưa có người**; kiểm tra ca vẫn báo thiếu và yêu cầu xuất hiện trong lịch sử. Có thể chọn **Mở lại** để tiếp tục tìm người.

## Tình huống 3 — đối chứng và lịch sử vận hành

1. Trong **Phân công**, chọn **Chạy cặp đối chứng**. Hệ thống chạy hai thuật toán trên cùng snapshot thay vì chạy rời ở hai thời điểm.
2. Mở **Thống kê**, chỉ ra cùng mã nhóm, cùng hash snapshot và phiên bản mã; so sánh tỷ lệ lấp và chênh tải.
3. Xem bảng **Tình trạng từng ca** để chỉ ra ca đủ/thiếu, xác nhận và yêu cầu thay người; tải CSV tình trạng ca.
4. Dùng **Chuyển** ở một phân công hiện tại để chuyển sang ứng viên khác. Nếu ứng viên thiếu kỹ năng, trùng lịch hoặc đã báo vắng ca đó, toàn bộ giao dịch bị từ chối.
5. Mở **Kho hoạt động**, **Lịch sử phân công** và **Lịch sử xử lý** để trình bày dữ liệu đã kết thúc vẫn được giữ.

## Trình bày thuật toán

Chạy `bash lab.sh compare`, nêu rõ cả sáu kết quả dùng đúng từng snapshot/hash. Baseline ưu tiên thứ tự đăng ký; cải tiến ưu tiên ca khan hiếm và cân bằng tải. Cả hai có 0 vi phạm; cải tiến giảm chênh tải xuống 1 nhưng là heuristic, không cam kết tối ưu toàn cục.
