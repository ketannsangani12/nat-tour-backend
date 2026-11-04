@php
    // Defaults for standalone rendering (e.g. split-booking modal).
    // On the main booking page these variables are already in scope and the ??= is a no-op.
    $getOTAChannelName ??= '';
    $details           ??= null;
    $comboBookingsData ??= [];
    $trans             ??= null;
    $transP            ??= null;
    $transF            ??= null;
    $routePrefix       ??= (session('userType') == 'Affiliate' ? '' : 'tour-operator.');
    $invoiced          ??= null;

    if (!isset($isCashFees)) {
        try { $isCashFees = cashFees(); } catch (\Throwable $e) { $isCashFees = 0; }
    }
    if (!isset($otaPlatforms)) {
        try { $otaPlatforms = \App\Utils\BokunUtils::getAllOtaTitles(); } catch (\Throwable $e) { $otaPlatforms = []; }
    }

    ['grouped' => $grouped, 'splitAllEntries' => $splitAllEntries] =
        groupBookingHistory($history);

    $histPhotoMap = buildHistPhotoMap($history);

    // Count open (unpaid) custom charges for this booking.
    // Pending "Payment Link / Pay Later" history entries are only shown while
    // there are still open charges — once all are paid they are redundant.
    $openCustomChargeCount = \Illuminate\Support\Facades\DB::table('tour_booking_custom_charges')
        ->where('tour_booking_id', $bookingM->id ?? 0)
        ->where('status', 'Open')
        ->count();
    $hasOpenCustomCharges   = $openCustomChargeCount > 0;
    $shownPendingLinkCount  = 0; // track how many pending link entries we have rendered

    $isComboParentBooking = isComboParentBooking($comboBookingsData, $bookingM->id ?? 0);

    // Pre-group Email history entries by sent_email_id for grouped rendering
    $emailGroupsBySentId = collect($grouped)
        ->where('operation', 'Email')
        ->groupBy(function ($h) {
            $events = $h->emailEvents ?? collect();
            $sentId = $events->isNotEmpty() ? $events->first()->sent_email_id : null;
            return $sentId ?? 'unsent_' . $h->id;
        });
    $renderedEmailSentIds = [];
@endphp
<div class="history-log" id="historyLog">

    @foreach($grouped as $hist)
        {{-- Only show as many pending "Payment Link / Pay Later" entries as there are open custom charges --}}
        @if ($hist->charge_type === 'custom_charge' && $hist->payment_method === 'Payment Link' && (int) $hist->is_processed === 0)
            @if (!$hasOpenCustomCharges || $shownPendingLinkCount >= $openCustomChargeCount)
                @continue
            @endif
            @php $shownPendingLinkCount++; @endphp
        @endif
        @if ($hist->operation == 'Note')
            @include('users.common.partials.booking.history-entries._note', ['hist' => $hist])
        @elseif ($hist->operation == 'Error' || $hist->operation == 'Card Error')
            @include('users.common.partials.booking.history-entries._error', ['hist' => $hist])
        @elseif ($hist->operation == 'Reference Booking')
            @include('users.common.partials.booking.history-entries._reference-booking', ['hist' => $hist])
        @elseif ($hist->operation == 'Comments')
            @include('users.common.partials.booking.history-entries._comments', ['hist' => $hist])
        @elseif ($hist->operation == 'Restore')
            @include('users.common.partials.booking.history-entries._restore', ['hist' => $hist])
        @elseif ($hist->operation == 'Payment Type Update')
            @include('users.common.partials.booking.history-entries._payment-type-update', ['hist' => $hist])
        @elseif ($hist->operation == 'Split Booking Original')
            @include('users.common.partials.booking.history-entries._split-original-booking', ['hist' => $hist, 'splitEntries' => $splitAllEntries])
        @elseif ($hist->operation == 'Split Booking')
            @include('users.common.partials.booking.history-entries._split-booking', ['hist' => $hist])
        @elseif ($hist->operation == 'Switch Tour Package')
            @include('users.common.partials.booking.history-entries._switch-tour-package', ['hist' => $hist, 'pairedPayment' => $hist->paired ?? null])
        @elseif ($hist->operation == 'Pickup Hotel')
            @include('users.common.partials.booking.history-entries._pickup-hotel', ['hist' => $hist])
        @elseif ($hist->operation == 'Email')
            @php
                $histEmailEvents = $hist->emailEvents ?? collect();
                $histSentId = $histEmailEvents->isNotEmpty() ? $histEmailEvents->first()->sent_email_id : ('unsent_' . $hist->id);
            @endphp
            @if (!in_array($histSentId, $renderedEmailSentIds))
                @php $renderedEmailSentIds[] = $histSentId; @endphp
                @include('users.common.partials.booking.history-entries._email', [
                    'hist'       => $hist,
                    'emailGroup' => $emailGroupsBySentId->get($histSentId) ?? collect(),
                ])
            @endif
        @elseif ($hist->operation == 'Payment')
            @include('users.common.partials.booking.history-entries._payment', ['hist' => $hist])
        @elseif ($hist->operation == 'Refund' && $hist->payment_method != 'Permitfees Refund')
            @include('users.common.partials.booking.history-entries._refund', ['hist' => $hist])
        @elseif ($hist->operation == 'Refund' && $hist->payment_method == 'Permitfees Refund')
            @include('users.common.partials.booking.history-entries._permit-fees-refund', ['hist' => $hist])
        @elseif ($hist->operation == 'Added')
            @include('users.common.partials.booking.history-entries._original', ['hist' => $hist])
        @elseif ($hist->operation == 'Update Pax')
            @include('users.common.partials.booking.history-entries._update_pax', ['hist' => $hist, 'pairedPayment' => $hist->paired ?? null])
        @elseif ($hist->operation == 'Cancelled')
            @include('users.common.partials.booking.history-entries._cancel', ['hist' => $hist])
        @elseif ($hist->operation == 'Rescheduled')
            @include('users.common.partials.booking.history-entries._rescheduled', ['hist' => $hist])
        @elseif($hist->operation == 'Check-in')
            @include('users.common.partials.booking.history-entries._checkin', ['hist' => $hist])
        @elseif ($hist->operation == 'Waiver')
            @include('users.common.partials.booking.history-entries._waiver', ['hist' => $hist])
        @elseif ($hist->operation == 'Customer Info Update')
            @include('users.common.partials.booking.history-entries._customer-info-update', ['hist' => $hist])
        @elseif ($hist->operation == 'Tip')
            @include('users.common.partials.booking.history-entries._tip', ['hist' => $hist])
        @elseif ($hist->operation == 'Card Save')
            @include('users.common.partials.booking.history-entries._card-save', ['hist' => $hist])
        @elseif ($hist->operation == 'Booked By')
            @include('users.common.partials.booking.history-entries._booked-by', ['hist' => $hist])
        @endif
    @endforeach

    <!-- BOOKED -->
    <div class="log-entry" data-type="system">
        <div class="log-time">{{ formatDateTime($bookingM->created_at) }}
            <div class="log-user">
                @php $addedHist = collect($grouped)->first(fn($h) => $h->operation == 'Added'); @endphp
                <img class="log-avatar" src="{{ $addedHist ? resolveHistAvatar($addedHist, $histPhotoMap) : asset('images/no-photo.png') }}" alt="">
                @php
                    $addedDetails = $addedHist ? (is_string($addedHist->details) ? json_decode($addedHist->details) : $addedHist->details) : null;
                    ['name' => $createdByName, 'role' => $createdByRole] = parseCreatedByWithName(
                        ($addedDetails && isset($addedDetails->created_by_with_name))
                            ? (string) $addedDetails->created_by_with_name
                            : ($details->created_by_with_name ?? null)
                    );
                @endphp
                <div class="log-user-info">
                    <span class="log-user-name">{{ $createdByName }}</span>
                    <span class="log-cred">{{ ($createdByRole != '') ? $createdByRole : ($addedHist?->operation_by_user_type ?? '') }}</span>
                </div>
            </div>
        </div>
        <div class="log-badge booked">
            @php
                $prefix = (count($comboBookingsData) > 1) ? 'Combo ' : '';
                $status = !empty($trans) ? 'Booked' : (!empty($transF) ? 'Failed' : 'Pending');
            @endphp
            {{ $prefix . $status }}
        </div>
        <div class="log-content">
            @php
                $bookedSource = !empty($transP) ? match (true) {
                    $transP->request_from == \App\Constants\Constants::FRONTEND => 'Frontend',
                    $transP->request_from == strtoupper($getOTAChannelName) => $getOTAChannelName,
                    $transP->request_from == \App\Constants\Constants::GETYOURGUIDE => 'GetYourGuide',
                    $transP->request_from == \App\Constants\Constants::TRIPADVISOR => 'TripAdvisor',
                    default => 'Backend',
                } : (!empty($transF) ? 'Failed' : 'Pending');
                $childColors = ['purple', 'blue', 'orange', 'pink'];
            @endphp

            @if (count($comboBookingsData) > 1 && (session('userType') == 'Tour Operator' || session('userType') == 'Admin'))
                {{-- ── Combo booking card ── --}}
                <div class="combo-card">
                    <div class="combo-card-header">
                        <div class="combo-icon">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <path d="M5.5 10.5a3.5 3.5 0 1 0 0-5M10.5 5.5a3.5 3.5 0 1 0 0 5" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                                <path d="M7 8h2" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div>
                            <div class="combo-card-title">
                                Booked from {{ $bookedSource }} — Combo Package
                                @if (!empty($bookingM->order_id))
                                    · Order #{{ bookingPrefix($bookingM->order_id) }}
                                @endif
                            </div>
                            <div class="combo-card-user">
                                {{ $createdByName }}{{ $createdByRole ? ' (' . $createdByRole . ')' : '' }}
                            </div>
                        </div>
                    </div>

                    @php
                        $parentBooking   = collect($comboBookingsData)->firstWhere('is_parent', true);
                        $childBookings   = collect($comboBookingsData)->where('is_parent', false)->values();
                        $parentBookingId = $parentBooking['id'] ?? null;
                        $childIds        = $childBookings->pluck('id');
                        $childCount      = $childBookings->count();
                    @endphp
                    <div class="combo-exp-rows">
                        {{-- Parent: first booking in the order --}}
                        @if ($parentBooking)
                        <div class="combo-exp-row" style="background:#f0fdf4;border-color:#86efac;">
                            <span class="exp-dot green"></span>
                            <a class="ticket-num green" href="{{ route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => $parentBooking['id']]) }}" target="_blank">#{{ bookingPrefix($parentBooking['id']) }}</a>
                            <span class="combo-row-name">{{ $parentBooking['packageName'] }}</span>
                            <span class="combo-parent-badge">PARENT</span>
                            @if ($parentBooking['is_current'])
                                <span class="combo-child-badge" style="background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;">current</span>
                            @endif
                            @if (!empty($parentBooking['tour_date_time']))
                                <span class="combo-row-meta">{{ $parentBooking['tour_date_time'] }}</span>
                            @endif
                            <span class="combo-row-meta">· {{ $parentBooking['tourists'] }} guests</span>
                        </div>
                        @endif

                        {{-- Children --}}
                        @foreach($childBookings as $ci => $comboBooking)
                            <div class="combo-connector" @if($ci > 0) style="padding-left:38px;" @endif>
                                <div class="combo-connector-line"></div>
                            </div>
                            @php $color = $childColors[$ci % count($childColors)]; @endphp
                            <div class="combo-exp-row" style="margin-left:16px;">
                                <span class="exp-dot {{ $color }}"></span>
                                <a class="ticket-num {{ $color }}" href="{{ route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => $comboBooking['id']]) }}" target="_blank">#{{ bookingPrefix($comboBooking['id']) }}</a>
                                <span class="combo-row-name">{{ $comboBooking['packageName'] }}</span>
                                <span class="combo-child-badge">child</span>
                                @if ($comboBooking['is_current'])
                                    <span class="combo-child-badge" style="background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;">current</span>
                                @endif
                                @if (!empty($comboBooking['tour_date_time']))
                                    <span class="combo-row-meta">{{ $comboBooking['tour_date_time'] }}</span>
                                @endif
                                <span class="combo-row-meta">· {{ $comboBooking['tourists'] }} guests</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="combo-footer">
                        @if ($parentBookingId)
                            #{{ bookingPrefix($parentBookingId) }} is the parent booking —
                            @foreach($childBookings as $ci => $comboBooking)
                                #{{ bookingPrefix($comboBooking['id']) }}{{ !$loop->last ? ' and' : '' }}
                            @endforeach
                            {{ $childCount === 1 ? 'is a' : 'are' }} linked child {{ Str::plural('booking', $childCount) }}. All history, payments and notes are connected.
                        @endif
                    </div>
                </div>

            @else
                {{-- ── Standard single booking card ── --}}
                <div class="sys-card booked">
                    <div class="sys-card-icon">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" />
                        </svg>
                    </div>
                    <div class="sys-card-body">
                        <div class="sys-card-title"><b>Booked From {{ $bookedSource }}</b></div>
                        <div class="sys-card-rows">
                            <div class="sys-card-row">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0H3" />
                                </svg>
                                Source:
                                @if (!empty($transP))
                                    @php
                                        $requestFrom = $transP->request_from;
                                        $sourceDetail = match (true) {
                                            $requestFrom == \App\Constants\Constants::FRONTEND => 'Customer Frontend',
                                            $requestFrom == strtoupper($getOTAChannelName) => $getOTAChannelName,
                                            $requestFrom == \App\Constants\Constants::GETYOURGUIDE => 'GetYourGuide',
                                            $requestFrom == \App\Constants\Constants::TRIPADVISOR => 'TripAdvisor',
                                            default => $bookingM->created_by_user_type . ' Backend',
                                        };
                                    @endphp
                                    {{ $sourceDetail }}
                                @elseif (!empty($transF))
                                    Failed
                                @else
                                    Pending
                                @endif
                            </div>
                            <div class="sys-card-row">
                                <span class="sys-pax-badge">
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Booking
                                    @if (!empty($transP))
                                        Confirmed
                                    @elseif (!empty($transF))
                                        Failed
                                    @else
                                        Pending
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

</div>
