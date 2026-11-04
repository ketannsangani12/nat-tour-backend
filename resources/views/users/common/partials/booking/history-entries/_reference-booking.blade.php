@php
    $r             = resolveHistCreatedBy($hist);
    $createdByName = $r['createdByName'];
    $createdByRole = $r['createdByRole'];
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

    <div class="log-badge system" style="color:#0d7a4e;">Reference Booking</div>

    <div class="log-content">
        <div class="note-box" style="background:#edfaf3;border-color:#6ee7b7;">
            <div class="note-by" style="color:#0d7a4e;">Reference booking number added / updated</div>
            <b>{{ $hist->details }}</b>
        </div>
    </div>
</div>
