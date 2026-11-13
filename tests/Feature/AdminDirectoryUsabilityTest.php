<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Payment, Room, Seat, Showtime, Ticket, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDirectoryUsabilityTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $cinema = Cinema::create(['name'=>'Rạp kiểm thử','address'=>'Test','city'=>'HCM','status'=>'active']);
        $room = Room::create(['cinema_id'=>$cinema->id,'name'=>'Rạp 1','type'=>'2D']);
        $movie = Movie::create(['title'=>'Phim kiểm thử','duration'=>120,'status'=>'now_showing']);
        $show = Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addDay()->startOfDay()->addHours(9),
            'end_time'=>now()->addDay()->startOfDay()->addHours(11),'base_price'=>100000,'status'=>'active']);
        $user = User::factory()->create(['name'=>'Người cần tìm','phone'=>'0901112223']);
        return compact('cinema','room','movie','show','user');
    }

    private function ticket(array $fixture, int $number, string $status='confirmed', ?string $payment='paid'): Ticket
    {
        $seat = Seat::create(['room_id'=>$fixture['room']->id,'seat_row'=>'A','seat_number'=>$number,'status'=>1]);
        $ticket = Ticket::create(['user_id'=>$fixture['user']->id,'showtime_id'=>$fixture['show']->id,'seat_id'=>$seat->id,'price'=>100000,'status'=>$status,'booked_at'=>now()]);
        if ($payment) Payment::create(['ticket_id'=>$ticket->id,'amount'=>70000,'method'=>'cash','status'=>$payment]);
        return $ticket;
    }

    public function test_ticket_lookup_phone_and_id_keep_booking_checkin_within_the_selected_booking(): void
    {
        $fixture = $this->fixture(); $one = $this->ticket($fixture,1); $two = $this->ticket($fixture,2);
        $unpaid = $this->ticket($fixture,3,payment:null); $used = $this->ticket($fixture,4,'used');
        $this->actingAs(User::factory()->create(['role'=>'staff']))->get(route('admin.tickets.index',['q'=>'090111']))
            ->assertOk()->assertViewHas('tickets',fn ($items)=>$items->total()===4)
            ->assertViewHas('counts',fn ($counts)=>$counts['ready']===2 && $counts['used']===1);
        $this->get(route('admin.tickets.index',['q'=>'#'.$one->id]))->assertViewHas('tickets',fn ($items)=>$items->pluck('id')->all()===[$one->id])
            ->assertSee('70.000')->assertSee('Giá trị thanh toán');
        $this->post(route('admin.tickets.booking-checkin',$one))->assertSessionHas('success');
        $this->assertSame('used',$one->fresh()->status); $this->assertSame('confirmed',$two->fresh()->status);
        $this->assertSame('confirmed',$unpaid->fresh()->status); $this->assertSame('used',$used->fresh()->status);
        $this->post(route('admin.tickets.booking-checkin',$one))->assertSessionHasErrors('status');
    }

    public function test_ticket_counts_cover_all_filtered_pages_and_invalid_dates_are_rejected(): void
    {
        $fixture = $this->fixture(); foreach(range(1,17) as $number) $this->ticket($fixture,$number);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.tickets.index',['movie_id'=>$fixture['movie']->id,'status'=>'confirmed','payment_status'=>'paid','page'=>2]))
            ->assertViewHas('tickets',fn ($items)=>$items->total()===17 && $items->count()===2)
            ->assertViewHas('counts',fn ($counts)=>$counts['ready']===17);
        $this->getJson(route('admin.tickets.index',['from'=>'2026-10-20','to'=>'2026-10-10']))->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson(route('admin.tickets.index',['from'=>'invalid']))->assertUnprocessable()->assertJsonValidationErrors('from');
        $this->assertEquals(17,Ticket::where('status','confirmed')->count());
    }

    public function test_user_filters_include_cinema_and_valid_dates_and_read_only_users_cannot_see_management_actions(): void
    {
        $fixture = $this->fixture();
        $manager = User::factory()->create(['role'=>'manager','cinema_id'=>$fixture['cinema']->id]);
        $reader = User::factory()->create(['role'=>'manager','admin_permissions'=>['dashboard.view','users.view']]);
        $this->actingAs($reader)->get(route('admin.users.index',['role'=>'manager','cinema_id'=>$fixture['cinema']->id]))
            ->assertOk()->assertViewHas('users',fn ($items)=>$items->pluck('id')->all()===[$manager->id])
            ->assertDontSee(route('admin.users.edit',$manager))->assertDontSee('Thêm người dùng');
        $this->getJson(route('admin.users.index',['registered_from'=>'2026-02-30']))->assertUnprocessable();
        $this->getJson(route('admin.users.index',['registered_from'=>'2026-10-20','registered_to'=>'2026-10-10']))->assertJsonValidationErrors('registered_to');
    }

    public function test_cinema_modes_keep_filters_and_operational_links_obey_permissions(): void
    {
        $fixture = $this->fixture();
        $reader = User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','cinemas.manage']]);
        $this->actingAs($reader)->get(route('admin.cinemas.index',['q'=>'kiểm thử','view'=>'list']))->assertOk()
            ->assertViewHas('filters',fn ($filters)=>$filters['view']==='list')
            ->assertViewHas('cinemas',fn ($items)=>$items->total()===1)->assertDontSee('Báo cáo doanh thu')
            ->assertDontSee(route('admin.rooms.index',['cinema_id'=>$fixture['cinema']->id]));
    }

    public function test_copying_an_overnight_show_preserves_duration_and_detects_target_conflicts(): void
    {
        $fixture = $this->fixture(); $source = $fixture['show'];
        $source->update(['start_time'=>'2026-10-12 23:30:00','end_time'=>'2026-10-13 01:30:00']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.showtimes.clone-day'),
            ['movie_id'=>$fixture['movie']->id,'source_date'=>'2026-10-12','target_date'=>'2026-10-14'])->assertSessionHas('success');
        $copy = Showtime::where('id','!=',$source->id)->firstOrFail();
        $this->assertSame('2026-10-14 23:30:00',$copy->start_time->toDateTimeString());
        $this->assertSame('2026-10-15 01:30:00',$copy->end_time->toDateTimeString());
        $this->assertEquals($source->base_price,$copy->base_price);
        $this->post(route('admin.showtimes.clone-day'),['movie_id'=>$fixture['movie']->id,'source_date'=>'2026-10-12','target_date'=>'2026-10-14'])->assertSessionHas('warning');
        $this->assertDatabaseCount('showtimes',2);
    }
}
