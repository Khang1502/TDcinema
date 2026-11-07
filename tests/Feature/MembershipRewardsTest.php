<?php

namespace Tests\Feature;

use App\Models\{Cinema, Invoice, MembershipLevel, Movie, Payment, Promotion, Room, Seat, Showtime, User};
use App\Services\{MembershipService, PromotionService};
use Database\Seeders\MembershipRewardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipRewardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tiers_use_points_and_apply_the_correct_discount(): void
    {
        $this->seed(MembershipRewardsSeeder::class);
        $this->assertDatabaseCount('membership_levels',5);
        $service = app(MembershipService::class);
        foreach ([[5999,'Thành viên vàng',10],[6000,'Thành viên bạch kim',15],[9999,'Thành viên bạch kim',15],[10000,'Thành viên kim cương',20]] as [$points,$name,$discount]) {
            $user = User::factory()->create(['role'=>'customer','points'=>$points]);
            $this->assertSame($name,$service->summary($user)['level']->name);
            $this->assertSame($discount*2000,$service->discount($user,200000));
        }
        $this->assertNull($service->summary($user)['next']);
    }

    public function test_vouchers_are_available_only_when_the_member_has_enough_points(): void
    {
        $this->seed(MembershipRewardsSeeder::class);
        $this->assertDatabaseCount('promotions',7);
        $user = User::factory()->create(['role'=>'customer','points'=>6000]);
        $this->actingAs($user);
        $this->assertSame(80000,app(PromotionService::class)->findAvailable('STARPLATINUM')->discountFor(200000));
        $this->assertSame(60000,app(PromotionService::class)->findAvailable('PLATINUM30')->discountFor(200000));
        $this->assertFalse(Promotion::available()->eligibleFor($user)->where('code','DIAMOND40')->exists());
        $this->post(route('promotions.select',Promotion::where('code','DIAMOND40')->first()))->assertSessionHasErrors('promo_code');
        $this->assertSame(6000,$user->fresh()->points);
        $user->forceFill(['points'=>10000])->save();
        $this->assertSame(80000,app(PromotionService::class)->findAvailable('DIAMOND40')->discountFor(200000));
        $this->assertSame(70000,app(PromotionService::class)->findAvailable('STARDIAMOND')->discountFor(70000));
    }

    public function test_guest_and_customer_see_rewards_and_admin_can_edit_new_tiers_and_vouchers(): void
    {
        $this->seed(MembershipRewardsSeeder::class);
        $gift = Promotion::where('code','DIAMOND40')->first();
        $level = MembershipLevel::where('min_points',10000)->first();
        $this->get(route('membership.index'))->assertOk()->assertSee('Thành viên bạch kim')->assertSee('Thành viên kim cương')->assertSee('Voucher thành viên')->assertSee('Từ 10.000 Stars')->assertDontSee('Chọn voucher &amp; đặt vé',false);
        $this->actingAs(User::factory()->create(['role'=>'customer','points'=>10000]))->get(route('membership.index'))->assertOk()->assertSee('Đã mở khóa')->assertSee(route('promotions.select',$gift));
        $this->post(route('promotions.select',$gift))->assertRedirect(route('movies.index'))->assertSessionHas('promotion.preferred_code','DIAMOND40');
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.membership.levels.edit',$level))->assertOk()->assertSee('diamond');
        $this->put(route('admin.membership.levels.update',$level),['name'=>$level->name,'min_points'=>10000,'discount_percent'=>22,'description'=>$level->description,'benefits_text'=>"Ưu đãi mới\nHỗ trợ tại rạp",'color'=>'diamond','is_active'=>1])->assertSessionHasNoErrors();
        $this->get(route('membership.index'))->assertSee('Ưu đãi mới')->assertSee('22');
        $this->get(route('admin.gifts.edit',$gift))->assertOk()->assertSee('DIAMOND40');
        $gift->update(['status'=>'expired']);
        $this->get(route('membership.index'))->assertDontSee('data-member-gift="DIAMOND40"',false);
    }

    public function test_repeat_install_preserves_points_existing_ids_customizations_and_voucher_usage(): void
    {
        $base = MembershipLevel::where('min_points',0)->firstOrFail();
        $member = User::factory()->create(['role'=>'customer','points'=>1500,'membership_level_id'=>$base->id]);
        $this->seed(MembershipRewardsSeeder::class);
        $level = MembershipLevel::where('min_points',10000)->firstOrFail();
        $level->update(['discount_percent'=>23,'benefits'=>['Quyền lợi do admin sửa']]);
        $gift = Promotion::where('code','DIAMOND40')->firstOrFail();
        $gift->update(['usage_limit'=>17,'value'=>42]);
        $this->seed(MembershipRewardsSeeder::class);
        $this->assertDatabaseCount('membership_levels',5);
        $this->assertDatabaseCount('promotions',7);
        $this->assertEquals(1500,$member->fresh()->points);
        $this->assertEquals($base->id,$member->fresh()->membership_level_id);
        $this->assertEquals(23,$level->fresh()->discount_percent);
        $this->assertSame(['Quyền lợi do admin sửa'],$level->fresh()->benefits);
        $this->assertEquals(17,$gift->fresh()->usage_limit);
        $this->assertEquals(42,$gift->fresh()->value);
    }

    public function test_diamond_voucher_is_used_in_checkout_without_stacking_or_spending_stars(): void
    {
        $this->seed(MembershipRewardsSeeder::class);
        config(['services.momo.mode'=>'demo']);
        $user = User::factory()->create(['role'=>'customer','points'=>10000,'email_verified_at'=>now()]);
        $cinema = Cinema::create(['name'=>'Rewards test','address'=>'Test','city'=>'HCM']);
        $room = Room::create(['cinema_id'=>$cinema->id,'name'=>'Rạp 1','type'=>'2D','total_seats'=>1]);
        Seat::create(['room_id'=>$room->id,'seat_row'=>'A','seat_number'=>1,'seat_type'=>'normal','status'=>1,'extra_price'=>0]);
        $movie = Movie::create(['title'=>'Rewards test','duration'=>90,'status'=>'now_showing']);
        $show = Showtime::create(['movie_id'=>$movie->id,'room_id'=>$room->id,'start_time'=>now()->addDay(),'end_time'=>now()->addDay()->addHours(2),'base_price'=>100000,'status'=>'active']);
        $this->actingAs($user)->postJson(route('booking.hold',$show),['seats'=>['A1']])->assertOk();
        $this->get(route('booking.payment',['showtime'=>$show,'combo_total'=>20000]))->assertOk()->assertViewHas('memberDiscount',24000);
        $this->postJson(route('booking.promo.apply'),['code'=>'DIAMOND40','showtime_id'=>$show->id])->assertJsonPath('discount',48000)->assertJsonPath('total',72000);
        $this->get(route('booking.payment',$show))->assertViewHas('promoDiscount',48000);
        $response = $this->post(route('booking.payment.process',$show),['payment_method'=>'momo','promo_code'=>'DIAMOND40']);
        $this->get($response->headers->get('Location'))->assertViewHas('success',true)->assertViewHas('grandTotal',72000)->assertViewHas('pointsEarned',72);
        $this->assertEquals(72000,Payment::sum('amount'));
        $this->assertEquals(72000,Invoice::firstOrFail()->total_amount);
        $this->assertEquals(0,Invoice::firstOrFail()->membership_discount);
        $this->assertEquals(10072,$user->fresh()->points);
        $this->assertEquals(59,Promotion::where('code','DIAMOND40')->value('usage_limit'));
    }
}
