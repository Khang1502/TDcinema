<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Box\Spout\Common\Entity\Style\Color;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $range = request('range', 'month'); // day|month|year
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();
        $prevMonthStart = $monthStart->copy()->subMonth();
        $prevMonthEnd = $monthStart->copy()->subDay();
        $paidTicketStatuses = ['confirmed', 'used'];

        // Doanh thu (payment paid)
        $revenueTotal = Payment::where('status', 'paid')->sum('amount');
        $revenueCurrent = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$monthStart, $today->endOfDay()])
            ->sum('amount');
        $revenuePrev = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$prevMonthStart, $prevMonthEnd->endOfDay()])
            ->sum('amount');

        // Vé đã thanh toán (confirmed/used + payment paid)
        $paidTicketsQuery = Ticket::whereIn('status', $paidTicketStatuses)
            ->whereHas('payment', fn ($q) => $q->where('status', 'paid'));

        $bookingsTotal = (clone $paidTicketsQuery)->count();
        $bookingsCurrent = (clone $paidTicketsQuery)
            ->whereBetween('created_at', [$monthStart, $today->endOfDay()])
            ->count();
        $bookingsPrev = (clone $paidTicketsQuery)
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd->endOfDay()])
            ->count();

        // Tổng vé tạo (để tính tỉ lệ thành công)
        $allTicketsCurrent = Ticket::whereBetween('created_at', [$monthStart, $today->endOfDay()])->count();
        $allTicketsPrev = Ticket::whereBetween('created_at', [$prevMonthStart, $prevMonthEnd->endOfDay()])->count();

        // Vé huỷ
        $cancelCurrent = Ticket::where('status', 'cancelled')
            ->whereBetween('created_at', [$monthStart, $today->endOfDay()])
            ->count();
        $cancelPrev = Ticket::where('status', 'cancelled')
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd->endOfDay()])
            ->count();

        // Người dùng hoạt động (đặt vé đã thanh toán trong 30 ngày)
        $activeWindow = $today->copy()->subDays(30);
        $activeUsers = (clone $paidTicketsQuery)
            ->where('created_at', '>=', $activeWindow)
            ->distinct('user_id')
            ->count('user_id');
        $activeUsersPrev = (clone $paidTicketsQuery)
            ->whereBetween('created_at', [$prevMonthStart, $prevMonthEnd->endOfDay()])
            ->distinct('user_id')
            ->count('user_id');

        // Phân bố phương thức thanh toán
        $paymentBreakdown = Payment::where('status', 'paid')
            ->selectRaw('method, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('method')
            ->get()
            ->map(function ($row) {
                return [
                    'method' => $row->method,
                    'count'  => (int) $row->cnt,
                    'total'  => (float) $row->total,
                ];
            });

        $successRateCurrent = $allTicketsCurrent > 0 ? $bookingsCurrent / $allTicketsCurrent : 0;
        $successRatePrev = $allTicketsPrev > 0 ? $bookingsPrev / $allTicketsPrev : 0;

        $stats = [
            'revenue' => $revenueTotal,
            'revenue_change' => $this->change($revenuePrev, $revenueCurrent),
            'bookings' => $bookingsTotal,
            'bookings_change' => $this->change($bookingsPrev, $bookingsCurrent),
            'active_users' => $activeUsers,
            'active_users_change' => $this->change($activeUsersPrev, $activeUsers),
            'cancel' => $cancelCurrent,
            'cancel_change' => $this->change($cancelPrev, $cancelCurrent),
            'success_rate' => $successRateCurrent,
            'success_rate_prev' => $successRatePrev,
            'success_rate_change' => $this->change($successRatePrev, $successRateCurrent),
        ];

        // Chart data
        [$labels, $revData] = $this->chartData($range, 'revenue', $paidTicketStatuses);
        [, $bookData] = $this->chartData($range, 'bookings', $paidTicketStatuses);

        return view('admin.dashboard.index', [
            'stats' => $stats,
            'range' => $range,
            'chartLabels' => $labels,
            'chartRevenue' => $revData,
            'chartBookings' => $bookData,
            'paymentBreakdown' => $paymentBreakdown,
            'cinemas' => Cinema::orderBy('name')->get(['id', 'name']),
            'currentMonth' => $today->month,
            'currentYear' => $today->year,
        ]);
    }

    private function change(float|int $prev, float|int $current): float
    {
        if ($prev <= 0) {
            return $current > 0 ? 1.0 : 0.0;
        }
        return ($current - $prev) / $prev;
    }

    /**
     * Build chart dataset by range.
     */
    private function chartData(string $range, string $type, array $paidTicketStatuses = ['confirmed', 'used']): array
    {
        $today = Carbon::today();

        if ($range === 'day') {
            // Hiển thị theo ngày của tháng hiện tại (từ ngày 1 đến hôm nay)
            $start = $today->copy()->startOfMonth();
            $daysCount = $start->diffInDays($today) + 1;
            $labels = [];
            $data = array_fill(0, $daysCount, 0);

            $map = ($type === 'revenue')
                ? Payment::where('status', 'paid')->whereBetween('paid_at', [$start, $today->endOfDay()])
                    ->selectRaw('DATE(paid_at) as d, SUM(amount) as total')->groupBy('d')->pluck('total', 'd')
                : Ticket::whereIn('status', $paidTicketStatuses)
                    ->whereHas('payment', fn ($q) => $q->where('status', 'paid'))
                    ->whereBetween('created_at', [$start, $today->endOfDay()])
                    ->selectRaw('DATE(created_at) as d, COUNT(*) as total')->groupBy('d')->pluck('total', 'd');

            for ($i = 0; $i < $daysCount; $i++) {
                $dayDate = $start->copy()->addDays($i);
                $labels[] = $dayDate->format('d/m');
                $data[$i] = (float) ($map[$dayDate->toDateString()] ?? 0);
            }
            return [$labels, $data];
        }

        if ($range === 'year') {
            $labels = [];
            $data = [];
            $years = range($today->year - 4, $today->year);

            $map = ($type === 'revenue')
                ? Payment::where('status', 'paid')
                    ->whereYear('paid_at', '>=', $years[0])
                    ->selectRaw('YEAR(paid_at) as y, SUM(amount) as total')->groupBy('y')->pluck('total', 'y')
                : Ticket::whereIn('status', $paidTicketStatuses)
                    ->whereHas('payment', fn ($q) => $q->where('status', 'paid'))
                    ->whereYear('created_at', '>=', $years[0])
                    ->selectRaw('YEAR(created_at) as y, COUNT(*) as total')->groupBy('y')->pluck('total', 'y');

            foreach ($years as $y) {
                $labels[] = (string)$y;
                $data[] = (float) ($map[$y] ?? 0);
            }
            return [$labels, $data];
        }

        // default: month (12 months of current year)
        $labels = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
        $data = array_fill(0, 12, 0);
        $year = $today->year;
        $map = ($type === 'revenue')
            ? Payment::where('status', 'paid')->whereYear('paid_at', $year)
                ->selectRaw('MONTH(paid_at) as m, SUM(amount) as total')->groupBy('m')->pluck('total', 'm')
            : Ticket::whereIn('status', $paidTicketStatuses)
                ->whereHas('payment', fn ($q) => $q->where('status', 'paid'))
                ->whereYear('created_at', $year)
                ->selectRaw('MONTH(created_at) as m, COUNT(*) as total')->groupBy('m')->pluck('total', 'm');

        foreach (range(1, 12) as $i) {
            $data[$i - 1] = (float) ($map[$i] ?? 0);
        }

        return [$labels, $data];
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
