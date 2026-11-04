@php
    $r             = resolveHistCreatedBy($hist);
    $createdByName = $r['createdByName'];
    $createdByRole = $r['createdByRole'];
@endphp

<div class="log-entry" data-type="note">
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

    <div class="log-badge note" style="color:#1d6fa8;">Comments</div>

    <div class="log-content">
        <div class="note-box" style="background:#eef6ff;border-color:#93c5fd;">
            <div class="note-by" style="color:#1d6fa8;">Comment from the customer added / updated</div>
            {{ $hist->details }}
        </div>
    </div>
</div>
