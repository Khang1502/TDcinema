<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Support\ImageAsset;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'poster_path',
        'banner_path',
        'description',
        'genre',
        'age_rating',
        'duration',
        'director',
        'actors',
        'trailer_url',
        'release_date',
        'status',
        'is_imax',
        'source',
        'external_id',
        'source_url',
        'rating_avg',
        'review_count',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $movie) {
            if ($movie->isDirty('genre')) $movie->genre = \App\Support\MovieGenres::resolveAliases($movie->genre);
        });
        static::saved(function (self $movie) {
            if ($movie->wasRecentlyCreated || $movie->wasChanged('genre')) \App\Support\MovieGenres::register($movie->genre);
        });
    }

    public function posterUrl(): string
    {
        return ImageAsset::exists($this->poster_path) ? ImageAsset::url($this->poster_path) : asset('assets/images/ui/movie-placeholder.svg');
    }

    public function bannerUrl(): string
    {
        return ImageAsset::exists($this->banner_path) ? ImageAsset::url($this->banner_path) : $this->posterUrl();
    }

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    protected function casts(): array
    {
        return [
            'is_imax' => 'boolean',
            'release_date' => 'date',
        ];
    }

    public function scopeImax($query)
    {
        return $query->where(function ($imaxQuery) {
            $imaxQuery->where('is_imax', true)->orWhere('status', 'imax');
        });
    }

    public function getTrailerSourceAttribute(): ?string
    {
        $trailer = trim((string) $this->trailer_url);
        if ($trailer === '') {
            return null;
        }

        if (Str::startsWith($trailer, ['http://', 'https://', '//'])) {
            return $trailer;
        }

        if (Str::startsWith($trailer, '/storage/')) {
            return url($trailer);
        }

        return Storage::disk('public')->url(ltrim($trailer, '/'));
    }

    public function hasUploadedTrailer(): bool
    {
        return str_starts_with((string) $this->trailer_url, 'movies/trailers/');
    }
}
