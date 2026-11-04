@php
    $r             = resolveHistCreatedBy($hist);
    $createdByName = $r['createdByName'];
    $createdByRole = $r['createdByRole'];
    $opByUType = ($hist->operation_by_user_type ?? '');
    if ($opByUType == 'Tour Operator') {
        $opByUType = 'A tour operator staff';
    } elseif ($opByUType == 'Affiliate') {
        $opByUType = 'An affiliate';
    } else {
        $opByUType = 'An admin';
    }

    $noteAttachments = [];
    if (!empty($hist->attachments)) {
        $decoded         = is_string($hist->attachments) ? json_decode($hist->attachments, true) : $hist->attachments;
        $noteAttachments = is_array($decoded) ? array_values($decoded) : [];
    }

    // Detect tour guide assignment / removal notes
    ['isTourGuideNote' => $isTourGuideNote, 'tourGuideAction' => $tourGuideAction, 'tourGuideName' => $tourGuideName] =
        resolveTourGuideNoteData($hist);
@endphp

@if ($isTourGuideNote)
    {{-- ── Tour Guide Assigned / Removed entry ── --}}
    <style>
        .tg-card {
            border-radius: 10px;
            border: 1.5px solid #6ee7b7;
            background: #f0fdf4;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 14px;
            font-size: 13px;
        }
        .tg-card-icon {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: #0d9488;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .tg-card-body {
            flex: 1;
            min-width: 0;
        }
        .tg-card-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
            margin-bottom: 5px;
        }
        .tg-card-title .tg-action-assigned {
            color: #15803d;
        }
        .tg-card-title .tg-action-removed {
            color: #dc2626;
        }
        .tg-card-row {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            color: #374151;
        }
        .tg-guide-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: #fff;
            border: 1.5px solid #6ee7b7;
            border-radius: 20px;
            padding: 2px 10px 2px 6px;
            font-size: 12px;
            font-weight: 600;
            color: #065f46;
        }
        .tg-guide-pill svg {
            flex-shrink: 0;
        }
        .tg-card-title .tg-action-updated {
            color: #d97706;
        }
        .tg-card-icon--updated {
            background: #d97706 !important;
        }
        .tg-card-icon--removed {
            background: #dc2626 !important;
        }
        .log-badge.tour-guide-assigned {
            color: #0d9488;
        }
        .log-badge.tour-guide-removed {
            color: #dc2626;
        }
        .log-badge.tour-guide-updated {
            color: #d97706;
        }
    </style>

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

        @php
            $tgBadgeClass = match($tourGuideAction) { 'assigned' => 'tour-guide-assigned', 'updated' => 'tour-guide-updated', default => 'tour-guide-removed' };
            $tgBadgeLabel = match($tourGuideAction) { 'assigned' => 'Assigned', 'updated' => 'Updated', default => 'Removed' };
        @endphp
        <div class="log-badge {{ $tgBadgeClass }}">
            Tour Guide<br>{{ $tgBadgeLabel }}
        </div>

        <div class="log-content">
            <div class="tg-card">
                @php $tgIconClass = match($tourGuideAction) { 'updated' => 'tg-card-icon--updated', 'removed' => 'tg-card-icon--removed', default => '' }; @endphp
                <div class="tg-card-icon {{ $tgIconClass }}">
                    @if ($tourGuideAction === 'assigned')
                        {{-- Person with plus --}}
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="19" y1="8" x2="19" y2="14"/>
                            <line x1="16" y1="11" x2="22" y2="11"/>
                        </svg>
                    @elseif ($tourGuideAction === 'updated')
                        {{-- Pencil / edit --}}
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    @else
                        {{-- Person with minus --}}
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <line x1="16" y1="11" x2="22" y2="11"/>
                        </svg>
                    @endif
                </div>
                <div class="tg-card-body">
                    @php
                        $tgTitleClass = match($tourGuideAction) { 'assigned' => 'tg-action-assigned', 'updated' => 'tg-action-updated', default => 'tg-action-removed' };
                        $tgTitleLabel = match($tourGuideAction) { 'assigned' => 'Assigned', 'updated' => 'Updated', default => 'Removed' };
                    @endphp
                    <div class="tg-card-title">
                        Tour Guide
                        <span class="{{ $tgTitleClass }}">{{ $tgTitleLabel }}</span>
                    </div>
                    <div class="tg-card-row">
                        @if ($tourGuideAction === 'updated')
                            {{ $hist->details }}
                        @else
                            Guide:
                            @if ($tourGuideName)
                                <span class="tg-guide-pill">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0d9488" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                        <circle cx="12" cy="7" r="4"/>
                                    </svg>
                                    {{ $tourGuideName }}
                                </span>
                            @endif
                            has been {{ $tourGuideAction === 'assigned' ? 'assigned to' : 'removed from' }} this booking.
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    {{-- ── Regular note entry ── --}}
    <div class="log-entry" data-type="note" @if(!empty($noteAttachments)) data-has-attachment="true" @endif>
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

        <div class="log-badge note">Note</div>

        <div class="log-content">
            <div class="note-box">
                <div class="note-by">{{ $opByUType }} Added / updated note</div>
                {{ $hist->details }}

                @foreach ($noteAttachments as $att)
                    @php
                        $attUrl  = is_array($att) ? ($att['url'] ?? '') : (string) $att;
                        $urlPath = $attUrl ? (parse_url($attUrl, PHP_URL_PATH) ?: '') : '';
                        $attName = $urlPath ? (basename($urlPath) ?: 'attachment') : 'attachment';
                        $attExt  = strtolower(pathinfo($attName, PATHINFO_EXTENSION));
                        $isImage = in_array($attExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                    @endphp
                    @if ($attUrl)
                        <div class="note-attachment">
                            @if ($isImage)
                                <img class="note-att-thumb" src="{{ $attUrl }}" alt="{{ $attName }}">
                            @else
                                <span class="note-att-icon">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                        <line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>
                                    </svg>
                                </span>
                            @endif
                            <div class="note-att-info">
                                <span class="note-att-name">{{ $attName }}</span>
                                <a href="{{ $attUrl }}" target="_blank" class="note-att-btn note-att-btn--view">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    View
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
@endif
