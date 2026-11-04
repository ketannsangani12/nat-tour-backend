@php
    $data          = resolveCardSaveData($hist);
    $createdByName = $data['createdByName'];
    $createdByRole = $data['createdByRole'];
    $source        = $data['source'];
    $cardLast4     = $data['cardLast4'];
@endphp

<div class="log-entry" data-type="system">
    <div class="log-time">{{ formatDateTime($hist->operation_at) }}
        <div class="log-user">
            <img class="log-avatar" src="{{ resolveHistAvatar($hist, $histPhotoMap) }}" alt="">
            <div class="log-user-info">
                <span class="log-user-name">{{ $createdByName }}</span>
                @if ($createdByRole)
                    <span class="log-cred">{{ $createdByRole }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="log-badge card-save">Card Save</div>

    <div class="log-content">
        <div class="sys-card card-save">
            <div class="sys-card-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title">Card Saved</div>
                <div class="sys-card-rows">
                    <div class="sys-card-row">
                        @if ($cardLast4)
                            <span class="card-chip">Card ···· {{ $cardLast4 }}</span>
                        @endif
                        @if ($source)
                            <span class="card-save-source">via {{ $source }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
