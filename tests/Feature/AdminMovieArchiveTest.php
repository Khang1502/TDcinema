<?php

namespace Tests\Feature;

use App\Models\{Cinema, Invoice, Movie, Payment, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminMovieArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function history(): array
    {
        $movie = Movie::create(['title'=>'Phim lịch sử', 'duration'=>100, 'status'=>'now_showing', 'poster_path'=>'movies/posters/archive.png']);
        $cinema = Cinema::create(['name'=>'TD Test', 'address'=>'Test', 'city'=>'HCM']);
        $room = Room::create(['cinema_id'=>$cinema->id, 'name'=>'Rạp 1', 'type'=>'2D']);
        $show = Showtime::create(['movie_id'=>$movie->id, 'room_id'=>$room->id, 'start_time'=>now()->subDay(), 'end_time'=>now()->subDay()->addHours(2), 'base_price'=>100000, 'status'=>'active']);
        $user = User::factory()->create(['role'=>'customer']);
        $tickets = collect();
        foreach (['confirmed','used','pending','cancelled','confirmed'] as $index=>$status) {
            $seat = Seat::create(['room_id'=>$room->id, 'seat_row'=>'A', 'seat_number'=>$index+1]);
            $ticket = Ticket::create(['user_id'=>$user->id, 'showtime_id'=>$show->id, 'seat_id'=>$seat->id, 'price'=>100000, 'status'=>$status, 'booked_at'=>now()]);
            if ($index < 4) Payment::create(['ticket_id'=>$ticket->id, 'amount'=>[70000,50000,999999,80000][$index], 'status'=>['paid','paid','failed','refunded'][$index], 'method'=>'cash']);
            $tickets->push($ticket);
        }
        Payment::create(['ticket_id'=>$tickets[0]->id, 'amount'=>70000, 'status'=>'failed', 'method'=>'cash']);
        $invoice = Invoice::create(['user_id'=>$user->id,'ticket_id'=>$tickets[0]->id,'total_amount'=>120000,'invoice_code'=>'ARCHIVE-01']);
        return compact('movie','show','tickets','invoice');
    }

    public function test_archive_and_restore_preserve_assets_showtimes_tickets_and_payments(): void
    {
        Storage::fake('public');
        ['movie'=>$movie,'show'=>$show,'tickets'=>$tickets,'invoice'=>$invoice] = $this->history();
        Storage::disk('public')->put($movie->poster_path, 'saved image');
        $before = [Showtime::count(), Ticket::count(), Payment::count(), Invoice::count()];
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.movies.archive.store', $movie))
            ->assertRedirect(route('admin.movies.archive.show', $movie));
        $this->assertSame('inactive', $movie->fresh()->status);
        $this->get(route('admin.movies.archive.show', $movie))->assertOk()->assertSee('120.000')->assertSee('TD Test');
        $this->post(route('admin.movies.restore', $movie), ['status'=>'now_showing'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('now_showing', $movie->fresh()->status);
        $this->assertSame($before, [Showtime::count(), Ticket::count(), Payment::count(), Invoice::count()]);
        $this->assertSame('active', $show->fresh()->status);
        $this->assertModelExists($invoice);
        Storage::disk('public')->assertExists($movie->poster_path);
        $this->get(route('admin.movies.archive.show', $movie))->assertNotFound();
    }

    public function test_statistics_use_paid_amounts_without_double_counting_tickets_or_invoice_totals(): void
    {
        ['movie'=>$movie] = $this->history();
        $movie->update(['status'=>'inactive']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.movies.archive.show', $movie))->assertOk()
            ->assertViewHas('summary', fn ($stats) => (int)$stats['sold_count']===2 && (int)$stats['used_count']===1
                && (int)$stats['tickets_count']===5 && (int)$stats['cancelled_count']===1 && (int)$stats['showtimes_count']===1
                && (float)$stats['revenue']===120000.0 && (float)$stats['refunded']===80000.0)
            ->assertViewHas('cinemas', fn ($items) => $items->count()===1 && (int)$items[0]['sold_count']===2 && (float)$items[0]['revenue']===120000.0);
    }

    public function test_archive_filters_paginate_and_include_old_inactive_movies_without_showtimes(): void
    {
        foreach (range(1,12) as $i) Movie::create(['title'=>'Kho phim '.$i, 'duration'=>90, 'status'=>'inactive']);
        Movie::create(['title'=>'Kho phim đang chiếu','duration'=>90,'status'=>'now_showing']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.movies.archive',['q'=>'Kho phim','page'=>2]))->assertOk()
            ->assertViewHas('movies', fn ($items) => $items->total()===12 && $items->count()===2 && $items->every(fn ($movie) => $movie->status==='inactive'))
            ->assertViewHas('summaries', fn ($items) => $items->every(fn ($stats) => $stats['revenue']===0 && $stats['showtimes_count']===0));
    }

    public function test_financial_fields_require_report_permission_and_archive_routes_require_movie_permission(): void
    {
        ['movie'=>$movie] = $this->history();
        $movie->update(['status'=>'inactive']);
        $staff = User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','movies.manage']]);
        $this->actingAs($staff)->get(route('admin.movies.archive.show', $movie))->assertOk()->assertDontSee('Doanh thu')->assertDontSee('120.000')
            ->assertViewHas('summary', fn ($stats) => !array_key_exists('revenue',$stats) && !array_key_exists('refunded',$stats));
        $this->get(route('admin.movies.archive'))->assertOk()->assertViewHas('summaries', fn ($items) => !array_key_exists('revenue',$items[$movie->id]));
        $this->actingAs(User::factory()->create(['role'=>'staff']));
        $this->get(route('admin.movies.archive'))->assertForbidden();
        $this->post(route('admin.movies.archive.store',$movie))->assertForbidden();
        $this->post(route('admin.movies.restore',$movie),['status'=>'now_showing'])->assertForbidden();
    }

    public function test_restoring_upcoming_closes_future_shows_but_keeps_history_and_rejects_invalid_status(): void
    {
        ['movie'=>$movie,'show'=>$past] = $this->history();
        $movie->update(['status'=>'inactive']);
        $future = Showtime::create(['movie_id'=>$movie->id,'room_id'=>$past->room_id,'start_time'=>now()->addDay(),'end_time'=>now()->addDay()->addHours(2),'base_price'=>100000,'status'=>'active']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.movies.restore',$movie),['status'=>'bad'])->assertSessionHasErrors('status');
        $this->assertSame('inactive',$movie->fresh()->status);
        $this->post(route('admin.movies.restore',$movie),['status'=>'upcoming'])->assertSessionHasNoErrors();
        $this->assertSame('upcoming',$movie->fresh()->status);
        $this->assertSame('cancelled',$future->fresh()->status);
        $this->assertSame('active',$past->fresh()->status);
        $this->assertSame(2,Showtime::count());
        $this->assertSame(5,Ticket::count());
    }
}
