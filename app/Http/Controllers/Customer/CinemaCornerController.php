<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CinemaCornerItem;
use App\Models\CinemaCornerLike;
use App\Models\Movie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CinemaCornerController extends Controller
{
    public function index(Request $request, string $category): View
    {
        abort_unless(isset(CinemaCornerItem::CATEGORIES[$category]), 404);
        $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim((string) $request->input('q', ''));
        $items = CinemaCornerItem::with('movie')->withCount('likes')->published()->where('category', $category)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('title', 'like', '%' . $search . '%')->orWhereHas('movie', fn ($movie) => $movie->where('title', 'like', '%' . $search . '%'))))
            ->orderBy('sort_order')->orderByDesc('id')->paginate(8)->withQueryString();

        return view('frontend.cinema-corner.index', [
            ...$this->shared(), 'items' => $items, 'category' => $category, 'search' => $search,
            'pageTitle' => CinemaCornerItem::CATEGORIES[$category],
        ]);
    }

    public function show(Request $request, CinemaCornerItem $cornerItem): View
    {
        abort_unless(CinemaCornerItem::published()->whereKey($cornerItem->id)->exists(), 404);
        $key = 'cinema_corner.viewed.' . $cornerItem->id;
        if (! $request->session()->has($key)) {
            $cornerItem->increment('views_count');
            $request->session()->put($key, true);
        }
        $cornerItem->load('movie')->loadCount('likes');
        $related = CinemaCornerItem::with('movie')->withCount('likes')->published()
            ->where('category', $cornerItem->category)->whereKeyNot($cornerItem->id)->orderBy('sort_order')->limit(3)->get();

        return view('frontend.cinema-corner.show', [
            ...$this->shared(), 'item' => $cornerItem, 'related' => $related,
            'category' => $cornerItem->category, 'pageTitle' => $cornerItem->displayTitle(),
        ]);
    }

    public function like(Request $request, CinemaCornerItem $cornerItem): JsonResponse
    {
        abort_unless(CinemaCornerItem::published()->whereKey($cornerItem->id)->exists(), 404);
        $data = $request->validate(['liked' => ['required', 'boolean']]);
        $attributes = ['cinema_corner_item_id' => $cornerItem->id, 'user_id' => $request->user()->id];
        if ($data['liked']) CinemaCornerLike::firstOrCreate($attributes);
        else CinemaCornerLike::where($attributes)->delete();

        return response()->json(['liked' => (bool) $data['liked'], 'likes' => $cornerItem->likes()->count()]);
    }

    private function shared(): array
    {
        $nowShowing = Movie::where('status', 'now_showing')->orderByDesc('release_date')->limit(8)->get();
        return [
            'nowShowing' => $nowShowing,
            'cornerLikedIds' => auth()->check() ? CinemaCornerLike::where('user_id', auth()->id())->pluck('cinema_corner_item_id')->all() : [],
        ];
    }
}
