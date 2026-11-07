<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ticket_id',
        'promotion_id',
        'total_amount',
        'invoice_code',
        'membership_level_name', 'membership_discount', 'points_earned',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }
}
