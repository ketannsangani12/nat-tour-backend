@php
    $d             = resolveCustomerInfoUpdateData($hist);
    $createdByName = $d['createdByName'];
    $createdByRole = $d['createdByRole'];
    $details       = $d['details'];
    $prevName      = $d['prevName'];
    $prevEmail     = $d['prevEmail'];
    $prevPhone     = $d['prevPhone'];
    $newName       = $d['newName']  ?? ($prevName  ? ($bookingM->customer         ?? null) : null);
    $newEmail      = $d['newEmail'] ?? ($prevEmail ? ($bookingM->cust_email        ?? null) : null);
    $newPhone      = $d['newPhone'] ?? ($prevPhone ? ($bookingM->cust_phone ?? null) : null);
@endphp

<style>
.ci-card {
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid #bfdbfe;
    font-size: 13px;
}
.ci-card-header {
    background: #eff6ff;
    padding: 10px 14px;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.ci-card-header-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: #1d4ed8;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ci-card-header-body {
    flex: 1;
    min-width: 0;
}
.ci-card-header-title {
    font-size: 13px;
    font-weight: 700;
    color: #1e3a8a;
    line-height: 1.3;
}
.ci-card-header-sub {
    font-size: 11px;
    color: #3b82f6;
    margin-top: 2px;
}
.ci-card-body {
    background: #eff6ff;
    padding: 12px 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.ci-field-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.ci-field-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
    width: 46px;
    flex-shrink: 0;
}
.ci-pill-old {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 12px;
    text-decoration: line-through;
    font-weight: 500;
}
.ci-pill-new {
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #15803d;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 12px;
    font-weight: 600;
}
.ci-arrow {
    color: #94a3b8;
    font-size: 13px;
    flex-shrink: 0;
}
</style>

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

    <div class="log-badge system" style="color:#1d4ed8;">Contact Update</div>

    <div class="log-content">
        <div class="ci-card">
            <div class="ci-card-header">
                <div class="ci-card-header-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </div>
                <div class="ci-card-header-body">
                    <div class="ci-card-header-title">Contact Information Updated</div>
                    <div class="ci-card-header-sub">
                        Updated by {{ $createdByName }}{{ $createdByRole ? " · $createdByRole" : '' }} · {{ formatDateTime($hist->operation_at) }}
                    </div>
                </div>
            </div>
            <div class="ci-card-body">
                @if ($prevName || $prevEmail || $prevPhone)
                    @if ($prevName)
                        <div class="ci-field-row">
                            <span class="ci-field-label">Name</span>
                            <span class="ci-pill-old">{{ $prevName }}</span>
                            <span class="ci-arrow">→</span>
                            <span class="ci-pill-new">{{ $newName ?: '—' }}</span>
                        </div>
                    @endif
                    @if ($prevEmail)
                        <div class="ci-field-row">
                            <span class="ci-field-label">Email</span>
                            <span class="ci-pill-old">{{ $prevEmail }}</span>
                            <span class="ci-arrow">→</span>
                            <span class="ci-pill-new">{{ $newEmail ?: '—' }}</span>
                        </div>
                    @endif
                    @if ($prevPhone)
                        <div class="ci-field-row">
                            <span class="ci-field-label">Phone</span>
                            <span class="ci-pill-old">{{ $prevPhone }}</span>
                            <span class="ci-arrow">→</span>
                            <span class="ci-pill-new">{{ $newPhone ?: '—' }}</span>
                        </div>
                    @endif
                @else
                    <div style="font-size:12px;color:#64748b;">Customer information was updated.</div>
                @endif
            </div>
        </div>
    </div>
</div>
