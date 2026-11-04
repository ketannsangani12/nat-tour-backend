@extends('layouts.default-2')

@php
$uTypeAdmin = session('userType') == 'Admin';
$routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
$agendaGuideCount = hasSetting('AGENDA_GUIDE_COUNT');
@endphp

@section('main-content')

@if($errors->any())
@include('layouts._errors')
@endif

@if(session('message'))
@include('layouts._message')
@endif
<style>
	.select2-container--default .select2-selection--single {
		padding: 5px 15px 5px 7px;
	}

	.select2-container--default .select2-selection--single .select2-selection__arrow b,
	.select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
		margin: 0 0 0 -18px;
	}

	#checkinToggle:focus {
		outline: none;
		box-shadow: 0 0 0 1px #000;
	}

	#checkinMenu input[type="checkbox"] {
		accent-color: #111827;
	}
	.inner-table-full-width table{
		width: 100% !important;
	}
</style>
<div class="day-time-slots">
	<section class="py-6 bg-white border-b border-[#E5E7EB] sticky top-[81px] z-[18]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full md:w-2/3 lg:w-3/4 min-[1351px]:w-[79%] max-w-full text-center px-3">
					<div class="flex flex-wrap -mx-3">
						<div class="w-full lg:w-2/5 max-w-full px-3 max-[1023px]:mb-2">
							<div class="date-select relative">
								<input type="text" id="date" name="date" class="relative w-full px-5 py-2 text-lg max-[1350px]:text-base font-medium text-[#111827] shadow-none leading-none outline-none text-center">
								<button type="button" class="prev-day datepicker-btn absolute top-[calc(50%-10px)] w-5 h-5 text-center left-0" data-incr-decr="-1"><svg class="mx-auto" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M6.5 11L1.5 6L6.5 1" stroke="#9CA3AF" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
									</svg></button>
								<button type="button" class="next-day datepicker-btn absolute top-[calc(50%-10px)] w-5 h-5 text-center right-0" data-incr-decr="1"><svg class="mx-auto" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M1.5 11L6.5 6L1.5 1" stroke="#9CA3AF" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round" />
									</svg></button>
							</div>
						</div>
						<div class="w-full sm:w-1/3 lg:w-1/5 max-w-full px-3 max-[639px]:mb-2">
							@if ($agendaView == 1)
							@if ($packageHasSlots)
							<div class="date-select-box">
								<select id="time-slots" class="select-tour-time form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto" onchange="filterSlots(this)">
									<option value="">Show All Time</option>
								</select>
							</div>
							@endif
							@else
							<div class="date-select-box">
								<select id="booking-hotels" class="select-tour-time form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto" onchange="filterSlots(this)">
									<option value="">Show All Hotels</option>
								</select>
							</div>
							@endif
							<input type="hidden" id="tour_operator" value="{{ $tourOperatorId }}">
						</div>
						<div class="w-full sm:w-1/3 lg:w-1/5 max-w-full px-3 max-[639px]:mb-2">
							@if(isset($tourPackages))
							<div class="date-select-box">
								<select name="tour_package" id="tour_package" class="form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto">
									<option value="0">Show All Package</option>
									@foreach($tourPackages as $tp)
									<option value="{{ $tp->id }}" @if($tp->id == $tourPackagesId) selected @endif >{{ $tp->name }}</option>
									@endforeach
								</select>
							</div>
							@endif
						</div>
						<div class="w-full sm:w-1/3 lg:w-1/5 max-w-full px-3">
							@if(isset($paymentStatus))
							<div>
								<select name="payment_status" id="payment_status" class="form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto">
									<option selected value="" {{($paymentStatus === 0) ? 'selected' : ''}}>Select Payment Status</option>
									<option value="2" {{($paymentStatus === "Completed") ? 'selected' : ''}}>Paid</option>
									<option value="3" {{($paymentStatus === "Failed") ? 'selected' : ''}}>Unpaid</option>
								</select>
							</div>
							@endif
						</div>
					</div>
				</div>
				<div class="w-full md:w-1/3 lg:w-1/4 min-[1351px]:w-[21%] max-w-full text-center px-3">
					<div class="flex items-center w-full max-[767px]:justify-center max-[767px]:mt-3">
						<div class="seachbybookingId">
							<input class="w-full m-0 border border-[#E5E7EB] bg-[#FAFAFA] rounded-lg px-3 py-[9px] text-sm text-[#000] shadow-none outline-none" type="text" id="booking-id" name="" placeholder="Search Booking / Name / Email">
						</div>
						<div style="margin-left:10px">
							<button id="reset" class="btn btn-main btn-main-sm themed-btn short-btn text-white text-sm bg-[#ED9C45] border border-[#ED9C45] hover:border-[#4B5563] hover:bg-white font-medium px-4 py-[9px] hover:text-[#4B5563] shadow-none transition-all duration-300 ease-in-out rounded-[7px]">
								Reset
							</button>
						</div>
						</
							</div>
					</div>
				</div>
	</section>

	<!-- <section class="select-tour-sec">
		<div class="row">
			<div class="col-12 col-md-12 col-lg-12">
				<div class="date-select-wrp d-flex">
					<div class="date-select">
						<input type="text" id="date" name="date">
						<button type="button" class="prev-day datepicker-btn" data-incr-decr="-1"></button>
						<button type="button" class="next-day datepicker-btn" data-incr-decr="1"></button>
					</div>
					@if ($agendaView == 1)
						@if ($packageHasSlots)
							<div class="date-select-box">
								<select id="time-slots" class="select-tour-time form-select" onchange="filterSlots(this)">
									<option value="">Show All Time</option>
								</select>
							</div>
						@endif
					@else
						<div class="date-select-box">
							<select id="booking-hotels" class="select-tour-time form-select" onchange="filterSlots(this)">
								<option value="">Show All Hotels</option>
							</select>
						</div>
					@endif
					<input type="hidden" id="tour_operator" value="{{ $tourOperatorId }}">
					@if(isset($tourPackages))
						<div style="margin-left:10px" class="date-select-box">
							<select name="tour_package" id="tour_package" class="form-select">
								<option value="0">Show All Package</option>
							@foreach($tourPackages as $tp)
								<option value="{{ $tp->id }}" @if($tp->id == $tourPackagesId) selected @endif >{{ $tp->name }}</option>
							@endforeach
							</select>
						</div>
					@endif
					@if(isset($paymentStatus))
						<div style="margin-left:10px">
							<select name="payment_status" id="payment_status" class="form-select">
								<option selected value="" {{($paymentStatus === 0) ? 'selected' : ''}}>Select Payment Status</option>
								<option value="2" {{($paymentStatus === "Completed") ? 'selected' : ''}}>Paid</option>
								<option value="3" {{($paymentStatus === "Failed") ? 'selected' : ''}}>Unpaid</option>
							</select>
						</div>
					@endif
				</div>
			</div>
		</div>
	</section> -->

	<!-- <section class="select-tour-sec">
		<div class="row">
			<div class="col-12 col-md-12 col-lg-12">
				<div class="date-select-wrp d-flex">
					<div class="seachbybookingId">
						<input type="text" id="booking-id" name="" placeholder="Seach by booking Id">
					</div>
					<div style="margin-left:10px">
						<button id="reset" class="btn btn-main btn-main-sm themed-btn short-btn" style="height: 38px">
							Reset
						</button>
					</div>
				</div>
			</div>
		</div>
	</section> -->

	@php
	// Base number of boxes
	$boxCount = 6;

	// Add more depending on conditions
	if ($redeemTicketFlag > 0) {
	$boxCount += 2;
	}

	// Optional: add more conditions
	if (count($dayBookings) > 0) {
	$boxCount += 1;
	}

	// Calculate width per box
	$width = 100 / $boxCount;
	@endphp

	<section class="tour-detail-sec pt-6 pb-3 max-[1024px]:pb-3 bg-[#F9FAFB]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid bg-white rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Total Tourists</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="all_tourists_count">0</span></div>
					</div>
				</div>
				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid bg-white rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Total Bookings</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="all_bookings_count">0</span></div>
					</div>
				</div>
				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#ffc107] bg-orange-50 rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Not Checked In</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="not_checked_in">0</span></div>
					</div>
				</div>

				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#198754] bg-[#19875430] rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">1st Check Ins</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="first_check_ins">0</span></div>
					</div>
				</div>

				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#b6effb] bg-[#0dcaf020] rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">2nd Check Ins</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="second_check_ins">0</span></div>
					</div>
				</div>

				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#f5c2c7] bg-[#dc354520] rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Absent</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="absent">0</span></div>
					</div>
				</div>

				@if($redeemTicketFlag > 0)
				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid bg-white rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Redeemed Tickets</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="redeemed_tickets">0</span></div>
					</div>
				</div>
				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid bg-white rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Not Redeemed Tickets</div>
						<div class="font-bold text-[#264f73] text-xl text-center"><span id="not_redeemed_tickets">0</span></div>
					</div>
				</div>
				@endif

				@if(count($dayBookings) > 0)
				<div class="w-1/2 md:w-2/6 lg:w-1/6 xl:w-[{{ $width }}%] max-w-full mb-3 text-center px-3">
					<div class="flex -mx-1 h-full">
						@if($tourPackagesId && $packageHasContacts)
						<div class="px-1 h-full">
							<button class="btn btn-main btn-main-sm btn-theme short-btn btn-csv-download w-full h-full flex flex-col items-center justify-center py-4 px-3 border border-solid bg-[#60BEBD] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] text-white rounded-lg shadow-none text-center transition-all" id="roster_report_csv_download">
								<i class="fa fa-file-csv"></i>
							</button>
						</div>
						<div class="px-1 h-full">
							<button class="btn btn-main btn-main-sm btn-theme short-btn btn-pdf-download w-full h-full flex flex-col items-center justify-center py-4 px-3 border border-solid bg-[#60BEBD] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] text-white rounded-lg shadow-none text-center transition-all" id="roster_report_pdf_download">
								<i class="fa fa-file-pdf"></i>
							</button>
						</div>
						@endif
						<div class="px-1 h-full">
							<button class="btn btn-main btn-main-sm btn-theme short-btn w-full h-full flex flex-col items-center justify-center py-4 px-3 border border-solid bg-[#60BEBD] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] text-white rounded-lg shadow-none text-center transition-all" id="printAgenda">
								<svg class="hover:text-[#4B5563] transition-colors duration-200 max-w-4" width="29" height="29" viewBox="0 0 29 29" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M3.19024 19.7202C3.99104 19.7202 4.64024 20.3694 4.64024 21.1702V24.0702C4.64024 24.4548 4.79298 24.8235 5.06496 25.0955C5.33686 25.3674 5.70566 25.5202 6.09024 25.5202H23.4902C23.8748 25.5202 24.2435 25.3675 24.5155 25.0955C24.7875 24.8235 24.9402 24.4548 24.9402 24.0702V21.1702C24.9402 20.3694 25.5894 19.7202 26.3902 19.7202C27.1911 19.7202 27.8402 20.3694 27.8402 21.1702V24.0702C27.8402 25.2238 27.382 26.3303 26.5661 27.1461C25.7503 27.9619 24.6439 28.4202 23.4902 28.4202H6.09024C4.93657 28.4202 3.83009 27.962 3.01429 27.1461C2.19855 26.3303 1.74023 25.2238 1.74023 24.0702V21.1702C1.74023 20.3694 2.38941 19.7202 3.19024 19.7202Z"></path>
									<path fill-rule="evenodd" clip-rule="evenodd" d="M6.23865 10.901C6.82379 10.2862 7.77248 10.2862 8.35762 10.901L14.7898 17.6593L21.2219 10.901C21.807 10.2862 22.7558 10.2862 23.3409 10.901C23.9261 11.5158 23.9261 12.5126 23.3409 13.1274L15.8492 20.9989C15.2641 21.6136 14.3154 21.6136 13.7303 20.9989L6.23865 13.1274C5.65352 12.5126 5.65352 11.5158 6.23865 10.901Z"></path>
									<path fill-rule="evenodd" clip-rule="evenodd" d="M14.7898 0.580078C15.5907 0.580078 16.2398 1.24782 16.2398 2.07151V19.9686C16.2398 20.7924 15.5907 21.4601 14.7898 21.4601C13.989 21.4601 13.3398 20.7924 13.3398 19.9686V2.07151C13.3398 1.24782 13.989 0.580078 14.7898 0.580078Z"></path>
								</svg>
							</button>
						</div>
						@if(tenant()->id !== 'gcw' && $agendaCheckinOverview)
						@can('agenda-checkin-overview')
						<div class="px-1 h-full">
							<button class="btn btn-main btn-main-sm btn-theme short-btn w-full h-full flex flex-col items-center justify-center py-4 px-3 border border-solid bg-[#60BEBD] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] text-white rounded-lg shadow-none text-center transition-all" id="agendaCheckinOverview">
								<svg class="hover:text-[#4B5563] transition-colors duration-200 max-w-4" width="27" height="27" viewBox="0 0 27 27" fill="none" xmlns="http://www.w3.org/2000/svg">
									<rect x="4.5" y="4.5" width="20" height="20" rx="5" stroke="currentColor" stroke-width="2.5" />
									<path d="M10 14.5L13.2 17.7L19 11.8" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
								</svg>
							</button>
						</div>
						@endcan
						@endif
					</div>
				</div>
				@endif
			</div>
		</div>
	</section>

	<section class="bg-[#F9FAFB] min-h-[calc(100vh-295px)]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full max-w-full text-center px-3">
					<!-- <div class="flex flex-col gap-[30px] p-4 sm:px-[20px] sm:py-[30px] border rounded-xl bg-white"> -->
					@php
					$slots = [];
					$tourPackageId = 0;
					$slotId = 0;
					$sltId = 0;
					$bookedSeats = 0;
					$seatsInfo = [];
					$assetPath = asset('');
					$dayBookingsId = [];
					$totalBookingsCount = count($dayBookings);
					@endphp

					@for($i = 0, $j = -1, $ic = count($dayBookings); $i < $ic; $i++)
						@php
						$dayBookingsId[]=[ 'id'=> $dayBookings[$i]->booking_id,
						];
						$agendaViewId = $agendaView == 1 ? 'tour_slot_id' : 'hotel_id';
						@endphp

						@if($dayBookings[$i]->tour_package_id != $tourPackageId || $dayBookings[$i]->$agendaViewId != $slotId)
						@php
						$tourPackageId = $dayBookings[$i]->tour_package_id;
						$slotId = $dayBookings[$i]->$agendaViewId;
						$packageHasWaiver = $dayBookings[$i]->package_has_waiver;

						if ($agendaView == 1) {
						$slots[] = [
						'id' => $slotId,
						'tFrom' => $dayBookings[$i]->time_from,
						'tTo' => $dayBookings[$i]->time_to,
						'packageHasSlots' => $dayBookings[$i]->package_has_slots,
						'packageHasWaiver' => $dayBookings[$i]->package_has_waiver,
						];
						} else {
						if ($slotId==0){
						$sltId = uniqid();
						$slots[] = [
						'id' => $sltId,
						'hotel_name' => $dayBookings[$i]->hotel_name,
						];
						} else {
						$slots[] = [
						'id' => $slotId,
						'hotel_name' => $dayBookings[$i]->hotel_name,
						];
						}
						}

						$seatsInfo[++$j] = [
						'slotId' => $slotId,
						'seatsCount' => $dayBookings[$i]->seats,
						'bookingsCount' => 0,
						'touristsCount' => 0,
						'infantsCount' => 0,
						'guidesCount' => 0,
						'uniqueGuides' => [],
						'checkInsCount' => [
						'1' => 0, // 1st check in
						'2' => 0, // 2nd check in
						'A' => 0, // absent
						],
						'redeemTicketsCount' => [
						'redeemed' => 0, // Inactive
						'not_redeemed' => 0 // Active
						],
						'waiverSignsCount' => 0,
						'ageGroupWiseTouristsCount' => [],
						'totalWaiver' => 0,
						'totalNotSigned' => 0,
						'aflTourists' => 0,
						'aflSeats' => 0,
						'aflRemainingSeats' => 0,
						'aflTotalTourists' => 0,
						'checkInType' => 0
						];
						@endphp

						@if ($agendaView != 1 && $slotId == 0)
						<div id="slot-{{ $sltId }}" data-time="{{ $agendaView == 1 ? $dayBookings[$i]->time_from . '-' . $dayBookings[$i]->time_to : $sltId }}" class="div-slot flex flex-col max-[1350px]:p-4 px-[20px] py-[30px] border rounded-xl bg-white mb-14">
							@else
							<div id="slot-{{ $slotId }}" data-time="{{ $agendaView == 1 ? $dayBookings[$i]->time_from . '-' . $dayBookings[$i]->time_to : $slotId }}" class="div-slot flex flex-col max-[1350px]:p-4 px-[20px] py-[30px] border rounded-xl bg-white mb-14">
								@endif

								<!-- <hr> -->

								<div class="flex flex-col justify-center md:flex-row md:items-center md:justify-between gap-2">
									<div class="flex flex-col lg:flex-row lg:items-center gap-2 lg:gap-[30px]">
										<h5 class="font-normal text-gray-600 text-2xl max-[1350px]:text-base block lg:max-w-[55%] md:text-left">{{ $dayBookings[$i]->tour_package_name }}:
											@if ($agendaView == 1)
											@if ($dayBookings[$i]->package_has_slots)
											<div class="tour-time font-bold text-gray-900 inline-block ml-1">
												@ <span class="locale-time">{{ $dayBookings[$i]->time_from }}</span>
												- <span class="locale-time">{{ $dayBookings[$i]->time_to }}</span>
											</div>
											@endif
											@else
											<div class="tour-time font-bold text-gray-900 inline-block ml-1">
												@ <span class="locale-time">{{ $dayBookings[$i]->hotel_name ?? 'Custom Pickup' }}</span>
											</div>
											@endif
										</h5>
										<div class="flex max-[1023px]:flex-wrap items-center max-[767px]:justify-center gap-1">
											<span class="flex items-center justify-center gap-2 py-1 px-2 max-[1350px]:px-[13px] max-[1350px]:py-1.5 bg-[#264F73] rounded-[5px] font-semibold text-white text-base max-[1350px]:text-xs">
												<div class="tour-detail-item primary-bg-color primary-border-color text-white-color">
													<div class="tour-detail-process flex items-center text-white whitespace-nowrap">
														<img class="mr-2" src="{{ $assetPath }}images/booking.png" alt="book"> <span class="mr-1" id="tourists-{{ $slotId }}">0</span> Bookings
														@if($dayBookings[$i]->package_has_slots)
														(<span class="mr-1 whitespace-nowrap" id="empty-{{ $slotId }}"></span> of <span class="mx-1" id="total-seats-{{ $slotId }}">{{ $dayBookings[$i]->seats }}</span> Available)
														@endif
													</div>
													<div class="tour-process-wrp">
														<div class="tour-process primary-border-color" id="progress-seats-{{ $slotId }}" style="width:0"></div>
													</div>
												</div>
											</span>

											@if ($packageHasWaiver && $dayBookings[$i]->is_check_in == 1)
											<span class="flex items-center justify-center gap-2 py-1 px-2 max-[1350px]:px-[13px] max-[1350px]:py-1.5 bg-green-600 rounded-[5px] font-semibold text-white text-base max-[1350px]:text-xs">
												<div class="tour-detail-item success-bg-color success-text-color success-bg-color success-border-color">
													<div class="tour-detail-process flex items-center text-white">
														@if($dayBookings[$i]->checkin_type == 1)
														<img class="mr-1" src="{{ $assetPath }}images/checked-white.png" alt="check"><span class="whitespace-nowrap" id="checked-in-{{ $slotId }}">0 / 0</span> <span class="whitespace-nowrap ml-1">Tickets Redeemed</span>
														@else
														<img class="mr-1" src="{{ $assetPath }}images/checked-white.png" alt="check"><span class="whitespace-nowrap" id="checked-in-{{ $slotId }}">0 / 0</span> <span class="whitespace-nowrap ml-1">Checked In</span>
														@endif
													</div>
													<div class="tour-process-wrp">
														<div id="progress-checked-in-{{ $slotId }}" class="tour-process success-border-color" style="width:0"></div>
													</div>
												</div>
											</span>
											@endif
										</div>
									</div>
									@php
									$dateTime = $date . ' ' . $dayBookings[$i]->time_from;

									if ($dateTime < date('Y-m-d H:i:s')) {
										$addBtnClass="hidden" ;
										} else {
										$addBtnClass="" ;
										}
										@endphp
										<div class="add-booking-wrp text-end max-[767px]:text-center">
										<button class="add-booking-btn btn-warning-small whitespace-nowrap {{ $addBtnClass }}" data-go-to="{{ route(prefixedRouteName($routePrefix.'booking.create')) . '?time_slot_id=' . $slotId }}" data-package-has-slots="{{ "$date {$dayBookings[$i]->package_has_slots}" }}" data-slot-date-time="{{ "$date {$dayBookings[$i]->time_from}" }}" onclick="return checkDateTime(this)" {{ ($dayBookings[$i]->affiliateFeature == 0) ? "disabled" : "" }}>+ Add Booking</a>
								</div>
							</div>

							<!-- <div class="row">
										<div class="col-12 col-sm-9 col-md-10">
											<div class="tour-detail-items-wrap">
												<div class="tour-detail-item primary-bg-color primary-border-color text-white-color">
													<div class="tour-detail-process">
														<img src="{{ $assetPath }}images/booking.png" alt=""> <span id="tourists-{{ $slotId }}">0</span> Bookings
														@if($dayBookings[$i]->package_has_slots)
															(<span id="empty-{{ $slotId }}"></span> of <span id="total-seats-{{ $slotId }}">{{ $dayBookings[$i]->seats }}</span> Available)
														@endif
													</div>
													<div class="tour-process-wrp">
														<div class="tour-process primary-border-color" id="progress-seats-{{ $slotId }}" style="width:0"></div>
													</div>
												</div>

												@if ($packageHasWaiver && $dayBookings[$i]->is_check_in == 1)
													<div class="tour-detail-item success-bg-color success-text-color success-bg-color success-border-color">
														<div class="tour-detail-process">
															@if($dayBookings[$i]->checkin_type == 1)
																<img src="{{ $assetPath }}images/checked.png" alt=""><span id="checked-in-{{ $slotId }}">0 / 0</span> Tickets Redeemed
															@else
																<img src="{{ $assetPath }}images/checked.png" alt=""><span id="checked-in-{{ $slotId }}">0 / 0</span> Checked In
															@endif
														</div>
														<div class="tour-process-wrp">
															<div id="progress-checked-in-{{ $slotId }}" class="tour-process success-border-color" style="width:0"></div>
														</div>
													</div>
												@endif
											</div>
										</div>
									</div> {{-- /.row --}} -->

							<div class="mt-7 max-[1350px]:mt-4 overflow-y-auto w-full inner-table-full-width">
								{{-- removed classes from below: datatable datatable-filter --}}
								@if($agendaView == 1)
								<table class="table table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left" id="datatable_{{$slotId}}">
									@else
									@if($slotId==0)
									<table class="table table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left" id="datatable_{{$sltId}}">
										@else
										<table class="table table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left" id="datatable_{{$slotId}}">
											@endif
											@endif
											<thead>
												<tr class="align-middle">
													<th class="w-[120px] th-custom-css">Booking #</th>
													<th class="w-[300px] th-custom-css">Contact</th>
													<th class="w-[120px] th-custom-css"><span id="tourists-count-{{ $slotId }}">0</span> Total</th>
													@if ($dayBookings[$i]->is_check_in == 1)
													@if($dayBookings[$i]->checkin_type == 1)
													<th class="w-[180px] th-custom-css"><span id="check-in-count-{{ $slotId }}">0</span> Tickets Redeemed</th>
													@else
													<th class="w-[180px] th-custom-css"><span id="check-in-count-{{ $slotId }}">0</span> Checked In</th>
													@endif
													@endif
													@if ($agendaGuideCount)
													<th class="w-[180px] th-custom-css"><span id="guides-count-{{ $slotId }}">0</span> Guides</th>
													@else
													<th class="w-[180px] th-custom-css">Guides</th>
													@endif
													@if ($packageHasWaiver)
													<th class="w-[140px] th-custom-css">Waivers</th>
													@endif
													<th class="w-[130px] th-custom-css">Payment</th>
													<th class="w-[120px] th-custom-css">Notes/Comments</th>
												</tr>
												<tr class="align-top">
													<th class="p-2 bg-white border whitespace-nowrap">{{-- booking # --}}</th>
													<th class="p-2 bg-white border"><span class="font-semibold text-black" id="bookings-count-{{ $slotId }}">0</span> Bookings</th>
													<th class="p-2 bg-white border font-medium text-black text-sm" id="age-wise-tourists-count-{{ $slotId }}"></th>
													@if ($dayBookings[$i]->is_check_in == 1)
													<th class="p-2 bg-white border font-medium text-black text-sm" id="check-in-status-wise-tourists-count-{{ $slotId }}"></th>
													@endif
													@if ($agendaGuideCount)
													<th class="p-2 bg-white border font-medium text-black text-sm" id="guides-summary-{{ $slotId }}"></th>
													@else
													<th class="p-2 bg-white border font-medium text-black text-sm"></th>
													@endif
													@if ($packageHasWaiver)
													<th class="p-2 bg-white border">
														<span class="success-text-color font-semibold text-[#2fa136] text-sm ">
															<span id="waiver-signs-count-{{ $slotId }}">0</span>
															<span id="waiver-signs-count-label-{{ $slotId }}">Signed</span>
														</span>
														<br />
														<span class="alert-text-color font-semibold text-[#f22e2e] text-sm ">
															<span id="waiver-not-signs-count-{{ $slotId }}">0</span>
															<span id="waiver-not-signs-count-label-{{ $slotId }}">Not Signed</span>
														</span>
													</th>
													@endif
													<th class="p-2 bg-white border">{{-- payments --}}</th>
													<th class="p-2 bg-white border">{{-- notes --}}</th>
												</tr>
											</thead>
											<tbody>
												@endif

												@php
												$seatsInfo[$j]['bookingsCount']++;
												if(session('userType') == 'Affiliate'){
												$seatsInfo[$j]['aflTourists'] += $dayBookings[$i]->aflTourists;
												$seatsInfo[$j]['aflSeats'] = $dayBookings[$i]->aflSeats;
												$seatsInfo[$j]['aflRemainingSeats'] = $dayBookings[$i]->aflRemainingSeats;
												$seatsInfo[$j]['aflTotalTourists'] = $dayBookings[$i]->aflTotalTourists;
												}
												$seatsInfo[$j]['touristsCount'] += $dayBookings[$i]->tourists;
												$seatsInfo[$j]['infantsCount'] += $dayBookings[$i]->infants;
												$seatsInfo[$j]['totalWaiver'] += $dayBookings[$i]->counter;
												$seatsInfo[$j]['totalNotSigned'] += $dayBookings[$i]->tourists + $dayBookings[$i]->infants - $dayBookings[$i]->counter;

												if($dayBookings[$i]->is_check_in == 1) {
												if($dayBookings[$i]->checkin_type == 1) {
												$seatsInfo[$j]['checkInType'] = $dayBookings[$i]->checkin_type;
												}
												}

												if ($packageHasWaiver && $dayBookings[$i]->liability_waiver_signing != '') {
												$seatsInfo[$j]['waiverSignsCount']++;
												}

												foreach($dayBookings[$i]->groupRates as $key => $groupRate) {
												if (!isset($seatsInfo[$j]['ageGroupWiseTouristsCount'][$key])) {
												$seatsInfo[$j]['ageGroupWiseTouristsCount'][$key] = 0;
												}
												$seatsInfo[$j]['ageGroupWiseTouristsCount'][$key] += $groupRate;
												}

												foreach($dayBookings[$i]->checkInsCount as $key => $checkIn) {
												$seatsInfo[$j]['checkInsCount'][$key] += $checkIn;
												}

												$seatsInfo[$j]['redeemTicketsCount']['not_redeemed'] += $dayBookings[$i]->not_redeemed_tickets;
												$seatsInfo[$j]['redeemTicketsCount']['redeemed'] += $dayBookings[$i]->redeemed_tickets;
												
												$guidesRaw = array_filter(array_map('trim', explode(',', $dayBookings[$i]->assigned_guides)));

												foreach ($guidesRaw as $guideText) {

												    // Extract guide ID
												    if (preg_match('/\[#ID:(\d+)\]/', $guideText, $idMatch)) {
												        $guideId = (int) $idMatch[1];

												        // Keep unique guide count
												        $seatsInfo[$j]['uniqueGuides'][$guideId] = true;
												    }

												    // Remove ID from display text
												    $guideName = preg_replace('/\s*\[#ID:\d+\]/', '', $guideText);

												    // Extract tourist count from "(4)"
												    $count = 1;
												    if (preg_match('/\((\d+)\)/', $guideName, $countMatch)) {
												        $count = (int) $countMatch[1];
												    }

												    // Clean guide name
												    $guideName = trim(preg_replace('/\(\d+\)/', '', $guideName));

												    if (!isset($seatsInfo[$j]['guidesSummary'][$guideName])) {
												        $seatsInfo[$j]['guidesSummary'][$guideName] = 0;
												    }

												    $seatsInfo[$j]['guidesSummary'][$guideName] += $count;
												}

												$seatsInfo[$j]['guidesCount'] = count($seatsInfo[$j]['uniqueGuides']);

												@endphp

												<tr class="align-top <?php echo $dayBookings[$i]->background_colour_class ?>"
													data-slot-booking-id="{{ $dayBookings[$i]->booking_id }}"
													data-is-unpaid="{{ !(in_array($dayBookings[$i]->via, array_merge(['Card','Cash','Invoiced'], $otaPlatforms)) && $dayBookings[$i]->booking_total <= $dayBookings[$i]->paids) ? '1' : '0' }}"
													data-is-affiliate="{{ $dayBookings[$i]->is_affiliate }}"
													data-booking-url="{{ route(prefixedRouteName($routePrefix.'booking.show'), ['booking' => $dayBookings[$i]->booking_id]) }}">
													<td class="p-2 border"><a class="font-medium text-blue-600 text-sm whitespace-nowrap" href="{{ route(prefixedRouteName($routePrefix.'booking.show'), [ 'booking' => $dayBookings[$i]->booking_id ]) }}" target="_blank">{{ bookingPrefix($dayBookings[$i]->booking_id) }}</a></td>
													<td class="p-2 border font-normal text-sm">
														<span class="cust-name font-medium text-black block">{{ $dayBookings[$i]->customer_name }}</span>
														<!-- {!! $dayBookings[$i]->is_affiliate == 'Yes' ? '<i class="fa fa-handshake text-[#727272]"></i>' : '' !!} -->
														<span class="cust-phone text-[#727272] block">{{ $dayBookings[$i]->phone_number }}</span>
														<span class="cust-email text-[#727272] block max-w-[175px] break-all">{{ $dayBookings[$i]->email }}</span>
														@if($dayBookings[$i]->has_contacts == 1)
														<span class="contact-details cursor-pointer mt-1 text-[#727272] px-[13px] py-[5px] mb-2 text-sm rounded-[100px] border border-solid font-normal inline-block" onclick="bookingContacts({{ $dayBookings[$i]->booking_id }})"><b>Contacts</b></span>
														@endif
													</td>
													<td class="p-2 border">
														@php
														foreach ($dayBookings[$i]->touristsAgeInfo as $ageInfo) {
														echo "<span class='font-medium text-black text-sm block whitespace-nowrap'>$ageInfo</span>";
														}
														@endphp
													</td>
													@if ($dayBookings[$i]->is_check_in == 1)
													<td class="p-2 border" data-order="{{$dayBookings[$i]->tourists}}">
														@if($dayBookings[$i]->checkin_type == 1)
														@include('users.common.agenda.redeem-tickets')
														@else
														@include('users.common.agenda.check-ins')
														@endif
													</td>
													@endif
													<td class="p-2 border">
														@if($dayBookings[$i]->assigned_guides != '')
														@php
														$assignedGuides = explode(",", $dayBookings[$i]->assigned_guides);
														@endphp
														@foreach($assignedGuides as $tourGuide)
														@php
												            // Remove [#ID:123] from display
												            $displayGuide = preg_replace('/\s*\[#ID:\d+\]/', '', trim($tourGuide));
												        @endphp
														<span class="px-3 py-[5px] bg-[#e8ffea] rounded-[6px] border border-[#2ea135] font-semibold text-[#2ea135] text-[12px] inline-flex items-center gap-1 mb-1 text-nowrap">
															{{ $displayGuide}}
														</span>
														@endforeach
														@endif
														<button type="button" onclick="getAssignedTourGuides({{ $dayBookings[$i]->booking_id }})" class="btn {{($dayBookings[$i]->assigned_guides != '') ? 'mt-1' : ''}}  assign-guide-btn btn-sm btn-primary-small whitespace-nowrap">
															<i class="fas fa-users me-2"></i>Guides
														</button>
													</td>

													@if ($packageHasWaiver)
													<td class="p-2 border">
														@if($dayBookings[$i]->counter > 0)
														<p class="font-medium text-[#0f5132] whitespace-nowrap text-sm success-text-color">{{ $dayBookings[$i]->counter }} Signed</p>
														@endif
														@if($dayBookings[$i]->tourists + $dayBookings[$i]->infants - $dayBookings[$i]->counter)
														<p class="font-medium text-[#f22e2e] whitespace-nowrap text-sm alert-text-color">{{ $dayBookings[$i]->tourists + $dayBookings[$i]->infants - $dayBookings[$i]->counter }} Not Signed</p>
														@endif
													</td>
													@endif

													<td class="tourist-payment-status p-2 border">
														@if (in_array($dayBookings[$i]->via, array_merge(['Card','Cash','Invoiced'], $otaPlatforms)) && $dayBookings[$i]->booking_total <= $dayBookings[$i]->paids)
															<span class="px-3 py-[5px] bg-[#e8ffea] rounded-[100px] border border-[#2ea135] font-semibold text-[#2ea135] text-sm inline-flex items-center gap-1"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
																	<g clip-path="url(#clip0_2603_556)">
																		<path d="M2.27468 2.31674C-0.305375 4.92484 -0.291353 9.14547 2.31674 11.7255C4.92484 14.3056 9.14547 14.2915 11.7255 11.6835C14.3056 9.07535 14.2915 4.85473 11.6835 2.27468C9.07535 -0.305374 4.85473 -0.291353 2.27468 2.31674Z" fill="#2EA135" />
																		<path d="M10.2388 5.72387L6.62115 9.38362C6.31267 9.6921 5.80788 9.6921 5.49939 9.38362L5.35917 9.2434L4.93851 8.82274L3.76066 7.65891C3.45218 7.35042 3.45218 6.84563 3.76066 6.53715C4.06914 6.22866 4.57394 6.22866 4.88242 6.53715L6.06027 7.70097L9.11707 4.61613C9.42556 4.30764 9.93035 4.30764 10.2388 4.61613C10.5473 4.91059 10.5473 5.41538 10.2388 5.72387Z" fill="white" stroke="white" stroke-width="0.5" />
																	</g>
																	<defs>
																		<clipPath id="clip0_2603_556">
																			<rect width="14" height="14" fill="white" />
																		</clipPath>
																	</defs>
																</svg> Paid</span>
															@else
															<span class="px-3 py-[5px] bg-[#ffe8e8] rounded-[100px] border border-[#ff0000] font-semibold text-[#ff0000] text-sm inline-flex items-center gap-1"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
																	<g clip-path="url(#clip0_2603_37)">
																		<path d="M7 0C5.61553 0 4.26216 0.410543 3.11101 1.17971C1.95987 1.94888 1.06266 3.04213 0.532846 4.32122C0.00303301 5.6003 -0.13559 7.00777 0.134506 8.36563C0.404603 9.7235 1.07129 10.9708 2.05026 11.9497C3.02922 12.9287 4.2765 13.5954 5.63437 13.8655C6.99224 14.1356 8.3997 13.997 9.67879 13.4672C10.9579 12.9373 12.0511 12.0401 12.8203 10.889C13.5895 9.73785 14 8.38447 14 7C14 5.14349 13.2625 3.36301 11.9497 2.05025C10.637 0.737498 8.85652 0 7 0Z" fill="#FF0000" />
																		<path d="M10.0915 8.94232C10.1737 9.01618 10.24 9.10602 10.2864 9.20635C10.3328 9.30669 10.3582 9.41542 10.3612 9.52591C10.3641 9.6364 10.3445 9.74634 10.3036 9.84901C10.2627 9.95169 10.2013 10.0449 10.1231 10.1231C10.0449 10.2013 9.95169 10.2627 9.84901 10.3036C9.74634 10.3445 9.6364 10.3641 9.52591 10.3612C9.41542 10.3582 9.30669 10.3328 9.20635 10.2864C9.10602 10.24 9.01618 10.1737 8.94232 10.0915L6.99982 8.14898L5.05732 10.0915C4.98345 10.1737 4.89361 10.24 4.79328 10.2864C4.69294 10.3328 4.58421 10.3582 4.47372 10.3612C4.36323 10.3641 4.25329 10.3445 4.15062 10.3036C4.04794 10.2627 3.95468 10.2013 3.87653 10.1231C3.79837 10.0449 3.73695 9.95169 3.69603 9.84901C3.6551 9.74634 3.63552 9.6364 3.63848 9.52591C3.64143 9.41542 3.66687 9.30669 3.71324 9.20635C3.7596 9.10602 3.82592 9.01618 3.90815 8.94232L5.85065 6.99981L3.90815 5.05732C3.76897 4.90237 3.6945 4.69995 3.70007 4.49174C3.70565 4.28354 3.79085 4.0854 3.93812 3.93812C4.0854 3.79085 4.28354 3.70565 4.49174 3.70007C4.69995 3.6945 4.90237 3.76897 5.05732 3.90815L6.99982 5.85065L8.94232 3.90815C9.01618 3.82592 9.10602 3.7596 9.20635 3.71324C9.30669 3.66687 9.41542 3.64143 9.52591 3.63848C9.6364 3.63552 9.74634 3.6551 9.84901 3.69603C9.95169 3.73695 10.0449 3.79837 10.1231 3.87653C10.2013 3.95468 10.2627 4.04794 10.3036 4.15062C10.3445 4.25329 10.3641 4.36323 10.3612 4.47372C10.3582 4.58421 10.3328 4.69294 10.2864 4.79328C10.24 4.89361 10.1737 4.98345 10.0915 5.05732L8.14898 6.99981L10.0915 8.94232Z" fill="white" stroke="white" stroke-width="0.5" />
																	</g>
																	<defs>
																		<clipPath id="clip0_2603_37">
																			<rect width="14" height="14" fill="white" />
																		</clipPath>
																	</defs>
																</svg> Unpaid </span>
															@endif
															<div class="mt-2">
																<a href="{{ route(prefixedRouteName("{$routePrefix}booking.print-info"), ['bookingId' => $dayBookings[$i]->booking_id]) }}" target="_blank" class="btn btn-sm whitespace-nowrap px-4 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:bg-white text-white rounded-lg hover:text-[#60BEBD] text-xs leading-[16px] font-medium cursor-pointer transition-all duration-300 ease-in-out">
																	<i class="fa fa-print me-1"></i> Print Receipt
																</a>
															</div>
													</td>
													<td class="p-2 border font-medium text-sm text-[#000] text-center">
														@if(!empty($dayBookings[$i]->note))
														<b>Note:</b>
														{{ \Illuminate\Support\Str::limit($dayBookings[$i]->note, 50) }}

														@if(strlen($dayBookings[$i]->note) > 50)
														<a href="javascript:void(0);"
															class="show-note"
															data-note="{{ e($dayBookings[$i]->note) }}">
															Show more
														</a>
														@endif
														@endif
														@if($dayBookings[$i]->comments != '')
														<hr class="my-2"><b>Comments: </b>
														{{ \Illuminate\Support\Str::limit($dayBookings[$i]->comments, 50) }}

														@if(strlen($dayBookings[$i]->comments) > 50)
														<a href="javascript:void(0);"
															class="show-comment"
															data-comment="{{ e($dayBookings[$i]->comments) }}">
															Show more
														</a>
														@endif
														@endif
														@if($dayBookings[$i]->comments != '' || $dayBookings[$i]->note != '')
														<br/>
														@endif
														<button type="button"
															class="add-note-btn btn-primary-small {{ ($dayBookings[$i]->comments != '' || $dayBookings[$i]->note != '') ? 'mt-3' : '' }}"
															data-booking-id="{{ $dayBookings[$i]->booking_id }}"
															data-comment="{{ $dayBookings[$i]->comments }}">
															+ Add
														</button>
													</td>
												</tr>

												@if($i == $ic - 1 || $dayBookings[$i + 1]->tour_package_id != $tourPackageId || $dayBookings[$i + 1]->$agendaViewId != $slotId)
											</tbody>
										</table>
							</div> {{-- /.tour-detail-table-wrap --}}
						</div> {{-- /#slot-id --}}
						@endif
						@endfor
						<!-- </div> -->
				</div>
			</div>
		</div>
	</section>
</div>
<div class="checkin-overview-container" style="display:none;"></div>

<!-- Booking Contacts Modal Start -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="booking_contacts" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h4 class="modal-title text-lg font-semibold text-[#111827]">Booking Contacts</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
						<path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
					</svg></button>
			</div>

			<div class="modal-body py-7 px-5">
				<div id="contact-body"></div>
			</div>
		</div>
	</div>
</div>
<!-- Booking Contacts Modal End -->
<!-- Note Modal Start -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="noteModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h4 class="modal-title text-lg font-semibold text-[#111827]">Note</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
						<path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
					</svg></button>
			</div>

			<div class="modal-body py-7 px-5">
				<p id="fullNoteText" class="mb-0"></p>
			</div>
		</div>
	</div>
</div>
<!-- Note Modal End -->
<!-- Comment Modal Start -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="commentModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-xl max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h4 class="modal-title text-lg font-semibold text-[#111827]">Comments</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
						<path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
					</svg></button>
			</div>

			<div class="modal-body py-7 px-5">
				<p id="fullCommentText" class="mb-0"></p>
			</div>
		</div>
	</div>
</div>
<!-- Comment Modal End -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="noteCommentModal" tabindex="-1">
	<div class="modal-dialog modal-xl max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">

			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h5 class="modal-title text-lg font-semibold text-[#111827]">Add Note | Comment</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg></button>
			</div>

			<div class="modal-body py-7 px-5">
				<input type="hidden" id="bookingId">

				<!-- Tabs -->
				<div class="flex border-b border-gray-200 mb-5">
					<button type="button"
						class="tab-btn px-4 py-2 text-sm font-semibold text-blue-600 border-b-2 border-blue-600"
						data-tab="noteTab">
						Note
					</button>
					<button type="button"
						class="tab-btn px-4 py-2 text-sm font-semibold text-gray-500 hover:text-blue-600"
						data-tab="commentTab">
						Comment
					</button>
				</div>

				<!-- Note Tab -->
				<div id="noteTab" class="tab-content">
					<label class="block mb-2 text-sm font-semibold text-gray-700">Note</label>
					<textarea id="noteText"
						rows="4"
						class="w-full border rounded-lg p-3 focus:outline-none focus:ring focus:ring-blue-200"
						placeholder="Enter new note"></textarea>
				</div>

				<!-- Comment Tab -->
				<div id="commentTab" class="tab-content hidden">
					<label class="block mb-2 text-sm font-semibold text-gray-700">Comment</label>
					<textarea id="commentText"
						rows="4"
						class="w-full border rounded-lg p-3 focus:outline-none focus:ring focus:ring-blue-200"
						placeholder="Enter comment"></textarea>
					<small class="text-gray-500" id="commentHint"></small>
				</div>
				<div class="flex flex-wrap -mx-3 mb-2">
				    <div class="w-full px-3 flex justify-end gap-2">
				        <button
				            type="button"
				            class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
				            id="saveNoteComment">
				            Submit
				        </button>
				    </div>
				</div>

			</div>

		</div>
	</div>
</div>

<!-- Assign Tour Guides Modal Start -->
@include('users.common.partials.booking._assign-guides-popup',['tourGuides' => $tourGuides])
<!-- Assign Tour Guides Modal End -->
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
@endif
<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
@else
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
@endif
@endsection

@section('js-custom')

<script>
	var viewId = "{{session()->get('viewId')}}";
	var slots = @json($slots);
	var dayBookingsId = @json($dayBookingsId);
	var seatsInfo = @json($seatsInfo);
	var totalBookingsCount = {{$totalBookingsCount}};
	var serverDT = moment('{{ date('Y-m-d H:i:s') }}');
	var updateCheckInStatusUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.update-check-in-status'))); ?>";
	var agendaUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.day-time-slots-booking-id'))); ?>";
	var updateRedeemTicketStatusUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.update-package-redeem-status'))); ?>";
	var rosterReportCSVUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.roster-report-csv'), [
									'date' => '_DATE_',
									'tourPackageId' => '_TOUR_PACKAGE_',
									'PaymentStatus' => '_PAYMENT_STATUS_',
									'slotId' => '_SLOTID_'
								])); ?>";
	var rosterReportPDFUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.roster-report-pdf'), [
									'date' => '_DATE_',
									'tourPackageId' => '_TOUR_PACKAGE_',
									'PaymentStatus' => '_PAYMENT_STATUS_',
									'slotId' => '_SLOTID_'
								])); ?>";
	var bookingContactsURL = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.contacts'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	var getAssignedGuidesURL = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.get-assigned-guides'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	var assignTourGuideUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.save-tour-guide'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	var agendaCheckinOverviewUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.agenda-checkin-overview'))); ?>";
	var saveNoteUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.save-note'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	var saveCommentUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.save-comments'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	var tourGuides = @json($tourGuides);
	var tourOperatorCheckInStatues = @json($tourOperatorCheckInStatues);
	setInterval(function() {
		serverDT.add(15, 'seconds');
	}, 15000);
	var uTypeAdmin = "{{ session('userType') }}";
	$(function() {
		$('#date').val(moment('{{ $date }}').format('dddd, MMMM D YYYY'));
		@php
		$routeParams = ['date' => '_DATE_'];
		$routeParams['tourPackageId'] = '_TOUR_PACKAGE_';
		echo "$('#tour_package').select2();";
		$routeParams['paymentStatus'] = '_PAYMENT_STATUS';
		echo "$('#payment_status').select2();";
		@endphp
	});

	function tourBookingDayTimeSlotsUrl() {
		return "{{ route(prefixedRouteName($routePrefix.'booking.day-time-slots'), $routeParams) }}"
			.replace('_DATE_', moment($('#date').datepicker('getDate')).format('yyyyMMDD'))
			.replace('_TOUR_PACKAGE_', $('#tour_package').val())
			.replace('_PAYMENT_STATUS', $('#payment_status').val());
	}

	$('#reset').on('click', function() {
		{{session()->forget('viewId')}}
		location.reload();
	})

	$("#printAgenda").click(function() {
		var downloadAgendaUrl = "{{ route(prefixedRouteName($routePrefix.'booking.download-agenda'), $routeParams) }}"
			.replace('_DATE_', moment($('#date').datepicker('getDate')).format('yyyyMMDD'))
			.replace('_TOUR_PACKAGE_', $('#tour_package').val())
			.replace('_PAYMENT_STATUS', $('#payment_status').val())
			.replace('_SLOTID', $('#time-slots').val());

		window.location = downloadAgendaUrl;
	});
</script>

<script src="{{ asset('js/user-common/tour-bookings-day-time-slots.js') }}"></script>
<script src="{{ asset('js/user-common/tour-booking-assign-guides.js') }}"></script>
<script src="{{ asset('js/user-common/agenda-checkin-overview.js') }}"></script>
<script>
	SocketManager.register(new BoardSocketModule());
	SocketManager.subscribe('tenant.{{ tenancy()->tenant->id }}');
</script>
@endsection