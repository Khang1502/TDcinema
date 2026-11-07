<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Movie;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Showtime;
use App\Models\Ticket;
use Carbon\Carbon;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Box\Spout\Common\Entity\Style\Color;

class DashboardController extends Controller
{
    public function index(Request $request, \App\Services\RevenueReportService $reports)
    {
        $data = $request->validate([
            'range' => ['nullable', 'in:day,month,year'],
            'cinema_id' => ['nullable', 'integer', 'exists:cinemas,id'],
        ]);
        $range = $data['range'] ?? 'month';
        $cinemaId = $data['cinema_id'] ?? null;
        $today = Carbon::today();
        [$from, $previousFrom, $previousTo] = $this->period($range, $today);
        $filters = ['from' => $from->toDateString(), 'to' => $today->toDateString(), 'movie_id' => null, 'cinema_id' => $cinemaId];
        $user = $request->user();
        $canReport = $user->hasAdminPermission('reports.view');
        $stats = $chart = null;
        $topMovies = $topCinemas = $paymentBreakdown = collect();
        if ($canReport) {
            $payments = $reports->payments($filters);
            $stats = $this->summary(clone $payments);
            $previousFilters = array_replace($filters, ['from' => $previousFrom->toDateString(), 'to' => $previousTo->toDateString()]);
            $previous = $this->summary($reports->payments($previousFilters));
            foreach (['revenue', 'bookings', 'active_users', 'success_rate'] as $key) {
                $stats[$key.'_previous'] = $previous[$key];
                $stats[$key.'_change'] = $key === 'success_rate'
                    ? ($previous['transactions'] ? ($stats[$key] - $previous[$key]) * 100 : null)
                    : ($previous[$key] > 0 ? ($stats[$key] - $previous[$key]) / $previous[$key] * 100 : null);
            }
            $chart = $this->chart(clone $payments, $range, $from, $today);
            $topMovies = (clone $payments)->where('payments.status', 'paid')
                ->select('showtimes.movie_id as id', 'movies.title', 'movies.status')
                ->selectRaw('SUM(payments.amount) as revenue, COUNT(DISTINCT CASE WHEN tickets.status IN (\'confirmed\',\'used\') THEN tickets.id END) as bookings')
                ->groupBy('showtimes.movie_id', 'movies.title', 'movies.status')
                ->orderByDesc('revenue')->orderBy('movies.title')->limit(5)->get();
            $posters = Movie::whereIn('id', $topMovies->pluck('id')->filter())->get(['id','poster_path'])->keyBy('id');
            $topMovies->each(fn ($row) => $row->poster = isset($posters[$row->id]) ? $posters[$row->id]->posterUrl() : null);
            $topCinemas = (clone $payments)->where('payments.status', 'paid')
                ->select('rooms.cinema_id as id', 'cinemas.name', 'cinemas.city')
                ->selectRaw('SUM(payments.amount) as revenue, COUNT(DISTINCT CASE WHEN tickets.status IN (\'confirmed\',\'used\') THEN tickets.id END) as bookings')
                ->groupBy('rooms.cinema_id', 'cinemas.name', 'cinemas.city')
                ->orderByDesc('revenue')->orderBy('cinemas.name')->limit(5)->get();
            $paymentBreakdown = (clone $payments)->where('payments.status', 'paid')
                ->select('payments.method')->selectRaw('COUNT(*) as count, SUM(payments.amount) as total')
                ->groupBy('payments.method')->orderByDesc('total')->get();
        }

        $operations = [];
        $nextShows = collect();
        $showtimes = Showtime::where('status', 'active')
            ->when($cinemaId, fn ($query) => $query->whereHas('room', fn ($room) => $room->where('cinema_id', $cinemaId)));
        if ($user->hasAdminPermission('showtimes.manage')) {
            $operations['shows_today'] = (clone $showtimes)->whereBetween('start_time', [$today, $today->copy()->endOfDay()])->count();
            $operations['shows_week'] = (clone $showtimes)->whereBetween('start_time', [$today, $today->copy()->addDays(6)->endOfDay()])->count();
            $operations['schedule_until'] = (clone $showtimes)->where('start_time', '>=', $today)->max('start_time');
            $nextShows = (clone $showtimes)->with(['movie:id,title', 'room:id,cinema_id,name', 'room.cinema:id,name'])
                ->whereBetween('start_time', [now(), $today->copy()->endOfDay()])->orderBy('start_time')->orderBy('id')->limit(4)->get();
        }
        if ($user->hasAdminPermission('tickets.manage')) {
            $tickets = Ticket::whereHas('showtime', fn ($query) => $query->where('status','active')->whereBetween('start_time', [$today, $today->copy()->endOfDay()])
                ->when($cinemaId, fn ($show) => $show->whereHas('room', fn ($room) => $room->where('cinema_id', $cinemaId))))
                ->whereHas('payment', fn ($query) => $query->where('status', 'paid'));
            $operations['ready'] = (clone $tickets)->where('status', 'confirmed')->count();
            $operations['used'] = (clone $tickets)->where('status', 'used')->count();
        }
        if ($user->hasAdminPermission('movies.manage')) {
            $operations['movies'] = Movie::where('status','now_showing')->when($cinemaId, fn ($query) => $query->whereHas('showtimes',
                fn ($show) => $show->where('status','active')->where('start_time','>=',$today)->whereHas('room',fn ($room) => $room->where('cinema_id',$cinemaId))))->count();
        }
        if ($user->hasAdminPermission('cinemas.manage')) {
            $operations['cinemas'] = Cinema::where('status','active')->when($cinemaId,fn ($query) => $query->whereKey($cinemaId))->count();
        }
        if ($user->hasAdminPermission('rooms.manage')) {
            $operations['rooms'] = Room::where('status','active')->when($cinemaId,fn ($query) => $query->where('cinema_id',$cinemaId))->count();
        }

        $quickLinks = collect([
            ['permission'=>'tickets.manage','route'=>'admin.tickets.index','label'=>'Vé & check-in','hint'=>'Tra cứu và đón khách','icon'=>'ticket-check'],
            ['permission'=>'showtimes.manage','route'=>'admin.showtimes.create','label'=>'Tạo suất chiếu','hint'=>'Bổ sung lịch mới','icon'=>'calendar-clock'],
            ['permission'=>'movies.manage','route'=>'admin.movies.index','label'=>'Quản lý phim','hint'=>'Nội dung và lịch chiếu','icon'=>'clapperboard'],
            ['permission'=>'cinemas.manage','route'=>'admin.cinemas.index','label'=>'Hệ thống rạp','hint'=>'Rạp và phòng chiếu','icon'=>'building-2'],
            ['permission'=>'membership.manage','route'=>'admin.membership.index','label'=>'Thành viên','hint'=>'Cấp bậc và quyền lợi','icon'=>'crown'],
            ['permission'=>'promotions.manage','route'=>'admin.promotions.index','label'=>'Khuyến mãi','hint'=>'Mã ưu đãi và banner','icon'=>'tags'],
            ['permission'=>'foods.manage','route'=>'admin.foods.index','label'=>'Đồ ăn & thức uống','hint'=>'Sản phẩm và combo','icon'=>'popcorn'],
            ['permission'=>'header.manage','route'=>'admin.header.edit','label'=>'Giao diện website','hint'=>'Thương hiệu và menu','icon'=>'panel-top'],
        ])->filter(fn ($link) => $user->hasAdminPermission($link['permission']))->values();
        return view('admin.dashboard.index', compact('stats','chart','range','cinemaId','canReport','operations','nextShows','quickLinks','topMovies','topCinemas','paymentBreakdown','filters') + [
            'cinemas' => Cinema::orderBy('name')->get(['id','name']),
            'periodLabel' => ['day'=>'Hôm nay','month'=>'Tháng này','year'=>'Năm nay'][$range],
            'comparisonLabel' => $previousFrom->format('d/m/Y').' – '.$previousTo->format('d/m/Y'),
        ]);
    }

    private function period(string $range, Carbon $today): array
    {
        return match ($range) {
            'day' => [$today->copy(), $today->copy()->subDay(), $today->copy()->subDay()],
            'year' => [$today->copy()->startOfYear(), $today->copy()->subYearNoOverflow()->startOfYear(), $today->copy()->subYearNoOverflow()],
            default => [$today->copy()->startOfMonth(), $today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()],
        };
    }

    private function summary(\Illuminate\Database\Query\Builder $payments): array
    {
        $row = (array) $payments->selectRaw("COALESCE(SUM(CASE WHEN payments.status = 'paid' THEN payments.amount ELSE 0 END),0) as revenue,
            COUNT(DISTINCT CASE WHEN payments.status = 'paid' AND tickets.status IN ('confirmed','used') THEN tickets.id END) as bookings,
            COUNT(DISTINCT CASE WHEN payments.status = 'paid' AND tickets.status IN ('confirmed','used') THEN tickets.user_id END) as active_users,
            SUM(CASE WHEN payments.status = 'paid' THEN 1 ELSE 0 END) as paid_transactions, COUNT(*) as transactions")->first();
        $stats = array_map(fn ($value) => (float) ($value ?? 0), $row);
        $stats['success_rate'] = $stats['transactions'] ? $stats['paid_transactions'] / $stats['transactions'] : 0;
        return $stats;
    }

    private function bucket(string $range, string $column): string
    {
        return match ($range) {
            'day' => DB::connection()->getDriverName() === 'sqlite' ? "strftime('%H', {$column})" : "DATE_FORMAT({$column}, '%H')",
            'year' => "SUBSTR({$column}, 1, 7)",
            default => "DATE({$column})",
        };
    }

    private function chart(\Illuminate\Database\Query\Builder $payments, string $range, Carbon $from, Carbon $today): array
    {
        $bucket = $this->bucket($range, 'COALESCE(payments.paid_at,payments.created_at)');
        $revenue = (clone $payments)->where('payments.status','paid')->selectRaw($bucket.' as bucket, SUM(payments.amount) as total')->groupBy('bucket')->pluck('total','bucket');
        // Count each ticket once, even when it has multiple successful payment records.
        $ticketPayments = (clone $payments)->where('payments.status','paid')->whereIn('tickets.status',['confirmed','used'])
            ->select('tickets.id')->selectRaw('MIN(COALESCE(payments.paid_at,payments.created_at)) as recorded_at')->groupBy('tickets.id');
        $bookings = DB::query()->fromSub($ticketPayments,'paid_tickets')->selectRaw($this->bucket($range,'recorded_at').' as bucket, COUNT(*) as total')->groupBy('bucket')->pluck('total','bucket');
        $count = $range === 'day' ? 24 : ($range === 'year' ? $today->month : $today->day);
        $points = [];
        for ($i = 0; $i < $count; $i++) {
            $date = $range === 'day' ? $from->copy()->addHours($i) : ($range === 'year' ? $from->copy()->addMonths($i) : $from->copy()->addDays($i));
            $key = $date->format($range === 'day' ? 'H' : ($range === 'year' ? 'Y-m' : 'Y-m-d'));
            $points[] = ['label'=>$range === 'year' ? 'T'.$date->month : $date->format($range === 'day' ? 'H\h' : 'd/m'),
                'revenue'=>(float)($revenue[$key] ?? 0), 'bookings'=>(int)($bookings[$key] ?? 0)];
        }
        return $points;
    }

    /**
     * Export quick CSV for current dashboard stats.
     */
    public function export(Request $request)
    {
        $today = Carbon::today();
        $month = (int) $request->input('month', $today->month);
        $year = (int) $request->input('year', $today->year);
        $cinemaId = $request->input('cinema_id');

        $from = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $to = $from->copy()->endOfMonth();

        $revenueQuery = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$from, $to])
            ->when($cinemaId, function ($q) use ($cinemaId) {
                $q->whereHas('ticket.showtime.room.cinema', fn ($qq) => $qq->where('id', $cinemaId));
            });

        $ticketQuery = Ticket::whereBetween('created_at', [$from, $to]);

        $revenueTotal = (clone $revenueQuery)->sum('amount');
        $bookingsTotal = (clone $ticketQuery)->count();
        $paidBookings = (clone $ticketQuery)
            ->whereIn('status', ['confirmed', 'used'])
            ->whereHas('payment', fn ($q) => $q->where('status', 'paid'))
            ->when($cinemaId, fn ($q) => $q->whereHas('showtime.room.cinema', fn ($qq) => $qq->where('id', $cinemaId)))
            ->count();

        $activeWindow = $today->copy()->subDays(30);
        $activeUsers = Ticket::where('created_at', '>=', $activeWindow)
            ->when($cinemaId, fn ($q) => $q->whereHas('showtime.room.cinema', fn ($qq) => $qq->where('id', $cinemaId)))
            ->whereHas('payment', fn ($q) => $q->where('status', 'paid'))
            ->distinct('user_id')
            ->count('user_id');

        // Xuất XLSX bằng Spout (ghi ra file tạm rồi download)
        $tmpPath = storage_path('app/dashboard_report.xlsx');
        $writer = WriterEntityFactory::createXLSXWriter();
        $writer->openToFile($tmpPath);

        $headerStyle = (new StyleBuilder())
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor(Color::rgb(226, 232, 240)) // #e2e8f0
            ->build();

        $titleStyle = (new StyleBuilder())
            ->setFontBold()
            ->setFontSize(14)
            ->setBackgroundColor(Color::WHITE)
            ->build();

        $sectionStyle = (new StyleBuilder())
            ->setFontBold()
            ->setBackgroundColor(Color::rgb(248, 250, 252)) // #f8fafc
            ->build();

        $textStyle = (new StyleBuilder())
            ->setBackgroundColor(Color::WHITE)
            ->build();
        $numberStyle = (new StyleBuilder())
            ->setFormat('#,##0')
            ->setBackgroundColor(Color::WHITE)
            ->build();
        $currencyStyle = (new StyleBuilder())
            ->setFormat('#,##0')
            ->setBackgroundColor(Color::WHITE)
            ->build();

        $rangeLabel = sprintf('Tháng %02d/%d', $month, $year);
        $cinemaLabel = $cinemaId
            ? optional(Cinema::find($cinemaId))->name ?? 'Rạp không xác định'
            : 'Tất cả rạp';

        // Tiêu đề + thông tin bộ lọc
        $writer->addRow(WriterEntityFactory::createRowFromArray(['BÁO CÁO DOANH THU & ĐẶT VÉ'], $titleStyle));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Kỳ báo cáo', $rangeLabel], $sectionStyle));
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Rạp', $cinemaLabel], $sectionStyle));
        $writer->addRow(WriterEntityFactory::createRowFromArray([''], $textStyle));

        // Header
        $writer->addRow(WriterEntityFactory::createRowFromArray(['Chỉ số', 'Giá trị'], $headerStyle));

        // Dữ liệu chính
        $rows = [
            ['Tổng doanh thu (VND)', $revenueTotal],
            ['Số vé tạo trong kỳ', $bookingsTotal],
            ['Số vé đã thanh toán (confirmed/used, có payment paid)', $paidBookings],
            ['Người dùng hoạt động 30 ngày (có vé paid)', $activeUsers],
        ];

        foreach ($rows as $row) {
            $cells = [
                WriterEntityFactory::createCell($row[0], $textStyle),
                WriterEntityFactory::createCell((float) $row[1], is_numeric($row[1]) ? $numberStyle : $textStyle),
            ];
            $writer->addRow(WriterEntityFactory::createRow($cells));
        }

        // Phân bố phương thức thanh toán
        $paymentBreakdown = (clone $revenueQuery)
            ->selectRaw('method, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('method')
            ->get();

        if ($paymentBreakdown->count()) {
            $writer->addRow(WriterEntityFactory::createRowFromArray([''], $textStyle));
            $writer->addRow(WriterEntityFactory::createRowFromArray(['Phân bố phương thức thanh toán'], $sectionStyle));
            $writer->addRow(WriterEntityFactory::createRowFromArray(['Phương thức', 'Số giao dịch', 'Tổng tiền (VND)'], $headerStyle));
            foreach ($paymentBreakdown as $row) {
                $writer->addRow(WriterEntityFactory::createRow([
                    WriterEntityFactory::createCell($row->method ?? 'N/A', $textStyle),
                    WriterEntityFactory::createCell((int) $row->cnt, $numberStyle),
                    WriterEntityFactory::createCell((float) $row->total, $currencyStyle),
                ]));
            }
        }

        $writer->close();

        return response()->download($tmpPath, 'dashboard_report.xlsx')->deleteFileAfterSend(true);
    }
}
