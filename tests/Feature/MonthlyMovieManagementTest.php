<?php

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyMovieManagementTest extends TestCase
{
    use RefreshDatabase;

    private function movie(string $title, string $status, float $rating, int $reviews = 0): Movie
    {
        return Movie::create([
            'title' => $title,
            'duration' => 100,
            'status' => $status,
            'rating_avg' => $rating,
            'review_count' => $reviews,
        ]);
    }

    public function test_customer_list_only_contains_eligible_now_showing_movies_in_rating_order(): void
    {
        $this->movie('Cao nhiều lượt', 'now_showing', 4.8, 40);
        $this->movie('Cao ít lượt', 'now_showing', 4.8, 5);
        $this->movie('Vừa', 'now_showing', 4.2, 10);
        $this->movie('Thấp', 'now_showing', 3.9, 100);
        $this->movie('Sắp chiếu', 'upcoming', 5.0, 100);
        $this->movie('Ngừng chiếu', 'inactive', 5.0, 100);

        $this->get(route('movies.index', ['status' => 'top_rated']))->assertOk()
            ->assertViewHas('movies', fn ($movies) => $movies->pluck('title')->all() === ['Cao nhiều lượt', 'Cao ít lượt', 'Vừa']);
    }

    public function test_admin_can_set_criteria_and_edit_or_hide_a_now_showing_movie(): void
    {
        $movie = $this->movie('Phim kiểm tra', 'now_showing', 4.5, 3);
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->get(route('admin.monthly-movies.index'))->assertOk()
            ->assertSee('Phim kiểm tra')->assertSee('Tiêu chí hiển thị');

        $this->put(route('admin.monthly-movies.settings'), ['min_rating' => 4.6, 'min_reviews' => 5])
            ->assertRedirect(route('admin.monthly-movies.index'));
        $this->get(route('movies.index', ['status' => 'top_rated']))
            ->assertViewHas('movies', fn ($movies) => $movies->isEmpty());

        $this->patch(route('admin.monthly-movies.update', $movie), [
            'rating_avg' => 4.9, 'review_count' => 8, 'is_monthly_featured' => 1,
        ])->assertSessionHasNoErrors();
        $this->get(route('movies.index', ['status' => 'top_rated']))
            ->assertViewHas('movies', fn ($movies) => $movies->pluck('id')->all() === [$movie->id]);

        $this->patch(route('admin.monthly-movies.update', $movie), [
            'rating_avg' => 4.9, 'review_count' => 8, 'is_monthly_featured' => 0,
        ])->assertSessionHasNoErrors();
        $this->get(route('movies.index', ['status' => 'top_rated']))
            ->assertViewHas('movies', fn ($movies) => $movies->isEmpty());
    }

    public function test_non_showing_movies_and_staff_cannot_change_monthly_selection(): void
    {
        $upcoming = $this->movie('Sắp chiếu', 'upcoming', 5.0, 20);
        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->patch(route('admin.monthly-movies.update', $upcoming), [
                'rating_avg' => 4.0, 'review_count' => 1, 'is_monthly_featured' => 1,
            ])->assertSessionHasErrors('movie');
        $this->assertEquals(5.0, $upcoming->fresh()->rating_avg);

        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('admin.monthly-movies.index'))->assertForbidden();
        $this->put(route('admin.monthly-movies.settings'), ['min_rating' => 1, 'min_reviews' => 0])->assertForbidden();
    }
}
