@php
    $r             = resolveHistCreatedBy($hist);
    $createdByName = $r['createdByName'];
    $createdByRole = $r['createdByRole'];
    $details       = $r['details'];
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

    <div class="log-badge system" style="color:#0369a1;">Payment Type Update</div>

    <div class="log-content">
        <div class="sys-card" style="background:#f0f7ff;border-color:#93c0e4;">
            <div class="sys-card-icon" style="background:#0369a1;">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title" style="color:#0369a1;">Payment Type Changed</div>
                <div class="sys-card-rows">
                    <div class="sys-card-row">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                        From <b>{{ $details->from ?? '—' }}</b> to <b>{{ $details->to ?? '—' }}</b>
                    </div>
                    @if (!empty($details->history_id))
                        <div class="sys-card-row">
                            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5" />
                            </svg>
                            History ID: <b>{{ $details->history_id }}</b>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
