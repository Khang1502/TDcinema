@php
    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Str;

    $posterUrl = function ($movie) {
        $path = $movie->poster_path ?? '';
        if (empty($path)) {
            return 'https://via.placeholder.com/360x540?text=Poster';
        }
        return preg_match('/^https?:\/\//', $path)
            ? $path
            : asset('storage/' . ltrim($path, '/'));
    };

    // Fallback banners from public/assets/images/banner
    $bannerFallbacks = [];
    $bannerDir = public_path('assets/images/banner');
    if (File::isDirectory($bannerDir)) {
        $bannerFallbacks = collect(File::files($bannerDir))
            ->filter(fn ($f) => preg_match('/\.(png|jpe?g|webp)$/i', $f->getFilename()))
            ->map(fn ($f) => asset('assets/images/banner/' . $f->getFilename()))
            ->values()
            ->toArray();
    }
    if (empty($bannerFallbacks)) {
        $bannerFallbacks[] = asset('assets/images/banners/hero.jpg');
    }

    $bannerUrl = function ($movie) use ($posterUrl, $bannerFallbacks) {
        $fallback = $bannerFallbacks[array_rand($bannerFallbacks)];
        $path = $movie?->banner_path ?? '';
        if ($path) {
            return preg_match('/^https?:\/\//', $path)
                ? $path
                : asset('storage/' . ltrim($path, '/'));
        }
        return $movie ? $posterUrl($movie) : $fallback;
    };

    // Slides ưu tiên banner_path, sau đó poster, cuối cùng ảnh tĩnh
    $slides = collect($nowShowing ?? [])
        ->take(3)
        ->map(fn ($m) => $m->banner_path ? $bannerUrl($m) : $posterUrl($m))
        ->filter()
        ->values()
        ->toArray();
    if (empty($slides)) {
        $slides = $bannerFallbacks;
    }

    $shortIntro = function ($movie) {
        $desc = trim($movie->description ?? '');
        if (!empty($desc)) {
            return Str::limit($desc, 120);
        }
        return ucfirst(strtolower($movie->genre ?? ''));
    };
    $featureArticle = ($upcoming->first() ?? $nowShowing->first());
    $articleList = collect($nowShowing)->merge($upcoming)->filter(function ($m) use ($featureArticle) {
        return $featureArticle ? $m->id !== $featureArticle->id : true;
    })->take(3);

    // SEO meta + schema prep
    $seoMovie = ($nowShowing ?? collect())->first();
    $seoTitle = 'TD Cinema - Đặt vé phim, xem lịch chiếu, trailer';
    $seoDesc = 'Đặt vé phim, xem lịch chiếu, trailer tại TD Cinema.';
    if ($seoMovie) {
        $seoTitle = ($seoMovie->title ?? 'TD Cinema') . ' | TD Cinema';
        $desc = trim($seoMovie->description ?? '');
        if (!empty($desc)) {
            $seoDesc = Str::limit($desc, 150);
        }
    }

    $orgSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'TD Cinema',
        'url' => url('/'),
        'logo' => asset('assets/images/logo-td-cinema.png'),
    ];
    $siteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => 'TD Cinema',
        'url' => url('/'),
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => url('/search') . '?q={query}',
            'query-input' => 'required name=query',
        ],
    ];
    $movieSchema = null;
    if ($seoMovie) {
        $movieSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Movie',
            'name' => $seoMovie->title,
            'description' => $seoDesc,
            'image' => $posterUrl($seoMovie),
            'genre' => $seoMovie->genre ?? 'Phim',
        ];
        if (!empty($seoMovie->release_date)) {
            $movieSchema['datePublished'] = $seoMovie->release_date;
        }
        if (!empty($seoMovie->trailer_url)) {
            $movieSchema['trailer'] = $seoMovie->trailer_url;
        }
        if (!empty($seoMovie->rating)) {
            $movieSchema['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $seoMovie->rating,
                'ratingCount' => $seoMovie->rating_count ?? 1,
            ];
        }
    }
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seoTitle }}</title>
    <meta name="description" content="{{ $seoDesc }}">
    <script type="application/ld+json">
        {!! json_encode($orgSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
        {!! json_encode($siteSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
    </script>
    @if($movieSchema)
        <script type="application/ld+json">
            {!! json_encode($movieSchema, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Lato:ital,wght@0,100;0,300;0,400;0,700;0,900;1,100;1,300;1,400;1,700;1,900&display=swap');
        :root {
            --color-primary: #359eff;
            --color-secondary: #f97316;
            --color-text-dark: #374151;
            --color-text-light: #ffffff;
            --font-main: 'Be Vietnam Pro', sans-serif;
        }
        * { box-sizing: border-box; margin:0; padding:0; }
        body { font-family: var(--font-main); background: #f5f7fb; color: var(--color-text-dark); }
        a { text-decoration: none; color: inherit; }
        img { max-width: 100%; display: block; }
        .page { width: 100%; max-width: 100%; margin: 0; }
        .container { width: 100%; max-width: 1280px; margin: 0 auto; padding: 0 15px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; border:none; border-radius:6px; font-weight:700; cursor:pointer; }
        .btn-primary { background: var(--color-primary); color: var(--color-text-light); padding: 10px 18px; }
        .btn-secondary { background: var(--color-secondary); color: var(--color-text-light); padding: 8px 14px; }
        .btn-outline { border:1px solid #ff5e00; color:#ff5e00; padding:8px 18px; background: transparent; }

        /* Header */
        .site-header {
            background: #ffffff;
            padding: 10px 0;
            position: static;
            border-bottom: 1px solid #e5e7eb;
        }
        .header-container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-left { display:flex; align-items:center; gap:32px; }
        .logo { display:flex; align-items:center; gap:8px; font-weight:800; color:#0b2c3d; text-transform:uppercase; letter-spacing:0.3px; }
        .logo img { width:40px; height:40px; object-fit:cover; }
        .main-nav ul { display:flex; align-items:center; gap:20px; list-style:none; margin:0; padding:0; }
        .main-nav a { font-size:14px; font-weight:500; color:#4b5563; padding:6px 0; display:inline-flex; align-items:center; gap:4px; }
        .main-nav a::after { content:'▾'; font-size:10px; color:#9ca3af; }
        .main-nav a.no-caret::after { display:none; }
        /* Mega menu */
        .has-mega { position:relative; }
        .mega { position:absolute; left:0; top:54px; background:#fff; box-shadow:0 12px 32px rgba(0,0,0,.12); border-radius:14px; padding:16px; display:none; z-index:50; width:920px; max-width:95vw; }
        .has-mega:hover .mega { display:block; }
        .mega h4 { margin:0 0 10px; font-size:14px; font-weight:800; color:#0f172a; text-transform:uppercase; letter-spacing:.2px; }
        .mega-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
        .mega-col { border-right:1px solid #e5e7eb; padding-right:12px; }
        .mega-col:last-child { border-right:none; padding-right:0; }
        .mega-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
        .mega-item { display:flex; gap:8px; text-decoration:none; color:#0f172a; }
        .mega-thumb { width:70px; height:96px; border-radius:8px; overflow:hidden; background:#f1f5f9; flex-shrink:0; }
        .mega-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
        .mega-info { display:flex; flex-direction:column; gap:4px; }
        .mega-title { font-size:13px; font-weight:700; line-height:1.3; color:#0f172a; }
        .mega-meta { font-size:12px; color:#6b7280; }
        .mega-rating { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#f59e0b; }
        .nav-buy-img img { height:42px; width:auto; display:block; }
        .header-right { display:flex; align-items:center; gap:16px; font-size:14px; }
        .header-search-btn { border:none; background:transparent; cursor:pointer; font-size:22px; line-height:1; color:#6b7280; display:flex; align-items:center; justify-content:center; }
        .search-form { display:none; }
        .header-login { font-weight:500; color:#6b7280; }
        .header-join-img img { height:38px; width:auto; display:block; object-fit:contain; }
        .join-wrapper { position:relative; }
        .join-popover { position:absolute; right:0; top:110%; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:16px; width:900px; max-width:95vw; display:none; z-index:40; }
        .join-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .join-card { text-align:center; border:1px solid #e5e7eb; border-radius:10px; padding:12px; background:#fff; }
        .join-card img { height:80px; margin:0 auto 10px; object-fit:contain; }
        .join-card h4 { font-size:14px; font-weight:700; color:#1f2937; margin-bottom:6px; }
        .join-card p { font-size:13px; color:#6b7280; margin-bottom:8px; }
        .join-card .btn { padding:8px 12px; }
        .user-info { display:flex; align-items:center; gap:10px; padding:6px 10px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; position:relative; cursor:pointer; }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:#e5e7eb; display:grid; place-items:center; font-weight:700; color:#4b5563; overflow:hidden; }
        .user-avatar img { width:100%; height:100%; object-fit:cover; }
        .user-meta { display:flex; flex-direction:column; gap:2px; line-height:1.2; }
        .user-name { font-weight:700; color:#1f2937; }
        .user-tier { font-size:12px; color:#6b7280; display:flex; gap:6px; align-items:center; }
        .user-menu { position:absolute; right:0; top:115%; background:#fff; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); padding:10px 0; min-width:180px; display:none; z-index:40; }
        .user-menu a { display:block; padding:8px 14px; color:#374151; font-size:14px; }
        .user-menu a:hover { background:#f3f4f6; }

        /* Hero slider */
        .hero { position:relative; height:420px; background:#1a1d29; overflow:visible; }
        .hero-slide { position:absolute; inset:0; background-size:cover; background-position:center; opacity:0; transition:opacity .6s ease; }
        .hero-slide.active { opacity:1; }
        .hero::after { content:''; position:absolute; inset:0; background: linear-gradient(180deg,rgba(26,29,41,.25),rgba(26,29,41,.55)); pointer-events:none; }
        .slider-dots { position:absolute; bottom:14px; left:50%; transform:translateX(-50%); display:flex; gap:6px; z-index:5; }
        .slider-dots button { width:10px; height:10px; border-radius:999px; border:none; background:rgba(255,255,255,.5); cursor:pointer; transition:all .2s; }
        .slider-dots button.active { width:22px; background: var(--color-primary); }

        /* Booking bar */
        .booking-bar { position:absolute; bottom:-46px; left:50%; transform:translateX(-50%); width:100%; max-width:1206px; padding:0 15px; z-index:6; }
        .booking-steps { background:#fff; border-radius:12px; box-shadow:0 8px 22px rgba(0,0,0,.12); display:grid; grid-template-columns:2.2fr 1.3fr 1.2fr 1.2fr 1fr; align-items:center; padding:10px 10px; gap:10px; overflow:visible; min-height:58px; }
        .step-item { display:flex; align-items:center; gap:8px; padding:0; border-radius:10px; transition:all .2s ease; border:0; background:transparent; min-width:0; }
        .step-item.active { background:transparent; border-color:transparent; box-shadow:none; }
        .step-item.disabled { opacity:0.6; pointer-events:none; }
        .step-number { width:22px; height:22px; border-radius:50%; background:#f97316; color:#fff; display:grid; place-items:center; font-weight:700; font-size:12px; flex-shrink:0; }
        .step-labels { display:flex; flex-direction:row; align-items:center; gap:8px; width:100%; min-width:0; }
        .step-title { display:none; }
        .step-value { font-size:13px; font-weight:600; color:#3c4043; }
        .qb-custom { position:relative; width:100%; }
        .qb-custom.qb-disabled { opacity:0.5; pointer-events:none; }
        .qb-display { background:#fff; border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 3px 10px rgba(0,0,0,.05); cursor:pointer; gap:8px; min-height:42px; }
        .qb-value { font-size:13px; font-weight:700; color:#111; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; flex:1; min-width:0; }
        .qb-arrow { width:14px; height:14px; background:url('{{ asset('assets/images/ui/step-chevron.png') }}') center/contain no-repeat; display:inline-block; flex-shrink:0; }
        .qb-panel { position:absolute; top:110%; left:0; right:0; background:#fff; border:1px solid #e5e7eb; border-radius:12px; box-shadow:0 14px 34px rgba(0,0,0,.16); max-height:260px; overflow:auto; z-index:200; padding:6px 0; }
        .qb-option { padding:10px 14px; font-size:13px; cursor:pointer; color:#111; transition:background .15s, color .15s; display:flex; gap:8px; align-items:center; }
        .qb-option:hover { background:#f3f4f6; }
        .qb-option.selected { background:#eef2ff; color:#1d4ed8; font-weight:700; }
        .qb-option + .qb-option { border-top:1px solid #f1f5f9; }
        .quick-buy { text-align:center; display:flex; align-items:center; justify-content:center; height:100%; }
        .quick-buy .btn-primary { width:100%; min-width:150px; height:100%; min-height:46px; border-radius:8px; box-shadow:0 6px 16px rgba(53,158,255,.25); }

        /* Movies */
        .movies { padding-top:90px; padding-bottom:40px; }
        .tabs { display:flex; gap:28px; border-bottom:1px solid rgba(0,0,0,.08); margin-bottom:22px; }
        .tabs a { font-weight:700; font-size:15px; padding:14px 0; color:rgba(0,0,0,.6); position:relative; transition:color .2s; }
        .tabs a.active { color:#034ea2; }
        .tabs a.active::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:2px; background:#034ea2; }
        .movie-grid { display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:22px; }
        .movie-card { background:#fff; border-radius:12px; overflow:hidden; border:1px solid rgba(0,0,0,.04); box-shadow:0 10px 24px rgba(0,0,0,.05); position:relative; transition:transform .2s ease, box-shadow .2s ease; }
        .movie-card:hover { transform:translateY(-4px); box-shadow:0 16px 32px rgba(0,0,0,.08); }
        .poster-link { display:block; text-decoration:none; color:inherit; height:100%; position:relative; z-index:1; }
        .movie-poster { position:relative; }
        .movie-poster img { width:100%; height:360px; object-fit:cover; display:block; border-radius:12px 12px 0 0; }
        .rating-badge { position:absolute; bottom:10px; left:10px; border-radius:4px; padding:4px 6px; font-size:11px; font-weight:800; color:#fff; background:#034ea2; }
        .age-badge { position:absolute; bottom:10px; right:10px; background:#f97316; color:#fff; border-radius:6px; padding:4px 8px; font-weight:800; font-size:11px; }
        .overlay { position:absolute; inset:0; background: linear-gradient(180deg,rgba(0,0,0,.05),rgba(0,0,0,.55)); opacity:0; transition:opacity .2s; display:flex; align-items:center; justify-content:center; gap:10px; z-index:3; pointer-events:none; }
        .movie-card:hover .overlay { opacity:1; }
        .overlay .btn { border-radius:8px; padding:10px 12px; font-size:13px; font-weight:700; pointer-events:auto; }
        .overlay .btn-buy { background:#f26b38; color:#fff; border:none; }
        .overlay .btn-trailer { background:transparent; color:#fff; border:1px solid #fff; }
        .movie-card-info { padding:12px; }
        .movie-card-info .title { font-size:14px; font-weight:800; color:#111; line-height:1.3; }
        .movie-card-info .genre { font-size:12px; color:#6b7280; text-transform: capitalize; margin-top:4px; }
        .see-more { text-align:center; margin-top:24px; }
        
        /* Cinema corner */
        .section-header { display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid rgba(0,0,0,.1); margin-bottom:18px; }
        .section-header h2 { font-size:17px; font-weight:700; color: rgba(0,0,0,.7); padding-bottom:12px; }
        .articles { display:grid; grid-template-columns:2fr 1fr; gap:12px; }
        .articles .large { position:relative; border-radius:12px; overflow:hidden; }
        .articles .large .content { position:absolute; bottom:0; left:0; right:0; padding:14px; color:#fff; background: linear-gradient(180deg,transparent,rgba(0,0,0,.65)); }
        .articles-column { display:flex; flex-direction:column; gap:12px; }
        .article-card { position:relative; border-radius:12px; overflow:hidden; display:block; }
        .article-card .content { position:absolute; bottom:0; left:0; right:0; padding:12px; color:#fff; background: linear-gradient(180deg,transparent,rgba(0,0,0,.65)); }
        .category { font-size:10px; font-weight:700; padding:4px 8px; border-radius:4px; display:inline-block; }
        .category.blue { background:#1d4ed8; } .category.green { background:#22c55e; }

        /* Promotions */
        .promo-grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap:16px; }
        .promo-grid img { width:100%; border-radius:10px; }
        .promo-row { display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:16px; padding:6px 0; }
        .promo-card { background:#fff; border-radius:12px; box-shadow:0 10px 24px rgba(0,0,0,.06); border:1px solid rgba(0,0,0,.04); height:100%; display:flex; flex-direction:column; }
        .promo-card img { width:100%; height:150px; object-fit:cover; border-radius:12px 12px 0 0; display:block; }
        .promo-card .promo-title { padding:10px 12px 12px; font-weight:700; font-size:14px; color:#0f172a; line-height:1.3; flex:1; }

        /* App promo */
        .app-promo { background:#0b1f4a ; color:#fff; padding:46px 60px; display:grid; grid-template-columns:1.1fr 1fr; gap:40px; align-items:center; box-shadow:0 18px 36px rgba(0,0,0,.12); width:100%; }
        .app-image { display:flex; justify-content:center; }
        .app-image img { height:470px; object-fit:contain; filter: drop-shadow(0 18px 24px rgba(0,0,0,.35)); transition:transform .3s ease; }
        .app-image img:hover { transform:translateY(-6px); }
        .app-content { display:flex; flex-direction:column; gap:18px; align-items:flex-start; }
        .app-content h2 { font-size:32px; font-weight:800; line-height:1.2; letter-spacing:0.2px; }
        .app-description { color:#dbeafe; line-height:1.7; font-size:16px; max-width:520px; }
        .download-options { display:flex; align-items:center; gap:18px; padding-top:12px; }
        .qr-code { background:#fff; padding:12px; border-radius:12px; }
        .store-links { display:flex; flex-direction:row; gap:10px; align-items:center; }

/* About & footer */
.about { padding:36px 0; }
.about p { color: rgba(26,29,41,.8); line-height:1.6; }
.about-section-extended {
    background: linear-gradient(180deg,#f5f7fb 0%,#eef1f6 60%,#f5f7fb 100%);
    padding:36px 0 40px;
}
.about-hero { margin-bottom:20px; }
.about-hero h2 { font-size:22px; font-weight:800; color:#0f172a; margin-bottom:6px; }
.about-hero p { font-size:15px; color:#4b5563; }
.about-body { display:flex; flex-direction:column; gap:14px; max-width:1100px; }
.about-body p { font-size:15px; color:#111827; line-height:1.7; }
.about-body a.brand-link { color:#034ea2; font-weight:800; }
.about-body a.brand-link:hover { text-decoration:underline; }

/* ================= FOOTER ================ */
.footer {
    background:#232633;
    color:#fff;
    padding:40px 0 24px;
    font-size:14px;
}

/* Lưới 4 cột giống Galaxy */
.footer-inner {
    max-width:1200px;
    margin:0 auto;
    display:grid;
    grid-template-columns: minmax(0,2.2fr) repeat(3,minmax(0,1fr));
    gap:32px;
    align-items:flex-start;
}

/* Cột logo + company */
.footer-brand {
    display:flex;
    align-items:flex-start;
    gap:16px;
}

.footer-logo img {
    width:110px;
    height:auto;
    display:block;
}

.footer-company-name {
    font-weight:700;
    margin-bottom:6px;
}

.footer-company p {
    margin:0;
    color:rgba(255,255,255,.7);
}

/* Tiêu đề cột */
.footer h4 {
    text-transform:uppercase;
    font-size:13px;
    margin-bottom:12px;
    font-weight:700;
}

/* Link trong các cột Góc điện ảnh */
.footer-col a {
    display:block;
    margin-bottom:8px;
    color:rgba(255,255,255,.7);
    text-decoration:none;
}

.footer-col a:hover {
    color:#fff;
    text-decoration:underline;
}

/* Liên hệ */
.footer-contact-item {
    display:flex;
    align-items:center;
    margin-bottom:8px;
    color:rgba(255,255,255,.75);
}

.footer-icon {
    width:18px;
    height:18px;
    margin-right:10px;
    flex-shrink:0;
}

/* Social icons */
.footer-social {
    margin-top:10px;
    display:flex;
    gap:12px;
}

.footer-social-icon {
    width:26px;
    height:26px;
    display:block;
    opacity:.85;
    transition:.2s;
}

.footer-social-icon:hover {
    opacity:1;
    transform:scale(1.08);
}

/* Dòng bản quyền dưới cùng */
.footer-bottom {
    border-top:1px solid rgba(255,255,255,.1);
    margin-top:30px;
    padding-top:16px;
    text-align:center;
    font-size:12px;
    color:rgba(255,255,255,.5);
}


        /* Cinema corner */
        .cinema-corner { padding:36px 0; }
        .cinema-grid { display:grid; grid-template-columns: 2fr 1fr; gap:18px; }
        .feature-card { background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 14px 28px rgba(0,0,0,.08); display:flex; flex-direction:column; }
        .feature-media img { width:100%; height:300px; object-fit:cover; display:block; }
        .feature-body { padding:14px 16px 16px; display:flex; flex-direction:column; gap:8px; }
        .feature-title { font-size:18px; font-weight:800; color:#0f172a; line-height:1.3; }
        .feature-desc { color:#475569; font-size:14px; line-height:1.5; }
        .feature-actions { display:flex; gap:10px; margin-top:4px; }
        .feature-actions .btn { border-radius:8px; padding:10px 12px; font-size:13px; font-weight:700; }
        .feature-actions .btn-buy { background:#f26b38; color:#fff; border:none; }
        .feature-actions .btn-trailer { background:transparent; color:#034ea2; border:1px solid #034ea2; }
        .article-list { display:flex; flex-direction:column; gap:12px; }
        .article-item { display:flex; gap:10px; background:#fff; border:1px solid rgba(0,0,0,.04); border-radius:12px; overflow:hidden; box-shadow:0 8px 18px rgba(0,0,0,.05); }
        .article-thumb { width:110px; height:78px; flex-shrink:0; object-fit:cover; }
        .article-content { padding:10px 12px; display:flex; flex-direction:column; gap:6px; }
        .article-title { font-size:14px; font-weight:700; color:#0f172a; line-height:1.3; }
        .article-meta { font-size:12px; color:#64748b; }

        /* Modal auth */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.6); display:none; align-items:center; justify-content:center; z-index:60; }
        .modal-overlay.active { display:flex; }
        .modal-box { width:440px; max-width:90vw; background:#fff; border-radius:12px; box-shadow:0 12px 32px rgba(0,0,0,.18); padding:24px 28px; }
        .modal-header { text-align:center; margin-bottom:16px; }
        .modal-title { font-size:22px; font-weight:800; color:#111827; margin-bottom:6px; }
        .modal-sub { color:#6b7280; font-size:14px; }
        .auth-form { display:flex; flex-direction:column; gap:12px; margin-top:12px; }
        .auth-form label { font-size:13px; font-weight:600; color:#374151; }
        .auth-form input { width:100%; padding:12px; border:1px solid #e5e7eb; border-radius:8px; font-size:14px; }
        .auth-form input:focus { outline:none; border-color:#359eff; box-shadow:0 0 0 2px rgba(53,158,255,.15); }
        .auth-hint { font-size:12px; color:#6b7280; }
        .auth-error { font-size:12px; color:#ef4444; }
        .auth-actions { display:flex; justify-content:space-between; align-items:center; font-size:13px; }
        .auth-link { color:#359eff; font-weight:600; cursor:pointer; }
        .auth-submit { width:100%; }
        .auth-alert { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; padding:10px 12px; border-radius:10px; font-size:13px; margin-bottom:10px; font-weight:700; }
        .modal-close { position:absolute; top:12px; right:12px; border:none; background:transparent; font-size:18px; cursor:pointer; color:#6b7280; }
        .auth-success { text-align:center; }
        .auth-success img { height:120px; margin:0 auto 12px; object-fit:contain; }
        .auth-success .msg { font-size:14px; color:#374151; margin-top:6px; }
        .hidden { display:none; }

        /* Trailer modal */
        .trailer-overlay { position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(0,0,0,.78); z-index:70; padding:20px; }
        .trailer-overlay.active { display:flex; }
        .trailer-box { width:min(960px,94vw); background:#000; border-radius:12px; overflow:hidden; position:relative; box-shadow:0 20px 50px rgba(0,0,0,.4); }
        .trailer-frame { width:100%; aspect-ratio:16 / 9; border:none; display:block; background:#000; }
        .trailer-close { position:absolute; top:10px; right:10px; width:34px; height:34px; border:none; border-radius:50%; background:rgba(255,255,255,.16); color:#fff; font-size:18px; cursor:pointer; display:grid; place-items:center; }
        .trailer-close:hover { background:rgba(255,255,255,.28); }
        .trailer-footer { padding:10px 12px; background:#0b0b0b; display:flex; justify-content:flex-end; border-top:1px solid rgba(255,255,255,.08); }
        .trailer-open { color:#fff; font-weight:600; font-size:13px; text-decoration:none; opacity:.85; }
        .trailer-open:hover { opacity:1; }
        body.modal-open { overflow:hidden; }

        /* Toast */
        .toast-container { position:fixed; top:14px; right:14px; display:flex; flex-direction:column; gap:8px; z-index:120; }
        .toast { padding:12px 14px; border-radius:10px; background:#111827; color:#fff; box-shadow:0 10px 30px rgba(0,0,0,.2); opacity:0; transform:translateY(-6px); transition:opacity .18s ease, transform .18s ease; font-size:13px; max-width:320px; }
        .toast.visible { opacity:1; transform:translateY(0); }
        .toast.error { background:#b91c1c; }
        .toast.info { background:#0f172a; }

        @media(max-width:992px){
            .booking-steps{grid-template-columns:repeat(2, minmax(0,1fr));}
            .step-item{min-width:0;}
            .quick-buy .btn-primary{width:100%;}
            .app-promo{grid-template-columns:1fr;}
            .articles{grid-template-columns:1fr;}
            .hero{height:320px;}
        }
        @media(max-width:640px){
            .movies{padding-top:32px;}
            .header-container{padding:0 16px;}
            .logo span{display:none;}
            .header-left{gap:16px;}
            .booking-steps{grid-template-columns:1fr;}
            .step-item{min-width:100%;}
            .quick-buy .btn-primary{width:100%;}
        }
    </style>
    @php
        $navNow = \App\Models\Movie::where('status','now_showing')->orderByDesc('release_date')->take(4)->get();
        $navUpcoming = \App\Models\Movie::where('status','upcoming')->orderByDesc('release_date')->take(4)->get();
        $navImax = \App\Models\Movie::where('status','imax')->orderByDesc('release_date')->take(4)->get();
        $navPoster = function($m){
            $p = $m->poster_path ?? '';
            if(empty($p)) return 'https://via.placeholder.com/140x200?text=TD';
            return preg_match('/^https?:\\/\\//',$p) ? $p : asset('storage/'.ltrim($p,'/'));
        };
    @endphp
</head>
<body>
<div class="toast-container" id="toastContainer"></div>
<div class="page" id="top">
    <header class="site-header">
        <div class="header-container">
            <div class="header-left">
                <a href="{{ route('home') }}" class="logo">
                    <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema Logo" onerror="this.src='https://via.placeholder.com/40?text=TD'">
                    <span>TD Cinema</span>
                </a>
                <nav class="main-nav">
                    <ul>
                        <li><a href="#" class="nav-buy-img no-caret"><img src="{{ asset('assets/images/ui/buyticket.png') }}" alt="Mua vé"></a></li>
                        <li class="has-mega">
                            <a href="{{ route('movies.index') }}">Phim</a>
                            <div class="mega">
                                <div class="mega-grid">
                                    <div class="mega-col">
                                        <h4>Phim đang chiếu</h4>
                                        <div class="mega-list">
                                            @forelse($navNow as $movie)
                                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                                    <div class="mega-thumb"><img src="{{ $navPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                                    <div class="mega-info">
                                                        <div class="mega-title">{{ $movie->title }}</div>
                                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                                        @if($movie->rating_avg)
                                                            <div class="mega-rating">★ {{ number_format($movie->rating_avg,1) }}</div>
                                                        @endif
                                                    </div>
                                                </a>
                                            @empty
                                                <span class="mega-meta">Chưa có phim</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="mega-col">
                                        <h4>Phim sắp chiếu</h4>
                                        <div class="mega-list">
                                            @forelse($navUpcoming as $movie)
                                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                                    <div class="mega-thumb"><img src="{{ $navPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                                    <div class="mega-info">
                                                        <div class="mega-title">{{ $movie->title }}</div>
                                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                                        @if($movie->rating_avg)
                                                            <div class="mega-rating">★ {{ number_format($movie->rating_avg,1) }}</div>
                                                        @endif
                                                    </div>
                                                </a>
                                            @empty
                                                <span class="mega-meta">Chưa có phim</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="mega-col">
                                        <h4>Phim IMAX</h4>
                                        <div class="mega-list">
                                            @forelse($navImax as $movie)
                                                <a class="mega-item" href="{{ route('movies.show', $movie) }}">
                                                    <div class="mega-thumb"><img src="{{ $navPoster($movie) }}" alt="{{ $movie->title }}"></div>
                                                    <div class="mega-info">
                                                        <div class="mega-title">{{ $movie->title }}</div>
                                                        <div class="mega-meta">{{ $movie->genre ?? 'Đang cập nhật' }}</div>
                                                        @if($movie->rating_avg)
                                                            <div class="mega-rating">★ {{ number_format($movie->rating_avg,1) }}</div>
                                                        @endif
                                                    </div>
                                                </a>
                                            @empty
                                                <span class="mega-meta">Chưa có phim</span>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        <li><a href="#cinema-corner">Star Shop</a></li>
                        <li><a href="#cinema-corner">Góc Điện Ảnh</a></li>
                        <li><a href="#promotions">Sự kiện</a></li>
                        <li><a href="#promotions">Rạp/Giá vé</a></li>
                    </ul>
                </nav>
            </div>
            <div class="header-right">
                <button type="button" class="header-search-btn" aria-label="Tìm kiếm">🔍</button>
                @auth
                    <div class="user-info" id="userMenuToggle">
                        <div class="user-avatar">
                            @if(auth()->user()->avatar ?? false)
                                <img src="{{ asset('storage/' . ltrim(auth()->user()->avatar, '/')) }}" alt="Avatar">
                            @else
                                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                            @endif
                        </div>
                        <div class="user-meta">
                            <span class="user-name">{{ auth()->user()->name ?? 'User' }}</span>
                            <span class="user-tier">
                                <span>Star</span>
                                <span>·</span>
                                <span>{{ auth()->user()->points ?? 0 }} Stars</span>
                            </span>
                        </div>
                        <div class="user-menu" id="userMenu">
                            <a href="{{ route('profile.edit') }}">Tài khoản</a>
                            <a href="{{ route('customer.tickets') }}">Lịch sử vé</a>
                            <a href="#">Đổi điểm/Stars</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" style="width:100%; text-align:left; padding:8px 14px; border:none; background:transparent; cursor:pointer;">Đăng xuất</button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="#" class="header-login" id="openLogin">Đăng nhập</a>
                    <div class="join-wrapper" id="joinWrapper">
                        <a href="#" class="header-join-img" id="joinToggle">
                            <img src="{{ asset('assets/images/membership/ThamGiaCVIP.jpg') }}" alt="Tham gia" onerror="this.src='https://via.placeholder.com/90x38?text=Join'">
                        </a>
                        <div class="join-popover" id="joinPopover">
                            <div class="join-grid">
                                <div class="join-card">
                                    <img src="{{ asset('assets/images/membership/rule.png') }}" alt="Thể lệ" onerror="this.src='https://via.placeholder.com/80?text=Rule'">
                                    <h4>Thể lệ</h4>
                                    <p>Xem quy định tham gia</p>
                                    <button class="btn btn-outline">Chi tiết</button>
                                </div>
                                <div class="join-card">
                                    <img src="{{ asset('assets/images/membership/benefit.png') }}" alt="Quyền lợi" onerror="this.src='https://via.placeholder.com/80?text=Benefit'">
                                    <h4>Quyền lợi</h4>
                                    <p>Ưu đãi hội viên</p>
                                    <button class="btn btn-outline">Chi tiết</button>
                                </div>
                                <div class="join-card">
                                    <img src="{{ asset('assets/images/membership/guide.png') }}" alt="Hướng dẫn" onerror="this.src='https://via.placeholder.com/80?text=Guide'">
                                    <h4>Hướng dẫn</h4>
                                    <p>Cách sử dụng điểm</p>
                                    <button class="btn btn-outline">Chi tiết</button>
                                </div>
                                <div class="join-card">
                                    <img src="{{ asset('assets/images/membership/join.png') }}" alt="Đăng ký" onerror="this.src='https://via.placeholder.com/80?text=Join'">
                                    <h4>Đăng ký thành viên</h4>
                                    <p>Nhận ưu đãi ngay</p>
                                    <button class="btn btn-primary" id="openRegister">Đăng ký</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </header>

    <section class="hero">
        @foreach ($slides as $idx => $slide)
            <div class="hero-slide {{ $idx === 0 ? 'active' : '' }}" style="background-image:url('{{ $slide }}')"></div>
        @endforeach
        <div class="slider-dots">
            @foreach ($slides as $idx => $slide)
                <button data-slide="{{ $idx }}" class="{{ $idx === 0 ? 'active' : '' }}"></button>
            @endforeach
        </div>
        <div class="booking-bar">
            <div class="booking-steps">
                <div class="step-item active" id="stepMovie">
                    <div class="step-number">1</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Phim</span>
                        <div class="qb-custom" data-target="movie">
                            <div class="qb-display">
                                <span class="qb-value" id="movieValue"> Chọn phim </span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="moviePanel"></div>
                        </div>
                    </div>
                </div>
                <div class="step-item disabled" id="stepCinema">
                    <div class="step-number">2</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Rạp</span>
                        <div class="qb-custom qb-disabled" data-target="cinema">
                            <div class="qb-display">
                                <span class="qb-value" id="cinemaValue">-- Chọn rạp --</span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="cinemaPanel"></div>
                        </div>
                    </div>
                </div>
                <div class="step-item disabled" id="stepDate">
                    <div class="step-number">3</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Ngày</span>
                        <div class="qb-custom qb-disabled" data-target="date">
                            <div class="qb-display">
                                <span class="qb-value" id="dateValue">-- Chọn ngày --</span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="datePanel"></div>
                        </div>
                    </div>
                </div>
                <div class="step-item disabled" id="stepShowtime">
                    <div class="step-number">4</div>
                    <div class="step-labels">
                        <span class="step-title">Chọn Suất</span>
                        <div class="qb-custom qb-disabled" data-target="showtime">
                            <div class="qb-display">
                                <span class="qb-value" id="showtimeValue">-- Chọn suất --</span>
                                <span class="qb-arrow">▾</span>
                            </div>
                            <div class="qb-panel hidden" id="showtimePanel"></div>
                        </div>
                    </div>
                </div>
                <div class="quick-buy">
                    <button type="button" class="btn btn-primary" id="quickBuyBtn" disabled>Đặt vé nhanh</button>
                </div>
            </div>
        </div>
    </section>

    <main class="movies" id="now">
        <div class="container">
            <nav class="tabs">
                <a href="#" class="tab-link active" data-tab="now">PHIM ĐANG CHIẾU</a>
                <a href="#" class="tab-link" data-tab="upcoming">PHIM SẮP CHIẾU</a>
                <a href="#" class="tab-link" data-tab="imax">PHIM IMAX</a>
            </nav>

            <div class="tab-content" data-tab-panel="now">
                <div class="movie-grid">
                    @foreach($nowShowing as $movie)
                        @php
                            $age = 'C13';
                            $ratingScore = $movie->rating_avg ?? null;
                        @endphp
                        <div class="movie-card">
                            <div class="movie-poster">
                                <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                                    <img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                                </a>
                                @if($ratingScore)
                                    <div class="rating-badge">{{ number_format($ratingScore,1) }}</div>
                                @endif
                                <div class="age-badge">{{ $age }}</div>
                                <div class="overlay">
                                    <button class="btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" data-detail="{{ route('movies.show', $movie) }}">Mua vé</button>
                                    <button class="btn btn-trailer" data-trailer="{{ $movie->trailer_url }}" data-title="{{ $movie->title }}">Trailer</button>
                                </div>
                            </div>
                            <div class="movie-card-info">
                                <h3 class="title">{{ $movie->title }}</h3>
                                <p class="genre">{{ strtolower($movie->genre) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="see-more">
                    <a class="btn btn-outline" href="{{ url('/movies?status=now_showing') }}">Xem thêm</a>
                </div>
            </div>

            <div class="tab-content" data-tab-panel="upcoming" style="display:none;">
                <div class="movie-grid">
                    @foreach($upcoming as $movie)
                        @php
                            $age = 'C13';
                            $ratingScore = $movie->rating_avg ?? null;
                        @endphp
                        <div class="movie-card">
                            <div class="movie-poster">
                                <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                                    <img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                                </a>
                                @if($ratingScore)
                                    <div class="rating-badge">{{ number_format($ratingScore,1) }}</div>
                                @endif
                                <div class="age-badge">{{ $age }}</div>
                                <div class="overlay">
                                    <button class="btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" data-detail="{{ route('movies.show', $movie) }}">Mua vé</button>
                                    <button class="btn btn-trailer" data-trailer="{{ $movie->trailer_url }}" data-title="{{ $movie->title }}">Trailer</button>
                                </div>
                            </div>
                            <div class="movie-card-info">
                                <h3 class="title">{{ $movie->title }}</h3>
                                <p class="genre">{{ strtolower($movie->genre) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="tab-content" data-tab-panel="imax" style="display:none;">
                <div class="movie-grid">
                    @foreach($imax as $movie)
                        @php
                            $age = 'C13';
                            $ratingScore = $movie->rating_avg ?? null;
                        @endphp
                        <div class="movie-card">
                            <div class="movie-poster">
                                <a class="poster-link" href="{{ route('movies.show', $movie) }}" title="{{ $movie->title }}">
                                    <img src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                                </a>
                                @if($ratingScore)
                                    <div class="rating-badge">{{ number_format($ratingScore,1) }}</div>
                                @endif
                                <div class="age-badge">{{ $age }}</div>
                                <div class="overlay">
                                    <button class="btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" data-detail="{{ route('movies.show', $movie) }}">Mua vé</button>
                                    <button class="btn btn-trailer" data-trailer="{{ $movie->trailer_url }}" data-title="{{ $movie->title }}">Trailer</button>
                                </div>
                            </div>
                            <div class="movie-card-info">
                                <h3 class="title">{{ $movie->title }}</h3>
                                <p class="genre">{{ strtolower($movie->genre) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </main>

    <section id="cinema-corner" class="cinema-corner">
        <div class="container">
            <div class="section-header">
                <div class="section-header-left">
                    <h2>Góc Điện Ảnh</h2>
                    <nav><a href="#" class="active">Bình luận phim</a><a href="#" style="margin-left:12px; color:#94a3b8;">Blog điện ảnh</a></nav>
                </div>
                <a href="#" class="see-all-link">See all</a>
            </div>
            <div class="cinema-grid">
                @if($featureArticle)
                    <div class="feature-card">
                        <div class="feature-media">
                            <img src="{{ $bannerUrl($featureArticle) }}" alt="{{ $featureArticle->title }}" loading="lazy" decoding="async">
                        </div>
                        <div class="feature-body">
                            <div class="feature-title">[Review] {{ $featureArticle->title }}</div>
                            <div class="feature-desc">{{ $shortIntro($featureArticle) }}</div>
                            <div class="feature-actions">
                                <button class="btn feature-btn btn-buy" data-movie-id="{{ $featureArticle->id }}" data-movie-title="{{ $featureArticle->title }}">Mua vé</button>
                                <button class="btn feature-btn btn-trailer" data-trailer="{{ $featureArticle->trailer_url }}" data-title="{{ $featureArticle->title }}">Trailer</button>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="article-list">
                    @forelse($articleList as $movie)
                        <div class="article-item">
                            <img class="article-thumb" src="{{ $posterUrl($movie) }}" alt="{{ $movie->title }}" loading="lazy" decoding="async">
                            <div class="article-content">
                                <div class="article-title">[Review] {{ $movie->title }}</div>
                                <div class="article-meta">{{ $shortIntro($movie) }}</div>
                                <div class="feature-actions" style="gap:6px;">
                                    <button class="btn feature-btn btn-buy" data-movie-id="{{ $movie->id }}" data-movie-title="{{ $movie->title }}" style="padding:8px 10px;">Mua vé</button>
                                    <button class="btn feature-btn btn-trailer" data-trailer="{{ $movie->trailer_url }}" data-title="{{ $movie->title }}" style="padding:8px 10px;">Trailer</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="article-meta">Chưa có nội dung.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <section id="promotions" class="promotions-section">
        <div class="container">
            <div class="section-header"><div class="section-header-left"><h2 class="active">Khuyến mãi</h2></div></div>
            <div class="promo-row">
                @forelse ($promotions as $promo)
                    <div class="promo-card">
                        <img src="{{ $promo->thumbnail ? asset('storage/' . $promo->thumbnail) : 'https://via.placeholder.com/260x150?text=Promo' }}" alt="{{ $promo->name }}" loading="lazy" decoding="async">
                        <div class="promo-title">{{ $promo->name }}</div>
                    </div>
                @empty
                    @foreach (range(1,4) as $i)
                        <div class="promo-card">
                            <img src="https://via.placeholder.com/260x150?text=Promo+{{ $i }}" alt="Promotion {{ $i }}" loading="lazy" decoding="async">
                            <div class="promo-title">Promotion {{ $i }}</div>
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    <section id="app-promo" class="app-promo-section" style="padding:0;">
        <div class="app-promo">
            <div class="app-image">
                <img src="{{ asset('assets/images/app/Frame-iphone-x.78ef1223.svg') }}" alt="TD Cinema App" loading="lazy" decoding="async">
            </div>
            <div class="app-content">
                <h2>Đặt Vé Online - Không Lo Trễ Nải</h2>
                <p class="app-description">Ghét đông đúc ồn ào? Lười xếp hàng mua vé? Quên cách mua vé giấy truyền thống, hãy quét QR hoặc tải app.</p>
                <div class="download-options">
                    <div class="qr-code"><img src="{{ asset('assets/images/app/glx-qr-code-1.218ae7da.svg') }}" alt="QR" loading="lazy" decoding="async"></div>
                    <span class="text-white text-sm font-semibold" style="letter-spacing:1px;">OR</span>
                    <div class="store-links">
                        <a href="https://apps.apple.com/vn/app/id593312549" target="_blank"><img src="{{ asset('assets/images/app/icon-ios-app-store.65ed00df.svg') }}" alt="App Store" loading="lazy" decoding="async"></a>
                        <a href="https://play.google.com/store/apps/details?id=com.galaxy.cinema&hl=vi" target="_blank"><img src="{{ asset('assets/images/app/badge-googleplay.svg') }}" alt="Google Play" loading="lazy" decoding="async"></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section-extended">
        <div class="container">
            <div class="about-hero">
                <h2>TRANG CHỦ</h2>
                <p>TD Cinema là hệ thống rạp chiếu phim mô phỏng do sinh viên Phan Trọng Đại phát triển trong đồ án chuyên ngành.</p>
            </div>
            <div class="about-body">
                <p><a href="#top" class="brand-link">TD Cinema</a> là hệ thống rạp chiếu phim mô phỏng được phát triển trong khuôn khổ đồ án chuyên ngành của sinh viên Phan Trọng Đại (ngành Công nghệ Thông tin), với mục tiêu xây dựng một nền tảng đặt vé xem phim trực tuyến hiện đại, thân thiện và mô phỏng sát với mô hình vận hành thực tế của các rạp chiếu phim chuyên nghiệp.</p>
                <p>Dù chỉ mới được phát triển ở giai đoạn đầu, <a href="#top" class="brand-link">TD Cinema</a> đã được thiết kế theo cấu trúc của một hệ thống rạp chiếu phim đầy đủ, bao gồm quản lý phim, phòng chiếu, suất chiếu, đặt vé, sơ đồ ghế, mã QR vào rạp và quản lý người dùng. Đây không chỉ là sản phẩm mô phỏng mà còn là nơi để vận dụng tổng hợp kiến thức từ lập trình web, cơ sở dữ liệu, UI/UX đến tư duy thiết kế hệ thống.</p>
                <p>Trên website <a href="#top" class="brand-link">TD Cinema</a>, người dùng có thể dễ dàng xem danh sách phim đang chiếu, phim sắp chiếu, thông tin chi tiết về phim, diễn viên, trailer cũng như lịch chiếu được cập nhật liên tục. Hệ thống đặt vé được xây dựng trực quan: người xem có thể chọn vé theo phim, theo rạp hoặc theo ngày, sau đó lựa chọn chỗ ngồi theo sơ đồ được mô phỏng như rạp thật. Khi hoàn tất thao tác, người dùng nhận được mã xác nhận hoặc mã QR để vào rạp nhanh chóng.</p>
                <p>Hệ thống còn tích hợp tính năng Đặt Vé Nhanh, hỗ trợ người dùng hoàn tất việc mua vé trong thời gian ngắn — mô phỏng theo những nền tảng rạp chiếu phim chuyên nghiệp như Galaxy Cinema. Bên cạnh đó, <a href="#top" class="brand-link">TD Cinema</a> cũng mô phỏng các chương trình ưu đãi, giảm giá vé và combo bắp nước nhằm tái hiện hoạt động kinh doanh phổ biến của các chuỗi rạp.</p>
                <p>Là người thực hiện hoàn toàn hệ thống, Phan Trọng Đại chú trọng vào việc xây dựng cơ sở dữ liệu chuẩn hóa, giao diện dễ sử dụng và trải nghiệm đặt vé mượt mà. <a href="#top" class="brand-link">TD Cinema</a> không chỉ là một bài tập lập trình mà còn là dự án giúp hoàn thiện kỹ năng phân tích hệ thống, thiết kế kiến trúc, xử lý quy trình nghiệp vụ và phát triển sản phẩm thực tế.</p>
                <p>Trong tương lai, dự án <a href="#top" class="brand-link">TD Cinema</a> có thể tiếp tục mở rộng thêm nhiều chức năng mới, tối ưu hiệu năng và nâng cao trải nghiệm người dùng — hướng đến mô hình của một hệ thống rạp chiếu phim hoàn chỉnh và chuyên nghiệp hơn.</p>
            </div>
        </div>
    </section>

    <footer class="footer">
    <div class="footer-inner">
        <!-- Cột logo + thông tin công ty -->
        <div class="footer-brand">
            <div class="footer-logo">
                <img src="{{ asset('assets/images/logo/logo.jpg') }}" alt="TD Cinema" onerror="this.src='https://via.placeholder.com/110x40?text=TD';" />
            </div>
            <div class="footer-company">
                <p class="footer-company-name">CÔNG TY CỔ PHẦN PHIM ĐẠI TRƯỜNG</p>
                <p>180 Cao Lỗ, phường Chánh Hưng, TP. Hồ Chí Minh</p>
            </div>
        </div>

        <!-- Góc điện ảnh -->
        <div class="footer-col">
            <h4>GÓC ĐIỆN ẢNH</h4>
            <a href="#">Thể loại phim</a>
            <a href="#">Bình luận phim</a>
            <a href="#">Blog điện ảnh</a>
            <a href="#">Phim hay tháng</a>
        </div>

        <div class="footer-col">
            <h4>GÓC ĐIỆN ẢNH</h4>
            <a href="#">Thể loại phim</a>
            <a href="#">Bình luận phim</a>
            <a href="#">Blog điện ảnh</a>
            <a href="#">Phim hay tháng</a>
        </div>

        <!-- Liên hệ + SVG icon -->
        <div class="footer-col footer-contact">
            <h4>LIÊN HỆ VỚI CHÚNG TÔI</h4>

            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/location-dot-solid-full.svg') }}" alt="" class="footer-icon">
                <span>180 Cao Lỗ, phường Chánh Hưng, TP.HCM</span>
            </div>

            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/phone-solid-full.svg') }}" alt="" class="footer-icon">
                <span>1900 1234</span>
            </div>

            <div class="footer-contact-item">
                <img src="{{ asset('assets/images/footer/message-solid-full.svg') }}" alt="" class="footer-icon">
                <span>support@tdcinema.com</span>
            </div>

            <div class="footer-social">
                <a href="#">
                    <img src="{{ asset('assets/images/footer/facebook-brands-solid-full.svg') }}"
                         alt="Facebook" class="footer-social-icon">
                </a>
                <a href="#">
                    <img src="{{ asset('assets/images/footer/instagram-brands-solid-full.svg') }}"
                         alt="Instagram" class="footer-social-icon">
                </a>
                <a href="#">
                    <img src="{{ asset('assets/images/footer/youtube-brands-solid-full.svg') }}"
                         alt="Youtube" class="footer-social-icon">
                </a>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        © 2025 TD Cinema. All Rights Reserved. Designed for demonstration purposes.
    </div>
</footer>

</div>

<!-- Trailer Modal -->
<div class="trailer-overlay" id="trailerOverlay">
    <div class="trailer-box">
        <button class="trailer-close" id="closeTrailer" aria-label="Đóng trailer">×</button>
        <iframe id="trailerFrame" class="trailer-frame" src="" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        <div class="trailer-footer">
            <a id="trailerLink" class="trailer-open" href="#" target="_blank" rel="noreferrer">Mở trên YouTube</a>
        </div>
    </div>
</div>

<!-- Auth Modals -->
<div class="modal-overlay" id="authOverlay">
    <div class="modal-box" id="loginBox">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Chào mừng trở lại!</div>
            <div class="modal-sub">Đăng nhập để tiếp tục trải nghiệm của bạn.</div>
        </div>
        @if($errors->has('email'))
            <div class="auth-alert">{{ $errors->first('email') }}</div>
        @endif
        <form class="auth-form" method="POST" action="{{ route('login') }}">
            @csrf
            <div>
                <label>Email hoặc Tên đăng nhập</label>
                <input type="text" name="email" value="{{ old('email') }}" placeholder="Nhập email hoặc tên đăng nhập">
            </div>
            <div>
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu của bạn">
            </div>
            <div class="auth-actions">
                <span class="auth-link" id="openForgot">Quên mật khẩu?</span>
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Đăng nhập</button>
            <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                <span>Chưa có tài khoản?</span>
                <span class="auth-link" id="switchRegister">Đăng ký ngay</span>
            </div>
        </form>
    </div>

    <div class="modal-box" id="registerBox" style="display:none;">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header">
            <div class="modal-title">Tạo tài khoản mới</div>
            <div class="modal-sub">Bắt đầu hành trình điện ảnh của bạn hôm nay.</div>
        </div>
        <form class="auth-form" method="POST" action="{{ route('register') }}">
            @csrf
            <div>
                <label>Họ và tên</label>
                <input type="text" name="name" placeholder="Nguyễn Văn A">
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" placeholder="nguyenvana@email.com">
            </div>
            <div>
                <label>Số điện thoại</label>
                <input type="text" name="phone" placeholder="0901234567">
            </div>
            <div>
                <label>Mật khẩu</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu của bạn">
                <div class="auth-hint">Phải có ít nhất 8 ký tự.</div>
            </div>
            <div>
                <label>Xác nhận mật khẩu</label>
                <input type="password" name="password_confirmation" placeholder="Nhập lại mật khẩu">
            </div>
            <button type="submit" class="btn btn-primary auth-submit">Đăng ký</button>
            <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                <span>Đã có tài khoản?</span>
                <span class="auth-link" id="switchLogin">Đăng nhập ngay</span>
            </div>
        </form>
    </div>

    <div class="modal-box" id="forgotBox" style="display:none;">
        <button class="modal-close" data-close>×</button>
        <div class="modal-header" id="forgotHeader">
            <div class="modal-title">Quên mật khẩu</div>
            <div class="modal-sub">Nhập email để nhận mã đặt lại mật khẩu.</div>
        </div>
        <div id="forgotFormWrap">
            <form class="auth-form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div>
                    <label>Email</label>
                    <input type="email" name="email" placeholder="you@example.com">
                </div>
                <button type="submit" class="btn btn-primary auth-submit">Gửi mã</button>
                <div class="auth-actions" style="justify-content:center; gap:4px; margin-top:8px;">
                    <span class="auth-link" id="backLogin">Quay lại đăng nhập</span>
                </div>
            </form>
        </div>
        <div id="forgotSuccess" class="auth-success hidden">
            <img src="{{ asset('assets/images/membership/benefit.png') }}" alt="Quên mật khẩu" onerror="this.style.display='none'">
            <div class="modal-title" style="margin-top:8px;">Quên mật khẩu?</div>
            <div class="msg">Email khôi phục mật khẩu đã được gửi thành công tới <strong id="resetEmailTxt"></strong>.</div>
            <button type="button" class="btn btn-primary auth-submit" id="closeForgotSuccess">Đóng</button>
        </div>
    </div>
</div>

<script>
    // Tabs
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const tab = link.dataset.tab;
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');
            document.querySelectorAll('[data-tab-panel]').forEach(panel => {
                panel.style.display = (panel.dataset.tabPanel === tab) ? 'block' : 'none';
            });
        });
    });

    // Slider
    const slides = Array.from(document.querySelectorAll('.hero-slide'));
    const dots = Array.from(document.querySelectorAll('.slider-dots button'));
    let current = 0;
    function showSlide(i) {
        slides.forEach((s, idx) => s.classList.toggle('active', idx === i));
        dots.forEach((d, idx) => d.classList.toggle('active', idx === i));
        current = i;
    }
    dots.forEach(d => d.addEventListener('click', () => showSlide(Number(d.dataset.slide))));
    setInterval(() => showSlide((current + 1) % slides.length), 5000);

    // Toast helper
    const toastContainer = document.getElementById('toastContainer');
    function showToast(message, type = 'info', duration = 3200) {
        if (!toastContainer) {
            alert(message);
            return;
        }
        const el = document.createElement('div');
        el.className = `toast ${type}`;
        el.textContent = message;
        toastContainer.appendChild(el);
        requestAnimationFrame(() => el.classList.add('visible'));
        setTimeout(() => {
            el.classList.remove('visible');
            setTimeout(() => el.remove(), 220);
        }, duration);
    }

    // Join popover
    const joinToggle = document.getElementById('joinToggle');
    const joinPopover = document.getElementById('joinPopover');
    const joinWrapper = document.getElementById('joinWrapper');
    const authOverlay = document.getElementById('authOverlay');
    const loginBox = document.getElementById('loginBox');
    const registerBox = document.getElementById('registerBox');
    const forgotBox = document.getElementById('forgotBox');

    function closeJoin() {
        if (joinPopover) joinPopover.style.display = 'none';
    }
    if (joinToggle && joinPopover) {
        joinToggle.addEventListener('click', (e) => {
            e.preventDefault();
            joinPopover.style.display = joinPopover.style.display === 'block' ? 'none' : 'block';
        });
    }

    // User menu
    const userToggle = document.getElementById('userMenuToggle');
    const userMenu = document.getElementById('userMenu');
    if (userToggle && userMenu) {
        userToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            userMenu.style.display = userMenu.style.display === 'block' ? 'none' : 'block';
        });
    }

    // Auth modals
    const openLogin = document.getElementById('openLogin');
    const openRegister = document.getElementById('openRegister');
    const switchRegister = document.getElementById('switchRegister');
    const switchLogin = document.getElementById('switchLogin');
    const openForgot = document.getElementById('openForgot');
    const backLogin = document.getElementById('backLogin');

    function showModal(box) {
        if (!authOverlay) return;
        authOverlay.classList.add('active');
        [loginBox, registerBox, forgotBox].forEach(b => { if (b) b.style.display = 'none'; });
        if (box) box.style.display = 'block';
    }
    function closeModal() {
        if (authOverlay) authOverlay.classList.remove('active');
    }
    if (openLogin) openLogin.addEventListener('click', e => { e.preventDefault(); closeJoin(); showModal(loginBox); });
    if (switchRegister) switchRegister.addEventListener('click', e => { e.preventDefault(); showModal(registerBox); });
    if (switchLogin) switchLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });
    if (openRegister) openRegister.addEventListener('click', e => { e.preventDefault(); closeJoin(); showModal(registerBox); });
    if (openForgot) openForgot.addEventListener('click', e => { e.preventDefault(); showModal(forgotBox); });
    if (backLogin) backLogin.addEventListener('click', e => { e.preventDefault(); showModal(loginBox); });

    document.querySelectorAll('[data-close]').forEach(btn => btn.addEventListener('click', closeModal));
    if (authOverlay) {
        authOverlay.addEventListener('click', e => {
            if (e.target === authOverlay) closeModal();
        });
    }

    // Click outside to close popovers/menus
    document.addEventListener('click', (e) => {
        if (joinWrapper && !joinWrapper.contains(e.target)) closeJoin();
        if (userMenu && userToggle && !userToggle.contains(e.target)) userMenu.style.display = 'none';
    });

    // Nếu có lỗi login, mở sẵn modal
    @if($errors->has('email'))
        showModal(loginBox);
    @endif

    // Nếu vừa gửi mail reset thành công, hiện modal success
    @if(session('reset_sent'))
        showModal(forgotBox);
        const formWrap = document.getElementById('forgotFormWrap');
        const header = document.getElementById('forgotHeader');
        const successBox = document.getElementById('forgotSuccess');
        const emailTxt = document.getElementById('resetEmailTxt');
        if (formWrap && successBox) {
            formWrap.classList.add('hidden');
            successBox.classList.remove('hidden');
            if (header) header.classList.add('hidden');
            if (emailTxt) emailTxt.textContent = "{{ session('reset_email') }}";
        }
    @endif

    const closeForgotSuccess = document.getElementById('closeForgotSuccess');
    if (closeForgotSuccess) {
        closeForgotSuccess.addEventListener('click', () => {
            closeModal();
            // reset form view for next time
            const formWrap = document.getElementById('forgotFormWrap');
            const successBox = document.getElementById('forgotSuccess');
            const header = document.getElementById('forgotHeader');
            if (formWrap && successBox) {
                formWrap.classList.remove('hidden');
                successBox.classList.add('hidden');
                if (header) header.classList.remove('hidden');
            }
        });
    }

    // Trailer modal
    const trailerOverlay = document.getElementById('trailerOverlay');
    const trailerFrame = document.getElementById('trailerFrame');
    const closeTrailerBtn = document.getElementById('closeTrailer');
    const trailerLink = document.getElementById('trailerLink');

    function toTrailerEmbed(url) {
        if (!url) return '';
        try {
            const u = new URL(url);
            if (u.hostname.includes('youtube.com')) {
                const vid = u.searchParams.get('v');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
                if (u.pathname.startsWith('/embed/')) {
                    return `${u.origin}${u.pathname}${u.search ? `${u.search}&autoplay=1` : '?autoplay=1'}`;
                }
            }
            if (u.hostname === 'youtu.be') {
                const vid = u.pathname.replace('/', '');
                if (vid) return `https://www.youtube.com/embed/${vid}?autoplay=1`;
            }
        } catch (err) {
            // giữ nguyên nếu URL không hợp lệ hoặc không parse được
        }
        return url;
    }

    function openTrailer(url) {
        if (!url) {
            alert('Chưa có trailer cho phim này.');
            return;
        }
        const targetUrl = toTrailerEmbed(url);
        if (!trailerOverlay || !trailerFrame) {
            window.open(url, '_blank');
            return;
        }
        trailerFrame.src = targetUrl;
        if (trailerLink) {
            trailerLink.href = url;
            trailerLink.style.display = url ? 'inline-flex' : 'none';
        }
        trailerOverlay.classList.add('active');
        document.body.classList.add('modal-open');
    }

    function closeTrailer() {
        if (!trailerOverlay || !trailerFrame) return;
        trailerOverlay.classList.remove('active');
        trailerFrame.src = '';
        if (trailerLink) {
            trailerLink.href = '#';
        }
        document.body.classList.remove('modal-open');
    }

    closeTrailerBtn?.addEventListener('click', closeTrailer);
    trailerOverlay?.addEventListener('click', (e) => {
        if (e.target === trailerOverlay) closeTrailer();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && trailerOverlay?.classList.contains('active')) {
            closeTrailer();
        }
    });

    // Booking bar logic (custom dropdown, tuần tự 1-2-3-4)
    const isAuthenticated = @json(auth()->check());
    const dropdowns = {
        movie: {box: document.querySelector('.qb-custom[data-target="movie"]'), panel: document.getElementById('moviePanel'), valueEl: document.getElementById('movieValue'), placeholder: '-- Chọn phim --'},
        cinema:{box: document.querySelector('.qb-custom[data-target="cinema"]'), panel: document.getElementById('cinemaPanel'), valueEl: document.getElementById('cinemaValue'), placeholder: '-- Chọn rạp --'},
        date:  {box: document.querySelector('.qb-custom[data-target="date"]'), panel: document.getElementById('datePanel'), valueEl: document.getElementById('dateValue'), placeholder: '-- Chọn ngày --'},
        showtime:{box: document.querySelector('.qb-custom[data-target="showtime"]'), panel: document.getElementById('showtimePanel'), valueEl: document.getElementById('showtimeValue'), placeholder: '-- Chọn suất --'},
    };
    const quickBuyBtn = document.getElementById('quickBuyBtn');

    const moviesData = @json(($nowShowing ?? collect())->map(fn($m) => ['value'=>$m->id,'label'=>$m->title])->values());

    let selectedMovie = '', selectedCinema = '', selectedDate = '', selectedShowtime = '';
    let cinemaOptions = [];
    const dateOptionsByCinema = {};
    const showtimesByCinemaDate = {};
    const stepIds = {
        movie: 'stepMovie',
        cinema: 'stepCinema',
        date: 'stepDate',
        showtime: 'stepShowtime',
    };

    function setDisabled(name, disabled, resetLabel=true) {
        const d = dropdowns[name];
        if (!d) return;
        d.box?.classList.toggle('qb-disabled', disabled);
        if (resetLabel && d.valueEl) {
            d.valueEl.textContent = d.placeholder;
            d.valueEl.title = d.placeholder;
        }
        if (d.panel) d.panel.innerHTML = '';
        const step = document.getElementById(stepIds[name] || '');
        if (step) {
            step.classList.toggle('disabled', disabled);
            if (!disabled && name !== 'movie') {
                step.classList.add('active');
            }
        }
    }

    function renderOptions(name, data, onSelect, selectedValue = '') {
        const d = dropdowns[name];
        if (!d || !d.panel) return;
        d.panel.innerHTML = '';
        if (!data || data.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'qb-option';
            empty.textContent = 'Không có lựa chọn';
            empty.style.opacity = '0.7';
            d.panel.appendChild(empty);
            return;
        }
        data.forEach(item => {
            const opt = document.createElement('div');
            opt.className = 'qb-option';
            opt.dataset.value = item.value;
            opt.textContent = item.label;
            if (selectedValue && selectedValue === item.value) {
                opt.classList.add('selected');
            }
            opt.addEventListener('click', () => {
                d.panel.querySelectorAll('.qb-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                d.valueEl.textContent = item.label;
                d.valueEl.title = item.label;
                d.panel.classList.add('hidden');
                onSelect(item);
            });
            d.panel.appendChild(opt);
        });
    }

    function closeAllPanels(except) {
        Object.values(dropdowns).forEach(d => {
            if (d.panel && d.panel !== except) d.panel.classList.add('hidden');
        });
    }

    // Toggle panels on click
    Object.keys(dropdowns).forEach(name => {
        const d = dropdowns[name];
        if (!d.box || !d.panel) return;
        const display = d.box.querySelector('.qb-display');
        display?.addEventListener('click', () => {
            if (d.box.classList.contains('qb-disabled')) return;
            closeAllPanels(d.panel);
            d.panel.classList.toggle('hidden');
        });
    });
    document.addEventListener('click', e => {
        const isInside = Object.values(dropdowns).some(d => d.box && d.box.contains(e.target));
        if (!isInside) closeAllPanels();
    });

    async function loadBookingData(movieId) {
        cinemaOptions = [];
        Object.keys(dateOptionsByCinema).forEach(k => delete dateOptionsByCinema[k]);
        Object.keys(showtimesByCinemaDate).forEach(k => delete showtimesByCinemaDate[k]);
        setDisabled('cinema', true);
        setDisabled('date', true);
        setDisabled('showtime', true);
        if (quickBuyBtn) quickBuyBtn.disabled = true;

        try {
            const res = await fetch(`{{ route('booking.options') }}?movie_id=${movieId}`, { headers: { 'Accept': 'application/json' }});
            if (!res.ok) throw new Error('Server error');
            const data = await res.json();
            cinemaOptions = (data.cinemas || []).map(c => ({
                value: c.id,
                label: c.name || 'Rạp',
                raw: c,
            }));
            cinemaOptions.forEach(c => {
                const dates = (c.raw.dates || []).map(d => ({ value: d.date, label: d.label }));
                dateOptionsByCinema[c.value] = dates;
                (c.raw.dates || []).forEach(d => {
                    const key = `${c.value}-${d.date}`;
                    showtimesByCinemaDate[key] = (d.showtimes || []).map(st => ({
                        value: st.id,
                        label: `${st.time} · ${st.room}`,
                    }));
                });
            });

            setDisabled('cinema', cinemaOptions.length === 0, true);
            renderOptions('cinema', cinemaOptions, cinemaSelected, selectedCinema);
            if (cinemaOptions.length === 0 && dropdowns.cinema?.panel) {
                dropdowns.cinema.panel.innerHTML = '<div class="qb-option" style="opacity:.7;">Không có suất cho phim này</div>';
                showToast('Phim này chưa có suất chiếu khả dụng.', 'info');
            }
        } catch (err) {
            console.error(err);
            setDisabled('cinema', true);
            if (dropdowns.cinema?.panel) {
                dropdowns.cinema.panel.innerHTML = '<div class="qb-option" style="opacity:.7;">Không tải được dữ liệu</div>';
            }
            showToast('Không tải được dữ liệu suất chiếu, vui lòng thử lại sau.', 'error');
        }
    }

    // Flows
    renderOptions('movie', moviesData, (item) => {
        selectedMovie = item.value;
        selectedCinema = selectedDate = selectedShowtime = '';
        loadBookingData(selectedMovie);
    }, selectedMovie);

    function cinemaSelected(item) {
        selectedCinema = item.value;
        selectedDate = selectedShowtime = '';
        const dates = dateOptionsByCinema[selectedCinema] || [];
        setDisabled('date', dates.length === 0, true);
        setDisabled('showtime', true);
        renderOptions('date', dates, dateSelected, selectedDate);
        if (quickBuyBtn) quickBuyBtn.disabled = true;
        if (dates.length === 0) {
            showToast('Rạp này chưa có lịch chiếu cho phim đã chọn.', 'info');
        }
    }
    function dateSelected(item) {
        selectedDate = item.value;
        selectedShowtime = '';
        const key = `${selectedCinema}-${selectedDate}`;
        const times = showtimesByCinemaDate[key] || [];
        setDisabled('showtime', times.length === 0, true);
        renderOptions('showtime', times, showtimeSelected, selectedShowtime);
        if (quickBuyBtn) quickBuyBtn.disabled = true;
        if (times.length === 0) {
            showToast('Ngày này chưa có suất chiếu.', 'info');
        }
    }
    function showtimeSelected(item) {
        selectedShowtime = item.value;
        if (quickBuyBtn) quickBuyBtn.disabled = !(selectedMovie && selectedCinema && selectedDate && selectedShowtime);
    }

    function selectMovie(movieId, label) {
        selectedMovie = movieId;
        selectedCinema = selectedDate = selectedShowtime = '';
        if (dropdowns.movie.valueEl) {
            dropdowns.movie.valueEl.textContent = label || dropdowns.movie.placeholder;
            dropdowns.movie.valueEl.title = label || dropdowns.movie.placeholder;
        }
        setDisabled('cinema', true);
        setDisabled('date', true);
        setDisabled('showtime', true);
        loadBookingData(movieId);
        closeAllPanels();
        const bar = document.querySelector('.booking-bar');
        if (bar) bar.scrollIntoView({ behavior: 'smooth' });
    }

    // Buy buttons on cards
    document.querySelectorAll('.btn-buy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const detailUrl = btn.dataset.detail;
            if (detailUrl) {
                window.location.href = detailUrl;
                return false;
            }
            const id = btn.dataset.movieId;
            const title = btn.dataset.movieTitle;
            if (id) selectMovie(id, title);
        });
    });

    // Trailer buttons on cards
    document.querySelectorAll('.btn-trailer').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            openTrailer(btn.dataset.trailer);
        });
    });

    // Ngăn overlay click truyền xuống poster link
    document.querySelectorAll('.overlay').forEach(ov => {
        ov.addEventListener('click', (e) => e.stopPropagation());
    });

    quickBuyBtn?.addEventListener('click', () => {
        if (quickBuyBtn.disabled) return;
        if (!isAuthenticated) {
            closeAllPanels();
            if (typeof showModal === 'function') {
                showModal(document.getElementById('loginBox'));
            }
            return;
        }
        window.location.href = "{{ url('/booking/checkout') }}/" + selectedShowtime;
    });
</script>
</body>
</html>
