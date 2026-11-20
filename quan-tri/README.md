# THE VICTOR · Bảng tính nhân viên v2

Hai khu hiện tại được giữ: /quan-tri/ nhận đơn website; /quan-tri/sheet.php là sổ tính nhân viên dùng bộ máy Univer 1.5.2 tự host. Mã trong workbook-src được build bằng npm ci rồi Vite; không dùng dịch vụ Google hay mô-đun Univer thương mại.

## Thao tác

Chọn ô rồi gõ thay nội dung; bấm đúp hoặc F2 đặt con trỏ sửa từng ký tự. Có thanh công thức, SUM/IF/COUNTIF/VLOOKUP và các hàm bộ máy hỗ trợ, dán vùng, kéo điền, định dạng chữ/màu/viền, gộp ô, chèn/xóa hàng cột, bộ lọc/sắp xếp, tìm/thay thế, xác thực dữ liệu, định dạng có điều kiện, nhiều tab và hoàn tác/làm lại. Tự lưu sau khi kết thúc sửa ô và ngừng thao tác 3 giây. Nút Lưu ngay cũng kết thúc sửa ô trước khi chụp sổ.

## Đơn website và bản làm việc

Lần mở đầu tiên sao chép tối đa 10.000 đơn hiện có, bao gồm trường vận hành đã nhập ở v1. Nút Nhận đơn website mới thêm những đơn chưa nhận. Mã đơn đã nhận được theo dõi riêng nên không nhập lặp dòng mà nhân viên đã xóa. Bảng nhân viên là bản làm việc độc lập: sửa hoặc xóa hàng không sửa hoặc xóa đơn website gốc. Tạo đơn có nội dung vẫn có nghĩa là đã tạo và đẩy vận chuyển; không tự gọi đơn vị giao hàng.

## Quyền, lưu trữ và phục hồi

Dùng tài khoản chủ shop và nhân viên hiện có. Quyền view được đọc bảng; edit được sửa sổ bao gồm thêm/xóa ô, hàng, cột, tab. Quyền xuất kiểm soát nút CSV/JSON; người có quyền xem vẫn có thể copy dữ liệu họ nhìn thấy. Quản lý nhân viên và khôi phục phiên bản chỉ dành cho chủ shop.

Sổ được lưu thành snapshot JSON (gồm công thức, rich text, định dạng, thứ tự tab và tài nguyên) vào bảng staff_workbook trong SQLite riêng ngoài public_html. Có 30 phiên bản cũ. Kiểm tra version ở máy chủ ngăn ghi đè phiên bản mới của người khác; đây chưa phải cộng tác con trỏ trực tiếp như Google Sheets. Khi xung đột, dừng tự lưu và giữ nguyên dữ liệu đang sửa; tải JSON rồi mở bản mới. Mỗi sổ tối đa 8 MB/150.000 ô có dữ liệu, 20 tab; mỗi tab tối đa 50.000 hàng và 512 cột.

CSV chỉ có giá trị hiển thị của tab hiện tại; JSON giữ cả công thức và định dạng. Không hỗ trợ Apps Script, hàm dịch vụ riêng của Google, biểu đồ/pivot và nhập/xuất XLSX gốc trong bản này. Không tuyên bố tương thích 100% Google Sheets.

## Triển khai

Chạy deploy-workbook.sh COMMIT_SHA trên hosting. Script tải commit cố định, chạy kiểm thử PHP với SQLite trong bộ nhớ, npm ci theo lockfile, build asset tự host, tạo thông báo giấy phép, sao lưu mã quản trị và SQLite rồi triển khai riêng phần bảng tính. Các tệp cấu hình bí mật, cơ sở dữ liệu và dữ liệu khách hàng không được commit. Bản v1 được ghi lại trong README-v1.md.
