@if($banners->isNotEmpty())
<section class="hero{{ isset($bannerVariant) ? ' hero--'.$bannerVariant : '' }}" aria-label="{{ $bannerLabel ?? 'Banner phim nổi bật' }}" tabindex="0" data-banner-slider>
    <div class="hero-stage">
        <div class="hero-track">
            @foreach($banners as $banner)
                <div class="hero-slide {{ $loop->first ? 'active' : '' }}" aria-hidden="{{ $loop->first ? 'false' : 'true' }}">
                    @if($destination = $banner->destinationUrl())
                        <a class="hero-slide-link" href="{{ $destination }}" tabindex="{{ $loop->first ? '0' : '-1' }}" aria-label="Xem {{ $banner->title }}">
                    @endif
                    <img src="{{ $banner->imageUrl() }}" alt="{{ $banner->title }}" loading="eager" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" decoding="async">
                    @if($destination)</a>@endif
                </div>
            @endforeach
        </div>
        @if($banners->count() > 1)
            <button type="button" class="hero-preview hero-preview--prev" data-banner-preview="prev" aria-label="Chuyển đến banner trước"></button>
            <button type="button" class="hero-preview hero-preview--next" data-banner-preview="next" aria-label="Chuyển đến banner tiếp theo"></button>
            <button type="button" class="hero-arrow hero-arrow--prev" data-slider-prev aria-label="Xem banner trước">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18 9 12l6-6"/></svg>
            </button>
            <button type="button" class="hero-arrow hero-arrow--next" data-slider-next aria-label="Xem banner tiếp theo">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
            </button>
        @endif
    </div>
    <div class="slider-dots" role="group" aria-label="Chọn banner">
        @foreach($banners as $banner)
            <button type="button" data-slide="{{ $loop->index }}" class="{{ $loop->first ? 'active' : '' }}" aria-label="Chuyển đến banner {{ $loop->iteration }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
        @endforeach
    </div>
</section>
@endif
