<?php

namespace Tests\Feature;

use App\Models\{Cinema, Movie, Payment, Room, Seat, Showtime, Ticket, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private ?User $customer = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-12 12:00:00'));
    }

    private function cinema(string $name): Cinema
    {
        return Cinema::create(['name'=>$name,'address'=>'Test','city'=>'HCM','status'=>'active']);
    }

    private function payment(Cinema $cinema, string $date, float $amount, string $status = 'paid', ?Movie $movie = null): Payment
    {
        $this->customer ??= User::factory()->create();
        $movie ??= Movie::first() ?? Movie::create(['title'=>'Phim thử nghiệm','duration'=>100,'status'=>'now_showing']);
        $room = Room::firstOrCreate(['cinema_id'=>$cinema->id,'name'=>'Rạp 1'],['type'=>'2D','total_seats'=>10,'status'=>'active']);
        $seat = Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>$room->seats()->count()+1,'seat_type'=>'normal','status'=>1]);
        $show = Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addHour(),'end_time'=>now()->addHours(3),'base_price'=>70000,'status'=>'active']);
        $ticket = Ticket::create(['user_id'=>$this->customer->id,'showtime_id'=>$show->id,'seat_id'=>$seat->id,'price'=>70000,
            'status'=>$status === 'paid' ? 'confirmed' : ($status === 'refunded' ? 'cancelled' : 'pending'),'booked_at'=>'2026-09-30 10:00:00']);
        DB::table('tickets')->where('id',$ticket->id)->update(['created_at'=>'2026-09-30 10:00:00']);
        return Payment::create(['ticket_id'=>$ticket->id,'amount'=>$amount,'method'=>'cash','status'=>$status,'paid_at'=>$date]);
    }

    public function test_cinema_period_cards_charts_and_rankings_reconcile_by_payment_date(): void
    {
        $a=$this->cinema('Rạp A'); $b=$this->cinema('Rạp B');
        $this->payment($a,'2026-10-12 10:00:00',100000);
        $this->payment($a,'2026-10-05 10:00:00',70000);
        $this->payment($a,'2026-09-11 10:00:00',50000);
        $this->payment($a,'2026-09-20 10:00:00',900000);
        $this->payment($a,'2026-10-20 10:00:00',999999);
        $this->payment($b,'2026-10-12 10:00:00',500000);
        $this->payment($a,'2026-10-05 10:00:00',90000,'refunded');
        $this->payment($a,'2026-10-05 10:00:00',99000,'failed');
        $fallback=$this->payment($a,'2026-10-06 10:00:00',10000);
        DB::table('payments')->where('id',$fallback->id)->update(['paid_at'=>null,'created_at'=>'2026-10-06 10:00:00']);
        $archived=Movie::create(['title'=>'Phim đã lưu trữ','duration'=>100,'status'=>'inactive']);
        $this->payment($a,'2026-10-07 10:00:00',30000,'paid',$archived);
        $count=Payment::count();
        $response=$this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.dashboard',['cinema_id'=>$a->id]))->assertOk();
        $response->assertViewHas('stats',fn ($s)=>$s['revenue']===210000.0 && $s['revenue_previous']===50000.0 && $s['revenue_change']===320.0
            && $s['bookings']===4.0 && $s['active_users']===1.0 && $s['transactions']===6.0);
        $response->assertViewHas('chart',fn ($chart)=>count($chart)===12 && array_sum(array_column($chart,'revenue'))===210000.0 && array_sum(array_column($chart,'bookings'))===4);
        $response->assertViewHas('topMovies',fn ($rows)=>$rows->sum('revenue')==210000 && $rows->contains('status','inactive'));
        $response->assertViewHas('topCinemas',fn ($rows)=>$rows->count()===1 && $rows[0]->id===$a->id);
        $response->assertViewHas('paymentBreakdown',fn ($rows)=>$rows->sum('total')==210000 && $rows->sum('count')===4);
        $response->assertSee('Đã lưu trữ')->assertSee(route('admin.reports.export',['from'=>'2026-10-01','to'=>'2026-10-12','cinema_id'=>$a->id]));
        $this->assertSame($count,Payment::count());
    }

    public function test_ticket_count_is_not_multiplied_by_payment_attempts_or_duplicate_successes(): void
    {
        $a=$this->cinema('Rạp A'); $first=$this->payment($a,'2026-10-05 10:00:00',70000);
        foreach (['paid'=>30000,'failed'=>90000] as $status=>$amount) Payment::create(['ticket_id'=>$first->ticket_id,'amount'=>$amount,'method'=>'momo','status'=>$status,'paid_at'=>'2026-10-06 11:00:00']);
        $this->actingAs(User::factory()->create(['role'=>'manager']))->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats',fn ($s)=>$s['revenue']===100000.0 && $s['bookings']===1.0 && $s['transactions']===3.0 && $s['paid_transactions']===2.0)
            ->assertViewHas('chart',fn ($rows)=>array_sum(array_column($rows,'bookings'))===1 && $rows[4]['bookings']===1 && $rows[5]['bookings']===0)
            ->assertViewHas('topMovies',fn ($rows)=>$rows[0]->bookings===1);
    }

    public function test_day_and_year_periods_have_correct_buckets_and_keep_same_elapsed_comparisons(): void
    {
        $a=$this->cinema('Rạp A');
        $this->payment($a,'2026-10-12 10:00:00',100000);
        $this->payment($a,'2026-10-11 09:00:00',50000);
        $this->payment($a,'2026-01-15 10:00:00',20000);
        $this->payment($a,'2025-10-12 09:00:00',10000);
        $this->payment($a,'2025-11-12 09:00:00',900000);
        $this->actingAs(User::factory()->create(['role'=>'manager']))->get(route('admin.dashboard',['range'=>'day']))->assertOk()
            ->assertViewHas('stats',fn ($s)=>$s['revenue']===100000.0 && $s['revenue_previous']===50000.0)
            ->assertViewHas('chart',fn ($rows)=>count($rows)===24 && $rows[10]['revenue']===100000.0 && $rows[10]['label']==='10h');
        $this->get(route('admin.dashboard',['range'=>'year']))->assertOk()
            ->assertViewHas('stats',fn ($s)=>$s['revenue']===170000.0 && $s['revenue_previous']===10000.0)
            ->assertViewHas('chart',fn ($rows)=>count($rows)===10 && $rows[0]['revenue']===20000.0 && $rows[9]['revenue']===150000.0);
    }

    public function test_empty_previous_period_does_not_invent_a_percentage_and_empty_charts_remain_empty(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('stats',fn ($s)=>$s['revenue_change']===null && $s['success_rate_change']===null)
            ->assertViewHas('chart',fn ($rows)=>array_sum(array_column($rows,'revenue'))===0.0)->assertSee('Chưa có dữ liệu xếp hạng');
        $this->payment($this->cinema('Rạp A'),'2026-10-12 10:00:00',100000);
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Kỳ trước chưa có dữ liệu')
            ->assertViewHas('stats',fn ($s)=>$s['revenue_change']===null && $s['success_rate_change']===null);
    }

    public function test_leap_day_comparison_is_clamped_to_a_real_day(): void
    {
        $this->travelTo(Carbon::parse('2024-02-29 12:00:00'));
        $a=$this->cinema('Rạp A');
        $this->payment($a,'2024-02-29 10:00:00',70000);
        $this->payment($a,'2023-02-28 10:00:00',50000);
        $this->payment($a,'2023-03-01 10:00:00',900000);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.dashboard',['range'=>'year']))->assertOk()
            ->assertViewHas('stats',fn ($s)=>$s['revenue_previous']===50000.0)
            ->assertViewHas('comparisonLabel','01/01/2023 – 28/02/2023');
    }

    public function test_operational_counts_follow_selected_cinema_and_skip_cancelled_shows(): void
    {
        $a=$this->cinema('Rạp A'); $b=$this->cinema('Rạp B');
        $paid=$this->payment($a,'2026-10-12 10:00:00',70000);
        $this->payment($b,'2026-10-12 10:00:00',70000);
        $cancelled=$this->payment($a,'2026-10-12 10:00:00',70000);
        $cancelled->ticket->showtime->update(['status'=>'cancelled']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.dashboard',['cinema_id'=>$a->id]))->assertOk()
            ->assertViewHas('operations',fn ($s)=>$s['shows_today']===1 && $s['ready']===1 && $s['cinemas']===1 && $s['rooms']===1)
            ->assertViewHas('nextShows',fn ($rows)=>$rows->count()===1 && $rows[0]->id===$paid->ticket->showtime_id);
    }

    public function test_custom_permissions_hide_financial_data_and_unavailable_shortcuts(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','movies.manage']]))
            ->get(route('admin.dashboard'))->assertOk()->assertViewHas('stats',null)->assertDontSee('Tổng doanh thu')->assertDontSee('Tạo vé tại quầy')
            ->assertViewHas('quickLinks',fn ($links)=>$links->pluck('route')->all()===['admin.movies.index']);
        $this->actingAs(User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','reports.view']]))
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Tổng doanh thu')->assertViewHas('operations',[])->assertViewHas('quickLinks',fn ($links)=>$links->isEmpty());
        $this->actingAs(User::factory()->create(['role'=>'manager','admin_permissions'=>['dashboard.view']]))
            ->get(route('admin.dashboard'))->assertOk()->assertSee('Chưa có tác vụ quản lý')->assertDontSee(route('admin.tickets.index'));
    }

    public function test_invalid_filters_are_rejected_and_sidebar_content_order_matches_requested_order(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->get(route('admin.dashboard',['range'=>'invalid']))->assertSessionHasErrors('range');
        $this->get(route('admin.dashboard',['cinema_id'=>99999]))->assertSessionHasErrors('cinema_id');
        $response=$this->get(route('admin.dashboard'))->assertOk()->assertSee('Giao diện và nội dung');
        $dom=new \DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath=new \DOMXPath($dom);
        $nodes=$xpath->query('//div[contains(@class,"sidebar-group")][p[text()="Giao diện và nội dung"]]/a');
        $routes=[]; foreach($nodes as $node) $routes[]=$node->getAttribute('href');
        $this->assertSame(array_map(fn ($route)=>route($route),['admin.header.edit','admin.banners.index','admin.footer.edit','admin.movies.index','admin.genres.index']),array_slice($routes,0,5));
    }
}
