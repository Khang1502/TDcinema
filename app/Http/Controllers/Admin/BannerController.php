<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Cinema;
use App\Models\Movie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class BannerController extends Controller
{
    public function index(Request $request): View
    {
        $placement = array_key_exists($request->string('placement')->toString(), Banner::PLACEMENTS) ? $request->string('placement')->toString() : '';
        $query = Banner::query()->when($placement !== '', fn ($query) => $query->where('placement', $placement));
        $total = (clone $query)->count();
        $active = (clone $query)->where('is_active', true)->count();
        $banners = $query->with(['movie', 'cinema'])->orderBy('placement')->orderBy('sort_order')->orderBy('id')->paginate(12)->withQueryString();
        $placementCounts = Banner::selectRaw('placement, COUNT(*) as total')->groupBy('placement')->pluck('total', 'placement');

        return view('admin.banners.index', compact('banners', 'total', 'active', 'placement', 'placementCounts'));
    }

    public function create(Request $request): View
    {
        $placement = array_key_exists($request->string('placement')->toString(), Banner::PLACEMENTS) ? $request->string('placement')->toString() : 'home';
        $banner = new Banner(['placement' => $placement, 'is_active' => true, 'sort_order' => (Banner::where('placement', $placement)->max('sort_order') ?? 0) + 1]);
        $movies = Movie::orderBy('title')->get(['id', 'title', 'banner_path']);
        $cinemas = Cinema::orderBy('name')->get(['id', 'name', 'image_path']);

        return view('admin.banners.form', compact('banner', 'movies', 'cinemas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $path = $this->imagePath($request);
        try {
            Banner::create([...$data, 'image_path' => $path]);
        } catch (Throwable $exception) {
            if ($request->hasFile('image')) $this->deleteOwnedImage($path);
            throw $exception;
        }

        return redirect()->route('admin.banners.index', $data['placement'] === 'cinemas' ? ['placement' => 'cinemas'] : [])->with('success', 'Đã thêm banner.');
    }

    public function edit(Banner $banner): View
    {
        $movies = Movie::orderBy('title')->get(['id', 'title', 'banner_path']);
        $cinemas = Cinema::orderBy('name')->get(['id', 'name', 'image_path']);

        return view('admin.banners.form', compact('banner', 'movies', 'cinemas'));
    }

    public function update(Request $request, Banner $banner): RedirectResponse
    {
        $data = $this->validated($request, $banner);
        $oldPath = $banner->image_path;
        $path = $this->imagePath($request, $banner);
        try {
            $banner->update([...$data, 'image_path' => $path]);
        } catch (Throwable $exception) {
            if ($request->hasFile('image')) $this->deleteOwnedImage($path);
            throw $exception;
        }
        if ($oldPath !== $path) $this->deleteOwnedImage($oldPath);

        return redirect()->route('admin.banners.index', $data['placement'] === 'cinemas' ? ['placement' => 'cinemas'] : [])->with('success', 'Đã cập nhật banner.');
    }

    public function destroy(Banner $banner): RedirectResponse
    {
        $path = $banner->image_path;
        $banner->delete();
        $this->deleteOwnedImage($path);

        return redirect()->route('admin.banners.index', $banner->placement === 'cinemas' ? ['placement' => 'cinemas'] : [])->with('success', 'Đã xóa banner.');
    }

    public function visibility(Request $request, Banner $banner): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $banner->update($data);

        return redirect()->route('admin.banners.index', $banner->placement === 'cinemas' ? ['placement' => 'cinemas'] : [])->with('success', $banner->is_active ? 'Đã bật banner tại '.$banner->placement_label.'.' : 'Đã ẩn banner tại '.$banner->placement_label.'.');
    }

    public function order(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'orders' => ['required', 'array', 'min:1', 'max:100'],
            'orders.*' => ['required', 'integer', 'min:0', 'max:9999'],
            'placement' => ['sometimes', 'required', Rule::in(array_keys(Banner::PLACEMENTS))],
        ], [], ['orders' => 'thứ tự banner', 'orders.*' => 'thứ tự']);
        $ids = array_keys($data['orders']);
        foreach ($ids as $id) {
            if (!ctype_digit((string) $id)) {
                throw ValidationException::withMessages(['orders' => 'Danh sách banner không hợp lệ.']);
            }
        }
        if (Banner::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['orders' => 'Một banner đã bị xóa. Vui lòng tải lại danh sách.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data['orders'] as $id => $position) {
                Banner::whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return redirect()->route('admin.banners.index', isset($data['placement']) ? ['placement' => $data['placement']] : [])->with('success', 'Đã lưu thứ tự banner.');
    }

    private function validated(Request $request, ?Banner $banner = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'placement' => ['sometimes', 'required', Rule::in(array_keys(Banner::PLACEMENTS))],
            'image_source' => ['required', Rule::in(['upload', 'movie', 'cinema'])],
            'image' => [Rule::requiredIf(!$banner && $request->input('image_source') === 'upload'), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'movie_id' => [Rule::requiredIf($request->input('image_source') === 'movie'), 'nullable', 'integer', 'exists:movies,id'],
            'cinema_id' => [Rule::requiredIf($request->input('image_source') === 'cinema'), 'nullable', 'integer', 'exists:cinemas,id'],
            'link_url' => ['nullable', 'string', 'max:2048', 'regex:#^(?:https?://[^\s]+|/(?!/)[^\s]*)$#i'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], [
            'image.required' => 'Vui lòng chọn ảnh banner.',
            'movie_id.required' => 'Vui lòng chọn phim để lấy ảnh banner.',
            'cinema_id.required' => 'Vui lòng chọn rạp để lấy ảnh banner.',
            'link_url.regex' => 'Dùng liên kết bắt đầu bằng /, http:// hoặc https://.',
        ], ['title' => 'tên banner', 'image' => 'ảnh banner', 'movie_id' => 'phim', 'cinema_id' => 'rạp', 'placement' => 'vị trí hiển thị', 'sort_order' => 'thứ tự', 'is_active' => 'trạng thái']);

        unset($data['image_source'], $data['image']);
        $data['placement'] = $data['placement'] ?? $banner?->placement ?? 'home';
        $data['cinema_id'] = $request->input('image_source') === 'cinema' ? $data['cinema_id'] : null;
        if ($request->input('image_source') === 'cinema') $data['movie_id'] = null;

        return $data;
    }

    private function imagePath(Request $request, ?Banner $banner = null): string
    {
        if ($request->input('image_source') === 'cinema') {
            $cinema = Cinema::findOrFail($request->integer('cinema_id'));
            if (! $cinema->hasImage()) {
                throw ValidationException::withMessages(['cinema_id' => 'Rạp này chưa có ảnh. Hãy thêm ảnh cho rạp hoặc tải ảnh banner riêng.']);
            }

            return $cinema->image_path;
        }
        if ($request->input('image_source') === 'movie') {
            $path = Movie::findOrFail($request->integer('movie_id'))->banner_path;
            if (!$path) {
                throw ValidationException::withMessages(['movie_id' => 'Phim này chưa có ảnh banner. Hãy chọn phim khác hoặc tải ảnh lên.']);
            }
            return $path;
        }
        if ($request->hasFile('image')) {
            return $request->file('image')->store('banners', 'public');
        }

        return $banner->image_path;
    }

    private function deleteOwnedImage(string $path): void
    {
        $owned = str_starts_with($path, 'banners/')
            || (preg_match('#^cinemas/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)$#', $path) && ! Cinema::where('image_path', $path)->exists());
        if ($owned && ! Banner::where('image_path', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }
}
