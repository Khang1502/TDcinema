<?php

namespace Tests\Feature;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CinemaImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function data(array $extra = []): array
    {
        return array_merge(['name' => 'Rạp Có Ảnh', 'address' => '100 Đường Điện Ảnh', 'city' => 'Hà Nội', 'region' => 'ha-noi', 'status' => 'active'], $extra);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('cinema.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jHioAAAAASUVORK5CYII='));
    }

    public function test_admin_upload_is_saved_and_visible_on_the_public_cards_and_admin_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.cinemas.store'), $this->data(['image' => $this->image()]))->assertSessionHasNoErrors();
        $cinema = Cinema::firstOrFail();
        $this->assertStringStartsWith('cinemas/', $cinema->image_path);
        Storage::disk('public')->assertExists($cinema->image_path);
        $this->get(route('admin.cinemas.index'))->assertOk()->assertSee($cinema->imageUrl());
        $this->get(route('admin.cinemas.edit', $cinema))->assertOk()->assertSee($cinema->imageUrl())->assertSee('name="remove_image"', false);
        $this->get(route('cinemas.index', ['region' => 'ha-noi']))->assertOk()
            ->assertSee('data-cinema-photo="'.$cinema->imageUrl().'"', false)
            ->assertSee(route('movies.index', ['status' => 'now_showing', 'cinema_id' => $cinema->id]));
    }

    public function test_replacing_an_image_removes_the_old_file_and_updates_the_url(): void
    {
        Storage::disk('public')->put('cinemas/old.png', 'old');
        $cinema = Cinema::create($this->data(['image_path' => 'cinemas/old.png']));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.cinemas.update', $cinema), $this->data(['image' => $this->image()]))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.cinemas.index'));
        $cinema->refresh();
        Storage::disk('public')->assertMissing('cinemas/old.png');
        Storage::disk('public')->assertExists($cinema->image_path);
        $this->assertNotSame('cinemas/old.png', $cinema->image_path);
        $this->get(route('cinemas.index'))->assertSee($cinema->imageUrl());
    }

    public function test_text_edits_keep_the_image_and_explicit_removal_restores_the_placeholder(): void
    {
        Storage::disk('public')->put('cinemas/keep.png', 'image');
        $cinema = Cinema::create($this->data(['image_path' => 'cinemas/keep.png']));
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.cinemas.update', $cinema), $this->data(['name' => 'Rạp Đổi Tên', 'image_path' => 'untrusted.png']))
            ->assertSessionHasNoErrors();
        $this->assertSame('cinemas/keep.png', $cinema->fresh()->image_path);
        Storage::disk('public')->assertExists('cinemas/keep.png');
        $this->put(route('admin.cinemas.update', $cinema), $this->data(['remove_image' => 1]))->assertSessionHasNoErrors();
        $cinema->refresh();
        $this->assertNull($cinema->image_path);
        Storage::disk('public')->assertMissing('cinemas/keep.png');
        $this->get(route('cinemas.index'))->assertOk()->assertSee('Hình ảnh đang cập nhật')->assertSee($cinema->imageUrl())->assertDontSee('data-cinema-photo="', false);
    }

    public function test_invalid_uploads_do_not_change_the_existing_cinema_or_delete_its_image(): void
    {
        Storage::disk('public')->put('cinemas/original.png', 'image');
        $cinema = Cinema::create($this->data(['image_path' => 'cinemas/original.png']));
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.cinemas.update', $cinema), $this->data(['image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), 'remove_image' => 1]))->assertSessionHasErrors('image');
        $this->put(route('admin.cinemas.update', $cinema), $this->data(['image' => UploadedFile::fake()->create('large.png', 4097, 'image/png')]))->assertSessionHasErrors('image');
        $this->assertSame('cinemas/original.png', $cinema->fresh()->image_path);
        Storage::disk('public')->assertExists('cinemas/original.png');
        $this->assertCount(1, Storage::disk('public')->allFiles('cinemas'));
    }

    public function test_images_are_optional_and_missing_files_have_a_working_placeholder(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.cinemas.store'), $this->data())->assertSessionHasNoErrors();
        $cinema = Cinema::firstOrFail();
        $this->assertNull($cinema->image_path);
        $this->assertFalse($cinema->hasImage());
        $cinema->update(['image_path' => 'cinemas/missing.png']);
        $this->assertSame(asset('assets/images/ui/cinema-placeholder.svg'), $cinema->imageUrl());
        $this->get(route('admin.cinemas.index'))->assertOk()->assertSee('Chưa có ảnh');
        $this->get(route('cinemas.index'))->assertOk()->assertSee('Hình ảnh đang cập nhật');
    }

    public function test_deleting_a_cinema_only_removes_its_owned_unshared_image(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Storage::disk('public')->put('cinemas/shared.png', 'image');
        $first = Cinema::create($this->data(['image_path' => 'cinemas/shared.png']));
        $second = Cinema::create($this->data(['name' => 'Rạp Thứ Hai', 'image_path' => 'cinemas/shared.png']));
        $this->delete(route('admin.cinemas.destroy', $first))->assertRedirect(route('admin.cinemas.index'));
        Storage::disk('public')->assertExists('cinemas/shared.png');
        $this->delete(route('admin.cinemas.destroy', $second))->assertRedirect(route('admin.cinemas.index'));
        Storage::disk('public')->assertMissing('cinemas/shared.png');
        Storage::disk('public')->put('movies/shared.png', 'movie');
        $third = Cinema::create($this->data(['name' => 'Rạp Dùng Ảnh Khác', 'image_path' => 'movies/shared.png']));
        $this->delete(route('admin.cinemas.destroy', $third))->assertRedirect(route('admin.cinemas.index'));
        Storage::disk('public')->assertExists('movies/shared.png');
    }

    public function test_cinema_with_showtimes_keeps_its_image_when_it_is_disabled(): void
    {
        Storage::disk('public')->put('cinemas/booked.png', 'image');
        $cinema = Cinema::create($this->data(['image_path' => 'cinemas/booked.png']));
        $room = Room::create(['cinema_id' => $cinema->id, 'name' => 'Phòng 1', 'type' => '2D', 'status' => 'active']);
        $movie = Movie::create(['title' => 'Phim thử nghiệm', 'duration' => 90]);
        Showtime::create(['room_id' => $room->id, 'movie_id' => $movie->id, 'start_time' => now()->addDay(), 'end_time' => now()->addDay()->addMinutes(90), 'base_price' => 80000, 'status' => 'active']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('admin.cinemas.destroy', $cinema))->assertSessionHas('warning');
        $this->assertSame('inactive', $cinema->fresh()->status);
        $this->assertSame('cinemas/booked.png', $cinema->fresh()->image_path);
        Storage::disk('public')->assertExists('cinemas/booked.png');
    }

    public function test_upload_management_requires_cinema_permissions(): void
    {
        foreach (['customer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'admin_permissions' => ['dashboard.view']]))
                ->post(route('admin.cinemas.store'), $this->data(['image' => $this->image()]))->assertForbidden();
        }
        $this->assertDatabaseCount('cinemas', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles('cinemas'));
    }
}
