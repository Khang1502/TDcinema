<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Showtime extends Model
{
    use HasFactory;

    protected $fillable = [
        'movie_id',
        'room_id',
        'start_time',
        'end_time',
        'base_price',
        'status',
        'source',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'base_price' => 'decimal:2',
        ];
    }

    public function movie()
    {
        return $this->belongsTo(Movie::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
