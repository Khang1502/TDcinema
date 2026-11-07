<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Support\ImageAsset;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'value',
        'start_date',
        'end_date',
        'usage_limit',
        'status',
        'thumbnail',
        'show_on_home',
        'sort_order',
        'is_member_gift', 'min_member_points', 'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'value' => 'integer',
        'usage_limit' => 'integer',
        'show_on_home' => 'boolean',
        'sort_order' => 'integer',
        'is_member_gift' => 'boolean', 'min_member_points' => 'integer',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())
            ->where(fn (Builder $query) => $query->whereNull('usage_limit')->orWhere('usage_limit', '>', 0));
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', 'active')->whereDate('start_date', '>', today())
            ->where(fn (Builder $query) => $query->whereNull('usage_limit')->orWhere('usage_limit', '>', 0));
    }

    public function scopeEligibleFor(Builder $query, ?User $user): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('is_member_gift', false)
            ->when($user && $user->role === 'customer', fn (Builder $q) => $q->orWhere('min_member_points', '<=', max(0, (int) $user->points))));
    }

    public function discountFor(float $total): int
    {
        $total = max(0, $total);
        $discount = $this->type === 'percent' ? $total * min(100, max(0, $this->value)) / 100 : max(0, $this->value);

        return (int) round(min($total, $discount));
    }

    public function getDiscountLabelAttribute(): string
    {
        return $this->type === 'percent' ? $this->value.'%' : number_format($this->value, 0, ',', '.').' đ';
    }

    public function getDisplayStatusAttribute(): string
    {
        if ($this->status !== 'active' || $this->end_date->lt(today())) return 'expired';
        if ($this->usage_limit !== null && $this->usage_limit <= 0) return 'exhausted';

        return $this->start_date->gt(today()) ? 'upcoming' : 'active';
    }

    public function imageUrl(): ?string
    {
        return ImageAsset::exists($this->thumbnail) ? ImageAsset::url($this->thumbnail) : null;
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }
}
