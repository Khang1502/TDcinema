<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\User;
use App\Support\CinemaRegions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CinemaRegionsTest extends TestCase
{
    use RefreshDatabase;

    private function cinema(string $name, ?string $city, array $extra = []): Cinema
    {
        return Cinema::create(array_merge(['name' => $name, 'address' => 'Địa chỉ '.$name, 'city' => $city, 'status' => 'active'], $extra));
    }

    public function test_existing_city_aliases_are_grouped_without_changing_cinema_details(): void
    {
        $first = $this->cinema('Rạp Cao Lỗ', 'TP.HCM');
        $second = $this->cinema('Rạp Trung Tâm', 'TP. Hồ Chí Minh');
        $third = $this->cinema('Rạp Mỹ Khê', 'Đà Nẵng');
        $fourth = $this->cinema('Rạp Xuân Khánh', 'Cần Thơ');
        $fifth = $this->cinema('Rạp Trần Phú', 'TP. Nha Trang');
        $groups = CinemaRegions::groups(Cinema::all());

        $this->assertSame(['ho-chi-minh', 'ha-noi', 'da-nang', 'nha-trang', 'can-tho'], $groups->keys()->all());
        $this->assertSame(['TP. Hồ Chí Minh', 'Hà Nội', 'Đà Nẵng', 'Nha Trang', 'Cần Thơ'], $groups->pluck('label')->all());
        $this->assertSame(2, $groups['ho-chi-minh']['count']);
        $this->assertSame([$first->id, $second->id], $groups['ho-chi-minh']['cinemas']->pluck('id')->all());
        $this->assertSame('da-nang', $third->region_key);
        $this->assertSame('can-tho', $fourth->region_key);
        $this->assertSame('nha-trang', $fifth->region_key);
        $this->assertNull($first->fresh()->region);
        $this->assertSame('TP.HCM', $first->fresh()->city);
    }

    public function test_header_lists_active_cinemas_by_region_and_keeps_the_cinema_in_booking_links(): void
    {
        $cinema = $this->cinema('Rạp Đang Mở', 'Hà Nội');
        $this->cinema('Rạp Không Được Hiển Thị', 'Hà Nội', ['status' => 'maintenance']);

        $this->get(route('home'))->assertOk()
            ->assertSee('data-cinema-region="ha-noi"', false)
            ->assertSee('id="cinema-panel-ha-noi"', false)
            ->assertSee('data-cinema-region="da-nang"', false)
            ->assertSee('data-cinema-region="nha-trang"', false)
            ->assertSee('data-cinema-region="can-tho"', false)
            ->assertDontSee('data-cinema-region="central"', false)
            ->assertDontSee('data-cinema-region="mekong"', false)
            ->assertDontSee('data-cinema-region="other"', false)
            ->assertSee('Rạp Đang Mở')
            ->assertDontSee('Rạp Không Được Hiển Thị')
            ->assertSee(route('movies.index', ['status' => 'now_showing', 'cinema_id' => $cinema->id]));
    }

    public function test_danang_and_nhatrang_are_separate_regions_with_only_their_active_cinemas(): void
    {
        $danang = $this->cinema('Rạp Đà Nẵng', 'Đà Nẵng');
        $nhatrang = $this->cinema('Rạp Nha Trang', 'Nha Trang');
        $this->cinema('Rạp Hà Nội', 'Hà Nội');
        $this->cinema('Rạp Tạm Đóng', 'Đà Nẵng', ['status' => 'inactive']);

        $this->get(route('cinemas.index', ['region' => 'da-nang']))->assertOk()
            ->assertViewHas('selectedRegion', 'da-nang')
            ->assertViewHas('cinemas', fn ($items) => $items->pluck('id')->all() === [$danang->id])
            ->assertViewHas('cinemaGroups', fn ($groups) => $groups->keys()->all() === ['da-nang']);

        $this->get(route('cinemas.index', ['region' => 'nha-trang', 'city' => 'Nha Trang']))->assertOk()
            ->assertViewHas('cinemas', fn ($items) => $items->pluck('id')->all() === [$nhatrang->id])
            ->assertViewHas('cinemaGroups', fn ($groups) => $groups->keys()->all() === ['nha-trang']);
    }

    public function test_admin_can_set_a_region_override_and_return_to_automatic_grouping(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['name' => 'Rạp Quản Trị', 'address' => '100 Đường Test', 'city' => 'Đà Nẵng', 'region' => 'nha-trang', 'status' => 'active'];
        $this->post(route('admin.cinemas.store'), $data)->assertSessionHasNoErrors();
        $cinema = Cinema::where('name', $data['name'])->firstOrFail();
        $this->assertSame('nha-trang', $cinema->region_key);

        $this->get(route('admin.cinemas.edit', $cinema))->assertOk()->assertSee('Khu vực hiển thị');
        $this->put(route('admin.cinemas.update', $cinema), array_merge($data, ['city' => 'HCM', 'region' => '']))
            ->assertRedirect(route('admin.cinemas.index'))->assertSessionHasNoErrors();
        $this->assertNull($cinema->fresh()->region);
        $this->assertSame('ho-chi-minh', $cinema->fresh()->region_key);
    }

    public function test_invalid_region_is_rejected_and_non_admin_cannot_edit_cinema_regions(): void
    {
        $data = ['name' => 'Rạp Không Hợp Lệ', 'address' => 'Địa chỉ', 'city' => 'Hà Nội', 'region' => 'central'];
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.cinemas.store'), $data)->assertSessionHasErrors('region');
        $this->assertDatabaseMissing('cinemas', ['name' => $data['name']]);

        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->post(route('admin.cinemas.store'), array_merge($data, ['region' => 'ha-noi']))->assertForbidden();
    }

    public function test_admin_filters_region_city_status_and_search_together(): void
    {
        $target = $this->cinema('Alpha Đà Nẵng', 'Đà Nẵng');
        $this->cinema('Alpha Nha Trang', 'Nha Trang');
        $this->cinema('Alpha Đã Đóng', 'Đà Nẵng', ['status' => 'inactive']);
        $this->cinema('Beta Đà Nẵng', 'Đà Nẵng');
        $this->cinema('Alpha Hà Nội', 'Hà Nội');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.cinemas.index', ['region' => 'da-nang', 'city' => 'Đà Nẵng', 'status' => 'active', 'q' => 'Alpha']))
            ->assertOk()->assertViewHas('cinemas', fn ($items) => $items->pluck('id')->all() === [$target->id]);
    }

    public function test_unrecognized_cities_can_be_managed_without_adding_a_sixth_region(): void
    {
        $cinema = $this->cinema('Rạp Thành Phố Mới', 'Thành phố mới');
        $this->assertSame('other', $cinema->region_key);
        $this->assertSame('Chưa phân khu vực', $cinema->region_label);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.cinemas.index'))->assertOk()
            ->assertSee($cinema->name)
            ->assertViewHas('cinemaCount', 1)
            ->assertViewHas('regions', fn ($groups) => $groups->count() === 5 && !$groups->has('other'));

        $cinema->update(['region' => 'ha-noi']);
        $this->get(route('cinemas.index', ['region' => 'ha-noi']))->assertOk()
            ->assertViewHas('cinemas', fn ($items) => $items->pluck('id')->all() === [$cinema->id]);
        $this->get(route('admin.cinemas.edit', $cinema))->assertOk()
            ->assertViewHas('regionOptions', CinemaRegions::LABELS);
    }

    public function test_legacy_broad_regions_fall_back_to_the_cinemas_own_city(): void
    {
        $danang = $this->cinema('Rạp Mỹ Khê', 'Đà Nẵng', ['region' => 'central']);
        $nhatrang = $this->cinema('Rạp Trần Phú', 'Nha Trang', ['region' => 'central']);
        $cantho = $this->cinema('Rạp Xuân Khánh', 'Cần Thơ', ['region' => 'mekong']);

        $this->assertSame('Đà Nẵng', $danang->region_label);
        $this->assertSame('Nha Trang', $nhatrang->region_label);
        $this->assertSame('Cần Thơ', $cantho->region_label);
        $this->assertSame(1, CinemaRegions::groups(Cinema::all())['nha-trang']['count']);
    }

    public function test_admin_pagination_keeps_region_filters(): void
    {
        for ($i = 1; $i <= 12; $i++) $this->cinema('Rạp Đà Nẵng '.$i, 'Đà Nẵng');
        $this->cinema('Rạp Ngoài Khu Vực', 'Hà Nội');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.cinemas.index', ['region' => 'da-nang']))->assertOk()
            ->assertViewHas('cinemas', fn ($items) => $items->total() === 12 && str_contains($items->url(2), 'region=da-nang'));
    }
}
