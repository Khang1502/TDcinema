<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;

    public const EXPIRED_REASON = 'Vé quá hạn';

    protected $fillable = [
        'user_id',
        'sold_by',
        'sales_channel',
        'showtime_id',
        'seat_id',
        'price',
        'qr_code',
        'status',
        'cancellation_reason',
        'booked_at',
    ];

    protected $dates = [
        'booked_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by')->withTrashed();
    }

    public function showtime()
    {
        return $this->belongsTo(Showtime::class);
    }

    public function seat()
    {
        return $this->belongsTo(Seat::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function foodOrders()
    {
        return $this->hasMany(FoodOrder::class);
    }
}
