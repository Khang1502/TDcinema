<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\PromotionDisplaySetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Promotion::where('is_member_gift', $request->routeIs('admin.gifts.*'));

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
            $query->upcoming();
        } elseif ($status === 'active') {
            $query->available();
        } elseif ($status === 'exhausted') {
            $query->where('status', 'active')->whereDate('end_date', '>=', $today)->where('usage_limit', 0);
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

        $countQuery = Promotion::where('is_member_gift', $request->routeIs('admin.gifts.*'));
        $counts = ['total' => (clone $countQuery)->count(), 'active' => (clone $countQuery)->available()->count(), 'upcoming' => (clone $countQuery)->upcoming()->count()];
        return view('admin.promotions.index', compact('promotions', 'filters', 'counts'));
    }

    public function create(): View
    {
        return view('admin.promotions.create');
    }

    public function display(): View
    {
        return view('admin.promotions.display', ['settings'=>PromotionDisplaySetting::current()]);
    }

    public function updateDisplay(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'=>['required','string','max:120'], 'eyebrow'=>['required','string','max:120'],
            'display_mode'=>['required','in:banner,cards'], 'is_visible'=>['required','boolean'],
            'autoplay'=>['required','boolean'], 'interval_seconds'=>['required','integer','between:3,20'],
        ]);
        PromotionDisplaySetting::updateOrCreate(['id'=>1], $data);
        return back()->with('success','Đã cập nhật cách hiển thị khuyến mãi trên trang chủ.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['remove_thumbnail']);
        $data['thumbnail'] = $this->handleThumbnail($request);
        Promotion::create($data);

        return redirect()->route('admin.promotions.index')->with('success', 'Đã tạo khuyến mãi.');
    }

    public function edit(Promotion $promotion): View
    {
        $this->checkCategory($promotion);
        return view('admin.promotions.edit', compact('promotion'));
    }

    public function update(Request $request, Promotion $promotion): RedirectResponse
    {
        $this->checkCategory($promotion);
        $data = $this->validated($request, $promotion->id);
        $oldThumbnail = $promotion->thumbnail;
        unset($data['thumbnail'], $data['remove_thumbnail']);
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $this->handleThumbnail($request);
        } elseif ($request->boolean('remove_thumbnail')) {
            $data['thumbnail'] = null;
        }
        $promotion->update($data);
        if ($oldThumbnail !== $promotion->thumbnail) $this->deleteThumbnail($oldThumbnail);

        return redirect()->route('admin.promotions.index')->with('success', 'Đã cập nhật khuyến mãi.');
    }

    public function destroy(Promotion $promotion): RedirectResponse
    {
        $this->checkCategory($promotion);
        $promotion->delete();
        return redirect()->route('admin.promotions.index')->with('success', 'Đã xóa khuyến mãi.');
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $gift = $request->routeIs('admin.gifts.*');
        $request->merge(['code' => strtoupper(trim((string) $request->input('code', ''))),
            'is_member_gift' => $gift,
            'min_member_points' => $gift ? $request->input('min_member_points', 0) : 0,
            'show_on_home' => $gift ? false : $request->boolean('show_on_home', true),
        ]);
        $uniqueCode = 'unique:promotions,code';
        if ($id) {
            $uniqueCode .= ',' . $id;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', $uniqueCode],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1', $request->input('type') === 'percent' ? 'max:100' : 'max:100000000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'usage_limit' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:active,expired'],
            'thumbnail' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_thumbnail' => ['sometimes', 'boolean'],
            'show_on_home' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'between:0,9999'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_member_gift' => ['sometimes', 'boolean'],
            'min_member_points' => ['sometimes', 'integer', 'between:0,100000000'],
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
        if ($path && str_starts_with($path, 'promotions/')
            && !Promotion::where('thumbnail', $path)->exists()
            && !\App\Models\Banner::where('image_path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }

    private function checkCategory(Promotion $promotion): void
    {
        abort_unless($promotion->is_member_gift === request()->routeIs('admin.gifts.*'), 404);
    }
}
