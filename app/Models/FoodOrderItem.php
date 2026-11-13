<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FoodOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'food_order_id',
        'food_item_id',
        'quantity',
        'fulfilled_quantity',
        'fulfilled_at',
        'price',
    ];

    protected function casts(): array
    {
        return ['fulfilled_quantity' => 'integer', 'fulfilled_at' => 'datetime'];
    }

    public function remainingQuantity(): int
    {
        return max(0, (int) $this->quantity - (int) $this->fulfilled_quantity);
    }

    public function order()
    {
        return $this->belongsTo(FoodOrder::class, 'food_order_id');
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }
}

