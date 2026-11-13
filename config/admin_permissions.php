<?php

return [
    'roles' => [
        'staff' => [
            'label' => 'Basic admin',
            'description' => 'Nhân viên vận hành vé và check-in hằng ngày.',
            'permissions' => ['dashboard.view', 'tickets.manage', 'food-sales.manage'],
        ],
        'manager' => [
            'label' => 'Manager admin',
            'description' => 'Quản lý nội dung, lịch chiếu, vận hành và báo cáo.',
            'permissions' => [
                'dashboard.view', 'reports.view', 'payments.manage', 'payment-methods.manage', 'tickets.manage', 'movies.manage',
                'showtimes.manage', 'cinemas.manage', 'rooms.manage', 'seats.manage',
                'promotions.manage', 'foods.manage', 'food-sales.manage', 'users.view', 'banners.manage', 'cinema-corner.manage', 'membership.manage', 'footer.manage', 'header.manage', 'gifts.manage',
            ],
        ],
        'admin' => [
            'label' => 'Super admin',
            'description' => 'Toàn quyền hệ thống và phân quyền tài khoản quản trị.',
            'permissions' => ['*'],
        ],
    ],
    'features' => [
        'dashboard.view' => 'Xem bảng điều khiển',
        'reports.view' => 'Xem và xuất báo cáo',
        'payments.manage' => 'Lịch sử thanh toán: tra cứu và xuất giao dịch',
        'payment-methods.manage' => 'Cấu hình phương thức thanh toán',
        'tickets.manage' => 'Quản lý vé & check-in',
        'movies.manage' => 'Quản lý phim & thể loại',
        'banners.manage' => 'Quản lý banner',
        'footer.manage' => 'Quản lý nội dung footer',
        'header.manage' => 'Quản lý header & thông tin website',
        'gifts.manage' => 'Quản lý quà tặng thành viên',
        'cinema-corner.manage' => 'Quản lý Góc Điện Ảnh',
        'showtimes.manage' => 'Quản lý suất và lịch chiếu',
        'cinemas.manage' => 'Quản lý hệ thống rạp',
        'rooms.manage' => 'Quản lý phòng chiếu',
        'seats.manage' => 'Quản lý sơ đồ ghế',
        'promotions.manage' => 'Quản lý khuyến mãi',
        'membership.manage' => 'Quản lý thành viên, cấp bậc & quyền lợi',
        'foods.manage' => 'Quản lý dịch vụ & đồ ăn',
        'food-sales.manage' => 'Quản lý, bán và check-in bắp nước online và tại quầy',
        'users.view' => 'Xem danh sách người dùng',
        'users.manage' => 'Tạo, sửa, khóa và xóa người dùng',
        'permissions.manage' => 'Phân quyền tài khoản admin',
    ],
];
