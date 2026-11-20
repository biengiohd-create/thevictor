# Quản trị THE VICTOR trên VinaHost

Mã quản trị này được lấy từ hosting đang chạy rồi bổ sung bảng nhập đơn và tài khoản nhân viên. Website trên nhánh main hiện chưa chứa toàn bộ API của hosting. Không triển khai toàn bộ repo đè lên public_html.

## Sử dụng

Mở `/quan-tri/sheet.php`, dùng tài khoản chủ shop hiện có. Bấm đúp hoặc F2 để sửa ô, Shift+bấm để chọn vùng, Ctrl+C/Ctrl+V để trao đổi dữ liệu với Excel/Google Sheets. Bấm **Lưu thay đổi** để gửi dữ liệu lên máy chủ. Có hoàn tác cho dữ liệu chưa lưu; không tự lưu ngầm.

Các cột: Tiền, Tên khách, SĐT, Địa chỉ, Sản phẩm/size, Note, Check size, Tạo đơn, Chiều cao/cân nặng, Tình trạng, Mã vận đơn, Hoàn. Cột Tạo đơn có dữ liệu nghĩa là nhân viên đã tạo và đẩy đơn sang đơn vị vận chuyển. Hệ thống chỉ ghi nhận; không gọi API vận chuyển. Khi sửa ô sản phẩm, nội dung được lưu thành một dòng hàng tự nhập; website không tự tra SKU, size, giá hoặc tồn kho cho dòng tự nhập này.

Tiền là tổng COD theo đơn, SĐT là chuỗi giữ số 0 đầu. Dán bắt đầu từ ô đã chọn, không bao gồm STT. Tối đa 200 dòng mỗi lần lưu, bảng tải 100 đơn/trang. CSV xuất theo bộ lọc, tối đa 10.000 đơn. Excel có thể tự đổi kiểu SĐT khi mở CSV; hãy nhập cột SĐT dưới dạng văn bản.

## Phân quyền

Chủ shop mở **Quản lý nhân viên** để tự tạo tài khoản, cấp quyền xem, tạo, sửa, xuất dữ liệu, xóa/khôi phục hoặc thống kê. Nhân viên có quyền xem toàn bộ đơn hàng. Mỗi lần đổi quyền hoặc khóa tài khoản sẽ thu hồi phiên cũ. Quyền được kiểm tra tại cả API cũ và API bảng tính. Không có tài khoản hay mật khẩu mặc định.

## Dữ liệu

Giữ nguyên `orders.sqlite` và cấu hình chủ shop trong `/home/twjmlulv/thevictor-private`, bên ngoài public_html. Bảng `order_sheet` lưu các trường vận hành mới, `order_sheet_audit` lưu các lần sửa với người sửa/thời gian, `staff` trong `admin-auth.sqlite` lưu tài khoản với password_hash. Cập nhật dùng version chung với trang chi tiết cũ. Nếu một dòng đã đổi, cả đợt lưu bị rollback, nội dung trên màn hình được giữ nguyên để người nhập sao chép và tải lại.

Không commit tệp cấu hình tài khoản, SQLite, phiên đăng nhập hay dữ liệu khách hàng lên GitHub.

## Triển khai có kiểm thử và sao lưu

`deploy-admin-sheet.sh COMMIT_SHA` chạy trên hosting. Script lấy commit cố định từ GitHub, kiểm tra cú pháp PHP và kiểm thử bằng cơ sở dữ liệu giả riêng, sao lưu mã quản trị và SQLite, sau đó thay 9 tệp quản trị. Không cấu hình tự động triển khai mọi commit chưa kiểm thử. Script nằm ngoài thư mục website khi cài trên hosting.

## Kiểm thử

PHP 8.1+, PDO SQLite, SQLite3 và mbstring. `php test-sheet.php` chỉ chạy CLI, tạo dữ liệu giả trong thư mục tạm riêng, kiểm tra tạo đơn, SĐT, trường vận chuyển, rollback khi xung đột, quyền nhân viên và thu hồi phiên. Không chạy trên cơ sở dữ liệu thật.
