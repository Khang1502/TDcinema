<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Showtime;
use App\Models\Movie;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class ShowtimeController extends Controller
{
    /**
     * Danh sách lịch chiếu (lọc theo ngày + phim).
     */
    public function index(Request $request)
    {
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));
        $movieId = $request->input('movie_id');
        $cinemaId = $request->input('cinema_id');
        $keyword = $request->input('q');

        $query = Showtime::with(['room.cinema', 'movie'])
            ->whereDate('start_time', $date)
            ->orderBy('start_time');

        if ($movieId) {
            $query->where('movie_id', $movieId);
        }

        if ($cinemaId) {
            $query->whereHas('room.cinema', function ($q) use ($cinemaId) {
                $q->where('id', $cinemaId);
            });
        }

        if ($keyword) {
            $query->whereHas('movie', function ($q) use ($keyword) {
                $q->where('title', 'like', '%' . $keyword . '%');
            });
        }

        $showtimes = $query->paginate(20)->withQueryString();
        $movies = Movie::orderBy('title')->get();
        $cinemas = \App\Models\Cinema::orderBy('name')->get();

        return view('admin.showtimes.index', compact('showtimes', 'movies', 'cinemas', 'date', 'movieId', 'cinemaId', 'keyword'));
    }

    /**
     * Màn hình Lịch chiếu (lọc theo ngày + phim, hiển thị theo mock).
     */
    public function schedule(Request $request)
    {
        $date = $request->input('date', Carbon::today()->format('Y-m-d'));
        $movies = Movie::orderBy('title')->get();

        // Chọn phim mặc định: phim truyền vào, hoặc phim đầu tiên nếu có danh sách
        $movieId = $request->input('movie_id');
        if (!$movieId && $movies->count() > 0) {
            $movieId = $movies->first()->id;
        }

        $showtimes = collect();
        if ($movieId) {
            $showtimes = Showtime::with(['room.cinema', 'movie'])
                ->whereDate('start_time', $date)
                ->where('movie_id', $movieId)
                ->orderBy('start_time')
                ->get();
        }

        return view('admin.showtimes.schedule', compact('showtimes', 'movies', 'date', 'movieId'));
    }

    public function create(Request $request)
    {
        $movies = Movie::orderBy('title')->get();
        $rooms = Room::with('cinema')->orderBy('name')->get();

        // Prefill khi nhấn “Thêm suất chiếu” từ trang lịch/schedule
        $prefillMovie = $request->query('movie_id');
        $prefillRoom  = $request->query('room_id');
        $prefillDate  = $request->query('date') ?: Carbon::today()->toDateString();

        // Giờ mặc định: 09:00 - 11:00 của ngày được chọn (có thể override qua query start_time/end_time)
        $defaultStart = $request->query('start_time')
            ? Carbon::parse($request->query('start_time'))
            : Carbon::parse($prefillDate . ' 09:00');

        $defaultEnd = $request->query('end_time')
            ? Carbon::parse($request->query('end_time'))
            : (clone $defaultStart)->addHours(2);

        return view('admin.showtimes.create', compact(
            'movies',
            'rooms',
            'prefillMovie',
            'prefillRoom',
            'prefillDate',
            'defaultStart',
            'defaultEnd'
        ));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data = $this->normalizeStatus($data);
        $conflicts = $this->findConflicts($data);
        if ($conflicts->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_time' => 'Phòng đã có suất trùng: ' . $this->formatConflicts($conflicts),
                ]);
        }
        Showtime::create($data);
        return redirect()->route('admin.showtimes.index')->with('success', 'Đã thêm suất chiếu.');
    }

    public function edit(Showtime $showtime)
    {
        $movies = Movie::orderBy('title')->get();
        $rooms = Room::with('cinema')->orderBy('name')->get();
        return view('admin.showtimes.edit', compact('showtime', 'movies', 'rooms'));
    }

    public function update(Request $request, Showtime $showtime)
    {
        $data = $this->validatedData($request);
        $data = $this->normalizeStatus($data);
        $conflicts = $this->findConflicts($data, $showtime->id);
        if ($conflicts->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    'start_time' => 'Phòng đã có suất trùng: ' . $this->formatConflicts($conflicts),
                ]);
        }
        $showtime->update($data);
        return redirect()->route('admin.showtimes.index')->with('success', 'Đã cập nhật suất chiếu.');
    }

    public function destroy(Showtime $showtime)
    {
        if ($showtime->tickets()->exists()) {
            $showtime->update(['status' => 'closed']);
            return redirect()->route('admin.showtimes.index')->with('warning', 'Suất chiếu đã có vé, hệ thống chỉ đóng suất chiếu.');
        }
        $showtime->delete();
        return redirect()->route('admin.showtimes.index')->with('success', 'Đã xóa suất chiếu.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'movie_id'    => ['required', 'exists:movies,id'],
            'room_id'     => ['required', 'exists:rooms,id'],
            'start_time'  => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_time'    => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_time'],
            'base_price'  => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:0,1'],
        ]);
    }

    private function normalizeStatus(array $data): array
    {
        if (!array_key_exists('status', $data)) {
            return $data;
        }
        $val = $data['status'];
        $data['status'] = (in_array($val, ['open', '1', 'true', 1], true)) ? 1 : 0;
        return $data;
    }

    /**
     * Nhân bản lịch chiếu từ một ngày sang ngày khác cho cùng phim.
     */
    public function cloneDay(Request $request)
    {
        $data = $request->validate([
            'movie_id'    => ['required', 'exists:movies,id'],
            'source_date' => ['required', 'date'],
            'target_date' => ['required', 'date'],
            'room_id'     => ['nullable', 'exists:rooms,id'],
        ]);

        $sourceDate = Carbon::parse($data['source_date'])->toDateString();
        $targetDate = Carbon::parse($data['target_date'])->toDateString();

        if ($sourceDate === $targetDate) {
            return back()->withInput()->withErrors(['target_date' => 'Ngày đích phải khác ngày nguồn.']);
        }

        $query = Showtime::with(['room.cinema', 'movie'])
            ->where('movie_id', $data['movie_id'])
            ->whereDate('start_time', $sourceDate);

        if (!empty($data['room_id'])) {
            $query->where('room_id', $data['room_id']);
        }

        $sourceShowtimes = $query->get();
        if ($sourceShowtimes->isEmpty()) {
            return back()->with('warning', 'Không có suất chiếu để sao chép cho ngày nguồn đã chọn.');
        }

        $created = 0;
        $skipped = [];
        $targetDay = Carbon::parse($targetDate);

        foreach ($sourceShowtimes as $source) {
            $startTime = Carbon::parse($source->start_time)->setDate($targetDay->year, $targetDay->month, $targetDay->day);
            $endTime = Carbon::parse($source->end_time)->setDate($targetDay->year, $targetDay->month, $targetDay->day);

            $payload = [
                'movie_id'   => $source->movie_id,
                'room_id'    => $source->room_id,
                'start_time' => $startTime->format('Y-m-d\TH:i'),
                'end_time'   => $endTime->format('Y-m-d\TH:i'),
                'base_price' => $source->base_price,
                'status'     => $source->status,
            ];

            $conflict = $this->findConflicts($payload);
            if ($conflict->isNotEmpty()) {
                $skipped[] = $this->formatConflicts($conflict);
                continue;
            }

            Showtime::create($payload);
            $created++;
        }

        $message = "Đã sao chép {$created} suất chiếu sang {$targetDate}.";
        if (!empty($skipped)) {
            $message .= ' Bỏ qua do trùng: ' . implode(' | ', $skipped);
            return redirect()->back()->with('warning', $message);
        }

        return redirect()->back()->with('success', $message);
    }

    private function findConflicts(array $data, ?int $ignoreId = null): Collection
    {
        $start = Carbon::parse($data['start_time']);
        $end = Carbon::parse($data['end_time']);

        $query = Showtime::with(['movie', 'room'])
            ->where('room_id', $data['room_id'])
            ->where(function ($q) use ($start, $end) {
                $q->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            });

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->get();
    }

    private function formatConflicts(Collection $conflicts): string
    {
        return $conflicts->map(function (Showtime $conflict) {
            $start = Carbon::parse($conflict->start_time)->format('H:i');
            $end = Carbon::parse($conflict->end_time)->format('H:i');
            $room = $conflict->room->name ?? 'Phòng ?';
            $movie = $conflict->movie->title ?? 'Phim ?';
            return "{$movie} ({$room}, {$start}-{$end})";
        })->implode('; ');
    }
}
