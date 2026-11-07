<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CinemaCornerItem;
use App\Models\CinemaCornerSetting;
use App\Models\Movie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CinemaCornerController extends Controller
{
    private const LINK_RULE = 'regex:#^(?:https?://[^\s]+|/(?!/)[^\s]*)$#i';

    public function index(Request $request): View
    {
        $settings = CinemaCornerSetting::current();
        $category = array_key_exists($request->query('category', ''), CinemaCornerItem::CATEGORIES) ? $request->query('category') : '';
        $items = CinemaCornerItem::with('movie')->withCount('likes')->when($category, fn ($query) => $query->where('category', $category))->orderBy('sort_order')->orderBy('id')->paginate(12)->withQueryString();
        $total = CinemaCornerItem::count();
        $active = CinemaCornerItem::published()->count();

        return view('admin.cinema-corner.index', compact('settings', 'items', 'total', 'active', 'category'));
    }

    public function create(): View
    {
        return $this->form(new CinemaCornerItem(['category' => request('category') === 'blog' ? 'blog' : 'review', 'sort_order' => (CinemaCornerItem::max('sort_order') ?? 0) + 1, 'is_active' => true]));
    }

    public function edit(CinemaCornerItem $cornerItem): View
    {
        return $this->form($cornerItem->load('movie'));
    }

    private function form(CinemaCornerItem $item): View
    {
        $movies = Movie::orderBy('title')->get();
        $settings = CinemaCornerSetting::current();

        return view('admin.cinema-corner.form', compact('item', 'movies', 'settings'));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new CinemaCornerItem());
    }

    public function update(Request $request, CinemaCornerItem $cornerItem): RedirectResponse
    {
        return $this->save($request, $cornerItem);
    }

    private function save(Request $request, CinemaCornerItem $item): RedirectResponse
    {
        $request->merge(['category' => $request->input('category', $item->category ?? 'review')]);
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(CinemaCornerItem::CATEGORIES))],
            'movie_id' => [Rule::requiredIf($request->input('image_source') === 'movie'), 'nullable', 'integer', 'exists:movies,id'],
            'title' => ['required_without:movie_id', 'nullable', 'string', 'max:191'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'content' => ['required_without:movie_id', 'nullable', 'string', 'max:15000'],
            'trailer_url' => ['nullable', 'string', 'max:2048', self::LINK_RULE],
            'image_source' => ['required', Rule::in(['movie', 'upload'])],
            'image' => [Rule::requiredIf($request->input('image_source') === 'upload' && !$item->image_path), 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'placement' => ['required', Rule::in(['featured', 'list'])],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], ['image.required' => 'Vui lòng chọn ảnh riêng hoặc dùng ảnh có sẵn của phim.', 'trailer_url.regex' => 'Trailer phải bắt đầu bằng /, http:// hoặc https://.'], [
            'movie_id' => 'phim', 'title' => 'tiêu đề', 'excerpt' => 'mô tả', 'content' => 'nội dung bài viết', 'image' => 'ảnh', 'sort_order' => 'thứ tự',
        ]);
        $fallback = new CinemaCornerItem(['content' => null, 'excerpt' => $data['excerpt'] ?? null]);
        $fallback->setRelation('movie', isset($data['movie_id']) ? Movie::find($data['movie_id']) : null);
        if ($fallback->movie && blank($item->content) && filled($data['content'] ?? null) && $data['content'] === $fallback->editorContent()) {
            $data['content'] = null;
        }
        $fallback->content = $data['content'] ?? null;
        $fallback->excerpt = null;
        if ($fallback->movie && blank($item->excerpt) && filled($data['excerpt'] ?? null) && $data['excerpt'] === $fallback->editorExcerpt()) {
            $data['excerpt'] = null;
        }
        $oldPath = $item->image_path;
        $newUpload = $request->input('image_source') === 'upload' && $request->hasFile('image');
        $path = $request->input('image_source') === 'movie' ? null : ($newUpload ? $request->file('image')->store('cinema-corner', 'public') : $oldPath);
        $featured = $data['placement'] === 'featured';
        unset($data['image_source'], $data['image'], $data['placement']);
        try {
            DB::transaction(function () use ($item, $data, $path, $featured) {
                $settings = CinemaCornerSetting::firstOrCreate(['id' => 1], CinemaCornerSetting::DEFAULTS);
                $settings = CinemaCornerSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
                $item->fill([...$data, 'image_path' => $path])->save();
                $field = $item->category === 'blog' ? 'blog_featured_item_id' : 'featured_item_id';
                $otherField = $item->category === 'blog' ? 'featured_item_id' : 'blog_featured_item_id';
                if ($settings->$otherField === $item->id) $settings->update([$otherField => null]);
                if ($featured) $settings->update([$field => $item->id]);
                elseif ($settings->$field === $item->id) $settings->update([$field => null]);
            });
        } catch (Throwable $exception) {
            if ($newUpload) $this->deleteOwnedImage($path);
            throw $exception;
        }
        if ($oldPath !== $path) $this->deleteOwnedImage($oldPath);

        return redirect()->route('admin.cinema-corner.index')->with('success', 'Đã lưu nội dung Góc Điện Ảnh.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'], 'eyebrow' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:300'], 'featured_label' => ['required', 'string', 'max:60'],
            'link_label' => ['nullable', 'string', 'max:60'], 'link_url' => ['nullable', 'string', 'max:2048', self::LINK_RULE],
            'list_limit' => ['required', 'integer', 'min:1', 'max:6'], 'is_active' => ['required', 'boolean'],
        ], ['link_url.regex' => 'Liên kết phải bắt đầu bằng /, http:// hoặc https://.']);
        CinemaCornerSetting::firstOrCreate(['id' => 1], CinemaCornerSetting::DEFAULTS)->update($data);

        return redirect()->route('admin.cinema-corner.index')->with('success', 'Đã cập nhật khu vực Góc Điện Ảnh trên trang chủ.');
    }

    public function visibility(Request $request, CinemaCornerItem $cornerItem): RedirectResponse
    {
        $cornerItem->update($request->validate(['is_active' => ['required', 'boolean']]));

        return redirect()->route('admin.cinema-corner.index')->with('success', $cornerItem->is_active ? 'Đã bật nội dung.' : 'Đã ẩn nội dung.');
    }

    public function order(Request $request): RedirectResponse
    {
        $data = $request->validate(['orders' => ['required', 'array', 'min:1', 'max:100'], 'orders.*' => ['required', 'integer', 'min:0', 'max:9999']]);
        $ids = array_keys($data['orders']);
        foreach ($ids as $id) {
            if (!ctype_digit((string) $id)) throw ValidationException::withMessages(['orders' => 'Danh sách nội dung không hợp lệ.']);
        }
        if (CinemaCornerItem::whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['orders' => 'Nội dung đã thay đổi. Vui lòng tải lại danh sách.']);
        }
        DB::transaction(function () use ($data) {
            foreach ($data['orders'] as $id => $position) CinemaCornerItem::whereKey($id)->update(['sort_order' => $position]);
        });

        return redirect()->route('admin.cinema-corner.index')->with('success', 'Đã lưu thứ tự nội dung.');
    }

    public function destroy(CinemaCornerItem $cornerItem): RedirectResponse
    {
        $path = $cornerItem->image_path;
        $cornerItem->delete();
        $this->deleteOwnedImage($path);

        return redirect()->route('admin.cinema-corner.index')->with('success', 'Đã xóa nội dung khỏi Góc Điện Ảnh.');
    }

    private function deleteOwnedImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'cinema-corner/') && !CinemaCornerItem::where('image_path', $path)->exists()) Storage::disk('public')->delete($path);
    }
}
