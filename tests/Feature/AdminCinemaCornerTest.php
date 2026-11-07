<?php

namespace Tests\Feature;

use App\Models\CinemaCornerItem;
use App\Models\CinemaCornerSetting;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCinemaCornerTest extends TestCase
{
    use RefreshDatabase;

    private function movie(string $title = 'Phim kiểm thử', array $extra = []): Movie
    {
        return Movie::create([...['title' => $title, 'duration' => 90, 'status' => 'now_showing', 'description' => 'Mô tả gốc của phim', 'poster_path' => 'movies/poster.jpg', 'banner_path' => 'movies/banner.jpg', 'trailer_url' => 'https://youtube.com/watch?v=abcdefghijk'], ...$extra]);
    }

    private function item(Movie $movie, array $extra = []): CinemaCornerItem
    {
        return CinemaCornerItem::create(['movie_id' => $movie->id, 'sort_order' => 1, 'is_active' => true, ...$extra]);
    }

    private function data(Movie $movie, array $extra = []): array
    {
        return ['movie_id' => $movie->id, 'image_source' => 'movie', 'placement' => 'list', 'sort_order' => 1, 'is_active' => 1, ...$extra];
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('corner.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1WQAAAAASUVORK5CYII='));
    }

    public function test_management_and_mutations_require_the_new_permission(): void
    {
        $movie = $this->movie();
        $item = $this->item($movie);
        foreach (['admin', 'manager'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.cinema-corner.index'))->assertOk()->assertSee('Góc Điện Ảnh');
            $this->get(route('admin.cinema-corner.create'))->assertOk();
        }
        foreach (['customer', 'staff'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('admin.cinema-corner.index'))->assertForbidden();
            $this->post(route('admin.cinema-corner.store'), $this->data($movie))->assertForbidden();
            $this->put(route('admin.cinema-corner.update', $item), $this->data($movie))->assertForbidden();
            $this->delete(route('admin.cinema-corner.destroy', $item))->assertForbidden();
            $this->patch(route('admin.cinema-corner.visibility', $item), ['is_active' => 0])->assertForbidden();
            $this->patch(route('admin.cinema-corner.order'), ['orders' => [$item->id => 2]])->assertForbidden();
            $this->put(route('admin.cinema-corner.settings'), CinemaCornerSetting::DEFAULTS)->assertForbidden();
        }
        $staff = User::factory()->create(['role' => 'staff', 'admin_permissions' => ['dashboard.view', 'cinema-corner.manage']]);
        $this->actingAs($staff)->get(route('admin.cinema-corner.index'))->assertOk();
        $manager = User::factory()->create(['role' => 'manager', 'admin_permissions' => ['dashboard.view']]);
        $this->actingAs($manager)->get(route('admin.cinema-corner.index'))->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_admin_changes_are_shown_on_homepage_without_changing_movie_details(): void
    {
        $movie = $this->movie();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.cinema-corner.store'), $this->data($movie, ['title' => 'Tiêu đề riêng', 'excerpt' => 'Mô tả riêng', 'trailer_url' => 'https://youtu.be/ZYXabcdefgh', 'placement' => 'featured']))
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.cinema-corner.index'));
        $item = CinemaCornerItem::firstOrFail();
        $this->assertSame($item->id, CinemaCornerSetting::current()->featured_item_id);
        $this->get(route('home'))->assertOk()->assertViewHas('cornerFeatured', fn ($featured) => $featured->is($item))
            ->assertSee('Tiêu đề riêng')->assertSee('Mô tả riêng')->assertSee('data-trailer="https://youtu.be/ZYXabcdefgh"', false)
            ->assertSee(route('movies.show', $movie));
        $this->assertSame('Phim kiểm thử', $movie->fresh()->title);
        $this->assertSame('Mô tả gốc của phim', $movie->fresh()->description);
    }

    public function test_edit_form_shows_inherited_text_and_unchanged_save_keeps_it_linked_to_the_movie(): void
    {
        $movie = $this->movie();
        $item = $this->item($movie);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->get(route('admin.cinema-corner.edit', $item))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), '>Mô tả gốc của phim</textarea>'));

        $this->put(route('admin.cinema-corner.update', $item), $this->data($movie, [
            'excerpt' => $item->editorExcerpt(), 'content' => $item->editorContent(),
        ]))->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->excerpt);
        $this->assertNull($item->fresh()->content);

        $movie->update(['description' => 'Mô tả đã cập nhật của phim']);
        $this->assertSame('Mô tả đã cập nhật của phim', $item->fresh()->articleContent());
    }

    public function test_custom_text_can_be_edited_and_invalid_submissions_preserve_the_entered_text(): void
    {
        $movie = $this->movie();
        $item = $this->item($movie);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->put(route('admin.cinema-corner.update', $item), $this->data($movie, [
            'excerpt' => 'Mô tả do admin viết', 'content' => "Nội dung riêng.\n\nĐoạn tiếp theo.",
        ]))->assertSessionHasNoErrors();
        $this->get(route('admin.cinema-corner.edit', $item))->assertOk()->assertSee('>Mô tả do admin viết</textarea>', false)->assertSee('Đoạn tiếp theo.');
        $this->assertSame('Mô tả gốc của phim', $movie->fresh()->description);

        $this->from(route('admin.cinema-corner.edit', $item))->put(route('admin.cinema-corner.update', $item), $this->data($movie, [
            'excerpt' => '', 'content' => 'Nội dung chưa lưu', 'sort_order' => -1,
        ]))->assertSessionHasErrors('sort_order');
        $this->get(route('admin.cinema-corner.edit', $item))->assertOk()->assertSee('>Nội dung chưa lưu</textarea>', false)->assertSee('placeholder="Nhập mô tả ngắn hoặc chọn phim để điền nội dung có sẵn"></textarea>', false);
    }

    public function test_featured_selection_order_and_list_limit_are_respected(): void
    {
        $first = $this->item($this->movie('Premier'), ['sort_order' => 30]);
        $second = $this->item($this->movie('Second'), ['sort_order' => 20]);
        $third = $this->item($this->movie('Third'), ['sort_order' => 10]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.cinema-corner.update', $first), $this->data($first->movie, ['placement' => 'featured', 'sort_order' => 30]))->assertSessionHasNoErrors();
        $this->put(route('admin.cinema-corner.update', $second), $this->data($second->movie, ['placement' => 'featured', 'sort_order' => 20]))->assertSessionHasNoErrors();
        $this->assertSame($second->id, CinemaCornerSetting::current()->featured_item_id);
        $this->put(route('admin.cinema-corner.settings'), [...CinemaCornerSetting::DEFAULTS, 'list_limit' => 1])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertViewHas('cornerArticles', fn ($items) => $items->pluck('id')->all() === [$third->id]);
        $this->patch(route('admin.cinema-corner.order'), ['orders' => [$first->id => 0, $third->id => 10]])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertViewHas('cornerArticles', fn ($items) => $items->pluck('id')->all() === [$first->id]);
        $this->patch(route('admin.cinema-corner.order'), ['orders' => [$first->id => 90, 99999 => 1]])->assertSessionHasErrors('orders');
        $this->assertSame(0, $first->fresh()->sort_order);
    }

    public function test_hiding_every_item_or_the_section_does_not_restore_automatic_films(): void
    {
        $item = $this->item($this->movie());
        $this->movie('Phim không được tự động chọn');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->patch(route('admin.cinema-corner.visibility', $item), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('id="cinema-corner"', false);
        $this->patch(route('admin.cinema-corner.visibility', $item), ['is_active' => 1])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSee('id="cinema-corner"', false);
        $this->put(route('admin.cinema-corner.settings'), [...CinemaCornerSetting::DEFAULTS, 'is_active' => 0])->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertDontSee('id="cinema-corner"', false);
        $this->delete(route('admin.cinema-corner.destroy', $item))->assertRedirect(route('admin.cinema-corner.index'));
        $this->assertModelMissing($item);
        $this->assertDatabaseCount('movies', 2);
    }

    public function test_section_text_and_links_are_editable_and_unsafe_links_are_rejected(): void
    {
        $this->item($this->movie());
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.cinema-corner.settings'), [...CinemaCornerSetting::DEFAULTS, 'title' => 'Câu chuyện điện ảnh', 'eyebrow' => 'Khám phá mới', 'description' => 'Thông tin đã chỉnh', 'featured_label' => 'Lựa chọn tuần này', 'link_label' => 'Xem phim ngay', 'link_url' => '/movies'])
            ->assertSessionHasNoErrors();
        $this->get(route('home'))->assertOk()->assertSee('Câu chuyện điện ảnh')->assertSee('Lựa chọn tuần này')->assertSee('Thông tin đã chỉnh')->assertSee('Xem phim ngay');
        $this->put(route('admin.cinema-corner.settings'), [...CinemaCornerSetting::DEFAULTS, 'link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('link_url');
        $this->put(route('admin.cinema-corner.settings'), [...CinemaCornerSetting::DEFAULTS, 'list_limit' => 9])->assertSessionHasErrors('list_limit');
        $this->assertSame('Câu chuyện điện ảnh', CinemaCornerSetting::current()->title);
    }

    public function test_uploaded_images_can_be_replaced_and_reset_without_deleting_movie_images(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('movies/poster.jpg', 'poster');
        Storage::disk('public')->put('movies/banner.jpg', 'banner');
        $movie = $this->movie();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('admin.cinema-corner.store'), $this->data($movie, ['image_source' => 'upload', 'image' => $this->image()]))->assertSessionHasNoErrors();
        $item = CinemaCornerItem::firstOrFail();
        $oldPath = $item->image_path;
        Storage::disk('public')->assertExists($oldPath);
        $this->put(route('admin.cinema-corner.update', $item), $this->data($movie, ['image_source' => 'upload']))->assertSessionHasNoErrors();
        $this->assertSame($oldPath, $item->fresh()->image_path);
        $this->put(route('admin.cinema-corner.update', $item), $this->data($movie, ['image_source' => 'upload', 'image' => $this->image()]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($oldPath);
        $newPath = $item->fresh()->image_path;
        Storage::disk('public')->assertExists($newPath);
        $this->put(route('admin.cinema-corner.update', $item), $this->data($movie))->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->image_path);
        Storage::disk('public')->assertMissing($newPath);
        $this->delete(route('admin.cinema-corner.destroy', $item))->assertRedirect(route('admin.cinema-corner.index'));
        Storage::disk('public')->assertExists('movies/poster.jpg');
        Storage::disk('public')->assertExists('movies/banner.jpg');
    }

    public function test_invalid_movie_image_or_trailer_cannot_be_saved(): void
    {
        $movie = $this->movie();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post(route('admin.cinema-corner.store'), $this->data($movie, ['movie_id' => 99999]))->assertSessionHasErrors('movie_id');
        $this->post(route('admin.cinema-corner.store'), $this->data($movie, ['image_source' => 'upload']))->assertSessionHasErrors('image');
        $this->post(route('admin.cinema-corner.store'), $this->data($movie, ['image_source' => 'upload', 'image' => UploadedFile::fake()->create('invalid.pdf', 10, 'application/pdf')]))->assertSessionHasErrors('image');
        $this->post(route('admin.cinema-corner.store'), $this->data($movie, ['trailer_url' => 'javascript:alert(1)']))->assertSessionHasErrors('trailer_url');
        $this->assertDatabaseCount('cinema_corner_items', 0);
    }

    public function test_deleted_or_inactive_movies_are_hidden_and_other_content_stays_available(): void
    {
        $deleted = $this->item($this->movie('Phim bị xóa'));
        $this->item($this->movie('Phim ngừng chiếu', ['status' => 'inactive']));
        $remaining = $this->item($this->movie('Phim còn hoạt động'));
        CinemaCornerSetting::current()->update(['featured_item_id' => $deleted->id]);
        $deleted->movie->delete();
        $this->assertNull($deleted->fresh()->movie_id);
        $this->get(route('home'))->assertOk()->assertViewHas('cornerFeatured', fn ($item) => $item->is($remaining))->assertViewHas('cornerArticles', fn ($items) => $items->isEmpty());
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.cinema-corner.index'))->assertOk()->assertSee('Phim đã xóa');
    }
}
