{{-- インストーラーの段の並び(Stepper)。終えた段に印を付け、今の段を目立たせる --}}
<ol class="installer-stepper list-unstyled d-flex flex-wrap justify-content-center gap-2 mb-4 small" aria-label="{{ __('インストールの段') }}">
    @foreach (\App\Installer\InstallerStep::cases() as $index => $stepItem)
        @php($done = $installer->state()->isCompleted($stepItem))
        <li
            class="d-flex align-items-center gap-1 px-2 py-1 rounded {{ $stepItem === $step ? 'bg-primary text-white' : ($done ? 'text-success' : 'text-secondary') }}"
            @if ($stepItem === $step) aria-current="step" @endif
        >
            <span class="fw-semibold">{{ $index + 1 }}</span>
            @if ($done && $stepItem !== $step)
                <i class="bi bi-check-circle-fill"></i>
            @endif
            <span>{{ $stepItem->label() }}</span>
        </li>
    @endforeach
</ol>
