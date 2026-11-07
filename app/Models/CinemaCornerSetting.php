<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CinemaCornerSetting extends Model
{
    public const DEFAULTS = [
        'title' => 'Góc Điện Ảnh',
        'eyebrow' => 'Khám phá những câu chuyện mới',
        'description' => 'Thông tin phim, nội dung nổi bật và trailer dành cho bạn.',
        'featured_label' => 'Phim nổi bật',
        'link_label' => 'Khám phá tất cả',
        'link_url' => '/movies?status=all',
        'list_limit' => 3,
        'is_active' => true,
    ];

    protected $fillable = ['title', 'eyebrow', 'description', 'featured_label', 'link_label', 'link_url', 'list_limit', 'is_active', 'featured_item_id', 'blog_featured_item_id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'list_limit' => 'integer', 'featured_item_id' => 'integer', 'blog_featured_item_id' => 'integer'];
    }

    public static function current(): self
    {
        return static::find(1) ?? new static(self::DEFAULTS);
    }
}
