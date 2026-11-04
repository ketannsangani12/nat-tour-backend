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

    <div class="log-badge system" style="color:#6d28d9;">Restore</div>

    <div class="log-content">
        <div class="note-box" style="background:#f5f3ff;border-color:#c4b5fd;">
            <div class="note-by" style="color:#6d28d9;">Booking restored</div>
            Restored by <b>{{ $createdByName }}</b>
        </div>
    </div>
</div>
