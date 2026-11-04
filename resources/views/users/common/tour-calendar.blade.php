@extends('layouts.default-2')

@php
	$userType = userType();
	$uTypeAdmin = session('userType') == 'Admin';
    $uTypeAffiliate = session('userType') == 'Affiliate';
	$domain = url('/');
	$currencySettings = [
		'code' => currencyFormation(true, false),
		'symbol' => currencyFormation(false, true),
		'separator' => ',',
		'decimal' => '.',
		'precision' => 2,
	];
@endphp

@section('main-content')
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
<div id="calendar-vue-app" class="max-w-[1600px] mx-auto" v-cloak>
	<div class="py-6 bg-white border-b border-[#E5E7EB]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full max-w-full text-center px-3">
					<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 flex-wrap">
						<div class="flex items-center gap-2">
						<h1 class="font-semibold text-2xl max-[1350px]:text-lg text-[#111827]">Tour Calendar</h1>
					<?php if(isPermissionGranted() === false){ ?>
						<div id="switch-company-wrapper"
						class="relative hidden"
						onmouseenter="showTenantDropdown()"
						onmouseleave="hideTenantDropdown()">

					<a class="flex items-center gap-2 px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="javascript:void(0);">
						<i class="fas fa-fw fa-building"></i>
						<span>
							Switch Company
							<svg class="inline-block ms-1" width="12" height="12" viewBox="0 0 14 15" fill="none">
								<path d="M1.4 4.7L7 10.3L12.6 4.7"
									stroke="#9CA3AF"
									stroke-width="2"
									stroke-linecap="round"
									stroke-linejoin="round" />
							</svg>
						</span>
					</a>
						<ul id="tenant-dropdown"
							class="hidden absolute right-0 top-full mt-2 z-[999] bg-white border py-1 min-w-[220px] shadow-md max-h-[260px] rounded overflow-y-auto"
							onmouseenter="showTenantDropdown()"
							onmouseleave="hideTenantDropdown()">
							<!-- JS populates tenants -->
						</ul>
					</div>
					<?php } ?>
					</div>
						<!-- Filters -->
						<div class="flex flex-col sm:flex-row sm:items-center gap-3 max-w-full">
							<select v-on:change="getTourSlots(this)" class="form-select select2 border border-black rounded-md p-[8px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black h-auto max-h-10" id="packageId" name="days[]" multiple>
								<option v-for="(package, index) in packages" :value="package.id">{{ package.name }}</option>
							</select>
							<span class="flex items-center max-[639px]:justify-center">
								<button type="button" class="btn rounded-circle mx-2 btn-prev-month w-10 h-10 border border-black rounded-full" v-on:click="getTourSlots('previous')">
									<svg class="inline-block" width="8" height="16" viewBox="0 0 10 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.16658 18.3332L0.833252 9.99984L9.16658 1.6665" stroke="#000000" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</button>

								<button type="button" class="btn rounded-circle mx-2 btn-next-month w-10 h-10 border border-black rounded-full" v-on:click="getTourSlots('next')">
									<svg class="inline-block" width="8" height="16" viewBox="0 0 10 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.833415 18.3332L9.16675 9.99984L0.833415 1.6665" stroke="#000000" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/></svg>
								</button>
							</span>

							<select id="display_option" class="form-select border border-black rounded-md p-[8px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black h-auto" v-on:change="getTourSlots(this)">
								<option :value="option.id" v-for="option in options">{{ option.name }}</option>
							</select>

							<select v-model="month" class="form-select border border-black rounded-md p-[8px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black h-auto" v-on:change="getTourSlots(this)">
								<option v-for="(month, index) in months" :value="index">{{ month }}</option>
							</select>

							<select id="sel-cal-year" class="form-select border border-black rounded-md p-[8px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black h-auto" v-on:change="getTourSlots('year')">
								<?php
								for($a = $year + 1, $ac = $year - 2; $a > $ac; $a--)
									echo "<option" . ($a == $year ? ' selected' : '') . ">$a</option>";
								?>
							</select>

							<select v-if="partnerships.length" v-model="partner" v-on:change="switchCompany(this.value)" class="form-select border border-black rounded-md p-[8px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black h-auto" id="partnerId" name="partner">
								<option v-for="(partner, index) in partnerships" :value="partner.tenant_id" :selected="partner.tenant_id == tenantId">{{ partner.tenant_name }}</option>
							</select>

							<?php if($uTypeAdmin) { ?>
								<div class="col-12 col-md-5" style="display: none;">
									<select id="tour_operator" v-model="tourOperator" class="form-select border border-black rounded-md p-[8px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black h-auto" v-on:change="getTourSlots(this)">
										<option v-for="(tourOperator, index) in tourOperators" :value="tourOperator.id">{{ tourOperator.name }}</option>
									</select>
								</div>
							<?php } ?>

							
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="flex flex-wrap pb-5">
		<div class="w-full lg:w-2/3 xl:w-4/5 max-w-full data-col order-1"> <!-- calendar and its options - start -->
			<div class="w-full overflow-x-auto">
				<div class="cal-table table w-full min-h-[560px]" id="calendar-table">
					<div class="cal-week-days w-full grid border-l border-[#E5E7EB]" style="grid-template-columns: repeat(7, minmax(10rem, 1fr));">
						<div class="cal-week-day min-w-40 bg-[#F9FAFB] text-sm text-[#374151] px-[10px] py-3 text-center border-b border-[#E5E7EB]" v-for="weekDay in days" style="min-width:160px;">{{ weekDay }}</div>
					</div>

					<div class="cal-month-days w-full grid" style="grid-template-columns: repeat(7, minmax(10rem, 1fr));">
						<!-- --->
						<div :class="[ 'cal-month-day relative hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out', day.class ]" v-for="(day, dayIdx) in calMonthDaysSchedules" :key="day.date" :id="day.id" style="min-width:160px;">
							<div class="cal-day-num-div text-lg font-semibold text-[#111827] min-w-40 min-h-12 p-[10px] border-s border-[#E5E7EB]" v-if="(option == 'week' || option == 'month') && !!(day.num == day.weekdate)">
								<span class="cal-day-num">{{ day.num }}</span>
							</div>

							<div class="cal-day-schedules min-w-40 border-s border-b border-[#E5E7EB]"
								:class="{
									'h-full': !((option == 'week' || option == 'month') && !!(day.num == day.weekdate)),
									'h-[calc(100%-48px)]': (option == 'week' || option == 'month') && !!(day.num == day.weekdate)
								}">
								<div v-for="(schedule, schIdx) in day.schedules.slice(0, 3)" :key="schedule.id" v-on:click="dayPkgSlotTimesModal(dayIdx, schIdx)" :class="['cal-package bg-[#F9FAFB] p-1 rounded mx-[10px] mb-1 cursor-pointer', 'cal-package-' + schedule.pkg_id ]">
									<div class="cal-package-title text-xs text-[#2C2421] font-medium">{{ schedule.pkg_name }}</div>
									<div :data-testid=`${schedule.pkg_name}-${day.date}` class="cal-seats-info">
										<!-- <span class="cal-b">B</span> -->
										<span :data-testid=`${schedule.pkg_name}-${day.date}-seats-filled` :class="[ 'cal-seats-filled bg-[#DBEAFE] text-[#1E40AF] text-xs font-bold py-[2px] px-2 mr-1 rounded', schedule.tourists > 0 ? 'info-text-color info-bg-color info-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ schedule.booked_seats }}</span>
										<span :data-testid=`${schedule.pkg_name}-${day.date}-seats-empty` :class="[ 'cal-seats-empty bg-[#DCFCE7] text-[#166534] text-xs font-bold py-[2px] px-2 mr-1 rounded', schedule.available_seats > 0 ? 'success-text-color success-bg-color success-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ schedule.available_seats }}</span>
										<span :class="[ 'cal-seats-overbooked bg-[#F3F4F6] text-[#374151] text-xs font-bold py-[2px] px-2 rounded', schedule.over_booking > 0 ? 'alert-text-color alert-bg-color alert-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ (schedule.over_booking != null) ? schedule.over_booking : 0 }}</span>
									</div>
									<div class="cal-seats-progress hidden">
										<div :style="{ width: ((schedule.tourists * 100) / schedule.seats) + '%' }"></div>
									</div>
								</div>
								<div v-if="day.schedules.length > 3" class="view-all-booking text-sm font-medium m-[10px] mt-0 text-center text-[#6B7280] cursor-pointer">View all tours</div>
							</div>
							<div class="cal-day-schedules day-schedules-popup package-wrap absolute top-1 w-56 shadow-[0px_7px_7px_0px_rgba(0,0,0,0.25)] bg-white rounded-lg p-3 hidden max-h-[35.5rem] overflow-y-auto overflow-x-hidden border border-[#E5E7EB] !opacity-100">
								<div class="text-xs font-bold text-[#2C2421] mb-2 inline-block">{{ day.date }}</div>
								<div class="absolute top-3 right-3 w-4 h-4 close-schedules-popup cursor-pointer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></div>
								<div v-for="(schedule, schIdx) in day.schedules" :key="schedule.id" v-on:click="dayPkgSlotTimesModal(dayIdx, schIdx)" :class="['cal-package bg-[#F9FAFB] p-1 rounded mb-1 cursor-pointer', 'cal-package-view-more' + schedule.pkg_id ]">
									<div class="cal-package-title text-xs text-[#2C2421] font-medium">{{ schedule.pkg_name }}</div>
									<div :data-testid=`${schedule.pkg_name}-${day.date}` class="cal-seats-info">
										<!-- <span class="cal-b">B</span> -->
										<span :data-testid=`${schedule.pkg_name}-${day.date}-seats-filled` :class="[ 'cal-seats-filled-view-more bg-[#DBEAFE] text-[#1E40AF] text-xs font-bold py-[2px] px-2 mr-1 rounded', schedule.tourists > 0 ? 'info-text-color info-bg-color info-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ schedule.booked_seats }}</span>
										<span :data-testid=`${schedule.pkg_name}-${day.date}-seats-empty` :class="[ 'cal-seats-empty-view-more bg-[#DCFCE7] text-[#166534] text-xs font-bold py-[2px] px-2 mr-1 rounded', schedule.available_seats > 0 ? 'success-text-color success-bg-color success-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ schedule.available_seats }}</span>
										<span :class="[ 'cal-seats-overbooked-view-more bg-[#F3F4F6] text-[#374151] text-xs font-bold py-[2px] px-2 rounded', schedule.over_booking > 0 ? 'alert-text-color alert-bg-color alert-border-color' : 'gray-text-color gray-bg-color gray-border-color' ]">{{ (schedule.over_booking != null) ? schedule.over_booking : 0 }}</span>
									</div>
									<div class="cal-seats-progress-view-more hidden">
										<div :style="{ width: ((schedule.tourists * 100) / schedule.seats) + '%' }"></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div> <!-- /.cal-table -->
			</div>
		</div> <!-- calendar and its options - end -->

		<div class="w-full lg:w-1/3 xl:w-1/5 max-w-full position-relative data-col order-2 border-l border-b border-[#E6E6E6] overflow-x-auto">
			<div class="bg-[#FAFAFA] p-2 sticky top-0">
				<input id="search" class="form-control txt-search text-sm text-[#111827] font-medium border border-black py-[9px] pl-10 pr-3 rounded-lg w-full outline-none" title="Enter at least 2 characters for searching">
				<input type="hidden" id="currency" value='@json($currencySettings)'>
			</div>
			<div class="bg-[#FAFAFA] p-2 h-full">
				

				<div class="bookings-list"> <!-- bookings - start -->
					<div class="searching text-center flex justify-center items-center gap-2.5 mb-2.5" v-if="searching">
						<img :src="rootPath + imgsPath.loadingAnim" class="align-text-top"> Searching
					</div>

					<div v-if="searchDone && tourBookings.length == 0" class="text-center">No record found</div>

					<div :class="[ 'booking-info border border-[#f5f5f5] rounded-xl mb-2 p-3 transition-all duration-300 ease-in-out cursor-pointer', booking.background_colour_class ]" v-for="(booking, index) in tourBookings" :key="'sidebar-' + booking.id + '-' + index + '-' + (booking.payment_method || 'none') + '-' + (booking.paid || 0)" :id="'booking-' + booking.id" v-on:click="initBookingInfoModal(booking.id, 'sidebar')">
						<div class="flex items-center justify-between mb-1">
							<div class="flex items-center">
								<a class="text-sm text-[#111827] font-medium mr-2" v-on:click.prevent="initBookingInfoModal(booking.id, 'modal')" href="#">{{ booking.cust_name }}</a>
								<!-- Dispute Indicator -->
								<span v-if="booking.has_active_dispute && booking.dispute_info" 
									  :class="['dispute-indicator text-xs font-semibold px-2 py-1 rounded-full border mr-2', booking.dispute_info.css_class]"
									  :title="getDisputeTooltip(booking.dispute_info)"
									  data-bs-toggle="tooltip"
									  data-bs-placement="top">
									<i class="fa fa-exclamation-triangle mr-1"></i>
									{{ booking.dispute_info.status_label }}
								</span>
							</div>
							<div class="flex items-center">
								<span class="text-xs font-semibold text-[#374151] px-2 py-1 bg-[#F3F4F6] border border-[#E6E6E6] rounded-full whitespace-nowrap">
									{{ booking.prefixedId }} 
									<span v-if="booking.is_tripadvisor_booking"><img class="tripadv-img inline-block max-h-full max-w-[22px] rounded-[4px] align-middle" :src="rootPath + imgsPath.otaImage + booking.ota_logo" :alt="booking.ota_title"></span>
									<span v-if="booking.is_get_your_guide_booking"><img class="gyg-img inline-block max-h-full max-w-[22px] rounded-[4px] align-middle" :src="rootPath + imgsPath.otaImage + booking.ota_logo" :alt="booking.ota_title"></span>
									<span v-if="booking.is_ota_booking" class="ms-1"><img class="ota-img" :src="rootPath + imgsPath.otaImage + booking.ota_logo" :alt="booking.ota_title"></span>
								</span>
								<span class="bookingnum text-xs text-[#374151] font-semibold border border-[#E6E6E6] rounded-full py-1 px-2 ml-2 whitespace-nowrap"><i class="fa fa-user"></i> {{ booking.tourists + booking.infants }}</span>
							</div>
						</div>

						<div class="bk-inf-cust-nm-out flex items-center justify-between mb-1">
							<p class="bookingspace text-xs text-[#6B7280]">[ {{ booking.t_groups }} ]</p>
							
							<div class="flex items-center whitespace-nowrap ml-2">
								<div v-if="(booking.status !== 'Cancelled' && booking.RefundAmount > 0 && booking.paidF != booking.totalF) || (booking.status === 'Cancelled' && booking.RefundAmount > 0 && booking.PaymentTotal > 0)" v-bind:class="[ 'float-end badge', 'red-bg-color text-xs font-semibold text-[#D63A1F] mr-2' ]">{{ 'Partial Refund' }}</div>
								<div v-else-if="booking.status === 'Cancelled' && booking.RefundAmount > 0" v-bind:class="[ 'float-end badge', 'red-bg-color text-xs font-semibold text-[#D63A1F] mr-2' ]">{{ 'Refunded' }}</div>
								<div v-else-if="booking.paid_split !== null && parseFloat(booking.total) > parseFloat(booking.paid) && booking.paidF != booking.totalF" v-bind:class="[ 'float-end badge', 'red-bg-color text-xs font-semibold text-[#D63A1F] mr-2' ]">{{ 'Split' }}</div>
								<div v-else-if="booking.paid != 0 && booking.paid != null && booking.payment_method == 'Invoiced' && booking.invoiceStatus == '0'" v-bind:class="[ 'float-end badge', 'red-bg-color text-xs font-semibold text-[#D63A1F] mr-2' ]">{{ currencyFormat(booking.total) }}</div>
								<div v-else-if="booking.paid != 0 && booking.paid != null && booking.payment_method == 'Invoiced' && booking.invoiceStatus !== 'paid'" v-bind:class="[ 'float-end badge', 'red-bg-color text-xs font-semibold text-[#D63A1F] mr-2' ]">{{ currencyFormat(booking.total) }}</div>
								<div v-else v-bind:class="[ 'float-end badge', (booking.paid != 0 && booking.paid != null || booking.paid >= booking.total) ? 'green-bg-color  text-xs font-medium text-[#047857] mr-2' : 'red-bg-color text-xs font-semibold text-[#D63A1F] mr-2' ]">{{ booking.paid | formatAmt(booking.total) }}</div>

								<div v-if ="booking.payment_method == 'Invoiced' && booking.status !== 'Cancelled' && booking.invoiceStatus !== '0'" v-bind:class="[ 'float-end badge', 'green-bg-color text-xs font-semibold text-[#047857] px-2 py-1 bg-[#ECFDF5] border border-[#A7F3D0] rounded-full' ]">
									{{ "Invoiced"}}
								</div>
								<div v-else-if ="booking.payment_method == 'Invoiced' && booking.status !== 'Cancelled' && booking.invoiceStatus == '0'" v-bind:class="[ 'float-end badge', 'billable-bg-color text-xs font-semibold text-[#000000] px-2 py-1 bg-[#edc13f] border border-[#edc13f] rounded-full' ]">
									{{ "Billable"}}
								</div>
								<div v-else v-bind:class="[ 'float-end badge', (['Card','Cash','Invoiced',...otaPlatforms].indexOf(booking.payment_method) !== -1 && booking.status !== 'Cancelled') ? 'green-bg-color text-xs font-semibold text-[#047857] px-2 py-1 bg-[#ECFDF5] border border-[#A7F3D0] rounded-full' : 'red-bg-color jkl text-xs font-semibold text-[#D63A1F] px-2 py-1 bg-[#FFF7ED] border border-[#FED7AA] rounded-full' ]">
									{{ booking.status === 'Cancelled' ? booking.status : ((['Card','Cash','Invoiced','Payment Link',...otaPlatforms].indexOf(booking.payment_method) !== -1) ? booking.payment_method : 'Pay Later') }}
								</div>
							</div>
						</div>

						<div class="bk-inf-pkg-nm text-sm font-semibold text-[#EE652B] mb-2">{{ booking.pkg_name }}</div>
						<div v-if="booking.package_has_slots" class="bk-inf-t-time text-xs font-medium text-[#7C706A] mb-1">{{ booking.tour_d_t | formatTourDT }}</div>
						<div v-else class="bk-inf-t-time text-xs font-medium text-[#7C706A] mb-1">{{ booking.tour_d_t | formatTourDateOnly }}</div>
						<div class="bk-inf-bk-time text-xs font-medium text-[#7C706A] mb-1">Booked on {{ booking.booking_d_t | formatBookingDT }}</div>
					</div>
				</div> <!-- bookings - end -->
			</div>
		</div> <!-- bookings sidebar - end -->
	</div>

	

	<div class="modal fade cal-bookings-modal fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="time-slot-bookings" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen max-w-[1024px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between relative">
					<div class="header-info flex flex-wrap items-center -mx-3 w-full">
						<div class="w-full md:w-1/2 max-w-full px-3 max-[767px]:mb-2">							
							<h3 class="text-lg font-semibold text-[#111827] mb-1">{{ dayPkgSlotInfo.isToday ? 'Today' : dayPkgSlotInfo.displayDate }}</h3>	
							<div class="flex items-center text-lg text-[#111827] mb-1">
								<button id="prevSlotBtn" type="button" v-if="dayPkgSlotInfo.packageHasSlots" class="btn rounded-circle mr-2 btn-prev-month w-8 h-8 min-w-8 min-h-8 border-2 border-black rounded-full"><svg width="8" height="16" viewBox="0 0 10 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block max-h-3 align-baseline"><path d="M9.16658 18.3332L0.833252 9.99984L9.16658 1.6665" stroke="#000000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
								 <span v-if="dayPkgSlotInfo.packageHasSlots">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot | formatSlotTimeDuration }}</span>
								<button id="nextSlotBtn" type="button" v-if="dayPkgSlotInfo.packageHasSlots" class="btn rounded-circle ml-2 btn-next-month w-8 h-8 min-w-8 min-h-8 border-2 border-black rounded-full"><svg width="8" height="16" viewBox="0 0 10 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block max-h-3 align-baseline"><path d="M0.833415 18.3332L9.16675 9.99984L0.833415 1.6665" stroke="#000000" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
							</div>							
							<span class="text-lg font-medium text-[#7C706A]">{{ dayPkgSlotInfo.pkg_name }}</span>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="flex items-center justify-between md:justify-end">
								<div>
									<?php if (request()->user()->can('agenda')) { ?>
										<a :href="dayPkgSlotInfo.link + dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.id" id="popup_booking" class="btn btn-secondary tourbookingButton  mr-2 text-xs text-[#020817] font-semibold shadow-sm rounded-lg px-4 py-2 bg-white border border-[#E2E8F0] hover:border-[#E2E8F0] hover:bg-[#E2E8F0] hover:text-[#020817] inline-flex gap-1.5 transition-all duration-300 ease-in-out"  target="_blank"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 4.66406V13.9974" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.0026 12C1.82579 12 1.65622 11.9298 1.5312 11.8047C1.40618 11.6797 1.33594 11.5101 1.33594 11.3333V2.66667C1.33594 2.48986 1.40618 2.32029 1.5312 2.19526C1.65622 2.07024 1.82579 2 2.0026 2H5.33594C6.04318 2 6.72146 2.28095 7.22156 2.78105C7.72165 3.28115 8.0026 3.95942 8.0026 4.66667C8.0026 3.95942 8.28356 3.28115 8.78365 2.78105C9.28375 2.28095 9.96203 2 10.6693 2H14.0026C14.1794 2 14.349 2.07024 14.474 2.19526C14.599 2.32029 14.6693 2.48986 14.6693 2.66667V11.3333C14.6693 11.5101 14.599 11.6797 14.474 11.8047C14.349 11.9298 14.1794 12 14.0026 12H10.0026C9.47217 12 8.96346 12.2107 8.58839 12.5858C8.21332 12.9609 8.0026 13.4696 8.0026 14C8.0026 13.4696 7.79189 12.9609 7.41682 12.5858C7.04175 12.2107 6.53304 12 6.0026 12H2.0026Z" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg> Agenda</a>
									<?php } ?>
									<?php if (request()->user()->can('closure-email')) { ?>
										<span id="lower_antelope_canyon_closure" onclick="return lowerAntelopeCanyonClosure(this)" :data-date="dayPkgSlotInfo.date" :data-slot-id="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.id" :data-tour-operator-id="dayPkgSlotInfo.tour_op_id" :data-tour-package-id="dayPkgSlotInfo.pkg_id" :data-tour-operator-name="dayPkgSlotInfo.tour_op_name" class="btn btn-secondary tourbookingButton  mr-2 text-xs text-[#020817] font-semibold shadow-sm rounded-lg px-4 py-2 bg-white border border-[#E2E8F0] hover:border-[#E2E8F0] hover:bg-[#E2E8F0] hover:text-[#020817] inline-flex gap-1.5 transition-all duration-300 ease-in-out cursor-pointer" style="margin-right: 10px;"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.3359 2.66406H2.66927C1.93289 2.66406 1.33594 3.26102 1.33594 3.9974V11.9974C1.33594 12.7338 1.93289 13.3307 2.66927 13.3307H13.3359C14.0723 13.3307 14.6693 12.7338 14.6693 11.9974V3.9974C14.6693 3.26102 14.0723 2.66406 13.3359 2.66406Z" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.6693 4.66406L8.68927 8.46406C8.48345 8.59301 8.24548 8.6614 8.0026 8.6614C7.75973 8.6614 7.52176 8.59301 7.31594 8.46406L1.33594 4.66406" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg> Closure Email</span>
									<?php } ?>
								</div>
							</div>
						</div>
					</div>
					<div class="btn-close absolute top-5 right-5 max-[1023px]:ml-auto">
						<button v-if="dayPkgSlotInfo.packageHasSlots" type="button" id="btset" class="btn-close" v-on:click="safeHideTimeSlotBookings();safeShowDayPkgSlots()" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
						<button v-else type="button" id="btset" class="btn-close" v-on:click="safeHideTimeSlotBookings();safeHideDayPkgSlots()" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
					</div>
				</div>

				<div class="modal-body p-4">
					<div class="booked-tickets">
						<div class="filter-row lg:flex lg:items-center lg:justify-between">
							<div class="rowone flex items-center justify-center mb-2">
								<h2 class="text-lg lg:text-2xl text-[#111827] mb-2 mr-3">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.booked_seats }} Booked</h2>
								<div class="seats border border-[#E6E6E6] rounded-full py-1 px-3 flex items-center justify-center mr-2 mb-2">
									<span class="bookingnum text-xs text-[#374151]" id="bookedtouristsCount">
										<svg class="inline-block mr-1 align-text-top" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_29_2)"><path d="M8.00196 10.5V9.5C8.00196 8.96956 7.79124 8.46084 7.41616 8.0858C7.04108 7.71072 6.5324 7.5 6.00196 7.5H3.00195C2.47152 7.5 1.96281 7.71072 1.58774 8.0858C1.21267 8.46084 1.00195 8.96956 1.00195 9.5V10.5" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.50196 5.5C5.60652 5.5 6.50196 4.60456 6.50196 3.5C6.50196 2.39543 5.60652 1.5 4.50196 1.5C3.39739 1.5 2.50195 2.39543 2.50195 3.5C2.50195 4.60456 3.39739 5.5 4.50196 5.5Z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.998 10.4995V9.49945C10.9977 9.05633 10.8502 8.62585 10.5787 8.27561C10.3072 7.92541 9.92713 7.67525 9.49805 7.56445" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.99805 1.56445C8.42825 1.67461 8.80957 1.92481 9.08189 2.27561C9.35417 2.62641 9.50197 3.05787 9.50197 3.50195C9.50197 3.94604 9.35417 4.37748 9.08189 4.72828C8.80957 5.07912 8.42825 5.32932 7.99805 5.43944" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_29_2"><rect width="12" height="12" fill="white"/></clipPath></defs></svg>
										{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.booked_seats }}
									</span>
									<span class="bookingnum text-xs text-[#374151] ml-1" id="cancelledtouristsCount" hidden>
										<svg class="inline-block mr-1 align-text-top" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_29_2)"><path d="M8.00196 10.5V9.5C8.00196 8.96956 7.79124 8.46084 7.41616 8.0858C7.04108 7.71072 6.5324 7.5 6.00196 7.5H3.00195C2.47152 7.5 1.96281 7.71072 1.58774 8.0858C1.21267 8.46084 1.00195 8.96956 1.00195 9.5V10.5" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.50196 5.5C5.60652 5.5 6.50196 4.60456 6.50196 3.5C6.50196 2.39543 5.60652 1.5 4.50196 1.5C3.39739 1.5 2.50195 2.39543 2.50195 3.5C2.50195 4.60456 3.39739 5.5 4.50196 5.5Z" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.998 10.4995V9.49945C10.9977 9.05633 10.8502 8.62585 10.5787 8.27561C10.3072 7.92541 9.92713 7.67525 9.49805 7.56445" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M7.99805 1.56445C8.42825 1.67461 8.80957 1.92481 9.08189 2.27561C9.35417 2.62641 9.50197 3.05787 9.50197 3.50195C9.50197 3.94604 9.35417 4.37748 9.08189 4.72828C8.80957 5.07912 8.42825 5.32932 7.99805 5.43944" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_29_2"><rect width="12" height="12" fill="white"/></clipPath></defs></svg>
										{{ dayPkgSlotInfo.timeSlotBookingsInfo.cancelledtouristsCount }}
									</span>
									<p class="bookingspace text-xs text-[#374151] ml-1" id="bookedCount">[ {{ dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfo | shortAgeGrpAndBookedSeatsInfo }} ]</p>
									<p class="bookingspace text-xs text-[#374151] ml-1" id="cancelledCount" hidden>[ {{ dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfoCancelled | shortAgeGrpAndBookedSeatsInfoCancelled }} ]</p>
								</div>
							</div>

							<div class="rowtwo flex items-center gap-2 mb-2 flex-wrap lg:flex-nowrap">
								<div class="status-warp">
									<div class="dropdown relative max-[767px]:mb-2">
										<button :class="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2 | getBookableStatusCls" class="text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 whitespace-nowrap max-[767px]:w-full" id="btn-dd-bookable-status" data-bs-toggle="dropdown" aria-expanded="true">
											<span v-if="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2 === 'Open'">
												<svg class="inline-block align-text-top mr-1" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.65546 4.09616C8.83856 4.27927 8.83856 4.57608 8.65546 4.75909L5.5108 7.90384C5.3277 8.08685 5.03098 8.08685 4.84787 7.90384L3.35089 6.40677C3.16779 6.22375 3.16779 5.92694 3.35089 5.74393C3.53391 5.56082 3.83072 5.56082 4.01373 5.74393L5.17929 6.90948L7.99252 4.09616C8.17563 3.91315 8.47244 3.91315 8.65546 4.09616ZM12.0032 6C12.0032 9.3165 9.31921 12 6.00317 12C2.68668 12 0.00317383 9.31604 0.00317383 6C0.00317383 2.6835 2.68713 0 6.00317 0C9.31967 0 12.0032 2.68396 12.0032 6ZM11.0657 6C11.0657 3.20169 8.80112 0.9375 6.00317 0.9375C3.20486 0.9375 0.940674 3.20206 0.940674 6C0.940674 8.79831 3.20523 11.0625 6.00317 11.0625C8.80148 11.0625 11.0657 8.79794 11.0657 6Z" fill="currentColor"/></svg>
											</span>
											<span v-else-if="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2 === 'Closed'">
												<svg class="inline-block align-text-top mr-1" width="16" height="16" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.86377 6H2.86377C2.31148 6 1.86377 6.44772 1.86377 7V10.5C1.86377 11.0523 2.31148 11.5 2.86377 11.5H9.86377C10.4161 11.5 10.8638 11.0523 10.8638 10.5V7C10.8638 6.44772 10.4161 6 9.86377 6Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.86377 6V4C3.86377 3.33696 4.12716 2.70107 4.596 2.23223C5.06484 1.76339 5.70073 1.5 6.36377 1.5C7.02681 1.5 7.6627 1.76339 8.13154 2.23223C8.60038 2.70107 8.86377 3.33696 8.86377 4V6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>
											</span>
											<span v-else>
												<svg class="inline-block align-text-top mr-1" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 11.625C2.6915 11.625 0 9.1017 0 6C0 2.89828 2.6915 0.375 6 0.375C9.30848 0.375 12 2.89828 12 6C12 9.1017 9.30848 11.625 6 11.625ZM6 1.3125C3.243 1.3125 1 3.41531 1 6C1 8.58465 3.243 10.6875 6 10.6875C8.75696 10.6875 11 8.58465 11 6C11 3.41531 8.75696 1.3125 6 1.3125ZM7.644 6.52265L6.7565 7.35469C5.743 6.99234 4.996 6.31969 4.555 5.29078L5.4425 4.45875L4.0865 3.1875L3.3975 3.83344C3.1275 4.08609 3 4.4189 3 4.75078C3 6.58547 5.516 8.81295 7.333 8.81295C7.687 8.81295 8.042 8.69295 8.31152 8.44028L9.00048 7.79437L7.644 6.52265Z" fill="currentColor"/></svg>
											</span>
											<span class="bookable-status" v-text="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2"></span>
											<svg class="inline-block align-text-top ml-auto" width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="currentColor" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</button>

										<ul class="dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded border text-sm text-left" aria-labelledby="btn-dd-bookable-status">
											<li class="justify-content-start flex items-center px-4 py-2 text-xs text-[#4B5563] hover:text-[#F97316] cursor-pointer">
												{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status_2_reason }}
											</li>
										</ul>
									</div>
								</div>
								<!-- Visibility status button -->
								<?php if (request()->user()->can('tour-slots')) { ?>
								<div class="visibility-warp">
									<button type="button"
										:class="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.hide_slot | getVisibilityBtnCls"
										class="text-xs font-semibold border px-3 py-2.5 rounded-lg flex items-center gap-1.5 min-w-28 max-[767px]:w-full cursor-default"
										disabled>
										<span v-if="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.hide_slot == 1">
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.41333 9.41325C9.23056 9.60944 9.01128 9.76739 8.76823 9.87789C8.52517 9.98839 8.2634 10.0492 7.99724 10.057C7.73108 10.0647 7.46618 10.0192 7.21706 9.92325C6.96793 9.82728 6.73975 9.68278 6.54609 9.49831C6.35243 9.31384 6.19716 9.09298 6.08966 8.84876C5.98216 8.60453 5.92462 8.34184 5.92046 8.07557C5.9163 7.8093 5.96562 7.54496 6.06545 7.2975C6.16529 7.05005 6.31363 6.82443 6.50133 6.63392M11.96 11.96C10.8204 12.8286 9.43282 13.3099 8.0013 13.3333C3.33464 13.3333 0.667969 7.99992 0.667969 7.99992C1.49589 6.45396 2.64657 5.10469 4.0413 4.03992L11.96 11.96ZM6.60797 2.82659C7.06442 2.71895 7.53216 2.66512 8.0013 2.66659C12.668 2.66659 15.3346 7.99992 15.3346 7.99992C14.9287 8.75717 14.4443 9.46892 13.8893 10.1199L6.60797 2.82659ZM0.667969 0.666595L15.3346 15.3333" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</span>
										<span v-else>
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.33594 8C1.33594 8 3.33594 3.33337 8.0026 3.33337C12.6693 3.33337 14.6693 8 14.6693 8C14.6693 8 12.6693 12.6667 8.0026 12.6667C3.33594 12.6667 1.33594 8 1.33594 8Z" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8.0026" cy="8" r="2" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</span>
										<span v-text="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.hide_slot == 1 ? 'Hidden' : 'Visible'"></span>
									</button>
								</div>
								<?php } ?>
								<?php if(!$uTypeAffiliate){ ?>
								<div v-if="dayPkgSlotInfo.packageHasSlots" class="availability-warp">
									<div class="dropdown relative">
										<button :class="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot | getBookedSeatsCls" class="text-xs text-[#111827] border px-3 py-2.5 rounded-lg flex items-center items-flex whitespace-nowrap min-[1024px]:min-w-72 max-[1023px]:w-full font-medium" data-bs-toggle="dropdown" aria-expanded="false">
											<span :class="(dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.booked_seats > 0 ? 'text-[#000000]' : 'text-[#1D4ED8]')">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.booked_seats }} Booked</span> <span class="mx-3">|</span> 
											<span :class="dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot | availableSeatsInfoCls">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot | availableSeatsInfo }}</span> <span class="mx-3">|</span> 
											<span class="text-[#4B5563]">{{ dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.seats }} Total</span>
										</button>

										<div class="dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded border text-sm text-left">

											<div class="capacities-warp nobg flex items-center px-4 py-2 text-xs text-[#4B5563] hover:text-[#F97316] cursor-pointer" v-if="dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfo.length == 0">[ No booking in the slot ]</div>

											<div class="capacities-warp nobg flex items-center px-4 py-2 text-xs text-[#4B5563] hover:text-[#F97316] cursor-pointer" v-for="(touristsInfo, index) in dayPkgSlotInfo.timeSlotBookingsInfo.touristsInfo" :key="'tourists-' + index">
												<div class="title">{{ touristsInfo.rate_for }}</div>
												<div class="booked">{{ touristsInfo.tourists }}</div>
											</div>
										</div> <!-- /.dropdown-menu -->
									</div>
								</div> <!-- /.availability-warp -->
                            	<?php } ?>
								<button class="add-booking-btn btn-primary-small" target="_blank" v-on:click.prevent="initNewBookingModal" :disabled="(dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.available_tourists !== undefined ? dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.available_tourists <= 0 : dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.tourists >= dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.seats) || dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.bookable_status == 'Closed' || dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.affiliateFeature == 0">+ Book</button>
							</div>
						</div> <!-- /.filter-row -->

						<div class="filter-row2 sm:flex sm:items-center sm:justify-center lg:justify-start mb-2">
							<div class="flex items-center justify-center max-[639px]:mb-2 bg-[#eeeeef] p-0.5 rounded-full" id="booked-purchaseby">
								<input type="checkbox" id="purchaseby" class="checkPurchaseBy mr-2 w-4 h-4 rounded hidden" name="purchaseby" value="2" v-model="dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy" v-on:change="ascSortBookings" />
								<label for="purchaseby" class="form-custom-label !flex items-center cursor-pointer !m-0 px-5 py-1 rounded-full" :class="!dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy ? 'bg-[#ED9C45] form-custom-label-white' : 'bg-transparent'">
									<span>Alphabetical</span>
								</label>
								<label for="purchaseby" class="form-custom-label !flex items-center cursor-pointer !m-0 px-5 py-1 rounded-full" :class="dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy ? 'bg-[#ED9C45] form-custom-label-white' : 'bg-transparent'">
									<span>Purchase Date</span>
								</label>
							</div>

							<div class="flex items-center justify-center max-[639px]:mb-2 bg-[#eeeeef] p-0.5 rounded-full ml-2">
								<input type="checkbox" id="cancelledToggle" class="checkset mr-2 w-4 h-4 rounded hidden" name="checkbox" value="1" v-model="dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings" v-on:change="ascSortCancelled" />
								<label for="cancelledToggle" class="form-custom-label !flex items-center !m-0 px-5 py-1 rounded-full cursor-pointer transition" :class="!dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings ? 'bg-[#ED9C45] form-custom-label-white shadow-sm' : 'bg-transparent'">
									<span>Active Bookings</span>
								</label>

								<label for="cancelledToggle" class="form-custom-label !flex items-center !m-0 px-5 py-1 rounded-full cursor-pointer transition" :class="dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings ? 'bg-[#ED9C45] form-custom-label-white shadow-sm' : 'bg-transparent'">
									<span>Cancelled Bookings ({{ dayPkgSlotInfo.timeSlotBookingsInfo.cancelledCount }})</span>
								</label>
							</div>
						</div>
					</div> <!-- /.booked-tickets -->

					<div class="booking-warp">
						<div :class="[ 'details-warp border border-[#E6E6E6] rounded-md py-3 px-4 mb-2 md:flex md:items-center md:justify-between', booking.background_colour_class ]" v-for="(booking, index) in dayPkgSlotInfo.timeSlotBookingsInfo.bookings" :key="'modal-' + booking.id + '-' + index + '-' + (booking.payment_method || 'none') + '-' + (booking.paid || 0)" v-if="((booking.status == 'Booked' || booking.status == 'Completed') && !dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings)">
							<div class="details-name flex items-center max-[767px]:justify-center">
								<a class="text-base text-[#111827] font-medium mr-2" v-on:click.prevent="initBookingInfoModal(booking.id, 'modal')" href="#">{{ booking.cust_name }}</a>
								<!-- Dispute Indicator -->
								<span v-if="booking.has_active_dispute && booking.dispute_info" 
									  :class="['dispute-indicator text-xs font-semibold px-2 py-1 rounded-full border mr-2', booking.dispute_info.css_class]"
									  :title="getDisputeTooltip(booking.dispute_info)"
									  data-bs-toggle="tooltip"
									  data-bs-placement="top">
									<i class="fa fa-exclamation-triangle mr-1"></i>
									{{ booking.dispute_info.status_label }}
								</span>
								<a class="text-xs text-[#374151] font-semibold bg-[#F3F4F6] border border-[#E6E6E6] rounded-full py-1 px-2 mr-2" v-on:click.prevent="initBookingInfoModal(booking.id, 'modal')" href="#" class="bookingid">ID: #{{ booking.prefixedId }}</a>
								<span class="bookingnum text-xs text-[#374151] font-semibold border border-[#E6E6E6] rounded-full py-1 px-2 mr-2"><i class="fa fa-user mr-1"></i> {{ booking.tourists }}</span>
								<p class="bookingspace text-sm text-[#6B7280]">[ {{ booking.t_groups }} ]</p>
								<span v-if="booking.is_tripadvisor_booking" class="ms-1"><img class="tripadv-img" :src="rootPath + imgsPath.otaImage + booking.ota_logo" :alt="booking.ota_title"></span>
								<span v-if="booking.is_get_your_guide_booking" class="ms-1"><img class="gyg-img" :src="rootPath + imgsPath.otaImage + booking.ota_logo" :alt="booking.ota_title"></span>
								<span v-if="booking.is_ota_booking" class="ms-1"><img class="ota-img" :src="rootPath + imgsPath.otaImage + booking.ota_logo" :alt="booking.ota_title"></span>
							</div>
							<div class="flex items-center max-[767px]:justify-center">
								<p class="starus text-sm text-[#6B7280] mr-5">Booked on {{ booking.booking_d_t | formatBookingDT }}</p>
								<div class="ammount">
									<div class="finalammount border rounded-full px-3 py-1 text-xs font-medium" v-if ="booking.via == 'Invoiced' && booking.paid != 0 && booking.paid != null &&	booking.invoiceStatus !== '0' && booking.invoiceStatus == 'paid'" v-bind:class="(booking.paid != 0 && booking.paid != null )?'paid bg-[#F0FDF4] border-[#BBF7D0] text-[#047857]':'unpaid bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C]'">
										{{ "Paid"}}
									</div>
									<div v-else-if ="booking.via == 'Invoiced' && booking.paid != 0 && booking.paid != null && booking.invoiceStatus == '0'" v-bind:class="[ 'float-end badge', 'billable-bg-color-2', 'py-1.5 px-2 text-xs font-semibold inline-block whitespace-nowrap text-black border border-[#FFFFD9] bg-[#ffc107] rounded-full' ]">
										{{ "Billable"}}
									</div>
									<div class="border rounded-full px-3 py-1 text-xs font-medium" v-else-if ="booking.via == 'Invoiced' && booking.paid != 0 && booking.paid != null && booking.invoiceStatus !== 'paid'" v-bind:class="[ 'finalammount bg-[#F0FDF4] border-[#BBF7D0] text-[#047857]', 'unpaid bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C]' ]">
										{{ "Invoiced"}}
									</div>
									<div v-else class="finalammount border rounded-full px-3 py-1 text-xs font-medium" v-bind:class="(booking.paid != 0 && booking.paid != null )?'paid bg-[#F0FDF4] border-[#BBF7D0] text-[#047857]':'unpaid bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C]'">
										{{ 
											(booking.paid != 0 && booking.paid != null && (booking.paid_split == null || (booking.total - booking.paid == 0)))
											? 'Paid' 
											: (booking.paid_split == null 
												? currencyFormat(booking.total - booking.paid) 
												: currencyFormat(booking.paid_split)
											)
										}}
									</div>
								</div>
							</div>
						</div>
					</div> <!-- /.booking-warp -->

					<div class="booking-warp-" hidden>
						<div :class="[ 'details-warp border border-[#E6E6E6] rounded-md py-3 px-4 mb-2 md:flex md:items-center md:justify-between', bookingsCancelled.background_colour_class ]" v-for="bookingsCancelled in dayPkgSlotInfo.timeSlotBookingsInfo.bookingsCancelled" :key="'cancelled-' + bookingsCancelled.id + '-' + bookingsCancelled.payment_method + '-' + bookingsCancelled.paid">
							<div class="details-name flex items-center max-[767px]:justify-center">
								<a class="text-base text-[#111827] font-medium mr-2" v-on:click.prevent="initBookingInfoModal(bookingsCancelled.id, 'modal')" href="#">{{ bookingsCancelled.cust_name }}</a>
								<a class="text-xs text-[#374151] font-semibold bg-[#F3F4F6] border border-[#E6E6E6] rounded-full py-1 px-2 mr-2" v-on:click.prevent="initBookingInfoModal(bookingsCancelled.id, 'modal')" href="#" class="bookingid">ID: #{{ bookingsCancelled.prefixedId }}</a>
								<span class="bookingnum text-xs text-[#374151] font-semibold border border-[#E6E6E6] rounded-full py-1 px-2 mr-2"><i class="fa fa-user mr-1"></i> {{ bookingsCancelled.tourists }}</span>
								<p class="bookingspace text-sm text-[#6B7280]">[ {{ bookingsCancelled.t_groups }} ]</p>
								<span v-if="bookingsCancelled.is_tripadvisor_booking" class="ms-1"><img class="tripadv-img" :src="rootPath + imgsPath.otaImage + bookingsCancelled.ota_logo" :alt="bookingsCancelled.ota_title"></span>
								<span v-if="bookingsCancelled.is_get_your_guide_booking" class="ms-1"><img class="gyg-img" :src="rootPath + imgsPath.otaImage + bookingsCancelled.ota_logo" :alt="bookingsCancelled.ota_title"></span>
								<span v-if="bookingsCancelled.is_ota_booking" class="ms-1"><img class="ota-img" :src="rootPath + imgsPath.otaImage + bookingsCancelled.ota_logo" :alt="bookingsCancelled.ota_title"></span>
							</div>

							<div class="flex items-center max-[767px]:justify-center">
								<p class="starus text-sm text-[#6B7280] mr-5">Booked on {{ bookingsCancelled.booking_d_t | formatBookingDT }}</p>
								<div class="ammount">
									<div class="finalammount unpaid border rounded-full px-3 py-1 text-xs font-medium">{{ currencyFormation.symbol + bookingsCancelled.total }}</div>
								</div>
							</div>
						</div>
					</div>
					<!-- /.booking-warp -->

				</div> <!-- /.modal-body -->
			</div> <!-- /.modal-content -->
		</div> <!-- /.modal-dialog -->
	</div> <!-- /#time-slot-bookings .cal-bookings-modal .modal -->

	<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="day-pkg-slot-times" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen  mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-110px)] flex items-center" :class="dayPkgSlotInfo.dayPackages && dayPkgSlotInfo.dayPackages.length > 1 ? 'max-w-[1380px]' : 'max-w-[1150px]'">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden flex" style="height:calc(100vh - 40px)">
				<!-- Packages sidebar -->
				<div v-if="dayPkgSlotInfo.dayPackages && dayPkgSlotInfo.dayPackages.length > 1" class="flex-shrink-0 w-64 border-r border-[#E5E7EB] flex flex-col overflow-hidden">
					<div class="px-4 py-3 border-b border-[#E5E7EB] bg-[#F9FAFB]">
						<p class="text-[10px] font-bold text-[#6B7280] uppercase tracking-widest mb-2">Packages</p>
						<div class="relative">
							<svg class="absolute left-2 top-1/2 -translate-y-1/2 text-[#9CA3AF]" width="13" height="13" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="7" cy="7" r="5.5" stroke="currentColor" stroke-width="1.4"/><path d="M11 11L14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
							<input type="text" v-model="pkgSidebarSearch" placeholder="Search packages..." class="w-full pl-7 pr-2 py-1.5 text-xs border border-[#E5E7EB] rounded-md bg-white text-[#111827] placeholder-[#9CA3AF] focus:outline-none focus:border-[#F97316]">
						</div>
					</div>
					<div class="flex-1 overflow-y-auto py-2">
						<div v-for="pkg in dayPkgSlotInfo.dayPackages.filter(function(p){ return p.pkg_name.toLowerCase().indexOf(pkgSidebarSearch.toLowerCase()) !== -1 })" :key="pkg.pkg_id" v-on:click="switchDayPackage(pkg.schIdx)" :class="pkg.pkg_id === dayPkgSlotInfo.pkg_id ? 'bg-[#FFF7ED] border-[#ed9c45] text-[#C2410C] border' : 'border-transparent text-[#374151] hover:bg-[#F9FAFB] border'" class="mx-2 mb-1 px-3 py-2.5 rounded-lg cursor-pointer transition-all">
							<p :class="pkg.pkg_id === dayPkgSlotInfo.pkg_id ? 'font-bold text-[#ed9c45]' : 'font-semibold text-[#111827]'" class="text-xs leading-snug">{{ pkg.pkg_name }}</p>
						</div>
					</div>
				</div>
				<!-- Main content -->
				<div class="flex-1 flex flex-col min-w-0 overflow-hidden">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between relative">
					<div class="header-info flex flex-wrap items-center -mx-3 w-full">
						<div class="w-full lg:w-1/2 max-w-full px-3 max-[1023px]:mb-2">
							<h3 class="text-lg font-semibold text-[#111827]">{{ dayPkgSlotInfo.pkg_name  }}</h3>
							<span id="packageId" hidden>{{ dayPkgSlotInfo.pkg_id }}</span>
							<div class="text-base font-medium text-[#7C706A]">
								<svg class="inline-block mr-1 align-text-top" width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.33594 1.83594V4.5026" stroke="#60BEBD" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6641 1.83594V4.5026" stroke="#60BEBD" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.6667 3.16406H3.33333C2.59695 3.16406 2 3.76102 2 4.4974V13.8307C2 14.5671 2.59695 15.1641 3.33333 15.1641H12.6667C13.403 15.1641 14 14.5671 14 13.8307V4.4974C14 3.76102 13.403 3.16406 12.6667 3.16406Z" stroke="#60BEBD" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 7.16406H14" stroke="#60BEBD" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<span v-if="dayPkgSlotInfo.isToday">Today: </span>
								<span id="dateForHuman">{{ dayPkgSlotInfo.displayDate }}</span>
								<span class="total-booking bg-[#DBEAFE] text-[#1E40AF] text-xs font-bold py-[2px] px-2 mr-1 rounded">{{ dayPkgSlotInfo.bookedSlots }}</span>
								<span class="available-booking bg-[#DCFCE7] text-[#166534] text-xs font-bold py-[2px] px-2 mr-1 rounded">{{ dayPkgSlotInfo.availableSlots }}</span>
								<span class="over-booking bg-[#F3F4F6] text-[#374151] text-xs font-bold py-[2px] px-2 rounded">{{ (dayPkgSlotInfo.overbookedSlots != null) ? dayPkgSlotInfo.overbookedSlots : 0 }}</span>
							</div>
						</div>
						<div class="w-full lg:w-1/2 max-w-full px-3">
							<div class="sm:flex items-center min-[1024px]:justify-end">
								<?php if (request()->user()->can('agenda')) { ?>
									<a :href="dayPkgSlotInfo.link" id="popup_booking" class="btn btn-secondary tourbookingButton mr-2 text-xs text-[#020817] font-semibold shadow-sm rounded-lg px-4 py-2 bg-white border border-[#E2E8F0] hover:border-[#E2E8F0] hover:bg-[#E2E8F0] hover:text-[#020817] inline-flex gap-1.5 transition-all duration-300 ease-in-out" target="_blank"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 4.66406V13.9974" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M2.0026 12C1.82579 12 1.65622 11.9298 1.5312 11.8047C1.40618 11.6797 1.33594 11.5101 1.33594 11.3333V2.66667C1.33594 2.48986 1.40618 2.32029 1.5312 2.19526C1.65622 2.07024 1.82579 2 2.0026 2H5.33594C6.04318 2 6.72146 2.28095 7.22156 2.78105C7.72165 3.28115 8.0026 3.95942 8.0026 4.66667C8.0026 3.95942 8.28356 3.28115 8.78365 2.78105C9.28375 2.28095 9.96203 2 10.6693 2H14.0026C14.1794 2 14.349 2.07024 14.474 2.19526C14.599 2.32029 14.6693 2.48986 14.6693 2.66667V11.3333C14.6693 11.5101 14.599 11.6797 14.474 11.8047C14.349 11.9298 14.1794 12 14.0026 12H10.0026C9.47217 12 8.96346 12.2107 8.58839 12.5858C8.21332 12.9609 8.0026 13.4696 8.0026 14C8.0026 13.4696 7.79189 12.9609 7.41682 12.5858C7.04175 12.2107 6.53304 12 6.0026 12H2.0026Z" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg> Agenda</a>
								<?php } ?>
								<?php if (request()->user()->can('closure-email')) { ?>
									<span id="lower_antelope_canyon_closure" onclick="return lowerAntelopeCanyonClosure(this)" :data-date="dayPkgSlotInfo.date" :data-tour-operator-id="dayPkgSlotInfo.tour_op_id" :data-tour-package-id="dayPkgSlotInfo.pkg_id" :data-tour-operator-name="dayPkgSlotInfo.tour_op_name" class="btn btn-secondary tourbookingButton mr-2 text-xs text-[#020817] font-semibold shadow-sm rounded-lg px-4 py-2 bg-white border border-[#E2E8F0] hover:border-[#E2E8F0] hover:bg-[#E2E8F0] hover:text-[#020817] inline-flex gap-1.5 transition-all duration-300 ease-in-out cursor-pointer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M13.3359 2.66406H2.66927C1.93289 2.66406 1.33594 3.26102 1.33594 3.9974V11.9974C1.33594 12.7338 1.93289 13.3307 2.66927 13.3307H13.3359C14.0723 13.3307 14.6693 12.7338 14.6693 11.9974V3.9974C14.6693 3.26102 14.0723 2.66406 13.3359 2.66406Z" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.6693 4.66406L8.68927 8.46406C8.48345 8.59301 8.24548 8.6614 8.0026 8.6614C7.75973 8.6614 7.52176 8.59301 7.31594 8.46406L1.33594 4.66406" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg> Closure Email</span>
								<?php } ?>
								<?php if (request()->user()->can('tour-slots')) { ?>
									<span id="add_time_slot" onclick="return addTimeSlot(this)" :data-date="dayPkgSlotInfo.date" :data-tour-operator-id="dayPkgSlotInfo.tour_op_id" :data-tour-package-id="dayPkgSlotInfo.pkg_id" :data-tour-operator-name="dayPkgSlotInfo.tour_op_name" class="btn btn-secondary add-time-slot mr-2 text-xs text-[#020817] font-semibold shadow-sm rounded-lg px-4 py-2 bg-white border border-[#E2E8F0] hover:border-[#E2E8F0] hover:bg-[#E2E8F0] hover:text-[#020817] inline-flex gap-1.5 transition-all duration-300 ease-in-out cursor-pointer"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.33594 8H12.6693" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M8 3.33594V12.6693" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg> Add Time Slot</span>
								<?php } ?>
							</div>
						</div>
					</div>
					<button type="button" class="btn-close absolute top-5 right-5 max-[1023px]:ml-auto" v-on:click="modalDayPkgSlots.hide()" data-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				</div>

				<div class="modal-body time-slot-section p-4 flex-1 min-h-0 overflow-y-auto">
					<!-- Filter tabs + Search -->
					<div class="flex flex-wrap items-center justify-between gap-2 px-3 pb-3 mb-1">
						<div class="flex flex-wrap items-center gap-1.5">
							<button type="button" v-on:click="slotFilter = 'all'"    :class="slotFilter === 'all'    ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]' : 'bg-white text-[#374151] border-[#E5E7EB] hover:bg-[#F9FAFB]'" class="text-xs font-medium px-3 py-1.5 rounded-full border transition-all">All</button>
							<button type="button" v-on:click="slotFilter = 'open'"   :class="slotFilter === 'open'   ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]' : 'bg-white text-[#374151] border-[#E5E7EB] hover:bg-[#F9FAFB]'" class="text-xs font-medium px-3 py-1.5 rounded-full border transition-all">Open</button>
							<button type="button" v-on:click="slotFilter = 'phone'"  :class="slotFilter === 'phone'  ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]' : 'bg-white text-[#374151] border-[#E5E7EB] hover:bg-[#F9FAFB]'" class="text-xs font-medium px-3 py-1.5 rounded-full border transition-all">Phone Only</button>
							<button type="button" v-on:click="slotFilter = 'closed'" :class="slotFilter === 'closed' ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]' : 'bg-white text-[#374151] border-[#E5E7EB] hover:bg-[#F9FAFB]'" class="text-xs font-medium px-3 py-1.5 rounded-full border transition-all">Closed</button>
							<?php if (request()->user()->can('tour-slots')) { ?>
							<button type="button" v-on:click="slotFilter = 'visible'" :class="slotFilter === 'visible' ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]' : 'bg-white text-[#374151] border-[#E5E7EB] hover:bg-[#F9FAFB]'" class="text-xs font-medium px-3 py-1.5 rounded-full border transition-all">Visible</button>
							<button type="button" v-on:click="slotFilter = 'hidden'"  :class="slotFilter === 'hidden'  ? 'bg-[#1E3A5F] text-white border-[#1E3A5F]' : 'bg-white text-[#374151] border-[#E5E7EB] hover:bg-[#F9FAFB]'" class="text-xs font-medium px-3 py-1.5 rounded-full border transition-all">Hidden</button>
							<?php } ?>
						</div>
						<div class="relative">
							<svg class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[#9CA3AF]" width="14" height="14" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="6.5" cy="6.5" r="5" stroke="currentColor" stroke-width="1.5"/><path d="M10.5 10.5L14 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
							<input type="text" v-model="slotSearch" placeholder="Search time slot..." class="pl-8 pr-3 py-1.5 text-xs border border-[#E5E7EB] rounded-lg outline-none text-[#374151] placeholder-[#9CA3AF] w-48 focus:border-[#93C5FD]">
						</div>
					</div>
					<?php if (request()->user()->can('tour-slots')) { ?>
						<!-- Bulk action bar – shown by JS when checkboxes are checked -->
						<div id="bulk-action-bar" class="hidden rounded-xl bg-[#1E3A5F] px-4 py-2.5 mb-3 flex flex-wrap items-center gap-3">
							<span id="bulk-selected-count" class="text-sm font-semibold text-white whitespace-nowrap">0 selected</span>
							<div class="flex items-center gap-1.5">
								<button type="button" onclick="saveTimeSlotStatusDirect('Open')" class="inline-flex items-center gap-1.5 text-xs font-medium text-white bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all"><span class="w-2 h-2 rounded-full bg-[#22C55E] flex-shrink-0"></span> Set Open</button>
								<button type="button" onclick="saveTimeSlotStatusDirect('Closed')" class="inline-flex items-center gap-1.5 text-xs font-medium text-white bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all"><span class="w-2 h-2 rounded-full bg-[#EF4444] flex-shrink-0"></span> Set Closed</button>
								<button type="button" onclick="saveTimeSlotStatusDirect('Only by Phone')" class="inline-flex items-center gap-1.5 text-xs font-medium text-white bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all"><span class="w-2 h-2 rounded-full bg-[#3B82F6] flex-shrink-0"></span> Phone Only</button>
							</div>
							<span class="text-white/30 text-sm">|</span>
							<div class="flex items-center gap-1.5">
								<button type="button" onclick="bulkSetVisibility(0)" class="text-xs font-medium text-white bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all">Make Visible</button>
								<button type="button" onclick="bulkSetVisibility(1)" class="text-xs font-medium text-white bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all">Hide</button>
							</div>
							<button type="button" onclick="clearBulkSelection()" class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-white bg-white/10 hover:bg-white/20 border border-white/20 px-3 py-1.5 rounded-lg transition-all"><svg width="10" height="10" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12M4 4l8 8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg> Clear</button>
						</div>
						<div v-show="filteredSlotTimes.length > 0" class="flex items-center px-3 pb-2 mb-2 border-b border-[#E5E7EB]">
							<input class="w-4 h-4 mr-2 flex-shrink-0" type="checkbox" id="all-slot-checkbox" />
							<!-- Column headers inline with checkbox – desktop only -->
							
						</div>
					<?php } ?>
					<div v-show="filteredSlotTimes.length === 0" class="py-10 text-center text-sm text-[#9CA3AF]">
						<svg class="mx-auto mb-2 text-[#D1D5DB]" width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
						No slots found for the selected filter.
					</div>
					<div class="max-w-full max-[1023px]:flex max-[1023px]:flex-wrap max-[1023px]:-mx-3">
						<div class="data-warp border border-[#F3F4F6] rounded-lg px-3 py-2 max-[767px]:w-[calc(100%-24px)] max-[1023px]:w-[calc(50%-24px)] max-[1023px]:mx-3 max-[1023px]:mb-6 max-w-full min-[1024px]:flex items-center mb-1" v-for="(slot, slotIdx) in filteredSlotTimes" :key="slot.id">
							<?php if (request()->user()->can('tour-slots')) { ?>
								<input type="checkbox" class="checkBoxClass w-4 h-4 min-w-4 min-h-4 mr-3 max-[1023px]:mb-2" :value="slot.id" :id="'checkbox-' + slot.id">
							<?php } ?>
							<button type="button" class="btn-warning-small mr-3 whitespace-nowrap max-[1023px]:mb-2" v-on:click.prevent="initNewBookingModalBySlot(slot.id, 'day-slot')" :disabled="(slot.available_tourists !== undefined ? slot.available_tourists <= 0 : slot.tourists >= slot.seats) || slot.bookable_status == 'Closed' || slot.affiliateFeature == 0">+ Book</button>
							<button type="button" class="timebutton max-[1023px]:w-[calc(100%-125px)] max-[1023px]:mb-2 max-[1023px]:inline-block border border-[#E5E7EB] bg-[#F3F4F6] text-sm text-[#111827] font-medium rounded-lg px-3 py-2.5 min-[1024px]:mr-3 flex items-center whitespace-nowrap min-[1024px]:w-48 min-w-40 shrink-0" data-bs-toggle="modal" data-bs-target="#time-slot-bookings" :data-time-slot-index="slotIdx">{{ slot | formatSlotTimeDuration }} <svg class="inline-block ml-auto" width="13" height="13" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3.86377 10.5L6.86377 7.5L3.86377 4.5" stroke="#9CA3AF" stroke-linecap="round" stroke-linejoin="round"/></svg></button>

							<div class="status-warp min-[1024px]:mr-3 max-[1023px]:mb-2 min-[1024px]:w-44">
								<div class="dropdown relative">
									<button
										type="button"
										:class="slot.bookable_status_2 | getBookableStatusCls" class="text-xs border px-3 py-2.5 rounded-lg flex items-center w-full min-w-36"
										:id="'btn-dd-ts-' + slot.id"
										data-bs-toggle="dropdown"
										aria-expanded="true"
										>
										<span v-if="slot.bookable_status_2 === 'Open'">
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.65546 4.09616C8.83856 4.27927 8.83856 4.57608 8.65546 4.75909L5.5108 7.90384C5.3277 8.08685 5.03098 8.08685 4.84787 7.90384L3.35089 6.40677C3.16779 6.22375 3.16779 5.92694 3.35089 5.74393C3.53391 5.56082 3.83072 5.56082 4.01373 5.74393L5.17929 6.90948L7.99252 4.09616C8.17563 3.91315 8.47244 3.91315 8.65546 4.09616ZM12.0032 6C12.0032 9.3165 9.31921 12 6.00317 12C2.68668 12 0.00317383 9.31604 0.00317383 6C0.00317383 2.6835 2.68713 0 6.00317 0C9.31967 0 12.0032 2.68396 12.0032 6ZM11.0657 6C11.0657 3.20169 8.80112 0.9375 6.00317 0.9375C3.20486 0.9375 0.940674 3.20206 0.940674 6C0.940674 8.79831 3.20523 11.0625 6.00317 11.0625C8.80148 11.0625 11.0657 8.79794 11.0657 6Z" fill="currentColor"/></svg>
										</span>

										<span v-else-if="slot.bookable_status_2 === 'Closed'">
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.86377 6H2.86377C2.31148 6 1.86377 6.44772 1.86377 7V10.5C1.86377 11.0523 2.31148 11.5 2.86377 11.5H9.86377C10.4161 11.5 10.8638 11.0523 10.8638 10.5V7C10.8638 6.44772 10.4161 6 9.86377 6Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.86377 6V4C3.86377 3.33696 4.12716 2.70107 4.596 2.23223C5.06484 1.76339 5.70073 1.5 6.36377 1.5C7.02681 1.5 7.6627 1.76339 8.13154 2.23223C8.60038 2.70107 8.86377 3.33696 8.86377 4V6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</span>

										<span v-else>
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 11.625C2.6915 11.625 0 9.1017 0 6C0 2.89828 2.6915 0.375 6 0.375C9.30848 0.375 12 2.89828 12 6C12 9.1017 9.30848 11.625 6 11.625ZM6 1.3125C3.243 1.3125 1 3.41531 1 6C1 8.58465 3.243 10.6875 6 10.6875C8.75696 10.6875 11 8.58465 11 6C11 3.41531 8.75696 1.3125 6 1.3125ZM7.644 6.52265L6.7565 7.35469C5.743 6.99234 4.996 6.31969 4.555 5.29078L5.4425 4.45875L4.0865 3.1875L3.3975 3.83344C3.1275 4.08609 3 4.4189 3 4.75078C3 6.58547 5.516 8.81295 7.333 8.81295C7.687 8.81295 8.042 8.69295 8.31152 8.44028L9.00048 7.79437L7.644 6.52265Z" fill="currentColor"/></svg>
										</span>

										<span class="bookable-status mx-1">{{ slot.bookable_status_2 }}</span>

										<svg class="inline-block align-text-top ml-auto" width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="currentColor" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"/></svg>
									</button>


									<?php if(request()->user()->can('tour-slots')) { ?>
										<ul class="dropdown-menu ul-slot-status hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded-lg border border-[#E5E7EB] text-sm text-left py-1" :aria-labelledby="'btn-dd-ts-' + slot.id">
											<li class="li-slot-status flex items-center px-4 py-2.5 text-xs text-[#374151] hover:bg-[#F9FAFB] cursor-pointer font-medium" v-on:click="setBookableStatus(slot.id, 'Open')"><svg class="mr-2 flex-shrink-0" width="10" height="10" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5" fill="#22C55E"/></svg> Open</li>
											<li class="li-slot-status flex items-center px-4 py-2.5 text-xs text-[#374151] hover:bg-[#F9FAFB] cursor-pointer font-medium" v-on:click="setBookableStatus(slot.id, 'Closed')"><svg class="mr-2 flex-shrink-0" width="10" height="10" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5" fill="#EF4444"/></svg> Closed</li>
											<li class="li-slot-status flex items-center px-4 py-2.5 text-xs text-[#374151] hover:bg-[#F9FAFB] cursor-pointer font-medium" v-on:click="setBookableStatus(slot.id, 'Only by Phone')"><svg class="mr-2 flex-shrink-0" width="10" height="10" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5" fill="#3B82F6"/></svg> Only by Phone</li>
										</ul>
									<?php } else { ?>
										<ul class="dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded border text-sm text-left" :aria-labelledby="'btn-dd-ts-' + slot.id">
											<li class="flex items-center px-4 py-2 text-xs text-[#4B5563] hover:text-[#F97316] cursor-pointer">{{ slot.bookable_status_2_reason }}</li>
										</ul>
									<?php } ?>
								</div>
							</div>
							<?php if (request()->user()->can('tour-slots')) { ?>
							<div class="visibility-warp min-[1024px]:mr-3 max-[1023px]:mb-2 min-[1024px]:w-32">
									<button type="button"
										:id="'btn-vis-ts-' + slot.id"
										v-on:click="toggleSlotVisibility(slot.id)"
										:class="slot.hide_slot | getVisibilityBtnCls"
										class="text-xs font-semibold border px-3 py-2.5 rounded-lg flex items-center gap-1.5 w-full min-w-28">
										<span v-if="slot.hide_slot == 1">
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.41333 9.41325C9.23056 9.60944 9.01128 9.76739 8.76823 9.87789C8.52517 9.98839 8.2634 10.0492 7.99724 10.057C7.73108 10.0647 7.46618 10.0192 7.21706 9.92325C6.96793 9.82728 6.73975 9.68278 6.54609 9.49831C6.35243 9.31384 6.19716 9.09298 6.08966 8.84876C5.98216 8.60453 5.92462 8.34184 5.92046 8.07557C5.9163 7.8093 5.96562 7.54496 6.06545 7.2975C6.16529 7.05005 6.31363 6.82443 6.50133 6.63392M11.96 11.96C10.8204 12.8286 9.43282 13.3099 8.0013 13.3333C3.33464 13.3333 0.667969 7.99992 0.667969 7.99992C1.49589 6.45396 2.64657 5.10469 4.0413 4.03992L11.96 11.96ZM6.60797 2.82659C7.06442 2.71895 7.53216 2.66512 8.0013 2.66659C12.668 2.66659 15.3346 7.99992 15.3346 7.99992C14.9287 8.75717 14.4443 9.46892 13.8893 10.1199L6.60797 2.82659ZM0.667969 0.666595L15.3346 15.3333" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</span>
										<span v-else>
											<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.33594 8C1.33594 8 3.33594 3.33337 8.0026 3.33337C12.6693 3.33337 14.6693 8 14.6693 8C14.6693 8 12.6693 12.6667 8.0026 12.6667C3.33594 12.6667 1.33594 8 1.33594 8Z" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8.0026" cy="8" r="2" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>
										</span>
										<span class="slot-visibility-label" v-text="slot.hide_slot == 1 ? 'Hidden' : 'Visible'"></span>
									</button>
							</div>
							<?php } ?>
							<div class="availability-warp min-[1024px]:mr-3 max-[1023px]:mb-2 min-[1024px]:w-80" >
								<div class="dropdown relative">
									<button type="button" :class="slot | getBookedSeatsCls" class="text-xs text-[#111827] border px-3 py-2.5 rounded-lg flex items-center items-flex whitespace-nowrap w-full min-[1024px]:min-w-72 font-medium" data-bs-toggle="dropdown" aria-expanded="false" :data-time-slot-id="slot.id" data-dropdown-of="tourists age/rage groups" id="ragetourists">
										<span :class="(slot.booked_seats > 0 ? 'text-[#000000]' : 'text-[#1D4ED8]')">{{ slot.booked_seats }} Booked</span> <span class="px-3">|</span> 
										<span :class="slot | availableSeatsInfoCls">{{ slot | availableSeatsInfo }}</span> <span class="px-3">|</span> 
										<span class="text-[#4B5563]">{{ slot.seats }} Total</span>
									</button>

									<div class="dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded border text-sm text-left" :id="'tourists-info-' + slot.id" data-bs-auto-close="outside">

										<div class="capacities-warp nobg px-4 py-2 text-xs text-[#4B5563] hover:text-[#F97316]" v-if="dayPkgSlotInfo.timeSlotTouristsInfo.length == 0">[ No booking in the slot ]</div>

										<div class="capacities-warp nobg flex items-center justify-between px-4 py-2 text-xs text-[#4B5563] hover:text-[#F97316]" v-for="touristsInfo in dayPkgSlotInfo.timeSlotTouristsInfo">
											<div class="title">{{ touristsInfo.rate_for }}</div>
											<div class="booked">{{ touristsInfo.tourists }}</div>
										</div>
									</div> <!-- /.dropdown-menu -->
								</div>
							</div> <!-- /.availability-warp -->
							<div class="div-affiliate-bookings min-[1024px]:w-44" v-if="dayPkgSlotInfo.is_affiliate == 1">
								<div class="dropdown relative">
									<button type="button" class="themed-btn dropdown-toggle border border-[#E5E7EB] bg-[#FAFAFA] text-xs text-[#2C2421] font-medium rounded-lg px-3 py-2.5 flex items-center whitespace-nowrap w-full min-w-36" data-bs-toggle="dropdown" :data-time-slot-id="slot.id" data-dropdown-of="affiliate bookings" data-bs-auto-close="false" aria-expanded="false">{{ slot.afl_tourists }} Affiliate Bookings <svg class="inline-block ml-auto" width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"/></svg></button>

									<div class="dropdown-menu dropdown-menu-end p-4 hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded border text-sm text-left" :id="'affiliate-bookings-info-' + slot.id">
										<table :class="['table table-sm w-full', 'table-borderless', dayPkgSlotInfo.timeSlotAffiliateBookings.length ? 'mb-2' : 'mb-0']">
											<tr v-for="aflBooking in dayPkgSlotInfo.timeSlotAffiliateBookings" :key="'afl-' + aflBooking.affiliate_id + '-' + aflBooking.tourists">
												<td class="text-xs font-medium text-[#374151] p-2 pl-0" :data-affiliate-id="aflBooking.affiliate_id">{{ aflBooking.name }}</td>
												<td class="text-xs font-medium text-[#374151] p-2 text-right">{{ aflBooking.tourists }}</td>
												<td class="text-xs font-medium text-[#374151] p-2 text-right"><svg v-on:click="editAffiliateBooking(slot.id, aflBooking.affiliate_id, aflBooking.tourists)" width="17" height="15" viewBox="0 0 17 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.50326 2.28125H4.35511C4.04078 2.28125 3.73932 2.40417 3.51705 2.62296C3.29479 2.84175 3.16992 3.1385 3.16992 3.44792V11.6146C3.16992 11.924 3.29479 12.2207 3.51705 12.4395C3.73932 12.6583 4.04078 12.7812 4.35511 12.7812H12.6514C12.9657 12.7812 13.2672 12.6583 13.4895 12.4395C13.7117 12.2207 13.8366 11.924 13.8366 11.6146V7.53125" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.2814 2.06556C12.5171 1.8335 12.8369 1.70313 13.1703 1.70312C13.5037 1.70312 13.8234 1.8335 14.0592 2.06556C14.2949 2.29763 14.4273 2.61237 14.4273 2.94056C14.4273 3.26875 14.2949 3.5835 14.0592 3.81556L8.71812 9.07373C8.57741 9.21212 8.40357 9.31343 8.21264 9.36831L6.51012 9.85831C6.45913 9.87295 6.40507 9.87383 6.35362 9.86085C6.30217 9.84788 6.2552 9.82152 6.21764 9.78455C6.18008 9.74758 6.15331 9.70135 6.14013 9.6507C6.12695 9.60005 6.12784 9.54684 6.14271 9.49665L6.64049 7.82073C6.6965 7.63292 6.79962 7.46201 6.94034 7.32373L12.2814 2.06556Z" stroke="#2C2421" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></td>
												<?php if(!$uTypeAffiliate){ ?>
													<td class="text-xs font-medium text-[#374151] p-2 text-right pr-0"><svg v-on:click="cancelAffiliateBooking(slot.id, aflBooking.affiliate_id)" width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.2422 4L4.24219 12" stroke="#DC2626" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.24219 4L12.2422 12" stroke="#DC2626" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></td>
												<?php } else{ ?>
													<td class="text-xs font-medium text-[#374151] p-2 text-right pr-0"><svg v-on:click="cancelAffiliateBooking(slot.id, aflBooking.affiliate_id)" v-if="slot.affiliateFeature == 1" width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.2422 4L4.24219 12" stroke="#DC2626" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.24219 4L12.2422 12" stroke="#DC2626" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></td>
												<?php } ?>
											</tr>
										</table>
										<div class="block h-[1px] bg-[#F3F4F6] my-2"></div>
										<div class="div-afl-book-title text-sm text-[#111827] font-medium mb-2">Reserve/book seats for affiliates:</div>

										<button type="button" class="btn btn-primary btn-sm btn-show-save-afl-booking  bg-[#2563EB] hover:bg-white border border-[#2563EB] text-white hover:text-[#2563EB] text-xs font-semibold px-3 py-1.5 w-full rounded-[6px] cursor-pointer transition-all duration-300 ease-in-out" v-on:click="showAffiliateBookingForm()" :enabled="slot.bookable_status_2 == 'Closed'" <?php if($uTypeAffiliate){ ?> :disabled="slot.affiliateFeature == 0" <?php } ?>>Add/Update a Block</button>

										<div class="div-save-afl-booking hidden">
											<select class="form-select sel-affiliate mb-2 bg-[#FAFAFA] text-xs text-[#2C2421] border border-[#E5E7EB] px-3 py-2.5 rounded-lg max-w-full outline-none" v-model="affiliate.id">
												{{ affiliates }}
												<!-- option :value="affil.id" v-for="affil in timeSlotAffilsWithNoBooking">{{ affil.name }}</option -->
												<option :value="affil.id" v-for="affil in affiliates">{{ affil.name }}</option>
											</select>

											<input type="number" class="form-control form-control-sm txt-affiliate-seats mb-2 w-full resize-none outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-[15px] py-[6px] h-[40px] text-sm text-[#020817]" min="1" :max="slot.seats" value="1" required placeholder="Number of seats to book">
											<button type="button" class="btn btn-primary btn-save-afl-booking btn-sm bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-sm font-semibold px-3 py-1.5 w-full rounded-[6px] cursor-pointer transition-all duration-300 ease-in-out" v-on:click="saveAffiliateBooking(slot.id)">Add/Update Block</button>
										</div>
									</div>
								</div>
							</div>
						</div> <!-- / slot times -->
					</div>
					<!-- New added time slots -->
					<div class="time-slots mb-2"></div>
					<!-- / Add slot times from calendar start -->
					<form method="POST" id="newTimeSlotForm" class="mb-2">
						<span :data-tour-package-id="dayPkgSlotInfo.pkg_id"></span>
						<div class="new-time-slot-form"></div>
					</form>
					<!-- / Add slot times from calendar end -->
					<div class="rec-history-container" v-if="dayPkgSlotInfo.recHistory.length">
						<div class="rec-history flex flex-wrap -mx-3 mt-2 pt-2 border-t border-[#E5E7EB]" v-for="recHist in dayPkgSlotInfo.recHistory">
							<div class="w-full lg:w-1/4 max-w-full px-3 text-xs text-[#6B7280] p-2 whitespace-nowrap">{{ recHist.dateTime | formatBookingDT }}</div>
							<div class="w-full lg:w-3/4 max-w-full px-3 text-sm text-[#374151] p-2" v-html="recHist.info"></div>
						</div>
					</div>
				</div> <!-- /.modal-body -->
				</div> <!-- /.main-content flex col -->
			</div> <!-- /.modal-content -->
		</div> <!-- /.modal-dialog -->
	</div> <!-- /#day-pkg-slot-times modal -->

	<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/50 hidden" id="modal-new-booking" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen max-w-[1380px] mx-auto my-[20px] h-[calc(100%-40px)] w-[calc(100%-110px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
					<h3 class="modal-title text-lg font-semibold text-[#111827]">Book a Tour</h3>
					<button type="button" class="btn-close" v-on:click="safeHideNewBooking();safeShowTimeSlotBookings()" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				</div>

				<div class="modal-body h-[calc(100%-60px)]">
					<iframe class="w-full h-full" id="frame-new-booking" v-on:load="showNewBookingModal" src=""></iframe>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-booking-info" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen max-w-[1518px] mx-auto my-[20px] h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
					<h3 class="modal-title text-lg font-semibold text-[#111827]">Booking Information</h3>
					<button type="button" class="btn-close" v-on:click="hideBookingInfoModal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				</div>

				<div class="modal-body h-[calc(100%-60px)]">
					<iframe class="w-full h-full" id="frame-booking-info" v-on:load="showBookingInfoModal" src=""></iframe>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="switchcalendarModal" tabindex="-1" aria-labelledby="switchcalendarModalLabel" aria-hidden="true">
	  	<div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
					<div class="card border-0 shadow-sm bg-light">
						<div class="card-body d-flex justify-content-between align-items-center p-3">
						<div>
							<h5 class="mb-0 text-dark">
							<i class="bi bi-calendar-week-fill me-2 text-warning"></i>
							<span id="switchCalendarName" class="fw-semibold"></span>
							</h5>
							<small id="switchCalendarDate" class="text-muted ms-4"></small>
						</div>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
						</div>
					</div>
				</div>
			</div>

			<!-- Modal Body  -->
			<div class="modal-body p-4">
			<div id="switchcalendarTableBody">
				<!-- Accordion will be injected here by JS  -->
			</div>
			</div>

			<!-- Modal Footer  -->
			<div class="modal-footer">
			<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
			</div>

	    </div>
	  </div>
	</div>


</div>
	@endverbatim
@endsection

@section('css-libraries')
    @if(App::environment('local'))
    <link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
    @else
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
    @endif
    <link href="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

@endsection

@section('css-custom')
	<link href="{{ asset('css/user-common/tour-calendar.css') }}" rel="stylesheet" />
@endsection

@section('js-libraries')
	@if(App::environment('local'))
		<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
		<script src="{{ asset('plugins/vue-2.6.14.js') }}"></script>
	@else
		<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/vue@2/dist/vue.js"></script>
	@endif
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
	<script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
	<script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('js-custom')

<script>
	currencyFormation = JSON.parse(document.getElementById('currency').value);
	var routePrefixForJs = '{{ routePrefixForJs() }}';
	
	var vueMounted = false;
	var csrfToken = '{{ csrf_token() }}';
	var tenantId = '{{ tenant()->id }}';

	var broadcastListeningToken = '{{ \Crypt::encrypt([
		'id' => session('broadcastListeningTokenId'),
		'uId' => auth()->id(),
		'uType' => session('userType'),
	]) }}';

	{{-- year kept out of vue so changing month through next and previous btns (which also changes year) do not affect it --}}
	var calInfo = {
		today: '{{ date("Y-m-d") }}',
		year: {{ $year }},
		month: {{ $month - 1 }},
		packageId : [{{ $packageId }}],
		week: {{ $week }},
		option: '{{ $option }}',
		tenantId: tenantId
	};

	var draftTourSlots = @json($tourSlots);
	var tourBookings =  @json($tourBookings);
	var otaPlatforms =  @json($otaPlatforms);
	@if(isset($weekDates))
		var weeklyDates = @json($weekDates);
	@endif

	var vueMixin = {
		data: {
			rootPath: "{{ asset('') }}",
			imgsPath: {
				checkboxChecked: 'images/mark-check.png',
				checkboxUnchecked: 'images/mark-uncheck.png',
				loadingAnim: 'images/loading.gif',
				otaImage: 'images/otas/'
			},
			affiliate: {
				id: {{ count($affiliates) ? $affiliates[0]->id : 0 }},
				seats: 1
			},
			affiliates: @json($affiliates),
			domain: '{{ $domain }}',
			@if($uTypeAdmin)
				tourOperator: {{ $tourOperatorId }},
				tourOperators: @json($tourOperators),
			@endif
			packages : @json($packages),
			partnerships : @json($partnerships),
			tenantId: tenantId,
			showAllPackageBookings: {{ $showAllPackageBookings }},
		}
	};

	$(document).ready(function () {
		$('.select2').select2({
			placeholder: "Select a packages",
			allowClear: true,
			closeOnSelect: false
		});
	});

	var serverDT = moment('{{ date('Y-m-d H:i:s') }}');
	var serverTZ = '{{ date('P') }}';

	setInterval(function() {
		serverDT.add(15, 'seconds');
	}, 15000);

	var path = '{{ url('/') }}';
	var environment = "{{ config('app.env') }}";
	var userType = '{{ session("userType") }}';

	window.calendarSwitchTenants = @json(switchCalendar());
	window.AppPermissions = @json(Auth::user()->getAllPermissions()->pluck('name'));
	window.AppRoles = @json(Auth::user()->roles->pluck('name'));
	window.switchTenants = @json(switchTenant());

if ($(location).attr('href') == path + "/affiliate/booking/calendar") {
	var dayTimeSlotsUrl = "{{ route('affiliate.get-tour-slot-times-by-date') }}";
	var switchCompanyUrl = "{{ route('affiliate.switch-company', ':id') }}";
	var getAffiliateBookingsUrl = "{{ route('affiliate.get-affiliate-bookings', [ 'timeSlotId' => 0 ]) }}";
	var bookingInfoPageUrl = "{{ route('affiliate.booking.show', [ 'booking' => 0 ]) }}";
	var saveAffiliateBookingUrl = "{{ route('affiliate.booking.save-affiliate-booking') }}";
	var timeSlotBookingsInfoUrl = "{{ route('affiliate.get-tour-slot-time-bookings-info', [ 'timeSlotId' => 0 ]) }}";
	var timeSlotTouristsInfoUrl = "{{ route('affiliate.get-tour-slot-time-tourists-info', [ 'timeSlotId' => 0 ]) }}";
	var calendarDateSlotsUrl = "{{ route('affiliate.booking.calendar-ahr') }}";
	var newBookingPageUrl = "{{ route('affiliate.booking.create') }}";
	var bookingSearchUrl = "{{ route('affiliate.search-booking') }}";
	var lowerAntelopeCanyonClosureUrl = "{{ route('affiliate.lower-antelope-canyon-closure-email') }}";
	var checkOverbookingUrl = "{{ route('affiliate.booking.check-overbooking') }}";
	var loggedInaffiliateId = "{{ auth()->id() }}";
	var updateSlotBookableStatus = "{{ route('affiliate.booking.update-slot-bookable-status') }}";
	var bulkUpdateSlotBookableStatus = "{{ route('affiliate.booking.bulk-update-slot-bookable-status') }}";
	var updateSlotHideStatusUrl = "{{ route('affiliate.booking.update-slot-hide-status') }}";
	var bulkUpdateSlotHideStatusUrl = "{{ route('affiliate.booking.bulk-update-slot-hide-status') }}";
	var addTimeSlotUrl = "{{ route('affiliate.booking.add-time-slot') }}";
} else {
	var dayTimeSlotsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-times-by-date') }}";
	var switchCompanyUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.switch-company', ':id') }}";
	var getAffiliateBookingsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-affiliate-bookings', [ 'timeSlotId' => 0 ]) }}";
	var bookingInfoPageUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.show', [ 'booking' => 0 ]) }}";
	var saveAffiliateBookingUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.save-affiliate-booking') }}";
	var timeSlotBookingsInfoUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-time-bookings-info', [ 'timeSlotId' => 0 ]) }}";
	var timeSlotTouristsInfoUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-time-tourists-info', [ 'timeSlotId' => 0 ]) }}";
	var calendarDateSlotsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.calendar-ahr') }}";
	var newBookingPageUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.create') }}";
	var bookingSearchUrl = "{{ route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'search-booking') }}";
	var lowerAntelopeCanyonClosureUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.lower-antelope-canyon-closure-email') }}";
	var loggedInaffiliateId = "";
	var checkOverbookingUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') .'tour-operator.booking.check-overbooking') }}";
	var updateSlotBookableStatus = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.update-slot-bookable-status') }}";
	var bulkUpdateSlotBookableStatus = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.bulk-update-slot-bookable-status') }}";
	var updateSlotHideStatusUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.update-slot-hide-status') }}";
	var bulkUpdateSlotHideStatusUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.bulk-update-slot-hide-status') }}";
	var addTimeSlotUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.add-time-slot') }}";
	var switchCalendardayTimeSlotsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.day-time-slots',  ['date' => '__DATE__', 'tourPackageId' => '__PKGID__']) }}?selectedCalendar=__CALID__";

}

	function paramsToSearchBooking() {
		return {
			search: $('#search').val(),
			@if($uTypeAdmin)
			tourOperatorId: calApp.tourOperator
			@endif
		}
	}

	$(document).ready(function () {

    $('#tenantId').on('change', function () {
        let tenant = $(this).val();
        showLoading();
        console.log('Tenant:', tenant);

        $.get(url, { tenant: tenant })
            .done(function (data) {
                window.location.href = data.url;
            })
            .fail(showSubmissionError)
            .always(hideLoading);
    });

});

	// function switchTenants(sel) {
	// 	var sel = $(sel);
	// 	var tenant = sel.val();
    // 	showLoading();
	// 	console.log(tenant);
	// 	$.get(url, { tenant: tenant })
	// 		.done(function (data) {
	// 			window.location = data.url; // redirect or handle as needed
	// 		})
	// 	.fail(showSubmissionError)
	// 	.always(hideLoading);
	// }
	function paramsToGetCalendarDateSlots() {
		var packageId = [];
		$.each($("#packageId option:selected"), function() {            
			packageId.push($(this).val());
		});
		var params = {
			yearMonth: '' + calInfo.year + (calApp.month + 1), // added blank string to prevent making sum of year and month
			@if($uTypeAdmin)
			tourOperatorId: calApp.tourOperator, //$('#tour_operator').val(),
			@endif
			packageId :packageId,
			option: calInfo.option,
			week: calInfo.week,
			tenantId: calApp.selectedCalendar
		};
		
		return params;
	}


</script>

@if($uTypeAdmin)
<x-admin-js />
@endif
<script src="{{ asset('js/user-common/tour-calendar.js') }}"></script>

<script>
	calApp.calMonthDaysSchedules = funCalMonthDaysSchedules(draftTourSlots);

	SocketManager.register(new CalendarSocketModule());
	SocketManager.subscribe('tenant.{{ $uTypeAdmin ? 'admin' : auth()->user()->tour_operator_id }}');
</script>

@endsection