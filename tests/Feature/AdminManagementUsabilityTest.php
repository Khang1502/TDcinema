<?php

namespace Tests\Feature;

use App\Models\{Cinema, FooterSetting, MembershipLevel, Movie, Room, Showtime, User};
use Database\Seeders\MembershipRewardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminManagementUsabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role'=>'admin']));
    }

    private function show(Movie $movie, string $date, ?Cinema $cinema=null, string $status='active'): Showtime
    {
        $cinema ??= Cinema::create(['name'=>'Rạp thử '.Cinema::count(),'address'=>'Test','city'=>'HCM']);
        $room = Room::create(['cinema_id'=>$cinema->id,'name'=>'Rạp 1','type'=>'2D']);
        return Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>$date,
            'end_time'=>\Carbon\Carbon::parse($date)->addHours(2),'base_price'=>70000,'status'=>$status]);
    }

    public function test_movie_catalog_distinguishes_future_active_shows_from_history_and_cancelled_shows(): void
    {
        $live = Movie::create(['title'=>'Phim có lịch','duration'=>90,'status'=>'now_showing']);
        $missing = Movie::create(['title'=>'Phim thiếu lịch','duration'=>100,'status'=>'now_showing']);
        Movie::create(['title'=>'Phim chưa chiếu','duration'=>90,'status'=>'upcoming']);
        $this->show($live,now()->addDay()->toDateTimeString());
        $this->show($live,now()->subDay()->toDateTimeString());
        $this->show($missing,now()->addDay()->toDateTimeString(),status:'cancelled');
        $this->get(route('admin.movies.index'))->assertOk()->assertViewHas('counts',fn ($counts)=>$counts['missing'] === 1)
            ->assertViewHas('movies',fn ($items)=>$items->firstWhere('id',$live->id)->upcoming_showtimes_count === 1
                && $items->firstWhere('id',$live->id)->showtimes_count === 2 && $items->firstWhere('id',$live->id)->next_showtime_at !== null);
        $this->get(route('admin.movies.index',['schedule'=>'missing']))->assertViewHas('movies',fn ($items)=>$items->pluck('id')->all() === [$missing->id]);
        $this->get(route('admin.movies.index',['schedule'=>'scheduled']))->assertViewHas('movies',fn ($items)=>$items->pluck('id')->all() === [$live->id]);
    }

    public function test_movie_search_and_filters_are_combined_and_sort_is_stable(): void
    {
        $one = Movie::create(['title'=>'Phi Vụ Động Trời 2','director'=>'Nguyễn Thanh','genre'=>'Hoạt hình, Gia đình','age_rating'=>'P','duration'=>100,'status'=>'now_showing','release_date'=>'2026-10-10']);
        Movie::create(['title'=>'Phi Vụ Động Trời 1','genre'=>'Hành động','age_rating'=>'T13','duration'=>100,'status'=>'upcoming','release_date'=>'2026-10-11']);
        $this->get(route('admin.movies.index',['q'=>'  DONG   phi vu 2 ','status'=>'now_showing','genre'=>'Gia đình','age'=>'P','view'=>'grid']))
            ->assertOk()->assertViewHas('movies',fn ($items)=>$items->pluck('id')->all() === [$one->id]);
        $this->get(route('admin.movies.index',['q'=>'nguyen thanh']))->assertViewHas('movies',fn ($items)=>$items->total() === 1);
        $this->get(route('admin.movies.index',['q'=>'%_']))->assertViewHas('movies',fn ($items)=>$items->total() === 0);
        foreach(range(1,23) as $number) Movie::create(['title'=>'Trang thử '.$number,'duration'=>90,'status'=>'now_showing']);
        $this->get(route('admin.movies.index',['q'=>'Trang thử','per_page'=>20,'view'=>'grid','page'=>2]))
            ->assertViewHas('movies',fn ($items)=>$items->total() === 23 && $items->count() === 3 && str_contains($items->url(1),'per_page=20'));
    }

    public function test_movie_action_links_obey_permissions_and_upcoming_cannot_create_showtimes(): void
    {
        Movie::create(['title'=>'Sắp chiếu','duration'=>90,'status'=>'upcoming']);
        $this->actingAs(User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','movies.manage']]))
            ->get(route('admin.movies.index'))->assertOk()->assertDontSee('Báo cáo doanh thu')->assertDontSee('Thêm suất chiếu');
    }

    public function test_showtime_keyword_cinema_movie_and_day_are_combined_with_accent_insensitive_search(): void
    {
        $movie = Movie::create(['title'=>'Phi Vụ Động Trời 2','duration'=>100,'status'=>'now_showing']);
        $other = Movie::create(['title'=>'Trái Tim Quái Thú','duration'=>100,'status'=>'now_showing']);
        $cinema = Cinema::create(['name'=>'TD Cao Lỗ','address'=>'Test','city'=>'HCM']);
        $match = $this->show($movie,'2026-10-12 00:00:00',$cinema);
        $this->show($movie,'2026-10-13 00:00:00',$cinema);
        $this->show($movie,'2026-10-12 12:00:00');
        $this->show($other,'2026-10-12 12:00:00',$cinema);
        $filters = ['q'=>' DONG   PHI vu 2 ','movie_id'=>$movie->id,'cinema_id'=>$cinema->id,'date'=>'2026-10-12'];
        $this->get(route('admin.showtimes.index',$filters))->assertOk()
            ->assertViewHas('showtimes',fn ($items)=>$items->pluck('id')->all() === [$match->id]);
        $this->get(route('admin.showtimes.index',array_replace($filters,['movie_id'=>$other->id])))
            ->assertOk()->assertViewHas('showtimes',fn ($items)=>$items->total() === 0);
        $this->get(route('admin.showtimes.index',array_replace($filters,['date'=>''])))
            ->assertViewHas('date',null)->assertViewHas('showtimes',fn ($items)=>$items->total() === 2);
    }

    public function test_showtime_filters_validate_dates_and_ids_and_keep_context_on_other_pages(): void
    {
        $movie = Movie::create(['title'=>'Lịch thử','duration'=>90,'status'=>'now_showing']);
        $cinema = Cinema::create(['name'=>'Rạp lọc','address'=>'Test','city'=>'HCM']);
        foreach(range(1,21) as $number) $this->show($movie,'2026-10-12 09:00:00',$cinema);
        $this->get(route('admin.showtimes.index',['q'=>'lich thu','movie_id'=>$movie->id,'cinema_id'=>$cinema->id,'date'=>'2026-10-12','page'=>2]))
            ->assertOk()->assertViewHas('showtimes',fn ($items)=>$items->total() === 21 && $items->count() === 1
                && str_contains($items->url(1),'q=lich%20thu') && str_contains($items->url(1),'cinema_id='.$cinema->id));
        foreach(['date'=>'2026-02-30','movie_id'=>999999,'cinema_id'=>999999] as $field=>$value)
            $this->getJson(route('admin.showtimes.index',[$field=>$value]))->assertUnprocessable()->assertJsonValidationErrors($field);
    }

    public function test_footer_link_order_and_clearing_all_rows_are_saved_without_losing_other_content(): void
    {
        $data = array_replace(FooterSetting::DEFAULTS,['support_links'=>[
            ['label'=>'Thành viên','url'=>'/thanh-vien'],['label'=>'Phim','url'=>'/movies'],'empty'=>['label'=>'','url'=>'']],
            'editorial_links'=>['empty'=>['label'=>'','url'=>'']]]);
        $this->put(route('admin.footer.update'),$data)->assertSessionHasNoErrors();
        $this->assertSame(['Thành viên','Phim'],array_column(FooterSetting::current()->support_links,'label'));
        $this->assertSame([],FooterSetting::current()->editorial_links);
        $this->assertSame(FooterSetting::DEFAULTS['company_name'],FooterSetting::current()->company_name);
        $this->get(route('home'))->assertSeeInOrder(['Thành viên','Phim']);
    }

    public function test_membership_counts_use_current_stars_and_active_thresholds_not_stored_foreign_keys(): void
    {
        $this->seed(MembershipRewardsSeeder::class);
        $base = MembershipLevel::where('min_points',0)->firstOrFail();
        $platinum = MembershipLevel::where('min_points',6000)->firstOrFail();
        $diamond = MembershipLevel::where('min_points',10000)->firstOrFail();
        foreach([0,5999,6000,9999,10000] as $points) User::factory()->create(['role'=>'customer','points'=>$points,'membership_level_id'=>$base->id]);
        $this->get(route('admin.membership.index'))->assertOk()->assertViewHas('memberCount',5)
            ->assertViewHas('tierMembers',fn ($counts)=>$counts[$platinum->id] === 2 && $counts[$diamond->id] === 1 && $counts[$base->id] === 1);
        $platinum->update(['is_active'=>false]);
        $gold = MembershipLevel::where('min_points',3000)->firstOrFail();
        $this->get(route('admin.membership.index',['state'=>'disabled']))->assertViewHas('levels',fn ($levels)=>$levels->modelKeys() === [$platinum->id])
            ->assertViewHas('tierMembers',fn ($counts)=>$counts[$platinum->id] === 0 && $counts[$gold->id] === 3);
        $this->get(route('admin.membership.index',['q'=>'kim cuong']))->assertViewHas('levels',fn ($levels)=>$levels->modelKeys() === [$diamond->id]);
    }
}
