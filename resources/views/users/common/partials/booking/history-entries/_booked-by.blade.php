@php
    $data          = resolveBookedByData($hist);
    $createdByName = $data['createdByName'];
    $createdByRole = $data['createdByRole'];
    $fromPerson    = $data['fromPerson'];
    $toPerson      = $data['toPerson'];
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

    <div class="log-badge system" style="color:#6d28d9;">Booked By</div>

    <div class="log-content">
        <div class="sys-card" style="background:#f5f3ff;border-color:#c4b5fd;">
            <div class="sys-card-icon" style="background:#6d28d9;">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title" style="color:#6d28d9;">Booked By Person Changed</div>
                <div class="sys-card-rows">
                    <div class="sys-card-row">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        From <b>{{ $fromPerson }}</b> to <b>{{ $toPerson }}</b>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
