<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Payment, Room, Seat, Showtime, Ticket, User};
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
        $this->get('/admin/tickets')->assertOk()->assertSee('Phim test');
        $this->get("/admin/tickets/{$ticket->id}")->assertOk();
        $this->post("/admin/tickets/{$ticket->id}/status", ['status' => 'used'])->assertSessionHas('success');
        $this->assertSame('used', $ticket->fresh()->status);
        $this->post("/admin/tickets/{$ticket->id}/status", ['status' => 'used'])->assertSessionHasErrors('status');
    }

    public function test_checkin_rejects_unpaid_failed_refunded_pending_and_cancelled_tickets(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach ([['confirmed', null], ['confirmed', 'failed'], ['confirmed', 'refunded'], ['pending', 'paid'], ['cancelled', 'paid']] as [$status, $payment]) {
            $ticket = $this->ticket($status, $payment);
            $this->from('/admin/tickets')->post("/admin/tickets/{$ticket->id}/status", ['status' => 'used'])
                ->assertSessionHasErrors('status');
            $this->assertSame($status, $ticket->fresh()->status);
        }
        $this->get('/admin/tickets')->assertSee('Chỉ vé đã xác nhận và thanh toán thành công mới được check-in.');
    }

    public function test_bulk_checkin_respects_status_and_payment_filters(): void
    {
        $ticket = $this->ticket();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        foreach ([['status' => 'cancelled'], ['payment_status' => 'refunded']] as $filter) {
            $this->post('/admin/tickets/bulk-checkin', $filter)->assertSessionHas('error');
            $this->assertSame('confirmed', $ticket->fresh()->status);
        }
        $this->post('/admin/tickets/bulk-checkin', ['status' => 'confirmed', 'payment_status' => 'paid'])
            ->assertSessionHas('success');
        $this->assertSame('used', $ticket->fresh()->status);
    }

    public function test_unpaid_filter_includes_tickets_without_payment(): void
    {
        $ticket = $this->ticket('pending', null);
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get('/admin/tickets?payment_status=unpaid')->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->contains('id', $ticket->id));
    }
}
