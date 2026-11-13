<?php

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_movies_can_be_searched_by_title_or_genre(): void
    {
        Movie::create([
            'title' => 'Hành Trình Sao Hỏa',
            'genre' => 'Khoa học viễn tưởng',
            'duration' => 120,
            'status' => 'now_showing',
        ]);
        Movie::create([
            'title' => 'Mùa Hè Rực Rỡ',
            'genre' => 'Tình cảm',
            'duration' => 95,
            'status' => 'now_showing',
        ]);

        $this->get('/movies?status=all&q=Sao+Hỏa')
            ->assertOk()
            ->assertViewHas('movies', fn ($movies) => $movies->count() === 1
                && $movies->first()->title === 'Hành Trình Sao Hỏa');

        $this->get('/movies?status=all&q=Tình+cảm')
            ->assertOk()
            ->assertViewHas('movies', fn ($movies) => $movies->count() === 1
                && $movies->first()->title === 'Mùa Hè Rực Rỡ');
    }

    public function test_search_query_is_preserved_when_paginating(): void
    {
        foreach (range(1, 13) as $index) {
            Movie::create([
                'title' => "Phim tìm kiếm {$index}",
                'genre' => 'Phiêu lưu',
                'duration' => 100,
                'status' => 'now_showing',
            ]);
        }

        $this->get('/movies?status=all&q=Phim+tìm+kiếm')
            ->assertOk()
            ->assertViewHas('movies', fn ($movies) => str_contains(
                urldecode($movies->url(2)),
                'q=Phim tìm kiếm'
            ));
    }

    public function test_empty_category_uses_upcoming_message_instead_of_search_error(): void
    {
        $this->get('/movies?status=imax')
            ->assertOk()
            ->assertSee('Chưa có phim nào ở phần này, vui lòng chờ đón trong thời gian sắp tới')
            ->assertDontSee('Không tìm thấy phim phù hợp');
    }

    public function test_empty_search_keeps_search_specific_message(): void
    {
        $this->get('/movies?status=all&q=khong-co-phim-nay')
            ->assertOk()
            ->assertSee('Không tìm thấy phim phù hợp');
    }

    public function test_top_rated_movies_are_ranked_and_have_their_own_heading(): void
    {
        foreach ([['Thấp', 3.8], ['Cao', 4.9], ['Giữa', 4.3], ['Chưa đánh giá', 0]] as [$title, $rating]) {
            Movie::create(['title' => $title, 'duration' => 90, 'status' => 'now_showing', 'rating_avg' => $rating]);
        }
        Movie::create(['title' => 'Sắp chiếu điểm cao', 'duration' => 90, 'status' => 'upcoming', 'rating_avg' => 5]);
        Movie::create(['title' => 'Ngừng chiếu điểm cao', 'duration' => 90, 'status' => 'inactive', 'rating_avg' => 5]);

        $this->get(route('movies.index', ['status' => 'top_rated']))
            ->assertOk()
            ->assertSee('<h1 class="top-rated-heading">Phim hay tháng</h1>', false)
            ->assertDontSee('class="nav-tabs"', false)
            ->assertDontSee('class="status-badge', false)
            ->assertViewHas('movies', fn ($movies) => $movies->pluck('title')->all() === ['Cao', 'Giữa']);
    }
}
