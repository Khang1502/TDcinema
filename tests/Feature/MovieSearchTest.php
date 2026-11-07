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
}
