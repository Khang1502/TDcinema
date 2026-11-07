<?php

namespace App\Services;

use App\Models\Showtime;
use App\Models\Seat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function parseSeatCodes(?string $seatParam): Collection
    {
        return collect(explode(',', (string) $seatParam))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();
    }

    public function areSameSeatCodes(Collection $left, Collection $right): bool
    {
        return $left->sort()->values()->all() === $right->sort()->values()->all();
    }

    public function calculateTicketTotal(Showtime $showtime, Collection $seatCodes): float
    {
        if ($seatCodes->isEmpty()) {
            return 0.0;
        }

        $ticketPrice = (float) ($showtime->base_price ?? 0);
        $extraTotal = (float) Seat::where('room_id', $showtime->room_id)
            ->whereIn(DB::raw("CONCAT(seat_row, seat_number)"), $seatCodes->all())
            ->sum('extra_price');

        return ($seatCodes->count() * $ticketPrice) + $extraTotal;
    }
}
