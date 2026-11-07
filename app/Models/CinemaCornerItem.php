<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Support\ImageAsset;
use Illuminate\Support\Str;

class CinemaCornerItem extends Model
{
    public const CATEGORIES = ['review' => 'Bình luận phim', 'blog' => 'Blog điện ảnh'];

    protected $fillable = ['movie_id', 'title', 'excerpt', 'content', 'category', 'image_path', 'trailer_url', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer', 'movie_id' => 'integer', 'views_count' => 'integer'];
    }

    public function movie(): BelongsTo
    {
        return $this->belongsTo(Movie::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (self $item) {
            $item->likes()->delete();
            CinemaCornerSetting::where('featured_item_id', $item->id)->update(['featured_item_id' => null]);
            CinemaCornerSetting::where('blog_featured_item_id', $item->id)->update(['blog_featured_item_id' => null]);
        });
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->movie?->title ?? 'Phim đã được xóa');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(CinemaCornerLike::class);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_active', true)->where(function (Builder $query) {
            $query->where('category', 'blog')
                ->orWhereHas('movie', fn ($movie) => $movie->whereIn('status', ['now_showing', 'upcoming', 'imax']))
                ->orWhere(fn ($standalone) => $standalone->whereNull('movie_id')->whereNotNull('title')->whereNotNull('content'));
        });
    }

    public function articleContent(): string
    {
        return trim(strip_tags($this->content ?: ($this->movie?->description ?: $this->excerpt ?: 'Nội dung đang được cập nhật.')));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category ?? 'review'] ?? self::CATEGORIES['review'];
    }

    public function displayExcerpt(int $limit = 240): string
    {
        return Str::limit(trim(strip_tags($this->excerpt ?: ($this->content ?: $this->movie?->description ?: $this->movie?->genre ?? ''))), $limit);
    }

    public function editorExcerpt(): string
    {
        if (filled($this->excerpt)) return $this->excerpt;
        return Str::substr($this->displayExcerpt(PHP_INT_MAX), 0, 2000);
    }

    public function editorContent(): string
    {
        if (filled($this->content)) return $this->content;
        return Str::substr(trim(strip_tags($this->content ?: ($this->movie?->description ?: $this->excerpt ?: ''))), 0, 15000);
    }

    public function imagePath(bool $featured = false): ?string
    {
        return $this->image_path ?: ($featured ? ($this->movie?->banner_path ?: $this->movie?->poster_path) : ($this->movie?->poster_path ?: $this->movie?->banner_path));
    }

    public function imageUrl(bool $featured = false): string
    {
        return ImageAsset::url($this->imagePath($featured), true) ?? asset('assets/images/ui/movie-placeholder.svg');
    }

    public function trailerSource(): ?string
    {
        return $this->trailer_url ?: $this->movie?->trailer_source;
    }
}
