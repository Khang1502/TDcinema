<li class="has-mega nav-dropdown">
            <a href="{{ \App\Models\HeaderSetting::linkUrl($menu['url']) }}" class="{{ request()->routeIs('movies.*') ? 'active' : '' }}" aria-haspopup="true">{{ $menu['label'] }}</a>
            <div class="mega nav-dropdown-menu">
                <div class="mega-grid">
                    <div class="mega-col">
                        <h4>Phim đang chiếu</h4>
                        <div class="mega-list">
                            @forelse($headerNavNow as $movie)
                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                    <div class="mega-thumb"><img src="{{ $headerNavPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                    <div class="mega-info">
                                        <div class="mega-title">{{ $movie->title }}</div>
                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                        @if($movie->rating_avg)<div class="mega-rating">★ {{ number_format($movie->rating_avg, 1) }}</div>@endif
                                    </div>
                                </a>
                            @empty
                                <span class="mega-meta">Chưa có phim nào ở phần này, vui lòng chờ đón trong thời gian sắp tới.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="mega-col">
                        <h4>Phim sắp chiếu</h4>
                        <div class="mega-list">
                            @forelse($headerNavUpcoming as $movie)
                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                    <div class="mega-thumb"><img src="{{ $headerNavPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                    <div class="mega-info">
                                        <div class="mega-title">{{ $movie->title }}</div>
                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                    </div>
                                </a>
                            @empty
                                <span class="mega-meta">Chưa có phim nào ở phần này, vui lòng chờ đón trong thời gian sắp tới.</span>
                            @endforelse
                        </div>
                    </div>
                    <div class="mega-col">
                        <h4>Phim IMAX</h4>
                        <div class="mega-list">
                            @forelse($headerNavImax as $movie)
                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                    <div class="mega-thumb"><img src="{{ $headerNavPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                    <div class="mega-info">
                                        <div class="mega-title">{{ $movie->title }}</div>
                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                        @if($movie->rating_avg)<div class="mega-rating">★ {{ number_format($movie->rating_avg, 1) }}</div>@endif
                                    </div>
                                </a>
                            @empty
                                <span class="mega-meta">Chưa có phim nào ở phần này, vui lòng chờ đón trong thời gian sắp tới.</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </li>
