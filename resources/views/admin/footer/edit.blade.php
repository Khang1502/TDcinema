@extends('admin.layouts.app')
@section('title','Quản lý footer')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/admin/footer-editor.css') }}?v={{ filemtime(public_path('assets/css/admin/footer-editor.css')) }}">@endpush
@section('content')
@php $sections=['brand'=>['panel-bottom','Thương hiệu','Tên, giới thiệu & bản quyền'],'support'=>['circle-help','Liên kết hỗ trợ','Các trang hỗ trợ khách hàng'],'editorial'=>['newspaper','Góc điện ảnh','Nội dung & hệ thống rạp'],'contact'=>['building-2','Thông tin liên hệ','Địa chỉ, hotline & email'],'social'=>['globe','Mạng xã hội','Facebook, Instagram, YouTube']]; @endphp
<div class="fe-page">
    <header class="fe-heading"><div><span class="fe-eyebrow">TD CINEMA · NỘI DUNG WEBSITE</span><h1>Thiết lập footer</h1><p>Chỉnh nội dung cuối trang theo từng nhóm. Lưu một lần để áp dụng trên website.</p></div><a class="fe-button" href="{{ route('home') }}#site-footer" target="_blank" rel="noopener">@include('admin.partials.icon',['name'=>'external-link','size'=>17]) Xem trên website</a></header>
    @if($errors->any())<div class="fe-errors" data-inline-notification role="alert"><strong>Vui lòng kiểm tra các trường được đánh dấu.</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form id="footer-settings-form" method="POST" action="{{ route('admin.footer.update') }}" data-footer-editor>@csrf @method('PUT')
        <div class="fe-layout"><aside class="fe-sidebar"><div class="fe-brand"><img src="{{ asset('assets/images/logo/logo-transparent.png') }}" alt="Logo TD Cinema"><div><strong>Footer website</strong><span data-footer-visibility>{{ old('is_visible',$settings->is_visible) ? 'Đang hiển thị' : 'Đang tạm ẩn' }}</span></div></div><nav class="fe-tabs" aria-label="Nhóm nội dung footer">@foreach($sections as $key=>[$icon,$title,$hint])<button type="button" data-footer-tab="{{ $key }}" id="footer-tab-{{ $key }}" aria-controls="footer-panel-{{ $key }}">@include('admin.partials.icon',['name'=>$icon,'size'=>19])<span><strong>{{ $title }}</strong><small>{{ $hint }}</small></span></button>@endforeach</nav><p class="fe-side-note">@include('admin.partials.icon',['name'=>'info','size'=>17]) Nội dung được áp dụng sau khi bạn lưu thay đổi.</p></aside>
        <div class="fe-panels">
            <section class="fe-panel" id="footer-panel-brand" data-footer-panel="brand" aria-labelledby="footer-tab-brand"><div class="fe-panel-heading"><span class="fe-panel-icon">@include('admin.partials.icon',['name'=>'panel-bottom','size'=>24])</span><div><h2>Thương hiệu & bản quyền</h2><p>Thông tin giới thiệu ở đầu footer và dòng bản quyền ở cuối trang.</p></div></div><div class="fe-fields">
                @include('admin.footer._field',['key'=>'brand_name','label'=>'Tên thương hiệu','max'=>120,'required'=>true])
                <label class="fe-field" for="footer-is_visible"><span>Hiển thị footer</span><select id="footer-is_visible" name="is_visible"><option value="1" @selected(old('is_visible',$settings->is_visible))>Hiển thị trên website</option><option value="0" @selected(!old('is_visible',$settings->is_visible))>Tạm ẩn footer</option></select><small>Có thể bật lại khi cần.</small></label>
                @include('admin.footer._field',['key'=>'company_name','label'=>'Tên công ty / đơn vị','max'=>200,'required'=>true,'wide'=>true])
                @include('admin.footer._field',['key'=>'description','label'=>'Giới thiệu ngắn','max'=>600,'type'=>'textarea','wide'=>true])
                @include('admin.footer._field',['key'=>'copyright','label'=>'Nội dung bản quyền','max'=>300,'required'=>true,'wide'=>true,'fieldHint'=>'Năm hiện tại tự động hiển thị phía trước.'])
            </div></section>
            @foreach(['support'=>'Liên kết hỗ trợ','editorial'=>'Liên kết góc điện ảnh'] as $group=>$label)
            <section class="fe-panel" id="footer-panel-{{ $group }}" data-footer-panel="{{ $group }}" aria-labelledby="footer-tab-{{ $group }}" data-footer-links="{{ $group }}"><div class="fe-panel-heading"><span class="fe-panel-icon">@include('admin.partials.icon',['name'=>$sections[$group][0],'size'=>24])</span><div><h2>{{ $label }}</h2><p>Thêm tối đa 8 liên kết. Dùng các nút lên / xuống để đổi thứ tự hiển thị.</p></div></div>
                @include('admin.footer._field',['key'=>$group.'_title','label'=>'Tiêu đề cột','max'=>80,'required'=>true])
                <div class="fe-links-heading"><strong>Danh sách liên kết</strong><span data-link-count></span></div>
                <div class="fe-link-list" data-link-list>@foreach(old($group.'_links',$settings->{$group.'_links'}) ?? [] as $index=>$link)@include('admin.footer._link-row',['group'=>$group,'index'=>$index,'link'=>$link])@endforeach</div>
                <p class="fe-links-empty" data-links-empty hidden>Chưa có liên kết. Thêm một trang để bắt đầu.</p>
                <input type="hidden" name="{{ $group }}_links[empty][label]" value=""><input type="hidden" name="{{ $group }}_links[empty][url]" value="">
                <div class="fe-add-row"><button type="button" class="fe-button" data-add-link>@include('admin.partials.icon',['name'=>'plus','size'=>17]) Thêm liên kết</button><small data-links-limit>Đường dẫn nội bộ bắt đầu bằng /, ví dụ /movies.</small></div>
                <template data-link-template>@include('admin.footer._link-row',['group'=>$group,'index'=>0,'link'=>[],'errors'=>new \Illuminate\Support\ViewErrorBag()])</template>
            </section>
            @endforeach
            <section class="fe-panel" id="footer-panel-contact" data-footer-panel="contact" aria-labelledby="footer-tab-contact"><div class="fe-panel-heading"><span class="fe-panel-icon">@include('admin.partials.icon',['name'=>'building-2','size'=>24])</span><div><h2>Thông tin liên hệ</h2><p>Để trống những thông tin bạn muốn ẩn trên footer.</p></div></div><div class="fe-fields">
                @include('admin.footer._field',['key'=>'contact_title','label'=>'Tiêu đề cột','max'=>80,'required'=>true,'wide'=>true])
                @include('admin.footer._field',['key'=>'address','label'=>'Địa chỉ','max'=>300,'type'=>'textarea','wide'=>true])
                @include('admin.footer._field',['key'=>'phone','label'=>'Hotline / số điện thoại','max'=>40,'type'=>'tel'])
                @include('admin.footer._field',['key'=>'email','label'=>'Email hỗ trợ','max'=>120,'type'=>'email'])
            </div></section>
            <section class="fe-panel" id="footer-panel-social" data-footer-panel="social" aria-labelledby="footer-tab-social"><div class="fe-panel-heading"><span class="fe-panel-icon">@include('admin.partials.icon',['name'=>'globe','size'=>24])</span><div><h2>Mạng xã hội</h2><p>Biểu tượng được hiển thị khi có đường dẫn. Để trống để ẩn kênh tương ứng.</p></div></div><div class="fe-fields">@foreach(['facebook'=>'Facebook','instagram'=>'Instagram','youtube'=>'YouTube'] as $network=>$label)@include('admin.footer._field',['key'=>$network.'_url','label'=>$label,'max'=>2048,'type'=>'url','wide'=>true,'placeholder'=>'https://...'])@endforeach</div></section>
        </div></div>
    </form>
</div>
<div class="fe-save-bar" data-footer-savebar><span data-footer-save-status aria-live="polite">Nội dung đã lưu trên website</span><div><button class="fe-button" type="button" data-footer-reset hidden>@include('admin.partials.icon',['name'=>'refresh-cw','size'=>16]) Hoàn tác</button><button class="fe-button primary" type="submit" form="footer-settings-form" data-footer-save>@include('admin.partials.icon',['name'=>'save','size'=>17]) Lưu thay đổi</button></div></div>
@endsection
@push('scripts')<script src="{{ asset('assets/js/admin/footer-editor.js') }}?v={{ filemtime(public_path('assets/js/admin/footer-editor.js')) }}" defer></script>@endpush
