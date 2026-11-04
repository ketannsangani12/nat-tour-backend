<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payout Notification</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f7;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;">
    {{-- Header --}}
    <tr>
        <td style="padding:30px 40px;background-color:{{ $type === 'failed' ? '#dc3545' : ($type === 'pending_approval' ? '#f0ad4e' : '#28a745') }};color:#ffffff;">
            <h1 style="margin:0;font-size:22px;font-weight:600;">
                @if($type === 'pending_approval')
                    Payout Awaiting Approval
                @elseif($type === 'executed')
                    Payout Executed Successfully
                @elseif($type === 'failed')
                    Payout Failed
                @endif
            </h1>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td style="padding:30px 40px;">
            <p style="margin:0 0 20px;color:#555;font-size:15px;line-height:1.5;">
                @if($type === 'pending_approval')
                    A new automatic payout has been created and requires your approval before it can be processed.
                @elseif($type === 'executed')
                    An approved payout has been processed and sent to the bank account.
                @elseif($type === 'failed')
                    An automatic payout has failed to process. Please review the details below and take action.
                @endif
            </p>

            <table width="100%" cellpadding="10" cellspacing="0" style="border:1px solid #e8e8e8;border-radius:6px;margin-bottom:20px;">
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;width:40%;"><strong>Reference</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ $payoutData['payout_reference'] }}</td>
                </tr>
                <tr>
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>Stripe Account</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">{{ $payoutData['stripe_account_id'] }}</td>
                </tr>
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>Total Amount</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">${{ number_format($payoutData['total_amount'], 2) }}</td>
                </tr>
                <tr>
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>Platform Fee</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;">${{ number_format($payoutData['platform_fee'], 2) }}</td>
                </tr>
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>Net Payout</strong></td>
                    <td style="font-size:14px;border-bottom:1px solid #e8e8e8;"><strong>${{ number_format($payoutData['net_amount'], 2) }}</strong></td>
                </tr>
                <tr>
                    <td style="color:#666;font-size:14px;{{ (!empty($payoutData['error']) || !empty($payoutData['stripe_payout_id'])) ? 'border-bottom:1px solid #e8e8e8;' : '' }}"><strong>Bookings</strong></td>
                    <td style="font-size:14px;{{ (!empty($payoutData['error']) || !empty($payoutData['stripe_payout_id'])) ? 'border-bottom:1px solid #e8e8e8;' : '' }}">{{ $payoutData['booking_count'] }}</td>
                </tr>
                @if($type === 'failed' && !empty($payoutData['error']))
                <tr style="background-color:#fff5f5;">
                    <td style="color:#666;font-size:14px;"><strong>Error</strong></td>
                    <td style="font-size:14px;color:#dc3545;">{{ $payoutData['error'] }}</td>
                </tr>
                @endif
                @if($type === 'executed' && !empty($payoutData['stripe_payout_id']))
                <tr style="background-color:#f8f9fa;">
                    <td style="color:#666;font-size:14px;"><strong>Stripe Payout ID</strong></td>
                    <td style="font-size:14px;">{{ $payoutData['stripe_payout_id'] }}</td>
                </tr>
                @endif
            </table>

            <p style="margin:0;color:#555;font-size:14px;line-height:1.5;">
                @if($type === 'pending_approval')
                    Please log in to the Auto Payouts dashboard to review and approve this payout.
                @elseif($type === 'failed')
                    Please log in to investigate the issue or retry the payout manually.
                @endif
            </p>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:20px 40px;background-color:#f8f9fa;border-top:1px solid #e8e8e8;">
            <p style="margin:0;color:#999;font-size:12px;">This is an automated notification from the Auto Payout system.</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
