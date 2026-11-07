@php $memberSummary = $memberSummary ?? app(\App\Services\MembershipService::class)->summary(auth()->user()); @endphp
<section class="membership-mini">
    <div><span class="membership-eyebrow">THÀNH VIÊN TD CINEMA</span><h2>{{ $memberSummary['level']?->name ?? 'Thành viên TD' }}</h2><p>{{ number_format($memberSummary['points'],0,',','.') }} Stars đã tích lũy</p></div>
    <div class="membership-mini-progress">
        @if($memberSummary['next'])<p>Còn <strong>{{ number_format($memberSummary['remaining'],0,',','.') }} Stars</strong> để đạt {{ $memberSummary['next']->name }}</p>@else<p>Bạn đang ở hạng cao nhất hiện tại.</p>@endif
        <div class="membership-progress" role="progressbar" aria-label="Tiến độ lên hạng" aria-valuenow="{{ round($memberSummary['progress']) }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $memberSummary['progress'] }}%"></span></div>
        <a href="{{ route('customer.membership') }}">Xem cấp bậc & quyền lợi →</a>
    </div>
</section>
