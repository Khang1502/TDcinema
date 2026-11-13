<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MonthlyMovieSetting;
use App\Models\Movie;
use App\Services\MonthlyMovieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MonthlyMovieController extends Controller
{
    public function index(Request $request, MonthlyMovieService $monthlyMovies): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['q'] ?? '');
        $settings = MonthlyMovieSetting::current();
        $movies = Movie::query()->where('status', 'now_showing')
            ->when($search !== '', fn ($query) => $query->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('rating_avg')
            ->orderByDesc('review_count')
            ->orderBy('title')
            ->paginate(20)->withQueryString();

        return view('admin.monthly-movies.index', [
            'movies' => $movies,
            'settings' => $settings,
            'search' => $search,
            'nowShowingCount' => Movie::where('status', 'now_showing')->count(),
            'visibleCount' => $monthlyMovies->eligibleQuery($settings)->count(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'min_rating' => ['required', 'numeric', 'min:0.1', 'max:5'],
            'min_reviews' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        MonthlyMovieSetting::updateOrCreate(['id' => 1], $data);

        return redirect()->route('admin.monthly-movies.index')->with('success', 'Đã cập nhật tiêu chí Phim hay tháng.');
    }

    public function update(Request $request, Movie $movie): RedirectResponse
    {
        if ($movie->status !== 'now_showing') {
            throw ValidationException::withMessages(['movie' => 'Chỉ phim đang chiếu mới có thể quản lý ở mục này.']);
        }

        $movie->update($request->validate([
            'rating_avg' => ['required', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_monthly_featured' => ['required', 'boolean'],
        ]));

        return redirect()->back()->with('success', 'Đã cập nhật phim '.$movie->title.' trong mục Phim hay tháng.');
    }
}
