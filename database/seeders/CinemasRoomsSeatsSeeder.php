<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;

class CinemasRoomsSeatsSeeder extends Seeder
{
    public function run(): void
    {
        Seat::truncate();
        Room::truncate();
        Cinema::truncate();

        $cinema = Cinema::create([
            'name'   => 'TD Cinema Cao Lỗ',
            'address'=> '100 Cao Lỗ, Quận 8, TP.HCM',
            'city'   => 'TP.HCM',
            'status' => 'active',
        ]);

        // 2 phòng, mỗi phòng 5 hàng x 10 ghế
        for ($roomIndex = 1; $roomIndex <= 2; $roomIndex++) {
            $room = Room::create([
                'cinema_id'   => $cinema->id,
                'name'        => 'Rạp ' . $roomIndex,
                'type'        => $roomIndex === 1 ? '2D' : 'IMAX',
                'total_seats' => 50,
                'status'      => 'active',
            ]);

            $rows = ['A','B','C','D','E'];
            foreach ($rows as $row) {
                for ($num = 1; $num <= 10; $num++) {
                    Seat::create([
                        'room_id'     => $room->id,
                        'seat_row'    => $row,
                        'seat_number' => $num,
                        'seat_type'   => 'normal',
                        'extra_price' => 0,
                    ]);
                }
            }
        }
    }
}

