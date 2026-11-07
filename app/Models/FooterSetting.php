<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FooterSetting extends Model
{
    public const DEFAULTS = [
        'is_visible' => true,
        'brand_name' => 'TD Cinema',
        'company_name' => 'CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG',
        'description' => 'Trải nghiệm điện ảnh, kết nối những câu chuyện.',
        'address' => '180 Cao Lỗ, phường Chánh Hưng, TP. Hồ Chí Minh',
        'phone' => '1900 1234', 'email' => 'support@tdcinema.com',
        'support_title' => 'Hỗ trợ', 'editorial_title' => 'Góc điện ảnh', 'contact_title' => 'Liên hệ với chúng tôi',
        'copyright' => 'TD Cinema. All Rights Reserved. Designed for demonstration purposes.',
        'support_links' => [
            ['label' => 'Lịch chiếu phim', 'url' => '/movies'],
            ['label' => 'Khuyến mãi', 'url' => '/khuyen-mai'],
            ['label' => 'Thành viên & quyền lợi', 'url' => '/thanh-vien'],
            ['label' => 'Tài khoản', 'url' => '/profile'],
        ],
        'editorial_links' => [
            ['label' => 'Bình luận phim', 'url' => '/goc-dien-anh/review'],
            ['label' => 'Blog điện ảnh', 'url' => '/goc-dien-anh/blog'],
            ['label' => 'Hệ thống rạp', 'url' => '/rap-gia-ve'],
        ],
        'facebook_url' => null, 'instagram_url' => null, 'youtube_url' => null,
    ];

    protected $fillable = ['is_visible', 'brand_name', 'company_name', 'description', 'address', 'phone', 'email',
        'support_title', 'editorial_title', 'contact_title', 'copyright', 'support_links', 'editorial_links',
        'facebook_url', 'instagram_url', 'youtube_url'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'support_links' => 'array', 'editorial_links' => 'array'];
    }

    public static function current(): self
    {
        return static::find(1) ?? new static(self::DEFAULTS);
    }
}
