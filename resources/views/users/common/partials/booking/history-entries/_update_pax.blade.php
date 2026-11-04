@php
    $d              = resolveUpdatePaxData($hist, $pairedPayment ?? null);
    $createdByName  = $d['createdByName'];
    $createdByRole  = $d['createdByRole'];
    $details        = $d['details'];
    $subOp          = $d['subOp'];
    $isChanged      = $d['isChanged'];
    $addedGrouped   = $d['addedGrouped'];
    $deletedGrouped = $d['deletedGrouped'];
    $summaryAdded   = $d['summaryAdded'];
    $summaryDeleted = $d['summaryDeleted'];
    $cardTitle      = $d['cardTitle'];
    $payment        = $d['payment'];
    $paymentDetails = $d['paymentDetails'];
    $paymentAmount  = $d['paymentAmount'];
    $paymentMethod  = $d['paymentMethod'];
    $paymentType    = $d['paymentType'];
    $refundType     = $d['refundType'];
    $cardNumber     = $d['cardNumber'];
    $isRefund       = $d['isRefund'];
    $paymentTotal   = $d['paymentTotal'];
    $refundTotal    = $d['refundTotal'];
@endphp

<div class="log-entry" data-type="payment">
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

    <div class="log-badge update-pax">
        Update Pax
        @if ($payment)
            <span
                class="{{ $isRefund ? 'badge-sub-refund' : 'badge-sub-payment' }}">{{ $isRefund ? 'Refund' : 'Payment' }}</span>
        @endif
    </div>

    <div class="log-content">
        <div class="sys-card original {{ $payment ? 'sys-card--spaced' : '' }}">
            <div class="sys-card-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title">{{ $cardTitle }}</div>
                <div class="sys-card-rows">
                    @if(!$payment && $paymentTotal > 0 && $refundTotal > 0 && $paymentTotal == $refundTotal)
                        <div class="no-charge-box">
                            <div class="no-charge-row">
                                <span class="no-charge-amount">$0.00</span>
                                <span class="no-charge-label">No Charge · No Refund</span>
                            </div>
                            <div class="no-charge-note">Replacement ticket is the same price as the removed ticket. Balance unchanged.</div>
                        </div>
                    @endif
                    @if (!empty($addedGrouped))
                        <div class="pax-changes-box pax-changes-box--added">
                            <div class="pax-changes-label pax-changes-label--added">✚ Added</div>
                            @foreach ($addedGrouped as $rateName => $count)
                                <div class="pax-changes-item">{{ $rateName }} x {{ $count }}</div>
                            @endforeach
                        </div>
                    @endif
                    @if (!empty($deletedGrouped))
                        <div class="pax-changes-box pax-changes-box--removed">
                            <div class="pax-changes-label pax-changes-label--removed">✕ Removed</div>
                            @foreach ($deletedGrouped as $rateName => $count)
                                <div class="pax-changes-item">{{ $rateName }} x {{ $count }}</div>
                            @endforeach
                        </div>
                    @endif
                    @if ($hist->tourists)
                        <div class="sys-card-row">
                            <span class="sys-pax-badge">
                                <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2.5">
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

        @if ($payment)
            <div class="txn-card {{ $isRefund ? 'refund' : 'payment' }}">
                <div class="txn-amount">{{ $isRefund ? '−' : '+' }} {{ moneyFormat($paymentAmount) }}</div>
                <div class="txn-method">
                    @if($isRefund)
                        {{ $refundType }}
                    @else
                        Guest Added — Additional Charge
                    @endif
                    @if ($paymentType === 'Card' && $cardNumber)
                        <span class="card-chip">Card ···· {{ $cardNumber }}</span>
                    @else
                        @php
                            $displayPaymentType = $paymentType;
                            if ($paymentType == 'Invoiced') {
                                $displayPaymentType = !empty($invoiced)
                                    ? ($invoiced->status == 'paid' ? $details->payment_type : 'Invoiced')
                                    : 'Billable';
                            }
                         @endphp
                        @if ($displayPaymentType != '')
                            <span class="card-chip"
                                style="{{ paymentTypeChipStyle($displayPaymentType) }}">{{ $displayPaymentType }}</span>
                        @endif
                    @endif
                </div>
                <div class="txn-breakdown">
                    <div>Ticket Cost: <b>{{ moneyFormat($payment->ticket_cost) }}</b></div>
                    @if ($paymentType == 'Cash' || $paymentType == 'Card' || in_array($paymentType, $otaPlatforms))
                        <div>Platform Fees: <b>{{ moneyFormat($payment->platform_fees) }}</b></div>
                    @endif
                    {{-- Same Paid/Change rows as the standalone payment card (_payment.blade.php) —
                    the change_amount was stored but never shown for a Change People cash payment. --}}
                    @if (!$isRefund && $paymentType == 'Cash')
                        @if (isset($paymentDetails->paid_amount) && $paymentDetails->paid_amount > 0 && $paymentMethod != 'Split Payment')
                            <div>Paid: <b>{{ moneyFormat($paymentDetails->paid_amount) }}</b></div>
                        @elseif (isset($payment->change_amount) && $payment->change_amount > 0)
                            <div>Paid: <b>{{ moneyFormat($payment->change_amount + $paymentAmount) }}</b></div>
                            <div>Change: <b>{{ moneyFormat($payment->change_amount) }}</b></div>
                        @endif
                    @endif
                    @if ($hist->tourists)
                        <div>New Pax Count: <b>{{ $hist->tourists }}</b></div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>