@php
    $r                  = resolveHistCreatedBy($hist);
    $createdByName      = $r['createdByName'];
    $createdByRole      = $r['createdByRole'];
    $details            = $r['details'];
    $originalBookingUrl = route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => $details->original_booking_id]);
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
    <div class="log-badge split">Split Booking</div>
    <div class="log-content">
        <div class="sys-card" style="background:#f8f2ff;border-color:#cca8e8;margin-top:2px;">
            <div class="sys-card-icon" style="background:#7040c0;"><svg width="16" height="16" fill="none"
                    viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"></path>
                </svg></div>
            <div class="sys-card-body">
                <div class="sys-card-title" style="color:#6030b8;">Split from Original Booking</div>
                <div class="sys-card-rows">
                    <div class="sys-card-row">Original Booking ID — <a href="{{ $originalBookingUrl }}" target="_blank"
                            style="color:#2563eb;font-weight:700;">{{ $details->original_booking_id }}</a> by
                        <strong>{{ $createdByName }} ({{ $createdByRole }})</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>