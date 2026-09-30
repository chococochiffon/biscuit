{{-- 最近編集したコンテンツ($recentContents は DashboardService::recentContents()) --}}
<div class="card h-100">
    <div class="card-header bg-transparent fw-semibold">{{ __('最近編集したコンテンツ') }}</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr class="small">
                    <th>{{ __('タイトル') }}</th>
                    <th>{{ __('状態') }}</th>
                    <th>{{ __('更新者') }}</th>
                    <th>{{ __('更新日時') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentContents as $content)
                    <tr>
                        <td>
                            <div class="small text-muted">{{ $content['type'] }}</div>
                            {{ $content['title'] }}
                        </td>
                        <td><span class="badge text-bg-{{ $content['status_color'] }}">{{ $content['status'] }}</span></td>
                        <td class="small">{{ $content['updated_by'] ?? '—' }}</td>
                        <td class="small text-nowrap">{{ $content['updated_at']?->format('Y/m/d H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ $content['edit_url'] }}" class="btn btn-sm btn-outline-secondary text-nowrap">{{ __('編集') }}</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="5">{{ __('まだコンテンツがありません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
