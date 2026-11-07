@props(['status'])

@if ($status)
    <div data-inline-notification {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
