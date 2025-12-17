<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cinema extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'city',
        'status',
    ];

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function users()
    {
        return $this->hasMany(User::class); // staff, manager thuộc rạp
    }
}

