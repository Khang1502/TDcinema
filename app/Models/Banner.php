<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\ImageAsset;

class Banner extends Model
{
    public const PLACEMENTS = ['home' => 'Trang chủ', 'cinemas' => 'Rạp/Giá vé'];

    protected $fillable = ['title', 'image_path', 'movie_id', 'cinema_id', 'placement', 'link_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    public function cinema(): BelongsTo
    {
        return $this->belongsTo(Cinema::class);
    }

    public function scopeForPlacement($query, string $placement)
    {
        return $query->where('placement', $placement)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('cinema_id')->orWhereHas('cinema', fn ($cinema) => $cinema->where('status', 'active')))
            ->orderBy('sort_order')->orderBy('id');
    }

    public function getPlacementLabelAttribute(): string
    {
        return self::PLACEMENTS[$this->placement] ?? self::PLACEMENTS['home'];
    }

    public function imageUrl(): string
    {
        return ImageAsset::url($this->image_path, true) ?? asset('assets/images/ui/cinema-placeholder.svg');
    }

    public function destinationUrl(): ?string
    {
        if ($this->link_url) {
            return $this->link_url;
        }

        if ($this->cinema) {
            $region = $this->cinema->region_key;

            return route('cinemas.index', isset(\App\Support\CinemaRegions::LABELS[$region]) ? ['region' => $region] : []).'#cinema-'.$this->cinema_id;
        }

        return $this->movie_id ? route('movies.show', $this->movie_id) : null;
    }
}
