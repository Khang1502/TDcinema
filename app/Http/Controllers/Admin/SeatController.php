<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Seat;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeatController extends Controller
{
    public function index(Request $request)
    {
        $cinemas = Cinema::orderBy('name')->get();

        // Ưu tiên lấy cinema_id từ query; nếu không có/hỏng thì lấy rạp đầu tiên có phòng
        $defaultCinemaId = $request->query('cinema_id');
        if (! $defaultCinemaId || ! $cinemas->contains('id', (int) $defaultCinemaId)) {
            $defaultCinemaId = optional(
                Cinema::whereHas('rooms')->orderBy('name')->first()
                ?? $cinemas->first()
            )->id;
        }

        // Lọc danh sách phòng theo rạp đã chọn
        $rooms = Room::where('cinema_id', $defaultCinemaId)
            ->orderBy('name')
            ->get();

        // Chọn phòng mặc định: query room_id nếu hợp lệ, ngược lại phòng đầu tiên của rạp
        $defaultRoomId = $request->query('room_id');
        if (! $defaultRoomId || ! $rooms->contains('id', (int) $defaultRoomId)) {
            $defaultRoomId = optional($rooms->first())->id;
        }

        // Lấy sơ đồ ghế của phòng đang chọn (nếu có)
        $seatRows   = collect();
        $seatStats  = [
            'total'      => 0,
            'active'     => 0,
            'inactive'   => 0,
            'by_type'    => ['normal' => 0, 'vip' => 0, 'sweetbox' => 0],
            'room_total' => 0,
            'diff'       => 0,
        ];
        if ($defaultRoomId) {
            $seats = Seat::where('room_id', $defaultRoomId)
                ->orderBy('seat_row')
                ->orderBy('seat_number')
                ->get();
            $seatRows = $seats->groupBy('seat_row');

            $seatStats['total']      = $seats->count();
            $seatStats['active']     = $seats->where('status', 1)->count();
            $seatStats['inactive']   = $seats->where('status', 0)->count();
            $seatStats['by_type']    = [
                'normal'   => $seats->where('seat_type', 'normal')->count(),
                'vip'      => $seats->where('seat_type', 'vip')->count(),
                'sweetbox' => $seats->where('seat_type', 'sweetbox')->count(),
            ];
            $seatStats['room_total'] = Room::find($defaultRoomId)?->total_seats ?? 0;
            $seatStats['diff']       = $seatStats['total'] - $seatStats['room_total'];
        }

        $allRooms = Room::with('cinema')->orderBy('cinema_id')->orderBy('name')->get();

        return view('admin.seats.index', compact('cinemas', 'rooms', 'defaultCinemaId', 'defaultRoomId', 'seatRows', 'allRooms', 'seatStats'));
    }

    public function create()
    {
        $rooms = Room::orderBy('name')->get();
        return view('admin.seats.create', compact('rooms'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        Seat::create($data);
        return redirect()->route('admin.seats.index')->with('success', 'Đã thêm ghế.');
    }

    public function edit(Seat $seat)
    {
        $rooms = Room::orderBy('name')->get();
        return view('admin.seats.edit', compact('seat', 'rooms'));
    }

    public function update(Request $request, Seat $seat)
    {
        $data = $this->validatedData($request, $seat->id);
        $seat->update($data);
        return redirect()->route('admin.seats.index')->with('success', 'Đã cập nhật ghế.');
    }

    public function destroy(Seat $seat)
    {
        $seat->delete();
        return redirect()->route('admin.seats.index')->with('success', 'Đã xóa ghế.');
    }

    /**
     * Bulk action: delete seats chưa có vé, còn lại chuyển status=0 (inactive).
     */
    public function bulk(Request $request)
    {
        $request->validate([
            'seat_ids' => ['required', 'array'],
            'seat_ids.*' => ['integer', 'exists:seats,id'],
            'action' => ['required', 'in:delete,inactive'],
        ]);

        $ids = $request->seat_ids;
        $action = $request->action;

        $seats = Seat::withCount('tickets')->whereIn('id', $ids)->get();

        $deleted = collect();
        $inactivated = collect();

        if ($action === 'delete') {
            $toDelete = $seats->where('tickets_count', 0)->pluck('id');
            if ($toDelete->isNotEmpty()) {
                Seat::whereIn('id', $toDelete)->delete();
                $deleted = $toDelete;
            }
            // Ghế có vé thì không xóa, set inactive
            $toInactive = $seats->where('tickets_count', '>', 0)->pluck('id');
            if ($toInactive->isNotEmpty()) {
                Seat::whereIn('id', $toInactive)->update(['status' => 0]);
                $inactivated = $toInactive;
            }
        } elseif ($action === 'inactive') {
            Seat::whereIn('id', $ids)->update(['status' => 0]);
            $inactivated = collect($ids);
        }

        $message = [];
        if ($deleted->isNotEmpty()) {
            $message[] = "Đã xóa " . $deleted->count() . " ghế (không có vé).";
        }
        if ($inactivated->isNotEmpty()) {
            $message[] = "Đã chuyển trạng thái " . $inactivated->count() . " ghế.";
        }
        if (empty($message)) {
            $message[] = "Không có ghế nào được xử lý.";
        }

        return back()->with('success', implode(' ', $message));
    }

    /**
     * Seat map view theo phòng (UI sơ đồ ghế).
     */
    public function map(Room $room)
    {
        return view('admin.seats.map', compact('room'));
    }

    /**
     * Lấy danh sách phòng theo rạp (cho dropdown động).
     */
    public function roomsByCinema(Cinema $cinema)
    {
        return Room::where('cinema_id', $cinema->id)
            ->orderBy('name')
            ->get(['id', 'name', 'cinema_id']);
    }

    /**
     * Trả dữ liệu ghế theo phòng (JSON, group theo hàng).
     */
    public function data(Room $room)
    {
        $seats = Seat::where('room_id', $room->id)
            ->orderBy('seat_row')
            ->orderBy('seat_number')
            ->get();

        $rows = $seats->groupBy('seat_row')->map(function ($rowSeats) {
            return $rowSeats->values()->map(function ($seat) {
                return [
                    'id'          => $seat->id,
                    'row'         => $seat->seat_row,
                    'number'      => $seat->seat_number,
                    'code'        => $seat->seat_row . $seat->seat_number,
                    'type'        => $seat->seat_type,
                    'status'      => $seat->status ? 'active' : 'inactive',
                    'extra_price' => (int) $seat->extra_price,
                ];
            });
        });

        return response()->json([
            'room' => [
                'id'   => $room->id,
                'name' => $room->name,
            ],
            'rows' => $rows,
        ]);
    }

    /**
     * Bulk update: type/status/extra_price cho nhiều ghế.
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'seats'               => ['required', 'array', 'min:1'],
            'seats.*.id'          => ['required', 'exists:seats,id'],
            'seats.*.seat_type'   => ['nullable', Rule::in(['normal', 'vip', 'sweetbox'])],
            'seats.*.status'      => ['nullable', 'in:0,1'],
            'seats.*.extra_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['seats'] as $item) {
                $seat = Seat::find($item['id']);
                if (! $seat) {
                    continue;
                }
                $update = [];
                foreach (['seat_type', 'status', 'extra_price'] as $field) {
                    if (array_key_exists($field, $item) && $item[$field] !== null) {
                        $update[$field] = $item[$field];
                    }
                }
                if ($update) {
                    $seat->update($update);
                }
            }
        });

        return response()->json(['message' => 'Cập nhật ghế thành công']);
    }

    /**
     * Bulk add ghế theo cấu hình hàng.
     * Body: rows: [{row_label, from, to, seat_type?, extra_price?}]
     */
    public function bulkAdd(Request $request, Room $room)
    {
        $data = $request->validate([
            'rows'               => ['required', 'array', 'min:1'],
            'rows.*.row_label'   => ['required', 'string', 'max:5'],
            'rows.*.from'        => ['required', 'integer', 'min:1'],
            'rows.*.to'          => ['required', 'integer', 'min:1'],
            'rows.*.seat_type'   => ['nullable', Rule::in(['normal', 'vip', 'sweetbox'])],
            'rows.*.extra_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($data, $room) {
            foreach ($data['rows'] as $rowCfg) {
                $type = $rowCfg['seat_type'] ?? 'normal';
                $fee  = $rowCfg['extra_price'] ?? 0;
                for ($i = $rowCfg['from']; $i <= $rowCfg['to']; $i++) {
                    Seat::firstOrCreate(
                        [
                            'room_id'     => $room->id,
                            'seat_row'    => $rowCfg['row_label'],
                            'seat_number' => $i,
                        ],
                        [
                            'seat_type'   => $type,
                            'status'      => 1,
                            'extra_price' => $fee,
                        ]
                    );
                }
            }
        });

        $this->syncRoomTotalSeats($room);

        return response()->json(['message' => 'Đã tạo ghế/hàng thành công']);
    }

    /**
     * Bulk delete/inactive: ghế có vé -> inactive, ghế không vé -> delete.
     */
    public function bulkDelete(Request $request)
    {
        $data = $request->validate([
            'seat_ids'   => ['required', 'array', 'min:1'],
            'seat_ids.*' => ['required', 'exists:seats,id'],
        ]);

        DB::transaction(function () use ($data) {
            $seats = Seat::withCount('tickets')->whereIn('id', $data['seat_ids'])->get();
            $roomsToSync = collect();
            foreach ($seats as $seat) {
                if ($seat->tickets_count > 0) {
                    $seat->update(['status' => 0]); // inactive
                    $roomsToSync->push($seat->room_id);
                } else {
                    $seat->delete();
                    $roomsToSync->push($seat->room_id);
                }
            }

            $roomsToSync = $roomsToSync->unique()->filter(); // chỉ còn các room_id hợp lệ
            foreach ($roomsToSync as $roomId) {
                $room = Room::find($roomId);
                if ($room) {
                    $this->syncRoomTotalSeats($room);
                }
            }
        });

        return response()->json(['message' => 'Đã xử lý xoá / inactive ghế']);
    }

    /**
     * Tạo sơ đồ ghế tự động cho 1 phòng (ghi đè layout cũ).
     */
    public function generateLayout(Request $request, Room $room)
    {
        $data = $request->validate([
            'rows_count'     => ['required', 'integer', 'min:1', 'max:52'],
            'seats_per_row'  => ['required', 'integer', 'min:1', 'max:100'],
            'aisles'         => ['nullable', 'string'], // dạng "8,9" sẽ tạo gap giữa 7|8|9|10
            'default_type'   => ['nullable', Rule::in(['normal', 'vip', 'sweetbox'])],
            'default_fee'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $aisles = collect(explode(',', (string) $data['aisles']))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->sort()
            ->values();

        $defaultType = $data['default_type'] ?? 'normal';
        $defaultFee  = $data['default_fee'] ?? 0;

        DB::transaction(function () use ($room, $data, $aisles, $defaultType, $defaultFee) {
            // Xoá layout cũ
            Seat::where('room_id', $room->id)->delete();

            for ($r = 0; $r < $data['rows_count']; $r++) {
                $rowLabel = $this->rowLabelFromIndex($r);
                for ($c = 1; $c <= $data['seats_per_row']; $c++) {
                    // Nếu cột nằm trong aisles -> bỏ qua để tạo lối đi
                    if ($aisles->contains($c)) {
                        continue;
                    }
                    Seat::create([
                        'room_id'     => $room->id,
                        'seat_row'    => $rowLabel,
                        'seat_number' => $c,
                        'seat_type'   => $defaultType,
                        'status'      => 1,
                        'extra_price' => $defaultFee,
                    ]);
                }
            }

            $this->syncRoomTotalSeats($room);
        });

        return back()->with('success', 'Đã tạo sơ đồ ghế tự động cho phòng ' . $room->name);
    }

    /**
     * Copy layout ghế từ phòng khác (ghi đè layout hiện tại).
     */
    public function copyLayout(Request $request, Room $room)
    {
        $data = $request->validate([
            'source_room_id' => ['required', 'different:room', 'exists:rooms,id'],
        ]);

        if ((int) $data['source_room_id'] === (int) $room->id) {
            return back()->with('error', 'Không thể copy cùng một phòng.');
        }

        $sourceSeats = Seat::where('room_id', $data['source_room_id'])->get();

        DB::transaction(function () use ($room, $sourceSeats) {
            Seat::where('room_id', $room->id)->delete();
            foreach ($sourceSeats as $seat) {
                Seat::create([
                    'room_id'     => $room->id,
                    'seat_row'    => $seat->seat_row,
                    'seat_number' => $seat->seat_number,
                    'seat_type'   => $seat->seat_type,
                    'status'      => $seat->status,
                    'extra_price' => $seat->extra_price,
                ]);
            }
            $this->syncRoomTotalSeats($room);
        });

        return back()->with('success', 'Đã copy sơ đồ từ phòng khác sang ' . $room->name);
    }

    /**
     * Đồng bộ lại tổng ghế của phòng theo bảng seats.
     */
    public function syncTotal(Room $room)
    {
        $this->syncRoomTotalSeats($room);
        return back()->with('success', 'Đã đồng bộ tổng ghế cho phòng ' . $room->name);
    }

    /**
     * Cập nhật tổng ghế của phòng theo bảng seats.
     */
    protected function syncRoomTotalSeats(Room $room): void
    {
        $count = Seat::where('room_id', $room->id)->count();
        $room->update(['total_seats' => $count]);
    }

    /**
     * Chuyển index 0,1,2... thành A,B,C... Z, AA, AB...
     */
    protected function rowLabelFromIndex(int $index): string
    {
        $letters = '';
        $index += 1; // để 1 -> A
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - 1, 26);
        }
        return $letters;
    }

    private function validatedData(Request $request, $ignoreId = null): array
    {
        return $request->validate([
            'room_id'     => ['required', 'exists:rooms,id'],
            'seat_row'    => ['required', 'string', 'max:2'],
            'seat_number' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('seats')->where(function ($q) use ($request) {
                    return $q->where('room_id', $request->room_id)
                        ->where('seat_row', $request->seat_row)
                        ->where('seat_number', $request->seat_number);
                })->ignore($ignoreId),
            ],
            'seat_type'   => ['required', Rule::in(['normal', 'vip', 'sweetbox'])],
            'extra_price' => ['required', 'numeric', 'min:0'],
            'status'      => ['required', 'in:0,1'],
        ]);
    }
}
