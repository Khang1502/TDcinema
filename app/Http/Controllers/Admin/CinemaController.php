<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Room;
use App\Models\Seat;
use Illuminate\Http\Request;

class CinemaController extends Controller
{
    public function index()
    {
        $cinemas = Cinema::withCount('rooms')
            ->orderBy('name')
            ->paginate(10);

        return view('admin.cinemas.index', compact('cinemas'));
    }

    public function create()
    {
        return view('admin.cinemas.create');
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $cinema = Cinema::create($data);

        // Sau khi tạo rạp, chuyển thẳng sang trang Sửa để thêm phòng chiếu
        return redirect()
            ->route('admin.cinemas.edit', $cinema)
            ->with('success', 'Đã tạo rạp. Thêm phòng chiếu cho rạp này.');
    }

    public function edit(Cinema $cinema)
    {
        $cinema->loadCount('rooms')->load('rooms');
        return view('admin.cinemas.edit', compact('cinema'));
    }

    public function update(Request $request, Cinema $cinema)
    {
        $data = $this->validatedData($request, $cinema->id);
        $cinema->update($data);

        return redirect()->route('admin.cinemas.index')->with('success', 'Đã cập nhật rạp.');
    }

    public function destroy(Cinema $cinema)
    {
        $hasShowtimes = $cinema->rooms()->whereHas('showtimes')->exists();

        if ($hasShowtimes) {
            $cinema->update(['status' => 'inactive']);
            return redirect()->route('admin.cinemas.index')->with('warning', 'Rạp có suất chiếu/vé. Đã chuyển sang trạng thái ngưng hoạt động.');
        }

        $cinema->delete();

        return redirect()->route('admin.cinemas.index')->with('success', 'Đã xoá rạp.');
    }

    private function validatedData(Request $request, $cinemaId = null): array
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255', 'unique:cinemas,name' . ($cinemaId ? ',' . $cinemaId : '')],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:255'],
            'status'  => ['nullable', 'in:active,inactive,maintenance'],
        ]);

        // Mặc định nếu không chọn sẽ là active
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        return $data;
    }

    // Phòng chiếu: thêm nhanh trong trang rạp
    public function storeRoom(Request $request, Cinema $cinema)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['nullable', 'string', 'max:100'],
            'total_seats' => ['nullable', 'integer', 'min:0'],
            'status'      => ['nullable', 'in:active,inactive,maintenance'],
        ]);

        $data['cinema_id'] = $cinema->id;
        $room = Room::create($data);
        $this->generateSeats($room);

        return redirect()->route('admin.cinemas.edit', $cinema)->with('success', 'Đã thêm phòng chiếu.');
    }

    public function destroyRoom(Cinema $cinema, Room $room)
    {
        if ($room->cinema_id !== $cinema->id) {
            abort(404);
        }
        if ($room->showtimes()->exists()) {
            $room->update(['status' => 'inactive']);
            return redirect()->route('admin.cinemas.edit', $cinema)->with('warning', 'Phòng đã có suất chiếu, hệ thống chỉ vô hiệu hoá phòng.');
        }
        $room->delete();
        return redirect()->route('admin.cinemas.edit', $cinema)->with('success', 'Đã xoá phòng chiếu.');
    }

    public function updateRoom(Request $request, Cinema $cinema, Room $room)
    {
        if ($room->cinema_id !== $cinema->id) {
            abort(404);
        }

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['nullable', 'string', 'max:100'],
            'total_seats' => ['nullable', 'integer', 'min:0'],
            'status'      => ['nullable', 'in:active,inactive,maintenance'],
        ]);
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        $room->update($data);
        $this->generateSeats($room);

        return redirect()->route('admin.cinemas.edit', [$cinema, 'edit_room' => null])->with('success', 'Đã cập nhật phòng chiếu.');
    }

    /**
     * Sinh ghế dựa trên total_seats của phòng (xoá cũ, tạo mới).
     */
    private function generateSeats(Room $room): void
    {
        if (empty($room->total_seats) || $room->total_seats <= 0) {
            return;
        }

        Seat::where('room_id', $room->id)->delete();

        $letters = range('A', 'Z');
        $perRow  = 10;
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
