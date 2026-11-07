<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MovieScheduleService
{
    public function removeUpcoming(?Movie $movie = null): array
    {
        $query = Showtime::whereHas('movie', fn ($q) => $q->where('status', 'upcoming'))
            ->when($movie, fn ($q) => $q->where('movie_id', $movie->id));
        $closed = (clone $query)->has('tickets')->where('status', '!=', 'cancelled')->update(['status' => 'cancelled']);
        $deleted = (clone $query)->doesntHave('tickets')->delete();
        return compact('closed', 'deleted');
    }

    public function fill(int $days = 7): array
    {
        return DB::transaction(function () use ($days) {
            $result = $this->removeUpcoming();
            $result['created'] = 0;
            $result['missing'] = [];
            $now = now();
            $rooms = Room::where('status', 'active')->whereHas('cinema', fn ($q) => $q->where('status', 'active'))
                ->whereHas('seats', fn ($q) => $q->where('status', true))
                ->orderBy('id')->lockForUpdate()->get();
            $byCinema = $rooms->groupBy('cinema_id');
            $existing = Showtime::whereIn('room_id', $rooms->pluck('id'))->where('status', 'active')
                ->where('end_time', '>', $now->copy()->startOfDay())
                ->where('start_time', '<=', $now->copy()->addDays($days - 1)->endOfDay())->get();
            $occupied = $existing->groupBy('room_id')->map(fn ($items) => $items->map(fn ($show) =>
                ['start'=>$show->start_time->timestamp, 'end'=>$show->end_time->timestamp])->all())->all();
            $roomLookup = $rooms->keyBy('id');
            $coverage = [];
            foreach ($existing as $show) {
                if ($show->start_time->gt($now)) {
                    $cinema = $roomLookup[$show->room_id]->cinema_id;
                    $coverage[$show->movie_id.'|'.$cinema.'|'.$show->start_time->toDateString()] = true;
                }
            }
            foreach (Movie::where('status', 'now_showing')->orderBy('id')->get() as $movie) {
                foreach ($byCinema as $cinemaId => $cinemaRooms) {
                    $preferred = $cinemaRooms->sortBy(fn ($room) => ($movie->is_imax && strtoupper($room->type) === 'IMAX') || (!$movie->is_imax && strtoupper($room->type) === '2D') ? 0 : 1);
                    for ($day = 0; $day < $days; $day++) {
                        $date = $now->copy()->addDays($day);
                        $dayStart = $date->copy()->startOfDay()->timestamp;
                        $dayEnd = $date->copy()->setTime(23,59)->timestamp;
                        $key = $movie->id.'|'.$cinemaId.'|'.$date->toDateString();
                        if (isset($coverage[$key])) continue;
                        $created = false;
                        foreach ($preferred as $room) {
                            for ($minute = 10 * 60; $minute <= 21 * 60; $minute += 30) {
                                $startStamp = $dayStart + $minute * 60;
                                if ($startStamp <= $now->timestamp + 1800) continue;
                                $endStamp = $startStamp + max(30,(int)$movie->duration) * 60;
                                if ($endStamp > $dayEnd) continue;
                                $conflict = false;
                                foreach ($occupied[$room->id] ?? [] as $slot) {
                                    if ($slot['start'] < $endStamp + 900 && $slot['end'] > $startStamp - 900) { $conflict=true; break; }
                                }
                                if ($conflict) continue;
                                $start = Carbon::createFromTimestamp($startStamp, $now->timezone);
                                $end = Carbon::createFromTimestamp($endStamp, $now->timezone);
                                $price = match(strtoupper($room->type)) { 'IMAX' => 110000, 'VIP' => 130000, '3D' => 100000, default => 80000 };
                                $show = Showtime::create(['movie_id'=>$movie->id, 'room_id'=>$room->id, 'start_time'=>$start,
                                    'end_time'=>$end, 'base_price'=>$price, 'status'=>'active', 'source'=>'local_schedule']);
                                $occupied[$room->id][] = ['start'=>$startStamp, 'end'=>$endStamp];
                                $coverage[$key] = true;
                                $result['created']++;
                                $created = true;
                                break 2;
                            }
                        }
                        if (!$created) $result['missing'][] = $key;
                    }
                }
            }
            // When some cinemas are full, give every movie a daily slot across
            // the system by sharing unused slots created by this generator.
            $result['full_cinema_days'] = count($result['missing']);
            $result['missing'] = [];
            $result['reassigned'] = 0;
            $movies = Movie::where('status','now_showing')->orderBy('id')->get();
            for ($day=0; $day<$days; $day++) {
                $date = $now->copy()->addDays($day);
                $daily = Showtime::whereIn('room_id',$rooms->pluck('id'))->where('status','active')
                    ->whereBetween('start_time',[$date->copy()->startOfDay(),$date->copy()->endOfDay()])
                    ->where('start_time','>',$now)->withCount('tickets')->get();
                $counts = $daily->countBy('movie_id')->all();
                foreach ($movies as $movie) {
                    if (($counts[$movie->id] ?? 0)>0) continue;
                    $candidate = $daily->first(fn ($show) => $show->source === 'local_schedule' && $show->tickets_count===0
                        && ($counts[$show->movie_id] ?? 0)>1 && $show->end_time->timestamp-$show->start_time->timestamp >= max(30,(int)$movie->duration)*60);
                    if (!$candidate) { $result['missing'][]=$movie->id.'|'.$date->toDateString(); continue; }
                    $counts[$candidate->movie_id]--;
                    $candidate->update(['movie_id'=>$movie->id,'end_time'=>$candidate->start_time->copy()->addMinutes(max(30,(int)$movie->duration))]);
                    $counts[$movie->id]=1;
                    $result['reassigned']++;
                }
            }
            return $result;
        });
    }
}
