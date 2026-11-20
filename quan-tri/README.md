# THE VICTOR · ĐƠN WEB / BẢNG ĐƠN

PHP/SQLite trên VinaHost. BẢNG ĐƠN dùng Univer 1.5.2 tự host, không phụ thuộc dịch vụ Google.

Menu Tệp, Chỉnh sửa, Xem, Chèn, Định dạng, Dữ liệu, Công cụ; thanh định dạng và công thức; sửa trực tiếp trong ô, copy/paste vùng, nhiều tab, hoàn tác/làm lại, bộ lọc theo giá trị/màu/điều kiện, sắp xếp cả hàng, tìm/thay thế, xác thực dữ liệu và định dạng có điều kiện. Nút Làm mới thay Mở bản mới. Giao diện không còn hướng dẫn dài và công cụ tải/mở JSON.

ĐƠN WEB là nguồn gốc, nhân viên không được sửa/xóa thông tin nguồn. Quyền riêng kiểm soát đổi trạng thái và LÊN BẢNG. LÊN BẢNG ghi đúng cột theo tiêu đề, vào dòng trống, chống trùng trong cùng giao dịch và giữ số 0 của SĐT. STT ở đầu BẢNG ĐƠN. Bảng làm việc độc lập, không gọi API vận chuyển.

## Nhóm và quyền

QUẢN LÝ mặc định có toàn bộ quyền ứng dụng. TRỰC PAGE: xem, sửa bảng, đổi trạng thái, lên bảng. CARE ĐƠN: như TRỰC PAGE và xem lịch sử. Đây là cấu hình nhóm, không tự gán tài khoản hiện hữu vào nhóm. Nhân viên nhận hợp của quyền nhóm và quyền bổ sung riêng; quyền gốc cũ được giữ, không tự cấp các quyền mới.

Các quyền: view, edit, inbox_status, board, export, history, restore, manage_staff, manage_groups, analytics. Xuất CSV và lịch sử được kiểm tra tại máy chủ, không chỉ ẩn nút. Khôi phục cần edit + history + restore. Quản lý nhân viên cần manage_staff; cấu hình nhóm cần manage_groups. Người quản lý được ủy quyền không cấp/quản lý quyền vượt quyền mình, không tự sửa tài khoản hoặc nhóm của mình. Đổi quyền thu hồi phiên đăng nhập liên quan.

CSV từ snapshot đã lưu trên máy chủ, có BOM UTF-8 và chống công thức chèn từ văn bản. Người được xem vẫn có thể copy dữ liệu hiển thị.

## Lưu trữ và giới hạn

Snapshot SQLite riêng ngoài public_html, 30 phiên bản lịch sử, kiểm tra phiên bản khi lưu để ngăn ghi đè. Khi xung đột giữ nội dung trên màn hình và dừng tự lưu. Tối đa 8 MB, 150.000 ô có dữ liệu, 20 tab, 50.000 hàng và 512 cột/tab. Chưa có cộng tác con trỏ trực tiếp, Apps Script, biểu đồ/pivot và nhập/xuất XLSX gốc; không tuyên bố đầy đủ mọi tính năng Google Sheets.

## Triển khai

Chạy deploy-inbox.sh COMMIT_SHA. PHP lint + test-workbook.php + test-sheet.php + test-inbox.php + test-team.php phải đạt trước khi build và triển khai. Script sao lưu mã, orders.sqlite và admin-auth.sqlite ngoài webroot. Không commit cấu hình bí mật hay dữ liệu khách hàng.
