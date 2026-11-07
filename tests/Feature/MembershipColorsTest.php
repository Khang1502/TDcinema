<?php

namespace Tests\Feature;

use App\Models\{MembershipLevel, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipColorsTest extends TestCase
{
    use RefreshDatabase;

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        return $document;
    }

    public function test_all_additional_colors_can_be_saved_and_reach_the_customer_catalog(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $next = (int)MembershipLevel::max('min_points') + 1000;
        foreach (['ruby','emerald','orange','rose','cyan','indigo','bronze','navy','coral','graphite'] as $index=>$color) {
            $this->post(route('admin.membership.levels.store'),['name'=>'Hạng '.$color,'min_points'=>$next+$index*1000,
                'discount_percent'=>10,'color'=>$color,'is_active'=>1])->assertSessionHasNoErrors()->assertRedirect(route('admin.membership.index'));
            $this->assertDatabaseHas('membership_levels',['name'=>'Hạng '.$color,'color'=>$color]);
        }
        $this->post(route('admin.membership.levels.store'),['name'=>'Màu không hợp lệ','min_points'=>$next+20000,
            'discount_percent'=>10,'color'=>'unknown','is_active'=>1])->assertSessionHasErrors('color');
        $response = $this->get(route('membership.index'))->assertOk()->assertSee('assets/css/membership-colors.css',false);
        foreach (['ruby','emerald','orange','rose','cyan','indigo','bronze','navy','coral','graphite'] as $color) {
            $response->assertSee('membership-level '.$color,false);
        }
    }

    public function test_color_notes_name_all_existing_owners_including_disabled_and_current_levels(): void
    {
        $base = MembershipLevel::where('min_points',0)->firstOrFail();
        $base->update(['name'=>'Hạng Ruby','color'=>'ruby']);
        MembershipLevel::create(['name'=>'Hạng đã tắt','min_points'=>70000,'color'=>'ruby','discount_percent'=>5,'is_active'=>false]);
        $this->actingAs(User::factory()->create(['role'=>'admin']));
        $response = $this->get(route('admin.membership.levels.create'))->assertOk();
        $document = $this->document($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(16,$xpath->query('//input[@name="color"]')->length);
        $note = $document->getElementById('level-color-note-ruby')->textContent;
        $this->assertStringContainsString('Hạng Ruby',$note);
        $this->assertStringContainsString('Hạng đã tắt (đang tắt)',$note);
        $this->assertSame('Chưa sử dụng',$document->getElementById('level-color-note-graphite')->textContent);
        $this->assertFalse($xpath->query('//input[@name="color" and @value="ruby"]')->item(0)->hasAttribute('disabled'));
        $response = $this->get(route('admin.membership.levels.edit',$base))->assertOk();
        $note = $this->document($response->getContent())->getElementById('level-color-note-ruby')->textContent;
        $this->assertStringContainsString('Hạng Ruby (hạng này)',$note);
        // A color may be reused; the notes describe usage without rejecting the selection.
        $this->post(route('admin.membership.levels.store'),['name'=>'Hạng dùng chung','min_points'=>80000,
            'discount_percent'=>5,'color'=>'ruby','is_active'=>1])->assertSessionHasNoErrors();
    }

    public function test_failed_save_keeps_the_new_color_selected_without_changing_the_saved_tier(): void
    {
        $base = MembershipLevel::where('min_points',0)->firstOrFail();
        $originalColor = $base->color;
        $this->actingAs(User::factory()->create(['role'=>'admin']))->from(route('admin.membership.levels.edit',$base))
            ->put(route('admin.membership.levels.update',$base),['name'=>$base->name,'min_points'=>0,
                'discount_percent'=>101,'color'=>'coral','is_active'=>1])->assertSessionHasErrors('discount_percent');
        $response = $this->get(route('admin.membership.levels.edit',$base))->assertOk();
        $xpath = new \DOMXPath($this->document($response->getContent()));
        $this->assertSame('coral',$xpath->query('//input[@name="color" and @checked]')->item(0)->getAttribute('value'));
        $this->assertSame($originalColor,$base->fresh()->color);
    }
}
