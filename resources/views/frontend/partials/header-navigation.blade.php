@php
    $headerNavNow = isset($navNow)
        ? collect($navNow)
        : \App\Models\Movie::where('status', 'now_showing')->orderByDesc('release_date')->take(4)->get();
    $headerNavUpcoming = isset($navUpcoming)
        ? collect($navUpcoming)
        : \App\Models\Movie::where('status', 'upcoming')->orderByDesc('release_date')->take(4)->get();
    $headerNavImax = isset($navImax)
        ? collect($navImax)
        : \App\Models\Movie::imax()->orderByDesc('release_date')->take(4)->get();
    $headerNavPoster = isset($navPoster)
        ? $navPoster
        : fn ($movie) => $movie->posterUrl();
@endphp

<nav class="main-nav" aria-label="Điều hướng chính"><ul>
@foreach(collect($headerSettings->menus)->sortBy('sort_order') as $key=>$menu)
@continue(!$menu['is_visible'])
@if($key==='movies')
@include('frontend.partials.header-movies-menu')
@elseif($key==='cinemas')
<li class="nav-dropdown nav-dropdown--right nav-dropdown--cinemas"><a href="{{ \App\Models\HeaderSetting::linkUrl($menu['url']) }}" class="{{ request()->routeIs('cinemas.index') ? 'active' : '' }}" aria-haspopup="true">{{ $menu['label'] }}</a>@include('frontend.partials.cinema-navigation')</li>
@else
<li class="{{ count($menu['children'] ?? []) ? 'nav-dropdown' : '' }}"><a href="{{ \App\Models\HeaderSetting::linkUrl($menu['url']) }}" @if(count($menu['children'] ?? [])) aria-haspopup="true" @endif>{{ $menu['label'] }}</a>
@if(count($menu['children'] ?? []))<div class="nav-dropdown-menu nav-dropdown-menu--compact"><span class="nav-menu-kicker">{{ $menu['label'] }}</span>@foreach($menu['children'] as $child)<a href="{{ \App\Models\HeaderSetting::linkUrl($child['url']) }}"><strong>{{ $child['label'] }}</strong>@if($child['description'] ?? null)<small>{{ $child['description'] }}</small>@endif</a>@endforeach</div>@endif
</li>
@endif
@endforeach
</ul></nav>
