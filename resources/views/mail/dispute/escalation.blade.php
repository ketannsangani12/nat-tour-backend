<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>URGENT: Dispute Escalation Required</title>
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
<table class="container" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 16px rgba(220,53,69,0.2);">
    {{-- Header --}}
    <tr>
        <td class="header" style="padding:40px;background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);color:#ffffff;text-align:center;position:relative;">
            <div style="position:absolute;top:0;left:0;right:0;height:4px;background: repeating-linear-gradient(90deg, #ffc107 0px, #ffc107 10px, #dc3545 10px, #dc3545 20px);"></div>
            <div style="margin-bottom:20px;">
                <div style="width:80px;height:80px;background-color:rgba(255,255,255,0.2);border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:15px;animation:pulse 2s infinite;">
                    <span style="font-size:36px;">🚨</span>
                </div>
            </div>
            <h1 style="margin:0;font-size:28px;font-weight:800;letter-spacing:-0.5px;text-shadow:0 2px 4px rgba(0,0,0,0.3);">
                URGENT ESCALATION
            </h1>
            <p style="margin:10px 0 0;font-size:18px;opacity:0.95;font-weight:500;">
                Critical Dispute Requires Immediate Supervisor Attention
            </p>
        </td>
    </tr>

    {{-- Body --}}
    <tr>
        <td class="content" style="padding:40px;">
            <div style="background: linear-gradient(135deg, #fff5f5 0%, #ffe6e6 100%);border:2px solid #dc3545;border-radius:12px;padding:25px;margin-bottom:30px;text-align:center;">
                <h2 style="margin:0 0 15px;color:#721c24;font-size:20px;font-weight:700;">
                    ⚠️ CRITICAL DISPUTE ESCALATION
                </h2>
                <p style="margin:0;color:#721c24;font-size:16px;line-height:1.5;">
                    This dispute has been escalated due to {{ $escalationReason }} and requires immediate supervisor intervention.
                </p>
            </div>

            <div style="background-color:#f8f9fa;border-radius:12px;padding:30px;margin-bottom:30px;">
                <h3 style="margin:0 0 20px;color:#212529;font-size:18px;font-weight:600;display:flex;align-items:center;">
                    <span style="width:24px;height:24px;background-color:#dc3545;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;font-size:12px;color:white;">🚨</span>
                    Escalation Details
                </h3>
                <table class="details-table" width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden;border:1px solid #e9ecef;">
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;width:35%;padding:15px;vertical-align:top;">Escalation Reason</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#dc3545;font-weight:600;">{{ $escalationReason }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Escalated At</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;color:#495057;">{{ now()->format('M j, Y g:i A T') }}</td>
                    </tr>
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Priority Level</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;">
                            <span style="background-color:#dc3545;color:#ffffff;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:700;text-transform:uppercase;">CRITICAL</span>
                        </td>
                    </tr>
                    @if($timeRemaining)
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Time Remaining</td>
                        <td style="font-size:16px;padding:15px;color:#dc3545;font-weight:700;">{{ $timeRemaining }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            <div style="background-color:#f8f9fa;border-radius:12px;padding:30px;margin-bottom:30px;">
                <h3 style="margin:0 0 20px;color:#212529;font-size:18px;font-weight:600;display:flex;align-items:center;">
                    <span style="width:24px;height:24px;background-color:#007bff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;font-size:12px;color:white;">📋</span>
                    Dispute Information
                </h3>
                <table class="details-table" width="100%" cellpadding="0" cellspacing="0" style="border-radius:8px;overflow:hidden;border:1px solid #e9ecef;">
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;width:35%;padding:15px;vertical-align:top;">Dispute ID</td>
                        <td style="font-size:14px;border-bottom:1px solid #e9ecef;padding:15px;font-family:monospace;color:#495057;">{{ $dispute->stripe_dispute_id }}</td>
                    </tr>
                    <tr style="background-color:#f8f9fa;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;border-bottom:1px solid #e9ecef;padding:15px;vertical-align:top;">Amount</td>
                        <td style="font-size:18px;font-weight:700;border-bottom:1px solid #e9ecef;padding:15px;color:#dc3545;">{{ $dispute->currency }} {{ number_format($dispute->amount, 2) }}</td>
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
                    @if($dispute->evidence_due_by)
                    <tr style="background-color:#ffffff;">
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Evidence Deadline</td>
                        <td style="font-size:14px;padding:15px;color:#dc3545;font-weight:600;">
                            {{ $dispute->evidence_due_by->format('M j, Y g:i A T') }}
                            @php
                                $hoursRemaining = now()->diffInHours($dispute->evidence_due_by, false);
                            @endphp
                            <br><small style="color:#dc3545;font-weight:700;">({{ $hoursRemaining }} hours remaining)</small>
                        </td>
                    </tr>
                    @endif
                </table>
            </div>

            @if($dispute->booking)
            <div style="background-color:#f8f9fa;border-radius:12px;padding:30px;margin-bottom:30px;">
                <h3 style="margin:0 0 20px;color:#212529;font-size:18px;font-weight:600;display:flex;align-items:center;">
                    <span style="width:24px;height:24px;background-color:#28a745;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-right:10px;font-size:12px;color:white;">🎫</span>
                    Booking Information
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
                        <td class="label" style="color:#6c757d;font-size:14px;font-weight:600;padding:15px;vertical-align:top;">Tour Date</td>
                        <td style="font-size:14px;padding:15px;color:#495057;">{{ $dispute->booking->tour_date }}</td>
                    </tr>
                </table>
            </div>
            @endif

            <div style="text-align:center;margin:40px 0;">
                <a href="{{ $dispute_url }}" 
                   class="button"
                   style="display:inline-block;padding:18px 36px;background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);color:#ffffff;text-decoration:none;border-radius:8px;font-weight:700;font-size:16px;box-shadow:0 6px 12px rgba(220,53,69,0.4);text-transform:uppercase;letter-spacing:0.5px;">
                    🚨 TAKE IMMEDIATE ACTION
                </a>
            </div>

            <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);border-radius:12px;padding:25px;margin-bottom:20px;border-left:4px solid #ffc107;">
                <h4 style="margin:0 0 15px;color:#856404;font-size:16px;font-weight:700;">⚡ IMMEDIATE ACTIONS REQUIRED</h4>
                <ul style="margin:0;padding-left:20px;color:#856404;font-size:14px;line-height:1.8;">
                    <li><strong>Review dispute immediately</strong> - Time-sensitive response required</li>
                    <li><strong>Assess evidence quality</strong> - Determine if additional documentation is needed</li>
                    <li><strong>Coordinate with team</strong> - Ensure all relevant staff are informed</li>
                    <li><strong>Prepare comprehensive response</strong> - Address all dispute points thoroughly</li>
                    <li><strong>Submit before deadline</strong> - Late submissions result in automatic loss</li>
                </ul>
            </div>

            <div style="background: linear-gradient(135deg, #f8d7da 0%, #f1aeb5 100%);border-radius:12px;padding:20px;text-align:center;">
                <p style="margin:0;color:#721c24;font-size:14px;font-weight:600;">
                    🔔 This escalation has been logged and requires supervisor acknowledgment within 1 hour.
                </p>
            </div>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td class="footer" style="padding:30px;background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);border-top:1px solid #dee2e6;text-align:center;">
            <div style="margin-bottom:15px;">
                <img src="{{ asset('images/logo.png') }}" alt="Company Logo" style="height:32px;opacity:0.7;">
            </div>
            <p style="margin:0;color:#6c757d;font-size:12px;line-height:1.5;">
                <strong>URGENT ESCALATION NOTIFICATION</strong><br>
                This is a critical alert from the Dispute Management System.<br>
                Immediate supervisor attention required.
            </p>
            <div style="margin-top:15px;padding-top:15px;border-top:1px solid #dee2e6;">
                <p style="margin:0;color:#adb5bd;font-size:11px;">
                    Escalated at {{ now()->format('M j, Y g:i A T') }} | © {{ date('Y') }} {{ config('app.name') }}
                </p>
            </div>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>