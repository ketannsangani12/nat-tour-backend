@php
    $splitEntries ??= [];
    $d                = resolveSplitOriginalBookingData($hist, $bookingM, $splitEntries);
    $createdByName    = $d['createdByName'];
    $createdByRole    = $d['createdByRole'];
    $details          = $d['details'];
    $splitEntries     = $d['splitEntries'];
    $splitsCreated    = $d['splitsCreated'];
    $totalGuestsMoved = $d['totalGuestsMoved'];
    $totalReallocated = $d['totalReallocated'];
    $splitCards       = $d['splitCards'];
    $remainingPax     = $d['remainingPax'];
    $firstSplitAt     = $d['firstSplitAt'];
@endphp

<div class="log-entry" data-type="refund">
    <div class="log-time">{{ formatDateTime($firstSplitAt) }}
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

    <div class="log-badge split">Split Booking Original</div>

    <div class="log-content">
        <div class="split-booking-entry">

            <!-- Purple header bar -->
            <div class="split-header">
                <div class="split-header-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="2.5" fill="#fff"></circle>
                        <line x1="12" y1="9.5" x2="12" y2="3.5" stroke="#fff" stroke-width="2" stroke-linecap="round"></line>
                        <polyline points="9.5,6 12,3.5 14.5,6" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
                        <line x1="14.2" y1="13.8" x2="19" y2="18.5" stroke="#fff" stroke-width="2" stroke-linecap="round"></line>
                        <polyline points="15.5,18.5 19,18.5 19,15" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
                        <line x1="9.8" y1="13.8" x2="5" y2="18.5" stroke="#fff" stroke-width="2" stroke-linecap="round"></line>
                        <polyline points="8.5,18.5 5,18.5 5,15" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"></polyline>
                    </svg>
                </div>
                <div class="split-header-title">Split — Original Booking</div>
            </div>

            <!-- Info body -->
            <div class="split-info-body">

                <!-- Left: Original booking details -->
                <div class="split-info-col">
                    <div class="split-orig-box">
                        <div class="split-section-label">Original Booking</div>
                        <div class="split-orig-name">{{ $bookingM->tour_operator ?? '' }}</div>
                        <div class="split-orig-meta">
                            {{ $bookingM->tour_package ?? '' }}
                            @if (!empty($bookingM->tour_date_time))
                                · {{ $bookingM->tour_date_time }}
                            @endif
                        </div>
                        <div class="split-orig-stats">
                            <div class="split-stat">
                                <span class="split-stat-label">Booking ID</span>
                                <span class="split-stat-value split-stat-value--id">#{{ bookingPrefix($bookingM->id) }}</span>
                            </div>
                            <!-- <div class="split-stat-divider"></div>
                            <div class="split-stat">
                                <span class="split-stat-label">Total</span>
                                <span class="split-stat-value">{{ moneyFormat($bookingM->booking_total ?? 0) }}</span>
                            </div> -->
                            <div class="split-stat-divider"></div>
                            <div class="split-stat">
                                <span class="split-stat-label">Original Pax</span>
                                <span class="split-stat-value">{{ $splitEntries[0]['hist']->tourists ?? 0 }} guests</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Split summary stats -->
                <div class="split-info-col split-info-col--right">
                    <div class="split-section-label">Split Summary</div>
                    <div class="split-summary-grid">
                        <div class="split-stat-tile">
                            <div class="split-stat-tile-value">{{ $splitsCreated }}</div>
                            <div class="split-stat-tile-label">Splits Created</div>
                        </div>
                        <div class="split-stat-tile">
                            <div class="split-stat-tile-value">{{ $totalGuestsMoved }}</div>
                            <div class="split-stat-tile-label">Guests Moved</div>
                        </div>
                        <div class="split-stat-tile split-stat-tile--green">
                            <div class="split-stat-tile-value split-stat-tile-value--green">{{ $remainingPax }}</div>
                            <div class="split-stat-tile-label split-stat-tile-label--green">Remaining Pax</div>
                        </div>
                        <div class="split-stat-tile">
                            <div class="split-stat-tile-value split-stat-tile-value--sm">{{ moneyFormat($totalReallocated) }}</div>
                            <div class="split-stat-tile-label">Reallocated</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Cards + connectors -->
            @if (!empty($splitCards))
                <div class="split-tree-section">
                    <svg viewBox="0 0 800 20" height="20" preserveAspectRatio="none" style="display:block;width:100%;margin:0;">
                        <defs>
                            <linearGradient id="lineGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#9060e0" stop-opacity="0.6"></stop>
                                <stop offset="100%" stop-color="#c4a0e8" stop-opacity="0.3"></stop>
                            </linearGradient>
                        </defs>
                        <line x1="400" y1="0" x2="400" y2="10" stroke="url(#lineGrad)" stroke-width="2" stroke-dasharray="4 2"></line>
                        <line x1="22" y1="10" x2="778" y2="10" stroke="url(#lineGrad)" stroke-width="2" stroke-dasharray="4 2"></line>
                    </svg>

                    <div class="split-cards-grid">
                        @foreach ($splitCards as $i => $card)
                            <div class="split-card-col">
                                <div class="split-card-ts-wrap">
                                    <div class="split-card-ts">{{ formatDateTime($card['operation_at']) }}</div>
                                </div>
                                <div class="split-card">
                                    <div class="split-card-header">
                                        <div class="split-card-num">
                                            <div class="split-num-badge">{{ $i + 1 }}</div>
                                            @if ($card['extended_booking_id'])
                                                <a class="split-card-id" href="#">#{{ bookingPrefix($card['extended_booking_id']) }}</a>
                                            @endif
                                        </div>
                                    </div>
                                    <hr class="split-card-divider">
                                    <div class="split-card-guests-row">
                                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="#7040c8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0z"></path></svg>
                                        <span><strong>{{ $card['guestsMoved'] }}</strong> guests moved</span>
                                    </div>
                                    <div class="split-card-amount">{{ moneyFormat($card['amount']) }}</div>
                                    <div class="split-card-amount-label">Reallocated</div>
                                    @if ($card['extended_booking_id'])
                                        <a class="split-view-btn"
                                            href="{{ route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => $card['extended_booking_id']]) }}"
                                            target="_blank">View Booking →</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div><!-- end .split-booking-entry -->
    </div>
</div>
