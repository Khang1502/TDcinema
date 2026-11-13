# Bán vé và bắp nước tại quầy

## Nghiệp vụ

- Nhân viên có quyền `tickets.manage` mở **Vé & check-in → Quản lý vé → Bán vé tại quầy**. Chọn phim đang chiếu, suất còn mở bán, tối đa 8 ghế, khách vãng lai hoặc thành viên. Thành viên có thể tìm theo tên và áp dụng ưu đãi khả dụng. Có thể mua kèm bắp nước, tối đa 10 phần cho mỗi sản phẩm. Khi xác nhận đã thu tiền mặt, server kiểm lại suất, ghế, hold, giá và ưu đãi rồi tạo vé, payment và hóa đơn trong một transaction. Mỗi ghế có một vé, còn các vé cùng lượt có chung mã check-in.
- Nhân viên có quyền `food-sales.manage` mở **Vé & check-in → Quản lý bắp nước → Bán tại quầy**. Chọn sản phẩm và số lượng, xác nhận thu tiền mặt. Hệ thống lưu đơn độc lập, từng dòng và đơn giá tại lúc bán, payment, nhân viên và mã nhận đồ. Đơn độc lập không cần vé, suất chiếu, ghế hay tài khoản.
- Trang quản lý bắp nước hiển thị cả đơn online mua cùng vé, combo mua cùng vé tại quầy và đơn bắp nước độc lập. Nhân viên có thể giao một số phần của từng dòng hoặc giao toàn bộ phần còn lại của đơn; `fulfilled_quantity` và `fulfilled_at` lưu tiến độ. Check-in vé dùng mã chung để cập nhật toàn bộ ghế hợp lệ còn lại trong lượt đặt; không có thao tác check-in từng vé riêng.
- Mã `sale_token` của form giúp lần gửi lại cùng giao dịch không tạo thêm vé/đơn. Cần mở form mới cho giao dịch tiếp theo.

## Dữ liệu và tương thích

Migration `2026_11_07_000001_add_counter_sales_support` cho `tickets.user_id`, `food_orders.user_id`, `payments.ticket_id` và `invoices.user_id` được NULL. Bổ sung `tickets.sold_by`, `tickets.sales_channel`, `food_orders.sold_by`, `food_orders.sale_token`, `food_orders.fulfilled_at`, `payments.food_order_id`. Các bản ghi cũ vẫn giữ nguyên. `food_orders.ticket_id` đã có thể NULL từ trước.

Mỗi vé tại quầy có một payment như vé online. Một invoice ghi tổng giao dịch cho nhiều ghế và làm cơ sở cộng Stars một lần cho thành viên. Đơn bắp nước có các dòng `food_order_items`, mã nhận đồ riêng và payment nối bằng `food_order_id`; đơn độc lập không đưa vào thống kê vé/phim/rạp. Báo cáo doanh thu hiển thị riêng doanh thu bắp nước độc lập và tổng vé + bắp nước.

## Chạy và demo

1. `composer install`, `npm.cmd ci`, cấu hình `.env` theo `.env.example`, kiểm tra `php artisan migrate:status`, chạy `php artisan migrate` trên database phù hợp, `npm.cmd run build`, `php artisan serve`.
2. Đăng nhập nhân viên, tạo sẵn phim/phòng/ghế/suất chiếu hợp lệ bằng tài khoản quản trị.
3. Bán hai ghế cho khách vãng lai, mở mã check-in chung và tra cứu từng vé. Lặp lại với tài khoản thành viên để xem ưu đãi và Stars; thêm combo rồi kiểm đơn bắp nước đi kèm.
4. Tạo sản phẩm nước suối trạng thái `available`; bán nhiều phần tại **Quản lý bắp nước**, mở đơn theo mã nhận, giao một phần rồi giao hết số còn lại. Kiểm cả đơn mua online cùng vé.
5. Chạy `php artisan test` để kiểm tra luồng mới và hồi quy.

## Phạm vi

Chỉ ghi nhận tiền mặt đã thu tại quầy, không tích hợp máy POS, in vé, kho hay thanh toán ngân hàng. `Showtime` do quản trị viên tạo trước; khi đã có vé thì chỉ được đổi trạng thái, không đổi phim/phòng/thời gian/giá. Doanh thu bắp nước độc lập không được phân bổ về rạp cụ thể vì đơn hiện chưa chọn rạp.
