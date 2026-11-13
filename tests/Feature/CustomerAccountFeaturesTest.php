<?php

namespace Tests\Feature;

use App\Models\{Cinema, FoodOrder, Movie, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAccountFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_each_profile_feature(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->get('/account/notifications')
            ->assertOk()->assertSee('Thông báo của bạn');
        $this->actingAs($user)->get('/account/gifts')
            ->assertOk()->assertSee('Quà tặng thành viên');
        $this->actingAs($user)->get('/account/policy')
            ->assertOk()->assertSee('Chính sách thành viên');
    }

    public function test_customer_can_mark_account_notifications_as_read(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)->post('/account/notifications/read')
            ->assertRedirect('/account/notifications')
            ->assertSessionHas('account.notifications_read_at');
    }

    public function test_ticket_history_uses_compact_vietnamese_pagination(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $cinema = Cinema::create([
            'name' => 'TD Cinema Test',
            'address' => '01 Đường Điện Ảnh',
            'city' => 'TP.HCM',
            'status' => 'active',
        ]);
        $room = Room::create([
            'cinema_id' => $cinema->id,
            'name' => 'Phòng 01',
            'type' => '2D',
            'status' => 'active',
        ]);
        $movie = Movie::create([
            'title' => 'Phim phân trang',
            'duration' => 100,
            'status' => 'now_showing',
        ]);
        $showtime = Showtime::create([
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(100),
            'base_price' => 90000,
            'status' => 'active',
        ]);

        foreach (range(1, 7) as $number) {
            $seat = Seat::create([
                'room_id' => $room->id,
                'seat_row' => 'A',
                'seat_number' => $number,
            ]);
            Ticket::create([
                'user_id' => $user->id,
                'showtime_id' => $showtime->id,
                'seat_id' => $seat->id,
                'price' => 90000,
                'status' => 'confirmed',
                'qr_code' => $number >= 6 ? 'BOOKING-SHARED' : null,
                'booked_at' => now()->subMinutes($number),
            ]);
        }

        $this->actingAs($user)->get(route('customer.tickets'))
            ->assertOk()
            ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 6)
            ->assertSee('2 vé · Ghế:')
            ->assertSee('Mã check-in chung:')
            ->assertSee('ticket-pagination', false)
            ->assertSee('Hiển thị')
            ->assertSee('trong tổng số')
            ->assertSee('aria-label="Trang sau"', false)
            ->assertDontSee('Showing');
    }

    public function test_ticket_checkin_modal_contains_each_active_food_pickup_code(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email_verified_at' => now()]);
        $cinema = Cinema::create(['name' => 'TD Cinema Test', 'address' => 'Test', 'city' => 'HCM']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng 1', 'type' => '2D']);
        $movie = Movie::create(['title' => 'Phim test', 'duration' => 90, 'status' => 'now_showing']);
        $showtime = Showtime::create([
            'movie_id' => $movie->id, 'room_id' => $room->id,
            'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHours(2),
            'base_price' => 80000, 'status' => 'active',
        ]);
        $seat = Seat::create(['room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => 1]);
        $ticket = Ticket::create([
            'user_id' => $user->id, 'showtime_id' => $showtime->id, 'seat_id' => $seat->id,
            'price' => 80000, 'status' => 'confirmed', 'qr_code' => 'BOOKING-WITH-FOOD', 'booked_at' => now(),
        ]);
        FoodOrder::create(['user_id' => $user->id, 'ticket_id' => $ticket->id,
            'pickup_code' => 'FPOP01', 'total_price' => 35000, 'status' => 'completed']);
        FoodOrder::create(['user_id' => $user->id, 'ticket_id' => $ticket->id,
            'pickup_code' => 'FPOP02', 'total_price' => 35000, 'status' => 'completed', 'fulfilled_at' => now()]);
        FoodOrder::create(['user_id' => $user->id, 'ticket_id' => $ticket->id,
            'pickup_code' => 'FCANCEL', 'total_price' => 35000, 'status' => 'cancelled']);

        $response = $this->actingAs($user)->get(route('customer.tickets'));
        $response->assertOk()->assertSee('Mã nhận bắp nước')->assertSee('id="modalFoodOrders"', false);
        preg_match('/data-food-orders="([^"]*)"/', $response->getContent(), $matches);
        $this->assertNotEmpty($matches);
        $orders = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['FPOP01', 'FPOP02'], array_column($orders, 'code'));
        $this->assertSame([false, true], array_column($orders, 'received'));
    }
}
