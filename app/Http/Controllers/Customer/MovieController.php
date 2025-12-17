<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
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
        $validStatuses = ['now_showing', 'upcoming', 'imax', 'all'];
        if (!in_array($status, $validStatuses, true)) {
            $status = 'now_showing';
        }

        $query = Movie::orderByDesc('release_date');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $movies = $query->paginate(12)->appends(['status' => $status]);

        return view('frontend.movies.index', [
            'movies' => $movies,
            'status' => $status,
        ]);
    }

    public function show(Movie $movie): View
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $today = Carbon::now($tz)->startOfDay();
        // Hiển thị hôm nay + 6 ngày tiếp theo (tổng 7 ngày)
        $endDay = $today->copy()->addDays(6)->endOfDay();

        // Sau khi đăng nhập giữ nguyên trang chi tiết
        session()->put('url.intended', request()->fullUrl());

        $showtimes = Showtime::with(['room.cinema'])
            ->where('movie_id', $movie->id)
            ->whereBetween('start_time', [$today, $endDay])
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
        ]);
    }
}
