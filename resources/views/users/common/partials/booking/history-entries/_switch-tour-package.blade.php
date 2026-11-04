@php
    $d                = resolveSwitchTourPackageData($hist, $pairedPayment ?? null, $isCashFees ?? '0');
    $createdByName    = $d['createdByName'];
    $createdByRole    = $d['createdByRole'];
    $details          = $d['details'];
    $addedGrouped     = $d['addedGrouped'];
    $deletedGrouped   = $d['deletedGrouped'];
    $oldPackage       = $d['oldPackage'];
    $newPackage       = $d['newPackage'];
    $payment          = $d['payment'];
    $paymentDetails   = $d['paymentDetails'];
    $paymentAmount    = $d['paymentAmount'];
    $paymentMethod    = $d['paymentMethod'];
    $paymentType      = $d['paymentType'];
    $refundType       = ($d['refundType'] == 'Full Refund minus Fees') ? 'Ticket Cost' : $d['refundType'];
    $cardNumber       = $d['cardNumber'];
    $isRefund         = $d['isRefund'];
    $showPlatformFees = $d['showPlatformFees'];
@endphp
<div class="log-entry" data-type="{{ $isRefund ? 'refund' : 'payment' }}">
    <div class="log-time">{{ formatDateTime($hist->operation_at) }}
        <div class="log-user"><img class="log-avatar" src="{{ resolveHistAvatar($hist, $histPhotoMap) }}" alt="">
            <div class="log-user-info"><span class="log-user-name">{{ $createdByName }}</span>
                @if ($createdByRole)
                    <span class="log-cred">{{ $createdByRole }}</span>
                @endif
            </div>
        </div>
    </div>
    <div class="log-badge update-pax">Switch Tour Package</div>
    <div class="log-content">
        <div class="sys-card original">
            <div class="sys-card-icon">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"></path>
                </svg>
            </div>
            <div class="sys-card-body">
                <div class="sys-card-title">Edit Booking — Switch <strong>{{ $oldPackage }}</strong>
                    to <strong>{{ $newPackage }}</strong></div>
                <div class="sys-card-rows">

                    <!-- Payment / Refund -->
                    @if ($payment)
                        <div class="switch-pkg-payment {{ $isRefund ? 'switch-pkg-payment--refund' : '' }}">
                            <div class="switch-pkg-payment-amount">{{ $isRefund ? '−' : '+' }}
                                {{ moneyFormat($paymentAmount) }}</div>
                            <div class="switch-pkg-payment-method">
                                {{ $isRefund ? $refundType : $paymentMethod }}

                                @if ($paymentType === 'Card' && $cardNumber)
                                    <span class="card-chip">Card ···· {{ $cardNumber }}</span>
                                @elseif ($paymentType !== '')
                                    @php
                                        $displayPaymentType = $paymentType;
                                        if ($paymentType == 'Invoiced' && !$isRefund) {
                                            $displayPaymentType = !empty($invoiced)
                                                ? ($invoiced->status == 'paid' ? $details->payment_type : 'Invoiced')
                                                : 'Billable';
                                        }
                                     @endphp
                                    <span class="card-chip"
                                        style="{{ paymentTypeChipStyle($displayPaymentType) }}">{{ $displayPaymentType }}</span>
                                @endif
                            </div>
                            <div class="switch-pkg-payment-breakdown">
                                <span>Ticket Cost: <b>{{ moneyFormat($payment->ticket_cost ?? 0) }}</b></span>
                                @if ($showPlatformFees)
                                    <span>Platform Fees: <b>{{ moneyFormat($payment->platform_fees ?? 0) }}</b></span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Package switch row -->
                    <div class="switch-pkg-row">
                        <span class="switch-pkg-from">{{ $oldPackage }}</span>
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#7040c0" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"></path>
                        </svg>
                        <span class="switch-pkg-to">{{ $newPackage }}</span>
                    </div>

                    <!-- Pax changes — side-by-side diff -->
                    @if (!empty($addedGrouped) || !empty($deletedGrouped))
                        <div class="pax-diff">
                            @if (!empty($addedGrouped))
                                <div class="pax-diff-col pax-diff-col--added">
                                    <div class="pax-diff-header">
                                        <span class="pax-diff-icon">+</span>
                                        <span class="pax-diff-title">Joined</span>
                                        <span class="pax-diff-count">{{ array_sum($addedGrouped) }}</span>
                                    </div>
                                    @foreach ($addedGrouped as $rateName => $count)
                                        <div class="pax-diff-row">
                                            <span class="pax-diff-name">{{ $rateName }}</span>
                                            <span class="pax-diff-qty">×{{ $count }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (!empty($deletedGrouped))
                                <div class="pax-diff-col pax-diff-col--removed">
                                    <div class="pax-diff-header">
                                        <span class="pax-diff-icon">−</span>
                                        <span class="pax-diff-title">Dropped</span>
                                        <span class="pax-diff-count">{{ array_sum($deletedGrouped) }}</span>
                                    </div>
                                    @foreach ($deletedGrouped as $rateName => $count)
                                        <div class="pax-diff-row">
                                            <span class="pax-diff-name">{{ $rateName }}</span>
                                            <span class="pax-diff-qty">×{{ $count }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Pax badge -->
                    <div class="sys-card-row">
                        <span class="sys-pax-badge">
                            <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            New Pax Count: {{ $hist->tourists }}
                        </span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>