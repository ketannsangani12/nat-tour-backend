@php
    $d             = resolveRescheduledData($hist, $bookingM);
    $createdByName = $d['createdByName'];
    $createdByRole = $d['createdByRole'];
    $details       = $d['details'];
    $hasDetails    = $d['hasDetails'];
    $hasSlots      = $d['hasSlots'];
    $dateTimeFrom  = $d['dateTimeFrom'];
    $dateTimeTo    = $d['dateTimeTo'];
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

    <div class="log-badge rescheduled">Rescheduled</div>

    <div class="log-content">
        <div class="sys-card rescheduled">
            <div class="sys-card-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title">Tour Date Rescheduled</div>
                <div class="sys-card-rows">
                    @if ($hasDetails)
                        <div class="reschedule-row">
                            <div class="reschedule-date">
                                <span class="reschedule-date-label">From</span>
                                <span class="reschedule-date-value">{{ $dateTimeFrom }}</span>
                            </div>
                            <span class="reschedule-arrow">&#8594;</span>
                            <div class="reschedule-date">
                                <span class="reschedule-date-label">To</span>
                                <span class="reschedule-date-value">{{ $dateTimeTo }}</span>
                            </div>
                        </div>
                    @else
                        <div class="sys-card-row">Rescheduled the booking</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
