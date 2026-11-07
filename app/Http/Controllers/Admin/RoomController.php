<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoomController extends Controller
{
    public function index(Request $request)
    {
        $cinemaId = $request->get('cinema_id');

        $roomsQuery = Room::with('cinema')->orderBy('cinema_id')->orderBy('id');
        if ($cinemaId) {
            $roomsQuery->where('cinema_id', $cinemaId);
        }

        $rooms = $roomsQuery->paginate(10)->appends($request->query());
        $cinemas = Cinema::orderBy('name')->get();

        return view('admin.rooms.index', compact('rooms', 'cinemas', 'cinemaId'));
    }

    public function create()
    {
        $cinemas = Cinema::orderBy('name')->get();
        return view('admin.rooms.create', compact('cinemas'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $room = Room::create($data);
        $this->generateSeats($room);

        return redirect()->route('admin.rooms.index')->with('success', 'Đã thêm phòng chiếu.');
    }

    public function edit(Room $room)
    {
        $cinemas = Cinema::orderBy('name')->get();
        return view('admin.rooms.edit', compact('room', 'cinemas'));
    }

    public function update(Request $request, Room $room)
    {
        $data = $this->validatedData($request);
        $room->update($data);
        $this->generateSeats($room);

        return redirect()->route('admin.rooms.index')->with('success', 'Đã cập nhật phòng chiếu.');
    }

    public function destroy(Room $room)
    {
        $room->delete();
        return redirect()->route('admin.rooms.index')->with('success', 'Đã xoá phòng chiếu.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'cinema_id'   => ['required', 'exists:cinemas,id'],
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:2D,3D,IMAX,VIP'],
            'total_seats' => ['nullable', 'integer', 'min:0'],
            'status'      => ['nullable', 'in:active,inactive,maintenance'],
        ]);

        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        return $data;
    }

    /**
     * Tự động sinh ghế cho phòng dựa trên total_seats (nếu có).
     * Hiện tại sẽ xoá ghế cũ và tạo lại với cấu trúc A1..A10, B1..B10, ...
     */
    private function generateSeats(Room $room): void
    {
        if (empty($room->total_seats) || $room->total_seats <= 0) {
            return;
        }

        // Xoá ghế cũ để đồng bộ số lượng (chưa xử lý giữ lịch sử vé)
        Seat::where('room_id', $room->id)->delete();

        $letters = range('A', 'Z');
        $perRow  = 10; // số ghế mỗi hàng
        $now     = now();

        $payload = [];
        for ($i = 0; $i < $room->total_seats; $i++) {
            $rowIndex = intdiv($i, $perRow) % count($letters);
            $row      = $letters[$rowIndex];
            $number   = ($i % $perRow) + 1;

            $payload[] = [
                'room_id'     => $room->id,
                'seat_row'    => $row,
                'seat_number' => $number,
                'seat_type'   => 'normal',
                'extra_price' => 0,
                'status'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        }

        foreach (array_chunk($payload, 500) as $chunk) {
            Seat::insert($chunk);
        }
    }
}
