<?php

namespace Tests\Feature;

use App\Models\{Cinema, Invoice, MembershipLevel, MembershipSetting, MembershipTransaction, Movie, Payment, Promotion, Room, Seat, Showtime, User};
use App\Services\MembershipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MembershipProgramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); Cache::flush(); }
    private function customer(int $points = 0): User { return User::factory()->create(['role'=>'customer','points'=>$points,'email_verified_at'=>now()]); }
    private function levelData(array $changes = []): array
    {
        return array_replace(['name'=>'Thành viên kim cương','min_points'=>5000,'discount_percent'=>15,'description'=>'Đặc quyền điện ảnh',
            'benefits_text'=>"Quà sinh nhật tại rạp\nƯu tiên hỗ trợ",'color'=>'violet','is_active'=>1], $changes);
    }
    private function booking(User $user): Showtime
    {
        $cinema = Cinema::create(['name'=>'TD Test','address'=>'01 Điện Ảnh','city'=>'TP.HCM']);
        $room = Room::create(['cinema_id'=>$cinema->id,'name'=>'Phòng 1','type'=>'2D','total_seats'=>2]);
        foreach ([1,2] as $number) Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>$number,'seat_type'=>'normal','status'=>1]);
        $movie = Movie::create(['title'=>'Phim thành viên','duration'=>90,'status'=>'now_showing']);
        $showtime = Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addDay(),'end_time'=>now()->addDay()->addHours(2),'base_price'=>60000,'status'=>'active']);
        $this->actingAs($user)->postJson(route('booking.hold',$showtime),['seats'=>['A1','A2']])->assertOk();
        $this->get(route('booking.payment',['showtime'=>$showtime,'combo_total'=>20000]))->assertOk();
        return $showtime;
    }

    public function test_guests_can_read_tiers_and_benefits_without_having_to_log_in(): void
    {
        $this->get(route('membership.index'))->assertOk()->assertSee('Cấp bậc & quyền lợi',false)->assertSee('Thành viên bạc')->assertSee('Chính sách thành viên')->assertSee('Tham gia ngay');
        $this->get(route('customer.membership'))->assertRedirect(route('login'));
    }
    public function test_points_determine_tier_and_progress_across_header_profile_and_policy(): void
    {
        $user = $this->customer(1250);
        $user->forceFill(['membership_level_id'=>MembershipLevel::where('min_points',0)->value('id')])->save();
        $summary = app(MembershipService::class)->summary($user);
        $this->assertSame('Thành viên bạc',$summary['level']->name);
        $this->assertSame(1750,$summary['remaining']);
        $this->assertEquals(12.5,$summary['progress']);
        foreach (['customer.membership','customer.policy','profile.edit','customer.tickets'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()->assertSee('Thành viên bạc')->assertSee('1.250')->assertDontSee('Tổng chi tiêu 2025')->assertDontSee('@else');
        }
        $this->get(route('home'))->assertSee('Thành viên bạc');
    }
    public function test_management_requires_its_own_permission_and_basic_staff_cannot_adjust_points(): void
    {
        $member = $this->customer();
        $this->actingAs($member)->get(route('admin.membership.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'staff']))->post(route('admin.membership.points',$member),['points'=>100,'reason'=>'Test'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role'=>'manager']))->get(route('admin.membership.index'))->assertOk();
        $this->actingAs(User::factory()->create(['role'=>'manager','admin_permissions'=>['dashboard.view']]))->get(route('admin.membership.index'))->assertForbidden();
        $this->assertSame(0,$member->fresh()->points);
    }
    public function test_admin_can_manage_tiers_and_benefits_and_changes_are_visible_publicly(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->post(route('admin.membership.levels.store'),$this->levelData())->assertRedirect(route('admin.membership.index'));
        $level = MembershipLevel::where('min_points',5000)->firstOrFail();
        $this->assertSame(['Quà sinh nhật tại rạp','Ưu tiên hỗ trợ'],$level->benefits);
        $this->get(route('membership.index'))->assertSee('Thành viên kim cương')->assertSee('Quà sinh nhật tại rạp');
        $this->put(route('admin.membership.levels.update',$level),$this->levelData(['name'=>'Kim cương mới','benefits_text'=>'Quyền lợi mới']))->assertRedirect();
        $this->get(route('membership.index'))->assertSee('Kim cương mới')->assertSee('Quyền lợi mới')->assertDontSee('Quà sinh nhật tại rạp');
        $this->put(route('admin.membership.levels.update',$level),$this->levelData(['is_active'=>0]))->assertRedirect();
        $this->get(route('membership.index'))->assertDontSee('Thành viên kim cương');
        $this->delete(route('admin.membership.levels.destroy',$level))->assertRedirect();
        $this->assertDatabaseMissing('membership_levels',['id'=>$level->id]);
    }
    public function test_invalid_tiers_cannot_break_the_base_tier_or_publish_unsafe_colors(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        foreach ([['min_points'=>1000],['discount_percent'=>101],['color'=>'url(javascript:bad)'],['benefits_text'=>str_repeat('a',161)]] as $change) {
            $this->post(route('admin.membership.levels.store'),$this->levelData($change))->assertSessionHasErrors();
        }
        $base = MembershipLevel::where('min_points',0)->firstOrFail();
        $this->put(route('admin.membership.levels.update',$base),$this->levelData(['min_points'=>0,'is_active'=>0]))->assertSessionHasErrors('min_points');
        $this->delete(route('admin.membership.levels.destroy',$base))->assertSessionHas('error');
        $this->assertTrue($base->fresh()->is_active);
    }
    public function test_admin_can_set_page_content_and_point_rate(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))->put(route('admin.membership.settings.update'),['name'=>'TD Club','title'=>'Trải nghiệm mới','subtitle'=>'Nội dung từ admin','earn_unit'=>2000,'is_enabled'=>1,'terms'=>'Chính sách mới'])->assertRedirect();
        $this->get(route('membership.index'))->assertSee('TD Club')->assertSee('Trải nghiệm mới')->assertSee('Nội dung từ admin')->assertSee('Chính sách mới')->assertSee('2.000 đ');
        $this->assertEquals(2000,MembershipSetting::current()->earn_unit);
    }
    public function test_admin_point_adjustments_are_audited_and_automatically_change_tier(): void
    {
        $member = $this->customer(950);
        $admin = User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->post(route('admin.membership.points',$member),['points'=>100,'reason'=>'Bổ sung điểm'])->assertRedirect();
        $this->assertSame(1050,$member->fresh()->points);
        $this->assertDatabaseHas('membership_transactions',['user_id'=>$member->id,'actor_id'=>$admin->id,'points'=>100,'balance_after'=>1050,'description'=>'Bổ sung điểm']);
        $this->assertSame('Thành viên bạc',$member->fresh()->member_tier->name);
        $this->post(route('admin.membership.points',$member),['points'=>-2000,'reason'=>'Không hợp lệ'])->assertSessionHasErrors('points');
        $this->post(route('admin.membership.points',$member),['points'=>100])->assertSessionHasErrors('reason');
        $this->assertSame(1050,$member->fresh()->points);
        $this->actingAs($member->fresh())->get(route('customer.membership'))->assertSee('Bổ sung điểm')->assertSee('+100');
    }
    public function test_member_search_and_tier_filters_use_current_points_and_hide_staff(): void
    {
        $silver = $this->customer(1500);
        $gold = $this->customer(4000);
        $staff = User::factory()->create(['role'=>'staff','name'=>'Không phải hội viên']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.membership.members',['level'=>MembershipLevel::where('min_points',1000)->value('id')]))->assertSee($silver->email)->assertDontSee($gold->email)->assertDontSee($staff->name);
        $this->get(route('admin.membership.members',['q'=>$gold->email]))->assertSee($gold->email)->assertDontSee($silver->email);
    }
    public function test_member_discount_is_used_in_invoice_and_points_are_awarded_once_on_paid_amount(): void
    {
        config(['services.momo.mode'=>'demo']);
        $user = $this->customer(1000);
        $showtime = $this->booking($user);
        $this->get(route('booking.payment',$showtime))->assertViewHas('memberDiscount',7000)->assertViewHas('promoDiscount',7000);
        $response = $this->post(route('booking.payment.process',$showtime),['payment_method'=>'momo','total'=>1]);
        $this->assertSame(1000,$user->fresh()->points);
        $url = $response->headers->get('Location');
        $this->get($url)->assertViewHas('success',true)->assertViewHas('grandTotal',133000)->assertViewHas('pointsEarned',133);
        $this->assertEquals(133000,Payment::sum('amount'));
        $invoice = Invoice::firstOrFail();
        $this->assertEquals(7000,$invoice->membership_discount);
        $this->assertSame('Thành viên bạc',$invoice->membership_level_name);
        $this->assertSame(1133,$user->fresh()->points);
        $this->assertSame(0,app(MembershipService::class)->award($invoice));
        $this->get($url)->assertViewHas('success',false);
        $this->assertDatabaseCount('membership_transactions',1);
        $this->assertSame(1133,$user->fresh()->points);
    }
    public function test_coupon_replaces_member_discount_and_removing_it_restores_the_tier_discount(): void
    {
        $user = $this->customer(3000);
        $showtime = $this->booking($user);
        $promo = Promotion::create(['name'=>'Ưu đãi','code'=>'MEMBERTEST','type'=>'fixed','value'=>25000,'start_date'=>today(),'end_date'=>today()->addDay(),'status'=>'active','usage_limit'=>2]);
        $this->postJson(route('booking.promo.apply'),['code'=>$promo->code,'showtime_id'=>$showtime->id])->assertJsonPath('discount',25000);
        $this->get(route('booking.payment',$showtime))->assertViewHas('promoDiscount',25000);
        $this->deleteJson(route('booking.promo.remove',['showtime_id'=>$showtime->id]))->assertJsonPath('discount',14000);
        $this->get(route('booking.payment',$showtime))->assertViewHas('promoDiscount',14000);
        $response = $this->post(route('booking.payment.process',$showtime),['payment_method'=>'onepay','promo_code'=>$promo->code]);
        $this->get($response->headers->get('Location'))->assertViewHas('grandTotal',115000);
        $this->assertEquals(0,Invoice::first()->membership_discount);
        $this->assertSame(3115,$user->fresh()->points);
        $this->assertEquals(1,$promo->fresh()->usage_limit);
    }
    public function test_failed_payment_never_awards_points_and_disabling_program_keeps_existing_balance(): void
    {
        $user = $this->customer(1000);
        $showtime = $this->booking($user);
        $response = $this->post(route('booking.payment.process',$showtime),['payment_method'=>'onepay']);
        $this->get(str_replace('resultCode=0','resultCode=1',$response->headers->get('Location')))->assertViewHas('success',false);
        $this->assertSame(1000,$user->fresh()->points);
        $this->assertDatabaseCount('membership_transactions',0);
        MembershipSetting::current()->update(['is_enabled'=>false]);
        app(MembershipService::class)->refresh();
        $this->get(route('booking.payment',$showtime))->assertViewHas('memberDiscount',0);
        $response = $this->post(route('booking.payment.process',$showtime),['payment_method'=>'onepay']);
        $this->get($response->headers->get('Location'))->assertViewHas('grandTotal',140000)->assertViewHas('pointsEarned',0);
        $this->assertSame(1000,$user->fresh()->points);
        $this->assertDatabaseCount('membership_transactions',0);
    }
    public function test_point_award_failure_rolls_back_tickets_invoice_and_promotion_use(): void
    {
        $user = $this->customer(950);
        $showtime = $this->booking($user);
        $this->partialMock(MembershipService::class,fn ($mock) => $mock->shouldReceive('award')->once()->andThrow(\Illuminate\Validation\ValidationException::withMessages(['points'=>'Điểm chưa thể cộng.'])));
        $response = $this->post(route('booking.payment.process',$showtime),['payment_method'=>'onepay']);
        $this->get($response->headers->get('Location'))->assertViewHas('success',false);
        $this->assertDatabaseCount('invoices',0);
        $this->assertDatabaseCount('tickets',0);
        $this->assertSame(950,$user->fresh()->points);
    }

    public function test_failure_after_point_award_rolls_back_points_ledger_and_all_booking_records(): void
    {
        $user = $this->customer(950);
        $showtime = $this->booking($user);
        $promo = Promotion::create(['name'=>'Ưu đãi','code'=>'ROLLBACKSTARS','type'=>'fixed','value'=>20000,'start_date'=>today(),'end_date'=>today()->addDay(),'status'=>'active','usage_limit'=>2]);
        \Illuminate\Support\Facades\Event::listen('eloquent.creating: App\\Models\\FoodOrder', function () use ($user) {
            $this->assertSame(1070, $user->fresh()->points);
            $this->assertDatabaseCount('membership_transactions', 1);
            throw \Illuminate\Validation\ValidationException::withMessages(['combo'=>'Không thể hoàn tất đơn combo.']);
        });
        $response = $this->post(route('booking.payment.process',$showtime),['payment_method'=>'onepay','promo_code'=>$promo->code]);
        $this->get($response->headers->get('Location'))->assertViewHas('success',false)->assertViewHas('pointsEarned',0);
        foreach (['tickets','payments','invoices','food_orders','membership_transactions'] as $table) $this->assertDatabaseCount($table,0);
        $this->assertSame(950,$user->fresh()->points);
        $this->assertEquals(0,$user->fresh()->total_spent);
        $this->assertSame(2,$promo->fresh()->usage_limit);
    }
}
