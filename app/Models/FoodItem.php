<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Support\ImageAsset;

class FoodItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'status',
    ];

    public function imageUrl(): ?string
    {
        return ImageAsset::exists($this->image) ? ImageAsset::url($this->image) : null;
    }

    public function orderItems()
    {
        return $this->hasMany(FoodOrderItem::class);
    }
}
