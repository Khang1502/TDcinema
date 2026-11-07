<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MovieController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'now_showing');
        $search = trim((string) $request->query('q', ''));
        $validStatuses = ['now_showing', 'upcoming', 'imax', 'all'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'now_showing';
        }

        $selectedCinema = Cinema::query()
            ->whereKey($request->integer('cinema_id'))
            ->where('status', 'active')
            ->first();

        $query = Movie::orderByDesc('release_date');
        if ($status === 'imax') {
            $query->imax();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($search !== '') {
            $query->where(function ($movieQuery) use ($search) {
                $movieQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('genre', 'like', "%{$search}%")
                    ->orWhere('director', 'like', "%{$search}%")
                    ->orWhere('actors', 'like', "%{$search}%");
            });
        }
        if ($selectedCinema) {
            $query->whereHas('showtimes', function ($showtimeQuery) use ($selectedCinema) {
                $showtimeQuery
                    ->where('start_time', '>=', Carbon::now('Asia/Ho_Chi_Minh'))
                    ->where(function ($statusQuery) {
                        $statusQuery->where('status', 'active')
                            ->orWhere('status', 1)
                            ->orWhereNull('status');
                    })
                    ->whereHas('room', function ($roomQuery) use ($selectedCinema) {
                        $roomQuery->where('cinema_id', $selectedCinema->id)
                            ->where('status', 'active');
                    });
            });
        }

        $movies = $query->paginate(12)->withQueryString();

        return view('frontend.movies.index', [
            'movies' => $movies,
            'status' => $status,
            'search' => $search,
            'selectedCinema' => $selectedCinema,
        ]);
    }

    public function show(Request $request, Movie $movie): View
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $today = Carbon::now($tz)->startOfDay();
        // Hiển thị hôm nay + 6 ngày tiếp theo (tổng 7 ngày)
        $endDay = $today->copy()->addDays(6)->endOfDay();

        // Sau khi đăng nhập giữ nguyên trang chi tiết
        session()->put('url.intended', request()->fullUrl());

        $selectedCinema = Cinema::query()
            ->whereKey($request->integer('cinema_id'))
            ->where('status', 'active')
            ->first();

        $showtimes = Showtime::with(['room.cinema'])
            ->whereHas('movie', fn ($q) => $q->where('status', 'now_showing'))
            ->where('movie_id', $movie->id)
            ->whereBetween('start_time', [$today, $endDay])
            ->where(function ($statusQuery) {
                $statusQuery->where('status', 'active')
                    ->orWhere('status', 1)
                    ->orWhereNull('status');
            })
            ->when($selectedCinema, function ($showtimeQuery) use ($selectedCinema) {
                $showtimeQuery->whereHas('room', function ($roomQuery) use ($selectedCinema) {
                    $roomQuery->where('cinema_id', $selectedCinema->id);
                });
            })
            ->orderBy('start_time')
            ->get();

        $groupedShowtimes = [];
        $cities = collect();
        $cinemasByCity = [];
        foreach ($showtimes as $showtime) {
            $dateKey = Carbon::parse($showtime->start_time)->toDateString();
            $cinema = $showtime->room?->cinema;
            if (!$cinema) {
                continue;
            }
            $cityName = $cinema->city ?? 'Khác';
            $cities->push($cityName);
            $cinemasByCity[$cityName][$cinema->id] = [
                'id' => $cinema->id,
                'name' => $cinema->name,
            ];
            $cinemaKey = $cinema->id;

            if (!isset($groupedShowtimes[$dateKey])) {
                $groupedShowtimes[$dateKey] = [];
            }
            if (!isset($groupedShowtimes[$dateKey][$cinemaKey])) {
                $groupedShowtimes[$dateKey][$cinemaKey] = [
                    'cinema' => $cinema,
                    'showtimes' => [],
                ];
            }

            $groupedShowtimes[$dateKey][$cinemaKey]['showtimes'][] = [
                'id' => $showtime->id,
                'time' => Carbon::parse($showtime->start_time)->format('H:i'),
                'room' => $showtime->room->name ?? '',
            ];
        }

        ksort($groupedShowtimes);
        $cities = $cities->filter()->unique()->values();
        $cinemasByCity = collect($cinemasByCity)->map(function ($cinemas) {
            return collect($cinemas)->values();
        });

        $dateTabs = collect(range(0, 6))->map(function ($i) use ($today) {
            $date = $today->copy()->addDays($i);
            return [
                'key' => $date->toDateString(),
                'label' => $date->locale('vi')->translatedFormat('D d/m'),
            ];
        })->toArray();

        $nowShowing = Movie::where('status', 'now_showing')
            ->where('id', '!=', $movie->id)
            ->when($selectedCinema, function ($movieQuery) use ($selectedCinema, $today, $endDay) {
                $movieQuery->whereHas('showtimes', function ($showtimeQuery) use ($selectedCinema, $today, $endDay) {
                    $showtimeQuery->whereBetween('start_time', [$today, $endDay])
                        ->whereHas('room', fn ($roomQuery) => $roomQuery->where('cinema_id', $selectedCinema->id));
                });
            })
            ->orderByDesc('release_date')
            ->take(5)
            ->get();

        return view('frontend.movie', [
            'movie' => $movie,
            'dateTabs' => $dateTabs,
            'groupedShowtimes' => $groupedShowtimes,
            'sidebarMovies' => $nowShowing,
            'cities' => $cities,
            'cinemasByCity' => $cinemasByCity,
            'selectedCinema' => $selectedCinema,
        ]);
    }
}
