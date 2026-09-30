@props([
    'label',
    'url',
    'counts',
    'items',
    'size' => 'fs-3',
])

{{-- ダッシュボードの件数。合計(counts['total'])を一覧へのリンクにし、内訳(items は キー => 項目名、件数は counts[キー])を並べる --}}
<div {{ $attributes->class('d-flex flex-wrap align-items-center gap-4') }}>
    <a href="{{ $url }}" class="text-decoration-none text-reset">
        <div class="small text-muted">{{ $label }}</div>
        <div class="{{ $size }} fw-semibold">{{ number_format($counts['total']) }}</div>
    </a>
    <dl class="d-flex flex-wrap gap-4 mb-0">
        @foreach ($items as $key => $itemLabel)
            <div data-count="{{ $key }}">
                <dt class="small text-muted fw-normal">{{ $itemLabel }}</dt>
                <dd class="fs-5 mb-0">{{ number_format($counts[$key]) }}</dd>
            </div>
        @endforeach
    </dl>
</div>
