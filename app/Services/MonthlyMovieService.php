<?php

namespace App\Services;

use App\Models\MonthlyMovieSetting;
use App\Models\Movie;
use Illuminate\Database\Eloquent\Builder;

class MonthlyMovieService
{
    public function eligibleQuery(?MonthlyMovieSetting $settings = null): Builder
    {
        $settings ??= MonthlyMovieSetting::current();

        return Movie::query()
            ->where('status', 'now_showing')
            ->where('is_monthly_featured', true)
            ->where('rating_avg', '>=', $settings->min_rating)
            ->where('review_count', '>=', $settings->min_reviews);
    }

    public function rankedQuery(?MonthlyMovieSetting $settings = null): Builder
    {
        return $this->eligibleQuery($settings)
            ->orderByDesc('rating_avg')
            ->orderByDesc('review_count')
            ->orderByDesc('release_date')
            ->orderBy('id');
    }
}
