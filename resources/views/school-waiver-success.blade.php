@extends('layouts.defaulmailLink')

@section('styles')
<style>
    body { background: #f3f4f6; }

    .success-card {
        background: #fff; border-radius: 12px;
        box-shadow: 0 1px 6px rgba(0,0,0,0.08);
        max-width: 600px; margin: 32px auto;
        padding: 40px 36px; text-align: center;
    }
    .success-icon {
        width: 72px; height: 72px; border-radius: 50%;
        background: #d1fae5; border: 3px solid #6ee7b7;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 20px;
    }
    .success-icon svg { color: #059669; }

    .success-title { font-size: 22px; font-weight: 700; color: #111827; margin-bottom: 8px; }
    .success-sub { font-size: 14px; color: #6b7280; margin-bottom: 28px; }
    .success-sub strong { color: #111827; }

    .detail-table { width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 24px; }
    .detail-table td { padding: 10px 0; font-size: 13px; border-bottom: 1px solid #f3f4f6; }
    .detail-table td:first-child { color: #6b7280; width: 48%; }
    .detail-table td:last-child { color: #111827; font-weight: 500; text-align: right; }

    .info-box {
        border-radius: 8px; padding: 14px 16px;
        font-size: 13px; text-align: left; margin-bottom: 12px;
        display: flex; gap: 10px; align-items: flex-start;
    }
    .info-box.green { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
    .info-box.blue  { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
    .info-box svg { flex-shrink: 0; margin-top: 1px; }

    .sign-another-btn {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 11px 24px;
        border: 2px solid #2563eb; border-radius: 8px;
        color: #2563eb; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-top: 8px;
        transition: background .15s, color .15s;
    }
    .sign-another-btn:hover { background: #2563eb; color: #fff; }

    .ref-code {
        font-family: monospace; font-size: 13px;
        background: #f3f4f6; padding: 2px 8px; border-radius: 4px;
        color: #111827;
    }
</style>
@endsection

@section('content')
<div style="padding: 0 12px;">
    <div class="success-card">

        {{-- CHECK ICON --}}
        <div class="success-icon">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>

        <div class="success-title">Waiver Signed Successfully!</div>
        <p class="success-sub">
            Thank you, <strong>{{ $firstName }} {{ $lastName }}</strong>! The waiver has been submitted and recorded.
        </p>

        {{-- DETAIL TABLE --}}
        <table class="detail-table">
            <tr>
                <td>Student Name</td>
                <td>{{ $firstName }} {{ $lastName }}</td>
            </tr>
            <tr>
                <td>Tour</td>
                <td>{{ $booking->tour_package }}</td>
            </tr>
            <tr>
                <td>Tour Date</td>
                <td>{{ date('l, F j, Y', strtotime($booking->tour_date)) }}</td>
            </tr>
            <tr>
                <td>Booking ID</td>
                <td>{{ bookingPrefix($booking->booking_id) }}</td>
            </tr>
            @if(!empty($parentEmail))
            <tr>
                <td>Confirmation Sent To</td>
                <td><a href="mailto:{{ $parentEmail }}" style="color:#2563eb;">{{ $parentEmail }}</a></td>
            </tr>
            @endif
            <tr>
                <td>Reference ID</td>
                <td><span class="ref-code">{{ $referenceId }}</span></td>
            </tr>
            <tr>
                <td>Submitted At</td>
                <td>{{ date('D, d M Y H:i', strtotime($submittedAt)) }} UTC</td>
            </tr>
            <tr>
                <td>Document</td>
                <td>Participant Waiver &amp; Release of Liability</td>
            </tr>
        </table>

        {{-- INFO BOXES --}}
        <div class="info-box green">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L12 14.01l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>A confirmation copy of this waiver has been recorded. Please keep your reference ID <strong>{{ $referenceId }}</strong> for your records.</span>
        </div>

        <div class="info-box blue">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4m0-4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
            <div>
                <strong>What happens next?</strong><br>
                Your teacher or tour operator will confirm your booking. Please arrive at the meeting point at least 15 minutes before your scheduled tour time. Wear appropriate clothing and footwear.
            </div>
        </div>

        {{-- SIGN ANOTHER --}}
        <div style="border-top: 1px solid #f3f4f6; margin-top: 20px; padding-top: 20px;">
            <p style="font-size: 13px; color: #6b7280; margin-bottom: 12px;">Does another student need to sign the waiver for the same booking?</p>
            <a href="{{ route('customer.school-waiver-form', ['bookingId' => $encBookingId]) }}" class="sign-another-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"><path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Sign Another Student Waiver
            </a>
        </div>

    </div>
</div>
@endsection
