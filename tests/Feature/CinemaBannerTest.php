<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Cinema;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CinemaBannerTest extends TestCase
{
    use RefreshDatabase;

    private function banner(array $data = []): Banner
    {
        return Banner::create(array_merge(['title' => 'Banner rạp', 'image_path' => 'banners/test.jpg', 'placement' => 'cinemas', 'sort_order' => 1, 'is_active' => true], $data));
    }

    private function cinema(array $data = []): Cinema
    {
        return Cinema::create(array_merge(['name' => 'Rạp Đà Nẵng', 'address' => 'Địa chỉ', 'city' => 'Đà Nẵng', 'status' => 'active'], $data));
    }

    public function test_cinema_banner_list_is_separate_from_home_and_preserves_order_and_cinema_filters(): void
    {
        $home = $this->banner(['title' => 'Banner phim trang chủ', 'placement' => 'home']);
        $cinema = $this->cinema();
        $later = $this->banner(['title' => 'Banner rạp sau', 'sort_order' => 10]);
        $first = $this->banner(['title' => 'Banner rạp đầu', 'sort_order' => 0, 'cinema_id' => $cinema->id]);
        $this->banner(['title' => 'Banner đang ẩn', 'is_active' => false]);
        $this->cinema(['name' => 'Rạp Hà Nội', 'city' => 'Hà Nội']);

        $this->get(route('home'))->assertOk()
            ->assertViewHas('banners', fn ($items) => $items->pluck('id')->all() === [$home->id])->assertDontSee('Banner rạp đầu');
        $this->get(route('cinemas.index', ['region' => 'da-nang', 'city' => 'Đà Nẵng']))->assertOk()
            ->assertViewHas('banners', fn ($items) => $items->pluck('id')->all() === [$first->id, $later->id])
            ->assertViewHas('cinemas', fn ($items) => $items->pluck('id')->all() === [$cinema->id])
            ->assertSee('data-banner-slider', false)->assertSee('data-slider-next', false)
            ->assertSee('banner-slider.css')->assertSee('banner-slider.js')->assertSee($first->destinationUrl())
            ->assertDontSee('Banner phim trang chủ')->assertDontSee('Banner đang ẩn')->assertDontSee('Rạp gần bạn.');
    }

    public function test_one_or_zero_banners_keep_the_cinema_listing_available(): void
    {
        $banner = $this->banner();
        $this->get(route('cinemas.index'))->assertOk()->assertSee($banner->imageUrl())
            ->assertSee('data-banner-slider', false)->assertDontSee('data-slider-next', false)->assertDontSee('Rạp gần bạn.');
        $banner->update(['is_active' => false]);
        $this->get(route('cinemas.index'))->assertOk()->assertDontSee('data-banner-slider', false)
            ->assertSee('Hệ thống rạp')->assertSee('Giá theo định dạng phòng');
    }

    public function test_admin_can_filter_cinema_banners_and_hide_them_without_affecting_home(): void
    {
        $home = $this->banner(['placement' => 'home']);
        $banner = $this->banner();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.banners.index', ['placement' => 'cinemas']))->assertOk()
            ->assertViewHas('banners', fn ($items) => $items->pluck('id')->all() === [$banner->id]);
        $this->patch(route('admin.banners.order'), ['placement' => 'cinemas', 'orders' => [$banner->id => 9]])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index', ['placement' => 'cinemas']));
        $this->patch(route('admin.banners.visibility', $banner), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('cinemas.index'))->assertOk()->assertDontSee('data-banner-slider', false);
        $this->get(route('home'))->assertOk()->assertViewHas('banners', fn ($items) => $items->pluck('id')->all() === [$home->id]);
        $this->patch(route('admin.banners.visibility', $banner), ['is_active' => 1])->assertSessionHasNoErrors();
        $this->get(route('cinemas.index'))->assertOk()->assertSee('data-banner-slider', false)->assertSee($banner->imageUrl());
    }

    public function test_admin_can_reuse_a_cinema_image_without_uploading_or_copying_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cinemas/shared.png', 'image');
        $cinema = $this->cinema(['image_path' => 'cinemas/shared.png']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.banners.store'), ['title' => 'Ảnh rạp', 'placement' => 'cinemas', 'image_source' => 'cinema', 'cinema_id' => $cinema->id, 'sort_order' => 1, 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index', ['placement' => 'cinemas']));
        $banner = Banner::firstOrFail();
        $this->assertSame($cinema->image_path, $banner->image_path);
        $this->assertSame(route('cinemas.index', ['region' => 'da-nang']).'#cinema-'.$cinema->id, $banner->destinationUrl());
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->get(route('admin.banners.edit', $banner))->assertOk()->assertSee('Dùng ảnh của rạp');
        $this->delete(route('admin.banners.destroy', $banner))->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists('cinemas/shared.png');
    }

    public function test_shared_image_survives_cinema_removal_until_the_last_banner_is_deleted(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('cinemas/shared.png', 'image');
        $cinema = $this->cinema(['image_path' => 'cinemas/shared.png']);
        $banner = $this->banner(['image_path' => $cinema->image_path, 'cinema_id' => $cinema->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.cinemas.update', $cinema), ['name' => $cinema->name, 'address' => $cinema->address, 'city' => $cinema->city, 'remove_image' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists('cinemas/shared.png');
        $this->get(route('cinemas.index'))->assertSee($banner->imageUrl());
        $this->delete(route('admin.cinemas.destroy', $cinema))->assertSessionHasNoErrors();
        $this->assertNull($banner->fresh()->cinema_id);
        $this->assertNull($banner->fresh()->destinationUrl());
        Storage::disk('public')->assertExists('cinemas/shared.png');
        $this->delete(route('admin.banners.destroy', $banner))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing('cinemas/shared.png');
    }

    public function test_inactive_cinemas_do_not_advertise_their_banners_and_invalid_sources_are_rejected(): void
    {
        Storage::fake('public');
        $cinema = $this->cinema(['status' => 'inactive']);
        $this->banner(['cinema_id' => $cinema->id]);
        $this->get(route('cinemas.index'))->assertOk()->assertViewHas('banners', fn ($items) => $items->isEmpty());
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = ['title' => 'Ảnh rạp', 'placement' => 'cinemas', 'image_source' => 'cinema', 'cinema_id' => $cinema->id, 'sort_order' => 1, 'is_active' => 1];
        $this->post(route('admin.banners.store'), $data)->assertSessionHasErrors('cinema_id');
        $this->post(route('admin.banners.store'), array_merge($data, ['placement' => 'unknown']))->assertSessionHasErrors('placement');
        $this->assertDatabaseCount('banners', 1);
    }
}
