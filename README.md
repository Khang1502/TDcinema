# TD Cinema

Ứng dụng đặt vé và quản lý rạp chiếu phim xây dựng bằng Laravel 12. Khách có thể xem phim, chọn suất, giữ ghế, đặt vé và bắp nước; nhân viên quản lý bán tại quầy, check-in và báo cáo.

## Chức năng chính

- Trang khách: phim đang chiếu/sắp chiếu, Phim hay tháng, rạp và suất chiếu, ưu đãi, thành viên, lịch sử vé và mã check-in.
- Đặt vé: tối đa 8 ghế mỗi lượt, tối đa 10 phần cho mỗi sản phẩm bắp nước. Các ghế cùng lượt dùng chung mã check-in; đơn bắp nước có mã nhận riêng.
- Vé & check-in: quản lý vé và bắp nước online/tại quầy, bán tại quầy, check-in cả lượt vé, giao từng phần hoặc toàn bộ đơn bắp nước.
- Quản trị: phim, thể loại, rạp, phòng, ghế, suất chiếu, Phim hay tháng, ưu đãi, phương thức thanh toán, lịch sử thanh toán, báo cáo, tài khoản/quyền và nội dung website.

## Yêu cầu môi trường

- PHP 8.2 trở lên cùng các extension theo `composer check-platform-reqs`
- Composer, Node.js và npm
- Database được cấu hình trong `.env` (file mẫu dùng SQLite; có thể cấu hình MySQL)

## Cài đặt trên Windows PowerShell

```powershell
cd D:\TDcinema
composer install
npm.cmd ci
Copy-Item .env.example .env
php artisan key:generate
```

Chỉnh `APP_URL` và các biến `DB_*` trong `.env` theo máy. Nếu dùng SQLite, tạo file `database/database.sqlite` trước khi migrate. Với database đã có dữ liệu, sao lưu và kiểm tra trạng thái migration trước khi chạy tiếp; không tạo lại `APP_KEY` của bản đang dùng.

```powershell
php artisan migrate:status
php artisan migrate
php artisan storage:link
npm.cmd run build
php artisan serve --host=127.0.0.1 --port=8765
```

Mở `http://127.0.0.1:8765/`. Nếu cổng 8765 đang được dùng, chọn cổng khác và cập nhật `APP_URL`. Để phát triển giao diện, chạy `npm.cmd run dev` trong một terminal khác.

## Kiểm tra

```powershell
php artisan test
npm.cmd run build
```

Bộ test dùng cấu hình riêng trong `phpunit.xml`; kết quả test không thay thế việc kiểm tra migration và thanh toán trên database/môi trường vận hành. Seeder có thể thay đổi dữ liệu mẫu, vì vậy cần xem nội dung seeder trước khi chạy trên database đang sử dụng.

## Tài liệu

- [Bán vé và bắp nước tại quầy](docs/counter-sales.md)
- [Nguồn ảnh phương thức thanh toán](docs/payment-method-logos.md)

`.env`, dependency, file build, log, cache và file tải lên trong `storage` được loại khỏi Git. Mã nguồn không chứa dữ liệu khách hàng hoặc khóa truy cập của môi trường chạy.
