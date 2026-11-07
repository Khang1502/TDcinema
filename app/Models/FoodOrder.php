<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FoodOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'total_price',
        'pickup_code',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function items()
    {
        return $this->hasMany(FoodOrderItem::class);
    }
}
