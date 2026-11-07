@php
    $headerCinemas = $cinemaDirectory ?? \App\Models\Cinema::where('status', 'active')->orderBy('name')->get(['id', 'name', 'city', 'region']);
    $headerRegions = \App\Support\CinemaRegions::groups($headerCinemas);
    $headerSelectedCinema = $headerCinemas->firstWhere('id', (int) ($cinemaId ?? request('cinema_id', 0)));
    $headerRegionKey = is_string(request('region')) && request('region') !== '' ? request('region') : $headerSelectedCinema?->region_key;
    if (!$headerRegionKey && request('city')) $headerRegionKey = \App\Support\CinemaRegions::infer(request('city'));
    if (!$headerRegions->has($headerRegionKey)) $headerRegionKey = $headerRegions->first(fn ($group) => $group['count'] > 0)['key'] ?? 'ho-chi-minh';
@endphp
<div class="nav-dropdown-menu cinema-nav-menu" data-cinema-directory>
    <div class="cinema-nav-columns">
        <div class="cinema-nav-regions" role="tablist" aria-label="Chọn khu vực rạp" aria-orientation="vertical">
            @foreach($headerRegions as $region)
                <button type="button" id="cinema-region-{{ $region['key'] }}" class="cinema-region-tab {{ $headerRegionKey === $region['key'] ? 'is-active' : '' }}" data-cinema-region="{{ $region['key'] }}" role="tab" aria-selected="{{ $headerRegionKey === $region['key'] ? 'true' : 'false' }}" aria-controls="cinema-panel-{{ $region['key'] }}" tabindex="{{ $headerRegionKey === $region['key'] ? '0' : '-1' }}">
                    <span>{{ $region['label'] }}</span><small>{{ $region['count'] }} rạp</small>
                </button>
            @endforeach
        </div>
        <div class="cinema-nav-results">
            @foreach($headerRegions as $region)
                <div id="cinema-panel-{{ $region['key'] }}" class="cinema-region-panel" role="tabpanel" aria-labelledby="cinema-region-{{ $region['key'] }}" {{ $headerRegionKey !== $region['key'] ? 'hidden' : '' }}>
                    <p class="cinema-nav-heading">{{ $region['label'] }} <span>· {{ $region['count'] }} rạp</span></p>
                    <div class="cinema-nav-list">
                        @php $headerRegionHasMultipleCities = $region['cinemas']->pluck('city')->unique()->count() > 1; @endphp
                        @forelse($region['cinemas'] as $cinema)
                            <a href="{{ route('movies.index', ['status' => 'now_showing', 'cinema_id' => $cinema->id]) }}" class="cinema-nav-link {{ $headerSelectedCinema?->id === $cinema->id ? 'is-selected' : '' }}"><span>{{ $cinema->name }}</span>@if($headerRegionHasMultipleCities)<small>{{ $cinema->city }}</small>@endif</a>
                        @empty
                            <div class="cinema-nav-empty"><span aria-hidden="true">⌖</span><p>Chưa có rạp đang hoạt động tại khu vực này.</p></div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <div class="cinema-nav-footer"><a href="{{ route('cinemas.index') }}#cinema-list">Tất cả rạp <span aria-hidden="true">→</span></a><a href="{{ route('cinemas.index') }}#ticket-prices">Bảng giá vé <span aria-hidden="true">→</span></a></div>
</div>
