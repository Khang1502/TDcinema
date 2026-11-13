<?php

namespace Tests\Feature;

use App\Models\{Cinema, FoodItem, FoodOrder, Movie, Payment, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketCheckinTest extends TestCase
{
    use RefreshDatabase;

    private function ticket(string $status = 'confirmed', ?string $payment = 'paid'): Ticket
    {
        $cinema = Cinema::create(['name' => 'TD Test '.Cinema::count(), 'address' => 'Test', 'city' => 'HCM']);
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Room 1', 'type' => '2D']);
        $movie = Movie::create(['title' => 'Phim test', 'duration' => 100, 'status' => 'now_showing']);
        $showtime = Showtime::create([
            'movie_id' => $movie->id, 'room_id' => $room->id,
            'start_time' => now()->addHour(), 'end_time' => now()->addHours(3),
            'base_price' => 80000, 'status' => 'active',
        ]);
        $seat = Seat::create(['room_id' => $room->id, 'seat_row' => 'A', 'seat_number' => 1]);
        $ticket = Ticket::create([
            'user_id' => User::factory()->create()->id, 'showtime_id' => $showtime->id,
            'seat_id' => $seat->id, 'price' => 80000, 'status' => $status, 'booked_at' => now(),
        ]);
        if ($payment !== null) {
            Payment::create(['ticket_id' => $ticket->id, 'amount' => 80000, 'method' => 'cash', 'status' => $payment]);
        }
        return $ticket;
    }

    public function test_paid_ticket_can_be_viewed_and_checked_in_only_once(): void
    {
        $ticket = $this->ticket();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/admin/tickets')->assertOk()->assertSee('Phim test')->assertSee('Check-in cả lượt')->assertDontSee('Check-in ghế này');
        $this->get("/admin/tickets/{$ticket->id}")->assertOk()->assertSee('Check-in cả lượt (1 ghế)')
            ->assertDontSee('ticket-booking-title')->assertDontSee('Check-in ghế này');
        $this->post(route('admin.tickets.status', $ticket), ['status' => 'used'])->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $ticket->fresh()->status);
        $this->post(route('admin.tickets.booking-checkin', $ticket))->assertSessionHas('success');
        $this->assertSame('used', $ticket->fresh()->status);
        $this->post(route('admin.tickets.booking-checkin', $ticket))->assertSessionHasErrors('status');
    }

    public function test_checkin_rejects_unpaid_failed_refunded_pending_and_cancelled_tickets(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach ([['confirmed', null], ['confirmed', 'failed'], ['confirmed', 'refunded'], ['pending', 'paid'], ['cancelled', 'paid']] as [$status, $payment]) {
            $ticket = $this->ticket($status, $payment);
            $this->from('/admin/tickets')->post(route('admin.tickets.booking-checkin', $ticket))
                ->assertSessionHasErrors('status');
            $this->assertSame($status, $ticket->fresh()->status);
        }
        $this->get('/admin/tickets')->assertOk()->assertDontSee('Check-in cả lượt (');
    }

    public function test_filtered_bulk_checkin_endpoint_is_removed(): void
    {
        $ticket = $this->ticket();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->post('/admin/tickets/bulk-checkin', ['q' => '#'.$ticket->id])->assertStatus(405);
        $this->assertSame('confirmed', $ticket->fresh()->status);
    }

    public function test_unpaid_filter_includes_tickets_without_payment(): void
    {
        $ticket = $this->ticket('pending', null);
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get('/admin/tickets?payment_status=unpaid')->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->contains('id', $ticket->id));
    }

    public function test_shared_booking_completes_remaining_paid_seats_from_prior_partial_checkin(): void
    {
        $first = $this->ticket();
        $first->update(['qr_code' => 'BOOKING-SHARED']);
        $secondSeat = Seat::create(['room_id' => $first->seat->room_id, 'seat_row' => 'A', 'seat_number' => 2]);
        $second = Ticket::create([
            'user_id' => $first->user_id, 'showtime_id' => $first->showtime_id, 'seat_id' => $secondSeat->id,
            'price' => 80000, 'status' => 'confirmed', 'qr_code' => 'BOOKING-SHARED', 'booked_at' => now(),
        ]);
        Payment::create(['ticket_id' => $second->id, 'amount' => 80000, 'method' => 'cash', 'status' => 'paid']);
        $unpaidSeat = Seat::create(['room_id' => $first->seat->room_id, 'seat_row' => 'A', 'seat_number' => 3]);
        $unpaid = Ticket::create([
            'user_id' => $first->user_id, 'showtime_id' => $first->showtime_id, 'seat_id' => $unpaidSeat->id,
            'price' => 80000, 'status' => 'confirmed', 'qr_code' => 'BOOKING-SHARED', 'booked_at' => now(),
        ]);
        $otherShow = $this->ticket();
        $otherShow->update(['qr_code' => 'BOOKING-SHARED']);

        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.tickets.index', ['q' => 'BOOKING-SHARED']))
            ->assertOk()->assertSee('Lượt đặt BOOKING-SHARED');
        $this->get(route('admin.tickets.show', $first))
            ->assertOk()->assertSee('Check-in cả lượt (2 ghế)')->assertDontSee('ticket-booking-title');

        $first->update(['status' => 'used']);
        $this->assertSame('used', $first->fresh()->status);
        $this->assertSame('confirmed', $second->fresh()->status);
        $this->get(route('admin.tickets.show', $second))->assertSee('Check-in cả lượt (1 ghế)');

        $this->post(route('admin.tickets.booking-checkin', $first))->assertSessionHas('success');
        $this->assertSame('used', $second->fresh()->status);
        $this->assertSame('confirmed', $unpaid->fresh()->status);
        $this->assertSame('confirmed', $otherShow->fresh()->status);
        $this->post(route('admin.tickets.booking-checkin', $first))->assertSessionHasErrors('status');
    }

    public function test_used_ticket_row_can_check_in_remaining_booking_without_seat_summary(): void
    {
        $used = $this->ticket('used');
        $used->update(['qr_code' => 'BOOKING-PARTIAL']);
        $seat = Seat::create(['room_id' => $used->seat->room_id, 'seat_row' => 'C', 'seat_number' => 6]);
        $waiting = Ticket::create([
            'user_id' => $used->user_id, 'showtime_id' => $used->showtime_id, 'seat_id' => $seat->id,
            'price' => 80000, 'status' => 'confirmed', 'qr_code' => 'BOOKING-PARTIAL', 'booked_at' => now(),
        ]);
        Payment::create(['ticket_id' => $waiting->id, 'amount' => 80000, 'method' => 'cash', 'status' => 'paid']);

        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $response = $this->get(route('admin.tickets.index', ['status' => 'used']));
        $response->assertOk()->assertViewHas('tickets', fn ($tickets) => $tickets->count() === 1);
        preg_match('/<article class="ad-record ad-ticket-record" data-ticket-id="'.$used->id.'">(.*?)<\/article>/s', $response->getContent(), $row);
        $this->assertNotEmpty($row);
        $this->assertStringContainsString('ad-ticket-action-status', $row[1]);
        $this->assertStringContainsString('Đã check-in', $row[1]);
        $this->assertStringNotContainsString('Vé này:', $row[1]);
        $this->assertGreaterThan(strpos($row[1], 'Chi tiết</a>'), strpos($row[1], 'ad-ticket-action-status'));
        $this->assertStringContainsString('Lượt đặt BOOKING-PARTIAL · 2 vé', $row[1]);
        $this->assertStringContainsString('Check-in cả lượt (1 ghế)', $row[1]);
        $this->assertStringNotContainsString('ad-booking-summary', $row[1]);
        $this->assertStringNotContainsString('Ghế C6', $row[1]);

        $this->post(route('admin.tickets.booking-checkin', $used))->assertSessionHas('success');
        $this->assertSame('used', $waiting->fresh()->status);
        $this->get(route('admin.tickets.index', ['status' => 'used']))
            ->assertDontSee('ad-booking-summary')
            ->assertDontSee('Check-in cả lượt (1 ghế)');
    }

    public function test_any_seat_in_booking_shows_shared_combo_with_food_checkin_and_images(): void
    {
        $first = $this->ticket();
        $first->update(['qr_code' => 'BOOKING-WITH-COMBO']);
        $first->showtime->movie->update(['poster_path' => 'https://example.test/movie-poster.jpg']);
        $seat = Seat::create(['room_id' => $first->seat->room_id, 'seat_row' => 'A', 'seat_number' => 2]);
        $second = Ticket::create([
            'user_id' => $first->user_id, 'showtime_id' => $first->showtime_id, 'seat_id' => $seat->id,
            'price' => 80000, 'status' => 'confirmed', 'qr_code' => 'BOOKING-WITH-COMBO', 'booked_at' => now(),
        ]);
        Payment::create(['ticket_id' => $second->id, 'amount' => 80000, 'method' => 'cash', 'status' => 'paid']);
        $combo = FoodItem::create([
            'name' => 'Combo bắp nước', 'price' => 35000, 'image' => 'https://example.test/combo.jpg', 'status' => 'available',
        ]);
        $order = FoodOrder::create([
            'user_id' => $first->user_id, 'ticket_id' => $first->id, 'total_price' => 35000,
            'pickup_code' => 'FCOMBO', 'status' => 'completed',
        ]);
        $order->items()->create(['food_item_id' => $combo->id, 'quantity' => 1, 'price' => 35000]);

        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.tickets.index', ['q' => 'BOOKING-WITH-COMBO']))
            ->assertOk()->assertSee('Có combo đi kèm')->assertSee('https://example.test/movie-poster.jpg');
        $this->get(route('admin.tickets.show', $second))->assertOk()
            ->assertSee('Combo đi kèm lượt đặt')->assertSee('Combo bắp nước')->assertSee('FCOMBO')
            ->assertSee('https://example.test/movie-poster.jpg')
            ->assertSee('https://example.test/combo.jpg')
            ->assertSee(route('admin.food-orders.show', $order))
            ->assertSee(route('admin.food-orders.fulfill', $order))
            ->assertSee('195.000 đ');
        $this->get(route('admin.food-orders.show', $order))->assertOk()
            ->assertSee('https://example.test/combo.jpg')->assertSee('https://example.test/movie-poster.jpg');
        $this->get(route('admin.food-orders.index'))->assertOk()
            ->assertSee('https://example.test/combo.jpg')->assertSee('https://example.test/movie-poster.jpg');
        $this->post(route('admin.food-orders.fulfill', $order))->assertSessionHas('success');
        $this->get(route('admin.tickets.show', $second))->assertDontSee('Check-in combo')
            ->assertSee('ticket-detail__order-status is-complete', false)->assertSee('Đã nhận đủ');
    }

    public function test_overdue_unused_tickets_move_to_cancelled_with_reason_and_cannot_check_in(): void
    {
        $expired = $this->ticket();
        $expired->update(['qr_code' => 'BOOKING-OLD']);
        $showtime = $expired->showtime;
        $showtime->update(['start_time' => now()->subHours(3), 'end_time' => now()->subHour()]);
        $seat = Seat::create(['room_id' => $expired->seat->room_id, 'seat_row' => 'B', 'seat_number' => 2]);
        $used = Ticket::create([
            'user_id' => $expired->user_id, 'showtime_id' => $showtime->id, 'seat_id' => $seat->id,
            'price' => 80000, 'status' => 'used', 'qr_code' => 'BOOKING-OLD', 'booked_at' => now()->subHours(4),
        ]);
        Payment::create(['ticket_id' => $used->id, 'amount' => 80000, 'method' => 'cash', 'status' => 'paid']);
        $future = $this->ticket();

        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get(route('admin.tickets.index', ['status' => 'cancelled']))->assertOk()
            ->assertSee('Ghi chú hủy vé: Vé quá hạn')->assertSee('ad-ticket-action-status', false)
            ->assertDontSee('Vé này: Quá hạn')
            ->assertDontSee('ad-booking-summary');
        $this->assertSame('cancelled', $expired->fresh()->status);
        $this->assertSame(Ticket::EXPIRED_REASON, $expired->fresh()->cancellation_reason);
        $this->assertSame('paid', $expired->payment->fresh()->status);
        $this->assertSame('used', $used->fresh()->status);
        $this->assertSame('confirmed', $future->fresh()->status);
        $this->get(route('admin.tickets.show', $expired))->assertOk()
            ->assertSee('Ghi chú hủy vé: Vé quá hạn')->assertDontSee('Check-in vé');
        $this->get(route('admin.tickets.show', $used))->assertOk()->assertDontSee('ticket-booking-title');
        $this->post(route('admin.tickets.status', $expired), ['status' => 'used'])->assertSessionHasErrors('status');
        $this->post(route('admin.tickets.status', $expired), ['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->assertSame('paid', $expired->payment->fresh()->status);
    }

    public function test_customer_history_and_scheduled_command_mark_overdue_tickets(): void
    {
        $customerTicket = $this->ticket();
        $customerTicket->user->update(['role' => 'customer', 'email_verified_at' => now()]);
        $customerTicket->showtime->update(['start_time' => now()->subHours(3), 'end_time' => now()->subHour()]);
        $this->actingAs($customerTicket->user)->get(route('customer.tickets'))
            ->assertOk()->assertSee('Quá hạn');
        $this->assertSame(Ticket::EXPIRED_REASON, $customerTicket->fresh()->cancellation_reason);

        $commandTicket = $this->ticket();
        $commandTicket->showtime->update(['start_time' => now()->subHours(3), 'end_time' => now()->subHour()]);
        $this->artisan('tickets:expire')->assertExitCode(0);
        $this->assertSame(Ticket::EXPIRED_REASON, $commandTicket->fresh()->cancellation_reason);
    }
}
