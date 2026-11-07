<?php

namespace App\Console\Commands;

use App\Services\MovieScheduleService;
use Illuminate\Console\Command;

class FillCurrentMovieSchedule extends Command
{
    protected $signature = 'movies:fill-schedule {--days=7 : Số ngày cần bổ sung lịch, từ 1 đến 30}';
    protected $description = 'Bổ sung lịch cho phim đang chiếu và gỡ lịch phim sắp chiếu, giữ nguyên vé đã đặt';

    public function handle(MovieScheduleService $schedules): int
    {
        $days = (int) $this->option('days');
        if ($days < 1 || $days > 30) { $this->error('Số ngày phải từ 1 đến 30.'); return self::FAILURE; }
        $result = $schedules->fill($days);
        $this->info("Đã thêm {$result['created']} suất, cân đối {$result['reassigned']} suất, gỡ {$result['deleted']} suất sắp chiếu, đóng {$result['closed']} suất có vé.");
        if ($result['missing']) $this->warn('Chưa đủ phòng trống cho '.count($result['missing']).' lịch phim / rạp / ngày.');
        return $result['missing'] ? self::FAILURE : self::SUCCESS;
    }
}
