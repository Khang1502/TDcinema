<?php

namespace App\Console\Commands;

use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Room;
use App\Models\Showtime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SyncGalaxyShowtimes extends Command
{
    protected $signature = 'movies:sync-galaxy-showtimes
        {--days=7 : Số ngày lịch chiếu cần đồng bộ, từ 1 đến 30}
        {--max-per-day=6 : Số suất tối đa của một phim tại một rạp mỗi ngày}
        {--dry-run : Chỉ tính toán, không ghi dữ liệu}';

    protected $description = 'Đồng bộ giờ chiếu Galaxy vào các rạp và phòng tương ứng của TD Cinema';

    private const SOURCE = 'galaxycine';
    private const API = 'https://www.galaxycine.vn/api';
    private const CLIENT_ID = '3da4eba4-94dd-4e74-bc25-38e89a01fe07';
    private const CLEANING_MINUTES = 15;

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1 || $days > 30) {
            $this->error('Số ngày phải nằm trong khoảng từ 1 đến 30.');
            return self::FAILURE;
        }
        $maxPerDay = (int) $this->option('max-per-day');
        if ($maxPerDay < 1 || $maxPerDay > 12) {
            $this->error('Số suất tối đa mỗi ngày phải nằm trong khoảng từ 1 đến 12.');
            return self::FAILURE;
        }

        $timezone = 'Asia/Ho_Chi_Minh';
        $from = Carbon::now($timezone);
        $to = $from->copy()->addDays($days - 1)->endOfDay();
        $movies = Movie::query()
            ->where('status', 'now_showing')
            ->where('source', self::SOURCE)
            ->whereNotNull('external_id')
            ->orderBy('release_date')
            ->get();

        if ($movies->isEmpty()) {
            $this->error('Chưa có phim Galaxy. Hãy chạy movies:sync-galaxy trước.');
            return self::FAILURE;
        }

        $sessions = collect();
        $failedMovies = [];
        $progress = $this->output->createProgressBar($movies->count());
        $progress->start();

        foreach ($movies as $movie) {
            try {
                $items = $this->fetchSessions((string) $movie->external_id);
                foreach ($items as $session) {
                    $start = $this->sessionStart($session, $timezone);
                    if (!$start || $start->lt($from) || $start->gt($to)) {
                        continue;
                    }
                    if (empty($session['id']) || empty($session['cinema']['id'])) {
                        continue;
                    }
                    $sessions->push([
                        'movie' => $movie,
                        'session' => $session,
                        'start' => $start,
                    ]);
                }
            } catch (Throwable $exception) {
                $failedMovies[] = $movie->title;
            }
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);

        $cinemas = Cinema::query()
            ->where('status', 'active')
            ->with(['rooms' => fn ($query) => $query->where('status', 'active')->orderBy('id')])
            ->get()
            ->filter(fn (Cinema $cinema) => $cinema->rooms->isNotEmpty());

        $sourceByCity = $this->sourceCinemasByCity($sessions);
        if ($sessions->isEmpty()) {
            $this->error('Galaxy chưa có lịch chiếu trong khoảng ngày đã chọn.');
            return self::FAILURE;
        }

        $roomIds = $cinemas->flatMap->rooms->pluck('id');
        $protectedShowtimes = Showtime::query()
            ->where('source', self::SOURCE)
            ->whereBetween('start_time', [$from, $to])
            ->whereHas('tickets')
            ->get(['movie_id', 'external_id']);
        $protectedExternalIds = $protectedShowtimes->pluck('external_id')->filter()->all();
        $protectedMovieIds = $protectedShowtimes->pluck('movie_id')->unique()->all();

        $availability = $this->availability($roomIds, $from, $to);
        $rows = [];
        $skippedConflicts = 0;
        $usedCinemaIds = [];
        $sessionsByMovie = $sessions->groupBy(fn (array $item) => $item['movie']->id);

        foreach ($cinemas as $cinema) {
            $city = $this->cityKey($cinema->city.' '.$cinema->name);

            foreach ($sessionsByMovie as $movieSessions) {
                $allSourceCinemaIds = $movieSessions
                    ->pluck('session.cinema.id')
                    ->map(fn ($id) => (string) $id)
                    ->unique()
                    ->values();
                $sourceCinemaIds = $city && $sourceByCity->has($city)
                    ? $movieSessions
                        ->filter(fn (array $item) => $this->cityKey(
                            data_get($item, 'session.cinema.name', '').' '.data_get($item, 'session.cinema.address', '')
                        ) === $city)
                        ->pluck('session.cinema.id')
                        ->map(fn ($id) => (string) $id)
                        ->unique()
                        ->values()
                    : collect();
                if ($sourceCinemaIds->isEmpty()) {
                    $sourceCinemaIds = $allSourceCinemaIds;
                }

                $sourceIndex = abs(crc32($cinema->id.'|'.$movieSessions->first()['movie']->external_id)) % $sourceCinemaIds->count();
                $sourceCinemaId = $sourceCinemaIds[$sourceIndex];
                $cinemaSessions = $movieSessions
                    ->filter(fn (array $item) => (string) data_get($item, 'session.cinema.id') === $sourceCinemaId)
                    ->sortBy(fn (array $item) => $item['start']->timestamp);
                $dailyCount = [];

                foreach ($cinemaSessions as $item) {
                    $session = $item['session'];
                    $movie = $item['movie'];
                    $dateKey = $item['start']->toDateString();
                    if (($dailyCount[$dateKey] ?? 0) >= $maxPerDay) {
                        continue;
                    }
                    $externalId = (string) $session['id'].'@'.$cinema->id;
                    if (in_array($externalId, $protectedExternalIds, true)) {
                        continue;
                    }

                    $slot = $this->nearestAvailableSlot(
                        $cinema->rooms,
                        $session,
                        $item['start'],
                        max(1, (int) $movie->duration),
                        $availability,
                        $from,
                        $to,
                    );
                    if (!$slot) {
                        $skippedConflicts++;
                        continue;
                    }
                    [$room, $start, $end] = $slot;

                    $rows[] = $this->showtimeRow($movie, $room, $start, $end, $externalId);
                    $availability[$room->id][] = [$start, $end, $externalId];
                    $dailyCount[$dateKey] = ($dailyCount[$dateKey] ?? 0) + 1;
                    $usedCinemaIds[$cinema->id] = true;
                }
            }
        }

        // Nếu lịch của một phim chỉ xuất hiện ở cụm rạp nguồn khác, vẫn bảo đảm
        // phim đó có ít nhất một suất tại rạp TD còn phòng phù hợp.
        $scheduledMovieIds = array_unique(array_merge(array_column($rows, 'movie_id'), $protectedMovieIds));
        foreach ($sessionsByMovie as $movieId => $movieSessions) {
            if (in_array((int) $movieId, $scheduledMovieIds, true)) {
                continue;
            }
            foreach ($movieSessions->sortBy(fn (array $item) => $item['start']->timestamp) as $item) {
                $sourceCity = $this->cityKey(
                    data_get($item, 'session.cinema.name', '').' '.data_get($item, 'session.cinema.address', '')
                );
                $candidateCinemas = $cinemas
                    ->filter(fn (Cinema $cinema) => $this->cityKey($cinema->city.' '.$cinema->name) === $sourceCity)
                    ->values();
                if ($candidateCinemas->isEmpty()) {
                    $candidateCinemas = $cinemas->values();
                }
                foreach ($candidateCinemas as $cinema) {
                    $slot = $this->nearestAvailableSlot(
                        $cinema->rooms,
                        $item['session'],
                        $item['start'],
                        max(1, (int) $item['movie']->duration),
                        $availability,
                        $from,
                        $to,
                    );
                    if (!$slot) {
                        continue;
                    }
                    [$room, $start, $end] = $slot;
                    $externalId = (string) $item['session']['id'].'@'.$cinema->id;
                    $rows[] = $this->showtimeRow($item['movie'], $room, $start, $end, $externalId);
                    $availability[$room->id][] = [$start, $end, $externalId];
                    $scheduledMovieIds[] = (int) $movieId;
                    $usedCinemaIds[$cinema->id] = true;
                    break 2;
                }
            }
        }

        if (!$this->option('dry-run')) {
            DB::transaction(function () use ($from, $to, $protectedExternalIds, $rows) {
                Showtime::query()
                    ->where('source', self::SOURCE)
                    ->whereBetween('start_time', [$from, $to])
                    ->when($protectedExternalIds !== [], fn ($query) => $query->whereNotIn('external_id', $protectedExternalIds))
                    ->whereDoesntHave('tickets')
                    ->delete();

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('showtimes')->insert($chunk);
                }
            });
        }

        $mode = $this->option('dry-run') ? 'Có thể tạo' : 'Đã tạo';
        $this->info(sprintf(
            '%s %s suất chiếu trong %d ngày tại %d rạp TD Cinema; bỏ qua %s suất do trùng phòng.',
            $mode,
            number_format(count($rows)),
            $days,
            count($usedCinemaIds),
            number_format($skippedConflicts),
        ));
        if ($failedMovies !== []) {
            $this->warn('Không tải được lịch của '.count($failedMovies).' phim: '.implode(', ', $failedMovies));
        }

        return self::SUCCESS;
    }

    private function sourceCinemasByCity(Collection $sessions): Collection
    {
        return $sessions
            ->map(fn (array $item) => $item['session']['cinema'])
            ->unique('id')
            ->map(fn (array $cinema) => [
                'id' => (string) $cinema['id'],
                'city' => $this->cityKey(($cinema['name'] ?? '').' '.($cinema['address'] ?? '')),
            ])
            ->filter(fn (array $cinema) => $cinema['city'] !== null)
            ->groupBy('city');
    }

    private function showtimeRow(Movie $movie, Room $room, Carbon $start, Carbon $end, string $externalId): array
    {
        return [
            'movie_id' => $movie->id,
            'room_id' => $room->id,
            'start_time' => $start->format('Y-m-d H:i:s'),
            'end_time' => $end->format('Y-m-d H:i:s'),
            'base_price' => $this->priceFor($room->type, $start),
            'status' => 'active',
            'source' => self::SOURCE,
            'external_id' => $externalId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function availability(Collection $roomIds, Carbon $from, Carbon $to): array
    {
        $availability = [];
        foreach ($roomIds as $roomId) {
            $availability[$roomId] = [];
        }

        Showtime::query()
            ->whereIn('room_id', $roomIds)
            ->where('start_time', '<=', $to)
            ->where('end_time', '>=', $from)
            ->where(function ($query) {
                $query->whereNull('source')
                    ->orWhere('source', '!=', self::SOURCE)
                    ->orWhereHas('tickets');
            })
            ->get(['room_id', 'start_time', 'end_time', 'external_id'])
            ->each(function (Showtime $showtime) use (&$availability) {
                $availability[$showtime->room_id][] = [
                    Carbon::parse($showtime->start_time),
                    Carbon::parse($showtime->end_time),
                    $showtime->external_id,
                ];
            });

        return $availability;
    }

    private function availableRoom(Collection $rooms, array $session, Carbon $start, Carbon $end, array $availability): ?Room
    {
        $preferredType = $this->roomType($session);
        $preferred = $rooms->filter(fn (Room $room) => strtoupper($room->type) === $preferredType)->values();
        $candidates = $preferred->isNotEmpty() ? $preferred : $rooms->values();
        $offset = abs(crc32((string) ($session['screenName'] ?? $session['id']))) % $candidates->count();

        for ($index = 0; $index < $candidates->count(); $index++) {
            $room = $candidates[($index + $offset) % $candidates->count()];
            $hasConflict = collect($availability[$room->id] ?? [])->contains(function (array $slot) use ($start, $end) {
                [$existingStart, $existingEnd] = $slot;
                return $start->lt($existingEnd->copy()->addMinutes(self::CLEANING_MINUTES))
                    && $end->copy()->addMinutes(self::CLEANING_MINUTES)->gt($existingStart);
            });
            if (!$hasConflict) {
                return $room;
            }
        }

        return null;
    }

    /**
     * Tìm phòng ở đúng giờ Galaxy trước, sau đó dịch tối đa 6 giờ theo bước
     * 15 phút nhưng vẫn giữ cùng ngày. Cách này chỉ dùng cho suất dự phòng để
     * mọi phim có lịch nguồn đều xuất hiện khi giờ gốc trùng lịch phòng TD.
     *
     * @return array{Room, Carbon, Carbon}|null
     */
    private function nearestAvailableSlot(
        Collection $rooms,
        array $session,
        Carbon $desiredStart,
        int $duration,
        array $availability,
        Carbon $from,
        Carbon $to,
    ): ?array {
        $offsets = [0];
        for ($minutes = 15; $minutes <= 360; $minutes += 15) {
            $offsets[] = $minutes;
            $offsets[] = -$minutes;
        }

        foreach ($offsets as $offset) {
            $start = $desiredStart->copy()->addMinutes($offset);
            $end = $start->copy()->addMinutes($duration);
            if (!$start->isSameDay($desiredStart) || $start->lt($from) || $end->gt($to)) {
                continue;
            }
            $room = $this->availableRoom($rooms, $session, $start, $end, $availability);
            if ($room) {
                return [$room, $start, $end];
            }
        }

        return null;
    }

    private function roomType(array $session): string
    {
        $format = Str::lower(($session['version'] ?? '').' '.($session['movieFormat'] ?? ''));
        return match (true) {
            str_contains($format, 'imax') => 'IMAX',
            str_contains($format, '3d') => '3D',
            default => '2D',
        };
    }

    private function cityKey(string $value): ?string
    {
        $city = Str::lower(Str::ascii($value));
        return match (true) {
            str_contains($city, 'ha noi') => 'ha-noi',
            str_contains($city, 'da nang') => 'da-nang',
            str_contains($city, 'can tho') => 'can-tho',
            str_contains($city, 'nha trang'), str_contains($city, 'khanh hoa') => 'nha-trang',
            str_contains($city, 'tp.hcm'), str_contains($city, 'tp. hcm'), str_contains($city, 'ho chi minh') => 'ho-chi-minh',
            default => null,
        };
    }

    private function sessionStart(array $session, string $timezone): ?Carbon
    {
        if (empty($session['showDate']) || empty($session['showTime'])) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d H:i', $session['showDate'].' '.$session['showTime'], $timezone);
        } catch (Throwable) {
            return null;
        }
    }

    private function priceFor(string $roomType, Carbon $start): int
    {
        $formatSurcharge = match (strtoupper($roomType)) {
            '3D' => 20_000,
            'IMAX' => 40_000,
            'VIP' => 30_000,
            default => 0,
        };

        return 70_000
            + ($start->isWeekend() ? 10_000 : 0)
            + ($start->hour >= 18 ? 10_000 : 0)
            + $formatSurcharge;
    }

    private function fetchSessions(string $movieId): array
    {
        return $this->request()
            ->get(self::API.'/v2/mobile/sessions2', [
                'includeCinema' => 'true',
                'includeMovie' => 'true',
                'movieId' => $movieId,
            ])
            ->throw()
            ->json('data.result', []);
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout(30)->retry(3, 300);
        if (app()->environment('local', 'testing')) {
            $request = $request->withoutVerifying();
        }

        return $request->withHeaders([
            'Accept' => 'application/json',
            'clientid' => self::CLIENT_ID,
            'platform' => 'website',
            'Referer' => 'https://www.galaxycine.vn/',
            'Origin' => 'https://www.galaxycine.vn',
            'User-Agent' => 'Mozilla/5.0 TD-Cinema showtime sync',
        ]);
    }
}
