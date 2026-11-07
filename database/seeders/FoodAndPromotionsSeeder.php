<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FoodItem;
use App\Models\FoodOrder;
use App\Models\FoodOrderItem;
use App\Models\Promotion;
use App\Models\User;
use App\Models\Ticket;

class FoodAndPromotionsSeeder extends Seeder
{
    public function run(): void
    {
        FoodOrderItem::truncate();
        FoodOrder::truncate();
        FoodItem::truncate();
        Promotion::truncate();

        $combo = FoodItem::create([
            'name'        => 'Combo Bắp Nước Lớn',
            'description' => '1 bắp lớn + 2 nước ngọt',
            'price'       => 89000,
            'image'       => 'https://cdn.galaxycine.vn/media/2025/4/11/combo-2-big-1920x1320_1744354864910.jpg',
        ]);

        $promotion = Promotion::create([
            'name'         => 'Happy Day',
            'code'         => 'HAPPYDAY',
            'type'         => 'percent',
            'value'        => 20,
            'start_date'   => now()->subDay(),
            'end_date'     => now()->addDays(10),
            'usage_limit'  => 100,
            'status'       => 'active',
        ]);

        $user   = User::where('email', 'customer@example.com')->first();
        $ticket = Ticket::first();

        if ($user && $ticket) {
            $order = FoodOrder::create([
                'user_id'     => $user->id,
                'ticket_id'   => $ticket->id,
                'total_price' => 89000,
                'pickup_code' => 'F123',
                'status'      => 'pending',
            ]);

            FoodOrderItem::create([
                'food_order_id' => $order->id,
                'food_item_id'  => $combo->id,
                'quantity'      => 1,
                'price'         => 89000,
            ]);
        }
    }
}
