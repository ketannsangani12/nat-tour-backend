@extends('layouts.default-2')

@section('main-content')
    @php
        if(Route::currentRouteName() == 'admin.importview')
            $action = 'Add';
    @endphp
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="content-title">
                        <h2 class="section-title">Import Data</h2>
                    </div>

                    <div class="sub-nav flex items-center justify-end mb-8">
                        <a class="btn-warning-small" href="javascript:void(0)"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
                    </div>

                    <div class="main-content mb-3">
                        <div class="successfully-title my-3" id="successcsv"></div>
                        <form class="form" id="import-contacts" enctype="multipart/form-data" method="POST" action="{{ $formSubmitRoute }}" data-redirect-on-success="{{ route('admin.importview') }}" data-on-success-msg= @if($action == 'Add') "data added" @endif >
                            @if($action == 'Add')
                                {{csrf_field()}}
                            @endif
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-1/3 max-w-full px-3 mb-3">
                                    <label for="txt-date" class="form-label form-custom-label">Date <req>*</req></label>
                                    <input name="txt-date" id="txt-date" class="form-control form-custom-input" value="{{ $date }}" placeholder="Enter tour date" readonly required>
                                    <input type="hidden" name="date" id="hid-date" value="{{ $hiddenDate }}">
                                </div>
                                <div class="w-2/3 max-w-full px-3 mb-3">
                                    <label for="name" class="form-label form-custom-label">Import <req>*</req></label>
                                    <input type="file" class="form-control form-custom-input" id="contacts_import" name="contacts_import" accept="text/csv" required />
                                    <span class="text-sm" id="errormsgcsv" style="color:red"></span>
                                    <span class="text-sm" style="color:red" id="csverror"></span>
                                </div>
                            </div>
                            <div class="mt-3 text-end">
                                <button type="button" class="btn btn-primary btn-primary-small btn-primary btn-primary-small-small" id="importeCsvbtn"><span>Import</span></button>
                            </div>
                        </form>
                    </div>

                    <div class="main-content">
                        @if($errors->any())
                            @include('layouts._errors')
                        @endif

                        @if(session('message'))
                            @include('layouts._message')
                        @endif

                        <form class="form mb-3" id="soft-block-bookings" enctype="multipart/form-data" method="POST" action="{{ $softBlockBookingRoute }}">
                            @csrf
                            <h5 class="text-base font-medium text-[#111827]">Bulk Affiliate Soft Bookings - CREATE</h5>
                            <br>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full sm:w-1/2 md:w-1/4 max-w-full px-3 mb-3">
                                    <label for="package" class="form-label form-custom-label">Packages <req>*</req></label>
                                    <select class="form-select form-custom-input" name="package_id" required>
                                        @foreach ($packages as $package)
                                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                    <label for="affiliate" class="form-label form-custom-label">Affiliates <req>*</req></label>
                                    <select class="form-select form-custom-input" name="affiliate_id" required>
                                        @foreach ($affiliates as $affiliate)
                                            <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/4 max-w-full px-3 mb-3">
                                    <label for="date-range" class="form-label form-custom-label">Date Range <req>*</req></label>
                                    <input type="text" name="date_range" id="date_range" class="form-control form-custom-input" placeholder="Enter date range" required>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                    <label for="time" class="form-label form-custom-label">Slot Time <req>*</req></label>
                                    <input type="text" name="time" id="time" class="form-control form-custom-input" placeholder="XX:XX:00" data-inputmask-mask="99:99:00,99:99:00,99:99:00,99:99:00,99:99:00" required>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                    <label for="spots" class="form-label form-custom-label">Spots <req>*</req></label>
                                    <input type="number" name="spots" id="spots" class="form-control form-custom-input" placeholder="Number of spots" required>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3" id="days-block">
                                    <label for="days" class="form-label form-custom-label">Days </label>
                                    <select class="form-select form-custom-input select2" id="days" name="days[]" multiple>
                                        <option value="1">Monday</option>
                                        <option value="2">Tuesday</option>
                                        <option value="3">Wednesday</option>
                                        <option value="4">Thursday</option>
                                        <option value="5">Friday</option>
                                        <option value="6">Saturday</option>
                                        <option value="7">Sunday</option>
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/4 max-w-full px-3 mb-3">
                                    <label for="admin_email" class="form-label form-custom-label">Created By </label>
                                    <select class="form-select form-custom-input" name="admin_email">
                                        @foreach ($admins as $admin)
                                            <option value="{{ $admin->email }}">{{ $admin->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                    <label for="is_reserved_for_affiliate" class="form-label form-custom-label">Soft Block <req>*</req></label>
                                    <select class="form-select form-custom-input" id="is_reserved_for_affiliate" name="is_reserved_for_affiliate">
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                    <label for="seat_preference" class="form-label form-custom-label">Spots Preference <req>*</req></label>
                                    <select class="form-select form-custom-input" id="seat_preference" name="seat_preference">
                                        <option value="1">Available</option>
                                        <option value="0">Fixed</option>
                                    </select>
                                </div>
                                <!-- <div class="col-3 col-md-3 col-lg-3 mt-3">
                                    <label for="custom_script" class="form-label form-custom-label">Execute Custom Script <sub>Default "No"</sub></label>
                                    <select class="form-select form-custom-input" id="custom_script" name="custom_script">
                                        <option value="0">No</option>
                                        <option value="1">Yes</option>
                                    </select>
                                </div> -->
                            </div>
                            <div class="mt-3 text-end">
                                <button type="submit" class="btn btn-primary btn-primary-small" id="softBookingCreateButton"><span>Start</span></button>
                            </div>
                        </form>
                    
                        <form class="form" id="soft-block-bookings" enctype="multipart/form-data" method="POST" action="{{ $correctAffiliateBookingRoute }}">
                            @csrf
                            <h5 class="text-base font-medium text-[#111827]">Correct Affiliate Bookings - UPDATE</h5>
                            <div class="text-xs font-medium text-[#111827] mb-3">Add the booking ID and press submit if you wish to update a single booking and you have the ID.</div>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/4 max-w-full px-3 mb-3">
                                    <div class="card border border-[#E5E7EB] rounded-lg p-3 w-full h-full">
                                        <div class="card-body">
                                            <label for="booking_id" class="form-label form-custom-label">Booking ID</label>
                                            <input type="number" name="booking_id" id="booking_id" class="form-control form-custom-input" placeholder="Enter Booking ID">
                                            <div class="mt-4 text-end">
                                                <button type="submit" class="btn btn-primary btn-primary-small" id="softBookingButton"><span>Start</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full md:w-3/4 max-w-full px-3 mb-3">
                                    <div class="card border border-[#E5E7EB] rounded-lg p-3 w-full h-full">
                                        <div class="card-body">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="package" class="form-label form-custom-label">Packages <req>*</req></label>
                                                    <select class="form-select form-custom-input" name="package_id" required>
                                                        @foreach ($packages as $package)
                                                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="affiliate" class="form-label form-custom-label">Affiliates <req>*</req></label>
                                                    <select class="form-select form-custom-input" name="affiliate_id" required>
                                                        @foreach ($affiliates as $affiliate)
                                                            <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="soft_blocks" class="form-label form-custom-label">Soft Blocks</label>
                                                    <select class="form-select form-custom-input" name="soft_blocks">
                                                        <option value="">All</option>
                                                        <option value="1">Yes</option>
                                                        <option value="0">No</option>
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="date-range" class="form-label form-custom-label">Date Range <req>*</req></label>
                                                    <input type="text" name="date_range" id="second_date_range" class="form-control form-custom-input" placeholder="Enter date range" required>
                                                </div>
                                                <p class="w-full max-w-full px-3 mb-3 text-sm font-medium text-[#111827]"><ins>Below inputs only needed if you want to update seats of existing soft blocked bookings</ins></p>
                                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                                    <label for="time" class="form-label form-custom-label">Slot Time</label>
                                                    <input type="text" name="time" id="second_time" class="form-control form-custom-input" placeholder="XX:XX:00" data-inputmask-mask="99:99:00">
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="spots" class="form-label form-custom-label">Spots <sub>0 value mean no need to update seats</sub></label>
                                                    <input type="number" name="spots" id="second_spots" value="0" class="form-control form-custom-input" placeholder="Number of spots">
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="seat_preference" class="form-label form-custom-label">Spots Preference<sub>Field depend on spots field</sub></label>
                                                    <select class="form-select form-custom-input" id="second_seat_preference" name="seat_preference">
                                                        <option value="1">Available</option>
                                                        <option value="0">Fixed</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="mt-3 text-end">
                                                <button type="submit" class="btn btn-primary btn-primary-small" id="softBookingUpdateButton"><span>Start</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <form class="form" id="soft-block-delete-bookings" enctype="multipart/form-data" method="POST" action="{{ $deleteBookingRoute }}">
                            @csrf
                            <h5 class="text-base font-medium text-[#111827]">Remove Bookings - DELETE</h5>
                            <div class="text-xs font-medium text-[#111827] mb-3">Add the booking ID and press submit if you wish to delete a single booking and you have the ID.</div>
                            <br>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/4 max-w-full px-3 mb-3">
                                    <div class="card border border-[#E5E7EB] rounded-lg p-3 w-full h-full">
                                        <div class="card-body">
                                            <label for="booking_id" class="form-label form-custom-label">Booking ID</label>
                                            <input type="number" name="booking_id" id="delete_booking_id" class="form-control form-custom-input" placeholder="Enter Booking ID">
                                            <div class="mt-3 text-end">
                                                <button type="submit" class="btn btn-primary btn-primary-small" id="softBookingDeleteButton"><span>Start</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full md:w-3/4 max-w-full px-3 mb-3">
                                    <div class="card border border-[#E5E7EB] rounded-lg p-3 w-full h-full">
                                        <div class="card-body">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="package" class="form-label form-custom-label">Packages <req>*</req></label>
                                                    <select class="form-select form-custom-input" name="package_id" required>
                                                        @foreach ($packages as $package)
                                                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="affiliate" class="form-label form-custom-label">Affiliates <req>*</req></label>
                                                    <select class="form-select form-custom-input" name="affiliate_id" required>
                                                        @foreach ($affiliates as $affiliate)
                                                            <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="soft_blocks" class="form-label form-custom-label">Soft Blocks</label>
                                                    <select class="form-select form-custom-input" name="soft_blocks">
                                                        <option value="">All</option>
                                                        <option value="1">Yes</option>
                                                        <option value="0">No</option>
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3">
                                                    <label for="date-range" class="form-label form-custom-label">Date Range <req>*</req></label>
                                                    <input type="text" name="date_range" id="third_date_range" class="form-control form-custom-input" placeholder="Enter date range" required>
                                                </div>
                                                <p class="w-full max-w-full px-3 mb-3 text-sm font-medium text-[#111827]"><ins>Below inputs only needed if you want to delete based on specific slot time.</ins></p>
                                                <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-3" id="days-block-1">
                                                    <label for="days" class="form-label form-custom-label">Days </label>
                                                    <select class="form-select form-custom-input select2" id="days-1" name="days[]" multiple>
                                                        <option value="1">Monday</option>
                                                        <option value="2">Tuesday</option>
                                                        <option value="3">Wednesday</option>
                                                        <option value="4">Thursday</option>
                                                        <option value="5">Friday</option>
                                                        <option value="6">Saturday</option>
                                                        <option value="7">Sunday</option>
                                                    </select>
                                                </div>
                                                <div class="w-full sm:w-1/2 md:w-1/6 max-w-full px-3 mb-3">
                                                    <label for="time" class="form-label form-custom-label">Slot Time</label>
                                                    <input type="text" name="time" id="second_time" class="form-control form-custom-input" placeholder="XX:XX:00" data-inputmask-mask="99:99:00">
                                                </div>
                                            </div>
                                            <div class="mt-3 text-end">
                                                <button type="submit" class="btn btn-primary btn-primary-small" id="softBookingBulkDeleteButton"><span>Start</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <form class="form" id="duplicate-booking-form" enctype="multipart/form-data" method="POST" action="{{ $duplicateBookingRoute }}">
                            @csrf
                            <h5 class="text-base font-medium text-[#111827]">Duplicate Booking - CLONE</h5>
                            <div class="text-xs font-medium text-[#111827] mb-3">Enter the booking ID to create an exact duplicate of the booking with all related records.</div>
                            <br>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <div class="card border border-[#E5E7EB] rounded-lg p-3 w-full h-full">
                                        <div class="card-body">
                                            <label for="duplicate_booking_id" class="form-label form-custom-label">Booking ID <req>*</req></label>
                                            <input type="number" name="booking_id" id="duplicate_booking_id" class="form-control form-custom-input" placeholder="Enter Booking ID to duplicate" required>
                                            <div class="mt-3 text-end">
                                                <button type="submit" class="btn btn-success btn-primary-small" id="duplicateBookingButton"><span>Duplicate</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="w-full md:w-2/3 max-w-full px-3 mb-3">
                                    <div class="card border border-[#E5E7EB] rounded-lg p-3 w-full h-full">
                                        <div class="card-body">
                                            <h6 class="text-sm font-medium text-[#111827] mb-2">What will be duplicated:</h6>
                                            <ul class="text-xs text-[#6B7280] space-y-1">
                                                <li>• <strong>tour_bookings_master:</strong> Main booking record with new group_id</li>
                                                <li>• <strong>transactions:</strong> All payment transactions linked to the booking</li>
                                                <li>• <strong>tour_bookings_transact:</strong> All rate group transactions</li>
                                                <li>• <strong>tour_booking_history:</strong> Complete booking history</li>
                                            </ul>
                                            <div class="mt-3 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800">
                                                <strong>Note:</strong> The duplicate will have a new booking ID, new group_id, and will maintain the same reference number
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
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
    <link href="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('js-libraries')
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('js-custom')
<script type="text/javascript">
$(document).ready(function () {
    jQuery("#time").inputmask({"mask": "99:99"});
    jQuery("#second_time").inputmask({"mask": "99:99"});

    $('.select2').select2({
        placeholder: "Select a days",
        allowClear: true,
		closeOnSelect: false
    });

    $('#days-block .select2-selection, #days-block .select2-search__field').css({'min-height':'26px'});
    $('#days-block-1 .select2-selection, #days-block-1 .select2-search__field').css({'min-height':'26px'});

    $('#txt-date').datepicker({
        altField: '#hid-date',
        altFormat: 'yy-mm-dd',
        dateFormat: 'mm/dd/yy',
    });

    $('#importeCsvbtn').on('click', function () {
        var formData = new FormData(document.getElementById("import-contacts"));
        let txt_date = $('#txt-date').val();
        let contacts_import_ = $('#contacts_import').val();
        if(txt_date == "" || txt_date == null){
            $('#dateerror').text('Date field is required.');
            return false;
        }else{
            $('#dateerror').text('');
        }
        if(contacts_import_ == "" || contacts_import_ == null){
            $('#csverror').text('Import field is required.');
            return false;
        }else{
            $('#csverror').text('');
        }

        var Url = $(this).parents('form').attr('action');
        var Method = $(this).parents('form').attr('method');
        var Enctype = $(this).parents('form').attr('enctype');

        $.ajax({
            url: Url,
            type: Method,
            mimeType: Enctype,
            dataType: 'json',
            cache: false,
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.success) {
                    $('input[name="contacts_import"]').val('');
                    $("#successcsv").show();
                    $('#successcsv').html("<div class='alert alert-success alert-dismissible fade show' role='alert'>"+ response.message +"<button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button></div>");
                    $("#successcsv").delay(4000).fadeOut(300);
                    location.reload();
                }
            },
            error: function (response) {
                $('#csverror').text(response.responseJSON.errors.contacts_import);
            }
        });
    });
});

const rednderDateRangePicker = () => {
    const start = moment().subtract(31, 'days');
    const end = moment();
    const setStartAndEndDate = (start, end) => {
        $('#date_range').val(start.format('MMMM D, YYYY') + ' / ' + end.format('MMMM D, YYYY'));
    }

    $('#date_range').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, setStartAndEndDate);

    setStartAndEndDate(start, end);
}

$(document).ready(()=>{ rednderDateRangePicker();});

const rednderSecondDateRangePicker = () => {
    const start = moment().subtract(31, 'days');
    const end = moment();
    const setStartAndEndDate = (start, end) => {
        $('#second_date_range').val(start.format('MMMM D, YYYY') + ' / ' + end.format('MMMM D, YYYY'));
    }

    $('#second_date_range').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, setStartAndEndDate);

    setStartAndEndDate(start, end);
}

$(document).ready(()=>{ rednderSecondDateRangePicker();});

const rednderThirdDateRangePicker = () => {
    const start = moment().subtract(31, 'days');
    const end = moment();
    const setStartAndEndDate = (start, end) => {
        $('#third_date_range').val(start.format('MMMM D, YYYY') + ' / ' + end.format('MMMM D, YYYY'));
    }

    $('#third_date_range').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, setStartAndEndDate);

    setStartAndEndDate(start, end);
}

$(document).ready(()=>{ rednderThirdDateRangePicker();});
</script>

@endsection

