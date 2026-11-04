@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
<script>
var vueMounted = false;
var csrfToken = '{{ csrf_token() }}';

var broadcastListeningToken = '{{ \Crypt::encrypt([
	'id' => session('broadcastListeningTokenId'),
	'uId' => auth()->id(),
	'uType' => session('userType'),
]) }}';

{{-- year kept out of vue so changing month through next and previous btns (which also changes year) do not affect it --}}
var calInfo = {
	today: '{{ date("Y-m-d") }}',
	year: {{ $calData['year'] }},
	month: {{ $calData['month'] - 1 }}
};

var draftTourSlots = @json($calData['tourSlots']);
var tourBookings =  @json($calData['tourBookings']);

var vueMixin = {
	data: {
		rootPath: "{{ asset('') }}",
		imgsPath: {
			checkboxChecked: 'images/mark-check.png',
			checkboxUnchecked: 'images/mark-uncheck.png',
			loadingAnim: 'images/loading.gif'
		},
		affiliate: {
			id: {{ count($affiliates) ? $affiliates[0]->id : 0 }},
			seats: 1
		},
		affiliates: @json($affiliates),
	@if($uTypeAdmin)
		tourOperator: {{ $tourOperatorId }},
		tourOperators: @json($tourOperators)
	@endif
	}
};
</script>

{{--
	instead of creating separate .vue file, whole vue element is placed here because
	tour operator dropdown/select box depends on whether admin user is logged in or not, and
	we don't want to give any clue to the tour operator staff users that there is tour operator 
	selection dropdown in the page. vue can remove the element based on v-if but still
	it will be in the html sent to the client/browser if not filtered out on server.
	so only option is to filter it with php tags instead of laravel's @-if as it will not
	work inside @-verbatim. @-verbatim is used to tell laravel that the inside content is not
	for laravel/blade, as both laravel/blade and vue have curly brackets syntax for outputing data
--}}
	@verbatim
<div id="calendar-vue-app" class="row" v-cloak>
	<div class="col-12 col-md-3 col-lg-2 position-relative data-col order-2"> <!-- bookings sidebar - start -->
		<div class="mb-2"><input id="search" class="form-control margin-t-2px txt-search" title="Enter at least 2 characters for searching"></div>

		<div class="bookings-list"> <!-- bookings - start -->
			<div class="searching text-center" v-if="searching">
				<img :src="rootPath + imgsPath.loadingAnim" class="align-text-top"> Searching
			</div>

			<div v-if="searchDone && tourBookings.length == 0" class="text-center">No record found</div>

			<div :class="[ 'booking-info', 'booking-of-afl-' + booking.is_affiliate ]" v-for="booking in tourBookings" :key="booking.id" :id="'booking-' + booking.id" v-on:click="initBookingInfoModal(booking.id, 'sidebar')">
				<div class="bk-inf-cust-nm-out pt-1">
					<div v-bind:class="[ 'float-end badge', booking.paid == booking.total ? 'green-bg-color' : 'red-bg-color' ]">{{ booking.paid | formatAmt(booking.total) }}</div>
					<div class="bk-inf-cust-nm">{{ booking.cust_name }}</div>
				</div>

				<div class="bk-inf-t-groups">[ {{ booking.t_groups }} ]</div>
				<div class="bk-inf-pkg-nm">{{ booking.pkg_name }}</div>
				<div class="bk-inf-t-time">{{ booking.tour_d_t | formatTourDT }}</div>
				<div class="bk-inf-bk-time">Booked on {{ booking.booking_d_t | formatBookingDT }}</div>
			</div>
		</div> <!-- bookings - end -->
	</div> <!-- bookings sidebar - end -->

	<div class="col-12 col-md-9 col-lg-10 data-col order-1"> <!-- calendar and its options - start -->
		<div class="row mb-2"> <!-- calendar options - start -->

			<?php if($uTypeAdmin) { ?>
				<div class="col-12 col-md-5">
					<select id="tour_operator" v-model="tourOperator" class="form-select margin-t-2px" v-on:change="getTourSlots(this)">
						<option v-for="(tourOperator, index) in tourOperators" :value="tourOperator.id">{{ tourOperator.name }}</option>
					</select>
				</div>
			<?php } ?>

			<div class="col-12 col-md-<?= $uTypeAdmin ? 7 : 12 ?> text-center text-md-end">
				<span>
				
					<button type="button" class="btn rounded-circle mx-1 btn-prev-month" v-on:click="getTourSlots('previous')">
						<i class="fa fa-chevron-left"></i>
					</button>
					
					<button type="button" class="btn rounded-circle mx-1 btn-next-month" v-on:click="getTourSlots('next')">
						<i class="fa fa-chevron-right"></i>
					</button>
				</span>
				
				<select v-model="month" class="form-select me-1 align-middle d-inline-block w-auto" v-on:change="getTourSlots(this)">
					<option v-for="(month, index) in months" :value="index">{{ month }}</option>
				</select>

				<select id="sel-cal-year" class="form-select d-inline-block w-auto" v-on:change="getTourSlots('year')">
						<?php
						for($a = $calData['year'] + 1, $ac = $calData['year'] - 10; $a > $ac; $a--)
							echo "<option" . ($a == $calData['year'] ? ' selected' : '') . ">$a</option>";
						?>
				</select>
			</div>
		</div> <!-- calendar options - end -->

		<div class="row"> <!-- calendar .row -->
			<div class="col">
				<div class="cal-table" style="overflow-x: auto;">
					<div class="cal-week-days">
						<div class="cal-week-day" v-for="weekDay in days" style="min-width:150px;">{{ weekDay }}</div>
					</div>

					<div class="cal-month-days" >
						<div :class="[ 'cal-month-day', day.class ]" v-for="(day, dayIdx) in calMonthDaysSchedules" :key="day.date" :id="day.id" style="min-width:150px;">
							<div class="cal-day-num-div" >
								<span class="cal-day-num">{{ day.num }}</span>
							</div>

							<div class="cal-day-schedules">
								<div v-for="(schedule, schIdx) in day.schedules" :key="schedule.id" v-on:click="dayPkgSlotTimesModal(dayIdx, schIdx)" :class="['cal-package', 'cal-package-' + schedule.pkg_id ]">
									<div class="cal-package-title">{{ schedule.pkg_name }}</div>
									<div class="cal-seats-info">
										<span class="cal-b">B</span>
										<span :class="[ 'cal-seats-filled', schedule.tourists > 0 ? 'success-text-color success-bg-color success-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ schedule.tourists }}</span>
										<span :class="[ 'cal-seats-empty', schedule.tourists < schedule.seats ? 'alert-text-color alert-bg-color alert-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ schedule.seats - schedule.tourists }}</span>
									</div>
									<div class="cal-seats-progress">
										<div :style="{ width: ((schedule.tourists * 100) / schedule.seats) + '%' }"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div> <!-- /.cal-table -->
			</div>
		</div> <!-- /calendar .row -->
	</div> <!-- calendar and its options - end -->

	<div class="modal fade cal-bookings-modal" id="time-slot-bookings" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<div class="header-info">
						<h3>{{ dayPkgSlotInfo.isToday ? 'Today' : dayPkgSlotInfo.displayDate }} {{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot | formatSlotTimeDuration }}</h3>
						<span>{{ dayPkgSlotInfo.pkg_name }}</span>
						<?php if($uTypeAdmin) { ?>
						<a :href="dayPkgSlotInfo.link2+dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.id" id="popup_booking" class="btn btn-secondary tourbookingButton"  target="_blank">Booking</a>
						
						<?php } if(Session::get('roleNames')){?>
							<?php if( Session::get('roleNames')->contains("name", "Manager") == true || Session::get('roleNames')->contains("name", "Manager") == true) { ?>
						<a :href="dayPkgSlotInfo.link4+dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.id" id="popup_booking" class="btn btn-secondary tourbookingButton"  target="_blank">Booking</a>
							<?php } ?>
								
						<?php } ?>
						<button type="button" class="btn-close" v-on:click="modalTimeSlotBookings.hide();modalDayPkgSlots.show()" aria-label="Close"></button>
					</div>
				</div>

				<div class="modal-body">
					<div class="booked-tickets">
						<div class="filter-row">
							<div class="rowone"><h2>{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.tourists }} Booked</h2></div>

							<div class="rowtwo">
								<div class="status-warp">
									<div class="dropdown">
										<button class="dropdown-toggle" id="btn-dd-bookable-status" data-bs-toggle="dropdown" aria-expanded="true"><i :class="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2 == 'Open' ? 'fas fa-check-circle' : (dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2 == 'Closed' ? 'fas fa-lock' : 'fas fa-phone-alt')"></i> {{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2 }}</button>

										<ul class="dropdown-menu" aria-labelledby="btn-dd-bookable-status">
											<li class="justify-content-start">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2_reason }}</li>
										</ul>
									</div>
								</div>

								<div class="availability-warp">
									<div class="dropdown">
										<button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.tourists }} Booked <span>|</span> {{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot | availableSeatsInfo }} | {{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.seats }} Total</button>

										<div class="dropdown-menu">

											<div class="capacities-warp nobg" v-if="dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfo.length == 0">[ No booking in the slot ]</div>
											
											<div class="capacities-warp nobg" v-for="touristsInfo in dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfo">
												<div class="title">{{ touristsInfo.rate_for }}</div>
												<div class="booked">{{ touristsInfo.tourists }}</div>
											</div>
										</div> <!-- /.dropdown-menu -->
									</div>
								</div> <!-- /.availability-warp -->

								<button class="add-booking-btn" target="_blank" v-on:click.prevent="initNewBookingModal" :disabled="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.tourists >= dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.seats || dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status == 'Closed'">+ Book</button>
							</div>
						</div> <!-- /.filter-row -->

						<div class="filter-row2">
							<div class="seats">
								<span class="bookingnum"><i class="fa fa-user"></i> {{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.tourists }}</span>
								<p class="bookingspace">[ {{ dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfo | shortAgeGrpAndBookedSeatsInfo }} ]</p>
							</div>
							<div class="float-right"><label>
								<input type="checkbox" v-model="dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings">
								<span>Show only cancelled bookings ({{ dayPkgSlotInfo.timeSlotBookingsInfo.cancelledCount }})</span>
							</label></div>
						</div>
					</div> <!-- /.booked-tickets -->

					<div class="booking-warp">
						<div :class="[ 'details-warp', 'booking-of-afl-' + booking.is_affiliate ]" v-for="booking in dayPkgSlotInfo.timeSlotBookingsInfo.bookings" v-if="((booking.status == 'Booked' || booking.status == 'Completed') && !dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings) || (booking.status == 'Cancelled' && dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings)">
							<div class="details-name">
								<a v-on:click.prevent="initBookingInfoModal(booking.id, 'modal')" href="#">{{ booking.cust_name }}</a>

								<a v-on:click.prevent="initBookingInfoModal(booking.id, 'modal')" href="#" class="bookingid">ID: #{{ booking.id }}</a>
								<span class="bookingnum"><i class="fa fa-user"></i> {{ booking.tourists }}</span>
								<p class="bookingspace">[ {{ booking.t_groups }} ]</p>
							</div>

							<div class="ammount">
								<div class="finalammount paid">{{ booking.paid < booking.total ? currencyFormation.symbol + (booking.total - booking.paid) : 'Paid' }}</div>
							</div>

							<p class="starus">Booked on {{ booking.booking_d_t | formatBookingDT }}</p>
						</div>
					</div> <!-- /.booking-warp -->
				</div> <!-- /.modal-body -->
			</div> <!-- /.modal-content -->
		</div> <!-- /.modal-dialog -->
	</div> <!-- /#time-slot-bookings .cal-bookings-modal .modal -->

	<div class="modal fade" id="day-pkg-slot-times" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-xl">
			<div class="modal-content">
				<div class="modal-header">
					<div class="header-info">
						<h3>{{ dayPkgSlotInfo.pkg_name }}</h3>

						<span>
							<i class="far fa-calendar"></i>
							<span v-if="dayPkgSlotInfo.isToday">Today: </span>
							<span>{{ dayPkgSlotInfo.displayDate }}</span>
						</span>
						<?php if($uTypeAdmin) { ?>
						<a :href="dayPkgSlotInfo.link" id="popup_booking" class="btn btn-secondary tourbookingButton"   target="_blank">Booking</a>
						
						<?php } if(Session::get('roleNames')){?>
							<?php if(Session::get('roleNames')->contains("name", "Manager") == true || Session::get('roleNames')->contains("name", "Manager") == true) { ?>
						<a :href="dayPkgSlotInfo.link3" id="popup_booking" class="btn btn-secondary tourbookingButton"  target="_blank">Booking</a>
							<?php } ?>
						<?php } ?>
						
						<button type="button" class="btn-close" v-on:click="modalDayPkgSlots.hide()" data-dismiss="modal" aria-label="Close"></button>
					</div>
				</div>

				<div class="modal-body">
					<div class="data-warp" v-for="(slot, slotIdx) in dayPkgSlotInfo.slotTimes">
							
						<button type="button" class="timebutton me-3" data-bs-toggle="modal" data-bs-target="#time-slot-bookings" :data-time-slot-index="slotIdx">{{ slot | formatSlotTimeDuration }} <i class="fas fa-chevron-right"></i></button>

						<div class="status-warp me-3">
							<div class="dropdown">
								<button type="button" :class="slot.bookable_status_2 | getBookableStatusCls" :id="'btn-dd-ts-' + slot.id" data-bs-toggle="dropdown" aria-expanded="true"><i :class="slot.bookable_status_2 == 'Open' ? 'fas fa-check-circle' : (slot.bookable_status_2 == 'Closed' ? 'fas fa-lock' : 'fas fa-phone-alt')"></i> {{ slot.bookable_status_2 }}</button>

								<ul class="dropdown-menu" :aria-labelledby="'btn-dd-ts-' + slot.id">
									<li class="justify-content-start">{{ slot.bookable_status_2_reason }}</li>
								</ul>
							</div>
						</div>

						<div class="availability-warp me-3">
							<div class="dropdown">
								<button type="button" :class="slot | getBookedSeatsCls" data-bs-toggle="dropdown" aria-expanded="false" :data-time-slot-id="slot.id" data-dropdown-of="tourists age/rage groups" id="ragetourists">{{ slot.tourists }} Booked <span>|</span> {{ slot | availableSeatsInfo }} | {{ slot.seats }} Total</button>

								<div class="dropdown-menu" :id="'tourists-info-' + slot.id" data-bs-auto-close="outside">

									<div class="capacities-warp nobg" v-if="dayPkgSlotInfo.timeSlotTouristsInfo.length == 0">[ No booking in the slot ]</div>

									<div class="capacities-warp nobg" v-for="touristsInfo in dayPkgSlotInfo.timeSlotTouristsInfo">
										<div class="title">{{ touristsInfo.rate_for }}</div>
										<div class="booked">{{ touristsInfo.tourists }}</div>
									</div>
								</div> <!-- /.dropdown-menu -->
							</div>
						</div> <!-- /.availability-warp -->

						<div class="div-affiliate-bookings">
							<div class="dropdown">
								<button type="button" class="themed-btn dropdown-toggle" data-bs-toggle="dropdown" :data-time-slot-id="slot.id" data-dropdown-of="affiliate bookings" data-bs-auto-close="false" aria-expanded="false">{{ slot.afl_tourists }} Affiliate Bookings</button>

								<div class="dropdown-menu dropdown-menu-end p-3" :id="'affiliate-bookings-info-' + slot.id">
									<table :class="['table table-sm', 'table-borderless', dayPkgSlotInfo.timeSlotAffiliateBookings.length ? 'mb-2' : 'mb-0']">
										<tr v-for="aflBooking in dayPkgSlotInfo.timeSlotAffiliateBookings">
											<td class="ps-0" :data-affiliate-id="aflBooking.affiliate_id">{{ aflBooking.name }}</td>
											<td class="text-end">{{ aflBooking.tourists }}</td>
											<td class="text-end"><i class="fa fa-pencil" v-on:click="editAffiliateBooking(slot.id, aflBooking.affiliate_id, aflBooking.tourists)"></i></td>
											<td class="text-end pe-0"><i class="fa fa-times" v-on:click="cancelAffiliateBooking(slot.id, aflBooking.affiliate_id)"></i></td>
										</tr>
									</table>

									<div class="mb-3 div-afl-book-title">Reserve/book seats for affiliates:</div>
									
									<button type="button" class="btn btn-primary btn-sm btn-show-save-afl-booking" v-on:click="showAffiliateBookingForm()" :disabled="slot.bookable_status_2 == 'Closed'">Add/Update a Block</button>

									<div class="div-save-afl-booking d-none">
										<select class="form-select sel-affiliate mb-2" v-model="affiliate.id">
											<!-- option :value="affil.id" v-for="affil in timeSlotAffilsWithNoBooking">{{ affil.name }}</option -->
											<option :value="affil.id" v-for="affil in affiliates">{{ affil.name }}</option>
										</select>

										<input type="number" class="form-control form-control-sm txt-affiliate-seats mb-2" min="1" :max="slot.seats" value="1" placeholder="Number of seats to book">
										<button type="button" class="btn btn-primary btn-save-afl-booking btn-sm" v-on:click="saveAffiliateBooking(slot.id)">Add/Update Block</button> 
									</div>
								</div>
							</div>
						</div>
					</div> <!-- / slot times -->

					<div class="rec-history-container" v-if="dayPkgSlotInfo.recHistory.length">
						<div class="rec-history row" v-for="recHist in dayPkgSlotInfo.recHistory">
							<div class="col-12 col-md-4">{{ recHist.dateTime | formatBookingDT }}</div>
							<div class="col-12 col-md-8" v-html="recHist.info"></div>
						</div>
					</div>
				</div> <!-- /.modal-body -->
			</div> <!-- /.modal-content -->
		</div> <!-- /.modal-dialog -->
	</div> <!-- /#day-pkg-slot-times modal -->

	<div class="modal fade" id="modal-new-booking" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen">
			<div class="modal-content">
				<div class="modal-header">
					<div class="container"><h3>Book a Tour</h3></div>
					<button type="button" class="btn-close" v-on:click="modalNewBooking.hide();modalTimeSlotBookings.show()" aria-label="Close"></button>
				</div>

				<div class="modal-body">
					<iframe id="frame-new-booking" v-on:load="showNewBookingModal" src=""></iframe>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="modal-booking-info" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen">
			<div class="modal-content">
				<div class="modal-header">
					<div class="container"><h3>Booking Information</h3></div>
					<button type="button" class="btn-close" v-on:click="hideBookingInfoModal" aria-label="Close"></button>
				</div>

				<div class="modal-body">
					<iframe id="frame-booking-info" v-on:load="showBookingInfoModal" src=""></iframe>
				</div>
			</div>
		</div>
	</div>
	
</div>
	@endverbatim
@endsection

@section('css-custom')
<style>
#cal-date-{{ date('Y-m-d') }} { background-color: #acf; }
.tourbookingButton{
	font-size: 13px;
    color: #0088cc;
    background: #ecf9ff;
    border: 1px solid #0088cc;
    border-radius: 3px;
    padding: 6px 16px;
    height: 32px;
	float: right;
	margin-top:-13px;
}
.tourbookingButton:hover{
	font-size: 13px;
    color: #0088cc;
    background: #ecf9ff;
    border: 1px solid #0088cc;
    border-radius: 3px;
    padding: 6px 16px;
    height: 32px;
	float: right;
}
</style>

<link href="{{ asset('css/user-common/tour-calendar.css') }}" rel="stylesheet" />
@endsection

@section('js-libraries')
	@if(App::environment('local'))
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
<script src="{{ asset('plugins/vue-2.6.14.js') }}"></script>
	@else
<script src="https://momentjs.com/downloads/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vue@2/dist/vue.js"></script>
	@endif
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
<script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
<script src="{{ asset('plugins/pusher.js') }}"></script>
@endsection

@section('js-custom')
<script>
var serverDT = moment('{{ date('Y-m-d H:i:s') }}';
var serverTZ = '{{ date('P') }}';

setInterval(function() {
	serverDT.add(15, 'seconds');
}, 15000);

Pusher.log = function(msg) {
}
var path = '{{ config('app.url') }}';
if($(location).attr('href') == path + "/affiliate/booking/calendar"){
	var dayTimeSlotsUrl = "{{ route('affiliate.get-tour-slot-times-by-date') }}";
	var getAffiliateBookingsUrl = "{{ route('affiliate.get-affiliate-bookings', [ 'timeSlotId' => 0 ]) }}";
	var bookingInfoPageUrl = "{{ route('affiliate.booking.show', [ 'booking' => 0 ]) }}";
	var saveAffiliateBookingUrl = "{{ route('affiliate.booking.save-affiliate-booking') }}";
	var timeSlotBookingsInfoUrl = "{{ route('affiliate.get-tour-slot-time-bookings-info', [ 'timeSlotId' => 0 ]) }}";
	var timeSlotTouristsInfoUrl = "{{ route('affiliate.get-tour-slot-time-tourists-info', [ 'timeSlotId' => 0 ]) }}";
	var calendarDateSlotsUrl = "{{ route('affiliate.booking.calendar-ahr') }}";
	var newBookingPageUrl = "{{ route('affiliate.booking.create') }}";
	var bookingSearchUrl = "{{ route('affiliate.search-booking') }}";
}else{
	var dayTimeSlotsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-times-by-date') }}";
	var getAffiliateBookingsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-affiliate-bookings', [ 'timeSlotId' => 0 ]) }}";
	var bookingInfoPageUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.show', [ 'booking' => 0 ]) }}";
	var saveAffiliateBookingUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.save-affiliate-booking') }}";
	var timeSlotBookingsInfoUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-time-bookings-info', [ 'timeSlotId' => 0 ]) }}";
	var timeSlotTouristsInfoUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-time-tourists-info', [ 'timeSlotId' => 0 ]) }}";
	var calendarDateSlotsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.calendar-ahr') }}";
	var newBookingPageUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.create') }}";
	var bookingSearchUrl = "{{ route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'search-booking') }}";
}



function paramsToSearchBooking() {
	return {
		search: $('#search').val(),
	@if($uTypeAdmin)
		tourOperatorId: calApp.tourOperator
	@endif
	}
}

function paramsToGetCalendarDateSlots() {
	var params = {
		yearMonth: '' + calInfo.year + (calApp.month + 1), {{-- added blank string to prevent making sum of year and month --}}
	@if($uTypeAdmin)
		tourOperatorId: calApp.tourOperator //$('#tour_operator').val(),
	@endif
	};

	return params;
}

function eventBookingNew(notif) {
	@if($uTypeAdmin)
	if(calApp.tourOperator == notif.booking.tour_operator_id || calApp.tourOperator == 0) {
	@endif
		calApp.tourBookings.unshift(notif.booking);
		updateSeatsInCal(notif.booking);
	@if($uTypeAdmin)
	}
	@endif
}

function eventBookingCancelled(notif) {
	@if($uTypeAdmin)
	if(calApp.tourOperator == notif.booking.tour_operator_id || calApp.tourOperator == 0) {
	@endif
		for(var a = 0; a < calApp.tourBookings.length; a++) {
			if(calApp.tourBookings[a].id == notif.booking.id) {
				calApp.tourBookings.splice(a, 1);
				break;
			}
		}

		notif.booking.tourists *= -1; // set to negative so the updateSeatsInCal() can update, without creating separate code for cancelled booking
		updateSeatsInCal(notif.booking);
	@if($uTypeAdmin)
	}
	@endif
}

function eventBookingAffiliate(notif) {
	@if($uTypeAdmin)
	if(calApp.tourOperator == notif.booking.tour_operator_id || calApp.tourOperator == 0) {
		var a = 0;

		for(; a < calApp.tourBookings.length; a++) {
			if(calApp.tourBookings[a].id == notif.booking.id) { // booking found
				Vue.set(calApp.tourBookings, 1, notif.booking);
				break;
			}
		}

		if(a >= calApp.tourBookings.length) // booking not found
			calApp.tourBookings.unshift(notif.booking);
	@endif
		var booking = JSON.parse(JSON.stringify(notif.booking));
		booking.tourists = booking.seatsDiff;
		// ^ create new object to disconnect from tour bookings array
		updateSeatsInCal(notif.booking);
	@if($uTypeAdmin)
	}
	@endif
}
</script>

	@if($uTypeAdmin)
<x-admin-js />
	@endif

<script src="{{ asset('js/user-common/tour-calendar.js') }}"></script>

<script>
calApp.calMonthDaysSchedules = funCalMonthDaysSchedules(draftTourSlots);

//listener.private('bookings')
if($(location).attr('href') == "http://127.0.0.1:8083/affiliate/booking/calendar"){
	var broadcastingChannel = listener.channel('bookings.affiliate.{{ auth()->user()->tour_operator_id }}');
		broadcastingChannel.listen('.booking-new', eventBookingNew);
		broadcastingChannel.listen('.booking-cancelled', eventBookingCancelled);
		broadcastingChannel.listen('.booking-affiliate', eventBookingAffiliate);

	}else{
		var broadcastingChannel = listener.channel('bookings.{{ $uTypeAdmin ? 'admin' : auth()->user()->tour_operator_id }}');
		broadcastingChannel.listen('.booking-new', eventBookingNew);
		broadcastingChannel.listen('.booking-cancelled', eventBookingCancelled);
		broadcastingChannel.listen('.booking-affiliate', eventBookingAffiliate);

	}
</script>
@endsection
