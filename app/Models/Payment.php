<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'food_order_id',
        'amount',
        'method',
        'status',
        'paid_at',
    ];

    protected $dates = [
        'paid_at',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function foodOrder()
    {
        return $this->belongsTo(FoodOrder::class);
    }
}

