<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Movie;
use App\Models\Banner;
use App\Models\MovieGenre;
use App\Support\MovieGenres;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MovieController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q'=>['nullable','string','max:150'], 'status'=>['nullable','in:now_showing,upcoming,inactive'],
            'genre'=>['nullable','string','max:100'], 'age'=>['nullable','in:P,K,T13,T16,T18'],
            'sort'=>['nullable','in:release_desc,release_asc,title,shows'], 'view'=>['nullable','in:list,grid'],
            'schedule'=>['nullable','in:missing,scheduled'], 'per_page'=>['nullable','in:10,20,40'],
        ]);
        $filters = ['q'=>trim($data['q'] ?? ''),'status'=>$data['status'] ?? '', 'genre'=>$data['genre'] ?? '',
            'age'=>$data['age'] ?? '', 'sort'=>$data['sort'] ?? 'release_desc','view'=>$data['view'] ?? 'list',
            'schedule'=>$data['schedule'] ?? '', 'per_page'=>(int)($data['per_page'] ?? 10)];
        // These are future scheduled sessions; historical and cancelled sessions remain separately visible.
        $futureShows = fn ($query)=>$query->where('status','active')->where('start_time','>=',now());
        $query = Movie::withCount(['showtimes','showtimes as upcoming_showtimes_count'=>$futureShows])
            ->withMin(['showtimes as next_showtime_at'=>$futureShows],'start_time');
        if ($filters['q'] !== '') $query->whereIn('id',\App\Support\KeywordSearch::matchingIds(
            Movie::get(['id','title','director']),$filters['q'],['title','director']));
        if ($filters['status'] !== '') $query->where('status',$filters['status']);
        if ($filters['genre'] !== '') {
            $filters['genre'] = MovieGenres::lookup()[MovieGenre::key($filters['genre'])]->name ?? $filters['genre'];
            $query->whereIn('id',MovieGenres::movieIds($filters['genre']));
        }
        if ($filters['age'] !== '') $query->where('age_rating',$filters['age']);
        if ($filters['schedule'] !== '') {
            $query->where('status','now_showing');
            $filters['schedule'] === 'missing' ? $query->whereDoesntHave('showtimes',$futureShows) : $query->whereHas('showtimes',$futureShows);
        }
        match ($filters['sort']) {
            'release_asc'=>$query->orderBy('release_date'), 'title'=>$query->orderBy('title'),
            'shows'=>$query->orderByDesc('upcoming_showtimes_count'), default=>$query->orderByDesc('release_date'),
        };
        $movies = $query->orderByDesc('id')->paginate($filters['per_page'])->withQueryString();
        $statusCounts = Movie::selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate','status');
        $counts = ['total'=>$statusCounts->sum(),'now_showing'=>(int)($statusCounts['now_showing'] ?? 0),
            'upcoming'=>(int)($statusCounts['upcoming'] ?? 0),'inactive'=>(int)($statusCounts['inactive'] ?? 0),
            'missing'=>Movie::where('status','now_showing')->whereDoesntHave('showtimes',$futureShows)->count()];
        $genres = MovieGenres::catalog()->pluck('name');
        return view('admin.movies.index',compact('movies','filters','counts','genres'));
    }

    public function create()
    {
        return view('admin.movies.create', ['genreOptions' => MovieGenres::options(),'genreCatalog'=>MovieGenres::lookup()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['rating_avg'] = $data['rating_avg'] ?? 0;
        $data['review_count'] = $data['review_count'] ?? 0;
        $data['age_rating'] = $data['age_rating'] ?? 'T13';

        if ($request->hasFile('poster')) {
            $data['poster_path'] = $request->file('poster')->store('movies/posters', 'public');
        }
        if ($request->hasFile('banner')) {
            $data['banner_path'] = $request->file('banner')->store('movies/banners', 'public');
        }
        if ($request->hasFile('trailer')) {
            $data['trailer_url'] = $request->file('trailer')->store('movies/trailers', 'public');
        } elseif (empty($data['trailer_url'])) {
            unset($data['trailer_url']);
        }

        Movie::create($data);

        return redirect()->route('admin.movies.index')->with('success', 'Đã thêm phim mới.');
    }

    public function edit(Movie $movie)
    {
        return view('admin.movies.edit', ['movie' => $movie, 'genreOptions' => MovieGenres::options(),'genreCatalog'=>MovieGenres::lookup()]);
    }

    public function update(Request $request, Movie $movie)
    {
        $data = $this->validateData($request, $movie->id);
        $data['rating_avg'] = $data['rating_avg'] ?? $movie->rating_avg ?? 0;
        $data['review_count'] = $data['review_count'] ?? $movie->review_count ?? 0;
        $data['age_rating'] = $data['age_rating'] ?? $movie->age_rating ?? 'T13';

        if ($request->hasFile('poster')) {
            if ($movie->poster_path) {
                $this->deleteUnusedImage($movie->poster_path);
            }
            $data['poster_path'] = $request->file('poster')->store('movies/posters', 'public');
        }
        if ($request->hasFile('banner')) {
            if ($movie->banner_path) {
                $this->deleteUnusedImage($movie->banner_path);
            }
            $data['banner_path'] = $request->file('banner')->store('movies/banners', 'public');
        }
        if ($request->hasFile('trailer')) {
            $this->deleteUploadedTrailer($movie->trailer_url);
            $data['trailer_url'] = $request->file('trailer')->store('movies/trailers', 'public');
        } elseif ($request->boolean('remove_trailer')) {
            $this->deleteUploadedTrailer($movie->trailer_url);
            $data['trailer_url'] = null;
        } elseif (! empty($data['trailer_url'])) {
            if ($data['trailer_url'] !== $movie->trailer_url) {
                $this->deleteUploadedTrailer($movie->trailer_url);
            }
        } else {
            // Để trống ô URL không làm mất trailer đã tải lên.
            unset($data['trailer_url']);
        }

        $movie->update($data);
        if ($movie->status === 'upcoming') app(\App\Services\MovieScheduleService::class)->removeUpcoming($movie);

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
            $this->deleteUnusedImage($movie->poster_path);
        }
        if ($movie->banner_path) {
            $this->deleteUnusedImage($movie->banner_path);
        }
        $this->deleteUploadedTrailer($movie->trailer_url);

        $movie->delete();

        return redirect()->route('admin.movies.index')->with('success', 'Đã xóa phim.');
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        // The new menu submits an array; existing callers may still submit the saved genre string.
        if ($request->has('genres_present')) {
            $selection = $request->validate([
                'genres_present' => ['required', 'in:1'],
                'genres' => ['nullable', 'array', 'max:20'],
                'genres.*' => ['required', 'string', 'max:100', 'not_regex:/[,;]/'],
            ], [], ['genres' => 'thể loại', 'genres.*' => 'thể loại']);
            $genre = implode(', ', MovieGenres::parse($selection['genres'] ?? []));
            $request->merge(['genre' => $genre === '' ? null : $genre]);
        }

        $data = $request->validate([
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'genre'        => ['nullable', 'string', 'max:100'],
            'age_rating'   => ['nullable', 'in:P,K,T13,T16,T18'],
            'duration'     => ['required', 'integer', 'min:1'],
            'director'     => ['nullable', 'string', 'max:100'],
            'actors'       => ['nullable', 'string'],
            'trailer_url'  => ['nullable', 'url'],
            'trailer'      => ['nullable', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:51200'],
            'remove_trailer' => ['nullable', 'boolean'],
            'release_date' => ['nullable', 'date'],
            'status'       => ['required', 'in:now_showing,upcoming,inactive'],
            'rating_avg'   => ['nullable', 'numeric', 'min:0', 'max:5'],
            'review_count' => ['nullable', 'integer', 'min:0'],
            'poster'       => ['nullable', 'image', 'max:2048'],
            'banner'       => ['nullable', 'image', 'max:4096'],
        ], ['genre.max' => 'Bạn đã chọn quá nhiều thể loại. Vui lòng bỏ bớt để tiếp tục.']);

        $names = MovieGenres::parse($data['genre'] ?? null);
        $lookup = MovieGenres::lookup();
        $previousKeys = $id ? array_map(MovieGenre::key(...),MovieGenres::parse(Movie::whereKey($id)->value('genre'))) : [];
        foreach ($names as $name) {
            $registered = $lookup[MovieGenre::key($name)] ?? null;
            if (($request->has('genres_present') && !$registered) || ($registered && !$registered->is_active
                && !array_intersect($registered->keys(),$previousKeys))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['genres'=>'Thể loại “'.$name.'” chưa có trong danh sách đang bật. Hãy kiểm tra mục Thể loại phim.']);
            }
        }
        if (array_key_exists('genre',$data)) {
            if ($request->has('genres_present')) $data['genre'] = $names ? implode(', ',array_map(fn ($name)=>$lookup[MovieGenre::key($name)]->name,$names)) : null;
            else $data['genre'] = MovieGenres::resolveAliases($data['genre']);
            if (mb_strlen($data['genre'] ?? '') > 100) throw \Illuminate\Validation\ValidationException::withMessages([
                'genre'=>'Bạn đã chọn quá nhiều thể loại. Vui lòng bỏ bớt để tiếp tục.']);
        }
        return $data;
    }

    private function deleteUnusedImage(string $path): void
    {
        if (!Banner::where('image_path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    private function deleteUploadedTrailer(?string $path): void
    {
        if ($path && str_starts_with($path, 'movies/trailers/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
