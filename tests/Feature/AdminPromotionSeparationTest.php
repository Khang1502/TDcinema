<?php

namespace Tests\Feature;

use App\Models\{Promotion, User};
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPromotionSeparationTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $changes=[]): array
    {
        return array_replace(['name'=>'Ưu đãi chung','code'=>'COMMON','type'=>'fixed','value'=>20000,'status'=>'active',
            'start_date'=>today()->toDateString(),'end_date'=>today()->addDays(7)->toDateString(),'show_on_home'=>true], $changes);
    }

    public function test_admin_lists_counts_and_forms_are_separate_for_promotions_and_member_gifts(): void
    {
        $common = Promotion::create($this->data());
        $gift = Promotion::create($this->data(['code'=>'GIFT','name'=>'Quà riêng','is_member_gift'=>true,'min_member_points'=>100]));
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.promotions.index'))->assertOk()->assertSee($common->code)->assertDontSee($gift->code)
            ->assertViewHas('counts',fn ($counts) => $counts['total']===1);
        $this->get(route('admin.gifts.index'))->assertOk()->assertSee($gift->code)->assertDontSee($common->code)->assertSee('100 Stars')->assertViewHas('counts',fn ($counts) => $counts['total']===1);
        $this->get(route('admin.promotions.create'))->assertOk()->assertDontSee('name="min_member_points"', false)->assertDontSee('name="is_member_gift"',false);
        $this->get(route('admin.gifts.create'))->assertOk()->assertSee('name="min_member_points"',false)->assertDontSee('name="show_on_home"',false);
    }

    public function test_category_cannot_be_changed_by_forged_form_inputs_or_other_category_routes(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']))->post(route('admin.promotions.store'),$this->data(['is_member_gift'=>true,'min_member_points'=>900]))->assertSessionHasNoErrors();
        $common=Promotion::where('code','COMMON')->firstOrFail();
        $this->assertFalse($common->is_member_gift);
        $this->assertSame(0,$common->min_member_points);
        $this->post(route('admin.gifts.store'),$this->data(['code'=>'GIFT','is_member_gift'=>false,'min_member_points'=>100,'show_on_home'=>true]))->assertSessionHasNoErrors();
        $gift=Promotion::where('code','GIFT')->firstOrFail();
        $this->assertTrue($gift->is_member_gift);
        $this->assertFalse($gift->show_on_home);
        $this->get(route('admin.promotions.edit',$gift))->assertNotFound();
        $this->put(route('admin.promotions.update',$gift),$this->data())->assertNotFound();
        $this->delete(route('admin.promotions.destroy',$gift))->assertNotFound();
        $this->get(route('admin.gifts.edit',$common))->assertNotFound();
        $this->assertModelExists($gift);
    }

    public function test_public_promotions_and_home_never_repeat_member_gifts_but_account_only_shows_eligible_gifts(): void
    {
        $common=Promotion::create($this->data());
        $gift=Promotion::create($this->data(['code'=>'GIFT','name'=>'Quà riêng','is_member_gift'=>true,'min_member_points'=>100]));
        $this->actingAs(User::factory()->create(['role'=>'customer','points'=>100]));
        $this->get(route('home'))->assertOk()->assertViewHas('promotions',fn ($items) => $items->modelKeys()===[$common->id]);
        $this->get(route('promotions.index'))->assertOk()->assertSee($common->code)->assertDontSee($gift->code)->assertViewHas('counts',fn ($counts) => $counts['active']===1);
        $this->get(route('customer.gifts'))->assertOk()->assertSee($gift->code)->assertDontSee($common->code);
        $this->assertSame($gift->id,app(PromotionService::class)->findAvailable($gift->code)->id);
        $this->assertSame($common->id,app(PromotionService::class)->findAvailable($common->code)->id);
    }

    public function test_existing_image_path_is_visible_and_upload_empty_does_not_remove_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('promotions/saved.png','old image');
        $promo=Promotion::create($this->data(['thumbnail'=>'promotions/saved.png']));
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.promotions.edit',$promo))->assertOk()->assertSee('promotions/saved.png')->assertSee('Đường dẫn ảnh đã lưu');
        $this->put(route('admin.promotions.update',$promo),$this->data())->assertSessionHasNoErrors();
        $this->assertSame('promotions/saved.png',$promo->fresh()->thumbnail);
        Storage::disk('public')->assertExists('promotions/saved.png');
        $this->put(route('admin.promotions.update',$promo),$this->data(['remove_thumbnail'=>1]))->assertSessionHasNoErrors();
        $this->assertNull($promo->fresh()->thumbnail);
        Storage::disk('public')->assertMissing('promotions/saved.png');
    }

    public function test_replacing_shared_image_preserves_other_record_and_remote_url_is_rendered_correctly(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('promotions/shared.png','old image');
        $promo=Promotion::create($this->data(['thumbnail'=>'promotions/shared.png']));
        $other=Promotion::create($this->data(['code'=>'OTHER','thumbnail'=>'promotions/shared.png']));
        $this->actingAs(User::factory()->create(['role'=>'admin']))->put(route('admin.promotions.update',$promo),$this->data(['thumbnail'=>UploadedFile::fake()->image('new.png'),'remove_thumbnail'=>1]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists('promotions/shared.png');
        Storage::disk('public')->assertExists($promo->fresh()->thumbnail);
        $other->update(['thumbnail'=>'https://example.com/banner.png']);
        $this->get(route('admin.promotions.edit',$other))->assertOk()->assertSee('src="https://example.com/banner.png"',false)->assertDontSee('storage/https:',false);
    }
}
