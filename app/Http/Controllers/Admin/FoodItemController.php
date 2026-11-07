<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Storage;

class FoodItemController extends Controller
{
    public function index(Request $request): View
    {
        $query = FoodItem::query()->orderByDesc('created_at');

        $search = trim((string) $request->get('q', ''));
        $status = $request->get('status');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['available', 'unavailable'], true)) {
            $query->where('status', $status);
        }

        $items = $query->paginate(10)->withQueryString();

        $filters = [
            'q' => $search,
            'status' => $status,
        ];

        return view('admin.foods.index', compact('items', 'filters'));
    }

    public function create(): View
    {
        return view('admin.foods.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:available,unavailable'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['image']);
        if ($request->hasFile('image')) {
            /** @var UploadedFile $file */
            $file = $request->file('image');
            $data['image'] = $file->store('foods', 'public');
        }

        FoodItem::create($data);

        return redirect()->route('admin.foods.index')->with('success', 'Đã tạo dịch vụ/đồ ăn.');
    }

    public function edit(FoodItem $food): View
    {
        return view('admin.foods.edit', compact('food'));
    }

    public function update(Request $request, FoodItem $food): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:available,unavailable'],
            'image' => ['nullable', 'image', 'max:2048'],
        ]);

        $oldImage = $food->image;
        unset($data['image']);
        if ($request->hasFile('image')) {
            /** @var UploadedFile $file */
            $file = $request->file('image');
            $data['image'] = $file->store('foods', 'public');
        }

        $food->update($data);
        if ($oldImage !== $food->image) $this->deleteUnusedImage($oldImage);

        return redirect()->route('admin.foods.index')->with('success', 'Đã cập nhật dịch vụ/đồ ăn.');
    }

    public function destroy(FoodItem $food): RedirectResponse
    {
        if ($food->orderItems()->exists()) {
            $food->update(['status' => 'unavailable']);
            return redirect()->route('admin.foods.index')->with('warning', 'Mục này đã có trong hóa đơn, hệ thống chỉ chuyển sang Ngừng cung cấp.');
        }
        $oldImage = $food->image;
        $food->delete();
        $this->deleteUnusedImage($oldImage);
        return redirect()->route('admin.foods.index')->with('success', 'Đã xóa dịch vụ/đồ ăn.');
    }
    private function deleteUnusedImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'foods/') && !FoodItem::where('image', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }
}
