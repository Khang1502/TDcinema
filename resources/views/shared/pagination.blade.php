@if ($paginator->hasPages() && $paginator->count())
    @php
        $current = $paginator->currentPage();
        $last = $paginator->lastPage();
        $start = max(1, $current - 1);
        $end = min($last, $current + 1);
        $pages = collect([1, ...range($start, $end), $last])->unique()->sort()->values();
    @endphp
    <nav class="td-pagination" aria-label="Phân trang">
        <p class="td-pagination__label">Trang <strong>{{ $current }}</strong> / {{ $last }}</p>
        <div class="td-pagination__controls">
            @unless ($paginator->onFirstPage())
                <a class="td-page td-page--arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12 5-5 5 5 5" /></svg><span class="td-pagination__arrow-label">Trước</span>
                </a>
            @endunless
            <div class="td-pagination__numbers">
                @foreach ($pages as $page)
                    @if ($loop->index > 0 && $page - $pages[$loop->index - 1] > 1)
                        <span class="td-pagination__dots" aria-hidden="true">…</span>
                    @endif
                    @if ($page === $current)
                        <span class="td-page is-active" aria-current="page" aria-label="Trang {{ $page }}">{{ $page }}</span>
                    @else
                        <a @class(['td-page', 'td-page--boundary' => abs($page - $current) > 1]) href="{{ $paginator->url($page) }}" aria-label="Đi tới trang {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            </div>
            @if ($paginator->hasMorePages())
                <a class="td-page td-page--arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">
                    <span class="td-pagination__arrow-label">Sau</span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m8 5 5 5-5 5" /></svg>
                </a>
            @endif
        </div>
    </nav>
@endif
