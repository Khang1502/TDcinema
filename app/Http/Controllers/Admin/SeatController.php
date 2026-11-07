<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Seat;
use App\Models\Room;
use App\Models\Showtime;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
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
            ->orderBy('id')
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

        $allRooms = Room::with('cinema')->orderBy('cinema_id')->orderBy('id')->get();
        $seatPricing = collect(['normal' => 'Thường', 'vip' => 'VIP', 'sweetbox' => 'Đôi'])->map(function ($label, $type) use ($seatRows) {
            $seats = $seatRows->flatten(1)->where('seat_type', $type);
            $fees = $seats->pluck('extra_price')->map(fn ($fee) => (float) $fee)->unique();
            return ['label' => $label, 'count' => $seats->count(), 'fee' => $fees->count() > 1 ? null : ($fees->first() ?? 0),
                'min' => $fees->min() ?? 0, 'max' => $fees->max() ?? 0];
        });
        $priceExample = $defaultRoomId ? Showtime::where('room_id', $defaultRoomId)
            ->where('status', 'active')->where('start_time', '>', now())->orderBy('start_time')->first() : null;

        return view('admin.seats.index', compact('cinemas', 'rooms', 'defaultCinemaId', 'defaultRoomId', 'seatRows', 'allRooms', 'seatStats', 'seatPricing', 'priceExample'));
    }

    public function create(Request $request)
    {
        $cinemas = Cinema::orderBy('name')->get(['id', 'name']);
        $rooms = Room::orderBy('cinema_id')->orderBy('id')->get();
        $selectedRoom = $rooms->firstWhere('id', filter_var($request->old('room_id', $request->query('room_id')), FILTER_VALIDATE_INT));
        $cinemaId = $request->old('cinema_id', $request->query('cinema_id', $selectedRoom?->cinema_id));
        $selectedCinemaId = $cinemas->firstWhere('id', filter_var($cinemaId, FILTER_VALIDATE_INT))?->id;
        $selectedRoomId = $selectedRoom && $selectedRoom->cinema_id == $selectedCinemaId ? $selectedRoom->id : null;
        $availableRooms = $rooms->where('cinema_id', $selectedCinemaId)->values();

        return view('admin.seats.create', compact('cinemas', 'rooms', 'availableRooms', 'selectedCinemaId', 'selectedRoomId'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $seat = Seat::create($data);
        $this->syncRoomTotalSeats($seat->room);

        return redirect()->route('admin.seats.index', [
            'cinema_id' => $seat->room->cinema_id,
            'room_id' => $seat->room_id,
        ])->with('success', 'Đã thêm ghế.');
    }

    public function edit(Seat $seat)
    {
        $rooms = Room::orderBy('cinema_id')->orderBy('id')->get();
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
        return redirect()->route('admin.seats.index', ['cinema_id' => $room->cinema_id, 'room_id' => $room->id]);
    }

    public function preview(Request $request)
    {
        $data = $this->layoutData($request);
        $aisles = collect(explode(',', $data['aisles'] ?? ''))->map(fn ($v) => (int) trim($v))->unique();
        $rows = collect();
        for ($r = 0; $r < $data['rows_count']; $r++) {
            $label = $this->rowLabelFromIndex($r);
            $seats = collect();
            for ($c = 1; $c <= $data['seats_per_row']; $c++) {
                if (! $aisles->contains($c)) {
                    $seats->push(['row' => $label, 'number' => $c, 'code' => $label.$c,
                        'type' => $data['default_type'] ?? 'normal', 'status' => 'active',
                        'extra_price' => $data['default_fee'] ?? 0]);
                }
            }
            $rows->put($label, $seats);
        }
        return response()->json(['rows' => $rows, 'layout' => \App\Support\SeatMapLayout::forRows($rows), 'total' => $rows->sum(fn ($seats) => $seats->count())]);
    }

    private function layoutData(Request $request): array
    {
        $data = $request->validate([
            'rows_count' => ['required', 'integer', 'min:1', 'max:52'],
            'seats_per_row' => ['required', 'integer', 'min:1', 'max:100'],
            'aisles' => ['nullable', 'string', 'regex:/^\s*(\d+\s*(,\s*\d+\s*)*)?$/'],
            'default_type' => ['nullable', Rule::in(['normal', 'vip', 'sweetbox'])],
            'default_fee' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ], ['aisles.regex' => 'Nhập số cột lối đi, ngăn cách bằng dấu phẩy (ví dụ: 5,12).']);
        $aisles = collect(explode(',', $data['aisles'] ?? ''))->filter(fn ($v) => trim($v) !== '')->map(fn ($v) => (int) $v)->unique();
        if ($aisles->contains(fn ($v) => $v < 1 || $v > $data['seats_per_row']) || $aisles->count() >= $data['seats_per_row']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['aisles' => 'Cột lối đi phải nằm trong số cột đã nhập và cần giữ ít nhất một ghế mỗi hàng.']);
        }
        return $data;
    }

    private function layoutIsProtected(Room $room): bool
    {
        return $room->showtimes()->whereHas('tickets')->exists()
            || $room->seats()->whereHas('tickets')->exists()
            || $this->hasActiveSeatHolds($room, $room->seats()->get());
    }

    /**
     * Lấy danh sách phòng theo rạp (cho dropdown động).
     */
    public function roomsByCinema(Cinema $cinema)
    {
        return Room::where('cinema_id', $cinema->id)
            ->orderBy('id')
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
            'layout' => \App\Support\SeatMapLayout::forRows($rows),
        ]);
    }

    /**
     * Bulk update: type/status/extra_price cho nhiều ghế.
     */
    public function bulkUpdate(Request $request)
    {
        $data = $request->validate([
            'seats'               => ['required', 'array', 'min:1'],
            'seats.*.id'          => ['required', 'integer', 'distinct', 'exists:seats,id'],
            'seats.*.seat_type'   => ['nullable', Rule::in(['normal', 'vip', 'sweetbox'])],
            'seats.*.status'      => ['nullable', 'in:0,1'],
            'seats.*.extra_price' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ]);

        DB::transaction(function () use ($data) {
            $ids = collect($data['seats'])->pluck('id');
            $roomIds = Seat::whereIn('id', $ids)->pluck('room_id')->unique();
            $rooms = Room::whereIn('id', $roomIds)->orderBy('id')->lockForUpdate()->get();
            $heldRoomIds = $this->roomsWithActiveSeatHolds();
            foreach ($rooms as $room) {
                if ($heldRoomIds !== null ? $heldRoomIds->has($room->id) : $this->hasActiveSeatHolds($room, $room->seats()->get())) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['seats' => 'Phòng đang có khách giữ ghế. Vui lòng thử lại sau khi lượt giữ ghế kết thúc.']);
                }
            }
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
                $room = Room::query()->whereKey((int) $roomId)->first();
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
        $data = $this->layoutData($request);

        $aisles = collect(explode(',', (string) ($data['aisles'] ?? '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->sort()
            ->values();

        $defaultType = $data['default_type'] ?? 'normal';
        $defaultFee  = $data['default_fee'] ?? 0;

        DB::transaction(function () use ($room, $data, $aisles, $defaultType, $defaultFee) {
            Room::whereKey($room->id)->lockForUpdate()->first();
            if ($this->layoutIsProtected($room)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['layout' => 'Phòng đã có vé hoặc đang có khách giữ ghế. Hãy chỉnh loại ghế / phụ thu, hoặc chọn phòng trống để tạo sơ đồ mới.']);
            }
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
            Room::whereKey($room->id)->lockForUpdate()->first();
            if ($this->layoutIsProtected($room)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['layout' => 'Phòng đã có vé hoặc đang có khách giữ ghế nên chưa thể thay sơ đồ.']);
            }
            if ($sourceSeats->isEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['source_room_id' => 'Phòng nguồn chưa có ghế.']);
            }
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

    public function updateTypePrices(Request $request, Room $room)
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['room', 'cinema', 'all'])],
            'fees' => ['required', 'array:normal,vip,sweetbox'],
            'fees.normal' => ['required', 'integer', 'min:0', 'max:999999'],
            'fees.vip' => ['required', 'integer', 'min:0', 'max:999999'],
            'fees.sweetbox' => ['required', 'integer', 'min:0', 'max:999999'],
        ], [
            'fees.*.required' => 'Vui lòng nhập phụ thu cho đủ ba loại ghế.',
            'fees.*.integer' => 'Phụ thu phải là số tiền nguyên đồng.',
            'fees.*.min' => 'Phụ thu không được nhỏ hơn 0 đồng.',
            'fees.*.max' => 'Phụ thu không được vượt quá 999.999 đồng.',
        ]);

        $result = DB::transaction(function () use ($room, $data) {
            $rooms = Room::with('cinema')->whereHas('seats')
                ->when($data['scope'] === 'room', fn ($query) => $query->where('id', $room->id))
                ->when($data['scope'] === 'cinema', fn ($query) => $query->where('cinema_id', $room->cinema_id))
                ->orderBy('id')->lockForUpdate()->get();
            $heldRoomIds = $this->roomsWithActiveSeatHolds();
            $eligibleIds = collect();
            $skipped = [];
            foreach ($rooms as $target) {
                $hasHolds = $heldRoomIds !== null ? $heldRoomIds->has($target->id)
                    : $this->hasActiveSeatHolds($target, $target->seats()->get());
                if ($hasHolds) {
                    $skipped[] = ($target->cinema->name ?? 'Rạp') . ' — ' . $target->name . ': đang có khách giữ ghế.';
                } else {
                    $eligibleIds->push($target->id);
                }
            }
            foreach ($data['fees'] as $type => $fee) {
                Seat::whereIn('room_id', $eligibleIds)->where('seat_type', $type)->update(['extra_price' => $fee]);
            }

            return ['rooms' => $eligibleIds->count(), 'skipped' => $skipped];
        });

        $message = $result['rooms'] > 0
            ? "Đã cập nhật giá theo loại ghế cho {$result['rooms']} phòng chiếu. Giá vé đã bán được giữ nguyên."
            : 'Chưa cập nhật giá: các phòng trong phạm vi đã chọn chưa có ghế hoặc đang có khách giữ ghế.';
        return redirect()->route('admin.seats.index', ['cinema_id' => $room->cinema_id, 'room_id' => $room->id])
            ->with($result['rooms'] > 0 ? 'success' : 'error', $message)
            ->with('seat_price_skipped', $result['skipped']);
    }

    public function applyLayoutToAll(Request $request, Room $room)
    {
        $request->validate(['confirm_apply_all' => ['required', 'accepted']]);

        $result = DB::transaction(function () use ($room) {
            // Lock rooms in the same order for concurrent layout applications.
            $rooms = Room::with('cinema')->orderBy('id')->lockForUpdate()->get();
            $sourceSeats = Seat::where('room_id', $room->id)->lockForUpdate()->get();
            if ($sourceSeats->isEmpty()) {
                return null;
            }

            $sourceCodes = $sourceSeats->map(fn ($seat) => $seat->seat_row . ':' . $seat->seat_number)->flip();
            $heldRoomIds = $this->roomsWithActiveSeatHolds();
            $updated = 0;
            $cinemas = collect();
            $skipped = [];

            foreach ($rooms->where('id', '!=', $room->id) as $target) {
                $seats = Seat::where('room_id', $target->id)->lockForUpdate()->get();
                $label = ($target->cinema->name ?? 'Rạp') . ' — ' . $target->name;
                if ($target->showtimes()->whereHas('tickets')->exists()
                    || $target->seats()->whereHas('tickets')->exists()) {
                    $skipped[] = $label . ': đã có vé.';
                    continue;
                }
                if ($heldRoomIds !== null ? $heldRoomIds->has($target->id) : $this->hasActiveSeatHolds($target, $seats)) {
                    $skipped[] = $label . ': đang có khách giữ ghế.';
                    continue;
                }

                // Keep IDs of matching seats; remove only positions outside the source layout.
                $removedIds = $seats->filter(fn ($seat) => ! $sourceCodes->has($seat->seat_row . ':' . $seat->seat_number))->pluck('id');
                if ($removedIds->isNotEmpty()) {
                    Seat::whereIn('id', $removedIds)->delete();
                }
                $timestamp = now();
                $records = $sourceSeats->map(fn ($seat) => [
                    'room_id' => $target->id,
                    'seat_row' => $seat->seat_row,
                    'seat_number' => $seat->seat_number,
                    'seat_type' => $seat->seat_type,
                    'status' => $seat->status,
                    'extra_price' => $seat->extra_price,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                foreach ($records->chunk(100) as $chunk) {
                    Seat::upsert($chunk->all(), ['room_id', 'seat_row', 'seat_number'], ['seat_type', 'status', 'extra_price', 'updated_at']);
                }
                $target->update(['total_seats' => $sourceSeats->count()]);
                $updated++;
                $cinemas->push($target->cinema_id);
            }

            return ['updated' => $updated, 'cinemas' => $cinemas->unique()->count(), 'skipped' => $skipped];
        });

        $redirect = redirect()->route('admin.seats.index', ['cinema_id' => $room->cinema_id, 'room_id' => $room->id]);
        if ($result === null) {
            return $redirect->with('error', 'Phòng nguồn chưa có sơ đồ ghế. Hãy tạo sơ đồ trước khi áp dụng.');
        }
        $message = $result['updated'] > 0
            ? "Đã áp dụng sơ đồ của phòng {$room->name} cho {$result['updated']} phòng chiếu thuộc {$result['cinemas']} rạp."
            : 'Chưa có phòng nào có thể áp dụng sơ đồ.';

        return $redirect->with($result['updated'] > 0 ? 'success' : 'error', $message)
            ->with('seat_layout_skipped', $result['skipped']);
    }

    /**
     * Inspect existing holds once, rather than every possible seat/showtime pair.
     * Null keeps the per-room fallback for cache drivers without key enumeration.
     */
    private function roomsWithActiveSeatHolds(): ?Collection
    {
        $store = Cache::getStore();
        if ($store instanceof DatabaseStore) {
            $prefix = $store->getPrefix();
            $table = config('cache.stores.' . config('cache.default') . '.table', 'cache');
            $keys = $store->getConnection()->table($table)
                ->where('expiration', '>', now()->timestamp)
                ->where('key', 'like', $prefix . 'booking-hold:%')
                ->pluck('key')
                ->filter(fn ($key) => str_starts_with($key, $prefix . 'booking-hold:'))
                ->map(fn ($key) => substr($key, strlen($prefix)));
        } elseif ($store instanceof ArrayStore) {
            $keys = collect(array_keys($store->all(false)))
                ->filter(fn ($key) => str_starts_with($key, 'booking-hold:'));
        } else {
            return null;
        }

        $showtimeIds = collect();
        foreach ($keys->chunk(100) as $chunk) {
            // Let the cache driver deserialize values and enforce its own expiry.
            foreach (Cache::many($chunk->values()->all()) as $key => $hold) {
                if (is_array($hold) && (int) ($hold['expires_at'] ?? 0) > now()->timestamp
                    && preg_match('/^booking-hold:(\d+):/', $key, $matches)) {
                    $showtimeIds->push((int) $matches[1]);
                }
            }
        }

        $roomIds = collect();
        foreach ($showtimeIds->unique()->chunk(500) as $chunk) {
            $roomIds = $roomIds->merge(Showtime::whereIn('id', $chunk->all())
                ->where('end_time', '>', now())->pluck('room_id'));
        }

        return $roomIds->unique()->flip();
    }

    private function hasActiveSeatHolds(Room $room, Collection $seats): bool
    {
        if ($seats->isEmpty()) {
            return false;
        }
        foreach ($room->showtimes()->where('end_time', '>', now())->select('id')->cursor() as $showtime) {
            $keys = $seats->map(fn ($seat) => "booking-hold:{$showtime->id}:" . strtoupper($seat->seat_row . $seat->seat_number));
            foreach ($keys->chunk(100) as $chunk) {
                foreach (Cache::many($chunk->all()) as $hold) {
                    if (is_array($hold) && (int) ($hold['expires_at'] ?? 0) > now()->timestamp) {
                        return true;
                    }
                }
            }
        }
        return false;
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
        $rules = [
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
        ];

        if ($ignoreId === null) {
            $rules['cinema_id'] = ['required', 'integer', 'exists:cinemas,id'];
            $rules['room_id'] = ['required', 'integer', Rule::exists('rooms', 'id')->where(
                'cinema_id', filter_var($request->input('cinema_id'), FILTER_VALIDATE_INT) ?: 0
            )];
        }

        $data = $request->validate($rules, [
            'cinema_id.required' => 'Vui lòng chọn rạp.',
            'cinema_id.exists' => 'Rạp đã chọn không còn tồn tại.',
            'room_id.required' => 'Vui lòng chọn phòng chiếu.',
            'room_id.exists' => 'Phòng chiếu không thuộc rạp đã chọn hoặc không còn tồn tại.',
        ]);
        unset($data['cinema_id']);

        return $data;
    }
}
