@extends('admin.layouts.app')
@section('title', 'Bán vé tại quầy')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/admin/counter-sale.css') }}?v={{ filemtime(public_path('assets/css/admin/counter-sale.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/admin/quantity-stepper.css') }}?v={{ filemtime(public_path('assets/css/admin/quantity-stepper.css')) }}">
@endpush

@section('content')
@php
    $initialCustomerType = old('customer_type', old('user_id') ? 'member' : 'guest');
    $initialSeatIds = old('seat_ids', $prefillSeat ? [$prefillSeat] : []);
@endphp
<div class="counter-sale">
    <header class="counter-sale__heading">
        <p class="counter-sale__eyebrow">BÁN HÀNG TẠI QUẦY</p>
        <h1>Bán vé tại quầy</h1>
        <p>Chọn suất chiếu, ghế ngồi và khách hàng để hoàn tất giao dịch tiền mặt.</p>
    </header>

    @if ($errors->any())
        <div data-inline-notification class="counter-sale__errors" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('admin.tickets.store') }}" data-counter-form
          data-max-seats="{{ config('booking_limits.seats_per_order') }}"
          data-create-url="{{ route('admin.tickets.create') }}"
          data-showtime-options-url="{{ route('admin.tickets.showtime-options') }}"
          data-member-search-url="{{ route('admin.tickets.member-search') }}"
          data-member-promotions-url="{{ route('admin.tickets.member-promotions') }}"
          data-initial-promo-code="{{ old('promo_code', '') }}">
        @csrf
        <input type="hidden" name="sale_token" value="{{ $saleToken }}">

        <section class="counter-sale__section" aria-labelledby="counter-showtime-title">
            <div class="counter-sale__section-head">
                <span class="counter-sale__step">01</span>
                <div>
                    <h2 id="counter-showtime-title">Chọn suất chiếu</h2>
                    <p>Chỉ hiện phim đang chiếu và các suất còn mở bán trong 7 ngày tới.</p>
                </div>
            </div>

            <div class="counter-showtime-fields">
                <div class="counter-field">
                    <span id="counter-movie-label">Phim</span>
                    <div class="counter-movie-picker" data-movie-picker>
                        <select data-filter="movie" aria-labelledby="counter-movie-label">
                            <option value="">Chọn phim</option>
                            @foreach($movies as $movie)
                                <option value="{{ $movie->id }}" @selected($selectedShowtime?->movie_id === $movie->id)>{{ $movie->title }}</option>
                            @endforeach
                        </select>
                        <input type="search" class="counter-movie-search" data-movie-search
                            role="combobox" aria-labelledby="counter-movie-label" aria-autocomplete="list"
                            aria-controls="counter-movie-results" aria-expanded="false"
                            placeholder="Nhập tên phim để tìm..." autocomplete="off" hidden>
                        <div class="counter-movie-menu" data-movie-menu hidden>
                            <div id="counter-movie-results" class="counter-movie-results" data-movie-results role="listbox" aria-labelledby="counter-movie-label"></div>
                            <p class="counter-movie-empty" data-movie-empty hidden>Không tìm thấy phim phù hợp.</p>
                        </div>
                    </div>
                </div>
                <label class="counter-field">Rạp
                    <select data-filter="cinema" aria-label="Rạp">
                        <option value="">Chọn rạp</option>
                        @if($selectedShowtime)<option value="{{ $selectedShowtime->room?->cinema_id }}" selected>{{ $selectedShowtime->room?->cinema?->name }}</option>@endif
                    </select>
                </label>
                <label class="counter-field">Phòng
                    <select data-filter="room" aria-label="Phòng">
                        <option value="">Chọn phòng</option>
                        @if($selectedShowtime)<option value="{{ $selectedShowtime->room_id }}" selected>{{ $selectedShowtime->room?->name }}</option>@endif
                    </select>
                </label>
                <label class="counter-field">Ngày chiếu
                    <select data-filter="date" aria-label="Ngày chiếu">
                        <option value="">Chọn ngày</option>
                        @if($selectedShowtime)<option value="{{ $selectedShowtime->start_time->format('Y-m-d') }}" selected>{{ $selectedShowtime->start_time->format('d/m/Y') }}</option>@endif
                    </select>
                </label>
                <label class="counter-field">Giờ chiếu
                    <select name="showtime_id" data-filter="showtime" aria-label="Giờ chiếu" required>
                        <option value="">Chọn giờ chiếu</option>
                        @if($selectedShowtime)
                            <option value="{{ $selectedShowtime->id }}" selected>
                                {{ $selectedShowtime->start_time->format('H:i') }}@if($selectedShowtime->end_time) – {{ $selectedShowtime->end_time->format('H:i') }}@endif
                            </option>
                        @endif
                    </select>
                </label>
            </div>
            <p class="counter-sale__field-error" data-showtime-load-error hidden>Không tải được suất chiếu. Vui lòng chọn lại phim hoặc tải lại trang.</p>
            @if($movies->isEmpty()) <p class="counter-sale__note">Hiện chưa có phim đang chiếu với suất còn mở bán.</p> @endif
            @error('showtime_id') <p class="counter-sale__field-error">{{ $message }}</p> @enderror

            @if ($selectedShowtime)
                @php
                    $selectedMovie = $selectedShowtime->movie;
                @endphp
                <div class="counter-showtime-summary" aria-label="Thông tin suất chiếu đã chọn">
                    <img class="counter-showtime-summary__poster"
                         src="{{ $selectedMovie?->posterUrl() ?? asset('assets/images/ui/movie-placeholder.svg') }}"
                         alt="Poster phim {{ $selectedMovie?->title }}" loading="lazy" width="110" height="160">
                    <div class="counter-showtime-summary__content">
                        <p class="counter-showtime-summary__eyebrow">SUẤT CHIẾU ĐÃ CHỌN</p>
                        <h3>{{ $selectedMovie?->title }}</h3>
                        <div class="counter-showtime-summary__tags">
                            @if($selectedMovie?->age_rating)<span class="counter-showtime-summary__age">{{ $selectedMovie->age_rating }}</span>@endif
                            @if($selectedMovie?->genre)<span>{{ $selectedMovie->genre }}</span>@endif
                            @if($selectedMovie?->duration)<span>{{ $selectedMovie->duration }} phút</span>@endif
                        </div>
                    </div>
                    <dl class="counter-showtime-summary__details">
                        <div><dt>Suất chiếu</dt><dd>{{ $selectedShowtime->start_time->format('H:i') }}@if($selectedShowtime->end_time) – {{ $selectedShowtime->end_time->format('H:i') }}@endif · {{ $selectedShowtime->start_time->format('d/m/Y') }}</dd></div>
                        <div><dt>Rạp</dt><dd>{{ $selectedShowtime->room?->cinema?->name }}</dd></div>
                    </dl>
                </div>
            @endif
        </section>

        <section class="counter-sale__section" aria-labelledby="counter-seats-title" data-seat-section>
            <div class="counter-sale__section-head counter-sale__section-head--split">
                <div class="counter-sale__section-title">
                    <span class="counter-sale__step">02</span>
                    <div>
                        <h2 id="counter-seats-title">Chọn ghế</h2>
                        <p>Chọn tối đa {{ config('booking_limits.seats_per_order') }} ghế trong cùng suất chiếu.</p>
                    </div>
                </div>
                @if($selectedShowtime)
                    <span class="counter-seat-availability" data-seat-availability>{{ $seats->count() - $bookedSeatIds->intersect($seats->pluck('id'))->count() }} ghế còn trống</span>
                @endif
            </div>

            <div class="counter-sale__empty" data-seat-placeholder hidden>Chọn giờ chiếu để tải sơ đồ ghế của phòng.</div>
            <div data-seat-content>
            @if($selectedShowtime && $seats->isNotEmpty())
                <div class="counter-seat-legend" aria-label="Chú giải trạng thái ghế">
                    <span><i class="counter-seat-legend__dot counter-seat-legend__dot--available"></i> Ghế trống</span>
                    <span><i class="counter-seat-legend__dot counter-seat-legend__dot--selected"></i> Đang chọn</span>
                    <span><i class="counter-seat-legend__dot counter-seat-legend__dot--booked"></i> Không khả dụng</span>
                    @if($seats->contains('seat_type', 'vip')) <span><i class="counter-seat-legend__dot counter-seat-legend__dot--vip"></i> VIP</span> @endif
                    @if($seats->contains('seat_type', 'sweetbox')) <span><i class="counter-seat-legend__dot counter-seat-legend__dot--sweetbox"></i> Ghế đôi</span> @endif
                </div>
                <div class="counter-seat-map-scroll" tabindex="0" aria-label="Sơ đồ ghế, cuộn ngang nếu cần">
                    <div class="counter-seat-map">
                        <div class="counter-seat-screen"><span>MÀN HÌNH</span></div>
                        @foreach($seats->groupBy('seat_row') as $row => $rowSeats)
                            <div class="counter-seat-row">
                                <span class="counter-seat-row__label">{{ $row }}</span>
                                <div class="counter-seat-row__seats">
                                    @foreach($rowSeats as $seat)
                                        @php
                                            $isBooked = $bookedSeatIds->contains($seat->id);
                                            $code = $seat->seat_row.$seat->seat_number;
                                            $price = $selectedShowtime->base_price + $seat->extra_price;
                                        @endphp
                                        <label class="counter-seat counter-seat--{{ $seat->seat_type }} {{ $isBooked ? 'counter-seat--booked' : '' }}" title="{{ $code }} · {{ number_format($price, 0, ',', '.') }}đ{{ $isBooked ? ' · Không khả dụng' : '' }}">
                                            <input type="checkbox" name="seat_ids[]" value="{{ $seat->id }}" data-price="{{ $price }}" data-code="{{ $code }}"
                                                aria-label="Ghế {{ $code }}, {{ number_format($price, 0, ',', '.') }} đồng{{ $isBooked ? ', không khả dụng' : '' }}"
                                                @checked(!$isBooked && in_array($seat->id, $initialSeatIds)) @disabled($isBooked)>
                                            <span class="counter-seat__code">{{ $code }}</span>
                                            <span class="counter-seat__price">{{ $isBooked ? 'Đã có vé' : number_format($price, 0, ',', '.').'đ' }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <span class="counter-seat-row__label">{{ $row }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="counter-seat-summary" aria-live="polite">
                    <div><strong>Ghế đã chọn</strong><span data-counter-selected-seats>Chưa chọn ghế</span></div>
                    <div><strong data-counter-seat-count>0 ghế</strong><span class="counter-seat-summary__total" data-counter-total>0đ</span></div>
                </div>
                <p class="counter-sale__note">Tạm tính trước ưu đãi thành viên. Giá và trạng thái ghế được xác minh lại khi thu tiền.</p>
            @else
                <div class="counter-sale__empty">{{ $selectedShowtime ? 'Phòng chiếu này chưa có ghế khả dụng.' : 'Chọn giờ chiếu để xem sơ đồ ghế của phòng.' }}</div>
            @endif
            </div>
            @error('seat_ids') <p class="counter-sale__field-error">{{ $message }}</p> @enderror
            @error('seat_id') <p class="counter-sale__field-error">{{ $message }}</p> @enderror
        </section>

        <section class="counter-sale__section" aria-labelledby="counter-customer-title">
            <div class="counter-sale__section-head">
                <span class="counter-sale__step">03</span>
                <div>
                    <h2 id="counter-customer-title">Khách hàng</h2>
                    <p>Chọn khách vãng lai hoặc tìm tài khoản thành viên.</p>
                </div>
            </div>
            <div class="counter-customer-choices">
                <label class="counter-customer-choice">
                    <input type="radio" name="customer_type" value="guest" @checked($initialCustomerType !== 'member')>
                    <span class="counter-customer-choice__mark" aria-hidden="true"></span>
                    <span><strong>1. Khách vãng lai</strong><small>Bán vé không cần tài khoản</small></span>
                </label>
                <label class="counter-customer-choice">
                    <input type="radio" name="customer_type" value="member" @checked($initialCustomerType === 'member')>
                    <span class="counter-customer-choice__mark" aria-hidden="true"></span>
                    <span><strong>2. Thành viên</strong><small>Tìm khách để áp dụng quyền lợi</small></span>
                </label>
            </div>
            <div class="counter-member-panel" data-member-panel @if($initialCustomerType !== 'member') hidden @endif>
                <label for="counter-member-input">Tìm thành viên theo tên, email hoặc số điện thoại</label>
                <div class="counter-member-search">
                    <input id="counter-member-input" type="search" data-member-input
                        value="{{ $selectedCustomer?->name }}" placeholder="Nhập ít nhất 2 ký tự để tìm..."
                        autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="counter-member-results" aria-expanded="false">
                    <div id="counter-member-results" class="counter-member-results" data-member-results role="listbox" hidden></div>
                </div>
                <input type="hidden" name="user_id" value="{{ $selectedCustomer?->id }}" data-member-id>
                <p class="counter-member-status" data-member-status aria-live="polite">@if($selectedCustomer) Đã chọn: {{ $selectedCustomer->name }} · {{ $selectedCustomer->email }} @else Chọn một khách trong danh sách gợi ý. @endif</p>
                @error('user_id') <p class="counter-sale__field-error">{{ $message }}</p> @enderror
                <div class="counter-member-promotions" data-member-promotions @if(!$selectedCustomer) hidden @endif>
                    <div class="counter-member-promotions__head">
                        <strong>Khuyến mãi khả dụng</strong>
                        <span>Mỗi giao dịch dùng một mã. Mã thay thế ưu đãi theo hạng.</span>
                    </div>
                    <p class="counter-sale__note" data-promo-loading hidden>Đang tải khuyến mãi...</p>
                    <div class="counter-promo-list" data-promo-list></div>
                    <p class="counter-promo-empty" data-promo-empty hidden>Không có khuyến mãi nào khả dụng.</p>
                    <p class="counter-sale__field-error" data-promo-load-error hidden>Không tải được khuyến mãi. Vui lòng chọn lại thành viên.</p>
                </div>
                @error('promo_code') <p class="counter-sale__field-error">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="counter-sale__section" aria-labelledby="counter-food-title">
            <div class="counter-sale__section-head counter-sale__section-head--split">
                <div class="counter-sale__section-title">
                    <span class="counter-sale__step">04</span>
                    <div>
                        <h2 id="counter-food-title">Combo / bắp nước <small>(tùy chọn)</small></h2>
                        <p>Chọn tối đa {{ config('booking_limits.quantity_per_food_item') }} phần mỗi loại nếu khách muốn mua cùng vé. Để 0 nếu không mua.</p>
                    </div>
                </div>
                <span class="counter-food-total">Tiền bắp nước: <strong data-counter-food-total>0đ</strong></span>
            </div>
            @if($products->isNotEmpty())
                <div class="counter-food-grid">
                    @foreach($products as $product)
                        <div class="counter-food-card">
                            @if($product->imageUrl())
                                <img src="{{ $product->imageUrl() }}" alt="" loading="lazy">
                            @endif
                            <div class="counter-food-card__body">
                                <strong>{{ $product->name }}</strong>
                                <span>{{ number_format($product->price, 0, ',', '.') }}đ / phần</span>
                                <label for="counter-food-{{ $product->id }}">Số lượng</label>
                                <div class="quantity-stepper" data-quantity-stepper>
                                    <button type="button" data-quantity-adjust="-1" aria-label="Giảm số lượng {{ $product->name }}">−</button>
                                    <input id="counter-food-{{ $product->id }}" type="number" inputmode="numeric" min="0" max="{{ config('booking_limits.quantity_per_food_item') }}"
                                        name="items[{{ $product->id }}]" value="{{ old('items.'.$product->id, 0) }}"
                                        data-food-quantity data-price="{{ $product->price }}">
                                    <button type="button" data-quantity-adjust="1" aria-label="Tăng số lượng {{ $product->name }}">+</button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="counter-sale__empty">Hiện chưa có combo hoặc bắp nước đang bán.</div>
            @endif
            @error('items') <p class="counter-sale__field-error">{{ $message }}</p> @enderror
        </section>

        <footer class="counter-sale__footer">
            <div class="counter-sale__payment-summary">
                <span>Tạm tính vé + bắp nước</span>
                <span class="counter-sale__discount" data-counter-discount-line hidden><span data-counter-discount-label>Ưu đãi</span><strong data-counter-discount>−0đ</strong></span>
                <strong data-counter-grand-total>0đ</strong>
                <small>Giá và ưu đãi được xác minh lại khi xác nhận. Kiểm tra tiền mặt đã nhận trước khi tạo vé.</small>
            </div>
            <div>
                <a href="{{ route('admin.tickets.index') }}" class="counter-sale__cancel">Hủy</a>
                <button type="submit" data-counter-submit>Xác nhận đã thu tiền và tạo vé</button>
            </div>
        </footer>
    </form>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin/counter-sale.js') }}?v={{ filemtime(public_path('assets/js/admin/counter-sale.js')) }}" defer></script>
    <script src="{{ asset('assets/js/admin/quantity-stepper.js') }}?v={{ filemtime(public_path('assets/js/admin/quantity-stepper.js')) }}" defer></script>
@endpush
