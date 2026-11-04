@php
    $r             = resolveHistCreatedBy($hist);
    $createdByName = $r['createdByName'];
    $createdByRole = $r['createdByRole'];
    // New rows store details as JSON ({message, created_by_with_name}) so the actor above
    // resolves correctly instead of falling back to "Customer" - pull the message back out of
    // it here. Older rows still have details as a plain string; optional() keeps that working.
    $errorMessage  = optional($r['details'])->message ?? $hist->details;
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

    <div class="log-badge system" style="color:#b91c1c;">{{ $hist->operation }}</div>

    <div class="log-content">
        <div class="note-box" style="background:#fff5f5;border-color:#fca5a5;">
            <div class="note-by" style="color:#b91c1c;">Booking error</div>
            <b>{{ $errorMessage }}</b>
        </div>
    </div>
</div>
