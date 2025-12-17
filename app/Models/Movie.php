<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'poster_path',
        'banner_path',
        'description',
        'genre',
        'duration',
        'director',
        'actors',
        'trailer_url',
        'release_date',
        'status',
        'rating_avg',
        'review_count',
    ];

    public function showtimes()
    {
        return $this->hasMany(Showtime::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
