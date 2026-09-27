@props([
    'label',
    'labelCols' => 4,
])

{{--
    詳細画面の項目 1 行(項目名と値)。<dl class="row detail-list"> の中に並べる。
    labelCols: 項目名の列幅(12 分割のうち。値は残りの幅) / そのほかの属性(class・style)は値の要素に付ける
--}}
<dt class="col-{{ $labelCols }} text-muted fw-normal">{{ $label }}</dt>
<dd {{ $attributes->class(['col-'.(12 - $labelCols)]) }}>{{ $slot }}</dd>
