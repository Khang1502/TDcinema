@once
@foreach([400, 500, 600, 700, 800] as $weight)
    @foreach(['latin', 'vietnamese'] as $subset)
        <link rel="preload" href="{{ asset('assets/fonts/be-vietnam-pro/'.$weight.'-'.$subset.'.woff2') }}" as="font" type="font/woff2" crossorigin>
    @endforeach
@endforeach
<link rel="stylesheet" href="{{ asset('assets/css/fonts.css') }}?v={{ filemtime(public_path('assets/css/fonts.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/page-navigation.css') }}?v={{ filemtime(public_path('assets/css/page-navigation.css')) }}">
@endonce
