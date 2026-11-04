@php
    $d           = resolveCheckinData($hist);
    $createdByName = $d['createdByName'];
    $createdByRole = $d['createdByRole'];
    $details       = $d['details'];
    $isRedeemed    = $d['isRedeemed'];
    $status        = $d['status'];
    $who           = $d['who'];
    $package       = $d['package'];
    $cardVariant   = $d['cardVariant'];
    $badgeLabel    = $d['badgeLabel'];
    $cardTitle     = $d['cardTitle'];
    $statusClass   = $d['statusClass'];
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

    <div class="log-badge check-in">{{ $badgeLabel }}</div>

    <div class="log-content">
        <div class="sys-card {{ $cardVariant }}">
            <div class="sys-card-icon">
                @if ($isRedeemed)
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M16.5 6v.75a3.375 3.375 0 01-3.375 3.375h-1.5A3.375 3.375 0 018.25 6.75V6m0 0A2.25 2.25 0 0110.5 3.75h3A2.25 2.25 0 0115.75 6m-7.5 0H6a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 006 21h12a2.25 2.25 0 002.25-2.25V8.25A2.25 2.25 0 0018 6h-2.25" />
                    </svg>
                @else
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>
                @endif
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title">{{ $cardTitle }}</div>
                <div class="sys-card-rows">
                    <div class="sys-card-row">
                        @if ($isRedeemed)
                            Redeemed for
                        @else
                            Set check-in status to
                            @if ($status)
                                <span class="ci-badge {{ $statusClass }}">{{ $status }}</span>
                            @endif
                            for
                        @endif
                        @if ($who)
                            <strong>{{ $who }}</strong>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
