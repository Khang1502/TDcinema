<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

class FoodOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'sold_by',
        'sale_token',
        'fulfilled_at',
        'ticket_id',
        'total_price',
        'pickup_code',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    protected function casts(): array
    {
        return ['fulfilled_at' => 'datetime'];
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'sold_by')->withTrashed();
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function items()
    {
        return $this->hasMany(FoodOrderItem::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereHas('payment', fn (Builder $payment) => $payment->where('status', 'paid'))
                ->orWhere(function (Builder $withoutOwnPayment) {
                    $withoutOwnPayment->whereDoesntHave('payment')
                        ->whereHas('ticket.payment', fn (Builder $payment) => $payment->where('status', 'paid'));
                });
        });
    }

    public function scopeReadyForPickup(Builder $query): Builder
    {
        return $query->where('status', 'completed')->whereNull('fulfilled_at')->paid();
    }

    public function isPaid(): bool
    {
        return $this->payment
            ? $this->payment->status === 'paid'
            : $this->ticket?->payment?->status === 'paid';
    }

    public function isReadyForPickup(): bool
    {
        return $this->status === 'completed' && $this->fulfilled_at === null && $this->isPaid();
    }

    public function fulfilledQuantity(): int
    {
        return (int) $this->items->sum('fulfilled_quantity');
    }

    public function totalQuantity(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
