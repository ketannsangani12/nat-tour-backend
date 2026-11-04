@php
    $d             = resolveOriginalData($hist);
    $createdByName = $d['createdByName'];
    $createdByRole = $d['createdByRole'];
    $details       = $d['details'];
    $newTourists   = $d['newTourists'];
    $addedGrouped  = $d['addedGrouped'];
@endphp

<div class="log-entry" data-type="system">
    <div class="log-time">{{ formatDateTime($hist->operation_at) }}
        <div class="log-user">
            <img class="log-avatar" src="{{ resolveHistAvatar($hist, $histPhotoMap) }}" alt="">
            <div class="log-user-info">
                <span class="log-user-name">{{ $createdByName }}</span>
                <span class="log-cred">{{ ($createdByRole != '') ? $createdByRole : $hist->operation_by_user_type }}</span>
            </div>
        </div>
    </div>

    <div class="log-badge original">Original</div>

    <div class="log-content">
        @if (!empty($comboBookingsData) && count($comboBookingsData) > 1 && (session('userType') == 'Tour Operator' || session('userType') == 'Admin'))
            @php
                $childColors  = ['#7c3aed', '#2563eb', '#d97706', '#db2777'];
                $parentEntry  = collect($comboBookingsData)->firstWhere('is_parent', true);
                $childEntries = collect($comboBookingsData)->where('is_parent', false)->values();
                $childCount   = $childEntries->count();
            @endphp
            <div class="sys-card original">
                <div class="sys-card-icon">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <path d="M9 3l1.5 4.5H15l-3.75 2.7 1.43 4.3L9 11.8 5.32 14.5l1.43-4.3L3 7.5h4.5L9 3z" fill="#fff"/>
                    </svg>
                </div>
                <div class="sys-card-body">
                    <div class="sys-card-title">
                        Combo Booking Created — 1 parent + {{ $childCount }} child {{ Str::plural('booking', $childCount) }} · {{ $newTourists }} {{ Str::plural('tourist', $newTourists) }}
                        @if (!empty($bookingM->order_id))
                            · Order #{{ bookingPrefix($bookingM->order_id) }}
                        @endif
                    </div>
                    <div class="sys-card-rows">
                        {{-- Parent row --}}
                        @if ($parentEntry)
                            <div class="sys-card-row">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                    <rect x="2" y="2" width="10" height="10" rx="2" stroke="#059669" stroke-width="1.3"/>
                                    <path d="M5 7h4M7 5v4" stroke="#059669" stroke-width="1.3" stroke-linecap="round"/>
                                </svg>
                                <a class="ticket-num green" href="{{ route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => $parentEntry['id']]) }}" target="_blank">#{{ bookingPrefix($parentEntry['id']) }}</a>
                                — {{ $parentEntry['packageName'] }}
                                <span class="combo-parent-badge" style="margin-left:4px;">PARENT</span>
                                @if ($parentEntry['is_current'])
                                    <span class="combo-child-badge" style="background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;margin-left:3px;">current</span>
                                @endif
                            </div>
                        @endif

                        {{-- Child rows --}}
                        @foreach($childEntries as $ci => $comboBooking)
                            @php $hex = $childColors[$ci % count($childColors)]; @endphp
                            <div class="sys-card-row" style="padding-left:14px;">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                    <rect x="2" y="2" width="10" height="10" rx="2" stroke="{{ $hex }}" stroke-width="1.3"/>
                                    <path d="M5 7h4M7 5v4" stroke="{{ $hex }}" stroke-width="1.3" stroke-linecap="round"/>
                                </svg>
                                <a class="ticket-num" style="color:{{ $hex }};" href="{{ route(prefixedRouteName($routePrefix . 'booking.show'), ['booking' => $comboBooking['id']]) }}" target="_blank">#{{ bookingPrefix($comboBooking['id']) }}</a>
                                — {{ $comboBooking['packageName'] }}
                                <span style="font-size:9px;color:#94a3b8;">child</span>
                                @if ($comboBooking['is_current'])
                                    <span class="combo-child-badge" style="background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;margin-left:3px;">current</span>
                                @endif
                            </div>
                        @endforeach

                        {{-- Pax count --}}
                        @if ($hist->tourists)
                            <div class="sys-card-row">
                                New Pax Count:
                                    {{ $hist->tourists }}
                                
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        @else
            {{-- Standard single booking card --}}
            <div class="sys-card original">
                <div class="sys-card-icon">
                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                    </svg>
                </div>
                <div class="sys-card-body">
                    <div class="sys-card-title">
                        Booking Created &mdash; {{ $newTourists }} tourist(s) added
                    </div>
                    <div class="sys-card-rows">
                        @foreach ($addedGrouped as $rateName => $count)
                            <div class="sys-card-row">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0" />
                                </svg>
                                {{ $rateName }} x {{ $count }}
                            </div>
                        @endforeach

                        @if ($hist->tourists)
                            <div class="sys-card-row">
                                <span class="sys-pax-badge">
                                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    New Pax Count: {{ $hist->tourists }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
