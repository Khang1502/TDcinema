<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MembershipLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_points',
        'discount_percent',
        'description', 'benefits', 'color', 'is_active',
    ];

    public const COLORS = [
        'blue'=>'Xanh dương', 'silver'=>'Bạc', 'gold'=>'Vàng', 'violet'=>'Tím',
        'platinum'=>'Bạch kim', 'diamond'=>'Kim cương', 'ruby'=>'Đỏ ruby',
        'emerald'=>'Xanh lá', 'orange'=>'Cam', 'rose'=>'Hồng', 'cyan'=>'Xanh băng',
        'indigo'=>'Chàm', 'bronze'=>'Đồng', 'navy'=>'Xanh navy', 'coral'=>'Cam san hô',
        'graphite'=>'Xám than',
    ];

    protected function casts(): array
    {
        return ['min_points'=>'integer','discount_percent'=>'integer','benefits'=>'array','is_active'=>'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('min_points')->orderBy('id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
