@once
@php $pageMessages = \App\Support\NotificationMessages::forPage(session()->driver(), $errors ?? new \Illuminate\Support\ViewErrorBag(), $pageNotifications ?? []); @endphp
<div class="td-notifications" id="td-notifications" aria-label="Thông báo" hidden>
    <div class="td-notifications__toolbar"><span>Thông báo</span><button type="button" data-notifications-clear>Đóng tất cả</button></div>
    <div class="td-notifications__list" data-notifications-list></div>
</div>
<dialog class="td-confirm" id="td-confirm" aria-labelledby="td-confirm-title" aria-describedby="td-confirm-message">
    <div class="td-confirm__icon" aria-hidden="true">!</div>
    <h2 id="td-confirm-title">Xác nhận thao tác</h2><p id="td-confirm-message"></p>
    <div class="td-confirm__actions"><button type="button" class="td-confirm__cancel" data-confirm-cancel>Hủy</button><button type="button" class="td-confirm__accept" data-confirm-accept>Đồng ý</button></div>
</dialog>
<script type="application/json" id="td-notification-data">{!! json_encode($pageMessages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<noscript><div class="td-notifications-fallback">@foreach($pageMessages as $notice)<p>{{ $notice['message'] }}</p>@if(!empty($notice['details']))<ul>@foreach($notice['details'] as $detail)<li>{{ $detail }}</li>@endforeach</ul>@endif @endforeach</div></noscript>
@endonce
