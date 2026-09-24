@php
    /**
     * 一覧テーブルの並び替え可能な見出し。クリックで並び順(sort=「field_asc」/「field_desc」)を切り替える。
     * 現在その項目で並んでいる場合は昇順/降順を反転し、そうでない場合は $defaultDirection で並べる。
     * 検索条件は引き継ぎ、ページは1ページ目に戻す。
     *
     * @var string $label 見出しの表示名
     * @var string $field 並び替えキーの項目部分(例: title, publication_start)
     * @var string $sort 現在の並び順のキー
     * @var string $defaultDirection 未選択の項目をクリックしたときの方向(asc/desc)
     */
    $defaultDirection ??= 'asc';
    $currentDirection = match ($sort) {
        $field.'_asc' => 'asc',
        $field.'_desc' => 'desc',
        default => null,
    };
    $nextDirection = $currentDirection ? ($currentDirection === 'asc' ? 'desc' : 'asc') : $defaultDirection;
@endphp

<th @if ($currentDirection) aria-sort="{{ $currentDirection === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a
        href="{{ request()->fullUrlWithQuery(['sort' => $field.'_'.$nextDirection, 'page' => null]) }}"
        class="text-reset text-decoration-none text-nowrap"
        data-role="sortable-th"
    >
        {{ $label }}
        @if ($currentDirection === 'asc')
            <i class="bi bi-caret-up-fill small"></i>
        @elseif ($currentDirection === 'desc')
            <i class="bi bi-caret-down-fill small"></i>
        @else
            <i class="bi bi-arrow-down-up small text-muted"></i>
        @endif
    </a>
</th>
