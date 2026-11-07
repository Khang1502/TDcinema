<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Services\MovieArchiveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovieArchiveController extends Controller
{
    public function index(Request $request, MovieArchiveService $archive)
    {
        $search = trim((string)$request->input('q', ''));
        $movies = Movie::where('status', 'inactive')->when($search !== '', fn ($q) => $q->where('title', 'like', '%'.$search.'%'))
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(10)->withQueryString();
        $financial = $request->user()->hasAdminPermission('reports.view');
        $summaries = $archive->summaries($movies->getCollection()->modelKeys(), $financial);
        return view('admin.movies.archive.index', compact('movies', 'search', 'financial', 'summaries'));
    }

    public function show(Request $request, Movie $movie, MovieArchiveService $archive)
    {
        abort_unless($movie->status === 'inactive', 404);
        $financial = $request->user()->hasAdminPermission('reports.view');
        $summary = $archive->summaries([$movie->id], $financial)->get($movie->id);
        $cinemas = $archive->cinemas($movie->id, $financial);
        $showtimes = $movie->showtimes()->with('room.cinema')->withCount('tickets')->orderByDesc('start_time')->orderByDesc('id')->paginate(12)->withQueryString();
        return view('admin.movies.archive.show', compact('movie', 'financial', 'summary', 'cinemas', 'showtimes'));
    }

    public function store(Movie $movie)
    {
        $movie->update(['status'=>'inactive']);
        return redirect()->route('admin.movies.archive.show', $movie)->with('success', 'Đã lưu trữ phim. Suất chiếu, vé và lịch sử thanh toán được giữ nguyên.');
    }

    public function restore(Request $request, Movie $movie)
    {
        abort_unless($movie->status === 'inactive', 404);
        $data = $request->validate(['status'=>['required','in:now_showing,upcoming']]);
        DB::transaction(function () use ($movie, $data) {
            $movie->update($data);
            if ($data['status'] === 'upcoming') $movie->showtimes()->where('start_time', '>', now())->where('status', 'active')->update(['status'=>'cancelled']);
        });
        return redirect()->route('admin.movies.index', ['status'=>$data['status']])->with('success', 'Đã khôi phục phim. Lịch sử vé và thanh toán vẫn được giữ nguyên.');
    }
}
