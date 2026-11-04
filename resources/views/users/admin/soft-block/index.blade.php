@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="content-title">
                        <h2 class="section-title">Mass Bookings</h2>
                    </div>

                    <div class="main-content">
                        <form id="soft-block-form">
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3">
                                    <label for="package-id" class="form-label form-custom-label">Packages <req>*</req></label>
                                    <select class="form-select form-custom-input" name="package_id" id="package-id" required aria-label="Select a package">
                                        @foreach ($packages as $index => $package)
                                        <option value="{{ $package->id }}" {{ $index === 0 ? 'selected' : '' }}>{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select a package.</div>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3">
                                    <label for="affiliate-id" class="form-label form-custom-label">Affiliates <req>*</req></label>
                                    <select class="form-select form-custom-input" name="affiliate_id" id="affiliate-id" required aria-label="Select an affiliate">
                                        <option value="">-- Select Affiliate --</option>
                                        @foreach ($affiliates as $index => $affiliate)
                                        <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select an affiliate.</div>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3">
                                    <label for="is-reserved-for-affiliate" class="form-label form-custom-label">Soft Block <req>*</req></label>
                                    <select class="form-select form-custom-input" id="is-reserved-for-affiliate" name="is_reserved_for_affiliate" required aria-label="Soft block option">
                                        <option value="1" selected>Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select an option.</div>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3">
                                    <label for="admin-email" class="form-label form-custom-label">Created By</label>
                                    <select class="form-select form-custom-input" name="admin_email" id="admin-email" aria-label="Select admin">
                                        @foreach ($admins as $index => $admin)
                                        <option value="{{ $admin->email }}" {{ $admin->email === Auth::user()->email ? 'selected' : '' }}>{{ $admin->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3 mt-3">
                                <div class="w-full max-w-full px-3">
                                    <label class="form-label form-custom-label">Time Slots</label>
                                    <div class="bg-[#F3F4F6] p-5 rounded-lg" id="time-slots-container">
                                        <!-- Initial slot -->
                                        <div class="flex flex-wrap -mx-3 items-start time-slot mb-2" data-slot-index="0">
                                            <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3">
                                                <label for="time_slots_0_date_range" class="form-label form-custom-label">Date Range <req>*</req></label>
                                                <input type="text" name="time_slots[0][date_range]" id="time_slots_0_date_range" class="form-control form-custom-input date-range-input" placeholder="Select date range" required aria-label="Date range for slot 1">
                                                <div class="invalid-feedback">Please select a valid date range.</div>
                                            </div>
                                            <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                                                <label for="time_slots_0_times" class="form-label form-custom-label">Times <req>*</req></label>
                                                <select class="form-select time_slots_times form-custom-input time-input" name="time_slots[0][times][]" id="time_slots_0_times" multiple required aria-label="Times for slot 1">
                                                    <option value="" disabled>Loading times...</option>
                                                </select>
                                                <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select at least one time.</div>
                                            </div>
                                            <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                                                <label for="time_slots_0_seats" class="form-label form-custom-label">Seats <req>*</req></label>
                                                <input type="number" name="time_slots[0][seats]" id="time_slots_0_seats" class="form-control form-custom-input seats-input" placeholder="Number of seats" required aria-label="Number of seats for slot 1">
                                                <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please enter a positive number.</div>
                                            </div>
                                            <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                                                <label for="time_slots_0_seat_preference" class="form-label form-custom-label">Seats Preference <req>*</req></label>
                                                <select class="form-select time_slots_seat_preference form-custom-input seat-preference-input" name="time_slots[0][seat_preference]" id="time_slots_0_seat_preference" required aria-label="Seats preference for slot 1">
                                                    <option value="1" selected>Available</option>
                                                    <option value="0">Fixed</option>
                                                </select>
                                                <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select an option.</div>
                                            </div>
                                            <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                                                <label for="time_slots_0_days" class="form-label form-custom-label">Days</label>
                                                <select class="form-select time_slots_seat_preference form-custom-input days-input custom-select2" name="time_slots[0][days][]" id="time_slots_0_days" multiple aria-label="Days for slot 1">
                                                    <option value="0" selected>All Days</option>
                                                    <option value="1">Monday</option>
                                                    <option value="2">Tuesday</option>
                                                    <option value="3">Wednesday</option>
                                                    <option value="4">Thursday</option>
                                                    <option value="5">Friday</option>
                                                    <option value="6">Saturday</option>
                                                    <option value="7">Sunday</option>
                                                </select>
                                            </div>
                                            <div class="w-full sm:w-1/2 lg:w-1/12 max-w-full px-3">
                                                <label for="time_slots_0_date_range" class="form-label form-custom-label">&nbsp;</label>
                                                <button type="button" class="btn btn-danger remove-slot-btn btn-danger-small" disabled>Remove</button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary mt-2 btn-primary-small" id="add-slot-btn">Add Time Slot</button>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3 mb-3">
                                <div class="w-full max-w-full px-3 flex items-center justify-end mb-2">
                                    <div class="form-check form-switch me-2">
                                        <input class="form-check-input ms-2 me-3" type="checkbox" name="update_existing_bookings" id="update_existing_bookings" value="1" style="color: lightgray; border-color: lightgray;">
                                        <label class="form-check-label form-custom-label" for="update_existing_bookings" style="color: #444;">
                                            Update Existing Bookings
                                        </label>
                                    </div>
                                </div>
                                <div class="w-full max-w-full px-3 flex justify-end">
                                    <button type="button" class="btn btn-primary me-2 mr-2 btn-info-small no-hover no-focus" id="soft-block-preview-button">Preview</button>
                                    <button type="button" class="btn btn-success me-2 btn-primary-small" id="soft-block-create-button">Save Bookings</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <div class="content-title flex justify-between items-center">
                        <h2 class="section-title">Slot Availability</h2>
                        <div class="legend flex max-[1023px]:flex-wrap">
                            <div class="max-[1023px]:w-full max-w-full flex items-center px-2">
                                <span class="badge bg-danger me-1 py-1 px-2 rounded-md bg-[#dc3545]"> </span>
                                <small class="text-sm text-[#020817]">Not Enough Seats / Booking Exists</small>
                            </div>
                            <div class="max-[1023px]:w-full max-w-full flex items-center px-2">
                                <span class="badge bg-warning me-1 py-1 px-2 rounded-md bg-[#ffc107]"> </span>
                                <small class="text-sm text-[#020817]">Seats Limited (Available Preference)</small>
                            </div>
                            <div class="max-[1023px]:w-full max-w-full flex items-center px-2">
                                <span class="badge bg-success me-1 py-1 px-2 rounded-md bg-[#198754]"> </span>
                                <small class="text-sm text-[#020817]">Seats Available</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="main-content border-y border-[#E2E8F0] py-4">
                        <div id="slot-availability-container">
                            <div class="p-4 text-sm text-[#7C706A]" id="slot-availability-accordion">
                                <p class="text-muted">Click "Preview" to view slot availability.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-title flex justify-between items-center py-4">
                        <h2 class="section-title">Affiliate Bookings</h2>
                        <div class="legend flex gap-3">
                            <button type="button" class="btn btn-warning btn-warning-small" id="soft-block-calendar-button">Calendar</button>
                        </div>
                    </div>
                    
                    <div class="main-content border-y border-[#E2E8F0] py-4">
                        <div id="affiliate-bookings-container">
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="affiliates" class="form-label form-custom-label">Select Affiliate</label>
                                    <select class="form-select form-custom-input affiliates" id="affiliates" aria-label="Select affiliate for calendar filtering" multiple>
                                        <option value="">All Affiliates</option>
                                        @foreach ($affiliates as $affiliate)
                                        <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mt-2" id="affiliate-legend" style="display: none;">
                                <h5 class="font-normal text-gray-600 text-base mb-4">Affiliate Legend</h5>
                                <div id="affiliate-legend-content" class="flex flex-wrap gap-3"></div>
                            </div>
                            <div class="max-w-full overflow-y-auto">
                                <div class="min-w-[1024px]" id="affiliate-bookings-calendar"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Bootstrap Modal for Booking Details -->
                    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="bookingDetailsModal" tabindex="-1" aria-labelledby="bookingDetailsModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                                    <h5 class="modal-title text-lg font-semibold text-[#111827]" id="bookingDetailsModalLabel">Bookings</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                                </div>
                                <div class="modal-body p-4">
                                    <div id="booking-details-accordion"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css-libraries')
<link href="https://cdn.jsdelivr.net/npm/bootstrap-daterangepicker@3.1.0/daterangepicker.css" rel="stylesheet" type="text/css" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet" />
@endsection

@section('css-custom')
<link href="{{ asset('css/admin/soft-block.css') }}" rel="stylesheet">
@endsection

@section('js-libraries')
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap-daterangepicker@3.1.0/daterangepicker.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
@endsection

@section('js-custom')
<script src="{{ asset('js/admin/soft-block.js') }}" defer></script>
<script>
    $(document).ready(() => {
        const previewSoftBlocksUrl = "{{ route(routePrefix().'.soft-block.preview-soft-blocks') }}";
        const createSoftBlocksUrl = "{{ route(routePrefix().'.soft-block.create-soft-blocks') }}";
        const fetchSoftBlocksUrl = "{{ route(routePrefix().'.soft-block.fetch-soft-blocks') }}";
        const fetchTimeSlotsUrl = "{{ route(routePrefix().'.soft-block.fetch-time-slots') }}";
        const bookingShowUrl = "{{ route(routePrefix().'.booking.show', ['booking' => 'BOOKING_ID_PLACEHOLDER']) }}";
        const softBlockBooking = new SoftBlockBooking({ previewSoftBlocksUrl, createSoftBlocksUrl, fetchSoftBlocksUrl, fetchTimeSlotsUrl, bookingShowUrl });
    });
</script>
@endsection