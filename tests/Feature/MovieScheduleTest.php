<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Seat;
use App\Models\Showtime;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MovieScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        $cinema=Cinema::create(['name'=>'Rạp kiểm thử','address'=>'Địa chỉ','city'=>'TP.HCM','status'=>'active']);
        $room=Room::create(['cinema_id'=>$cinema->id,'name'=>'Phòng 1','type'=>'2D','status'=>'active','total_seats'=>1]);
        Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>1,'seat_type'=>'normal','status'=>1]);
        return $room;
    }
    private function movie(string $status='now_showing'): Movie { return Movie::create(['title'=>'Phim '.Movie::count(),'duration'=>100,'status'=>$status]); }
    private function show(Movie $movie,Room $room,array $data=[]): Showtime
    {
        return Showtime::create(array_replace(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addDay()->setTime(12,0),'end_time'=>now()->addDay()->setTime(13,40),'base_price'=>80000,'status'=>'active'],$data));
    }
    public function test_fill_adds_real_bookable_slots_without_conflicts_and_repeated_runs_do_not_duplicate_them(): void
    {
        $this->travelTo(now()->setTime(8,0));
        $room=$this->room(); $a=$this->movie(); $b=$this->movie();
        $existing=$this->show($a,$room);
        $result=app(MovieScheduleService::class)->fill(2);
        $this->assertSame(3,$result['created']);
        $this->assertSame([], $result['missing']);
        $shows=Showtime::where('room_id',$room->id)->orderBy('start_time')->get();
        foreach($shows as $index=>$show) {
            $this->assertGreaterThan(0,$show->base_price);
            if($index) $this->assertTrue($show->start_time->gte($shows[$index-1]->end_time->copy()->addMinutes(15)));
        }
        $this->assertSame(0,app(MovieScheduleService::class)->fill(2)['created']);
        $this->assertSame(4,Showtime::count());
        $this->assertSame('12:00',$existing->fresh()->start_time->format('H:i'));
    }
    public function test_upcoming_slots_are_removed_but_sold_tickets_and_their_showtime_history_are_preserved(): void
    {
        $room=$this->room(); $coming=$this->movie('upcoming'); $current=$this->movie();
        $unused=$this->show($coming,$room); $booked=$this->show($coming,$room,['start_time'=>now()->addDay()->setTime(15,0),'end_time'=>now()->addDay()->setTime(16,40)]);
        $ticket=Ticket::create(['user_id'=>User::factory()->create()->id,'showtime_id'=>$booked->id,'seat_id'=>$room->seats()->first()->id,'price'=>80000,'qr_code'=>'KEEP-QR','status'=>'confirmed','booked_at'=>now()]);
        $ticketData=$ticket->fresh()->getAttributes();
        $result=app(MovieScheduleService::class)->fill(2);
        $this->assertSame(1,$result['deleted']); $this->assertSame(1,$result['closed']);
        $this->assertModelMissing($unused); $this->assertSame('cancelled',$booked->fresh()->status);
        $this->assertSame($ticketData,$ticket->fresh()->getAttributes());
        $this->assertSame(0,$coming->showtimes()->where('status','active')->count());
        $this->assertTrue($current->showtimes()->where('status','active')->exists());
    }
    public function test_closed_rooms_or_cinemas_and_rooms_without_seats_never_get_new_schedules(): void
    {
        $room=$this->room(); $movie=$this->movie();
        $room->update(['status'=>'inactive']);
        $result=app(MovieScheduleService::class)->fill(1);
        $this->assertSame(0,$result['created']); $this->assertNotEmpty($result['missing']);
        $room->update(['status'=>'active']); $room->cinema->update(['status'=>'inactive']);
        $this->assertSame(0,app(MovieScheduleService::class)->fill(1)['created']);
        $room->cinema->update(['status'=>'active']); $room->seats()->delete();
        $this->assertSame(0,app(MovieScheduleService::class)->fill(1)['created']);
    }
    public function test_admin_cannot_create_or_clone_a_showtime_for_upcoming_movies_and_customer_cannot_book_one(): void
    {
        $room=$this->room(); $movie=$this->movie('upcoming'); $show=$this->show($movie,$room);
        $data=['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addDays(2)->format('Y-m-d\TH:i'),'end_time'=>now()->addDays(2)->addHours(2)->format('Y-m-d\TH:i'),'base_price'=>80000,'status'=>'active'];
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->post(route('admin.showtimes.store'),$data)->assertSessionHasErrors('movie_id');
        $this->post(route('admin.showtimes.clone-day'),['movie_id'=>$movie->id,'source_date'=>now()->addDay()->toDateString(),'target_date'=>now()->addDays(2)->toDateString()])->assertSessionHasErrors('movie_id');
        $this->actingAs(User::factory()->create(['role'=>'customer']));
        $this->get(route('booking.checkout',$show))->assertNotFound();
        $this->postJson(route('booking.hold',$show),['seats'=>['A1']])->assertNotFound();
        $this->get(route('booking.payment',$show))->assertNotFound();
    }
}
