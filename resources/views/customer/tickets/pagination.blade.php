@if ($paginator->hasPages())
    <nav class="ticket-pagination" role="navigation" aria-label="Phân trang lịch sử đặt vé">
        <p class="ticket-pagination__summary">
            Hiển thị <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            trong tổng số <strong>{{ $paginator->total() }}</strong> vé
        </p>

        <div class="ticket-pagination__controls">
            @if ($paginator->onFirstPage())
                <span class="ticket-page ticket-page--arrow is-disabled" aria-disabled="true" aria-label="Trang trước">‹</span>
            @else
                <a class="ticket-page ticket-page--arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="ticket-page ticket-page--dots" aria-hidden="true">…</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class="ticket-page is-active" aria-current="page" aria-label="Trang {{ $page }}">{{ $page }}</span>
                        @else
                            <a class="ticket-page" href="{{ $url }}" aria-label="Đi tới trang {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="ticket-page ticket-page--arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">›</a>
            @else
                <span class="ticket-page ticket-page--arrow is-disabled" aria-disabled="true" aria-label="Trang sau">›</span>
            @endif
        </div>
    </nav>
@endif
