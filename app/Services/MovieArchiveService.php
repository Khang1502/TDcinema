<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MovieArchiveService
{
    public function summaries(array $ids, bool $financial): Collection
    {
        if (!$ids) return collect();
        $shows = DB::table('showtimes')->join('rooms', 'rooms.id', '=', 'showtimes.room_id')->whereIn('showtimes.movie_id', $ids)
            ->selectRaw('showtimes.movie_id as id, COUNT(*) as showtimes_count, COUNT(DISTINCT rooms.cinema_id) as cinemas_count, MIN(showtimes.start_time) as first_show, MAX(showtimes.start_time) as last_show')
            ->groupBy('showtimes.movie_id')->get()->keyBy('id');
        $tickets = $this->ticketQuery($ids, $financial)->selectRaw('showtimes.movie_id as id')->groupBy('showtimes.movie_id')->get()->keyBy('id');
        return collect($ids)->mapWithKeys(fn ($id) => [$id => array_merge($this->emptySummary($financial), (array)($shows[$id] ?? []), (array)($tickets[$id] ?? []))]);
    }

    public function cinemas(int $movieId, bool $financial): Collection
    {
        $shows = DB::table('showtimes')->join('rooms', 'rooms.id', '=', 'showtimes.room_id')->join('cinemas', 'cinemas.id', '=', 'rooms.cinema_id')->where('showtimes.movie_id', $movieId)
            ->selectRaw('cinemas.id, cinemas.name, COUNT(*) as showtimes_count')->groupBy('cinemas.id', 'cinemas.name')->orderBy('cinemas.name')->get();
        $tickets = $this->ticketQuery([$movieId], $financial)->join('rooms', 'rooms.id', '=', 'showtimes.room_id')
            ->selectRaw('rooms.cinema_id as id')->groupBy('rooms.cinema_id')->get()->keyBy('id');
        return $shows->map(fn ($show) => array_merge($this->emptySummary($financial), (array)$show, (array)($tickets[$show->id] ?? [])));
    }

    private function ticketQuery(array $ids, bool $financial)
    {
        // Group payment attempts by ticket first so each seat is counted once.
        $payments = DB::table('payments')->join('tickets as paid_tickets', 'paid_tickets.id', '=', 'payments.ticket_id')
            ->join('showtimes as paid_shows', 'paid_shows.id', '=', 'paid_tickets.showtime_id')->whereIn('paid_shows.movie_id', $ids)
            ->select('payments.ticket_id')->selectRaw("MAX(CASE WHEN payments.status = 'paid' THEN 1 ELSE 0 END) as is_paid")->groupBy('payments.ticket_id');
        if ($financial) $payments->selectRaw("SUM(CASE WHEN payments.status = 'paid' THEN payments.amount ELSE 0 END) as revenue, SUM(CASE WHEN payments.status = 'refunded' THEN payments.amount ELSE 0 END) as refunded");
        $query = DB::table('tickets')->join('showtimes', 'showtimes.id', '=', 'tickets.showtime_id')->whereIn('showtimes.movie_id', $ids)
            ->leftJoinSub($payments, 'payment_totals', 'payment_totals.ticket_id', '=', 'tickets.id')
            ->selectRaw("COUNT(*) as tickets_count, SUM(CASE WHEN tickets.status IN ('confirmed', 'used') AND payment_totals.is_paid = 1 THEN 1 ELSE 0 END) as sold_count, SUM(CASE WHEN tickets.status = 'used' AND payment_totals.is_paid = 1 THEN 1 ELSE 0 END) as used_count, SUM(CASE WHEN tickets.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count");
        if ($financial) $query->selectRaw('COALESCE(SUM(payment_totals.revenue), 0) as revenue, COALESCE(SUM(payment_totals.refunded), 0) as refunded');
        return $query;
    }

    private function emptySummary(bool $financial): array
    {
        $data = ['showtimes_count'=>0, 'cinemas_count'=>0, 'tickets_count'=>0, 'sold_count'=>0, 'used_count'=>0, 'cancelled_count'=>0, 'first_show'=>null, 'last_show'=>null];
        return $financial ? $data + ['revenue'=>0, 'refunded'=>0] : $data;
    }
}
