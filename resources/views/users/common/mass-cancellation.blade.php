@extends('layouts.default-2')

@section('main-content')
<div class="bg-[#f0f4f8] min-h-[calc(100vh-82px)] pb-10 text-[#1e293b]">

  <div class="w-full max-w-[1600px] mx-auto px-7 pt-6">
    <h1 class="text-2xl font-bold text-[#1e293b] mb-1">Mass Cancellation</h1>
    <p class="text-sm text-[#64748b]">Select a date and time, pick the bookings to cancel and apply one refund option to all of them. Each booking is cancelled exactly like the Cancel Booking option on the booking page.</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_400px] gap-5 max-w-[1600px] mx-auto px-7 py-6 items-start">
    <div>
      <!-- Step 1: filters -->
      <div class="bg-white rounded-xl border border-[#e2e8f0] shadow-[0_1px_4px_rgba(0,0,0,0.06)] mb-5">
        <div class="px-[22px] py-4 border-b border-[#f1f5f9]">
          <h2 class="text-sm font-bold text-[#1e293b]">Step 1 — Date &amp; Time</h2>
          <p class="text-xs text-[#64748b] mt-px font-medium">Which bookings should be cancelled?</p>
        </div>
        <div class="p-[22px]">
          <div class="grid gap-[14px] grid-cols-1 md:grid-cols-3">
            <div class="flex flex-col gap-1.5">
              <label for="mcDate" class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Date</label>
              <input type="text" id="mcDateDisplay" placeholder="mm/dd/yyyy" autocomplete="off" readonly
                class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 py-[9px] text-sm h-10 w-full bg-white cursor-pointer">
              <input type="hidden" id="mcDate">
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="mcPackage" class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Tour Package</label>
              <select id="mcPackage" class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 text-sm h-10 w-full bg-white">
                <option value="" disabled selected>Please select tour package</option>
                @foreach($tourPackages as $tp)
                  <option value="{{ $tp->id }}">{{ $tp->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label for="mcTime" class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Time</label>
              <select id="mcTime" class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 text-sm h-10 w-full bg-white" disabled>
                <option value="" disabled selected>Select a date and tour package first</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 2: bookings -->
      <div class="relative bg-white rounded-xl border border-[#e2e8f0] shadow-[0_1px_4px_rgba(0,0,0,0.06)] mb-5">
        <!-- Loading overlay while bookings load or cancellation runs -->
        <div id="mcLoading" class="hidden absolute inset-0 z-10 bg-white/80 rounded-xl flex-col items-center justify-center gap-3 text-[#1e3a5c]">
          <i class="fas fa-spinner fa-spin text-3xl"></i>
          <div id="mcLoadingText" class="text-sm font-semibold">Loading...</div>
        </div>
        <div class="px-[22px] py-4 border-b border-[#f1f5f9] flex items-center justify-between gap-2">
          <div>
            <h2 class="text-sm font-bold text-[#1e293b]">Step 2 — Select Bookings</h2>
            <p class="text-xs text-[#64748b] mt-px font-medium"><span id="mcSelectedCount">0</span> of <span id="mcEligibleCount">0</span> bookings selected</p>
          </div>
        </div>
        <div class="p-[22px] overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-[11px] uppercase text-[#475569] border-b border-[#e2e8f0]">
                <th class="py-2 pr-2 w-8"><input type="checkbox" id="mcSelectAll" class="w-4 h-4" title="Select all"></th>
                <th class="py-2 pr-3">Booking</th>
                <th class="py-2 pr-3">Guest</th>
                <th class="py-2 pr-3">Package</th>
                <th class="py-2 pr-3">Time</th>
                <th class="py-2 pr-3 text-right">Pax</th>
                <th class="py-2 pr-3 text-right">Total</th>
                <th class="py-2 pr-3 text-right">Paid</th>
                <th class="py-2 pr-3">Payment</th>
                <th class="py-2">Result</th>
              </tr>
            </thead>
            <tbody id="mcBookings">
              <tr><td colspan="10" class="py-6 text-center text-[#94a3b8]">Select a date, tour package and time to load bookings</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Step 3: cancel options (same options as the Cancel Booking modal) -->
    <div class="bg-white rounded-xl border border-[#e2e8f0] shadow-[0_1px_4px_rgba(0,0,0,0.06)] lg:sticky lg:top-4">
      <div class="px-[22px] py-4 border-b border-[#f1f5f9]">
        <h2 class="text-sm font-bold text-[#1e293b]">Step 3 — Cancel Booking</h2>
        <p class="text-xs text-[#64748b] mt-px font-medium">Applied to every selected booking</p>
      </div>
      <form id="mcForm" class="p-[22px] text-sm text-[#020817]">
        @if(empty($allowedRefundTypes))
          <p class="text-[#b91c1c]">You do not have permission to use any cancellation refund method.</p>
        @else
          <div class="grid grid-cols-2 gap-3 mb-4">
            @if(in_array('full_refund', $allowedRefundTypes))
              <label class="flex items-center gap-2"><input type="radio" name="refund_type" value="full_refund" class="w-4 h-4" checked> Ticket Refund</label>
            @endif
            @if(in_array('100_refund', $allowedRefundTypes))
              <label class="flex items-center gap-2"><input type="radio" name="refund_type" value="100_refund" class="w-4 h-4" @if(!in_array('full_refund', $allowedRefundTypes)) checked @endif> Weather / 100% Refund</label>
            @endif
            @if(in_array('dont_refund', $allowedRefundTypes))
              <label class="flex items-center gap-2"><input type="radio" name="refund_type" value="dont_refund" class="w-4 h-4" @if(count($allowedRefundTypes) === 1) checked @endif> Don't Refund</label>
            @endif
          </div>

          <p class="text-xs text-[#64748b] mb-4">Each booking is refunded through its original payment method (card refunds go back to the card).</p>

          <label for="mcComment" class="block mb-1">Add a note</label>
          <textarea id="mcComment" name="comment" rows="4" maxlength="1000" class="form-custom-input w-full min-h-16 mb-4" placeholder="Please specify the reason to cancel the bookings"></textarea>

          <label class="flex items-center gap-2 mb-5 cursor-pointer"><input type="checkbox" name="send_cancel_mail" value="1" class="w-4 h-4"> Send cancellation email to guests</label>

          <button type="submit" id="mcSubmit" class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small disabled:opacity-50" disabled>Cancel Selected Bookings</button>
          <div id="mcSummary" class="mt-4 text-sm hidden"></div>
        @endif
      </form>
    </div>
  </div>
</div>
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
@endif
<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
<style>
  /* Month / year dropdowns of the date picker: the base theme's fixed 45% width
     plus the app's select padding cut the year off ("202"), so size them to their content */
  .ui-datepicker .ui-datepicker-title {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin: 0 4px;
  }
  .ui-datepicker .ui-datepicker-title select.ui-datepicker-month,
  .ui-datepicker .ui-datepicker-title select.ui-datepicker-year {
    width: auto;
    min-width: 0;
    margin: 0;
    padding: 4px 26px 4px 8px;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.4;
    color: #111827;
    background-color: #ffffff;
    background-position: right 6px center;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
  }
  .ui-datepicker .ui-datepicker-title select.ui-datepicker-year {
    min-width: 78px;
  }
</style>
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
@else
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
@endif
@endsection

@section('js-custom')
<script>
  window.MASS_CANCEL_CONFIG = {
    timeSlotsUrl: "{{ route(routePrefix() . '.mass-cancellation.time-slots') }}",
    bookingsUrl: "{{ route(routePrefix() . '.mass-cancellation.bookings') }}",
    cancelUrl: "{{ route(routePrefix() . '.mass-cancellation.cancel') }}",
    statusUrl: "{{ route(routePrefix() . '.mass-cancellation.status', ['massCancellation' => '__ID__']) }}",
    today: "{{ $today }}",
    bookingUrl: "{{ route(routePrefix() . '.booking.show', ['booking' => '__ID__']) }}",
    maxBookings: {{ \App\Services\CancelBooking\MassCancellationService::MAX_BOOKINGS_PER_REQUEST }}
  };
</script>
<script src="{{ asset('js/user-common/mass-cancellation.js') }}?v={{ @filemtime(public_path('js/user-common/mass-cancellation.js')) ?: time() }}"></script>
@endsection
