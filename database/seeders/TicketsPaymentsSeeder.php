<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ticket;
use App\Models\Payment;
use App\Models\User;
use App\Models\Showtime;
use App\Models\Seat;
use Carbon\Carbon;

class TicketsPaymentsSeeder extends Seeder
{
    public function run(): void
    {
        Ticket::truncate();
        Payment::truncate();

        $user     = User::where('email', 'customer@example.com')->first();
        $showtime = Showtime::first();
        $seat     = Seat::first();

        if (! $user || ! $showtime || ! $seat) {
            return;
        }

        $ticket = Ticket::create([
            'user_id'     => $user->id,
            'showtime_id' => $showtime->id,
            'seat_id'     => $seat->id,
            'price'       => 90000,
            'qr_code'     => 'TICKET_DEMO_QR',
            'status'      => 'confirmed',
            'booked_at'   => Carbon::now(),
        ]);

        Payment::create([
            'ticket_id' => $ticket->id,
            'amount'    => 90000,
            'method'    => 'cash',
            'status'    => 'paid',
            'paid_at'   => Carbon::now(),
        ]);
    }
}
