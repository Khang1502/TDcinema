@if($cornerSettings->is_active && $cornerTabs->contains(fn ($tab) => $tab['featured']))
@php $activeCornerTab = request('corner') === 'blog' ? 'blog' : ($cornerTabs['review']['featured'] ? 'review' : 'blog'); @endphp
<section id="cinema-corner" class="editorial-corner">
    <div class="editorial-container">
        @if($cornerSettings->eyebrow && $cornerSettings->eyebrow !== \App\Models\CinemaCornerSetting::DEFAULTS['eyebrow'])<p class="editorial-kicker">{{ $cornerSettings->eyebrow }}</p>@endif
        <div class="editorial-heading">
            <h2>{{ $cornerSettings->title }}</h2>
            <div class="editorial-tabs" role="tablist" aria-label="Chuyên mục Góc Điện Ảnh">
                @foreach($cornerTabs as $category => $tab)
                <button type="button" id="corner-tab-{{ $category }}" role="tab" data-corner-tab="{{ $category }}" aria-controls="corner-panel-{{ $category }}" aria-selected="{{ $activeCornerTab === $category ? 'true' : 'false' }}" tabindex="{{ $activeCornerTab === $category ? '0' : '-1' }}">{{ $tab['label'] }}</button>
                @endforeach
            </div>
        </div>
        @if($cornerSettings->description)<p class="editorial-intro">{{ $cornerSettings->description }}</p>@endif
        @foreach($cornerTabs as $category => $tab)
        <div id="corner-panel-{{ $category }}" role="tabpanel" aria-labelledby="corner-tab-{{ $category }}" data-corner-panel="{{ $category }}" @if($activeCornerTab !== $category) hidden @endif>
            @if($tab['featured'])
            @php $featured = $tab['featured']; @endphp
            <div class="editorial-grid {{ $tab['articles']->isEmpty() ? 'editorial-grid--single' : '' }}">
                <article class="editorial-feature" data-corner-item="{{ $featured->id }}">
                    <a class="editorial-feature-image" href="{{ route('cinema-corner.show', $featured) }}"><img src="{{ $featured->imageUrl(true) }}" alt="{{ $featured->displayTitle() }}" loading="lazy" decoding="async"><span class="editorial-feature-label">{{ $cornerSettings->featured_label === 'Phim nổi bật' ? 'Bài nổi bật' : $cornerSettings->featured_label }}</span></a>
                    <h3><a href="{{ route('cinema-corner.show', $featured) }}">{{ $category === 'review' ? '[Review] ' : '' }}{{ $featured->displayTitle() }}</a></h3>
                    @include('frontend.cinema-corner.engagement', ['item' => $featured])
                    <p class="editorial-feature-excerpt">{{ $featured->displayExcerpt(180) }}</p>
                    @if($featured->movie || $featured->trailerSource())
                    <div class="editorial-actions">
                        @if($featured->movie)<a href="{{ route('movies.show', $featured->movie) }}">{{ $featured->movie->status === 'upcoming' ? 'Thông tin phim' : 'Mua vé' }} <span aria-hidden="true">→</span></a>@endif
                        @if($featured->trailerSource())<button type="button" class="btn-trailer" data-trailer="{{ $featured->trailerSource() }}">▷ Xem trailer</button>@endif
                    </div>
                    @endif
                </article>
                @if($tab['articles']->isNotEmpty())
                <div class="editorial-side-list">
                    @foreach($tab['articles'] as $item)
                    <article class="editorial-side-item" data-corner-item="{{ $item->id }}">
                        <a class="editorial-side-image" href="{{ route('cinema-corner.show', $item) }}"><img src="{{ $item->imageUrl(true) }}" alt="{{ $item->displayTitle() }}" loading="lazy" decoding="async"></a>
                        <div><h3><a href="{{ route('cinema-corner.show', $item) }}">{{ $category === 'review' ? '[Review] ' : '' }}{{ $item->displayTitle() }}</a></h3>@include('frontend.cinema-corner.engagement')</div>
                    </article>
                    @endforeach
                </div>
                @endif
            </div>
            @else
                <div class="editorial-empty">Các bài viết {{ mb_strtolower($tab['label']) }} đang được cập nhật.</div>
            @endif
            <div class="editorial-more"><a href="{{ route('cinema-corner.index', $category) }}">Xem thêm <span aria-hidden="true">›</span></a></div>
        </div>
        @endforeach
        @if($cornerSettings->link_url && $cornerSettings->link_label && $cornerSettings->link_url !== '/movies?status=all')<a class="editorial-extra-link" href="{{ $cornerSettings->link_url }}">{{ $cornerSettings->link_label }} →</a>@endif
    </div>
</section>
@endif
