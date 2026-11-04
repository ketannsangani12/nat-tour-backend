@php
    $d             = resolvePermitFeesRefundData($hist);
    $createdByName = $d['createdByName'];
    $createdByRole = $d['createdByRole'];
    $details       = $d['details'];
    $cardNumber    = $d['cardNumber'];
    $paymentType   = $d['paymentType'];
    $totalAmount   = $d['totalAmount'];
    $permitPersons = $d['permitPersons'];
    $totalPermits  = $d['totalPermits'];
    $perPersonAmount = $d['perPersonAmount'];
@endphp

<div class="log-entry" data-type="refund">
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

    <div class="log-badge refund">Refund <span class="badge-sub-permit">Permit</span></div>

    <div class="log-content">
        <div class="txn-card refund">
            <div class="txn-amount">− {{ moneyFormat($totalAmount) }}</div>
            <div class="txn-method">
                Permit Fees Refund
                @if ($paymentType === 'Card' && $cardNumber)
                    <span class="card-chip">Card ···· {{ $cardNumber }}</span>
                @elseif ($paymentType)
                    <span class="card-chip" style="{{ paymentTypeChipStyle($paymentType) }}">{{ $paymentType }}</span>
                @endif
            </div>
            <div class="txn-breakdown">
                <div>Ticket Cost: <b>{{ moneyFormat($hist->ticket_cost ?? 0) }}</b></div>
                @if ($paymentType === 'Cash' || $paymentType === 'Card')
                    <div>Platform Fees: <b>{{ moneyFormat($hist->platform_fees ?? 0) }}</b></div>
                @endif
                @if ($hist->tourists)
                    <div>New Pax Count: <b>{{ $hist->tourists }}</b></div>
                @endif
            </div>
            @if (!empty($permitPersons))
                <div class="permit-refund-list">
                    <div class="permit-refund-label">
                        <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                        </svg>
                        {{ $totalPermits }} {{ Str::plural('Permit', $totalPermits) }} Removed
                    </div>
                    @foreach ($permitPersons as $person)
                        <div class="permit-refund-item">
                            <div>
                                <div class="permit-refund-item-name">{{ $person }}</div>
                                <div class="permit-refund-item-sub">Permit Fee</div>
                            </div>
                            <div class="permit-refund-item-price">
                                <div class="permit-refund-original">{{ moneyFormat($perPersonAmount) }}</div>
                                <div class="permit-refund-new">$0.00 <span class="permit-refund-diff">(− {{ moneyFormat($perPersonAmount) }})</span></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
