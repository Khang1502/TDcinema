            @php $seatLayout = \App\Support\SeatMapLayout::forRows($seatRows); @endphp
            <div class="admin-auditorium" id="savedAuditorium">
                <div class="admin-screen"><span>Màn hình</span></div>
                <div class="seat-grid" id="savedSeatGrid" style="--seat-layout: {{ $seatLayout['template'] }}; --seat-map-width: {{ $seatLayout['width'] }}px; --seat-map-min-width: {{ $seatLayout['min_width'] }}px;" role="region" tabindex="0" aria-label="Sơ đồ ghế có lối đi giữa các cụm ghế">
                    @foreach ($seatRows as $rowLabel => $seats)
                        <div class="seat-row {{ $seatLayout['rows'][$rowLabel]['cross_aisle'] ? 'seat-row--cross-aisle' : '' }}">
                            <button type="button" class="seat-row-label" data-select-row="{{ $rowLabel }}" title="Chọn cả hàng {{ $rowLabel }}">{{ $rowLabel }}</button>
                            <div class="seat-row-seats">
                            @foreach ($seatLayout['aisles'] as $aisleColumn)
                                <span class="seat-aisle" style="grid-column: {{ $aisleColumn }};" aria-hidden="true"></span>
                            @endforeach
                            @foreach ($seats as $seat)
                                @php
                                    $typeClass = match ($seat->seat_type) {
                                        'vip' => 'seat-vip',
                                        'sweetbox' => 'seat-couple',
                                        default => 'seat-normal',
                                    };
                                    $classes = 'seat ' . $typeClass;
                                    if (! $seat->status) $classes .= ' seat-inactive';
                                @endphp
                                <button type="button" class="{{ $classes }}"
                                     style="grid-column: {{ $seatLayout['columns'][$seat->seat_number] }};"
                                     data-seat-id="{{ $seat->id }}" data-row="{{ $rowLabel }}" data-code="{{ $seat->seat_row . $seat->seat_number }}" data-type="{{ $seat->seat_type }}" data-status="{{ (int)$seat->status }}" data-fee="{{ $seat->extra_price }}"
                                     aria-label="Ghế {{ $seat->seat_row . $seat->seat_number }}, phụ thu {{ number_format($seat->extra_price, 0, ',', '.') }} đồng"
                                     aria-pressed="false"
                                     title="{{ $seat->seat_row . $seat->seat_number }}&#10;Phụ thu: {{ number_format($seat->extra_price) }}đ">
                                    {{ $seat->seat_number }}
                                </button>
                            @endforeach
                            </div>
                            <div class="seat-row-label" aria-hidden="true">{{ $rowLabel }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
