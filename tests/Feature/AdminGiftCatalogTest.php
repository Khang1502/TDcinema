<?php

namespace Tests\Feature;

use App\Models\{MembershipLevel, Promotion, User};
use Database\Seeders\MembershipRewardsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGiftCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MembershipRewardsSeeder::class);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
    }

    private function gift(array $changes=[]): Promotion
    {
        return Promotion::create(array_replace(['name'=>'Voucher tùy chỉnh','code'=>'CUSTOM','type'=>'fixed','value'=>20000,
            'is_member_gift'=>true,'min_member_points'=>4500,'status'=>'active','start_date'=>today(),'end_date'=>today()->addMonth()],$changes));
    }

    public function test_gifts_are_grouped_by_current_tier_thresholds_and_public_promotions_are_excluded(): void
    {
        $custom = $this->gift();
        $this->gift(['code'=>'PUBLIC_ONLY','is_member_gift'=>false]);
        $gold = MembershipLevel::where('min_points',3000)->firstOrFail();
        $platinum = MembershipLevel::where('min_points',6000)->firstOrFail();
        $this->get(route('admin.gifts.index'))->assertOk()->assertViewIs('admin.gifts.index')
            ->assertViewHas('counts',fn ($counts)=>$counts['total'] === 8 && $counts['active'] === 8)
            ->assertViewHas('groups',fn ($groups)=>$groups->get((string)$gold->id)->contains('id',$custom->id) && $groups->get((string)$platinum->id)->count() === 2)
            ->assertSee('4.500 Stars')->assertDontSee('PUBLIC_ONLY');
        $this->get(route('admin.gifts.index',['level'=>$gold->id]))->assertOk()->assertSee('CUSTOM')->assertSee('STARGOLD')
            ->assertDontSee('STARPLATINUM')->assertViewHas('promotions',fn ($gifts)=>$gifts->total() === 2);
        $this->get(route('admin.gifts.index',['level'=>$platinum->id]))->assertOk()->assertSee('STARPLATINUM')->assertSee('PLATINUM30')
            ->assertDontSee('STARDIAMOND')->assertDontSee('CUSTOM');
    }

    public function test_tier_search_type_and_dynamic_status_filters_work_together(): void
    {
        $diamond = MembershipLevel::where('min_points',10000)->firstOrFail();
        Promotion::where('code','DIAMOND40')->update(['end_date'=>today()->subDay(),'start_date'=>today()->subMonth()]);
        $this->gift(['code'=>'UPCOMING','min_member_points'=>10000,'type'=>'percent','value'=>35,'start_date'=>today()->addDay()]);
        $this->gift(['code'=>'NO_USES','min_member_points'=>10000,'usage_limit'=>0]);
        $this->gift(['code'=>'DISABLED','min_member_points'=>10000,'status'=>'expired']);
        $this->get(route('admin.gifts.index',['level'=>$diamond->id,'type'=>'percent','status'=>'expired','q'=>'DIAMOND']))
            ->assertOk()->assertSee('DIAMOND40')->assertDontSee('STARDIAMOND')->assertDontSee('UPCOMING')
            ->assertViewHas('promotions',fn ($items)=>$items->total() === 1)
            ->assertViewHas('tierCounts',fn ($counts)=>$counts[(string)$diamond->id] === 1);
        $this->get(route('admin.gifts.index',['status'=>'upcoming']))->assertSee('UPCOMING')->assertDontSee('NO_USES');
        $this->get(route('admin.gifts.index',['status'=>'exhausted']))->assertSee('NO_USES')->assertDontSee('DISABLED');
        $this->get(route('admin.gifts.index',['status'=>'active']))->assertDontSee('DIAMOND40')->assertDontSee('NO_USES')
            ->assertDontSee('UPCOMING')->assertViewHas('counts',fn ($counts)=>$counts['attention'] === 3 && $counts['upcoming'] === 1);
    }

    public function test_group_creation_prefills_stars_and_custom_thresholds_are_preserved(): void
    {
        $platinum = MembershipLevel::where('min_points',6000)->firstOrFail();
        $this->get(route('admin.gifts.create',['level'=>$platinum->id]))->assertOk()
            ->assertViewHas('giftDefaultPoints',6000)->assertSee('value="6000"',false)->assertSee('data-gift-tier-picker',false);
        $custom = $this->gift();
        $this->get(route('admin.gifts.edit',$custom))->assertOk()->assertSee('value="4500"',false)
            ->assertSee('Mốc Stars tùy chỉnh');
        $this->assertEquals(4500,$custom->fresh()->min_member_points);
        $this->get(route('admin.promotions.create'))->assertDontSee('data-gift-tier-picker',false);
    }

    public function test_pagination_keeps_filters_and_does_not_repeat_all_tiers_on_each_page(): void
    {
        $diamond = MembershipLevel::where('min_points',10000)->firstOrFail();
        foreach (range(1,19) as $number) $this->gift(['code'=>'PAGE'.$number,'min_member_points'=>10000]);
        $this->get(route('admin.gifts.index',['level'=>$diamond->id,'q'=>'PAGE','type'=>'fixed','page'=>2]))
            ->assertOk()->assertViewHas('promotions',fn ($items)=>$items->total() === 19 && $items->count() === 1)
            ->assertViewHas('groups',fn ($groups)=>$groups->keys()->all() === [$diamond->id])
            ->assertSee('q=PAGE')->assertSee('type=fixed')->assertSee('class="gifts-pagination"',false);
        $this->get(route('admin.gifts.index',['q'=>'NONE']))->assertOk()->assertSee('Chưa có quà tặng phù hợp')
            ->assertDontSee('class="gifts-pagination"',false);
    }

    public function test_disabled_tiers_and_no_tiers_do_not_hide_existing_vouchers(): void
    {
        MembershipLevel::where('min_points',6000)->update(['is_active'=>false]);
        $gold = MembershipLevel::where('min_points',3000)->firstOrFail();
        $this->get(route('admin.gifts.index'))->assertOk()->assertSee('STARPLATINUM')
            ->assertViewHas('groups',fn ($groups)=>$groups->get((string)$gold->id)->count() === 3);
        MembershipLevel::query()->update(['is_active'=>false]);
        $this->get(route('admin.gifts.index',['level'=>'other']))->assertOk()->assertSee('Mốc Stars khác')
            ->assertViewHas('promotions',fn ($items)=>$items->total() === 7);
    }
}
