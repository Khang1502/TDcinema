<?php

namespace Tests\Feature;

use App\Models\CinemaCornerItem;
use App\Models\CinemaCornerLike;
use App\Models\CinemaCornerSetting;
use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CinemaCornerArticlesTest extends TestCase
{
    use RefreshDatabase;

    private function article(string $category = 'blog', array $extra = []): CinemaCornerItem
    {
        return CinemaCornerItem::create(['category' => $category, 'title' => 'Câu chuyện điện ảnh', 'content' => "Đoạn thứ nhất.\n\nĐoạn thứ hai.", 'excerpt' => 'Mô tả bài viết', 'is_active' => true, 'sort_order' => 1, ...$extra]);
    }

    public function test_two_categories_have_separate_home_panels_and_listing_pages(): void
    {
        $review = $this->article('review', ['title' => 'Bài bình luận mới']);
        $blog = $this->article('blog', ['title' => 'Bài blog mới']);
        $hidden = $this->article('blog', ['title' => 'Nội dung bí mật', 'is_active' => false]);
        $this->get(route('home'))->assertOk()->assertSee('corner-tab-review')->assertSee('corner-tab-blog')
            ->assertViewHas('cornerTabs', fn ($tabs) => $tabs['review']['featured']->is($review) && $tabs['blog']['featured']->is($blog));
        $this->get(route('cinema-corner.index', 'review'))->assertOk()->assertSee('Bài bình luận mới')->assertDontSee('Bài blog mới');
        $this->get(route('cinema-corner.index', 'blog'))->assertOk()->assertSee('Bài blog mới')->assertDontSee('Bài bình luận mới')->assertDontSee('Nội dung bí mật');
        $this->get(route('cinema-corner.show', $hidden))->assertNotFound();
        $this->get('/goc-dien-anh/unknown')->assertNotFound();
    }

    public function test_reading_article_counts_once_per_session_and_escapes_content(): void
    {
        $item = $this->article('blog', ['content' => "Dòng tiếng Việt.\n\n<script>alert('x')</script> Nội dung."]);
        $this->get(route('cinema-corner.show', $item))->assertOk()->assertSee('Dòng tiếng Việt.')->assertDontSee("<script>alert('x')</script>", false);
        $this->get(route('cinema-corner.show', $item))->assertOk();
        $this->assertSame(1, $item->fresh()->views_count);
        $this->assertDatabaseCount('cinema_corner_likes', 0);
    }

    public function test_like_is_authenticated_idempotent_and_can_be_removed(): void
    {
        $item = $this->article();
        $this->postJson(route('cinema-corner.like', $item), ['liked' => true])->assertUnauthorized();
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user)->postJson(route('cinema-corner.like', $item), ['liked' => true])->assertOk()->assertJson(['liked' => true, 'likes' => 1]);
        $this->postJson(route('cinema-corner.like', $item), ['liked' => true])->assertOk()->assertJson(['likes' => 1]);
        $this->assertDatabaseCount('cinema_corner_likes', 1);
        $this->get(route('cinema-corner.index', 'blog'))->assertSee('aria-pressed="true"', false);
        $this->postJson(route('cinema-corner.like', $item), ['liked' => false])->assertOk()->assertJson(['liked' => false, 'likes' => 0]);
        $this->postJson(route('cinema-corner.like', $item), ['liked' => 'invalid'])->assertUnprocessable();
        $item->update(['is_active' => false]);
        $this->postJson(route('cinema-corner.like', $item), ['liked' => true])->assertNotFound();
        $this->assertDatabaseCount('cinema_corner_likes', 0);
    }

    public function test_admin_can_write_standalone_blog_and_move_featured_article_between_categories(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = ['category' => 'blog', 'title' => 'Bài độc lập', 'content' => 'Nội dung riêng của blog', 'image_source' => 'upload', 'placement' => 'featured', 'is_active' => 1, 'sort_order' => 2];
        $this->post(route('admin.cinema-corner.store'), [...$payload, 'image' => UploadedFile::fake()->createWithContent('article.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a1WQAAAAASUVORK5CYII='))])->assertSessionHasNoErrors();
        $item = CinemaCornerItem::firstOrFail();
        $this->assertNull($item->movie_id);
        $this->assertSame($item->id, CinemaCornerSetting::current()->blog_featured_item_id);
        Storage::disk('public')->assertExists($item->image_path);
        $this->get(route('cinema-corner.show', $item))->assertOk()->assertSee('Nội dung riêng của blog');
        $this->put(route('admin.cinema-corner.update', $item), [...$payload, 'category' => 'review'])->assertSessionHasNoErrors();
        $settings = CinemaCornerSetting::current();
        $this->assertNull($settings->blog_featured_item_id);
        $this->assertSame($item->id, $settings->featured_item_id);
        $this->post(route('admin.cinema-corner.store'), [...$payload, 'title' => '', 'content' => '', 'category' => 'invalid'])->assertSessionHasErrors(['title','content','category','image']);
    }

    public function test_search_and_pagination_keep_category_and_exclude_hidden_items(): void
    {
        for ($i = 1; $i <= 11; $i++) $this->article('blog', ['title' => 'Tìm kiếm bài '.$i, 'sort_order' => $i]);
        $this->article('review', ['title' => 'Tìm kiếm bình luận']);
        $this->get(route('cinema-corner.index', ['category' => 'blog', 'q' => 'Tìm kiếm']))->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 11 && $items->count() === 8)->assertSee('q=T%C3%ACm');
        $this->get(route('cinema-corner.index', ['category' => 'blog', 'q' => 'Không tồn tại']))->assertOk()->assertSee('Không tìm thấy bài viết phù hợp');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('admin.cinema-corner.index', ['category' => 'review']))->assertOk()->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_deleting_article_or_user_cleans_up_likes_and_featured_selection(): void
    {
        $item = $this->article();
        $user = User::factory()->create();
        CinemaCornerLike::create(['cinema_corner_item_id' => $item->id, 'user_id' => $user->id]);
        $user->forceDelete();
        $this->assertDatabaseCount('cinema_corner_likes', 0);
        $another = User::factory()->create();
        CinemaCornerLike::create(['cinema_corner_item_id' => $item->id, 'user_id' => $another->id]);
        CinemaCornerSetting::current()->update(['blog_featured_item_id' => $item->id]);
        $item->delete();
        $this->assertDatabaseCount('cinema_corner_likes', 0);
        $this->assertNull(CinemaCornerSetting::current()->blog_featured_item_id);
    }
}
