<?php

namespace App\Services;

use App\Models\Ticket;

class TicketExpiryService
{
    public function expireOverdue(?int $ticketId = null, ?int $userId = null): int
    {
        return Ticket::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('showtime', fn ($query) => $query->where('end_time', '<=', now()))
            ->when($ticketId, fn ($query) => $query->whereKey($ticketId))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->update(['status' => 'cancelled', 'cancellation_reason' => Ticket::EXPIRED_REASON]);
    }
}
