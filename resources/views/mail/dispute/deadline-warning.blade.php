<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dispute Evidence Deadline Warning</title>
<style>
    @media only screen and (max-width: 600px) {
        .container { width: 100% !important; }
        .content { padding: 20px !important; }
        .header { padding: 20px !important; }
        .footer { padding: 15px !important; }
        .button { padding: 10px 20px !important; font-size: 14px !important; }
        .details-table td { display: block !important; width: 100% !important; padding: 8px !important; }
        .details-table .label { font-weight: 600 !important; border-bottom: none !important; }
    }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f8f9fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen,Ubuntu,Cantarell,sans-serif;line-height:1.6;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;padding:40px 20px;">
<tr><td align="center">
<table class="container" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 6px rgba(0,0,0,0.1);">
    {{-- Header --}}
    <tr>
        <td class="header" style="padding:40px;background: linear-gradient(135deg, {{ $urgency === 'critical' ? '#dc3545 0%, #c82333 100%' : ($urgency === 'high' ? '#f0ad4e 0%, #ec971f 100%' : '#17a2b8 0%, #138496 100%') }});color:#ffffff;text-align:center;">
            <div style="margin-bottom:15px;">
                <div style="width:60px;height:60px;background-color:rgba(255,255,255,0.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:15px;">
                    @if($daysUntilDeadline === 1)
                        <span style="font-size:28px;">🚨</span>
                    @else
                        <span style="font-size:28px;">⚠️</span>
                    @endif
                </div>
            </div>
            <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
                @if($daysUntilDeadline === 1)
                    Evidence Due Tomorrow
                @else
                    Evidence Due in {{ $daysUntilDeadline }} Days
                @endif
            </h1>
            <p style="margin:10px 0 0;font-size:16px;opacity:0.9;font-weight:400;">
                Deadline approaching - action required
            </p>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td class="content" style="padding:40px;">
            <div style="text-align:center;margin-bottom:30px;">
                <p style="margin:0;color:#6c757d;font-size:16px;line-height:1.5;">
                    A payment dispute requires your immediate attention. The evidence submission deadline is approaching 
                    @if($daysUntilDeadline === 1)
                        <strong style="color:#dc3545;">tomorrow</strong>.
                    @else
                        in <strong style="color:#f0ad4e;">{{ $daysUntilDeadline }} day(s)</strong>.
                    @endif
                </p>
            </div>

            @if($urgency === 'critical')
            <div style="background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);border-left:4px solid #dc3545;border-radius:8px;padding:20px;margin-bottom:30px;">
                <div style="display:flex;align-items:center;">
                    <span style="font-size:20px;margin-right:10px;">🚨</span>
                    <div>
                        <p style="margin:0;color:#721c24;font-weight:600;font-size:15px;">
                            CRITICAL: Evidence must be submitted by tomorrow!
                        </p>
                        <p style="margin:5px 0 0;color:#721c24;font-size:14px;">
                            Failure to respond will result in automatic dispute loss
                        </p>
                    </div>
                </div>
            </div>
            @elseif($urgency === 'high')
            <div style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%);border-left:4px solid #f0ad4e;border-radius:8px;padding:20px;margin-bottom:30px;">
                <div style="display:flex;align-items:center;">
                    <span style="font-size:20px;margin-right:10px;">⚠️</span>
                    <div>
                        <p style="margin:0;color:#8a6d3b;font-weight:600;font-size:15px;">
                            HIGH PRIORITY: Please prioritize evidence submission
                        </p>
                        <p style="margin:5px 0 0;color:#8a6d3b;font-size:14px;">
                            Prepare comprehensive documentation to maximize success
                        </p>
                    </div>
                </div>
            </div>
            @endif

            <div style="background-color:#f8f9fa;border-radius:12px;padding:30px;margin-bottom:30px;">
                <h3 style="margin:0 0 20px;color:#212529;font-size:18px;font-weight:600;display:flex;align-items:center;">
                    <span style="width:24px;height:24px;background-color:#007bff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;font-size:12px;color:white;">📋</span>
                    Dispute Details
                </h3>
                <table class="details-table" width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden;border:1px solid #e9ecef;">
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;width:35%;padding:15px;vertical-align:top;">Dispute ID</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;font-family:monospace;color:#495057;">{{ $dispute->stripe_dispute_id }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Amount</td>
                        <td style="font-size:16px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;color:#dc3545;">{{ $dispute->currency }} {{ number_format($dispute->amount, 2) }}</td>
                    </tr>
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Reason</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#495057;">{{ $dispute->reason_label }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Status</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;">
                            <span style="background-color:#ffc107;color:#212529;padding:4px 8px;border-radius:4px;font-size:12px;font-weight:600;">{{ $dispute->status_label }}</span>
                        </td>
                    </tr>
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Evidence Deadline</td>
                        <td style="font-size:14px;padding:15px;color:{{ $urgency === 'critical' ? '#dc3545' : '#495057' }};font-weight:{{ $urgency === 'critical' ? '600' : 'normal' }};">
                            {{ $dispute->evidence_due_by->format('M j, Y g:i A T') }}
                            @php
                                $hoursRemaining = now()->diffInHours($dispute->evidence_due_by, false);
                            @endphp
                            <br><small style="color:{{ $urgency === 'critical' ? '#dc3545' : '#6c757d' }};font-weight:{{ $urgency === 'critical' ? '600' : 'normal' }};">({{ $hoursRemaining }} hours remaining)</small>
                        </td>
                    </tr>
                </table>
            </div>

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

            <div style="text-align:center;margin:30px 0;">
                <a href="{{ $dispute_url }}" 
                   style="display:inline-block;padding:12px 24px;background-color:#007bff;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;">
                    View Dispute Details
                </a>
            </div>

            <p style="margin:0;color:#555;font-size:14px;line-height:1.5;">
                Please review and submit evidence as soon as possible to avoid losing this dispute. 
                Failure to respond by the deadline may result in an automatic loss.
            </p>
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