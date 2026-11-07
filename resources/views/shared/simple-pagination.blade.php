@if ($paginator->hasPages() && $paginator->count())
    <nav class="td-pagination" aria-label="Phân trang">
        <p class="td-pagination__label">Trang <strong>{{ $paginator->currentPage() }}</strong></p>
        <div class="td-pagination__controls">
            @unless ($paginator->onFirstPage())
                <a class="td-page td-page--arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước"><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m12 5-5 5 5 5" /></svg><span class="td-pagination__arrow-label">Trước</span></a>
            @endunless
            @if ($paginator->hasMorePages())
                <a class="td-page td-page--arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau"><span class="td-pagination__arrow-label">Sau</span><svg viewBox="0 0 20 20" aria-hidden="true"><path d="m8 5 5 5-5 5" /></svg></a>
            @endif
        </div>
    </nav>
@endif
