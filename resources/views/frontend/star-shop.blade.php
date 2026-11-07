@php
    $productImage = fn ($product) => $product->imageUrl();
@endphp
<!DOCTYPE html>
<html lang="vi">
<head>
    @include('shared.notification-assets')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @include('shared.page-assets')
    <meta name="description" content="Khám phá combo bắp nước và quà tặng điện ảnh tại TD Cinema Star Shop.">
    <title>Star Shop | TD Cinema</title>
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/movies.css') }}?v={{ filemtime(public_path('assets/css/frontend/movies.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/frontend/star-shop.css') }}">
    @include('frontend.partials.header-assets')
</head>
<body data-login-error="{{ $errors->has('email') && !session('reset_error') ? '1' : '0' }}"
      data-reset-error="{{ session('reset_error') ? '1' : '0' }}"
      data-reset-sent="{{ session('reset_sent') ? '1' : '0' }}"
      data-reset-email="{{ session('reset_email') }}">
<div class="page" id="top">
    @include('frontend.partials.header', ['searchId' => 'shop-movie-search'])

    <main>
        <section class="shop-hero">
            <div class="shop-container shop-hero__inner">
                <div class="shop-hero__copy">
                    <span class="shop-eyebrow">TD Cinema · Star Shop</span>
                    <h1>Thêm vị ngon<br><em>cho từng thước phim.</em></h1>
                    <p>Combo bắp nước, quà tặng điện ảnh và những bộ sưu tập giới hạn đang chờ bạn tại quầy.</p>
                    <div class="shop-hero__actions">
                        <a href="#products" class="shop-button shop-button--primary">Khám phá sản phẩm</a>
                        <a href="{{ route('movies.index', ['status' => 'now_showing']) }}" class="shop-button shop-button--ghost">Chọn phim để đặt combo</a>
                    </div>
                </div>
                <div class="shop-hero__visual" aria-hidden="true">
                    <span class="shop-spark shop-spark--one">✦</span>
                    <span class="shop-spark shop-spark--two">✦</span>
                    <div class="shop-cup"><span>TD</span><b>★</b></div>
                    <div class="shop-popcorn"><span>POP</span><i></i></div>
                    <div class="shop-ticket">STAR<br>SHOP</div>
                </div>
            </div>
        </section>

        <section class="shop-benefits" aria-label="Thông tin mua hàng">
            <div class="shop-container shop-benefits__grid">
                <div><span>01</span><p><strong>Chọn cùng vé</strong>Mua combo trong bước đặt vé</p></div>
                <div><span>02</span><p><strong>Nhận tại quầy</strong>Đưa mã vé để nhận nhanh</p></div>
                <div><span>03</span><p><strong>Luôn tươi mới</strong>Chuẩn bị ngay trước suất chiếu</p></div>
            </div>
        </section>

        <section class="shop-products shop-container" id="products">
            <div class="shop-section-heading">
                <div>
                    <span class="shop-eyebrow shop-eyebrow--dark">Danh mục đang bán</span>
                    <h2>Combo & quà tặng nổi bật</h2>
                </div>
                <p>{{ $products->count() }} sản phẩm hiện có</p>
            </div>

            <div class="shop-grid">
                @forelse($products as $product)
                    @php $image = $productImage($product); @endphp
                    <article class="shop-card">
                        <div class="shop-card__media {{ $image ? '' : 'is-fallback' }}">
                            @if($image)
                                <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" onerror="this.hidden=true; this.parentElement.classList.add('is-fallback')">
                            @endif
                            <div class="shop-card__fallback" aria-hidden="true"><span>★</span><b>TD</b></div>
                            <span class="shop-card__badge">Có tại quầy</span>
                        </div>
                        <div class="shop-card__body">
                            <div class="shop-card__meta">Star Shop</div>
                            <h3>{{ $product->name }}</h3>
                            <p>{{ $product->description ?: 'Combo được chuẩn bị tươi mới tại quầy TD Cinema.' }}</p>
                            <div class="shop-card__footer">
                                <strong>{{ number_format($product->price, 0, ',', '.') }} <small>đ</small></strong>
                                <a href="{{ route('movies.index', ['status' => 'now_showing']) }}" aria-label="Chọn phim để mua {{ $product->name }}">Chọn phim <span>→</span></a>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="shop-empty">
                        <div aria-hidden="true">★</div>
                        <h2>Star Shop đang chuẩn bị sản phẩm mới</h2>
                        <p>Vui lòng chờ đón các combo và quà tặng điện ảnh trong thời gian sắp tới.</p>
                        <a href="{{ route('movies.index') }}">Khám phá phim đang chiếu</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="shop-note shop-container">
            <div>
                <span>Hướng dẫn nhanh</span>
                <h2>Đặt vé trước, chọn combo sau.</h2>
                <p>Sản phẩm Star Shop được thêm ở bước chọn combo và nhận tại quầy bằng mã vé của bạn.</p>
            </div>
            <a href="{{ route('movies.index', ['status' => 'now_showing']) }}">Bắt đầu đặt vé <span>→</span></a>
        </section>
    </main>

    @include('frontend.partials.footer')
</div>

@include('frontend.partials.auth-modal')
<script src="{{ asset('assets/js/frontend/auth-modal.js') }}" defer></script>
@include('shared.notifications')
</body>
</html>
