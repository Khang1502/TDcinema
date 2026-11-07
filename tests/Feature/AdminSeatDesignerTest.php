<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Room, Seat, Showtime, Ticket, User};
use App\Services\BookingHoldService;
use App\Support\SeatMapLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSeatDesignerTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        $cinema = Cinema::create(['name'=>'Test '.Cinema::count(),'address'=>'Test','city'=>'HCM']);
        return Room::create(['cinema_id'=>$cinema->id,'name'=>'Rạp 1','type'=>'2D','total_seats'=>0]);
    }

    private function show(Room $room): Showtime
    {
        $movie = Movie::create(['title'=>'Test','duration'=>90,'status'=>'now_showing']);
        return Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addHour(),'end_time'=>now()->addHours(3),'base_price'=>70000,'status'=>'active']);
    }

    private function config(): array
    {
        return ['rows_count'=>3,'seats_per_row'=>10,'aisles'=>'5','default_type'=>'vip','default_fee'=>20000];
    }

    public function test_preview_matches_generated_layout_and_does_not_write_to_database(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create(['role'=>'manager']));
        $preview = $this->getJson(route('admin.seats.preview',$this->config()))->assertOk()->assertJsonPath('total',27)->assertJsonPath('rows.A.0.code','A1')->assertJsonPath('rows.A.4.number',6)->assertJsonPath('rows.C.8.type','vip')->json();
        $this->assertSame(0,Seat::count());
        $this->assertEquals(0,$room->fresh()->total_seats);
        $this->post(route('admin.seats.generate',$room),$this->config())->assertSessionHas('success');
        $this->assertSame(27,Seat::count());
        $this->assertEquals(27,$room->fresh()->total_seats);
        $this->assertEquals($preview['layout'],SeatMapLayout::forRows($room->seats()->orderBy('seat_row')->orderBy('seat_number')->get()->groupBy('seat_row')));
        $this->assertDatabaseMissing('seats',['room_id'=>$room->id,'seat_number'=>5]);
        $this->assertDatabaseHas('seats',['room_id'=>$room->id,'seat_row'=>'C','seat_number'=>10,'extra_price'=>20000,'seat_type'=>'vip']);
    }

    public function test_invalid_aisles_and_dimensions_are_rejected(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        foreach (['text','0','11',implode(',',range(1,10))] as $aisles) $this->getJson(route('admin.seats.preview',array_merge($this->config(),['aisles'=>$aisles])))->assertUnprocessable()->assertJsonValidationErrors('aisles');
        $this->post(route('admin.seats.generate',$room),array_merge($this->config(),['rows_count'=>53]))->assertSessionHasErrors('rows_count');
        $this->assertSame(0,Seat::count());
    }

    public function test_replacing_or_copying_a_layout_preserves_ticket_history(): void
    {
        $room = $this->room(); $source = $this->room();
        $seat = Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>1,'seat_type'=>'normal','status'=>1,'extra_price'=>0]);
        Seat::create(['room_id'=>$source->id,'seat_row'=>'B','seat_number'=>2,'seat_type'=>'vip','status'=>1,'extra_price'=>20000]);
        $ticket = Ticket::create(['showtime_id'=>$this->show($room)->id,'seat_id'=>$seat->id,'user_id'=>User::factory()->create()->id,'price'=>70000,'status'=>'confirmed','booked_at'=>now()]);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->post(route('admin.seats.generate',$room),$this->config())->assertSessionHasErrors('layout');
        $this->post(route('admin.seats.copy',$room),['source_room_id'=>$source->id])->assertSessionHasErrors('layout');
        $this->assertModelExists($ticket); $this->assertModelExists($seat);
        $this->assertEquals(1,$room->seats()->count());
    }

    public function test_live_holds_block_replacement_until_they_expire(): void
    {
        $room = $this->room();
        Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>1,'seat_type'=>'normal','status'=>1,'extra_price'=>0]);
        app(BookingHoldService::class)->start(999,$this->show($room)->id,collect(['A1']));
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.seats.generate',$room),$this->config())->assertSessionHasErrors('layout');
        $this->travel(11)->minutes();
        $this->post(route('admin.seats.generate',$room),$this->config())->assertSessionHas('success');
        $this->assertEquals(27,$room->seats()->count());
    }

    public function test_designer_tools_have_labels_and_old_map_link_opens_the_same_editor(): void
    {
        $room = $this->room();
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.seats.index'))
            ->assertOk()->assertSee('data-designer-tab="generate"',false)->assertSee('Xem sơ đồ nguồn')->assertSee('Lưu thay đổi ghế')->assertSee('designerPreviewStatus')->assertSee('Chọn một mẫu');
        $this->get(route('admin.seats.map',$room))->assertRedirect(route('admin.seats.index',['cinema_id'=>$room->cinema_id,'room_id'=>$room->id]));
        $this->actingAs(User::factory()->create(['role'=>'staff']))->getJson(route('admin.seats.preview',$this->config()))->assertForbidden();
    }

    public function test_bulk_edits_preserve_seat_ids_and_sold_prices_and_are_atomic_for_held_rooms(): void
    {
        $room = $this->room();
        $other = $this->room();
        $seats = collect([$room,$other])->map(fn ($r) => Seat::create(['room_id'=>$r->id,'seat_row'=>'A','seat_number'=>1,'seat_type'=>'normal','status'=>1,'extra_price'=>0]));
        $ticket = Ticket::create(['showtime_id'=>$this->show($room)->id,'seat_id'=>$seats[0]->id,'user_id'=>User::factory()->create()->id,'price'=>70000,'status'=>'confirmed','booked_at'=>now()]);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $payload = ['seats'=>$seats->map(fn ($s) => ['id'=>$s->id,'seat_type'=>'vip','extra_price'=>20000])->all()];
        app(BookingHoldService::class)->start(987,$this->show($other)->id,collect(['A1']));
        $this->patchJson(route('admin.seats.bulk-update'),$payload)->assertUnprocessable()->assertJsonValidationErrors('seats');
        foreach ($seats as $seat) $this->assertSame('normal',$seat->fresh()->seat_type);
        $this->travel(11)->minutes();
        $this->patchJson(route('admin.seats.bulk-update'),$payload)->assertOk();
        foreach ($seats as $seat) { $this->assertModelExists($seat); $this->assertSame('vip',$seat->fresh()->seat_type); $this->assertEquals(20000,$seat->fresh()->extra_price); }
        $this->assertEquals(70000,$ticket->fresh()->price);
    }
}
