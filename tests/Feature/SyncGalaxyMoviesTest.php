<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncGalaxyMoviesTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_imports_movie_details_and_keeps_imax_as_an_additional_category(): void
    {
        $summary = [
            'id' => 'movie-1',
            'name' => 'Phim Đồng Bộ',
            'age' => '16',
            'duration' => 120,
            'startDate' => now()->subDay()->format('Y-m-d 00:00:00'),
            'imageLandscape' => 'https://cdn.example.test/banner.jpg',
            'imagePortrait' => 'https://cdn.example.test/poster.jpg',
            'rate' => 9.2,
            'totalVotes' => 48,
        ];
        $detail = array_replace($summary, [
            'link' => 'https://www.galaxycine.vn/dat-ve/phim-dong-bo',
            'trailer' => 'https://www.youtube.com/watch?v=example',
            'country' => 'Việt Nam',
            'categories' => [['name' => 'Hành động'], ['name' => 'Phiêu lưu']],
            'directors' => [['name' => 'Đạo Diễn A']],
            'actors' => [['name' => 'Diễn Viên A'], ['name' => 'Diễn Viên B']],
        ]);

        Http::fake([
            '*movies/showing' => Http::response(['data' => ['result' => [$summary]]]),
            '*movies/comming' => Http::response(['data' => ['result' => []]]),
            '*movies/movie-imax' => Http::response(['data' => ['result' => [$summary]]]),
            '*movies/movie-1' => Http::response(['data' => $detail]),
        ]);

        $this->artisan('movies:sync-galaxy', ['--no-images' => true])
            ->assertSuccessful();

        $movie = Movie::where('external_id', 'movie-1')->firstOrFail();
        $this->assertSame('now_showing', $movie->status);
        $this->assertTrue($movie->is_imax);
        $this->assertSame('T16', $movie->age_rating);
        $this->assertSame('Hành động, Phiêu lưu', $movie->genre);
        $this->assertSame('Đạo Diễn A', $movie->director);
        $this->assertSame('Diễn Viên A, Diễn Viên B', $movie->actors);
        $this->assertSame(4.6, (float) $movie->rating_avg);
        $this->assertStringContainsString('Phim do Đạo Diễn A đạo diễn', $movie->description);
        $this->assertStringContainsString('Dàn diễn viên gồm Diễn Viên A, Diễn Viên B', $movie->description);
        $this->assertStringNotContainsString('<p', $movie->description);
    }
}
