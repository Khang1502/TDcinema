<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cinema;
use App\Models\Banner;
use App\Models\Room;
use App\Models\Seat;
use App\Support\CinemaRegions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CinemaController extends Controller
{
    public function index(Request $request)
    {
        $directory = Cinema::orderBy('name')->get(['id', 'name', 'city', 'region', 'status']);
        $regions = CinemaRegions::groups($directory);
        $cinemaCount = $directory->count();
        $selectedRegion = array_key_exists($request->string('region')->toString(), CinemaRegions::LABELS)
            ? $request->string('region')->toString() : '';
        $cities = $directory->when($selectedRegion !== '', fn ($items) => $items->where('region_key', $selectedRegion))
            ->pluck('city')->filter()->unique()->sort()->values();
        $filters = [
            'region' => $selectedRegion,
            'city' => $cities->contains($request->query('city')) ? $request->query('city') : '',
            'status' => in_array($request->query('status'), ['active', 'inactive', 'maintenance'], true) ? $request->query('status') : '',
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 255),
            'view'=>$request->query('view') === 'list' ? 'list' : 'grid',
        ];
        $cinemas = Cinema::withCount('rooms')
            ->when($filters['region'] !== '', fn ($query) => $query->whereIn('id', $regions[$selectedRegion]['cinemas']->pluck('id')))
            ->when($filters['city'] !== '', fn ($query) => $query->where('city', $filters['city']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['q'] !== '', function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->where('name', 'like', '%'.$filters['q'].'%')->orWhere('address', 'like', '%'.$filters['q'].'%');
                });
            })
            ->orderBy('name')
            ->paginate(10)->withQueryString();

        $counts = ['active'=>$directory->where('status','active')->count(),'maintenance'=>$directory->where('status','maintenance')->count(),
            'inactive'=>$directory->where('status','inactive')->count(),'rooms'=>Room::count()];
        return view('admin.cinemas.index', compact('cinemas','regions','cities','filters','cinemaCount','counts'));
    }

    public function create()
    {
        $regionOptions = CinemaRegions::LABELS;
        $citySuggestions = CinemaRegions::citySuggestions(Cinema::get(['city']));

        return view('admin.cinemas.create', compact('regionOptions', 'citySuggestions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $path = $this->uploadedImage($request);
        try {
            $cinema = Cinema::create([...$data, 'image_path' => $path]);
        } catch (Throwable $exception) {
            $this->deleteOwnedImage($path);
            throw $exception;
        }

        // Sau khi tạo rạp, chuyển thẳng sang trang Sửa để thêm phòng chiếu
        return redirect()
            ->route('admin.cinemas.edit', $cinema)
            ->with('success', 'Đã tạo rạp. Thêm phòng chiếu cho rạp này.');
    }

    public function edit(Cinema $cinema)
    {
        $cinema->loadCount('rooms')->load(['rooms' => fn ($query) => $query->orderBy('id')]);
        $regionOptions = CinemaRegions::LABELS;
        $citySuggestions = CinemaRegions::citySuggestions(Cinema::get(['city']));

        return view('admin.cinemas.edit', compact('cinema', 'regionOptions', 'citySuggestions'));
    }

    public function update(Request $request, Cinema $cinema)
    {
        $data = $this->validatedData($request, $cinema->id);
        $oldPath = $cinema->image_path;
        $newPath = $request->hasFile('image') ? $this->uploadedImage($request) : null;
        $path = $newPath ?? ($request->boolean('remove_image') ? null : $oldPath);
        try {
            $cinema->update([...$data, 'image_path' => $path]);
        } catch (Throwable $exception) {
            $this->deleteOwnedImage($newPath);
            throw $exception;
        }
        if ($oldPath !== $path) $this->deleteOwnedImage($oldPath);

        return redirect()->route('admin.cinemas.index')->with('success', 'Đã cập nhật rạp.');
    }

    public function destroy(Cinema $cinema)
    {
        $hasShowtimes = $cinema->rooms()->whereHas('showtimes')->exists();

        if ($hasShowtimes) {
            $cinema->update(['status' => 'inactive']);
            return redirect()->route('admin.cinemas.index')->with('warning', 'Rạp có suất chiếu/vé. Đã chuyển sang trạng thái ngưng hoạt động.');
        }

        $path = $cinema->image_path;
        $cinema->delete();
        $this->deleteOwnedImage($path);

        return redirect()->route('admin.cinemas.index')->with('success', 'Đã xoá rạp.');
    }

    private function validatedData(Request $request, $cinemaId = null): array
    {
        $data = $request->validate([
            'name'    => ['required', 'string', 'max:255', 'unique:cinemas,name' . ($cinemaId ? ',' . $cinemaId : '')],
            'address' => ['nullable', 'string', 'max:255'],
            'city'    => ['nullable', 'string', 'max:255'],
            'region'  => ['nullable', Rule::in(array_keys(CinemaRegions::LABELS))],
            'status'  => ['nullable', 'in:active,inactive,maintenance'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ], [
            'image.image' => 'Vui lòng chọn một tệp hình ảnh hợp lệ.',
            'image.mimes' => 'Ảnh rạp phải có định dạng JPG, PNG hoặc WEBP.',
            'image.max' => 'Ảnh rạp không được vượt quá 4 MB.',
        ], ['image' => 'ảnh rạp', 'remove_image' => 'gỡ ảnh rạp']);
        unset($data['image'], $data['remove_image']);

        // Mặc định nếu không chọn sẽ là active
        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        return $data;
    }

    private function uploadedImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) return null;

        $path = $request->file('image')->store('cinemas', 'public');
        if (! $path) {
            throw ValidationException::withMessages(['image' => 'Không thể lưu ảnh rạp. Vui lòng thử lại.']);
        }

        return $path;
    }

    private function deleteOwnedImage(?string $path): void
    {
        if ($path && preg_match('#^cinemas/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$#', $path)
            && ! Cinema::where('image_path', $path)->exists()
            && ! Banner::where('image_path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    // Phòng chiếu: thêm nhanh trong trang rạp
    public function storeRoom(Request $request, Cinema $cinema)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'type'        => ['required', 'in:2D,3D,IMAX,VIP'],
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
            'type'        => ['required', 'in:2D,3D,IMAX,VIP'],
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
