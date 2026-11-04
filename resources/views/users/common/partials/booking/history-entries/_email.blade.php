@php
    $ep = resolveEmailPipelineData($hist, $emailGroup);
    extract($ep);
@endphp

@once
<style>
    .email-card {
        border: 1px solid #a8c4e8; border-radius: 8px; background: #f0f6ff;
        padding: 10px 14px; margin-top: 4px;
        transition: background .15s, border-color .15s;
        display: flex; align-items: center; gap: 12px;
    }
    .email-card-icon {
        width: 36px; height: 36px; border-radius: 8px; background: #2d5ecc;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .email-card.bounced-card .email-card-icon { background: #dc2626; }
    .email-card-body { flex: 1; min-width: 0; }
    .email-card-type { font-size: 13px; font-weight: 700; color: #1e48a8; margin-bottom: 2px; }
    .email-card-sub  { font-size: 12px; color: #475569; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .email-card-meta { font-size: 11px; color: #64748b; display: flex; gap: 8px; align-items: center; margin-top: 4px; }
    .email-pipeline-mini {
        display: flex; align-items: center; margin-top: 10px; padding-top: 10px;
        border-top: 1px solid #a8c4e8;
    }
    .epm-step { display: flex; flex-direction: column; align-items: center; gap: 4px; }
    .epm-node {
        width: 30px; height: 30px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
        animation: epmPop 0.4s cubic-bezier(.34,1.56,.64,1) both;
    }
    .epm-node.sent      { background: #2d5ecc; }
    .epm-node.delivered { background: #1e8c42; }
    .epm-node.view      { background: #d08020; cursor: pointer; transition: transform .15s;
                          animation: epmPop 0.4s cubic-bezier(.34,1.56,.64,1) both, epmViewGlow 1.8s ease 2.4s 3; }
    .epm-node.view:hover { transform: scale(1.12); }
    .epm-node.bounced   { background: #dc2626; }
    .epm-node.inactive  { background: #d1d5db; animation: none !important; }
    .epm-label { font-size: 10px; font-weight: 700; white-space: nowrap; }
    .epm-label.sent      { color: #2d5ecc; }
    .epm-label.delivered { color: #1e8c42; }
    .epm-label.view      { color: #c07010; }
    .epm-label.bounced   { color: #b91c1c; }
    .epm-label.inactive  { color: #9ca3af; }
    .epm-time { font-size: 9px; color: #94a3b8; white-space: nowrap; }
    .epm-connector {
        flex: 1; display: flex; align-items: center; justify-content: center;
        gap: 3px; margin-bottom: 14px;
        transform-origin: left center;
        animation: epmDotsIn 0.3s ease both;
    }
    .epm-dot { display: flex; align-items: center; justify-content: center; color: #1e8c42; }
    .epm-dot.pending { color: #d1d5db; }
    .email-pipeline-mini > .epm-step:nth-child(1) .epm-node { animation-delay: 0.15s; }
    .email-pipeline-mini > .epm-connector:nth-child(2) { animation-delay: 0.55s; }
    .email-pipeline-mini > .epm-step:nth-child(3) .epm-node { animation-delay: 0.9s; }
    .email-pipeline-mini > .epm-connector:nth-child(4) { animation-delay: 1.3s; }
    .email-pipeline-mini > .epm-step:nth-child(5) .epm-node { animation-delay: 1.65s; }
    @keyframes epmPop {
        0%   { transform: scale(0); opacity: 0; }
        70%  { transform: scale(1.15); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    @keyframes epmDotsIn {
        0%   { opacity: 0; transform: scaleX(0); }
        100% { opacity: 1; transform: scaleX(1); }
    }
    @keyframes epmViewGlow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245,158,11,0); }
        50%       { box-shadow: 0 0 0 6px rgba(245,158,11,0.35); }
    }
    @keyframes emailFly {
        0%   { left: 0;              transform: scaleX(1);  }
        42%  { left: calc(100% - 14px); transform: scaleX(1);  }
        50%  { left: calc(100% - 14px); transform: scaleX(-1); }
        92%  { left: 0;              transform: scaleX(-1); }
        100% { left: 0;              transform: scaleX(1);  }
    }
    .bounce-envelope {
        position: absolute; top: 50%; margin-top: -7px; left: 0;
        animation: emailFly 5s cubic-bezier(.45,0,.55,1) infinite;
        transform-origin: center;
    }
    /* Legacy email status chips (old bookings without event rows) */
    .esp-sent      { background:#d4e8fc; color:#2d5ecc; border:1px solid #90b8e8; }
    .esp-delivered { background:#d0f5dc; color:#1e8c42; border:1px solid #90d8a8; }
    .esp-viewed    { background:#fef3c7; color:#d08020; border:1px solid #fcd34d; }
    .esp-clicked   { background:#ede9fe; color:#7c3aed; border:1px solid #c4b5fd; }
    .esp-spam      { background:#fef3c7; color:#b45309; border:1px solid #fbbf24; }
    .esp-bounced   { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; }
    .email-status-chip { display:inline-block; padding:1px 7px; border-radius:10px; font-size:10px; font-weight:700; margin-left:6px; vertical-align:middle; }
    /* Email preview modal */
    .email-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:9999; align-items:flex-start; justify-content:center; padding:32px 16px; overflow-y:auto; }
    .email-modal-overlay.open { display:flex; }
    .email-modal { background:#fff; border-radius:12px; width:100%; max-width:640px; box-shadow:0 20px 60px rgba(0,0,0,.25); overflow:hidden; position:relative; }
    .email-modal-topbar { display:flex; align-items:center; justify-content:space-between; padding:14px 20px; background:#1e293b; color:#fff; }
    .email-modal-title { font-size:14px; font-weight:700; }
    .email-modal-close { width:28px; height:28px; border-radius:6px; border:none; background:rgba(255,255,255,.15); color:#fff; font-size:16px; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .email-modal-close:hover { background:rgba(255,255,255,.25); }
    .email-modal-meta { padding:10px 20px; background:#f8fafc; border-bottom:1px solid #e5e7eb; font-size:12px; color:#64748b; display:flex; gap:16px; flex-wrap:wrap; align-items:center; }
    .email-modal-meta b { color:#334155; }
    .email-modal-body { padding:0; background:#f0f2f5; max-height:72vh; overflow:hidden; }
    #emailModalFrame { height:72vh; }
    .email-status-pill { padding:1px 6px; border-radius:10px; font-size:10px; font-weight:700; }
    .email-status-pill.delivered { background:#d0f5dc; color:#1a8040; border:1px solid #90d8a8; }
    .email-status-pill.sent { background:#d4e8fc; color:#2d5ecc; border:1px solid #90b8e8; }
    .epm-node.clickable { cursor:pointer; }
</style>
@endonce

<div class="log-entry" data-type="email">
    <div class="log-time">{{ formatDateTime($senderHistEntry->operation_at) }}
        <div class="log-user">
            <img class="log-avatar" src="{{ resolveHistAvatar($senderHistEntry, $histPhotoMap) }}" alt="">
            <div class="log-user-info">
                @if ($isSystemEvent)
                    <span class="log-user-name" style="color:#64748b;font-weight:600;">System</span>
                    <span class="log-cred">Automated</span>
                @else
                    <span class="log-user-name">{{ $createdByName }}</span>
                    @if ($createdByRole)
                        <span class="log-cred">{{ $createdByRole }}</span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    <div class="log-badge email">Email</div>

    <div class="log-content">

        @if (!$hasEvents)
            {{-- Old booking: no event rows — display legacy details + status chip --}}
            <div class="note-box" style="background:#f0f6ff;border-color:#a8c4e8;">
                <div class="note-by" style="color:#2d5ecc;">
                    @if (!$isSystemEvent)
                        {{ $createdByName }}@if($createdByRole) &middot; {{ $createdByRole }}@endif
                    @else
                        System
                    @endif
                </div>
                {{ $emailDetails }}
                <span class="email-status-chip esp-{{ $statusKey }}">{{ $badgeLabel }}</span>
            </div>

        @elseif ($isBounced)
            {{-- Bounced email card --}}
            <div class="email-card bounced-card" style="flex-direction:column;align-items:stretch;cursor:default;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="email-card-icon">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                    </div>
                    <div class="email-card-body">
                        <div class="email-card-type" style="color:#b91c1c;">{{ $subject ?? '' }} — Bounced</div>
                        @if ($senderName)
                            <div class="email-card-sub">{{ $senderName }}</div>
                        @endif
                        @if ($recipientEmail)
                            <div class="email-card-meta"><span>To: {{ $recipientEmail }}</span></div>
                        @endif
                    </div>
                </div>

                <div class="email-pipeline-mini" style="justify-content:center;gap:0;">
                    <div class="epm-step">
                        <div class="epm-node sent">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
                        </div>
                        <div class="epm-label sent">Sent</div>
                        @if ($fmtSentAt)
                            <div class="epm-time">{{ $fmtSentAt }}</div>
                        @endif
                    </div>

                    <div style="position:relative;flex:1;max-width:260px;min-width:120px;display:flex;align-items:center;margin-bottom:14px;">
                        <svg width="100%" height="12" style="display:block;" preserveAspectRatio="none">
                            <line x1="0" y1="6" x2="100%" y2="6" stroke="#fca5a5" stroke-width="1.5" stroke-dasharray="4 3"/>
                        </svg>
                        <div class="bounce-envelope">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#dc2626" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                        </div>
                    </div>

                    <div class="epm-step">
                        <div class="epm-node bounced">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div class="epm-label bounced">Bounced</div>
                        @if ($fmtBounceAt)
                            <div class="epm-time">{{ $fmtBounceAt }}</div>
                        @endif
                    </div>
                </div>

                @if ($bounceReason)
                    <div style="margin-top:8px;padding:8px 12px;background:#fee2e2;border:1px solid #fca5a5;border-radius:6px;font-size:11px;color:#b91c1c;display:flex;align-items:flex-start;gap:6px;">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        <span><strong>Bounce reason:</strong> {{ $bounceReason }}</span>
                    </div>
                @endif
            </div>

        @else
            {{-- New email card: Sent → Delivered → View pipeline --}}
            <div class="email-card" style="flex-direction:column;align-items:stretch;cursor:default;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="email-card-icon">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                    </div>
                    <div class="email-card-body">
                        <div class="email-card-type">{{ $subject }}</div>
                        @if ($senderName)
                            <div class="email-card-sub">{{ $senderName }}</div>
                        @endif
                        @if ($recipientEmail)
                            <div class="email-card-meta"><span>To: {{ $recipientEmail }}</span></div>
                        @endif
                    </div>
                </div>

                <div class="email-pipeline-mini">
                    {{-- Sent --}}
                    <div class="epm-step">
                        <div class="epm-node sent">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.125A59.769 59.769 0 0121.485 12 59.768 59.768 0 013.27 20.875L5.999 12zm0 0h7.5"/></svg>
                        </div>
                        <div class="epm-label sent">Sent</div>
                        @if ($fmtSentAt)
                            <div class="epm-time">{{ $fmtSentAt }}</div>
                        @endif
                    </div>

                    {{-- Connector 1: active when delivered --}}
                    <div class="epm-connector">
                        <div class="epm-dot{{ $isDelivered ? '' : ' pending' }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M7 12l3.5 3.5 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>

                    {{-- Delivered --}}
                    <div class="epm-step">
                        <div class="epm-node {{ $isDelivered ? 'delivered' : 'inactive' }}">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <div class="epm-label {{ $isDelivered ? 'delivered' : 'inactive' }}">Delivered</div>
                        @if ($isDelivered && $fmtDeliveredAt)
                            <div class="epm-time">{{ $fmtDeliveredAt }}</div>
                        @endif
                    </div>

                    {{-- Connector 2: active when opened --}}
                    <div class="epm-connector">
                        <div class="epm-dot{{ $isOpened ? '' : ' pending' }}">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M7 12l3.5 3.5 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                    </div>

                    {{-- View --}}
                    <div class="epm-step">
                        <div class="epm-node view {{ $emailContent ? ' clickable' : '' }}"
                            @if ($emailContent) onclick="openEmailModal('{{ $hist->id }}')" @endif
                            title="{{ $openCount }} {{ $openCount === 1 ? 'open' : 'opens' }}">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                        <div class="epm-label {{ $isOpened ? 'view' : 'inactive' }}">View</div>
                        <div class="epm-time">{{ $openCount }} {{ $openCount === 1 ? 'open' : 'opens' }}</div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>

{{-- Per-email metadata + content stored in JS (avoids HTML-parsing loss of <html>/<head> tags) --}}
@if ($hasEvents)
    <div id="eml-{{ $hist->id }}"
        data-type="{{ $emailTypeLabel }}"
        data-to="{{ $recipientEmail }}"
        data-sent-at="{{ $fullSentAt }}"
        data-by="{{ $createdByName }}{{ $createdByRole ? ' (' . $createdByRole . ')' : '' }}"
        data-status="{{ $isDelivered ? 'Delivered' : 'Sent' }}"
        data-has-content="{{ $emailContent ? '1' : '0' }}"
        style="display:none;"></div>
    @if ($emailContent)
    <script>
        window.__emailContent = window.__emailContent || {};
        window.__emailContent[{{ $hist->id }}] = {!! json_encode($emailContent, JSON_HEX_TAG) !!};
    </script>
    @endif
@endif


@once
{{-- Shared email preview modal — JS populates it on each open --}}
<div class="email-modal-overlay" id="emailModal" onclick="if(event.target===this)closeEmailModal()">
    <div class="email-modal">
        <div class="email-modal-topbar">
            <div class="email-modal-title" id="emailModalTitle">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-right:6px;vertical-align:middle;"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
                Email
            </div>
            <button class="email-modal-close" onclick="closeEmailModal()">&#x2715;</button>
        </div>
        <div class="email-modal-meta">
            <span><b>To:</b> <span id="emailModalTo"></span></span>
            <span><b>Sent:</b> <span id="emailModalSentAt"></span></span>
            <span><b>By:</b> <span id="emailModalBy"></span></span>
            <span id="emailModalStatusPill" class="email-status-pill" style="margin-left:auto;"></span>
        </div>
        <div class="email-modal-body" style="padding:0;">
            <iframe id="emailModalFrame"
                style="width:100%;min-height:500px;border:none;display:block;"
                sandbox="allow-same-origin"
                title="Email Preview"></iframe>
        </div>
    </div>
</div>
@endonce
