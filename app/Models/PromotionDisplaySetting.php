<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromotionDisplaySetting extends Model
{
    public const DEFAULTS = ['title'=>'Khuyến mãi & ưu đãi','eyebrow'=>'Xem phim với giá tốt hơn',
        'display_mode'=>'banner','is_visible'=>true,'autoplay'=>true,'interval_seconds'=>5];

    protected $fillable = ['title','eyebrow','display_mode','is_visible','autoplay','interval_seconds'];

    protected function casts(): array
    {
        return ['is_visible'=>'boolean','autoplay'=>'boolean','interval_seconds'=>'integer'];
    }

    public static function current(): self
    {
        return static::find(1) ?? new static(self::DEFAULTS);
    }
}
