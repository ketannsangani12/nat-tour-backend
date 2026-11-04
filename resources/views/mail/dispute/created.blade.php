<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>New Payment Dispute Created</title>
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
        <td class="header" style="padding:40px;background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);color:#ffffff;text-align:center;">
            <div style="margin-bottom:15px;">
                <div style="width:60px;height:60px;background-color:rgba(255,255,255,0.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:15px;">
                    <span style="font-size:28px;">🚨</span>
                </div>
            </div>
            <h1 style="margin:0;font-size:24px;font-weight:700;letter-spacing:-0.5px;">
                New Payment Dispute Alert
            </h1>
            <p style="margin:10px 0 0;font-size:16px;opacity:0.9;font-weight:400;">
                Immediate attention required
            </p>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td class="content" style="padding:40px;">
            <div style="text-align:center;margin-bottom:30px;">
                <p style="margin:0;color:#6c757d;font-size:16px;line-height:1.5;">
                    A new payment dispute has been created and requires your immediate attention.
                </p>
            </div>

            @if($urgency === 'critical')
            <div style="background: linear-gradient(135deg, #fff5f5 0%, #ffeaea 100%);border-left:4px solid #dc3545;border-radius:8px;padding:20px;margin-bottom:30px;">
                <div style="display:flex;align-items:center;">
                    <span style="font-size:20px;margin-right:10px;">🚨</span>
                    <div>
                        <p style="margin:0;color:#721c24;font-weight:600;font-size:15px;">
                            URGENT: Evidence submission deadline is within 24 hours!
                        </p>
                        <p style="margin:5px 0 0;color:#721c24;font-size:14px;">
                            Immediate action required to avoid automatic loss
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
                            HIGH PRIORITY: Evidence submission deadline approaching
                        </p>
                        <p style="margin:5px 0 0;color:#8a6d3b;font-size:14px;">
                            Please prioritize evidence preparation and submission
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
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Created</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#495057;">{{ $dispute->created_at->format('M j, Y g:i A T') }}</td>
                    </tr>
                    @if($dispute->evidence_due_by)
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Evidence Due</td>
                        <td style="font-size:14px;padding:15px;color:{{ $urgency === 'critical' ? '#dc3545' : '#495057' }};font-weight:{{ $urgency === 'critical' ? '600' : 'normal' }};">
                            {{ $dispute->evidence_due_by->format('M j, Y g:i A T') }}
                            @php
                                $daysUntilDeadline = now()->diffInDays($dispute->evidence_due_by, false);
                            @endphp
                            <br><small style="color:#6c757d;">({{ $daysUntilDeadline }} day{{ $daysUntilDeadline !== 1 ? 's' : '' }} remaining)</small>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>

            @if($dispute->booking)
            <div style="background-color:#f8f9fa;border-radius:12px;padding:30px;margin-bottom:30px;">
                <h3 style="margin:0 0 20px;color:#212529;font-size:18px;font-weight:600;display:flex;align-items:center;">
                    <span style="width:24px;height:24px;background-color:#28a745;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;font-size:12px;color:white;">🎫</span>
                    Associated Booking
                </h3>
                <table class="details-table" width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden;border:1px solid #e9ecef;">
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;width:35%;padding:15px;vertical-align:top;">Booking ID</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;font-family:monospace;color:#495057;">#{{ $dispute->booking->id }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Customer</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#495057;font-weight:600;">{{ $dispute->booking->customer_name }}</td>
                    </tr>
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Tour Date</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#495057;">{{ $dispute->booking->tour_date }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Tour Package</td>
                        <td style="font-size:14px;padding:15px;color:#495057;">{{ $dispute->booking->tour_package_name ?? 'N/A' }}</td>
                    </tr>
                </table>
            </div>
            @endif

            @if($dispute->transaction)
            <div style="background-color:#f8f9fa;border-radius:12px;padding:30px;margin-bottom:30px;">
                <h3 style="margin:0 0 20px;color:#212529;font-size:18px;font-weight:600;display:flex;align-items:center;">
                    <span style="width:24px;height:24px;background-color:#17a2b8;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;font-size:12px;color:white;">💳</span>
                    Payment Information
                </h3>
                <table class="details-table" width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden;border:1px solid #e9ecef;">
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;width:35%;padding:15px;vertical-align:top;">Transaction ID</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;font-family:monospace;color:#495057;">{{ $dispute->transaction->id }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Payment Date</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#495057;">{{ $dispute->transaction->created_at->format('M j, Y') }}</td>
                    </tr>
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Payment Method</td>
                        <td style="font-size:14px;padding:15px;color:#495057;">{{ $dispute->transaction->payment_method ?? 'Card' }}</td>
                    </tr>
                </table>
            </div>
            @endif

            <div style="text-align:center;margin:40px 0;">
                <a href="{{ $dispute_url }}" 
                   class="button"
                   style="display:inline-block;padding:16px 32px;background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:16px;box-shadow:0 4px 6px rgba(0,123,255,0.3);transition:all 0.3s ease;">
                    View Dispute Details →
                </a>
            </div>

            <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);border-radius:12px;padding:25px;margin-bottom:20px;border-left:4px solid #2196f3;">
                <h4 style="margin:0 0 10px;color:#1565c0;font-size:16px;font-weight:600;">📋 Next Steps</h4>
                <ul style="margin:0;padding-left:20px;color:#1976d2;font-size:14px;line-height:1.6;">
                    <li>Review the dispute details and associated booking information</li>
                    <li>Gather relevant evidence (booking confirmation, communication, delivery proof)</li>
                    <li>Prepare a comprehensive response addressing the dispute reason</li>
                    <li>Submit evidence before the deadline to maximize chances of success</li>
                </ul>
            </div>

            <p style="margin:0;color:#6c757d;font-size:14px;line-height:1.6;text-align:center;">
                Early preparation and comprehensive evidence submission significantly increase the chances of a successful dispute resolution.
            </p>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td class="footer" style="padding:30px;background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);border-top:1px solid #dee2e6;text-align:center;">
            <div style="margin-bottom:15px;">
                <img src="{{ asset('images/logo.png') }}" alt="Company Logo" style="height:32px;opacity:0.7;">
            </div>
            <p style="margin:0;color:#6c757d;font-size:12px;line-height:1.5;">
                This is an automated notification from the Dispute Management System.<br>
                Please do not reply to this email. For support, contact your system administrator.
            </p>
            <div style="margin-top:15px;padding-top:15px;border-top:1px solid #dee2e6;">
                <p style="margin:0;color:#adb5bd;font-size:11px;">
                    © {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                </p>
            </div>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>