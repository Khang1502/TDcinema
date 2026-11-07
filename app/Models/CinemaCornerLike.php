<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CinemaCornerLike extends Model
{
    protected $fillable = ['cinema_corner_item_id', 'user_id'];
}
