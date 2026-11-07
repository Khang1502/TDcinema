<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Room, Seat, Showtime, Ticket, User};
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

        foreach (range(1, 6) as $number) {
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
                'booked_at' => now()->subMinutes($number),
            ]);
        }

        $this->actingAs($user)->get(route('customer.tickets'))
            ->assertOk()
            ->assertSee('ticket-pagination', false)
            ->assertSee('Hiển thị')
            ->assertSee('trong tổng số')
            ->assertSee('aria-label="Trang sau"', false)
            ->assertDontSee('Showing');
    }
}
