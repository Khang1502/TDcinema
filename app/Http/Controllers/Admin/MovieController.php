<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $query = Movie::withCount('showtimes')->orderByDesc('release_date');

        $search = trim((string) $request->get('q', ''));
        $status = $request->get('status'); // now_showing / upcoming / inactive

        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        if (in_array($status, ['now_showing', 'upcoming', 'inactive'], true)) {
            $today = Carbon::today()->toDateString();
            if ($status === 'now_showing') {
                $query->whereNotNull('release_date')->where('release_date', '<=', $today);
            } elseif ($status === 'upcoming') {
                $query->whereNotNull('release_date')->where('release_date', '>', $today);
            } else {
                $query->where('status', 'inactive');
            }
        }

        $movies = $query->paginate(10)->withQueryString();

        $filters = [
            'q' => $search,
            'status' => $status,
        ];

        return view('admin.movies.index', compact('movies', 'filters'));
    }

    public function create()
    {
        return view('admin.movies.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['rating_avg'] = $data['rating_avg'] ?? 0;
        $data['review_count'] = $data['review_count'] ?? 0;

        if ($request->hasFile('poster')) {
            $data['poster_path'] = $request->file('poster')->store('movies/posters', 'public');
        }
        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store('movies/banners', 'public');
        }

        Movie::create($data);

        return redirect()->route('admin.movies.index')->with('success', 'Đã thêm phim mới.');
    }

    public function edit(Movie $movie)
    {
        return view('admin.movies.edit', compact('movie'));
    }

    public function update(Request $request, Movie $movie)
    {
        $data = $this->validateData($request, $movie->id);
        $data['rating_avg'] = $data['rating_avg'] ?? $movie->rating_avg ?? 0;
        $data['review_count'] = $data['review_count'] ?? $movie->review_count ?? 0;

        if ($request->hasFile('poster')) {
            if ($movie->poster_path) {
                Storage::disk('public')->delete($movie->poster_path);
            }
            $data['poster_path'] = $request->file('poster')->store('movies/posters', 'public');
        }
        if ($request->hasFile('banner')) {
            if ($movie->banner_path) {
                Storage::disk('public')->delete($movie->banner_path);
            }
            $data['banner_path'] = $request->file('banner')->store('movies/banners', 'public');
        }

        $movie->update($data);

        return redirect()->route('admin.movies.index')->with('success', 'Đã cập nhật phim.');
    }

    public function destroy(Movie $movie)
    {
        // Nếu phim đã có suất chiếu, không xoá cứng mà chuyển inactive
        if ($movie->showtimes()->exists()) {
            $movie->update(['status' => 'inactive']);
            return redirect()->route('admin.movies.index')->with('warning', 'Phim đã có suất chiếu, hệ thống chỉ vô hiệu hóa phim.');
        }

        if ($movie->poster_path) {
            Storage::disk('public')->delete($movie->poster_path);
        }
        if ($movie->banner_path) {
            Storage::disk('public')->delete($movie->banner_path);
        }

        $movie->delete();

        return redirect()->route('admin.movies.index')->with('success', 'Đã xóa phim.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'genre'        => ['nullable', 'string', 'max:100'],
            'duration'     => ['required', 'integer', 'min:1'],
            'director'     => ['nullable', 'string', 'max:100'],
            'actors'       => ['nullable', 'string'],
            'trailer_url'  => ['nullable', 'url'],
            'release_date' => ['nullable', 'date'],
            'status'       => ['required', 'in:now_showing,upcoming,inactive'],
            'rating_avg'   => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'poster'       => ['nullable', 'image', 'max:2048'],
            'banner'       => ['nullable', 'image', 'max:4096'],
        ]);
    }
}
