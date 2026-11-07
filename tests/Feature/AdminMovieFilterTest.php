<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMovieFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_filters_match_saved_status_regardless_of_release_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $expected = [];

        foreach (['now_showing', 'upcoming', 'inactive'] as $status) {
            foreach ([now()->subMonth()->toDateString(), now()->addMonth()->toDateString(), null] as $date) {
                $movie = Movie::create([
                    'title' => 'Phim '.$status.' '.count($expected[$status] ?? []),
                    'duration' => 100,
                    'status' => $status,
                    'release_date' => $date,
                ]);
                $expected[$status][] = $movie->id;
            }
        }

        foreach ($expected as $status => $ids) {
            $this->actingAs($admin)->get(route('admin.movies.index', ['status' => $status]))
                ->assertOk()
                ->assertViewHas('movies', fn ($movies) => $movies->total() === count($ids)
                    && $movies->getCollection()->pluck('id')->sort()->values()->all() === $ids)
                ->assertViewHas('filters', fn ($filters) => $filters['status'] === $status);
        }
    }

    public function test_search_and_pagination_keep_the_selected_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 12) as $index) {
            Movie::create([
                'title' => 'Phim tìm kiếm '.$index,
                'duration' => 100,
                'status' => 'now_showing',
                'release_date' => now()->addMonth()->toDateString(),
            ]);
        }
        foreach (['upcoming', 'inactive'] as $status) {
            Movie::create([
                'title' => 'Phim tìm kiếm '.$status,
                'duration' => 100,
                'status' => $status,
                'release_date' => now()->subMonth()->toDateString(),
            ]);
        }
        Movie::create(['title' => 'Tên khác', 'duration' => 100, 'status' => 'now_showing']);

        $filters = ['q' => 'Phim tìm kiếm', 'status' => 'now_showing'];
        $this->actingAs($admin)->get(route('admin.movies.index', $filters))
            ->assertOk()
            ->assertViewHas('movies', fn ($movies) => $movies->total() === 12
                && $movies->count() === 10
                && $movies->every(fn ($movie) => $movie->status === 'now_showing')
                && str_contains(urldecode($movies->url(2)), 'q=Phim tìm kiếm')
                && str_contains($movies->url(2), 'status=now_showing'));

        $this->get(route('admin.movies.index', $filters + ['page' => 2]))
            ->assertOk()
            ->assertViewHas('movies', fn ($movies) => $movies->total() === 12
                && $movies->count() === 2
                && $movies->every(fn ($movie) => $movie->status === 'now_showing'));

        $this->get(route('admin.movies.index'))->assertOk()
            ->assertViewHas('movies', fn ($movies) => $movies->total() === 15);
    }
}
