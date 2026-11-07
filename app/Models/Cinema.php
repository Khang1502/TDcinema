<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\CinemaRegions;
use App\Support\ImageAsset;

class Cinema extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'region',
        'status',
        'image_path',
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $cinema) {
            Banner::where('cinema_id', $cinema->id)->update(['cinema_id' => null]);
        });
    }

    public function hasImage(): bool
    {
        return ImageAsset::exists($this->image_path);
    }

    public function imageUrl(): string
    {
        if (! $this->hasImage()) {
            return asset('assets/images/ui/cinema-placeholder.svg');
        }

        return ImageAsset::url($this->image_path, true);
    }

    public function getRegionKeyAttribute(): string
    {
        return isset(CinemaRegions::LABELS[$this->region])
            ? $this->region
            : CinemaRegions::infer($this->city);
    }

    public function getRegionLabelAttribute(): string
    {
        return CinemaRegions::LABELS[$this->region_key] ?? 'Chưa phân khu vực';
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function users()
    {
        return $this->hasMany(User::class); // staff, manager thuộc rạp
    }
}
