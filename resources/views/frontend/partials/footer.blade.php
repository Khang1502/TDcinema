@php $footerSettings = \App\Models\FooterSetting::current(); @endphp
@if($footerSettings->is_visible)
<footer class="site-footer" id="site-footer">
    <div class="site-footer-inner">
        <div class="site-footer-brand"><a href="{{ route('home') }}" class="site-footer-logo"><img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt=""><strong>{{ $footerSettings->brand_name }}</strong></a><h3>{{ $footerSettings->company_name }}</h3>@if($footerSettings->description)<p>{{ $footerSettings->description }}</p>@endif</div>
        @foreach(['support'=>'support', 'editorial'=>'editorial'] as $group)
        @if(count($footerSettings->{$group.'_links'} ?? []))
        <nav class="site-footer-links" aria-label="{{ $footerSettings->{$group.'_title'} }}"><h3>{{ $footerSettings->{$group.'_title'} }}</h3>@foreach($footerSettings->{$group.'_links'} as $link)<a href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach</nav>
        @endif
        @endforeach
        <div class="site-footer-contact"><h3>{{ $footerSettings->contact_title }}</h3>
            @if($footerSettings->address)<div><img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt=""><span>{{ $footerSettings->address }}</span></div>@endif
            @if($footerSettings->phone)<div><img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt=""><a href="tel:{{ preg_replace('/[^0-9+]/', '', $footerSettings->phone) }}">{{ $footerSettings->phone }}</a></div>@endif
            @if($footerSettings->email)<div><img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt=""><a href="mailto:{{ $footerSettings->email }}">{{ $footerSettings->email }}</a></div>@endif
            <div class="site-footer-social">@foreach(['facebook'=>'Facebook', 'instagram'=>'Instagram', 'youtube'=>'YouTube'] as $network=>$label)@if($footerSettings->{$network.'_url'})<a href="{{ $footerSettings->{$network.'_url'} }}" target="_blank" rel="noopener noreferrer" aria-label="{{ $label }}"><img src="{{ asset('assets/images/footer/'.$network.'-brands-solid-full.svg') }}" alt=""></a>@endif @endforeach</div>
        </div>
    </div>
    <div class="site-footer-bottom">© {{ now()->year }} {{ $footerSettings->copyright }}</div>
</footer>
@endif
