@extends('layouts.defaulmailLink')

@section('styles')
<style>
    body { background: #f3f4f6; }

    .sw-card { background: #fff; border-radius: 10px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); margin-bottom: 16px; }

    .sw-stats {
        display: flex; flex-wrap: wrap;
        border-bottom: 1px solid #f0f0f0; padding: 16px 24px; gap: 24px;
    }
    .sw-stat-item { }
    .sw-stat-label { font-size: 10px; font-weight: 600; color: #9ca3af; letter-spacing: 0.5px; text-transform: uppercase; }
    .sw-stat-value { font-size: 14px; font-weight: 600; color: #111827; margin-top: 2px; }
    .sw-status-active {
        display: inline-block; background: #d1fae5; color: #065f46;
        font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 20px;
    }

    .sw-alert-yellow {
        background: #fffbeb; border: 1px solid #fcd34d;
        border-radius: 8px; padding: 12px 16px;
        font-size: 13px; color: #92400e;
        display: flex; gap: 10px; align-items: flex-start;
    }
    .sw-alert-yellow svg { flex-shrink: 0; margin-top: 1px; }

    .sw-waiver-box {
        border: 1px solid #e5e7eb; border-radius: 8px;
        max-height: 320px; overflow-y: auto;
        padding: 20px; font-size: 13px; color: #374151;
        line-height: 1.7;
    }

    .sw-section-title {
        display: flex; align-items: center; gap: 10px;
        font-size: 15px; font-weight: 700; color: #111827;
        padding: 16px 20px; border-bottom: 1px solid #f0f0f0;
        background: #f9fafb; border-radius: 10px 10px 0 0;
    }
    .sw-section-icon {
        width: 32px; height: 32px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }
    .sw-section-icon.blue  { background: #dbeafe; color: #1d4ed8; }
    .sw-section-icon.green { background: #d1fae5; color: #065f46; }
    .sw-section-body { padding: 20px; }

    .sw-form-row { display: flex; gap: 16px; margin-bottom: 16px; }
    .sw-form-group { flex: 1; }
    .sw-form-group label {
        display: block; font-size: 12px; font-weight: 600;
        color: #374151; margin-bottom: 5px;
    }
    .sw-form-group label .req { color: #ef4444; margin-left: 2px; }
    .sw-form-group input[type="text"],
    .sw-form-group input[type="email"],
    .sw-form-group input[type="number"] {
        width: 100%; padding: 9px 12px;
        border: 1px solid #d1d5db; border-radius: 7px;
        font-size: 14px; color: #111827;
        transition: border-color .15s;
        box-sizing: border-box;
    }
    .sw-form-group input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
    .sw-hint { font-size: 11px; color: #9ca3af; margin-top: 4px; }

    label.sw-check-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 13px; color: #374151;
        margin: 0;
        cursor: pointer;
        width: 100%;
    }
    label.sw-check-item:last-of-type { border-bottom: none; }
    label.sw-check-item input[type="checkbox"] {
        flex: 0 0 16px;
        width: 16px; height: 16px;
        margin: 0;
        cursor: pointer;
        accent-color: #2563eb;
        -webkit-appearance: checkbox;
        appearance: auto;
        align-self: flex-start;
        margin-top: 8px;
    }
    label.sw-check-item span {
        flex: 1;
        line-height: 1.5;
    }

    .sw-warning-sign {
        background: #fffbeb; border: 1px solid #fcd34d;
        border-radius: 6px; padding: 10px 14px;
        font-size: 12px; color: #92400e; margin-bottom: 16px;
        display: flex; align-items: flex-start; gap: 8px;
    }

    .sw-submit-btn {
        width: 100%; padding: 13px;
        background: linear-gradient(135deg, #1a3c5e, #2563eb);
        color: #fff; font-size: 15px; font-weight: 600;
        border: none; border-radius: 8px; cursor: pointer;
        letter-spacing: 0.3px; transition: opacity .15s;
    }
    .sw-submit-btn:hover { opacity: 0.92; }
    .sw-submit-btn:disabled { opacity: 0.6; cursor: not-allowed; }

    @media (max-width: 600px) {
        .sw-form-row { flex-direction: column; gap: 0; }
        .sw-stats { gap: 14px; }
    }
</style>
@endsection

@section('content')
<div style="max-width: 780px; margin: 20px auto; padding: 0 12px;">

    {{-- BOOKING STATS --}}
    <div class="sw-card">
        <div class="sw-stats">
            <div class="sw-stat-item">
                <div class="sw-stat-label">Your Operator</div>
                <div class="sw-stat-value">{{ $bookingInfo->tour_operator }}</div>
            </div>
            <div class="sw-stat-item">
                <div class="sw-stat-label">Students Number</div>
                <div class="sw-stat-value">{{ $totalStudents }}</div>
            </div>
            <div class="sw-stat-item">
                <div class="sw-stat-label">Status</div>
                <div class="sw-stat-value">
                    <span class="sw-status-active">{{ strtoupper($bookingInfo->status == 'Booked' ? 'ACTIVE' : $bookingInfo->status) }}</span>
                </div>
            </div>
            <div class="sw-stat-item">
                <div class="sw-stat-label">Document</div>
                <div class="sw-stat-value">Liability Waiver</div>
            </div>
        </div>

        <div style="padding: 16px 24px;">
            {{-- WAIVER DOCUMENT with sticky scroll status inside --}}
            <div class="sw-waiver-box mb-0" id="waiverScrollBox">
                @if(!empty($bookingInfo->school_waiver_content))
                    {!! $bookingInfo->school_waiver_content !!}
                @elseif(!empty($bookingInfo->waiver_content))
                    {!! $bookingInfo->waiver_content !!}
                @else
                    <p><strong>PARTICIPANT WAIVER AND RELEASE OF LIABILITY</strong></p>
                    <p><strong>ASSUMPTION OF RISK &amp; INDEMNIFICATION AGREEMENT</strong></p>
                    <p>{{ $bookingInfo->tour_operator }}<br>{{ $bookingInfo->address }}, {{ $bookingInfo->city }}, {{ $bookingInfo->state }}, {{ $bookingInfo->country }}</p>
                    <p><strong>READ THIS DOCUMENT CAREFULLY BEFORE SIGNING. BY SIGNING IT, YOU ARE GIVING UP IMPORTANT LEGAL RIGHTS INCLUDING THE RIGHT TO SUE.</strong></p>
                    <h6>1. DESCRIPTION OF ACTIVITY</h6>
                    <p>The Activity involves participating in the tour operated by the Company. I am voluntarily participating in the activities and I am participating entirely at my own risk.</p>
                    <h6>2. ACKNOWLEDGEMENT AND ASSUMPTION OF RISKS</h6>
                    <p>I understand that participation involves risks including but not limited to physical injury, illness, or death. I voluntarily assume all risks, known and unknown, associated with the activity.</p>
                    <h6>3. RELEASE OF LIABILITY</h6>
                    <p>I hereby release, waive, and discharge the Company and its officers, directors, employees, agents, and assigns from any and all liability, claims, demands, actions, and causes of action arising out of my participation in the activity.</p>
                    <h6>4. INDEMNIFICATION</h6>
                    <p>I agree to indemnify, defend, and hold harmless the Company from any and all claims, damages, losses, and expenses arising out of or resulting from my participation in the activity.</p>
                    <h6>8. PARENT/GUARDIAN AUTHORIZATION</h6>
                    <p>As the parent or legal guardian of the minor student, I agree to the terms set forth in this Agreement and authorize my minor's participation in the Activities. I fully understand that my minor's participation involves a risk of serious injury or death, and I fully assume all risks associated with my child's participation.</p>
                    <p><em>By submitting this form, you confirm you have read and agree to the above waiver in its entirety.</em></p>
                @endif

                {{-- Sticky status bar — sticks to bottom of scroll box --}}
                <div id="waiverScrollStatus" style="
                    position: sticky; bottom: -20px;
                    margin: 12px -20px -20px;
                    padding: 10px 20px;
                    font-size: 13px;
                    display: flex; align-items: center; gap: 8px;
                    background: #fffbeb; border-top: 1px solid #fcd34d; color: #92400e;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" id="waiverScrollIcon" style="flex-shrink:0;">
                        <path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span id="waiverScrollMsg">Please scroll to the bottom of the waiver to enable the agreement checkboxes.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- SUBMITTED STUDENTS TABLE --}}
    @if($submittedCount > 0)
    <div class="sw-card">
        <div class="sw-section-title" style="justify-content: space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div class="sw-section-icon blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <div>
                    <div>Submitted Student Waivers</div>
                    <div style="font-size:12px;font-weight:400;color:#6b7280;">{{ $submittedCount }} waiver(s) received for this booking</div>
                </div>
            </div>
            <span style="background:#d1fae5;color:#065f46;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;letter-spacing:0.5px;">{{ $submittedCount }} SIGNED</span>
        </div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="border-bottom:1px solid #f0f0f0;">
                        <th style="padding:10px 20px;color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;text-align:left;width:40px;">#</th>
                        <th style="padding:10px 16px;color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Student Name</th>
                        <th style="padding:10px 16px;color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Age</th>
                        <th style="padding:10px 16px;color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;text-align:left;">Submitted</th>
                        <th style="padding:10px 20px;color:#9ca3af;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;text-align:right;">Ref</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schoolEntries as $i => $entry)
                    <tr style="border-bottom:1px solid #f9fafb;">
                        <td style="padding:14px 20px;color:#9ca3af;font-size:13px;">{{ $i + 1 }}</td>
                        <td style="padding:14px 16px;color:#111827;font-weight:600;">{{ $entry[0] }}</td>
                        <td style="padding:14px 16px;color:#374151;">{{ $entry[1] }}</td>
                        <td style="padding:14px 16px;color:#374151;">
                            {{ date('d M Y g:i A', strtotime($entry[2])) }}<br>
                            <span style="font-size:11px;color:#9ca3af;">{{ \Carbon\Carbon::parse($entry[2])->diffForHumans() }}</span>
                        </td>
                        <td style="padding:14px 20px;text-align:right;">
                            @if(isset($entry[4]))
                                <span style="font-family:monospace;font-size:12px;color:#6b7280;background:#f3f4f6;padding:2px 8px;border-radius:4px;">{{ $entry[4] }}</span>
                            @else
                                <span style="font-size:12px;color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    @if($isFull)
    <div class="sw-card" style="padding: 32px 24px; text-align: center;">
        <div style="width:56px;height:56px;border-radius:50%;background:#fef3c7;border:2px solid #fcd34d;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </div>
        <div style="font-size:17px;font-weight:700;color:#111827;margin-bottom:8px;">All Waivers Submitted</div>
        <p style="font-size:13px;color:#6b7280;margin-bottom:0;">
            All {{ $totalStudents }} student waiver{{ $totalStudents == 1 ? '' : 's' }} for this booking have already been submitted.
            No further entries can be added.
        </p>
    </div>
    @else
    <form method="POST" action="{{ route('customer.school-waiver-submission', ['bookingId' => $encBookingId]) }}">
        @csrf

        @if($errors->any())
        <div class="sw-card" style="border-left: 4px solid #ef4444; padding: 14px 20px; margin-bottom: 16px;">
            <ul class="mb-0" style="font-size: 13px; color: #ef4444;">
                @foreach($errors->keys() as $key)
                    @if(!in_array($key, ['agree1', 'agree2', 'agree3']))
                        <li>{{ $errors->first($key) }}</li>
                    @endif
                @endforeach
                @if($errors->has('agree1') || $errors->has('agree2') || $errors->has('agree3'))
                    <li>Please confirm all agreement checkboxes before submitting.</li>
                @endif
            </ul>
        </div>
        @endif

        {{-- STUDENT INFORMATION --}}
        <div class="sw-card">
            <div class="sw-section-title">
                <div class="sw-section-icon blue">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <div>Student Information</div>
                    <div style="font-size: 12px; font-weight: 400; color: #6b7280;">Enter the participating student's details</div>
                </div>
            </div>
            <div class="sw-section-body">
                <div class="sw-form-row">
                    <div class="sw-form-group">
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="First name" required>
                    </div>
                    <div class="sw-form-group">
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Last name" required>
                    </div>
                </div>
                <div class="sw-form-row">
                    <div class="sw-form-group">
                        <label>Student Age <span class="req">*</span></label>
                        <input type="number" name="age" value="{{ old('age') }}" placeholder="e.g. 10" min="1" max="{{ $ageMax }}" required>
                        <div class="sw-hint">Must be between 1 and {{ $ageMax }} years old</div>
                    </div>
                    <div class="sw-form-group">
                        <label>Parent / Guardian Email</label>
                        <input type="email" name="parent_email" value="{{ old('parent_email') }}" placeholder="parent@example.com">
                        <div class="sw-hint">Optional — a confirmation will be noted if provided</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- AGREEMENT & SIGNATURE --}}
        <div class="sw-card">
            <div class="sw-section-title">
                <div class="sw-section-icon green">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div>
                    <div>Agreement &amp; Signature</div>
                    <div style="font-size: 12px; font-weight: 400; color: #6b7280;">Please review and confirm all statements below</div>
                </div>
            </div>
            <div class="sw-section-body">
                <label class="sw-check-item" for="agree1" id="lbl-agree1" style="opacity:.5;">
                    <input type="checkbox" name="agree1" id="agree1" required disabled>
                    <span>I have read and understood the full Participant Waiver and Release of Liability. I agree to all terms and conditions set out in the above document and acknowledge that I am giving up important legal rights.</span>
                </label>
                <label class="sw-check-item" for="agree2" id="lbl-agree2" style="opacity:.5;">
                    <input type="checkbox" name="agree2" id="agree2" required disabled>
                    <span>As parent / guardian of the student named above, I authorise their participation in this activity and agree to this waiver on their behalf. I confirm that all information entered is accurate and complete.</span>
                </label>
                <label class="sw-check-item" for="agree3" id="lbl-agree3" style="opacity:.5;">
                    <input type="checkbox" name="agree3" id="agree3" required disabled>
                    <span>I confirm the student's age and health declaration are accurate. The student has no medical conditions that would prevent safe participation in the activity described in this waiver.</span>
                </label>

                <div class="sw-warning-sign mt-3">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>By clicking <strong>Submit Signed Waiver</strong> you are electronically signing this legal document. This constitutes a valid and binding signature.</span>
                </div>

                <button type="submit" class="sw-submit-btn" id="swSubmitBtn">
                    → &nbsp;Submit Signed Waiver
                </button>
            </div>
        </div>

    </form>
    @endif
</div>
@endsection

@section('scripts')
<script>
(function () {
    var box       = document.getElementById('waiverScrollBox');
    var status    = document.getElementById('waiverScrollStatus');
    var icon      = document.getElementById('waiverScrollIcon');
    var msg       = document.getElementById('waiverScrollMsg');
    var checks    = document.querySelectorAll('#agree1, #agree2, #agree3');
    var submitBtn = document.getElementById('swSubmitBtn');
    var unlocked  = false;

    function checkScrolled() {
        // Consider "read" when within 30px of the bottom
        if (!unlocked && box.scrollTop + box.clientHeight >= box.scrollHeight - 30) {
            unlocked = true;

            // Update banner to green
            status.style.background  = '#f0fdf4';
            status.style.borderTop   = '1px solid #86efac';
            status.style.color       = '#166534';
            icon.innerHTML = '<path d="M22 11.08V12a10 10 0 11-5.93-9.14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M22 4L12 14.01l-3-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
            msg.textContent = 'You have read the full waiver.';

            // Enable checkboxes + labels
            checks.forEach(function (cb) {
                cb.disabled = false;
                var lbl = cb.closest('label');
                if (lbl) lbl.style.opacity = '1';
            });
        }
    }

    // Handle case where waiver content is short enough to not need scrolling
    function checkIfScrollable() {
        if (box.scrollHeight <= box.clientHeight) {
            // Content fits without scroll — unlock immediately
            box.dispatchEvent(new Event('scroll'));
        }
    }

    box.addEventListener('scroll', checkScrolled);
    // Run once on load in case content is short
    window.addEventListener('load', checkIfScrollable);

    document.getElementById('swSubmitBtn').addEventListener('click', function () {
        this.disabled = true;
        this.textContent = 'Submitting…';
        this.closest('form').submit();
    });
})();
</script>
@endsection
