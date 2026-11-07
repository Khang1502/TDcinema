<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GenerateShowtimesThrough extends Command
{
    protected $signature = 'cinema:generate-showtimes
        {--from= : Ngày bắt đầu (YYYY-MM-DD), mặc định là ngày mai}
        {--to=2027-12-31 : Ngày kết thúc (YYYY-MM-DD)}
        {--shows-per-room=4 : Số suất mỗi phòng mỗi ngày, từ 1 đến 4}';

    protected $description = 'Tạo lịch chiếu cân bằng cho toàn bộ phim và phòng đang hoạt động';

    private const START_TIMES = ['09:00', '12:45', '16:30', '20:15'];

    public function handle(): int
    {
        try {
            $from = Carbon::createFromFormat('Y-m-d', $this->option('from') ?: now()->addDay()->toDateString())->startOfDay();
            $to = Carbon::createFromFormat('Y-m-d', (string) $this->option('to'))->startOfDay();
        } catch (InvalidArgumentException) {
            $this->error('Ngày phải có định dạng YYYY-MM-DD.');
            return self::FAILURE;
        }

        $showsPerRoom = (int) $this->option('shows-per-room');
        if ($from->gt($to) || $showsPerRoom < 1 || $showsPerRoom > count(self::START_TIMES)) {
            $this->error('Khoảng ngày hoặc số suất mỗi phòng không hợp lệ.');
            return self::FAILURE;
        }

        $movies = Movie::orderBy('id')->get(['id', 'title', 'duration']);
        $rooms = Room::where('status', 'active')->orderBy('cinema_id')->orderBy('id')->get(['id', 'cinema_id', 'type']);

        if ($movies->isEmpty() || $rooms->isEmpty()) {
            $this->error('Cần có ít nhất một phim và một phòng đang hoạt động.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Tạo lịch từ %s đến %s cho %d phim, %d phòng (%d suất/phòng/ngày).',
            $from->format('d/m/Y'),
            $to->format('d/m/Y'),
            $movies->count(),
            $rooms->count(),
            $showsPerRoom,
        ));

        $inserted = 0;
        $skipped = 0;
        $dayOffset = 0;
        $progress = $this->output->createProgressBar($from->diffInDays($to) + 1);
        $progress->start();

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();
            $existing = DB::table('showtimes')
                ->whereBetween('start_time', [$dayStart, $dayEnd])
                ->get(['room_id', 'start_time'])
                ->mapWithKeys(fn ($row) => [
                    $row->room_id.'|'.Carbon::parse($row->start_time)->format('Y-m-d H:i:s') => true,
                ]);

            $rows = [];
            foreach ($rooms as $roomIndex => $room) {
                foreach (array_slice(self::START_TIMES, 0, $showsPerRoom) as $slotIndex => $clock) {
                    $start = Carbon::parse($date->toDateString().' '.$clock);
                    $key = $room->id.'|'.$start->format('Y-m-d H:i:s');
                    if ($existing->has($key)) {
                        $skipped++;
                        continue;
                    }

                    // Hai hệ số nguyên tố giúp phim được rải đều giữa ngày, phòng và khung giờ.
                    $movieIndex = ($dayOffset * 7 + $roomIndex * 3 + $slotIndex) % $movies->count();
                    $movie = $movies[$movieIndex];
                    $end = $start->copy()->addMinutes((int) $movie->duration);

                    if (! $end->isSameDay($start)) {
                        $skipped++;
                        continue;
                    }

                    $rows[] = [
                        'movie_id' => $movie->id,
                        'room_id' => $room->id,
                        'start_time' => $start->format('Y-m-d H:i:s'),
                        'end_time' => $end->format('Y-m-d H:i:s'),
                        'base_price' => $this->priceFor($room->type, $start),
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('showtimes')->insert($chunk);
                $inserted += count($chunk);
            }

            $dayOffset++;
            $progress->advance();
        }

        $progress->finish();
        $this->newLine(2);
        $this->info(number_format($inserted).' suất đã tạo; '.number_format($skipped).' suất trùng/không hợp lệ được bỏ qua.');

        return self::SUCCESS;
    }

    private function priceFor(string $roomType, Carbon $start): int
    {
        $formatSurcharge = match (strtoupper($roomType)) {
            '3D' => 20_000,
            'IMAX' => 40_000,
            'VIP' => 30_000,
            default => 0,
        };
        $weekendSurcharge = $start->isWeekend() ? 10_000 : 0;
        $eveningSurcharge = $start->hour >= 18 ? 10_000 : 0;

        return 70_000 + $formatSurcharge + $weekendSurcharge + $eveningSurcharge;
    }
}
