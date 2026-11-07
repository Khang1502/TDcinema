@php $isLiked = in_array($item->id, $cornerLikedIds ?? [], true); @endphp
<div class="editorial-engagement">
    <button type="button" class="editorial-like {{ $isLiked ? 'is-liked' : '' }}" data-corner-like="{{ $item->id }}" data-url="{{ route('cinema-corner.like', $item) }}" aria-pressed="{{ $isLiked ? 'true' : 'false' }}" aria-label="{{ $isLiked ? 'Bỏ thích' : 'Thích' }} bài {{ $item->displayTitle() }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 10H3v11h4V10ZM7 20h11a2 2 0 0 0 2-1.7l1-7A2 2 0 0 0 19 9h-6l1-4a2 2 0 0 0-4-1l-3 6v10Z"/></svg>
        <span data-like-label>{{ $isLiked ? 'Đã thích' : 'Thích' }}</span><span data-like-count>{{ $item->likes_count ?: '' }}</span>
    </button>
    <span class="editorial-views" title="Lượt xem"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>{{ number_format($item->views_count ?? 0, 0, ',', '.') }}<span class="editorial-sr-only"> lượt xem</span></span>
    <span class="editorial-like-message" data-like-message="{{ $item->id }}" role="status"></span>
</div>
