<?php

namespace App\Services;

use App\Models\Cinema;
use App\Models\Movie;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public const METHODS = ['cash' => 'Tiền mặt', 'momo' => 'MoMo', 'vnpay' => 'VNPay'];
    public const STATUSES = ['paid' => 'Đã thanh toán', 'refunded' => 'Đã hoàn tiền', 'failed' => 'Thất bại'];

    public function payments(array $filters): Builder
    {
        return $this->scope(DB::table('payments')
            ->join('tickets', 'tickets.id', '=', 'payments.ticket_id')
            ->leftJoin('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
            ->leftJoin('rooms', 'rooms.id', '=', 'showtimes.room_id'), $filters)
            ->leftJoin('movies', 'movies.id', '=', 'showtimes.movie_id')
            ->leftJoin('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')
            ->whereBetween(DB::raw('COALESCE(payments.paid_at, payments.created_at)'), [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']);
    }

    private function scope(Builder $query, array $filters): Builder
    {
        return $query->when($filters['movie_id'], fn ($q, $id) => $q->where('showtimes.movie_id', $id))
            ->when($filters['cinema_id'], fn ($q, $id) => $q->where('rooms.cinema_id', $id));
    }

    private function metrics(): string
    {
        // A refund replaces the paid status on the same payment record. It is
        // included in receipts and subtracted once, rather than subtracted twice.
        return "COUNT(*) as transactions,
            COALESCE(SUM(CASE WHEN payments.status IN ('paid','refunded') THEN payments.amount ELSE 0 END),0) as received,
            COALESCE(SUM(CASE WHEN payments.status = 'refunded' THEN payments.amount ELSE 0 END),0) as refunded,
            COALESCE(SUM(CASE WHEN payments.status = 'paid' THEN payments.amount ELSE 0 END),0) as revenue,
            COUNT(DISTINCT CASE WHEN payments.status IN ('paid','refunded') THEN tickets.id END) as settled_tickets,
            COUNT(DISTINCT CASE WHEN payments.status = 'paid' AND tickets.status IN ('confirmed','used') THEN tickets.id END) as sold_tickets,
            COUNT(DISTINCT CASE WHEN payments.status = 'paid' AND tickets.status = 'used' THEN tickets.id END) as used_tickets,
            SUM(CASE WHEN payments.status = 'paid' THEN 1 ELSE 0 END) as paid_transactions,
            SUM(CASE WHEN payments.status = 'refunded' THEN 1 ELSE 0 END) as refunded_transactions,
            SUM(CASE WHEN payments.status = 'failed' THEN 1 ELSE 0 END) as failed_transactions";
    }

    public function build(array $filters): array
    {
        $base = $this->payments($filters);
        $summary = (array) (clone $base)->selectRaw($this->metrics())->first();
        foreach ($summary as $key => $value) $summary[$key] = (float) ($value ?? 0);

        // Operational counts use the date the ticket was booked, rather than the payment date.
        $bookings = $this->scope(DB::table('tickets')
            ->leftJoin('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')
            ->leftJoin('rooms', 'rooms.id', '=', 'showtimes.room_id'), $filters)
            ->whereBetween(DB::raw('COALESCE(tickets.booked_at, tickets.created_at)'), [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->selectRaw("COUNT(*) as booked_tickets, SUM(CASE WHEN tickets.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_tickets, SUM(CASE WHEN tickets.status = 'pending' THEN 1 ELSE 0 END) as pending_tickets")->first();
        foreach ((array) $bookings as $key => $value) $summary[$key] = (int) ($value ?? 0);

        // Use a semi-join: multiple payment attempts must not multiply a combo order.
        $settledIds = (clone $base)->whereIn('payments.status', ['paid', 'refunded'])->select('tickets.id');
        $food = DB::table('food_orders')->where('status', 'completed')->whereIn('ticket_id', $settledIds)
            ->selectRaw('COUNT(*) as combo_orders, COALESCE(SUM(total_price),0) as combo_value')->first();
        $summary += (array) $food;

        $movieStats = (clone $base)->select('showtimes.movie_id as id')->selectRaw($this->metrics())->groupBy('showtimes.movie_id')->get()->keyBy('id');
        $cinemaStats = (clone $base)->select('rooms.cinema_id as id')->selectRaw($this->metrics())->groupBy('rooms.cinema_id')->get()->keyBy('id');
        $empty = array_fill_keys(['received', 'refunded', 'revenue', 'sold_tickets', 'used_tickets', 'settled_tickets', 'transactions'], 0);
        $movies = Movie::when($filters['movie_id'], fn ($q, $id) => $q->whereKey($id))->get(['id','title','status'])->map(fn ($movie) => array_merge($empty, $movie->toArray(), (array) ($movieStats[$movie->id] ?? [])));
        $cinemas = Cinema::when($filters['cinema_id'], fn ($q, $id) => $q->whereKey($id))->get(['id','name','city','status'])->map(fn ($cinema) => array_merge($empty, $cinema->toArray(), (array) ($cinemaStats[$cinema->id] ?? [])));
        // Preserve unassigned historical revenue in the totals, if a relation is missing.
        if (isset($movieStats[''])) $movies->push(array_merge($empty, (array) $movieStats[''], ['id'=>null,'title'=>'Phim không còn thông tin','status'=>'inactive']));
        if (isset($cinemaStats[''])) $cinemas->push(array_merge($empty, (array) $cinemaStats[''], ['id'=>null,'name'=>'Rạp không còn thông tin','city'=>'','status'=>'inactive']));
        $movies = $movies->sortBy([['revenue','desc'], ['received','desc'], ['title','asc']])->values();
        $cinemas = $cinemas->sortBy([['revenue','desc'], ['received','desc'], ['name','asc']])->values();
        $daily = (clone $base)->selectRaw('DATE(COALESCE(payments.paid_at,payments.created_at)) as date, '.$this->metrics())
            ->groupBy('date')->orderBy('date')->get();
        $methods = (clone $base)->select('payments.method')->selectRaw($this->metrics())->groupBy('payments.method')->orderByDesc('revenue')->get();
        $matrix = (clone $base)->select('showtimes.movie_id','rooms.cinema_id','movies.title','cinemas.name')
            ->selectRaw($this->metrics())->groupBy('showtimes.movie_id','rooms.cinema_id','movies.title','cinemas.name')->orderByDesc('revenue')->get();

        return compact('summary', 'movies', 'cinemas', 'daily', 'methods', 'matrix');
    }

    public function details(array $filters): Builder
    {
        return $this->payments($filters)->leftJoin('users', 'users.id', '=', 'tickets.user_id')
            ->leftJoin('seats', 'seats.id', '=', 'tickets.seat_id')
            ->select('payments.id','payments.ticket_id','payments.amount','payments.method','payments.status',
                'tickets.qr_code','tickets.status as ticket_status','tickets.price','movies.title as movie_title',
                'cinemas.name as cinema_name','rooms.name as room_name','showtimes.start_time','users.name as customer_name',
                'seats.seat_row','seats.seat_number')
            ->selectRaw('COALESCE(payments.paid_at,payments.created_at) as recorded_at')
            ->orderByDesc('recorded_at')->orderByDesc('payments.id');
    }
}
