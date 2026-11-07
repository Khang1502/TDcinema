<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminBannerTest extends TestCase
{
    use RefreshDatabase;

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('banner.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1WQAAAAASUVORK5CYII='));
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['title' => 'Banner mới', 'image_source' => 'upload', 'sort_order' => 1, 'is_active' => 1], $overrides);
    }

    private function banner(array $overrides = []): Banner
    {
        return Banner::create(array_replace(['title' => 'Banner kiểm thử', 'image_path' => 'movies/banner.jpg', 'sort_order' => 1, 'is_active' => true], $overrides));
    }

    public function test_banner_management_is_protected_by_admin_permissions(): void
    {
        foreach (['admin', 'manager'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.banners.index'))->assertOk()->assertSee('Quản lý banner');
            $this->get(route('admin.banners.create'))->assertOk();
        }
        $banner = $this->banner();
        foreach (['staff', 'customer'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.banners.index'))->assertForbidden();
            $this->post(route('admin.banners.store'), $this->data())->assertForbidden();
            $this->delete(route('admin.banners.destroy', $banner))->assertForbidden();
            $this->patch(route('admin.banners.order'), ['orders' => [$banner->id => 2]])->assertForbidden();
            $this->patch(route('admin.banners.visibility', $banner), ['is_active' => 0])->assertForbidden();
        }
        $this->assertModelExists($banner);
        $staff = User::factory()->create(['role' => 'staff', 'admin_permissions' => ['dashboard.view', 'banners.manage']]);
        $this->actingAs($staff)->get(route('admin.banners.index'))->assertOk();
        $manager = User::factory()->create(['role' => 'manager', 'admin_permissions' => ['dashboard.view']]);
        $this->actingAs($manager)->get(route('admin.banners.index'))->assertForbidden();
    }

    public function test_uploaded_banner_is_saved_and_linked_on_the_homepage(): void
    {
        Storage::fake('public');
        $movie = Movie::create(['title' => 'Phim liên kết', 'duration' => 90, 'status' => 'now_showing']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.banners.store'), $this->data(['image' => $this->image(), 'movie_id' => $movie->id]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index'));
        $banner = Banner::firstOrFail();
        Storage::disk('public')->assertExists($banner->image_path);
        $this->assertStringStartsWith('banners/', $banner->image_path);
        $this->assertSame(route('movies.show', $movie), $banner->destinationUrl());
        $this->get(route('home'))->assertOk()->assertSee('Xem Banner mới')->assertSee($banner->imageUrl());
    }

    public function test_movie_banner_can_be_used_without_uploading_another_file(): void
    {
        $movie = Movie::create(['title' => 'Phim dùng ảnh', 'duration' => 90, 'status' => 'now_showing', 'banner_path' => 'movies/shared.jpg']);
        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->post(route('admin.banners.store'), $this->data(['image_source' => 'movie', 'movie_id' => $movie->id, 'link_url' => '/rap-gia-ve']))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index'));
        $banner = Banner::firstOrFail();
        $this->assertSame('movies/shared.jpg', $banner->image_path);
        $this->assertSame('/rap-gia-ve', $banner->destinationUrl());
    }

    public function test_replacing_and_deleting_uploaded_images_cleans_up_only_owned_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/old.png', 'old');
        $banner = $this->banner(['image_path' => 'banners/old.png']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.banners.update', $banner), $this->data(['title' => 'Đã chỉnh sửa', 'image' => $this->image(), 'is_active' => 0]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index'));
        $banner->refresh();
        Storage::disk('public')->assertMissing('banners/old.png');
        Storage::disk('public')->assertExists($banner->image_path);
        $this->assertFalse($banner->is_active);
        $this->delete(route('admin.banners.destroy', $banner))->assertRedirect(route('admin.banners.index'));
        Storage::disk('public')->assertMissing($banner->image_path);
        $this->assertModelMissing($banner);
    }

    public function test_deleting_a_banner_does_not_delete_the_shared_movie_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/shared.jpg', 'movie image');
        $movie = Movie::create(['title' => 'Phim giữ ảnh', 'duration' => 90, 'banner_path' => 'movies/shared.jpg']);
        $banner = $this->banner(['image_path' => $movie->banner_path, 'movie_id' => $movie->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.banners.destroy', $banner))->assertRedirect(route('admin.banners.index'));
        Storage::disk('public')->assertExists('movies/shared.jpg');
        $this->assertModelExists($movie);
    }

    public function test_movie_image_replacement_preserves_the_image_used_by_a_homepage_banner(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/shared.jpg', 'original banner');
        $movie = Movie::create(['title' => 'Phim cập nhật ảnh', 'duration' => 90, 'status' => 'now_showing', 'banner_path' => 'movies/shared.jpg']);
        $banner = $this->banner(['image_path' => $movie->banner_path, 'movie_id' => $movie->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.movies.update', $movie), ['title' => $movie->title, 'duration' => 90, 'status' => 'now_showing', 'banner' => $this->image()])
            ->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists('movies/shared.jpg');
        $this->assertSame('movies/shared.jpg', $banner->fresh()->image_path);
        $this->assertNotSame($movie->fresh()->banner_path, $banner->image_path);
    }

    public function test_movie_deletion_keeps_homepage_artwork_and_removes_its_movie_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/shared.jpg', 'original banner');
        $movie = Movie::create(['title' => 'Phim được xóa', 'duration' => 90, 'status' => 'now_showing', 'banner_path' => 'movies/shared.jpg']);
        $banner = $this->banner(['image_path' => $movie->banner_path, 'movie_id' => $movie->id]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.movies.destroy', $movie))->assertRedirect(route('admin.movies.index'));
        Storage::disk('public')->assertExists('movies/shared.jpg');
        $this->assertNull($banner->fresh()->movie_id);
        $this->assertNull($banner->fresh()->destinationUrl());
    }

    public function test_updates_without_a_new_upload_keep_the_existing_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('banners/existing.png', 'existing');
        $banner = $this->banner(['image_path' => 'banners/existing.png']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.banners.update', $banner), $this->data(['title' => 'Giữ ảnh']))
            ->assertSessionHasNoErrors();
        $this->assertSame('banners/existing.png', $banner->fresh()->image_path);
        Storage::disk('public')->assertExists('banners/existing.png');
    }

    public function test_invalid_image_source_or_unsafe_link_cannot_be_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.banners.store'), $this->data())->assertSessionHasErrors('image');
        $this->post(route('admin.banners.store'), $this->data(['image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('image');
        $this->post(route('admin.banners.store'), $this->data(['image' => $this->image(), 'link_url' => 'javascript:alert(1)']))->assertSessionHasErrors('link_url');
        $movie = Movie::create(['title' => 'Phim chưa có banner', 'duration' => 90]);
        $this->post(route('admin.banners.store'), $this->data(['image_source' => 'movie', 'movie_id' => $movie->id]))->assertSessionHasErrors('movie_id');
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_order_is_saved_and_homepage_only_displays_active_banners(): void
    {
        $first = $this->banner(['title' => 'Banner một', 'sort_order' => 1]);
        $second = $this->banner(['title' => 'Banner hai', 'sort_order' => 2]);
        $this->banner(['title' => 'Banner ẩn', 'sort_order' => 0, 'is_active' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.banners.order'), ['orders' => [$first->id => 10, $second->id => 1]])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.banners.index'));
        $this->get(route('home'))->assertOk()->assertViewHas('banners', fn ($banners) => $banners->pluck('id')->all() === [$second->id, $first->id]);
        $this->patch(route('admin.banners.order'), ['orders' => [$first->id => 0, 99999 => 2]])->assertSessionHasErrors('orders');
        $this->assertSame(10, $first->fresh()->sort_order);
    }

    public function test_hiding_all_banners_does_not_restore_automatic_movie_banners(): void
    {
        $banner = $this->banner();
        Movie::create(['title' => 'Phim tự động cũ', 'duration' => 90, 'status' => 'now_showing', 'banner_path' => 'movies/banner.jpg']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->patch(route('admin.banners.visibility', $banner), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertViewHas('banners', fn ($banners) => $banners->isEmpty())->assertDontSee('class="hero"', false);
        $this->patch(route('admin.banners.visibility', $banner), ['is_active' => 1])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSee('class="hero"', false);
        $this->delete(route('admin.banners.destroy', $banner))->assertRedirect(route('admin.banners.index'));
        $this->get(route('home'))->assertOk()->assertDontSee('class="hero"', false);
    }
}
