            <section class="seat-tool-card seat-pricing" aria-labelledby="seatPricingTitle">
                <div class="seat-pricing-heading">
                    <div><h3 id="seatPricingTitle">Giá vé theo loại ghế</h3><p>Giá vé = giá cơ bản của suất chiếu + phụ thu loại ghế.</p></div>
                    @if ($priceExample)
                        <div class="seat-pricing-example">Giá cơ bản tham khảo <strong>{{ number_format($priceExample->base_price, 0, ',', '.') }} đ</strong><small>Suất {{ $priceExample->start_time->format('H:i d/m/Y') }} của phòng đang chọn</small></div>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.seats.type-prices', $defaultRoomId) }}" id="seatPricingForm" data-base-price="{{ $priceExample?->base_price ?? '' }}">
                    @csrf
                    @method('PATCH')
                    <div class="seat-pricing-grid">
                        @foreach ($seatPricing as $type => $pricing)
                            <div class="seat-price-card seat-price-card--{{ $type }}">
                                <div class="seat-price-title"><strong>Ghế {{ $pricing['label'] }}</strong><small>{{ $pricing['count'] }} ghế trong phòng</small></div>
                                <label for="seatFee-{{ $type }}">Phụ thu (VNĐ)</label>
                                <input class="seat-control" type="number" id="seatFee-{{ $type }}" name="fees[{{ $type }}]" data-seat-fee="{{ $type }}" value="{{ old('fees.' . $type, $pricing['fee']) }}" min="0" max="999999" step="1" required placeholder="Nhập phụ thu mới">
                                @if ($pricing['fee'] === null)
                                    <p class="seat-price-mixed">Hiện có nhiều mức: {{ number_format($pricing['min'], 0, ',', '.') }}–{{ number_format($pricing['max'], 0, ',', '.') }} đ. Nhập một mức để đồng bộ loại ghế này.</p>
                                @endif
                                <div class="seat-price-preview"><span>{{ $priceExample ? 'Giá vé tham khảo' : 'Giá vé' }}</span><output data-seat-price="{{ $type }}">Giá cơ bản + phụ thu</output></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="seat-pricing-actions">
                        <label for="seatPriceScope">Phạm vi áp dụng
                            <select class="seat-control" name="scope" id="seatPriceScope">
                                <option value="room" @selected(old('scope', 'room') === 'room')>Phòng đang chọn — {{ $rooms->firstWhere('id', $defaultRoomId)?->name }}</option>
                                <option value="cinema" @selected(old('scope') === 'cinema')>Tất cả phòng trong rạp đang chọn</option>
                                <option value="all" @selected(old('scope') === 'all')>Tất cả các rạp</option>
                            </select>
                        </label>
                        <button type="submit" class="seat-action-btn seat-action-btn--primary">Lưu giá theo loại ghế</button>
                    </div>
                    <p class="seat-pricing-note">Áp dụng cho các ghế hiện có trong phạm vi đã chọn. Vé đã bán giữ nguyên giá; phòng đang có khách giữ ghế được bỏ qua. Phụ thu ghế đôi tính cho một vị trí ghế đôi.</p>
                </form>
            </section>
