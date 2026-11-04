<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dispute Status Update</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
    {{-- Header --}}
    <tr>
        <td style="padding:30px 40px;background-color:{{ $newStatus === 'won' ? '#28a745' : ($newStatus === 'lost' ? '#dc3545' : ($newStatus === 'charge_refunded' ? '#17a2b8' : '#6c757d')) }};color:#ffffff;">
            <h1 style="margin:0;font-size:22px;font-weight:600;">
                @if($newStatus === 'won')
                    ✅ Dispute Won
                @elseif($newStatus === 'lost')
                    ❌ Dispute Lost
                @elseif($newStatus === 'charge_refunded')
                    💰 Dispute Charge Refunded
                @elseif($newStatus === 'under_review')
                    🔍 Dispute Under Review
                @else
                    📋 Dispute Status Updated
                @endif
            </h1>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td style="padding:30px 40px;">
            <p style="margin:0 0 20px;color:#555;font-size:15px;line-height:1.5;">
                The status of payment dispute <strong>{{ $dispute->stripe_dispute_id }}</strong> has been updated.
            </p>

            <h3 style="margin:0 0 15px;color:#333;font-size:16px;">Status Change</h3>
            <table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #e8e8e8;border-radius:6px;margin-bottom:20px;">
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;width:40%;"><strong>From</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ ucfirst(str_replace('_', ' ', $oldStatus)) }}</td>
                </tr>
                <tr>
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>To</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;font-weight:600;color:{{ $newStatus === 'won' ? '#28a745' : ($newStatus === 'lost' ? '#dc3545' : '#333') }};">
                        @if($newStatus === 'won')
                            ✅ Won
                        @elseif($newStatus === 'lost')
                            ❌ Lost
                        @elseif($newStatus === 'charge_refunded')
                            💰 Charge Refunded
                        @elseif($newStatus === 'under_review')
                            🔍 Under Review
                        @elseif($newStatus === 'warning_needs_response')
                            ⚠️ Needs Response
                        @else
                            📋 {{ ucfirst(str_replace('_', ' ', $newStatus)) }}
                        @endif
                    </td>
                </tr>
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;"><strong>Updated</strong></td>
                    <td style="font-size:14px;">{{ now()->format('M j, Y g:i A T') }}</td>
                </tr>
            </table>

            {{-- Status-specific message --}}
            @if($newStatus === 'won')
            <div style="background-color:#d4edda;border-left:4px solid #28a745;padding:15px;margin-bottom:20px;">
                <p style="margin:0;color:#155724;font-weight:600;">
                    🎉 Great news! You have successfully won this dispute. The funds will remain in your account and no chargeback fee will be applied.
                </p>
            </div>
            @elseif($newStatus === 'lost')
            <div style="background-color:#f8d7da;border-left:4px solid #dc3545;padding:15px;margin-bottom:20px;">
                <p style="margin:0;color:#721c24;font-weight:600;">
                    😞 Unfortunately, this dispute was not resolved in your favor. The disputed amount has been refunded to the customer, and a chargeback fee may apply.
                </p>
            </div>
            @elseif($newStatus === 'charge_refunded')
            <div style="background-color:#d1ecf1;border-left:4px solid #17a2b8;padding:15px;margin-bottom:20px;">
                <p style="margin:0;color:#0c5460;font-weight:600;">
                    💰 The charge has been refunded as part of the dispute resolution. This may have been done proactively or as part of the dispute process.
                </p>
            </div>
            @elseif($newStatus === 'under_review')
            <div style="background-color:#e2e3e5;border-left:4px solid #6c757d;padding:15px;margin-bottom:20px;">
                <p style="margin:0;color:#383d41;font-weight:600;">
                    🔍 Your evidence is being reviewed by the card issuer. You will be notified once a decision has been made. No further action is required at this time.
                </p>
            </div>
            @elseif($newStatus === 'warning_needs_response')
            <div style="background-color:#fff8e1;border-left:4px solid #f0ad4e;padding:15px;margin-bottom:20px;">
                <p style="margin:0;color:#8a6d3b;font-weight:600;">
                    ⚠️ Action Required: This dispute requires evidence submission. Please review and submit your response before the deadline.
                </p>
            </div>
            @endif

            <h3 style="margin:0 0 15px;color:#333;font-size:16px;">Dispute Details</h3>
            <table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #e8e8e8;border-radius:6px;margin-bottom:20px;">
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;width:40%;"><strong>Dispute ID</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ $dispute->stripe_dispute_id }}</td>
                </tr>
                <tr>
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>Amount</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ $dispute->currency }} {{ number_format($dispute->amount, 2) }}</td>
                </tr>
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;"><strong>Reason</strong></td>
                    <td style="font-size:14px;">{{ $dispute->reason_label }}</td>
                </tr>
            </table>

            @if($dispute->booking)
            <h3 style="margin:0 0 15px;color:#333;font-size:16px;">Associated Booking</h3>
            <table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #e8e8e8;border-radius:6px;margin-bottom:20px;">
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;width:40%;"><strong>Booking ID</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ $dispute->booking->id }}</td>
                </tr>
                <tr>
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>Customer</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ $dispute->booking->customer_name }}</td>
                </tr>
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;"><strong>Tour Date</strong></td>
                    <td style="font-size:14px;">{{ $dispute->booking->tour_date }}</td>
                </tr>
            </table>
            @endif

            @if(!empty($metadata))
            <h3 style="margin:0 0 15px;color:#333;font-size:16px;">Additional Information</h3>
            <table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #e8e8e8;border-radius:6px;margin-bottom:20px;">
                @foreach($metadata as $key => $value)
                    @if(is_string($value) || is_numeric($value))
                    <tr style="{{ $loop->even ? 'background-color:#f8f9fa;' : '' }}">
                        <td style="color:#666;font-size:14px;{{ !$loop->last ? 'border-bottom:1px solid #e8e8e8;' : '' }}width:40%;"><strong>{{ ucfirst(str_replace('_', ' ', $key)) }}</strong></td>
                        <td style="font-size:14px;{{ !$loop->last ? 'border-bottom:1px solid #e8e8e8;' : '' }}">{{ $value }}</td>
                    </tr>
                    @endif
                @endforeach
            </table>
            @endif

            <div style="text-align:center;margin:30px 0;">
                <a href="{{ $dispute_url }}" 
                   style="display:inline-block;padding:12px 24px;background-color:#007bff;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
                    View Dispute Details
                </a>
            </div>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:20px 40px;background-color:#f8f9fa;border-top:1px solid #e8e8e8;">
            <p style="margin:0;color:#999;font-size:12px;">
                This is an automated notification from the Dispute Management system. 
                Please do not reply to this email.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>