<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Cinema;
use App\Models\CinemaCornerItem;
use App\Models\FoodItem;
use App\Models\HeaderSetting;
use App\Models\Movie;
use App\Models\Promotion;
use App\Models\User;
use App\Support\ImageAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminImageSourcesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function food(array $extra = []): FoodItem
    {
        return FoodItem::create(['name' => 'Combo kiểm thử', 'price' => 90000, 'status' => 'available', ...$extra]);
    }

    private function movie(array $extra = []): Movie
    {
        return Movie::create(['title' => 'Phim có ảnh', 'duration' => 90, 'status' => 'now_showing', ...$extra]);
    }

    private function sourcePaths($response): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $paths = [];
        foreach ((new \DOMXPath($document))->query('//input[@data-image-source-path]') as $input) {
            $this->assertTrue($input->hasAttribute('readonly'));
            $this->assertFalse($input->hasAttribute('name'));
            $paths[] = $input->getAttribute('value');
        }

        return $paths;
    }

    public function test_image_urls_support_remote_and_existing_storage_paths_without_adding_duplicate_prefixes(): void
    {
        $remote = 'https://images.example.com/cinema.jpg';
        foreach ([new Movie(['poster_path' => $remote]), new Banner(['image_path' => $remote]), new Cinema(['image_path' => $remote]), new CinemaCornerItem(['image_path' => $remote]), new FoodItem(['image' => $remote]), new Promotion(['thumbnail' => $remote])] as $model) {
            $this->assertSame($remote, $model instanceof Movie ? $model->posterUrl() : $model->imageUrl());
        }
        Storage::disk('public')->put('foods/combo.png', 'image');
        foreach (['foods/combo.png', '/storage/foods/combo.png', 'storage/foods/combo.png', 'foods\\combo.png'] as $path) {
            $this->assertSame(asset('storage/foods/combo.png'), (new FoodItem(['image' => $path]))->imageUrl());
        }
        $this->assertSame('HTTPS://images.example.com/poster.jpg', ImageAsset::url(' HTTPS://images.example.com/poster.jpg '));
        $this->assertNull(ImageAsset::url(' '));
        $this->assertNull((new FoodItem(['image' => 'foods/missing.png']))->imageUrl());
        $this->assertSame(asset('assets/images/ui/movie-placeholder.svg'), (new Movie(['poster_path' => 'movies/missing.png']))->posterUrl());
    }

    public function test_food_images_and_their_current_paths_are_consistent_in_admin_and_star_shop(): void
    {
        $remote = 'https://images.example.com/combo.jpg';
        $food = $this->food(['image' => $remote]);
        $this->get(route('admin.foods.index'))->assertOk()->assertSee('src="'.$remote.'"', false)->assertDontSee('/storage/https:');
        $response = $this->get(route('admin.foods.edit', $food))->assertOk()->assertSee('src="'.$remote.'"', false);
        $this->assertSame([$remote], $this->sourcePaths($response));
        $this->get(route('star-shop'))->assertOk()->assertSee('src="'.$remote.'"', false)->assertDontSee('/storage/https:');

        Storage::disk('public')->put('foods/local.png', 'image');
        $food->update(['image' => 'foods/local.png']);
        $response = $this->get(route('admin.foods.edit', $food))->assertOk()->assertSee(asset('storage/foods/local.png'));
        $this->assertSame(['foods/local.png'], $this->sourcePaths($response));
    }

    public function test_editing_food_without_a_new_upload_keeps_the_current_remote_image(): void
    {
        $food = $this->food(['image' => 'https://images.example.com/keep.jpg']);
        $this->put(route('admin.foods.update', $food), ['name' => 'Combo đổi tên', 'price' => 95000, 'status' => 'available', 'image' => null])
            ->assertSessionHasNoErrors()->assertRedirect(route('admin.foods.index'));
        $this->assertSame('https://images.example.com/keep.jpg', $food->fresh()->image);
    }

    public function test_uploading_a_food_image_does_not_delete_an_image_shared_by_another_product(): void
    {
        Storage::disk('public')->put('foods/shared.png', 'old');
        $food = $this->food(['image' => 'foods/shared.png']);
        $other = $this->food(['image' => 'foods/shared.png']);
        $image = UploadedFile::fake()->createWithContent('combo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jHioAAAAASUVORK5CYII='));
        $this->put(route('admin.foods.update', $food), ['name' => $food->name, 'price' => $food->price, 'status' => $food->status, 'image' => $image])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($food->fresh()->image);
        Storage::disk('public')->assertExists($other->image);
        $this->delete(route('admin.foods.destroy', $other))->assertRedirect();
        Storage::disk('public')->assertMissing('foods/shared.png');
    }

    public function test_movie_editor_shows_both_stored_paths_and_empty_fields_have_no_current_preview(): void
    {
        $movie = $this->movie(['poster_path' => 'https://images.example.com/poster.jpg', 'banner_path' => 'movies/banner.jpg']);
        Storage::disk('public')->put('movies/banner.jpg', 'banner');
        $response = $this->get(route('admin.movies.edit', $movie))->assertOk()->assertDontSee('/storage/https:');
        $this->assertSame([$movie->poster_path, $movie->banner_path], $this->sourcePaths($response));

        $empty = $this->movie();
        $response = $this->get(route('admin.movies.edit', $empty))->assertOk();
        $this->assertSame(['', ''], $this->sourcePaths($response));
        $response->assertSee('data-original-url=""  hidden', false);
    }

    public function test_banner_cinema_and_corner_editors_expose_the_source_of_saved_or_inherited_images(): void
    {
        Storage::disk('public')->put('cinemas/photo.jpg', 'cinema');
        $cinema = Cinema::create(['name' => 'Rạp có ảnh', 'address' => '100 Đường A', 'city' => 'Hà Nội', 'status' => 'active', 'image_path' => 'cinemas/photo.jpg']);
        $banner = Banner::create(['title' => 'Banner rạp', 'placement' => 'cinemas', 'cinema_id' => $cinema->id, 'image_path' => $cinema->image_path, 'sort_order' => 0, 'is_active' => true]);
        $this->assertSame([$cinema->image_path], $this->sourcePaths($this->get(route('admin.cinemas.edit', $cinema))->assertOk()));
        $this->assertSame([$cinema->image_path], $this->sourcePaths($this->get(route('admin.banners.edit', $banner))->assertOk()));

        $movie = $this->movie(['banner_path' => 'https://images.example.com/inherited.jpg']);
        $item = CinemaCornerItem::create(['movie_id' => $movie->id, 'sort_order' => 0, 'is_active' => true]);
        $response = $this->get(route('admin.cinema-corner.edit', $item))->assertOk()->assertSee('Đường dẫn ảnh từ phim');
        $this->assertSame([$movie->banner_path], $this->sourcePaths($response));
        $this->assertNull($item->fresh()->image_path);
    }

    public function test_default_logo_is_identified_with_its_actual_asset_path(): void
    {
        $response = $this->get(route('admin.header.edit'))->assertOk()->assertSee('Đường dẫn logo mặc định');
        $this->assertSame(['assets/images/logo/logo-transparent.png'], $this->sourcePaths($response));
        HeaderSetting::updateOrCreate(['id' => 1], [...HeaderSetting::DEFAULTS, 'logo_path' => 'https://images.example.com/logo.png']);
        $response = $this->get(route('admin.header.edit'))->assertOk()->assertSee('Đường dẫn logo đã lưu');
        $this->assertSame(['https://images.example.com/logo.png'], $this->sourcePaths($response));
    }

    public function test_banner_editor_uses_the_actual_banner_path_even_when_the_file_is_missing(): void
    {
        $movie = $this->movie(['poster_path' => 'https://images.example.com/poster.jpg', 'banner_path' => 'movies/missing-banner.jpg']);
        $banner = Banner::create(['title' => 'Banner thiếu tệp', 'movie_id' => $movie->id, 'image_path' => $movie->banner_path, 'sort_order' => 0, 'is_active' => true]);
        $response = $this->get(route('admin.banners.edit', $banner))->assertOk();
        $this->assertSame([$movie->banner_path], $this->sourcePaths($response));
        $response->assertSee('data-image="'.asset('storage/movies/missing-banner.jpg').'" data-image-path="movies/missing-banner.jpg"', false);
        $this->assertSame($movie->poster_path, $movie->bannerUrl());
    }
}
