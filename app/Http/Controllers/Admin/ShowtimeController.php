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
        $data = $request->validate(['q'=>['nullable','string','max:150'], 'date'=>['nullable','date_format:Y-m-d'],
            'movie_id'=>['nullable','integer','exists:movies,id'], 'cinema_id'=>['nullable','integer','exists:cinemas,id']]);
        $date = $request->has('date') ? ($data['date'] ?? null) : Carbon::today()->toDateString();
        $movieId = $data['movie_id'] ?? null;
        $cinemaId = $data['cinema_id'] ?? null;
        $keyword = trim(preg_replace('/\s+/u',' ',$data['q'] ?? ''));
        $movies = Movie::orderBy('title')->get(['id','title','status']);
        $cinemas = \App\Models\Cinema::orderBy('name')->get(['id','name']);
        $query = Showtime::with(['room.cinema','movie'])->withCount(['tickets as confirmed_tickets_count'=>fn ($q)=>$q->whereIn('status',['confirmed','used'])]);
        if ($date) {
            $day = Carbon::createFromFormat('Y-m-d',$date)->startOfDay();
            $query->where('start_time','>=',$day)->where('start_time','<',$day->copy()->addDay());
        }
        if ($movieId) $query->where('movie_id',$movieId);
        if ($cinemaId) $query->whereHas('room',fn ($q)=>$q->where('cinema_id',$cinemaId));
        if ($keyword !== '') $query->whereIn('movie_id',\App\Support\KeywordSearch::matchingIds($movies,$keyword));
        $showtimes = $query->orderBy('start_time')->orderBy('id')->paginate(20)->withQueryString();
        return view('admin.showtimes.index',compact('showtimes','movies','cinemas','date','movieId','cinemaId','keyword'));
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
            $selectedDay = Carbon::parse($date);
            $showtimes = Showtime::with(['room.cinema', 'movie'])
                ->whereBetween('start_time', [$selectedDay->copy()->startOfDay(), $selectedDay->copy()->endOfDay()])
                ->where('movie_id', $movieId)
                ->orderBy('start_time')
                ->get();
        }

        return view('admin.showtimes.schedule', compact('showtimes', 'movies', 'date', 'movieId'));
    }

    public function create(Request $request)
    {
        $movies = Movie::where('status','now_showing')->orderBy('title')->get();
        $rooms = Room::with('cinema')
            ->orderBy('cinema_id')
            ->orderBy('id')
            ->get();

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
        $rooms = Room::with('cinema')
            ->orderBy('cinema_id')
            ->orderBy('id')
            ->get();
        return view('admin.showtimes.edit', compact('showtime', 'movies', 'rooms'));
    }

    public function update(Request $request, Showtime $showtime)
    {
        $data = $this->validatedData($request);
        if ($showtime->tickets()->exists() && (
            (int) $showtime->movie_id !== (int) $data['movie_id']
            || (int) $showtime->room_id !== (int) $data['room_id']
            || $showtime->start_time->format('Y-m-d\TH:i') !== $data['start_time']
            || $showtime->end_time->format('Y-m-d\TH:i') !== $data['end_time']
            || (float) $showtime->base_price !== (float) $data['base_price']
        )) {
            return back()->withInput()->withErrors(['showtime' => 'Suất chiếu đã có vé; không thể đổi phim, phòng, thời gian hoặc giá. Có thể chuyển trạng thái sang hủy.']);
        }
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
            $showtime->update(['status' => 'cancelled']);
            return redirect()->route('admin.showtimes.index')->with('warning', 'Suất chiếu đã có vé, hệ thống chỉ đóng suất chiếu.');
        }
        $showtime->delete();
        return redirect()->route('admin.showtimes.index')->with('success', 'Đã xóa suất chiếu.');
    }

    public function refreshSchedule(Request $request, \App\Services\MovieScheduleService $schedules)
    {
        $data = $request->validate(['days'=>['required','integer','between:1,30']]);
        $result = $schedules->fill((int) $data['days']);
        $message = "Đã thêm {$result['created']} suất, cân đối {$result['reassigned']} suất cho phim đang chiếu, gỡ {$result['deleted']} suất sắp chiếu và đóng {$result['closed']} suất có vé.";
        if ($result['missing']) $message .= ' Có '.count($result['missing']).' lịch chưa đủ phòng trống.';
        return back()->with($result['missing'] ? 'warning' : 'success', $message);
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'movie_id'    => ['required', \Illuminate\Validation\Rule::exists('movies', 'id')->where('status', 'now_showing')],
            'room_id'     => ['required', 'exists:rooms,id'],
            'start_time'  => ['required', 'date_format:Y-m-d\\TH:i'],
            'end_time'    => ['required', 'date_format:Y-m-d\\TH:i', 'after:start_time'],
            'base_price'  => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:active,cancelled'],
        ]);
    }

    /**
     * Nhân bản lịch chiếu từ một ngày sang ngày khác cho cùng phim.
     */
    public function cloneDay(Request $request)
    {
        $data = $request->validate([
            'movie_id'    => ['required', \Illuminate\Validation\Rule::exists('movies', 'id')->where('status', 'now_showing')],
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
            ->whereBetween('start_time', [
                Carbon::parse($sourceDate)->startOfDay(),
                Carbon::parse($sourceDate)->endOfDay(),
            ]);

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
            $durationSeconds = (int)$source->start_time->diffInSeconds($source->end_time,false);
            if ($durationSeconds <= 0) {
                $skipped[] = 'Suất #'.$source->id.' có thời gian kết thúc không hợp lệ';
                continue;
            }
            $endTime = $startTime->copy()->addSeconds($durationSeconds);

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
