<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Promotion::query();

        $search = trim((string) $request->get('q', ''));
        $status = $request->get('status');
        $type = $request->get('type');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (in_array($type, ['percent', 'fixed'], true)) {
            $query->where('type', $type);
        }

        // Trạng thái động dựa trên ngày
        $today = Carbon::today();
        if ($status === 'upcoming') {
            $query->whereDate('start_date', '>', $today);
        } elseif ($status === 'active') {
            $query->whereDate('start_date', '<=', $today)
                  ->whereDate('end_date', '>=', $today)
                  ->where('status', 'active');
        } elseif ($status === 'expired') {
            $query->where(function ($q) use ($today) {
                $q->whereDate('end_date', '<', $today)
                  ->orWhere('status', 'expired');
            });
        }

        $promotions = $query->orderByDesc('start_date')->paginate(10)->withQueryString();

        $filters = [
            'q' => $search,
            'status' => $status,
            'type' => $type,
        ];

        return view('admin.promotions.index', compact('promotions', 'filters'));
    }

    public function create(): View
    {
        return view('admin.promotions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['thumbnail'] = $this->handleThumbnail($request);
        Promotion::create($data);

        return redirect()->route('admin.promotions.index')->with('success', 'Đã tạo khuyến mãi.');
    }

    public function edit(Promotion $promotion): View
    {
        return view('admin.promotions.edit', compact('promotion'));
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $data = $this->validated($request, $promotion->id);
        if ($request->hasFile('thumbnail')) {
            $this->deleteThumbnail($promotion->thumbnail);
            $data['thumbnail'] = $this->handleThumbnail($request);
        }
        $promotion->update($data);

        return redirect()->route('admin.promotions.index')->with('success', 'Đã cập nhật khuyến mãi.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $promotion->delete();
        return redirect()->route('admin.promotions.index')->with('success', 'Đã xóa khuyến mãi.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $uniqueCode = 'unique:promotions,code';
        if ($id) {
            $uniqueCode .= ',' . $id;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', $uniqueCode],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,expired'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        return $data;
    }

    private function handleThumbnail(Request $request): ?string
    {
        if (!$request->hasFile('thumbnail')) {
            return null;
        }
        return $request->file('thumbnail')->store('promotions', 'public');
    }

    private function deleteThumbnail(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
