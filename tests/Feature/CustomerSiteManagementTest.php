<?php

namespace Tests\Feature;

use App\Models\FooterSetting;
use App\Models\HeaderSetting;
use App\Models\Promotion;
use App\Models\PromotionDisplaySetting;
use App\Models\User;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerSiteManagementTest extends TestCase
{
    use RefreshDatabase;

    private function promo(array $data=[]): Promotion
    {
        return Promotion::create(array_replace(['name'=>'Ưu đãi kiểm thử','code'=>'TEST'.Promotion::count(),'type'=>'fixed','value'=>20000,'status'=>'active','start_date'=>today(),'end_date'=>today()->addDays(7),'show_on_home'=>true],$data));
    }

    public function test_new_management_pages_obey_individual_permissions(): void
    {
        foreach(['admin','manager'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]));
            foreach(['admin.website.index','admin.header.edit','admin.footer.edit','admin.promotions.display','admin.gifts.index','admin.gifts.create'] as $route) $this->get(route($route))->assertOk();
        }
        foreach(['staff','customer'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role]));
            foreach(['admin.header.edit','admin.footer.edit','admin.promotions.display','admin.gifts.index'] as $route) $this->get(route($route))->assertForbidden();
            foreach(['admin.header.update','admin.footer.update','admin.promotions.display.update'] as $route) $this->put(route($route),[])->assertForbidden();
            $this->post(route('admin.gifts.store'),[])->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','footer.manage']]));
        $this->get(route('admin.footer.edit'))->assertOk();
        $this->get(route('admin.header.edit'))->assertForbidden();
        $this->get(route('admin.website.index'))->assertOk()->assertSee('Footer')->assertDontSee('Quản lý quà tặng thành viên');
    }

    public function test_footer_edits_are_shared_and_can_be_hidden(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $data=array_replace(FooterSetting::DEFAULTS,['company_name'=>'Công ty mới','email'=>'hello@cinema.test','support_links'=>[['label'=>'Quyền lợi mới','url'=>'/thanh-vien']], 'facebook_url'=>'https://facebook.com/tdcinema']);
        $this->put(route('admin.footer.update'),$data)->assertSessionHasNoErrors()->assertRedirect();
        foreach(['home','movies.index','cinemas.index','promotions.index','membership.index'] as $route) $this->get(route($route))->assertOk()->assertSee('Công ty mới')->assertSee('hello@cinema.test')->assertSee('Quyền lợi mới');
        $this->get(route('cinema-corner.index','blog'))->assertOk()->assertSee('Công ty mới');
        $this->actingAs(User::factory()->create(['role'=>'customer']));
        foreach(['profile.edit','customer.tickets','customer.gifts'] as $route) {
            $response=$this->get(route($route))->assertOk()->assertSee('Công ty mới');
            $this->assertSame(1,substr_count($response->getContent(),'id="site-footer"'));
        }
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->put(route('admin.footer.update'),array_replace($data,['is_visible'=>false]))->assertSessionHasNoErrors();
        $this->get(route('home'))->assertDontSee('id="site-footer"',false);
    }

    public function test_footer_rejects_unsafe_links_and_incomplete_rows_without_changing_existing_content(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $this->put(route('admin.footer.update'),array_replace(FooterSetting::DEFAULTS,['support_links'=>[['label'=>'Bad','url'=>'javascript:alert(1)']], 'email'=>'not-email']))
            ->assertSessionHasErrors(['support_links.0.url','email']);
        $this->assertSame(FooterSetting::DEFAULTS['support_links'],FooterSetting::current()->support_links);
        $this->put(route('admin.footer.update'),array_replace(FooterSetting::DEFAULTS,['support_links'=>[['label'=>'Only label','url'=>'']]]))->assertSessionHasErrors('support_links.0.url');
        $this->put(route('admin.footer.update'),array_replace(FooterSetting::DEFAULTS,['support_links'=>[['label'=>'','url'=>'']],'editorial_links'=>[]]))->assertSessionHasNoErrors();
        $this->assertSame([],FooterSetting::current()->support_links);
    }

    public function test_header_controls_menu_order_visibility_links_and_about_content_on_all_pages(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $data=HeaderSetting::DEFAULTS; unset($data['logo_path']);
        $data['brand_name']='Khang Cinema'; $data['about_title']='Giới thiệu mới'; $data['about_body']='Nội dung do admin quản lý.';
        $data['menus']['events']['label']='Ưu đãi mới'; $data['menus']['events']['sort_order']=0;
        $data['menus']['movies']['sort_order']=2; $data['menus']['shop']['is_visible']=false;
        $data['menus']['events']['children']=[['label'=>'Trang ưu đãi riêng','url'=>'/khuyen-mai','description'=>'Mô tả do admin sửa']];
        $data['show_search']=false;
        $this->put(route('admin.header.update'),$data)->assertSessionHasNoErrors()->assertRedirect();
        foreach(['home','movies.index','cinemas.index','membership.index'] as $route) {
            $response=$this->get(route($route))->assertOk()->assertSee('Khang Cinema')->assertSee('Ưu đãi mới')->assertSee('Mô tả do admin sửa');
            $this->assertStringNotContainsString('class="header-search-form"',$response->getContent());
        }
        $response=$this->get(route('home'))->assertSee('Giới thiệu mới')->assertSee('Nội dung do admin quản lý.');
        $this->assertLessThan(strpos($response->getContent(),'aria-haspopup="true">Phim'),strpos($response->getContent(),'aria-haspopup="true">Ưu đãi mới'));
        $data['menus']['events']['url']='//evil.test';
        $this->put(route('admin.header.update'),$data)->assertSessionHasErrors('menus.events.url');
        $this->assertSame('/khuyen-mai',HeaderSetting::current()->menus['events']['url']);
    }

    public function test_promotion_display_settings_control_mode_timing_and_home_visibility_order(): void
    {
        $a=$this->promo(['name'=>'Promo A','sort_order'=>20]);
        $b=$this->promo(['name'=>'Promo B','sort_order'=>10]);
        $hidden=$this->promo(['name'=>'Hidden promo','show_on_home'=>false]);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $data=array_replace(PromotionDisplaySetting::DEFAULTS,['title'=>'Tiêu đề ưu đãi','display_mode'=>'cards','autoplay'=>false,'interval_seconds'=>7]);
        $this->put(route('admin.promotions.display.update'),$data)->assertSessionHasNoErrors();
        $response=$this->get(route('home'))->assertOk()->assertSee('Tiêu đề ưu đãi')->assertSee('data-mode="cards"',false)->assertSee('data-autoplay="0"',false)->assertSee('data-interval="7000"',false)->assertDontSee('Hidden promo');
        $this->assertLessThan(strpos($response->getContent(),'Promo A'),strpos($response->getContent(),'Promo B'));
        $this->get(route('promotions.index'))->assertSee('Hidden promo');
        $this->put(route('admin.promotions.display.update'),array_replace($data,['interval_seconds'=>1]))->assertSessionHasErrors('interval_seconds');
        $this->put(route('admin.promotions.display.update'),array_replace($data,['is_visible'=>false]))->assertSessionHasNoErrors();
        $this->get(route('home'))->assertDontSee('data-offer-carousel',false);
    }

    public function test_gifts_can_be_managed_separately_and_only_eligible_members_can_use_their_codes(): void
    {
        $regular=$this->promo(['name'=>'Regular promo']);
        $admin=User::factory()->create(['role'=>'staff','admin_permissions'=>['dashboard.view','gifts.manage']]);
        $this->actingAs($admin);
        $data=['name'=>'Quà thành viên','code'=>'MEMBER100','type'=>'percent','value'=>15,'status'=>'active','start_date'=>today()->toDateString(),'end_date'=>today()->addDays(10)->toDateString(),'min_member_points'=>100,'description'=>'Quà cho thành viên đạt 100 Stars'];
        $this->post(route('admin.gifts.store'),$data)->assertRedirect(route('admin.gifts.index'))->assertSessionHasNoErrors();
        $gift=Promotion::where('code','MEMBER100')->firstOrFail();
        $this->assertTrue($gift->is_member_gift);
        $this->get(route('admin.gifts.edit',$gift))->assertOk();
        $this->get(route('admin.gifts.edit',$regular))->assertNotFound();
        $this->put(route('admin.gifts.update',$regular),$data)->assertNotFound();
        $this->get(route('admin.gifts.index'))->assertSee('Quà thành viên')->assertDontSee('Regular promo');
        $member=User::factory()->create(['role'=>'customer','points'=>99]);
        $this->actingAs($member)->get(route('customer.gifts'))->assertOk()->assertDontSee('MEMBER100')->assertDontSee($regular->code);
        try { app(PromotionService::class)->findAvailable('MEMBER100'); $this->fail('Ineligible gift code accepted'); } catch(ValidationException $exception) { $this->assertArrayHasKey('promo_code',$exception->errors()); }
        $member->forceFill(['points'=>100])->save();
        $this->actingAs($member)->get(route('customer.gifts'))->assertOk()->assertSee('MEMBER100');
        $this->assertSame($gift->id,app(PromotionService::class)->findAvailable('MEMBER100')->id);
        $this->assertSame(100,(int)$member->fresh()->points);
        $this->actingAs($admin)->delete(route('admin.gifts.destroy',$gift))->assertRedirect(route('admin.gifts.index'));
        $this->assertModelMissing($gift);
    }
}
