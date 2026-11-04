@php
    $d                 = resolveCancelData($hist, $isCashFees ?? '0');
    $createdByName     = $d['createdByName'];
    $createdByRole     = $d['createdByRole'];
    $details           = $d['details'];
    $paymentType       = $d['paymentType'];
    $histPaymentType   = $d['histPaymentType'];
    $cardNumber        = $d['cardNumber'];
    $note              = $d['note'];
    $refundAmount      = $d['refundAmount'];
    $totalTicketCost   = $d['totalTicketCost'];
    $totalPlatformFees = $d['totalPlatformFees'];
    $showPlatformFees  = $d['showPlatformFees'];
    $refundType        = $d['refundType'];
    $cancelLabel       = $d['cancelLabel'];
    $isCardBreakdown   = $d['isCardBreakdown'];
    $breakdownCards    = $d['breakdownCards'];
    $breakdownAmounts  = $d['breakdownAmounts'];
@endphp
<div class="log-entry" data-type="refund">
    <div class="log-time">{{ formatDateTime($hist->operation_at) }}
        <div class="log-user"><img class="log-avatar" src="{{ resolveHistAvatar($hist, $histPhotoMap) }}" alt="">
            <div class="log-user-info"><span class="log-user-name">{{ $createdByName }}</span><span
                    class="log-cred">{{ $createdByRole }}</span></div>
        </div>
    </div>
    <div class="log-badge cancelled">Cancelled</div>
    <div class="log-content">
        <div class="txn-card cancelled">
            <div class="txn-amount">− {{ moneyFormat($refundAmount) }}</div>
            <div class="txn-method">
                Booking Cancelled{{ $cancelLabel ? " - $cancelLabel" : '' }}
                @if ($paymentType == 'Card' && $cardNumber)
                    <span class="card-chip">Card ···· {{ $cardNumber }}</span>
                @elseif($paymentType != '')
                    <span class="card-chip" style="{{ paymentTypeChipStyle($paymentType) }}">{{ $paymentType }}</span>
                @endif
            </div>
            <div class="txn-breakdown">
                 <div>Ticket Cost: <b>{{ moneyFormat($totalTicketCost) }}</b></div>
                @if ($showPlatformFees)
                        <div>Platform Fees: <b>{{ moneyFormat($totalPlatformFees) }}</b></div>
                @endif
                @if ($hist->tourists)
                        <div>New Pax Count: <b>{{ $hist->tourists }}</b></div>
                @endif
            </div>
            @if ($isCardBreakdown)
                <div class="refund-card-breakdown mb-2">
                    <div class="refund-card-breakdown-label">Card Breakdown</div>
                    @foreach ($breakdownCards as $k => $cardNo)
                        @if (isset($breakdownAmounts[$k]))
                            <div class="refund-card-breakdown-row">
                                <span class="refund-card-breakdown-amount">{{ moneyFormat($breakdownAmounts[$k]) }}</span>
                                <span class="card-chip">Card ···· {{ str_pad((string) $cardNo, 4, '0', STR_PAD_LEFT) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
            @if($note)
                <div class="txn-note-box">
                    Note: {{ $note }}
                </div>
            @endif
        </div>
    </div>
</div>