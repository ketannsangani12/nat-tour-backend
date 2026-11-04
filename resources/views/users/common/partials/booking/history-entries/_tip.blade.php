@php
    $r             = resolveHistCreatedBy($hist);
    $createdByName = $r['createdByName'];
    $createdByRole = $r['createdByRole'];
    $details       = $r['details'];

    $paymentType = ($details && !empty($details->payment_type)) ? (string) $details->payment_type : '';
    $tipAmount   = $details ? (float) ($details->amount ?? 0) : 0.0;
    $cardNumber  = ($details && !empty($details->card_number))
        ? str_pad((string) $details->card_number, 4, '0', STR_PAD_LEFT)
        : null;
    $note = ($details && !empty($details->note)) ? (string) $details->note : '';
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

    <div class="log-badge tip">Tip</div>

    <div class="log-content">
        <div class="txn-card tip">
            <div class="txn-amount">+ {{ moneyFormat($tipAmount) }}</div>
            <div class="txn-method">
                Tip Received
                @if ($paymentType === 'Card' && $cardNumber)
                    <span class="card-chip">Card ···· {{ $cardNumber }}</span>
                @elseif ($paymentType !== '')
                    <span class="card-chip" style="{{ paymentTypeChipStyle($paymentType) }}">{{ $paymentType }}</span>
                @endif
            </div>
            @if ($note)
                <div class="txn-note-box">
                    Note: {{ $note }}
                </div>
            @endif
        </div>
    </div>
</div>
