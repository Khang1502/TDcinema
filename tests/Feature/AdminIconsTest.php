<?php

namespace Tests\Feature;

use App\Models\{HeaderSetting, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIconsTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_icons_describe_their_destination_and_use_available_local_symbols(): void
    {
        $response = $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.dashboard'))->assertOk();
        $dom = new \DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['admin.header.edit'=>'panel-top','admin.footer.edit'=>'panel-bottom','admin.rooms.index'=>'projector','admin.seats.index'=>'armchair','admin.promotions.index'=>'tags','admin.membership.index'=>'crown','admin.permissions.index'=>'shield-check','admin.reports.index'=>'chart-no-axes-combined'] as $route=>$name) {
            $url = route($route);
            $nodes = $xpath->query('//nav//a[@href="'.$url.'"]//svg[@data-admin-icon="'.$name.'"]');
            $this->assertSame(1,$nodes->length,$route.' has the correct icon');
        }
        $sprite = simplexml_load_file(public_path('assets/icons/admin/sprite.svg'));
        $names = [];
        foreach ($sprite->symbol as $symbol) $names[] = (string)$symbol['id'];
        foreach ($xpath->query('//svg[@data-admin-icon]') as $svg) {
            $this->assertContains($svg->getAttribute('data-admin-icon'),$names);
            $this->assertNotSame('circle-help',$svg->getAttribute('data-admin-icon'));
            $this->assertSame('true',$svg->getAttribute('aria-hidden'));
        }
        $this->assertFileExists(public_path('assets/icons/admin/LICENSE'));
    }

    public function test_admin_brand_uses_the_configured_logo_without_a_white_tile(): void
    {
        $settings = HeaderSetting::current();
        $settings->update(['brand_name'=>'TD Test','logo_path'=>'logos/custom.png']);
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get(route('admin.dashboard'))->assertOk()
            ->assertSee('class="sidebar-brand__logo"',false)->assertSee('Logo TD Test')->assertSee('logos/custom.png');
    }
}
