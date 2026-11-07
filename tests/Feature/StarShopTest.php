<?php

namespace Tests\Feature;

use App\Models\FoodItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_shop_displays_only_available_products(): void
    {
        $available = FoodItem::create([
            'name' => 'Combo Star đặc biệt',
            'description' => 'Bắp rang và hai phần nước.',
            'price' => 129000,
            'status' => 'available',
        ]);
        $unavailable = FoodItem::create([
            'name' => 'Combo đã ngừng bán',
            'description' => 'Không còn phục vụ.',
            'price' => 99000,
            'status' => 'unavailable',
        ]);

        $this->get('/star-shop')
            ->assertOk()
            ->assertViewIs('frontend.star-shop')
            ->assertViewHas('products', fn ($products) => $products->contains($available) && ! $products->contains($unavailable))
            ->assertSee('Combo Star đặc biệt')
            ->assertDontSee('Combo đã ngừng bán');
    }

    public function test_admin_can_open_star_shop_from_the_customer_site(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/star-shop')
            ->assertOk()
            ->assertSee('Star Shop');
    }

    public function test_customer_navigation_links_to_star_shop(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('star-shop').'"', false);
    }
}
