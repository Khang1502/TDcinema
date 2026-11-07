<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Banner;
use App\Models\Showtime;
use App\Support\CinemaRegions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CinemaController extends Controller
{
    public function index(Request $request): View
    {
        $banners = Banner::with('cinema')->forPlacement('cinemas')->get();
        $cinemaDirectory = Cinema::where('status', 'active')->orderBy('name')->get(['id', 'name', 'city', 'region']);
        $regions = CinemaRegions::groups($cinemaDirectory);
        $selectedRegion = array_key_exists($request->string('region')->toString(), CinemaRegions::LABELS)
            ? $request->string('region')->toString() : '';
        $cities = $cinemaDirectory->when($selectedRegion !== '', fn ($items) => $items->where('region_key', $selectedRegion))
            ->pluck('city')->filter()->unique()->sort()->values();

        $selectedCity = trim((string) $request->query('city', ''));
        if ($selectedCity !== '' && ! $cities->contains($selectedCity)) {
            $selectedCity = '';
        }

        $cinemas = Cinema::query()
            ->select('cinemas.*')
            ->where('cinemas.status', 'active')
            ->when($selectedRegion !== '', fn (Builder $query) => $query->whereIn('cinemas.id', $regions[$selectedRegion]['cinemas']->pluck('id')))
            ->when($selectedCity !== '', fn (Builder $query) => $query->where('cinemas.city', $selectedCity))
            ->withCount([
                'rooms as active_rooms_count' => fn (Builder $query) => $query->where('status', 'active'),
            ])
            ->selectSub(function ($query) {
                $query->from('showtimes')
                    ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
                    ->whereColumn('rooms.cinema_id', 'cinemas.id')
                    ->where('showtimes.status', 'active')
                    ->selectRaw('MIN(showtimes.base_price)');
            }, 'min_ticket_price')
            ->orderBy('cinemas.city')
            ->orderBy('cinemas.name')
            ->get();

        $priceFormats = Showtime::query()
            ->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
            ->where('showtimes.status', 'active')
            ->selectRaw('rooms.type, MIN(showtimes.base_price) as min_price, MAX(showtimes.base_price) as max_price')
            ->groupBy('rooms.type')
            ->orderBy('min_price')
            ->get();

        $cinemaGroups = CinemaRegions::groups($cinemas)->filter(fn ($group) => $group['count'] > 0);

        return view('frontend.cinemas', compact('banners', 'cinemas', 'cities', 'selectedCity', 'selectedRegion', 'regions', 'cinemaGroups', 'cinemaDirectory', 'priceFormats'));
    }
}
