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
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
