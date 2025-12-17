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
        'price',
    ];

    public function order()
    {
        return $this->belongsTo(FoodOrder::class, 'food_order_id');
    }

    public function foodItem()
    {
        return $this->belongsTo(FoodItem::class);
    }
}

