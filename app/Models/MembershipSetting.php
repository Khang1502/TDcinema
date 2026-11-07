<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipSetting extends Model
{
    protected $fillable = ['name','title','subtitle','earn_unit','is_enabled','terms'];
    protected $attributes = ['name'=>'TD Stars','title'=>'Mỗi lần xem phim, thêm một đặc quyền.','earn_unit'=>1000,'is_enabled'=>true];

    protected function casts(): array
    {
        return ['earn_unit'=>'integer','is_enabled'=>'boolean'];
    }

    public static function current(): self
    {
        return static::find(1) ?? new static;
    }
}
