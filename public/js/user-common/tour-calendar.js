var aflBookDd = null;

function funCalMonthDaysSchedules(tourSlots) {
	var month = (typeof calApp.month == 'undefined' ? calInfo.month : calApp.month) + 1;
	month = (month < 10 ? '0' : '') + month;
	var startDt = moment(calInfo.year + '-' + month + '-' + '01');
	var monthStartDay = startDt.day();
	var monthEndDayNum = startDt.add(1, 'month').subtract(1, 'day').date();
	var end = (monthStartDay + monthEndDayNum) == 27 ? 27 : ((monthStartDay + monthEndDayNum) <= 34 ? 34 : 41);
	var days = [];
	var serverDate = serverDT.format('YYYY-MM-DD');

	for (var a = 1 - monthStartDay, b = 0, c = 0; b <= end; a++, b++) {
		var day = {
			class: 'day-' + (b % 7),
			num: '',
			date: 'cal-extra-' + b,
			schedules: []
		};

		if (a > 0 && a <= monthEndDayNum) {
			day.num = a;
			day.date = calInfo.year + '-' + month + '-' + (a < 10 ? '0' : '') + a;
			if (calInfo.option == "week") {
				var exists = weeklyDates?.some(function (field) {
					return field === a;
				});
				if (exists == true) {
					day.weekdate = a;
				}
			} else {
				day.weekdate = a;
			}
			day.id = 'cal-date-' + day.date;
			day.class += ' ' + (day.date < serverDate ? 'cal-day-dim' : '');

			if (tourSlots[c] && tourSlots[c].date == day.date)
				while (tourSlots[c] && tourSlots[c].date == day.date)
					day.schedules.push(tourSlots[c++]);
		} else {
			day.id = 'cal-day-dim cal-day-' + b;
		}

		days.push(day);
	}

	return days;
}

function newTimeSlotBookingsInfoObj() {
	return {
		timeSlot: {},
		bookings: [],
		touristsInfo: [],
		showOnlyCancelledBookings: false,
		cancelledCount: 0,
		bookingsCancelled: [],
		touristsInfoCancelled: [],
		cancelledtouristsCount: 0,

	};
}

var calApp = new Vue({
	el: '#calendar-vue-app',
	mixins: [vueMixin],
	data: {
		days: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
		months: [
			'January', 'February', 'March', 'April', 'May', 'June',
			'July', 'August', 'September', 'October', 'November', 'December'
		],
		options: [
			{ id: 'month', name: 'Month' },
			{ id: 'week', name: 'Week' },
			// {id:'day', name:'Day'}
		],
		month: calInfo.month,
		week: calInfo.week,
		option: calInfo.option,
		packageId: calInfo.packageId,
		tourBookings: tourBookings,
		otaPlatforms: otaPlatforms,
		calMonthDaysSchedules: [],
		dayPkgSlotInfo: {
			isToday: true,
			date: '',
			pkg_name: '',
			timeSlotBookingsInfo: newTimeSlotBookingsInfoObj(),
			timeSlotTouristsInfo: [],
			timeSlotAffiliateBookings: [],
			slotTimes: [],
			recHistory: [],
			packageHasSlots: 1,
			dayPackages: [],
			dayIdx: null,
		},
		slotFilter: 'all',
		slotSearch: '',
		pkgSidebarSearch: '',
		modalTimeSlotBookings: null,
		modalDayPkgSlots: null,
		modalNewBooking: null,
		modalBookingInfo: null,
		bookingInfoModalOpenedFrom: '',
		searching: false,
		searchDone: false,
		existingBookings: JSON.stringify(tourBookings),
		timeSlotAffilsWithNoBooking: [],
		partner: calInfo.tenantId,
		currencyFormation: currencyFormation,
		selectedCalendar: '',
		calendarSwitchOptions: [],
		appPermissions: window.AppPermissions || [],
		appRoles: window.AppRoles || [],
		modalNewBookingOrigin: null, // track from where new booking modal is triggered
		currentSlotIndex: null
	},
	mounted: function () {
		vueMounted = true;
		$('#packageId').on('change', this.handleSelectChange);
		$('#packageId').val(calInfo.packageId);
		$('#display_option').val(calInfo.option);
		$('#display_option').attr('selected', true);
		if (calInfo.option == 'week') {
			$('#calendar-table').addClass('weekly-view')
		}
		this.calendarSwitchOptions = window.calendarSwitchTenants;
		this.selectedCalendar = tenantId;
	},
	methods: {
		handleSelectChange() {
			if (!vueMounted)
				return;
			showLoading();
			var vueCalApp = this;

			$.post(calendarDateSlotsUrl, paramsToGetCalendarDateSlots())
				.done(function (data) {
					vueCalApp.calMonthDaysSchedules = funCalMonthDaysSchedules(data.tourSlots);
					if (typeof data.tourBookings != 'undefined' && data.tourBookings != null) {
						vueCalApp.tourBookings = data.tourBookings;
						vueCalApp.existingBookings = JSON.stringify(data.tourBookings);
					}
				})
				.fail(function () {
					// swal('Error occurred', 'Unable to get slots of the month from server.', 'error');
				})
				.always(hideLoading);

		},
		getTourSlots: function (triggeringEl) {
			var vueCalApp = this;

			if (!vueMounted)
				return;

			showLoading();

			if (triggeringEl == 'current') {
				var yearMonth = calInfo.today.substr(0, 7).split('-');
				calInfo.year = yearMonth[0];
				this.month = yearMonth[1] - 1;

				$('#sel-cal-year').val(calInfo.year);
			}
			else if (triggeringEl == 'next') {
				var selectedOption = $("#display_option").find(":selected").val();

				if (selectedOption == 'month') {

					this.month++;

					if (this.month > 11) {
						this.month = 0;
						calInfo.year++;
					}

					$('#sel-cal-year').val(calInfo.year);
				} else {
					var currentDate = new Date();
					var currentMonth = currentDate.getMonth();
					var requestedMonth = vueCalApp.month;
					var weeksOfMonth = getWeeksStartAndEndInMonth(calInfo.year, currentMonth, requestedMonth, calInfo.today, 1, calInfo.week, "next");

					if (calInfo.week < weeksOfMonth.length) {
						calInfo.week++;
					} else {
						calInfo.week = 1;
						if (vueCalApp.month == 11) {
							calInfo.year++;
							vueCalApp.month = 0;
						} else {
							vueCalApp.month = requestedMonth + 1;
						}
					}

					$('#sel-cal-year').val(calInfo.year);
				}
			}
			else if (triggeringEl == 'previous') {
				var selectedOption = $("#display_option").find(":selected").val();

				if (selectedOption == 'month') {

					this.month--;

					if (this.month < 0) {
						this.month = 11;
						calInfo.year--;
					}

					$('#sel-cal-year').val(calInfo.year);
				} else {
					var currentDate = new Date();
					var currentMonth = currentDate.getMonth();
					var requestedMonth = vueCalApp.month;
					var weeksOfMonth = getWeeksStartAndEndInMonth(calInfo.year, currentMonth, requestedMonth, calInfo.today, 1, calInfo.week, "previous");

					if (calInfo.week <= weeksOfMonth.length) {
						if (calInfo.week == 1) {
							if (vueCalApp.month == 0) {
								calInfo.year--;
								vueCalApp.month = 11;
							} else {
								vueCalApp.month = requestedMonth - 1;
							}
							calInfo.week = weeksOfMonth.length;
						} else {
							calInfo.week--;
						}
					}

					$('#sel-cal-year').val(calInfo.year);
				}
			} else if (triggeringEl == 'year') {
				calInfo.year = $('#sel-cal-year').val();

			} else if (triggeringEl == 'tenant') {
				calInfo.year = $('#sel-cal-year').val();
				document.getElementById('packageSelectWrapper').classList.remove('invisible-preserve-space');

				if (calApp.selectedCalendar && calApp.selectedCalendar !== tenantId) {
					const packageSelect = document.getElementById('packageId');
					if (packageSelect) {
						// Reset multi-select
						for (let i = 0; i < packageSelect.options.length; i++) {
							packageSelect.options[i].selected = false;
						}
						// If using Select2, also trigger the UI reset
						if ($(packageSelect).hasClass('select2')) {
							$(packageSelect).val(null).trigger('change');
						}
					}
					document.getElementById('packageSelectWrapper').classList.add('invisible-preserve-space');
				}

			} else {
				var selectedOption = $("#display_option").find(":selected").val();
				var currentDate = new Date();
				var currentMonth = currentDate.getMonth();
				var requestedMonth = vueCalApp.month;

				if (selectedOption.length > 0) {
					calInfo.option = selectedOption;
				}

				calInfo.week = getWeeksStartAndEndInMonth(calInfo.year, currentMonth, requestedMonth, calInfo.today, 0, calInfo.week, null);
			}

			$.post(calendarDateSlotsUrl, paramsToGetCalendarDateSlots())
				.done(function (data) {
					if (calInfo.option == "week") {
						weeklyDates = data.weekDates;
						$('#calendar-table').addClass('weekly-view');
					}
					if (calInfo.option == "month") {
						$('#calendar-table').removeClass('weekly-view');
					}
					vueCalApp.calMonthDaysSchedules = funCalMonthDaysSchedules(data.tourSlots);
					if (typeof data.tourBookings != 'undefined' && data.tourBookings != null) {
						vueCalApp.tourBookings = data.tourBookings;
						vueCalApp.existingBookings = JSON.stringify(data.tourBookings);
					}
				})
				.fail(function () {
					// swal('Error occurred', 'Unable to get slots of the month from server.', 'error');
				})
				.always(hideLoading);
		},
		dayPkgSlotTimesModal: function (dayIdx, schIdx) {
			var slot = this.calMonthDaysSchedules[dayIdx].schedules[schIdx];
			var vueCalApp = this;
			showLoading();

			// Remove add time slot form if remain without save
			$(".new-time-slot-form .data-warp, .time-slots .data-warp").remove();
			clearBulkSelection();

			// --- Build the URL dynamically ---
			var dateParam = slot.date.split('-').join('');   // convert YYYY-MM-DD → YYYYMMDD
			var tourPackageId = slot.pkg_id;

			//  Check first
			if (calApp.selectedCalendar && calApp.selectedCalendar !== tenantId) {

				switchCalendarSlotTimesModal(dateParam, tourPackageId, tenantId, slot.date);
				return; // Skip the rest if calendar is switched
			}

			$.post(dayTimeSlotsUrl, {
				date: dateParam,
				tourPackageId: tourPackageId,
				getRateGroups: 0,
				allBookableStatus: 0
			})
				.done(function (response) {
					var sDT = serverDT.format('YYYY-MM-DD HH:mm:ss');

					for (var a = 0; a < response.slotTimes.length; a++) {
						if (response.slotTimes[a].bookable_status == 'Only by Phone') {
							response.slotTimes[a].bookable_status_2 = 'Only by Phone';
							response.slotTimes[a].bookable_status_2_reason = "Slot's status is set 'Only by Phone'";
						} else if (slot.date + ' ' + response.slotTimes[a].time_from < sDT) {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = 'Tour date-time reached';
						}
						else if (response.slotTimes[a].available_tourists !== undefined && response.slotTimes[a].available_tourists <= 0) {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = 'Slot full / overbooked';
						}
						else if (response.slotTimes[a].available_tourists === undefined && response.slotTimes[a].tourists >= response.slotTimes[a].seats) {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = 'Slot full / overbooked';
						}
						else if (response.slotTimes[a].bookable_status == 'Closed') {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = "Slot's status is set 'Closed'";
						} else {
							response.slotTimes[a].bookable_status_2 = response.slotTimes[a].bookable_status;
							response.slotTimes[a].bookable_status_2_reason = 'Bookings open';
						}
					}

					var is_affiliate = response.is_affiliate ? response.is_affiliate : 0;



					// Calculate header totals by summing individual slot values (optimized)
					var totals = response.slotTimes.reduce(function(acc, slotTime) {
						var slotBooked = parseInt(slotTime.tourists || 0);
						var slotSeats = parseInt(slotTime.seats || 0);
						var slotAvailable = parseInt(slotTime.available_tourists !== undefined ? 
							slotTime.available_tourists : Math.max(0, slotSeats - slotBooked));
						var slotOverbooked = Math.max(0, slotBooked - slotSeats);
						
						return {
							booked: acc.booked + slotBooked,
							available: acc.available + slotAvailable,
							overbooked: acc.overbooked + slotOverbooked
						};
					}, { booked: 0, available: 0, overbooked: 0 });

					var daySchedules = vueCalApp.calMonthDaysSchedules[dayIdx]
						? vueCalApp.calMonthDaysSchedules[dayIdx].schedules
						: [];

					var pkgSlotInfo = {
						isToday: (slot.date == calInfo.today),
						date: moment(slot.date),
						displayDate: moment(slot.date).format('dddd, MMMM Do, Y'),
						pkg_id: slot.pkg_id,
						pkg_name: slot.pkg_name,
						slotTimes: response.slotTimes,
						tour_op_id: response.tourOperatorId,
						timeSlotAffiliateBookings: [],
						timeSlotTouristsInfo: [],
						timeSlotBookingsInfo: newTimeSlotBookingsInfoObj(),
						recHistory: parseRecHistory(response.recordsHistory),
						link: rootPath + routePrefixForJs + "/booking/day-time-slots/" + slot.date.split('-').join('') + "/" + 0 + "/" + 0 + "/",
						bookedSlots: totals.booked,
						availableSlots: totals.available,
						overbookedSlots: totals.overbooked,
						is_affiliate: is_affiliate,
						tour_op_name: response.tourOperatorName ?? '',
						packageHasSlots: response.packageHasSlots,
						dayIdx: dayIdx,
						dayPackages: daySchedules.map(function(s, i) {
							return { schIdx: i, pkg_id: s.pkg_id, pkg_name: s.pkg_name, seats: s.available_seats };
						}),
					};
					vueCalApp.slotFilter = 'all';
					vueCalApp.slotSearch = '';
					vueCalApp.modalDayPkgSlots = new bootstrap.Modal($('#day-pkg-slot-times')[0]),
						vueCalApp.dayPkgSlotInfo = pkgSlotInfo;

					$('.dropdown-toggle').each(function () {
						new bootstrap.Dropdown(this);
					});

					if (response.packageHasSlots) {
						vueCalApp.modalDayPkgSlots.show();
					} else {
						vueCalApp.timeSlotBookingsModal(0);
					}
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		},
		timeSlotBookingsModal: function (timeSlotIndex) {
			var vueCalApp = this;
			vueCalApp.currentSlotIndex = timeSlotIndex; // 🟢 store active index
			var timeSlot = this.dayPkgSlotInfo.slotTimes[timeSlotIndex];
			showLoading();
			$(".checkPurchaseBy").prop('checked', false);
			$(".checkset").prop('checked', false);
			$(".booking-warp").prop("hidden", false);
			$(".booking-warp-").prop("hidden", true);
			$("#bookedCount").prop("hidden", false);
			$("#cancelledCount").prop("hidden", true);
			$("#bookedtouristsCount").prop("hidden", false);
			$("#cancelledtouristsCount").prop("hidden", true);

			// following request is also sent in updateDataForPopups() function
			$.getJSON(timeSlotBookingsInfoUrl + timeSlot.id)
				.done(function (timeSlotBookingsInfo) {
					vueCalApp.modalDayPkgSlots.hide();
					setTimeSlotBookingsInfo(timeSlotBookingsInfo, timeSlot);
					vueCalApp.modalTimeSlotBookings = new bootstrap.Modal($('#time-slot-bookings')[0]);
					vueCalApp.modalTimeSlotBookings.show();

					// 🟢 update navigation buttons
					$('#prevSlotBtn').prop('disabled', vueCalApp.currentSlotIndex === 0);
					$('#nextSlotBtn').prop('disabled', vueCalApp.currentSlotIndex === vueCalApp.dayPkgSlotInfo.slotTimes.length - 1);
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		},
		timeSlotTouristsInfoDropdown: function (timeSlotId) {
			var vueCalApp = this;
			showLoading();

			$.getJSON(timeSlotTouristsInfoUrl + timeSlotId)
				.done(function (touristsInfo) {
					vueCalApp.dayPkgSlotInfo.timeSlotTouristsInfo = touristsInfo;
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		},
		initNewBookingModal: function () {
			var dtFormat = 'YYYY-MM-DD kk:mm:ssZ';

			if (this.dayPkgSlotInfo.packageHasSlots == 1 && serverDT.format(dtFormat) > moment(this.dayPkgSlotInfo.date.format('YYYY-MM-DD') + ' ' + this.dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.time_from + serverTZ).format(dtFormat)) {
				swal('', "The tour's date-time has passed, booking isn't possible in the past.", 'warning');
				return;
			}

			$frameNewBooking = $('#frame-new-booking');
			var timeSlotId = this.dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot.id;

			showLoading();
			$frameNewBooking.data('slot-time-id', timeSlotId).attr('src', newBookingPageUrl + '?time_slot_id=' + timeSlotId + '&t=' + (new Date).getTime() + '&modal=1');
		},
		ascSortBookings: function () {
			if (calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy == true && calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings == false) {
				bookingsPurchaseBy = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookings
				const sortByDate = bookingsPurchaseBy => {
					const sorter = (a, b) => {
						return new Date(b.booking_d_t).getTime() - new Date(a.booking_d_t).getTime();
					}
					bookingsPurchaseBy.sort(sorter);
				};
				sortByDate(bookingsPurchaseBy);
			} else if (calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy == false && calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings == true) {
				bookingsPurchaseBy = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookingsCancelled.sort(function (a, b) {
					var A = (a.cust_name).toUpperCase();
					var B = (b.cust_name).toUpperCase();
					return (A < B) ? -1 : (A > B) ? 1 : 0;
				});

			} else if (calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings == true && calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy == true) {
				bookingsPurchaseBy = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookingsCancelled
				const sortByDate = bookingsPurchaseBy => {
					const sorter = (a, b) => {
						return new Date(b.booking_d_t).getTime() - new Date(a.booking_d_t).getTime();
					}
					bookingsPurchaseBy.sort(sorter);
				};
				sortByDate(bookingsPurchaseBy);
			} else {
				bookingsPurchaseBy = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookings.sort(function (a, b) {
					var A = (a.cust_name).toUpperCase();
					var B = (b.cust_name).toUpperCase();
					return (A < B) ? -1 : (A > B) ? 1 : 0;
				});
			}
		},
		ascSortCancelled: function () {
			if (calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlyCancelledBookings == true && calApp.dayPkgSlotInfo.timeSlotBookingsInfo.showOnlybookingsPurchaseBy == true) {
				bookingsPurchaseBy = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookingsCancelled
				const sortByDate = bookingsPurchaseBy => {
					const sorter = (a, b) => {
						return new Date(b.booking_d_t).getTime() - new Date(a.booking_d_t).getTime();
					}
					bookingsPurchaseBy.sort(sorter);
				};
				sortByDate(bookingsPurchaseBy);
			} else {
				bookingsPurchaseBy = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookingsCancelled.sort(function (a, b) {
					var A = (a.cust_name).toUpperCase();
					var B = (b.cust_name).toUpperCase();
					return (A < B) ? -1 : (A > B) ? 1 : 0;
				});

			}

		},
		showNewBookingModal: function () {
			if ($('#frame-new-booking').attr('src') == '') // when calendar page loads
				return;

			hideLoading();
			// this.modalTimeSlotBookings.hide();
			// only hide time-slot modal if this is the origin
			if (this.modalNewBookingOrigin !== 'day-slot' && this.modalTimeSlotBookings && typeof this.modalTimeSlotBookings.hide === 'function') {
				this.modalTimeSlotBookings.hide();
			}

			if (this.modalNewBooking == null)
				this.modalNewBooking = new bootstrap.Modal($('#modal-new-booking')[0]);

			this.modalNewBooking.show();
		},
		initBookingInfoModal: function (bookingId, bookingInfoModalOpenedFrom) {
			showLoading();
			$frameBookingInfo = $('#frame-booking-info');

			$frameBookingInfo.attr('src', bookingInfoPageUrl + bookingId + '?modal=1')
				.attr('booking-info-modal-opened-from', bookingInfoModalOpenedFrom);
		},
		showBookingInfoModal: function () {
			var frameBookingInfo = $('#frame-booking-info')[0];

			if (frameBookingInfo.src == '' || frameBookingInfo.src == location.href) // when calendar page loads
				return;

			if (this.modalTimeSlotBookings)
				this.modalTimeSlotBookings.hide();

			hideLoading();

			if (typeof frameBookingInfo.contentWindow.modalTitle != 'undefined')
				$('#modal-booking-info .modal-header h3').text(frameBookingInfo.contentWindow.modalTitle);

			if (this.modalBookingInfo == null)
				this.modalBookingInfo = new bootstrap.Modal($('#modal-booking-info')[0]);

			this.modalBookingInfo.show();
		},
		hideBookingInfoModal: function () {
			try {
				if (this.modalBookingInfo && typeof this.modalBookingInfo.hide === 'function') {
					this.modalBookingInfo.hide();
				}

				var openedFrom = $('#frame-booking-info').attr('booking-info-modal-opened-from');

				if (openedFrom == 'modal') {
					if (this.modalTimeSlotBookings && typeof this.modalTimeSlotBookings.show === 'function') {
						this.modalTimeSlotBookings.show();
					}
				}
			} catch (error) {
				console.error('Error in hideBookingInfoModal:', error);
			}
		},
		getAffiliateBookings: function (timeSlotId) {
			var vueCalApp = this;
			showLoading();

			$.get(getAffiliateBookingsUrl + timeSlotId)
				.done(function (affiliateBookings) {

					vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings = affiliateBookings;
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		},
		editAffiliateBooking: function (timeSlotId, affiliateId, tourists) {
			var $divAffilBookingsInfo = $('#affiliate-bookings-info-' + timeSlotId);
			$divAffilBookingsInfo.find('.sel-affiliate').val(affiliateId);
			$divAffilBookingsInfo.find('.txt-affiliate-seats').val(tourists);
			this.showAffiliateBookingForm();
		},
		showAffiliateBookingForm: function () {
			$('.btn-show-save-afl-booking').addClass('hidden');
			$('.div-save-afl-booking').removeClass('hidden');
		},
		saveAffiliateBooking: function (timeSlotId) {
			var $divAffilBookingsInfo = $('#affiliate-bookings-info-' + timeSlotId);
			var $txtSeats = $divAffilBookingsInfo.find('.txt-affiliate-seats');

			if ($txtSeats.val() == '' || $txtSeats.val() == 0) {
				$txtSeats[0].focus();
				return;
			}
			var vueCalApp = this;
			if (userType != 'Affiliate') {
				var affiliateId = $divAffilBookingsInfo.find('.sel-affiliate').val();
				$.post(checkOverbookingUrl, {
					'slot_time': timeSlotId,
					'affiliate_id': affiliateId
				}).then(function (response) {
					var bookingTourists = $txtSeats.val();
					if (response.affiliate_tourists > 0 && bookingTourists <= response.affiliate_tourists) {
						makeAffiliateBooking(vueCalApp);
					} else {
						if (response.affiliate_tourists > 0) {
							bookingTourists = bookingTourists - response.affiliate_tourists;
						}
						var seats = response.seats - bookingTourists;
						var overbooked = response.booked_seats - seats;
						if (overbooked >= 0) {
							var addText = '';
							if (overbooked > 0) {
								addText += ' This slot will be overbooked by ' + overbooked + ' seats.';
							} else if (overbooked == 0) {
								addText += ' This slot will be full.';
							}
							addText += ' Do you want to proceed?';

							swal({
								title: 'An overbooking event',
								text: addText,
								icon: "warning",
								dangerMode: true,
								buttons: true,
								buttons: {
									no: {
										text: 'Cancel',
										value: false,
									},
									yes: {
										text: 'Continue',
										value: true,
										className: 'btn-danger',
									}
								}
							}).then(function (result) {
								if (result) {
									makeAffiliateBooking(vueCalApp);
								} else {
									return false;
								}
							});
						} else {
							makeAffiliateBooking(vueCalApp);
						}
					}
				});
			} else {
				makeAffiliateBooking(vueCalApp);
			}

			//This function added to reuse same code in check overbooking feature
			function makeAffiliateBooking(vueCalApp) {
				showLoading();

				var affiliateId = $divAffilBookingsInfo.find('.sel-affiliate').val();

				$.post(saveAffiliateBookingUrl, {
					tour_slot_id: timeSlotId,
					affiliate_id: affiliateId,
					seats: $txtSeats.val()
				})
					.done(function (response) {
						var msg = '';

						if ($txtSeats.val() == 0)
							msg = 'Booking of the affiliate is set to Zero and booking block marked as Cancelled.';
						else
							msg = 'Seats booked/reserved for the affiliate.';

						$divAffilBookingsInfo.find('.btn-save-afl-booking')
							.before('<div class="alert alert-success alert-dismissible alert-sm text-xs !p-4 !mb-2" role="alert">' + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');

						var a = 0, affilsTourists = 0, hadAffilAlreadyBooked = false;

						for (a = 0; a < vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings.length; a++) {
							if (vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings[a].affiliate_id == affiliateId) {
								vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings[a].tourists = $txtSeats.val();
								hadAffilAlreadyBooked = true;
								affilsTourists += parseInt($txtSeats.val());
							} else
								affilsTourists += parseInt(vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings[a].tourists);
						}

						if (!hadAffilAlreadyBooked) {
							affilsTourists += parseInt($txtSeats.val());
							var selAffiliate = $divAffilBookingsInfo.find('.sel-affiliate')[0];

							vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings.push({
								affiliate_id: affiliateId,
								name: selAffiliate.options[selAffiliate.selectedIndex].text,
								tourists: $txtSeats.val()
							});
						}

						for (a = 0; a < vueCalApp.dayPkgSlotInfo.slotTimes.length; a++) {
							if (vueCalApp.dayPkgSlotInfo.slotTimes[a].id == timeSlotId) {
								vueCalApp.dayPkgSlotInfo.slotTimes[a].afl_tourists = affilsTourists;
								vueCalApp.dayPkgSlotInfo.slotTimes[a].tourists = response.totalBookedSeats;
								vueCalApp.dayPkgSlotInfo.slotTimes[a].booked_seats = response.totalBookedSeats;
								break;
							}
						}
					})
					.fail(showSubmissionError)
					.always(hideLoading);
			}
		},
		cancelAffiliateBooking: function (timeSlotId, affiliateId) {
			var vueCalApp = this;

			swal({
				title: 'Cancel Affiliate Booking?',
				text: 'Are you sure?',
				icon: "warning",
				buttons: {
					no: {
						text: 'No',
						value: false,
					},
					yes: {
						text: 'Yes',
						className: 'btn-danger',
					}
				}
			})
				.then(function (result) {
					if (!result)
						return;

					showLoading();

					$.post(saveAffiliateBookingUrl, {
						tour_slot_id: timeSlotId,
						affiliate_id: affiliateId,
						seats: 0,
					})
						.done(function (response) {
							var aflSeats = 0;

							for (var a = 0; a < vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings.length; a++) {
								if (vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings[a].affiliate_id == affiliateId) {
									aflSeats = vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings[a].tourists;
									vueCalApp.dayPkgSlotInfo.timeSlotAffiliateBookings.splice(a, 1);
									break;
								}
							}

							for (var a = 0; a < vueCalApp.dayPkgSlotInfo.slotTimes.length; a++) {
								if (vueCalApp.dayPkgSlotInfo.slotTimes[a].id == timeSlotId) {
									vueCalApp.dayPkgSlotInfo.slotTimes[a].afl_tourists -= aflSeats;
									vueCalApp.dayPkgSlotInfo.slotTimes[a].tourists = response.totalBookedSeats;
									break;
								}
							}

							$('#affiliate-bookings-info-' + timeSlotId).find('.div-afl-book-title')
								.before('<div class="alert alert-success alert-dismissible alert-sm" role="alert">Booking of the affiliate is set to Zero and booking block marked as Cancelled.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');
						})
						.fail(showSubmissionError)
						.always(hideLoading);
				});
		},
		currencyFormat: function (amount) {
			return `${currencyFormation.symbol}${new Intl.NumberFormat('en-US', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(amount)}`;
		},
		getDisputeTooltip: function (disputeInfo) {
			if (!disputeInfo) return '';
			
			let tooltip = `Dispute: ${disputeInfo.status_label}\n`;
			tooltip += `Reason: ${disputeInfo.reason_label}\n`;
			tooltip += `Amount: ${disputeInfo.currency} ${disputeInfo.amount}\n`;
			
			if (disputeInfo.evidence_due_by) {
				const dueDate = new Date(disputeInfo.evidence_due_by);
				tooltip += `Evidence Due: ${dueDate.toLocaleDateString()}\n`;
				
				if (disputeInfo.days_until_deadline !== null) {
					if (disputeInfo.days_until_deadline > 0) {
						tooltip += `Days Remaining: ${disputeInfo.days_until_deadline}`;
					} else if (disputeInfo.days_until_deadline === 0) {
						tooltip += `Due Today!`;
					} else {
						tooltip += `Overdue by ${Math.abs(disputeInfo.days_until_deadline)} days`;
					}
				}
			}
			
			if (disputeInfo.requires_attention) {
				tooltip += `\n⚠️ Requires Immediate Attention`;
			}
			
			return tooltip;
		},
		switchCompany: function () {
			showLoading();
			url = switchCompanyUrl.replace(':id', this.partner);

			$.get(url)
				.done(function (data) {
					window.location = data.url;
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		},
		switchDayPackage: function (schIdx) {
			var self = this;
			var dayIdx = self.dayPkgSlotInfo.dayIdx;
			if (dayIdx === null || !self.calMonthDaysSchedules[dayIdx]) return;

			var slot = self.calMonthDaysSchedules[dayIdx].schedules[schIdx];
			if (!slot) return;

			self.slotFilter = 'all';
			self.slotSearch = '';
			showLoading();

			$(".new-time-slot-form .data-warp").remove();

			$.post(dayTimeSlotsUrl, {
				date: slot.date.split('-').join(''),
				tourPackageId: slot.pkg_id,
				getRateGroups: 0,
				allBookableStatus: 0
			}).done(function (response) {
				var sDT = serverDT.format('YYYY-MM-DD HH:mm:ss');

				for (var a = 0; a < response.slotTimes.length; a++) {
					if (response.slotTimes[a].bookable_status == 'Only by Phone') {
						response.slotTimes[a].bookable_status_2 = 'Only by Phone';
						response.slotTimes[a].bookable_status_2_reason = "Slot's status is set 'Only by Phone'";
					} else if (slot.date + ' ' + response.slotTimes[a].time_from < sDT) {
						response.slotTimes[a].bookable_status_2 = 'Closed';
						response.slotTimes[a].bookable_status_2_reason = 'Tour date-time reached';
					} else if (response.slotTimes[a].available_tourists !== undefined && response.slotTimes[a].available_tourists <= 0) {
						response.slotTimes[a].bookable_status_2 = 'Closed';
						response.slotTimes[a].bookable_status_2_reason = 'Slot full / overbooked';
					} else if (response.slotTimes[a].available_tourists === undefined && response.slotTimes[a].tourists >= response.slotTimes[a].seats) {
						response.slotTimes[a].bookable_status_2 = 'Closed';
						response.slotTimes[a].bookable_status_2_reason = 'Slot full / overbooked';
					} else if (response.slotTimes[a].bookable_status == 'Closed') {
						response.slotTimes[a].bookable_status_2 = 'Closed';
						response.slotTimes[a].bookable_status_2_reason = "Slot's status is set 'Closed'";
					} else {
						response.slotTimes[a].bookable_status_2 = response.slotTimes[a].bookable_status;
						response.slotTimes[a].bookable_status_2_reason = 'Bookings open';
					}
				}

				var totals = response.slotTimes.reduce(function(acc, s) {
					var booked = parseInt(s.tourists || 0);
					var seats  = parseInt(s.seats || 0);
					var avail  = parseInt(s.available_tourists !== undefined ? s.available_tourists : Math.max(0, seats - booked));
					return { booked: acc.booked + booked, available: acc.available + avail, overbooked: acc.overbooked + Math.max(0, booked - seats) };
				}, { booked: 0, available: 0, overbooked: 0 });

				var daySchedules = self.calMonthDaysSchedules[dayIdx].schedules;

				self.$set(self.dayPkgSlotInfo, 'pkg_id',    slot.pkg_id);
				self.$set(self.dayPkgSlotInfo, 'pkg_name',  slot.pkg_name);
				self.$set(self.dayPkgSlotInfo, 'slotTimes', response.slotTimes);
				self.$set(self.dayPkgSlotInfo, 'bookedSlots',    totals.booked);
				self.$set(self.dayPkgSlotInfo, 'availableSlots', totals.available);
				self.$set(self.dayPkgSlotInfo, 'overbookedSlots',totals.overbooked);
				self.$set(self.dayPkgSlotInfo, 'recHistory', parseRecHistory(response.recordsHistory));
				self.$set(self.dayPkgSlotInfo, 'timeSlotAffiliateBookings', []);
				self.$set(self.dayPkgSlotInfo, 'timeSlotTouristsInfo', []);
				self.$set(self.dayPkgSlotInfo, 'timeSlotBookingsInfo', newTimeSlotBookingsInfoObj());
				self.$set(self.dayPkgSlotInfo, 'packageHasSlots', response.packageHasSlots);
				self.$set(self.dayPkgSlotInfo, 'is_affiliate', response.is_affiliate || 0);
				self.$set(self.dayPkgSlotInfo, 'tour_op_id',   response.tourOperatorId);
				self.$set(self.dayPkgSlotInfo, 'dayPackages',  daySchedules.map(function(s, i) {
					return { schIdx: i, pkg_id: s.pkg_id, pkg_name: s.pkg_name, seats: s.available_seats };
				}));

				clearBulkSelection();

				self.$nextTick(function () {
					$('.dropdown-toggle').each(function () { new bootstrap.Dropdown(this); });
				});
			}).fail(showSubmissionError).always(hideLoading);
		},
		setBookableStatus: function (slot_id, bookable_status) {
			initBookableStatus(slot_id, bookable_status);
		},
		toggleSlotVisibility: function (slot_id) {
			var self = this;
			var slot = self.dayPkgSlotInfo.slotTimes.find(function (s) { return s.id == slot_id; });
			var isCurrentlyHidden = slot && slot.hide_slot == 1;
			var action = isCurrentlyHidden ? 'make this slot visible' : 'hide this slot';

			swal({
				title: 'Update Slot Visibility',
				text: 'Are you sure you want to ' + action + '?',
				icon: 'warning',
				dangerMode: true,
				buttons: {
					no: { text: 'Cancel', value: false, className: 'btn-danger' },
					yes: { text: 'Continue', value: true, className: 'btn-success' }
				}
			}).then(function (result) {
				if (!result) return;
				showLoading();
				$.post(updateSlotHideStatusUrl, { slot_id: slot_id })
					.done(function (response) {
						if (slot) {
							self.$set(slot, 'hide_slot', response.hide_slot);
						}
					})
					.fail(showSubmissionError)
					.always(hideLoading);
			});
		},
		// Safe modal methods to prevent Vue.js errors
		safeHideTimeSlotBookings: function() {
			try {
				if (this.modalTimeSlotBookings && typeof this.modalTimeSlotBookings.hide === 'function') {
					this.modalTimeSlotBookings.hide();
				}
			} catch (error) {
				console.error('Error hiding modalTimeSlotBookings:', error);
			}
		},
		safeShowDayPkgSlots: function() {
			try {
				if (this.modalDayPkgSlots && typeof this.modalDayPkgSlots.show === 'function') {
					this.modalDayPkgSlots.show();
				}
			} catch (error) {
				console.error('Error showing modalDayPkgSlots:', error);
			}
		},
		safeHideDayPkgSlots: function() {
			try {
				if (this.modalDayPkgSlots && typeof this.modalDayPkgSlots.hide === 'function') {
					this.modalDayPkgSlots.hide();
				}
			} catch (error) {
				console.error('Error hiding modalDayPkgSlots:', error);
			}
		},
		safeHideNewBooking: function() {
			try {
				if (this.modalNewBooking && typeof this.modalNewBooking.hide === 'function') {
					this.modalNewBooking.hide();
				}
			} catch (error) {
				console.error('Error hiding modalNewBooking:', error);
			}
		},
		safeShowTimeSlotBookings: function() {
			try {
				if (this.modalTimeSlotBookings && typeof this.modalTimeSlotBookings.show === 'function') {
					this.modalTimeSlotBookings.show();
				}
			} catch (error) {
				console.error('Error showing modalTimeSlotBookings:', error);
			}
		},
		initNewBookingModalBySlot: function (timeSlotId, originType = 'day-slot') {
			this.modalNewBookingOrigin = originType; // store the origin type

			// Find the matching slot object first
			var slotObj = this.dayPkgSlotInfo.slotTimes.find(s => s.id == timeSlotId);
			if (!slotObj) {
				swal('', "Invalid slot selected.", 'error');
				return;
			}

			// Prevent past booking
			if (
				this.dayPkgSlotInfo.packageHasSlots == 1 &&
				serverDT.isAfter(moment(this.dayPkgSlotInfo.date.format('YYYY-MM-DD') + ' ' + slotObj.time_from + serverTZ))
			) {
				swal('', "The tour's date-time has passed, booking isn't possible in the past.", 'warning');
				return;
			}

			$frameNewBooking = $('#frame-new-booking');

			showLoading();
			$frameNewBooking.data('slot-time-id', timeSlotId).attr('src', newBookingPageUrl + '?time_slot_id=' + timeSlotId + '&t=' + (new Date).getTime() + '&modal=1');
		}
	},
	computed: {
		canSwitchCalendar() {
			return this.appRoles.includes('super-admin') || this.appPermissions.includes('switch-calendar');
		},
		filteredSlotTimes() {
			var slots = this.dayPkgSlotInfo.slotTimes;
			var filter = this.slotFilter;
			var search = this.slotSearch.toLowerCase().trim();

			if (filter === 'open')       slots = slots.filter(function(s) { return s.bookable_status_2 === 'Open'; });
			else if (filter === 'phone') slots = slots.filter(function(s) { return s.bookable_status_2 === 'Only by Phone'; });
			else if (filter === 'closed')  slots = slots.filter(function(s) { return s.bookable_status_2 === 'Closed'; });
			else if (filter === 'visible') slots = slots.filter(function(s) { return !s.hide_slot || s.hide_slot == 0; });
			else if (filter === 'hidden')  slots = slots.filter(function(s) { return s.hide_slot == 1; });

			if (search) {
				slots = slots.filter(function(s) {
					// Search against the 12-hour formatted label (what the user sees),
					// not the raw 24-hour "HH:MM:SS" strings stored in the data.
					var label = (formatTime(s.time_from || '') + ' - ' + formatTime(s.time_to || '')).toLowerCase();
					return label.indexOf(search) !== -1;
				});
			}

			return slots;
		}
	},
	filters: {
		formatAmt: function (paidAmt, totalAmt) {
			return ((paidAmt != 0 && paidAmt != null) ? 'Paid' : `${currencyFormation.symbol}${new Intl.NumberFormat('en-US', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2
			}).format(totalAmt - paidAmt)}`);
		},
		formatTourDT: function (dt) {
			return moment(dt).format('ddd - MMM / D / Y @ h:mm a');
		},
		formatTourDateOnly: function (dt) {
			return moment(dt).format('ddd - MMM / D / Y');
		},
		formatBookingDT: function (dt) {
			return moment(dt).format('M/D/Y @ h:mm a');
		},
		formatSlotTimeDuration: function (timeSlot) {
			return formatTime(timeSlot.time_from) +
				(timeSlot.time_to ? ' - ' + formatTime(timeSlot.time_to) : ' onwards');
		},
		shortAgeGrpAndBookedSeatsInfo: function (touristsInfo) {
			var agbs = [];
			for (var a = 0; a < touristsInfo.length; a++)
				if (touristsInfo[a].rate_for.indexOf('Adults - Military Teachers') !== -1 || touristsInfo[a].rate_for.indexOf('Children (Ages 8-12) - Military/Teachers') !== -1 || touristsInfo[a].rate_for.indexOf('Children (Ages 4-7) - Military/Teachers') !== -1) {
					if (touristsInfo[a].rate_for.indexOf('Adults - Military Teachers') !== -1) {
						agbs.push('AM:' + touristsInfo[a].tourists);
					} else if (touristsInfo[a].rate_for.indexOf('Children (Ages 8-12) - Military/Teachers') !== -1 || touristsInfo[a].rate_for.indexOf('Children (Ages 4-7) - Military/Teachers') !== -1) {
						agbs.push('CM:' + touristsInfo[a].tourists);
					}
				} else {
					agbs.push(touristsInfo[a].rate_for[0] + ':' + touristsInfo[a].tourists);
				}
			return agbs.join(' | ');
		},
		shortAgeGrpAndBookedSeatsInfoCancelled: function (touristsInfoCancelled) {
			var agbss = [];
			for (var a = 0; a < touristsInfoCancelled.length; a++)
				if (touristsInfoCancelled[a].rate_for.indexOf('Adults - Military Teachers') !== -1 || touristsInfoCancelled[a].rate_for.indexOf('Children (Ages 8-12) - Military/Teachers') !== -1 || touristsInfoCancelled[a].rate_for.indexOf('Children (Ages 4-7) - Military/Teachers') !== -1) {
					if (touristsInfoCancelled[a].rate_for.indexOf('Adults - Military Teachers') !== -1) {
						agbss.push('AM:' + touristsInfoCancelled[a].tourists);
					} else if (touristsInfoCancelled[a].rate_for.indexOf('Children (Ages 8-12) - Military/Teachers') !== -1 || touristsInfoCancelled[a].rate_for.indexOf('Children (Ages 4-7) - Military/Teachers') !== -1) {
						agbss.push('CM:' + touristsInfoCancelled[a].tourists);
					}
				} else {
					agbss.push(touristsInfoCancelled[a].rate_for[0] + ':' + touristsInfoCancelled[a].tourists);
				}
			return agbss.join(' | ');
		},
		getPaymentCls: function (booking) {
			return 'finalammount ' + (booking.paid < booking.total ? 'un' : '') + 'paid';
		},
		getVisibilityBtnCls: function (hide_slot) {
			return hide_slot == 1
				? 'bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C]'
				: 'bg-[#F0FDF4] border-[#BBF7D0] text-[#166534]';
		},
		getBookableStatusCls: function (bookable_status) {
			if (bookable_status === 'Open')
				return 'dropdown-toggle bookable bg-[#F0FDF4] border-[#BBF7D0] text-[#166534]';
			if (bookable_status === 'Only by Phone')
				return 'dropdown-toggle callback bg-[#EFF6FF] border-[#BFDBFE] text-[#1E40AF]';
			return 'dropdown-toggle bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C]';
		},
		getBookedSeatsCls: function (slot) {
			if (userType == 'Affiliate') {
				return 'dropdown-toggle ' + ((slot.tourists == 0 && slot.afl_tourists == 0) ? 'availabilitycall bg-[#EFF6FF] border-[#BFDBFE]' : (slot.tourists < slot.seats ? 'bookable bg-[#F0FDF4] border-[#BBF7D0]' : 'overbooked bg-[#FEF2F2] border-[#FECACA]'));
			} else {
				return 'dropdown-toggle ' + (slot.tourists == 0 ? 'availabilitycall bg-[#EFF6FF] border-[#BFDBFE]' : (slot.tourists < slot.seats ? 'bookable bg-[#F0FDF4] border-[#BBF7D0]' : 'overbooked bg-[#FEF2F2] border-[#FECACA]'));
			}

		},
		availableSeatsInfoCls: function (slot) {
			// Use the available_tourists field from backend if available, otherwise calculate
			var availableSeats = slot.available_tourists !== undefined ? parseInt(slot.available_tourists) : Math.max(0, slot.seats - slot.tourists);

			return availableSeats > 0 ? 'text-[#15803D]' : 'text-[#B91C1C]';
		},
		availableSeatsInfo: function (slot) {
			// Use the available_tourists field from backend if available, otherwise calculate
			var availableSeats = slot.available_tourists !== undefined ? parseInt(slot.available_tourists) : Math.max(0, slot.seats - slot.tourists);
			var overbooked = Math.max(0, slot.tourists - slot.seats);

			return availableSeats > 0
				? availableSeats + ' Available'
				: (overbooked > 0 ? overbooked + ' Overbooked' : '0 Available');
		}
	},
	updated: resizeBookingsList
});

function switchCalendarSlotTimesModal(dateParam, tourPackageId, tenantId, date) {

	var url = switchCalendardayTimeSlotsUrl
		.replace('__DATE__', dateParam)
		.replace('__PKGID__', tourPackageId)
		.replace('__CALID__', calApp.selectedCalendar);

	$.get(url, function (response) {
		var tbody = $("#switchcalendarTableBody");
		tbody.empty();

		const dateSwitchCalendar = new Date(date);
		const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
		const formattedDate = dateSwitchCalendar.toLocaleDateString('en-US', options).replace(',', '');
		document.getElementById('switchCalendarDate').textContent = formattedDate;

		const calendarName = calApp.selectedCalendar;
		const formattedName = calendarName.toUpperCase();
		document.getElementById('switchCalendarName').textContent = formattedName;

		if (response.dayBookings && response.dayBookings.length > 0) {
			const groupedSlots = {};

			response.dayBookings.forEach(function (row) {
				const timeFrom = new Date(`${row.date}T${row.time_from}`);
				const timeTo = new Date(`${row.date}T${row.time_to}`);

				const timeSlotLabel = `${timeFrom.toLocaleTimeString('en-US', {
					hour: 'numeric', minute: '2-digit', hour12: true
				})} - ${timeTo.toLocaleTimeString('en-US', {
					hour: 'numeric', minute: '2-digit', hour12: true
				})}`;

				if (!groupedSlots[timeSlotLabel]) {
					groupedSlots[timeSlotLabel] = [];
				}
				groupedSlots[timeSlotLabel].push(row);
			});

			let accordionHTML = `
			    <div class="accordion mt-2" id="timeSlotAccordion">`;

			let idx = 0;
			for (const [timeSlot, bookings] of Object.entries(groupedSlots)) {
				const collapseId = `collapseTimeSlot${idx}`;
				const headingId = `headingTimeSlot${idx}`;

				accordionHTML += `
				    <div class="accordion-item mt-3">
				        <h2 class="accordion-header" id="${headingId}">
				            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" aria-expanded="true" aria-controls="${collapseId}">
				                ${timeSlot} <span class="badge bg-primary ms-3">${bookings.length} bookings</span>
				            </button>
				        </h2>
				        <div id="${collapseId}" class="accordion-collapse collapse show" aria-labelledby="${headingId}">
				            <div class="accordion-body p-0">
				                <table class="table table-bordered table-striped align-middle m-0">
				                    <thead class="table-header-blue">
				                        <tr>
				                            <th style="width: 100px;">Booking #</th>
				                            <th style="width: 230px;">Contact</th>
				                            <th style="width: 150px;">Total Pax</th>
				                            <th style="width: 150px;">Tickets</th>
				                            <th style="width: 150px;">Comments</th>
				                            <th style="width: 100px;">Waivers</th>
				                            <th style="width: 90px;">Payment</th>
				                            <th style="width: 120px;">Booked By</th>
				                            <th style="width: 180px;">Notes</th>
				                        </tr>
				                    </thead>
				                    <tbody>`;

				bookings.forEach(function (row) {
					const totalTourists = Number(row.tourists || 0);
					const signedCount = Number(row.counter || 0);
					const notSigned = totalTourists - signedCount;

					const waiverStatus = [
						signedCount > 0 ? `<span style="color: #2fa136; font-weight: 500;">${signedCount} Signed</span>` : '',
						notSigned > 0 ? `<span style="color: #ED3F3F; font-weight: 500;">${notSigned} Not Signed</span>` : ''
					].filter(Boolean).join(" / ");

					const totalGuestsText = row.groupRates
						? Object.entries(row.groupRates).map(([k, v]) => `${k}: ${v}`).join(", ")
						: "";

					const paidMethods = ['Card', 'Cash', 'Invoiced', ...response.otaPlatforms];
					const paidStatus = (paidMethods.includes(row.via) && Number(row.booking_total) <= Number(row.paids))
						? `<span style="color: #2fa136; font-weight: 500; font-size: small;"><i class="fa fa-check-circle"></i> Paid</span>`
						: `<span style="color: #ED3F3F; font-weight: 500; font-size: small;"><i class="fa fa-times-circle"></i> Unpaid</span>`;

					const displayCheckIns = row.checkInsTotal || row.notCheckInsTotal ? row.checkInsTotal : row.redeemed_tickets;

					const currentHost = window.location.hostname; // e.g., "kens.tour-backend.test" or "localhost"
					const tenantSubdomain = calApp.selectedCalendar; // e.g., "kens", "apm"
					const bookingId = row.booking_id;

					// Extract base domain 
					let baseDomainParts = currentHost.split('.');
					if (baseDomainParts.length >= 3) {
						baseDomainParts.shift(); // remove subdomain
					}
					const baseDomain = baseDomainParts.join('.');

					// Build full URL
					let bookingUrl;
					bookingUrl = `https://${tenantSubdomain}.${baseDomain}/tour-booking-info/${bookingId}`;

					// Render Booking # as link
					const bookingLink = `<a href="${bookingUrl}" target="_blank" class="text-decoration-underline fw-bold">${bookingId}</a>`;

					accordionHTML += `
                        <tr>
                            <td style="width: 100px;">${bookingLink}</td>
                            <td style="width: 230px;">${row.customer_name} ${row.email}</td>
                            <td style="width: 150px;">${totalGuestsText}</td>
                            <td style="width: 150px;">${displayCheckIns}/${row.tourists} ${row.tour_package_name}</td>
                            <td style="width: 150px;">${row.comment || ''}</td>
                            <td style="width: 100px;">${waiverStatus}</td>
                            <td style="width: 90px;">${paidStatus}</td>
                            <td style="width: 120px;">${row.created_by_user_type}</td>
                            <td style="width: 180px;">${row.note || ''}</td>
                        </tr>`;
				});

				accordionHTML += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>`;

				idx++;
			}

			accordionHTML += `</div>`;
			tbody.append(accordionHTML);
		} else {
			tbody.append('<div class="text-center py-4">No bookings found.</div>');
		}

		const modal = new bootstrap.Modal(document.getElementById('switchcalendarModal'));
		modal.show();
	}).always(hideLoading);
}

function resizeBookingsList() {
	var winHeight = $(window).height();
	var $cal = $('.cal-table');
	var $bookingsList = $('.bookings-list');

	if ($cal.height() + $cal.offset().top < winHeight)
		$bookingsList.css('max-height', (winHeight - $bookingsList.offset().top) - 24 + 'px');
	else
		$bookingsList.css('max-height', $cal.height() + 'px');
}

$(function () {
	$(window).on('resize', resizeBookingsList);
	resizeBookingsList();

	$(document).on('click', '.timebutton', function () {
		calApp.timeSlotBookingsModal($(this).data('time-slot-index'));
	});

	$(document).on('show.bs.dropdown', '.dropdown-toggle', function (event) {
		switch ($(this).data('dropdown-of')) {
			case 'tourists age/rage groups':
				calApp.timeSlotTouristsInfoDropdown($(this).data('time-slot-id'));
				break;
			case 'affiliate bookings':
				$('.div-save-afl-booking').addClass('hidden')
					.find('.alert-dismissible').remove();

				if (aflBookDd != null)
					aflBookDd.hide();

				aflBookDd = bootstrap.Dropdown.getInstance(event.target);
				$('.btn-show-save-afl-booking').removeClass('hidden');
				var timeSlotId = $(this).attr('data-time-slot-id');
				$('#affiliate-bookings-info-' + timeSlotId).find('.alert-dismissible').remove();
				calApp.getAffiliateBookings(timeSlotId);
				break;
		}
	});

	$(document).on('hide.bs.modal', function () {
		$('.modal-backdrop').remove();

		if (aflBookDd != null)
			aflBookDd.hide();
	});

	$(document).on('shown.bs.modal', function () {
		// multiple modal backdrops are created and old one is not removed automatically when ...
		// ... opening new modal, so keep only one backdrop
		if ($('.modal-backdrop').length > 1) {
			var a = 1, al = $('.modal-backdrop').length;

			$('.modal-backdrop').each(function () {
				if (a < al) {
					a++;
					$(this).remove();
				}
			});
		}
	});

	$('#search').on('input, keyup', function () {
		var input = $(this);
		if (input.val().length < 1) {
			calApp.searching = false;
			calApp.tourBookings = JSON.parse(calApp.existingBookings);
		} else {
			calApp.searching = true;

			$.getJSON(bookingSearchUrl, paramsToSearchBooking(), function (data) {
				if (data.search == input.val()) {
					calApp.searching = false;
					calApp.searchDone = true;
					calApp.tourBookings = data.bookings;
				}
			});
		}
	});

	$("#btset").click(function () {
		$(".booking-warp").prop("hidden", false);
		$(".booking-warp-").prop("hidden", true);
		$("#bookedCount").prop("hidden", false);
		$("#cancelledCount").prop("hidden", true);
		$("#bookedtouristsCount").prop("hidden", false);
		$("#cancelledtouristsCount").prop("hidden", true);
	});
	$('#packageId').on('select2:select', function (e) {
		var data = e.params.data;

		if (data.id == 0) {
			$('#packageId').val('').trigger("change");
			$('#packageId').val(0).trigger("change");
			$('#packageId').select2({ closeOnSelect: false });
		} else {
			// Get all selected package IDs except 0
			var packageId = $("#packageId").val() || [];
			packageId = packageId.filter(function (item) {
				return item != 0;
			});

			// Set selected package value
			$('#packageId').val(packageId).trigger("change");
		}
	});
	$(".checkset").click(function () {
		if ($("input[name='checkbox']:checked").val() == "1") {
			$(".booking-warp").prop("hidden", true);
			$(".booking-warp-").prop("hidden", false);
			$("#bookedCount").prop("hidden", true);
			$("#cancelledCount").prop("hidden", false);
			$("#bookedtouristsCount").prop("hidden", true);
			$("#cancelledtouristsCount").prop("hidden", false);
		}
		if ($("input[name='checkbox']:checked").val() == null) {
			$(".booking-warp").prop("hidden", false);
			$(".booking-warp-").prop("hidden", true);
			$("#bookedCount").prop("hidden", false);
			$("#cancelledCount").prop("hidden", true);
			$("#bookedtouristsCount").prop("hidden", false);
			$("#cancelledtouristsCount").prop("hidden", true);
		}
	});

});

/**
 * Real-time update system for parent-child packages
 *
 * Features:
 * - Updates calendar grid in real-time when bookings change
 * - Handles parent-child package relationships with server refresh
 * - Updates simple packages with fast client-side calculations
 * - Visual effects with animations and flash highlights
 * - Debounced updates to prevent excessive server calls
 *
 * Quick disable: RealTimeUpdates.enabled = false;
 */
var RealTimeUpdates = {
	// Configuration - Set to false to disable real-time updates
	enabled: true,

	// Visual effects configuration
	config: {
		enableVisualEffects: true,
		enableConsoleLogging: false,  // Disabled for production
		debounceDelay: 500,
		animationDuration: 300,
		flashDuration: 200
	},

	// Cache for tracking update operations
	updateQueue: [],
	isUpdating: false,

	// Initialize real-time update system
	init: function() {
		if (!this.enabled) {
			if (this.config.enableConsoleLogging) {
				console.log('Real-time updates are disabled');
			}
			return;
		}

		if (this.config.enableConsoleLogging) {
			console.log('Real-time updates initialized for parent-child packages');
		}
	},

	// Main entry point for booking updates
	handleBookingUpdate: function(booking, operation) {
		if (!this.enabled) return;

		// Always refresh modals for payment-related events (new bookings from payment completion)
		// This ensures Time Slot Bookings Modal refreshes when payment is made
		if (operation === 'new' || operation === 'update') {
			this.refreshOpenModals();
		}

		// Check if this package should be treated as having relations
		var shouldUseRelatedLogic = booking.has_relations;

		if (shouldUseRelatedLogic) {
			this.handleRelatedPackageUpdate(booking, operation);
		} else {
			this.handleSimplePackageUpdate(booking, operation);
		}
	},

	// Handle updates for packages with parent-child relationships
	handleRelatedPackageUpdate: function(booking, operation) {
		// Add to update queue to prevent multiple simultaneous refreshes
		this.updateQueue.push({
			booking: booking,
			operation: operation,
			timestamp: Date.now()
		});

		// Debounce updates to avoid excessive server calls
		if (!this.isUpdating) {
			this.isUpdating = true;
			setTimeout(() => {
				this.processUpdateQueue();
			}, this.config.debounceDelay);
		}
	},

	// Process queued updates
	processUpdateQueue: function() {
		if (this.updateQueue.length === 0) {
			this.isUpdating = false;
			return;
		}

		// Get unique dates and package groups from queue
		var affectedDates = new Set();
		var affectedPackageGroups = new Set();

		this.updateQueue.forEach(item => {
			var booking = item.booking;
			affectedDates.add(booking.date);

			if (booking.related_package_ids) {
				booking.related_package_ids.forEach(pkgId => {
					affectedPackageGroups.add(pkgId);
				});
			}
		});

		// Clear queue
		this.updateQueue = [];

		// Refresh calendar data for affected dates
		this.refreshCalendarData(Array.from(affectedDates), Array.from(affectedPackageGroups));
	},

	// Refresh calendar data from server
	refreshCalendarData: function(affectedDates, affectedPackageIds) {
		// Check if calApp is available
		if (typeof calApp === 'undefined') {
			console.error('calApp is not defined! Cannot refresh calendar data.');
			this.isUpdating = false;
			return;
		}

		// Handle undefined date by using multiple fallback strategies
		var currentDate = calApp.date;

		if (!currentDate && affectedDates.length > 0) {
			// Use the first affected date if calApp.date is not available
			currentDate = affectedDates[0];
		}

		if (!currentDate) {
			// Try to get date from URL parameters
			var urlParams = new URLSearchParams(window.location.search);
			var yearParam = urlParams.get('year');
			var monthParam = urlParams.get('month');

			if (yearParam && monthParam) {
				currentDate = yearParam + '-' + monthParam.padStart(2, '0') + '-01';
			}
		}

		if (!currentDate) {
			// Final fallback to current date
			currentDate = new Date().toISOString().substr(0, 10);
		}

		var currentOption = calApp.option || 'month';
		var currentWeek = calApp.week;
		var currentPackageId = calApp.packageId;

		// Ensure we have a valid date format (YYYY-MM-DD)
		var dateStr = currentDate.toString();
		var year, month;

		if (dateStr.length >= 10) {
			year = dateStr.substr(0, 4);
			month = dateStr.substr(5, 2);
		} else {
			// Fallback parsing
			var dateObj = new Date(currentDate);
			year = dateObj.getFullYear().toString();
			month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
		}

		// Format data to match calendar-ahr endpoint expectations (same as paramsToGetCalendarDateSlots)
		var yearMonth = '' + year + month; // Combine year and month with string concatenation (e.g., "202512")

		var ajaxData = {
			yearMonth: yearMonth,
			option: currentOption,
			week: currentWeek,
			packageId: currentPackageId || [], // Use packageId (not package_id) and ensure it's an array
			tenantId: calApp.selectedCalendar || null
		};

		// Add tourOperatorId for admin users (check if we're in admin context)
		if (typeof calApp.tourOperator !== 'undefined') {
			ajaxData.tourOperatorId = calApp.tourOperator;
		}

		// Use the same endpoint that the calendar page uses
		var calendarUrl = typeof calendarDateSlotsUrl !== 'undefined' ? calendarDateSlotsUrl : '/admin/tour-operator/booking/calendar-ahr';

		$.ajax({
			url: calendarUrl,
			method: 'POST',
			data: ajaxData,
			success: (response) => {
				if (response.tourSlots) {
					this.updateCalendarDisplay(response.tourSlots, affectedPackageIds);
				}
				this.isUpdating = false;
			},
			error: (xhr, status, error) => {
				console.error('Calendar refresh failed:', status, error);
				this.isUpdating = false;
			}
		});
	},

	// Update calendar display with fresh server data
	updateCalendarDisplay: function(tourSlots, affectedPackageIds) {
		// 1. Update Calendar Grid Display (id="calendar-table")
		this.updateCalendarGrid(tourSlots, affectedPackageIds);
		
		// 2. Refresh Day Package Slot Times Modal (id="day-pkg-slot-times") 
		// 3. Refresh Time Slot Bookings Modal (id="time-slot-bookings")
		this.refreshOpenModals();
	},

	// Update Calendar Grid Display specifically
	updateCalendarGrid: function(tourSlots, affectedPackageIds) {
		tourSlots.forEach(slot => {
			// Only update affected packages for performance
			if (affectedPackageIds.length === 0 || affectedPackageIds.includes(slot.pkg_id)) {
				this.updatePackageDisplay(slot);
			}
		});
	},

	// Update individual package display
	updatePackageDisplay: function(slot) {
		if (!this.enabled) return;

		var $dayCell = $('#cal-date-' + slot.date);
		if (!$dayCell.length) return;

		var $pkg = $dayCell.find('.cal-package-' + slot.pkg_id);
		var $pkgViewMore = $dayCell.find('.cal-package-view-more' + slot.pkg_id);

		if (!$pkg.length) return;

		// Find and cache all elements to update
		var elements = this.findPackageElements($pkg, $pkgViewMore);

		// Get current and new values
		var values = this.calculateValues(slot, elements);

		// Only update if values have actually changed
		if (this.hasValuesChanged(values)) {
			this.performVisualUpdates($pkg, $pkgViewMore, elements, values, slot);

			// Force Vue reactivity update if calApp is available
			if (typeof calApp !== 'undefined' && calApp.$forceUpdate) {
				calApp.$forceUpdate();
			}
		}
	},

	// Helper function to find all package elements
	findPackageElements: function($pkg, $pkgViewMore) {
		// Try to find elements with both regular and view-more classes
		var elements = {
			filled: $pkg.find('.cal-seats-filled'),
			empty: $pkg.find('.cal-seats-empty'),
			overbooked: $pkg.find('.cal-seats-overbooked'),
			filledViewMore: $pkgViewMore.find('.cal-seats-filled-view-more'),
			emptyViewMore: $pkgViewMore.find('.cal-seats-empty-view-more'),
			overbookedViewMore: $pkgViewMore.find('.cal-seats-overbooked-view-more')
		};

		// If main elements not found but $pkg has view-more classes, use those
		if (!elements.filled.length && $pkg.find('.cal-seats-filled-view-more').length) {
			elements.filled = $pkg.find('.cal-seats-filled-view-more');
			elements.empty = $pkg.find('.cal-seats-empty-view-more');
			elements.overbooked = $pkg.find('.cal-seats-overbooked-view-more');
		}

		return elements;
	},

	// Helper function to calculate current and new values
	calculateValues: function(slot, elements) {
		var currentFilled = elements.filled.text();
		var currentEmpty = elements.empty.text();
		var currentOverbooked = elements.overbooked.text();

		var newFilled = slot.booked_seats || slot.tourists || 0;
		var newEmpty = slot.available_seats || 0;
		var newOverbooked = slot.over_booking || slot.overbooked_seats || 0;

		// Calculate overbooking if not provided
		if (!newOverbooked && slot.seats) {
			newOverbooked = Math.max(0, newFilled - slot.seats);
		}

		return {
			currentFilled: currentFilled,
			currentEmpty: currentEmpty,
			currentOverbooked: currentOverbooked,
			newFilled: newFilled,
			newEmpty: newEmpty,
			newOverbooked: newOverbooked
		};
	},

	// Helper function to check if values have changed
	hasValuesChanged: function(values) {
		return values.currentFilled != values.newFilled || 
			   values.currentEmpty != values.newEmpty || 
			   values.currentOverbooked != values.newOverbooked;
	},

	// Helper function to perform all visual updates
	performVisualUpdates: function($pkg, $pkgViewMore, elements, values, slot) {
		if (this.config.enableVisualEffects) {
			// Add visual flash effect to highlight the change
			this.flashElement($pkg, 'update');
			this.flashElement($pkgViewMore, 'update');

			// Update all values with animation
			this.animateValueChange(elements.filled, values.newFilled);
			this.animateValueChange(elements.filledViewMore, values.newFilled);
			this.animateValueChange(elements.empty, values.newEmpty);
			this.animateValueChange(elements.emptyViewMore, values.newEmpty);
			this.animateValueChange(elements.overbooked, values.newOverbooked);
			this.animateValueChange(elements.overbookedViewMore, values.newOverbooked);
		} else {
			// Direct updates without animation
			elements.filled.text(values.newFilled);
			elements.filledViewMore.text(values.newFilled);
			elements.empty.text(values.newEmpty);
			elements.emptyViewMore.text(values.newEmpty);
			elements.overbooked.text(values.newOverbooked);
			elements.overbookedViewMore.text(values.newOverbooked);
		}

		// Update progress bar
		this.updateProgressBar($pkg, $pkgViewMore, values.newFilled, values.newEmpty);

		// Update styling
		this.updatePackageStyling(elements.empty, elements.emptyViewMore, values.newEmpty);
		this.updatePackageStyling(elements.filled, elements.filledViewMore, values.newFilled, true);
		this.updatePackageStyling(elements.overbooked, elements.overbookedViewMore, values.newOverbooked, false, true);
	},

	// Helper function to update progress bar
	updateProgressBar: function($pkg, $pkgViewMore, newFilled, newEmpty) {
		var seatsTotal = newFilled + newEmpty;
		if (seatsTotal > 0) {
			var progressPercent = (newFilled * 100) / seatsTotal;
			var $progressBar = $pkg.find('.cal-seats-progress').children().first();
			var $progressBarViewMore = $pkgViewMore.find('.cal-seats-progress-view-more').children().first();

			var transitionStyle = this.config.enableVisualEffects ? 
				`width ${this.config.animationDuration}ms ease` : 'none';

			if ($progressBar.length) {
				$progressBar.css({
					'transition': transitionStyle,
					'width': progressPercent + '%'
				});
			}
			if ($progressBarViewMore.length) {
				$progressBarViewMore.css({
					'transition': transitionStyle,
					'width': progressPercent + '%'
				});
			}
		}
	},

	// Add visual flash effect to highlight changes
	flashElement: function($element, type) {
		if (!this.config.enableVisualEffects || !$element.length) return;

		var flashColor = type === 'update' ? '#10B981' : '#EF4444';
		var originalBg = $element.css('background-color') || 'transparent';
		var duration = this.config.flashDuration;

		$element.css({
			'transition': `background-color ${duration}ms ease, transform ${duration}ms ease`,
			'background-color': flashColor,
			'transform': 'scale(1.02)'
		});

		setTimeout(() => {
			$element.css({
				'background-color': originalBg,
				'transform': 'scale(1)'
			});

			setTimeout(() => {
				$element.css('transition', '');
			}, this.config.animationDuration);
		}, duration);
	},

	// Animate value changes to make them more visible
	animateValueChange: function($element, newValue) {
		if (!$element.length) return;

		var currentValue = parseInt($element.text()) || 0;

		if (currentValue !== newValue) {
			if (this.config.enableVisualEffects) {
				// Add temporary highlight with animation
				$element.css({
					'font-weight': 'bold',
					'color': '#059669',
					'transform': 'scale(1.1)',
					'transition': `all ${this.config.animationDuration}ms ease`
				});

				// Update the value
				$element.text(newValue);

				// Remove highlight after animation
				setTimeout(() => {
					$element.css({
						'font-weight': '',
						'color': '',
						'transform': '',
						'transition': ''
					});
				}, this.config.animationDuration + 200);
			} else {
				// Direct update without animation
				$element.text(newValue);
			}
		}
	},

	// Update package styling based on availability
	updatePackageStyling: function($element, $elementViewMore, value, isFilled = false, isOverbooked = false) {
		if (isOverbooked) {
			// Handle overbooking styling
			if (value > 0) {
				$element.removeClass('gray-text-color gray-bg-color gray-border-color')
					.addClass('alert-text-color alert-bg-color alert-border-color');
				$elementViewMore.removeClass('gray-text-color gray-bg-color gray-border-color')
					.addClass('alert-text-color alert-bg-color alert-border-color');
			} else {
				$element.removeClass('alert-text-color alert-bg-color alert-border-color')
					.addClass('gray-text-color gray-bg-color gray-border-color');
				$elementViewMore.removeClass('alert-text-color alert-bg-color alert-border-color')
					.addClass('gray-text-color gray-bg-color gray-border-color');
			}
		} else if (isFilled && value > 0) {
			$element.removeClass('gray-text-color gray-bg-color gray-border-color')
				.addClass('info-text-color info-bg-color info-border-color');
			$elementViewMore.removeClass('gray-text-color gray-bg-color gray-border-color')
				.addClass('info-text-color info-bg-color info-border-color');
		} else if (!isFilled) {
			if (value === 0) {
				$element.removeClass('alert-text-color alert-bg-color alert-border-color')
					.addClass('gray-text-color gray-bg-color gray-border-color');
				$elementViewMore.removeClass('alert-text-color alert-bg-color alert-border-color')
					.addClass('gray-text-color gray-bg-color gray-border-color');
			} else {
				$element.removeClass('gray-text-color gray-bg-color gray-border-color')
					.addClass('alert-text-color alert-bg-color alert-border-color');
				$elementViewMore.removeClass('gray-text-color gray-bg-color gray-border-color')
					.addClass('alert-text-color alert-bg-color alert-border-color');
			}
		}
	},

	// Handle simple package updates (non-related packages)
	handleSimplePackageUpdate: function(booking, operation) {
		if (!this.enabled) return;

		var dateStr = booking.tour_d_t.substr(0, 10);
		var $dayCell = $('#cal-date-' + dateStr);
		if (!$dayCell.length) return;

		var $pkg = $dayCell.find('.cal-package-' + booking.pkg_id);
		var $pkgViewMore = $dayCell.find('.cal-package-view-more' + booking.pkg_id);

		// Check if at least one element exists (main calendar OR view-more)
		if (!$pkg.length && !$pkgViewMore.length) return;

		// If only view-more exists, use it as the main package element
		if (!$pkg.length && $pkgViewMore.length) {
			$pkg = $pkgViewMore;
		}

		// Find elements and calculate values
		var elements = this.findPackageElements($pkg, $pkgViewMore);
		var touristChange = parseInt(booking.tourists) || 0;

		var currentFilled = parseInt(elements.filled.text()) || 0;
		var currentEmpty = parseInt(elements.empty.text()) || 0;
		var currentOverbooked = parseInt(elements.overbooked.text()) || 0;

		// Apply the change
		var newFilled = currentFilled + touristChange;
		var newEmpty = currentEmpty - touristChange;
		var newOverbooked = booking.seats ? Math.max(0, newFilled - booking.seats) : currentOverbooked;

		var values = {
			currentFilled: currentFilled,
			currentEmpty: currentEmpty,
			currentOverbooked: currentOverbooked,
			newFilled: newFilled,
			newEmpty: newEmpty,
			newOverbooked: newOverbooked
		};

		// Perform updates
		this.performVisualUpdates($pkg, $pkgViewMore, elements, values, booking);

		// Update popups for simple packages
		if (typeof updateDataForPopups === 'function') {
			updateDataForPopups(booking);
		}

		// Refresh any open modals
		this.refreshOpenModals();

		// Also trigger server refresh for accuracy
		setTimeout(() => {
			if (typeof calApp !== 'undefined' && calApp.handleSelectChange) {
				calApp.handleSelectChange();
			}
		}, 1000);
	},

	// Refresh any open modals with updated data
	refreshOpenModals: function() {
		// Check what modals are currently open using Bootstrap modal state
		var $dayPkgModal = $('#day-pkg-slot-times');
		var $timeSlotModal = $('#time-slot-bookings');

		var dayPkgModalOpen = $dayPkgModal.hasClass('show') || $dayPkgModal.is(':visible');
		var timeSlotModalOpen = $timeSlotModal.hasClass('show') || $timeSlotModal.is(':visible');

		// Refresh Day Package Slot Times Modal if open
		if (dayPkgModalOpen) {
			this.refreshDayPackageSlotTimesModal();
		}

		// Refresh Time Slot Bookings Modal if open
		if (timeSlotModalOpen) {
			this.refreshTimeSlotBookingsModal();
		}

		// Force Vue reactivity update for any open modals
		if ((dayPkgModalOpen || timeSlotModalOpen) && typeof calApp !== 'undefined' && calApp.$forceUpdate) {
			calApp.$forceUpdate();
		}
	},

	// Refresh Day Package Slot Times Modal
	refreshDayPackageSlotTimesModal: function() {
		try {
			if (typeof calApp === 'undefined' || !calApp.dayPkgSlotInfo) {
				return;
			}

			var currentDate = calApp.dayPkgSlotInfo.date;
			var currentPackageId = calApp.dayPkgSlotInfo.pkg_id;

			if (!currentDate || !currentPackageId) {
				return;
			}

			// Use the same endpoint that dayPkgSlotTimesModal uses
			var dateParam = (currentDate.format ? currentDate.format('YYYY-MM-DD') : currentDate).split('-').join('');

			$.post(dayTimeSlotsUrl, {
				date: dateParam,
				tourPackageId: currentPackageId,
				getRateGroups: 0,
				allBookableStatus: 0
			})
			.done((response) => {
				if (response.slotTimes) {
					// Process the slot times with the same logic as dayPkgSlotTimesModal
					var sDT = serverDT.format('YYYY-MM-DD HH:mm:ss');
					var dateStr = currentDate.format ? currentDate.format('YYYY-MM-DD') : currentDate;

					for (var a = 0; a < response.slotTimes.length; a++) {
						if (response.slotTimes[a].bookable_status == 'Only by Phone') {
							response.slotTimes[a].bookable_status_2 = 'Only by Phone';
							response.slotTimes[a].bookable_status_2_reason = "Slot's status is set 'Only by Phone'";
						} else if (dateStr + ' ' + response.slotTimes[a].time_from < sDT) {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = 'Tour date-time reached';
						}
						else if (response.slotTimes[a].available_tourists !== undefined && response.slotTimes[a].available_tourists <= 0) {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = 'Slot full / overbooked';
						}
						else if (response.slotTimes[a].available_tourists === undefined && response.slotTimes[a].tourists >= response.slotTimes[a].seats) {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = 'Slot full / overbooked';
						}
						else if (response.slotTimes[a].bookable_status == 'Closed') {
							response.slotTimes[a].bookable_status_2 = 'Closed';
							response.slotTimes[a].bookable_status_2_reason = "Slot's status is set 'Closed'";
						} else {
							response.slotTimes[a].bookable_status_2 = response.slotTimes[a].bookable_status;
							response.slotTimes[a].bookable_status_2_reason = 'Bookings open';
						}
					}

					// Ensure each slot has the available_tourists field properly set
					for (var i = 0; i < response.slotTimes.length; i++) {
						var slot = response.slotTimes[i];
						var slotBooked = parseInt(slot.tourists || 0);
						var slotSeats = parseInt(slot.seats || 0);
						
						// Ensure available_tourists is properly calculated and set
						if (slot.available_tourists === undefined || slot.available_tourists === null) {
							slot.available_tourists = Math.max(0, slotSeats - slotBooked);
						} else {
							slot.available_tourists = parseInt(slot.available_tourists);
						}
						
						// Also ensure booked_seats is set for consistency
						if (slot.booked_seats === undefined || slot.booked_seats === null) {
							slot.booked_seats = slotBooked;
						}
					}

					// Calculate header totals by summing individual slot values
					var totals = response.slotTimes.reduce(function(acc, slotTime) {
						var slotBooked = parseInt(slotTime.tourists || 0);
						var slotSeats = parseInt(slotTime.seats || 0);
						var slotAvailable = parseInt(slotTime.available_tourists);
						var slotOverbooked = Math.max(0, slotBooked - slotSeats);

						return {
							booked: acc.booked + slotBooked,
							available: acc.available + slotAvailable,
							overbooked: acc.overbooked + slotOverbooked
						};
					}, { booked: 0, available: 0, overbooked: 0 });

					// Update the modal data with fresh server data
					calApp.dayPkgSlotInfo.slotTimes = response.slotTimes;
					calApp.dayPkgSlotInfo.bookedSlots = totals.booked;
					calApp.dayPkgSlotInfo.availableSlots = totals.available;
					calApp.dayPkgSlotInfo.overbookedSlots = totals.overbooked;

					// Add visual flash to indicate update
					$('#day-pkg-slot-times .modal-content').css({
						'border': '2px solid #10B981',
						'transition': 'border 0.3s ease'
					});

					setTimeout(function() {
						$('#day-pkg-slot-times .modal-content').css('border', '');
					}, 1000);
				}

				// Force Vue to update with multiple methods to ensure reactivity
				if (calApp) {
					// Method 1: Force update
					if (calApp.$forceUpdate) {
						calApp.$forceUpdate();
					}
					
					// Method 2: Trigger reactivity on the specific data
					if (calApp.$set && calApp.dayPkgSlotInfo) {
						calApp.$set(calApp.dayPkgSlotInfo, 'slotTimes', calApp.dayPkgSlotInfo.slotTimes);
						calApp.$set(calApp.dayPkgSlotInfo, 'bookedSlots', calApp.dayPkgSlotInfo.bookedSlots);
						calApp.$set(calApp.dayPkgSlotInfo, 'availableSlots', calApp.dayPkgSlotInfo.availableSlots);
						calApp.$set(calApp.dayPkgSlotInfo, 'overbookedSlots', calApp.dayPkgSlotInfo.overbookedSlots);
					}
				}
			})
			.fail((xhr, status, error) => {
				console.error('❌ Failed to refresh Day Package Slot Times Modal');
				console.error('Status:', status, 'Error:', error);
				console.error('Response:', xhr.responseText);

				// Fallback: Force Vue reactivity update
				if (calApp && calApp.$forceUpdate) {
					calApp.$forceUpdate();
				}
			});

		} catch (error) {
			console.error('Error refreshing Day Package Slot Times Modal:', error);
		}
	},

	// Refresh Time Slot Bookings Modal
	refreshTimeSlotBookingsModal: function() {
		try {
			if (!calApp || !calApp.dayPkgSlotInfo || !calApp.dayPkgSlotInfo.timeSlotBookingsInfo) {
				return;
			}

			var currentTimeSlot = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot;

			if (!currentTimeSlot || !currentTimeSlot.id) {
				return;
			}

			// Use the existing timeSlotBookingsInfoUrl to refresh the modal
			if (typeof timeSlotBookingsInfoUrl !== 'undefined') {
				var url = timeSlotBookingsInfoUrl + currentTimeSlot.id;

				$.getJSON(url)
					.done((timeSlotBookingsInfo) => {
						// Update the time slot with fresh data from server
						if (timeSlotBookingsInfo.totalTourists !== undefined) {
							currentTimeSlot.tourists = timeSlotBookingsInfo.totalTourists;
							currentTimeSlot.booked_seats = timeSlotBookingsInfo.totalTourists;
						}

						// Update available_tourists field properly
						var slotBooked = parseInt(currentTimeSlot.tourists || 0);
						var slotSeats = parseInt(currentTimeSlot.seats || 0);
						
						if (timeSlotBookingsInfo.availableSeats !== undefined) {
							currentTimeSlot.available_tourists = parseInt(timeSlotBookingsInfo.availableSeats);
						} else {
							currentTimeSlot.available_tourists = Math.max(0, slotSeats - slotBooked);
						}
						
						// Ensure booked_seats is also updated
						currentTimeSlot.booked_seats = slotBooked;

						// Update the corresponding slot in dayPkgSlotInfo.slotTimes as well
						if (calApp.dayPkgSlotInfo && calApp.dayPkgSlotInfo.slotTimes) {
							for (var i = 0; i < calApp.dayPkgSlotInfo.slotTimes.length; i++) {
								if (calApp.dayPkgSlotInfo.slotTimes[i].id === currentTimeSlot.id) {
									calApp.dayPkgSlotInfo.slotTimes[i].tourists = currentTimeSlot.tourists;
									calApp.dayPkgSlotInfo.slotTimes[i].available_tourists = currentTimeSlot.available_tourists;
									calApp.dayPkgSlotInfo.slotTimes[i].booked_seats = currentTimeSlot.booked_seats;
									break;
								}
							}
							
							// Recalculate header totals
							var totals = calApp.dayPkgSlotInfo.slotTimes.reduce(function(acc, slotTime) {
								var slotBooked = parseInt(slotTime.tourists || 0);
								var slotSeats = parseInt(slotTime.seats || 0);
								var slotAvailable = parseInt(slotTime.available_tourists || 0);
								var slotOverbooked = Math.max(0, slotBooked - slotSeats);

								return {
									booked: acc.booked + slotBooked,
									available: acc.available + slotAvailable,
									overbooked: acc.overbooked + slotOverbooked
								};
							}, { booked: 0, available: 0, overbooked: 0 });
							
							calApp.dayPkgSlotInfo.bookedSlots = totals.booked;
							calApp.dayPkgSlotInfo.availableSlots = totals.available;
							calApp.dayPkgSlotInfo.overbookedSlots = totals.overbooked;
						}

						// Use the existing function to update the modal with fresh booking data
						if (typeof setTimeSlotBookingsInfo === 'function') {
							setTimeSlotBookingsInfo(timeSlotBookingsInfo, currentTimeSlot);
						} else {
							// Manually update the modal data with fresh booking information
							var cancelledCount = 0;
							var cancelledtouristsCount = 0;

							if (timeSlotBookingsInfo.touristsInfoCancelled) {
								for (var a = 0; a < timeSlotBookingsInfo.touristsInfoCancelled.length; a++) {
									cancelledtouristsCount += Number(timeSlotBookingsInfo.touristsInfoCancelled[a].tourists);
								}
							}

							if (timeSlotBookingsInfo.bookingsCancelled) {
								for (var a = 0; a < timeSlotBookingsInfo.bookingsCancelled.length; a++) {
									cancelledCount++;
								}
							}

							// Update the complete booking information including payment statuses
							calApp.dayPkgSlotInfo.timeSlotBookingsInfo = {
								timeSlot: currentTimeSlot,
								bookings: timeSlotBookingsInfo.bookings || [],
								bookingsPurchaseBy: timeSlotBookingsInfo.bookings || [],
								bookingsCancelled: timeSlotBookingsInfo.bookingsCancelled || [],
								touristsInfo: timeSlotBookingsInfo.touristsInfo || [],
								showOnlyCancelledBookings: false,
								showOnlybookingsPurchaseBy: false,
								cancelledCount: cancelledCount,
								touristsInfoCancelled: timeSlotBookingsInfo.touristsInfoCancelled || [],
								cancelledtouristsCount: cancelledtouristsCount
							};
						}

						// Add visual flash to indicate update (payment completion)
						$('#time-slot-bookings .modal-content').css({
							'border': '2px solid #10B981',
							'transition': 'border 0.3s ease'
						});

						setTimeout(function() {
							$('#time-slot-bookings .modal-content').css('border', '');
						}, 1000);

						// Force Vue to update with multiple methods to ensure reactivity
						if (calApp) {
							// Method 1: Force update
							if (calApp.$forceUpdate) {
								calApp.$forceUpdate();
							}
							
							// Method 2: Trigger reactivity on the specific data to ensure booking list updates
							if (calApp.$set && calApp.dayPkgSlotInfo) {
								if (calApp.dayPkgSlotInfo.timeSlotBookingsInfo) {
									calApp.$set(calApp.dayPkgSlotInfo.timeSlotBookingsInfo, 'timeSlot', currentTimeSlot);
									calApp.$set(calApp.dayPkgSlotInfo.timeSlotBookingsInfo, 'bookings', calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookings);
									calApp.$set(calApp.dayPkgSlotInfo.timeSlotBookingsInfo, 'bookingsPurchaseBy', calApp.dayPkgSlotInfo.timeSlotBookingsInfo.bookingsPurchaseBy);
								}
								if (calApp.dayPkgSlotInfo.slotTimes) {
									calApp.$set(calApp.dayPkgSlotInfo, 'slotTimes', calApp.dayPkgSlotInfo.slotTimes);
									calApp.$set(calApp.dayPkgSlotInfo, 'bookedSlots', calApp.dayPkgSlotInfo.bookedSlots);
									calApp.$set(calApp.dayPkgSlotInfo, 'availableSlots', calApp.dayPkgSlotInfo.availableSlots);
									calApp.$set(calApp.dayPkgSlotInfo, 'overbookedSlots', calApp.dayPkgSlotInfo.overbookedSlots);
								}
							}
						}
					})
					.fail((xhr, status, error) => {
						console.error('❌ Failed to refresh Time Slot Bookings Modal');
						console.error('Status:', status, 'Error:', error);
						console.error('Response:', xhr.responseText);

						// Fallback: Force Vue reactivity update
						if (calApp && calApp.$forceUpdate) {
							calApp.$forceUpdate();
						}
					});
			}
		} catch (error) {
			console.error('Error refreshing Time Slot Bookings Modal:', error);
		}
	}
};

// ============================================================================
// REAL-TIME UPDATES CONFIGURATION
// ============================================================================
// To disable real-time updates completely, set: RealTimeUpdates.enabled = false;
// To disable visual effects only, set: RealTimeUpdates.config.enableVisualEffects = false;
// To disable console logging, set: RealTimeUpdates.config.enableConsoleLogging = false;
// ============================================================================

// Initialize the real-time update system
$(document).ready(function() {
	RealTimeUpdates.init();

	// Make RealTimeUpdates globally available
	window.RealTimeUpdates = RealTimeUpdates;

	// Production initialization message
	if (RealTimeUpdates.enabled && RealTimeUpdates.config.enableConsoleLogging) {
		console.log('✅ Real-time update system initialized');
		console.log('💡 To disable: RealTimeUpdates.enabled = false;');
	}
});

// Main function that uses the real-time update system
function updateSeatsInCal(booking) {
	if (RealTimeUpdates.enabled) {
		RealTimeUpdates.handleBookingUpdate(booking, 'update');
	}
}

function updateDataForPopups(booking) {
	if (calApp.dayPkgSlotInfo.date == '')
		// day's time slots popup hasn't been opened
		return;

	if (calApp.dayPkgSlotInfo.date.format('YYYY-MM-DD') != booking.tour_d_t.substr(0, 10))
		// popup is/was opened for different date than the customer/affiliate's tour's date
		return;

	if (!calApp.dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot || Object.keys(calApp.dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot).length === 0)
		// time slot's bookings popup hasn't been opened
		return;

	var timeSlot = calApp.dayPkgSlotInfo.timeSlotBookingsInfo.timeSlot;

	if (timeSlot.id != booking.tour_slot_id)
		// popup is/was opened for different time than the customer/affiliate's tour's time
		return;

	var isModalShown = (
		(calApp.modalTimeSlotBookings && calApp.modalTimeSlotBookings._isShown) ||
		(calApp.modalNewBooking && calApp.modalNewBooking._isShown) ||
		(calApp.modalBookingInfo && calApp.modalBookingInfo._isShown)
	);

	if (isModalShown) {
		// When a modal is open, fetch authoritative data from the server
		// to avoid double-counting from local increments racing with server state
		$.getJSON(timeSlotBookingsInfoUrl + timeSlot.id)
			.done(function (timeSlotBookingsInfo) {
				setTimeSlotBookingsInfo(timeSlotBookingsInfo, timeSlot);

				// Also update slotTimes array from server-provided totals
				for (var a = 0; a < calApp.dayPkgSlotInfo.slotTimes.length; a++) {
					if (calApp.dayPkgSlotInfo.slotTimes[a].id == timeSlot.id) {
						calApp.dayPkgSlotInfo.slotTimes[a].tourists = parseInt(timeSlot.booked_seats);
						calApp.dayPkgSlotInfo.slotTimes[a].booked_seats = parseInt(timeSlot.booked_seats);
						var slotSeats = parseInt(calApp.dayPkgSlotInfo.slotTimes[a].seats || 0);
						calApp.dayPkgSlotInfo.slotTimes[a].available_tourists = Math.max(0, slotSeats - parseInt(timeSlot.booked_seats));
						break;
					}
				}

				// Recalculate header totals from updated slotTimes
				var totals = calApp.dayPkgSlotInfo.slotTimes.reduce(function(acc, slotTime) {
					var slotBooked = parseInt(slotTime.tourists || 0);
					var slotSeats = parseInt(slotTime.seats || 0);
					var slotAvailable = parseInt(slotTime.available_tourists || 0);
					var slotOverbooked = Math.max(0, slotBooked - slotSeats);
					return {
						booked: acc.booked + slotBooked,
						available: acc.available + slotAvailable,
						overbooked: acc.overbooked + slotOverbooked
					};
				}, { booked: 0, available: 0, overbooked: 0 });

				calApp.dayPkgSlotInfo.bookedSlots = totals.booked;
				calApp.dayPkgSlotInfo.availableSlots = totals.available;
				calApp.dayPkgSlotInfo.overbookedSlots = totals.overbooked;

				if (calApp && calApp.$forceUpdate) {
					calApp.$forceUpdate();
				}
			});
	} else if (booking.tourists > 0) {
		// No modal open -- apply local optimistic update
		timeSlot.tourists = parseInt(timeSlot.booked_seats);
		timeSlot.booked_seats = timeSlot.tourists;
		var totalSeats = parseInt(timeSlot.seats || 0);
		var bookedSeats = parseInt(timeSlot.tourists || 0);
		timeSlot.available_tourists = Math.max(0, totalSeats - bookedSeats);

		for (var a = 0; a < calApp.dayPkgSlotInfo.slotTimes.length; a++) {
			if (calApp.dayPkgSlotInfo.slotTimes[a].id == booking.tour_slot_id) {
				calApp.dayPkgSlotInfo.slotTimes[a].tourists += parseInt(booking.tourists);
				calApp.dayPkgSlotInfo.slotTimes[a].booked_seats += parseInt(booking.tourists);

				var slotSeats = parseInt(calApp.dayPkgSlotInfo.slotTimes[a].seats || 0);
				var slotBooked = parseInt(calApp.dayPkgSlotInfo.slotTimes[a].tourists || 0);
				calApp.dayPkgSlotInfo.slotTimes[a].available_tourists = Math.max(0, slotSeats - slotBooked);

				break;
			}
		}

		var totals = calApp.dayPkgSlotInfo.slotTimes.reduce(function(acc, slotTime) {
			var slotBooked = parseInt(slotTime.tourists || 0);
			var slotSeats = parseInt(slotTime.seats || 0);
			var slotAvailable = parseInt(slotTime.available_tourists || 0);
			var slotOverbooked = Math.max(0, slotBooked - slotSeats);
			return {
				booked: acc.booked + slotBooked,
				available: acc.available + slotAvailable,
				overbooked: acc.overbooked + slotOverbooked
			};
		}, { booked: 0, available: 0, overbooked: 0 });

		calApp.dayPkgSlotInfo.bookedSlots = totals.booked;
		calApp.dayPkgSlotInfo.availableSlots = totals.available;
		calApp.dayPkgSlotInfo.overbookedSlots = totals.overbooked;

		if (calApp && calApp.$forceUpdate) {
			calApp.$forceUpdate();
		}
	}
}

function setTimeSlotBookingsInfo(timeSlotBookingsInfo, timeSlot) {
	var cancelledCount = 0;
	var cancelledtouristsCount = 0;
	var bookingsPurchaseBy = [];
	for (var a = 0; a < timeSlotBookingsInfo.touristsInfoCancelled.length; a++) {
		cancelledtouristsCount += Number(timeSlotBookingsInfo.touristsInfoCancelled[a].tourists);
	}

	for (var a = 0; a < timeSlotBookingsInfo.bookingsCancelled.length; a++)
		cancelledCount++;

	/* START: package relation total tourists combined calculation */
	if (timeSlotBookingsInfo?.totalTourists > 0) {
		timeSlot.tourists = timeSlotBookingsInfo.totalTourists;
	}
	/* END: package relation total tourists combined calculation */

	// Always update available_tourists from API response or calculate it
	if (timeSlotBookingsInfo.availableSeats !== undefined) {
		timeSlot.available_tourists = parseInt(timeSlotBookingsInfo.availableSeats);
	} else {
		// Calculate available seats based on current data
		var totalSeats = parseInt(timeSlot.seats || 0);
		var bookedSeats = parseInt(timeSlot.tourists || 0);
		timeSlot.available_tourists = Math.max(0, totalSeats - bookedSeats);
	}

	// Ensure booked_seats is also properly set
	if (timeSlot.booked_seats === undefined || timeSlot.booked_seats === null) {
		timeSlot.booked_seats = timeSlot.tourists || 0;
	}

	calApp.dayPkgSlotInfo.timeSlotBookingsInfo = {
		timeSlot: timeSlot,
		bookings: timeSlotBookingsInfo.bookings,
		bookingsPurchaseBy: timeSlotBookingsInfo.bookings,
		bookingsCancelled: timeSlotBookingsInfo.bookingsCancelled,
		touristsInfo: timeSlotBookingsInfo.touristsInfo,
		showOnlyCancelledBookings: false,
		showOnlybookingsPurchaseBy: false,
		cancelledCount: cancelledCount,
		touristsInfoCancelled: timeSlotBookingsInfo.touristsInfoCancelled,
		cancelledtouristsCount: cancelledtouristsCount
	};
}

function parseRecHistory(rh) {
	const recHistory = [];

	for (let a = 0; a < rh.length; a++) {
		let objVals = {}, arVals = [], joiner = '', entity = '';

		switch (rh[a].table_name) {
			case 'tour_packages':
				entity = 'tour package';
				break;
			case 'tour_package_rates':
				entity = 'age/rate group';
				break;
			case 'tour_slot_dates':
				rh[a].entity_info = formatDate(rh[a].entity_info);
				entity = 'tour date';
				break;
			case 'tour_slot_times':
				rh[a].entity_info = calApp.$options.filters.formatBookingDT(rh[a].entity_info);
				entity = 'tour time slot';
				break;
		}

		const operationByUser = rh[a].staff_name
			? `${rh[a].staff_name} (Tour Operator)`
			: `${rh[a].admin_name} (Admin)`;

		if (['i', 'u'].includes(rh[a].operation_type)) {
			objVals = JSON.parse(rh[a].new_values);
			joiner = rh[a].operation_type === 'i' ? ': ' : ' to';

			parseHistoryValues(objVals, arVals, joiner);

			const infoText = `${operationByUser} ${rh[a].operation_type === 'i' ? 'added' : 'updated'} ${entity} ` +
				(rh[a].entity_info ? `<b>${encLT(rh[a].entity_info)}</b>: ` : ': ') +
				arVals.join(', ');

			recHistory.push({
				dateTime: rh[a].operation_at,
				info: infoText
			});
		} else if (rh[a].operation_type === 'd') {
			recHistory.push({
				dateTime: rh[a].operation_at,
				info: `${operationByUser} deleted ${entity} <b>${encLT(rh[a].entity_info)}</b>`
			});
		} else if (rh[a].operation_type === 'e') {
			recHistory.push({
				dateTime: rh[a].operation_at,
				info: `${operationByUser} sent closure email for ${entity} <b>${encLT(rh[a].entity_info)}</b>`
			});
		}
	}

	return recHistory;
}

function parseHistoryValues(objVals, arVals, joiner) {
	for (key in objVals) {
		if (key == 'tour_package_id')
			arVals.push('<b>tour package</b>');
		else if (key == 'short_description' || key == 'full_description')
			arVals.push('<b>' + key.replace(/_/g, ' ') + '</b>')
		else {
			if (key == 'date')
				objVals[key] = formatDate(objVals[key]);
			if (key == 'from_time' || key == 'to_time')
				objVals[key] = formatTime(objVals[key]);

			arVals.push('<b>' + key.replace(/_/g, ' ') + '</b>' + joiner + ' <b>' + encLT(objVals[key]) + '</b>');
		}
	}
}

function lowerAntelopeCanyonClosure(arg) {
	const tourOpName = $(arg).attr('data-tour-operator-name')?.trim()
		? `${$(arg).attr('data-tour-operator-name')} Closure`
		: 'Lower Antelope Canyon Closure';

	swal({
		title: tourOpName,
		text: 'Are you sure you want to send bulk emails to tourists about a closure for this tour?',
		icon: 'warning',
		buttons: {
			no: {
				text: 'No',
				value: false,
				className: 'btn-danger',
			},
			yes: {
				text: 'Yes',
				className: 'btn-success',
			}
		}
	}).then((result) => {
		if (!result) return;

		const date = moment($(arg).attr('data-date')).format('YYYY-MM-DD');
		const payload = {
			date,
			slot_id: $(arg).attr('data-slot-id'),
			tour_operator_id: $(arg).attr('data-tour-operator-id'),
			tour_package_id: $(arg).attr('data-tour-package-id')
		};

		showLoading();

		$.post(lowerAntelopeCanyonClosureUrl, payload)
			.done(() => swal('All clients will soon get an email announcing the closure.', '', 'success'))
			.fail(showSubmissionError)
			.always(hideLoading);
	});
}

$("#tour_operator").change(function () {
	location.reload();
});

function dateFromWeekNumber(year, week) {
	var d = new Date(year, 0, 1);
	var dayNum = d.getDay();
	var diff = --week * 7;

	// If 1 Jan is Friday to Sunday, go to next week
	if (!dayNum || dayNum > 4) {
		diff += 7;
	}

	// Add required number of days
	d.setDate(d.getDate() - d.getDay() + ++diff);
	return d;
}

function getWeeksStartAndEndInMonth(year, currentMonth, requestedMonth, today, pnflag, week, event) {
	// Adjust month when navigating weeks
	if (week === 1) {
		if (event === "previous") {
			requestedMonth -= 1;
		} else if (event === "next") {
			requestedMonth += 1;
		}
	}

	const weeks = [];
	const firstDate = new Date(year, requestedMonth, 1);
	const lastDate = new Date(year, requestedMonth + 1, 0);
	const numDays = lastDate.getDate();

	let weekCounter = 1;
	let start = 1;
	let weekNo = 1;
	let end = 7 - firstDate.getDay();

	// Generate week ranges for the month
	while (start <= numDays) {
		weeks.push({ start, end, week: weekCounter, month: requestedMonth });

		start = end + 1;
		end += 7;
		weekCounter++;

		if (start === 1 && end === 8) {
			end = 1;
		}
		if (end > numDays) {
			end = numDays;
		}
	}

	// Determine current week or return weeks list
	if (pnflag === 0) {
		for (const element of weeks) {
			if (currentMonth === requestedMonth) {
				const nowDate = new Date(today).getDate();
				if (nowDate >= element.start && nowDate <= element.end) {
					weekNo = element.week;
					break;
				}
			} else {
				weekNo = element.week;
				break;
			}
		}
		return weekNo;
	} else {
		return weeks;
	}

}

function addTimeSlot() {
	const timeSlots = calApp.dayPkgSlotInfo.slotTimes;

	if (!timeSlots.length) return;

	const lastSlot = timeSlots[timeSlots.length - 1];
	let { time_from: fromTime, time_to: toTime, seats } = lastSlot;

	let lastDataWarp = $(".time-slots .data-warp").last(); // Get the last .data-warp directly

	if (lastDataWarp.length) {
		fromTime = lastDataWarp.find('.time-from').text();
		toTime = lastDataWarp.find('.time-to').text();
	}

	if (!fromTime || !toTime) return;

	// Convert HH:MM to minutes
	const convertToMinutes = (time) => {
		const [hours, minutes] = time.split(":").map(Number);
		return hours * 60 + minutes;
	};

	// Convert minutes back to HH:MM format
	const formatTime = (minutes) => {
		const hours = Math.floor(minutes / 60) % 24; // Keep within 24-hour format
		const mins = minutes % 60;
		return `${hours.toString().padStart(2, "0")}:${mins.toString().padStart(2, "0")}`;
	};

	const startMinutes = convertToMinutes(fromTime);
	const endMinutes = convertToMinutes(toTime);
	const duration = endMinutes - startMinutes;

	if (duration <= 0) {
		swal('Invalid Time Range', 'End time must be after start time.', 'warning');
		return;
	}

	const nextStartTime = formatTime(endMinutes);
	const nextEndTime = formatTime(endMinutes + duration);

	const timeSlotSection = `
        <div class="data-warp flex flex-wrap -mx-3 mt-2">
            <div class="w-full sm:w-2/4 lg:w-1/6 max-w-full px-3">
                <input class="form-control from-time w-full outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-10 text-sm text-[#020817]" type="time" name="from_time" id="from_time" value="${nextStartTime}" step="60" title="Start Time" />
            </div>
            <div class="w-full sm:w-2/4 lg:w-1/6 max-w-full px-3">
                <input class="form-control to-time w-full outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-10 text-sm text-[#020817]" type="time" name="to_time" id="to_time" value="${nextEndTime}" step="60" title="End Time" />
            </div>
            <div class="status-warp w-full sm:w-2/4 lg:w-1/6 max-w-full px-3">
                <div class="dropdown">
                    <button type="button" class="dropdown-toggle bookable bg-[#F0FDF4] border-[#BBF7D0] text-[#166534] text-sm border px-3 py-2.5 h-10 rounded-lg flex items-center whitespace-nowrap min-w-36 max-[1023px]:w-full" data-bs-toggle="dropdown" aria-expanded="true">
                        <span class="status-icon"><svg class=\"inline-block align-text-top\" width=\"14\" height=\"14\" viewBox=\"0 0 12 12\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M8.65546 4.09616C8.83856 4.27927 8.83856 4.57608 8.65546 4.75909L5.5108 7.90384C5.3277 8.08685 5.03098 8.08685 4.84787 7.90384L3.35089 6.40677C3.16779 6.22375 3.16779 5.92694 3.35089 5.74393C3.53391 5.56082 3.83072 5.56082 4.01373 5.74393L5.17929 6.90948L7.99252 4.09616C8.17563 3.91315 8.47244 3.91315 8.65546 4.09616ZM12.0032 6C12.0032 9.3165 9.31921 12 6.00317 12C2.68668 12 0.00317383 9.31604 0.00317383 6C0.00317383 2.6835 2.68713 0 6.00317 0C9.31967 0 12.0032 2.68396 12.0032 6ZM11.0657 6C11.0657 3.20169 8.80112 0.9375 6.00317 0.9375C3.20486 0.9375 0.940674 3.20206 0.940674 6C0.940674 8.79831 3.20523 11.0625 6.00317 11.0625C8.80148 11.0625 11.0657 8.79794 11.0657 6Z\" fill=\"currentColor\"/></svg></span> <span class="bookable-status ml-1">Open</span>
						<svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="currentColor" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    </button>
                    <ul class="dropdown-menu ul-slot-status hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded-lg border border-[#E5E7EB] text-sm text-left py-1">
                        <li class="li-slot-status flex items-center px-4 py-2.5 text-xs text-[#374151] hover:bg-[#F9FAFB] cursor-pointer font-medium" onclick="return timeSlotStatus(this, 'Open')"><svg class="mr-2 flex-shrink-0" width="10" height="10" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5" fill="#22C55E"/></svg>Open</li>
                        <li class="li-slot-status flex items-center px-4 py-2.5 text-xs text-[#374151] hover:bg-[#F9FAFB] cursor-pointer font-medium" onclick="return timeSlotStatus(this, 'Closed')"><svg class="mr-2 flex-shrink-0" width="10" height="10" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5" fill="#EF4444"/></svg>Closed</li>
                        <li class="li-slot-status flex items-center px-4 py-2.5 text-xs text-[#374151] hover:bg-[#F9FAFB] cursor-pointer font-medium" onclick="return timeSlotStatus(this, 'Only by Phone')"><svg class="mr-2 flex-shrink-0" width="10" height="10" viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"><circle cx="5" cy="5" r="5" fill="#3B82F6"/></svg>Only by Phone</li>
                    </ul>
                    <input type="hidden" name="time_slot_status" id="time_slot_status" value="Open">
                </div>
            </div>
            <div class="w-full sm:w-2/4 lg:w-1/6 max-w-full px-3">
                <input type="number" class="form-control time-slot-seats w-full outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-10 text-sm text-[#020817]" name="seats" id="time_slot_seats" value="${seats}" min="0" max="999999" placeholder="Seats" title="Seats">
            </div>
            <div class="w-full sm:w-2/4 lg:w-1/6 max-w-full px-3 text-sm text-[#020817] flex items-center">
                <input class="hide-slot w-4 h-4 mr-2" type="checkbox" name="hide_time_slot" id="hide_time_slot" value=""> Is Hidden?
            </div>
            <div class="w-full sm:w-2/4 lg:w-1/6 max-w-full px-3">
                <button type="button" class="btn btn-sm btn-primary btn-time-slot h-10 mr-2 text-xs text-white font-semibold shadow-sm rounded-lg px-4 py-2 inline-block bg-[#2563EB] border border-[#2563EB] hover:border-[#2563EB] hover:bg-white hover:text-[#2563EB] transition-all duration-300 ease-in-out group" title="Save" onclick="return saveTimeSlot()"><svg width="13" height="9" viewBox="0 0 13 9" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block"><path d="M12.2167 1L4.88338 8.33333L1.55005 5" stroke="white" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" class="group-hover:stroke-[#2563EB]"></path></svg></button>
                <button type="button" class="btn btn-sm btn-danger btn-time-slot h-10 text-xs font-semibold shadow-sm rounded-lg px-4 py-2 inline-block text-[#DC2626] bg-[#FAFAFA] border border-[#FECACA] hover:border-[#FECACA] hover:bg-[#FECACA] hover:text-[#DC2626] transition-all duration-300 ease-in-out" title="Cancel" onclick="return removeTimeSlot(this)"><svg width="17" height="16" viewBox="0 0 17 16" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block"><path d="M12.2461 4L4.24609 12" stroke="#DC2626" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4.24609 4L12.2461 12" stroke="#DC2626" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
            </div>
        </div>`;

	$(".new-time-slot-form").append(timeSlotSection);

	// Scroll within the modal body to show the new slot
	const $newSlot = $(".new-time-slot-form .data-warp").last();
	const $modalBody = $(".modal-body.time-slot-section");

	if ($newSlot.length && $modalBody.length) {
		$modalBody.animate({
			scrollTop: $modalBody.scrollTop() + $newSlot.position().top
		}, 600);
	}
}

function removeTimeSlot(element) {
	$(element).closest(".data-warp").remove();
}

function timeSlotStatus(obj, status) {
	let buttonSection = $(obj).parent().parent().find('.dropdown-toggle');
	const statusButtonIcons = {
		'Open':         '<svg class=\"inline-block align-text-top\" width=\"14\" height=\"14\" viewBox=\"0 0 12 12\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M8.65546 4.09616C8.83856 4.27927 8.83856 4.57608 8.65546 4.75909L5.5108 7.90384C5.3277 8.08685 5.03098 8.08685 4.84787 7.90384L3.35089 6.40677C3.16779 6.22375 3.16779 5.92694 3.35089 5.74393C3.53391 5.56082 3.83072 5.56082 4.01373 5.74393L5.17929 6.90948L7.99252 4.09616C8.17563 3.91315 8.47244 3.91315 8.65546 4.09616ZM12.0032 6C12.0032 9.3165 9.31921 12 6.00317 12C2.68668 12 0.00317383 9.31604 0.00317383 6C0.00317383 2.6835 2.68713 0 6.00317 0C9.31967 0 12.0032 2.68396 12.0032 6ZM11.0657 6C11.0657 3.20169 8.80112 0.9375 6.00317 0.9375C3.20486 0.9375 0.940674 3.20206 0.940674 6C0.940674 8.79831 3.20523 11.0625 6.00317 11.0625C8.80148 11.0625 11.0657 8.79794 11.0657 6Z\" fill=\"currentColor\"/></svg>',
		'Closed':       '<svg class=\"inline-block align-text-top\" width=\"14\" height=\"14\" viewBox=\"0 0 13 13\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M9.86377 6H2.86377C2.31148 6 1.86377 6.44772 1.86377 7V10.5C1.86377 11.0523 2.31148 11.5 2.86377 11.5H9.86377C10.4161 11.5 10.8638 11.0523 10.8638 10.5V7C10.8638 6.44772 10.4161 6 9.86377 6Z\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M3.86377 6V4C3.86377 3.33696 4.12716 2.70107 4.596 2.23223C5.06484 1.76339 5.70073 1.5 6.36377 1.5C7.02681 1.5 7.6627 1.76339 8.13154 2.23223C8.60038 2.70107 8.86377 3.33696 8.86377 4V6\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>',
		'Only by Phone':'<svg class=\"inline-block align-text-top\" width=\"14\" height=\"14\" viewBox=\"0 0 12 12\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M6 11.625C2.6915 11.625 0 9.1017 0 6C0 2.89828 2.6915 0.375 6 0.375C9.30848 0.375 12 2.89828 12 6C12 9.1017 9.30848 11.625 6 11.625ZM6 1.3125C3.243 1.3125 1 3.41531 1 6C1 8.58465 3.243 10.6875 6 10.6875C8.75696 10.6875 11 8.58465 11 6C11 3.41531 8.75696 1.3125 6 1.3125ZM7.644 6.52265L6.7565 7.35469C5.743 6.99234 4.996 6.31969 4.555 5.29078L5.4425 4.45875L4.0865 3.1875L3.3975 3.83344C3.1275 4.08609 3 4.4189 3 4.75078C3 6.58547 5.516 8.81295 7.333 8.81295C7.687 8.81295 8.042 8.69295 8.31152 8.44028L9.00048 7.79437L7.644 6.52265Z\" fill=\"currentColor\"/></svg>'
	};
	const statusButtonCls = {
		'Open':         'dropdown-toggle bookable bg-[#F0FDF4] border-[#BBF7D0] text-[#166534] text-sm border px-3 py-2.5 h-10 rounded-lg flex items-center whitespace-nowrap min-w-36 max-[1023px]:w-full',
		'Closed':       'dropdown-toggle bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C] text-sm border px-3 py-2.5 h-10 rounded-lg flex items-center whitespace-nowrap min-w-36 max-[1023px]:w-full',
		'Only by Phone':'dropdown-toggle callback bg-[#EFF6FF] border-[#BFDBFE] text-[#1E40AF] text-sm border px-3 py-2.5 h-10 rounded-lg flex items-center whitespace-nowrap min-w-36 max-[1023px]:w-full'
	};

	buttonSection.attr('class', statusButtonCls[status] || statusButtonCls['Closed']);
	buttonSection.find('.status-icon').html(statusButtonIcons[status] || statusButtonIcons['Closed']);
	$("#time_slot_status").val(status);
	buttonSection.find('.bookable-status').text(status);
}

function saveTimeSlot() {
	const date = $("#day-pkg-slot-times #dateForHuman").text();
	const packageId = $("#newTimeSlotForm span").attr("data-tour-package-id");

	const fromTime = $("#from_time").val();
	const toTime = $("#to_time").val();
	const seats = $("#time_slot_seats").val();
	const status = $("#time_slot_status").val();
	const hideTimeSlot = $("#hide_time_slot").is(":checked") ? 1 : 0;

	// Validate required fields
	if (!fromTime || !toTime || !seats) {
		swal("Please fill in all required fields (Start Time, End Time, and Seats).", "", "warning");
		return false;
	}

	showLoading();

	$.post(addTimeSlotUrl, { date, tour_package_id: packageId, from_time: fromTime, to_time: toTime, status, seats, hide_time_slot: hideTimeSlot })
		.done(initTimeSlotUI)
		.fail(showSubmissionError)
		.always(hideLoading);
}

function initTimeSlotUI(response) {
	swal('Time slot added.', '', 'success');

	// Push new slot into Vue's reactive slotTimes so it renders identically to existing slots
	var existingSlot = calApp.dayPkgSlotInfo.slotTimes[0];
	calApp.dayPkgSlotInfo.slotTimes.push({
		id: response.time_slot_id,
		time_from: response.from_time + ':00',
		time_to: response.to_time + ':00',
		seats: parseInt(response.seats),
		tourists: 0,
		booked_seats: 0,
		available_tourists: parseInt(response.seats),
		afl_tourists: 0,
		bookable_status: response.status,
		bookable_status_2: response.status,
		hide_slot: response.hide_slot,
		affiliateFeature: existingSlot ? existingSlot.affiliateFeature : undefined,
	});

	calApp.$set(calApp.dayPkgSlotInfo, 'availableSlots', calApp.dayPkgSlotInfo.availableSlots + parseInt(response.seats));

	calApp.$nextTick(function () {
		$('.dropdown-toggle').each(function () { new bootstrap.Dropdown(this); });
	});

	// Remove the add-form row
	$(".new-time-slot-form .data-warp").attr('id', `time_slot_form_id_${response.time_slot_id}`);
	$(`#time_slot_form_id_${response.time_slot_id}`).remove();
}

function initBookableStatus(slot_id, bookable_status) {
	swal({
		title: 'Update Slot Status',
		text: 'Are you sure you want to update the status for the selected slot?',
		icon: "warning",
		dangerMode: true,
		buttons: {
			no: {
				text: 'Cancel',
				value: false,
				className: 'btn-danger',
			},
			yes: {
				text: 'Continue',
				value: true,
				className: 'btn-success',
			}
		}
	}).then(function (result) {
		if (!result) return false;

		showLoading();

		$.post(updateSlotBookableStatus, { slot_id, status: bookable_status })
			.done(function (response) {
				let buttonId = `#btn-dd-ts-${slot_id}`;

				const statusButtonIcons = {
					'Open':         '<svg class=\"inline-block align-text-top\" width=\"16\" height=\"16\" viewBox=\"0 0 12 12\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M8.65546 4.09616C8.83856 4.27927 8.83856 4.57608 8.65546 4.75909L5.5108 7.90384C5.3277 8.08685 5.03098 8.08685 4.84787 7.90384L3.35089 6.40677C3.16779 6.22375 3.16779 5.92694 3.35089 5.74393C3.53391 5.56082 3.83072 5.56082 4.01373 5.74393L5.17929 6.90948L7.99252 4.09616C8.17563 3.91315 8.47244 3.91315 8.65546 4.09616ZM12.0032 6C12.0032 9.3165 9.31921 12 6.00317 12C2.68668 12 0.00317383 9.31604 0.00317383 6C0.00317383 2.6835 2.68713 0 6.00317 0C9.31967 0 12.0032 2.68396 12.0032 6ZM11.0657 6C11.0657 3.20169 8.80112 0.9375 6.00317 0.9375C3.20486 0.9375 0.940674 3.20206 0.940674 6C0.940674 8.79831 3.20523 11.0625 6.00317 11.0625C8.80148 11.0625 11.0657 8.79794 11.0657 6Z\" fill=\"currentColor\"/></svg>',
					'Closed':       '<svg class=\"inline-block align-text-top\" width=\"16\" height=\"16\" viewBox=\"0 0 13 13\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M9.86377 6H2.86377C2.31148 6 1.86377 6.44772 1.86377 7V10.5C1.86377 11.0523 2.31148 11.5 2.86377 11.5H9.86377C10.4161 11.5 10.8638 11.0523 10.8638 10.5V7C10.8638 6.44772 10.4161 6 9.86377 6Z\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/><path d=\"M3.86377 6V4C3.86377 3.33696 4.12716 2.70107 4.596 2.23223C5.06484 1.76339 5.70073 1.5 6.36377 1.5C7.02681 1.5 7.6627 1.76339 8.13154 2.23223C8.60038 2.70107 8.86377 3.33696 8.86377 4V6\" stroke=\"currentColor\" stroke-linecap=\"round\" stroke-linejoin=\"round\"/></svg>',
					'Only by Phone':'<svg class=\"inline-block align-text-top\" width=\"16\" height=\"16\" viewBox=\"0 0 12 12\" fill=\"none\" xmlns=\"http://www.w3.org/2000/svg\"><path d=\"M6 11.625C2.6915 11.625 0 9.1017 0 6C0 2.89828 2.6915 0.375 6 0.375C9.30848 0.375 12 2.89828 12 6C12 9.1017 9.30848 11.625 6 11.625ZM6 1.3125C3.243 1.3125 1 3.41531 1 6C1 8.58465 3.243 10.6875 6 10.6875C8.75696 10.6875 11 8.58465 11 6C11 3.41531 8.75696 1.3125 6 1.3125ZM7.644 6.52265L6.7565 7.35469C5.743 6.99234 4.996 6.31969 4.555 5.29078L5.4425 4.45875L4.0865 3.1875L3.3975 3.83344C3.1275 4.08609 3 4.4189 3 4.75078C3 6.58547 5.516 8.81295 7.333 8.81295C7.687 8.81295 8.042 8.69295 8.31152 8.44028L9.00048 7.79437L7.644 6.52265Z\" fill=\"currentColor\"/></svg>'
				};
				const statusButtonCls = {
					'Open':         'dropdown-toggle bookable bg-[#F0FDF4] border-[#BBF7D0] text-[#166534] text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 max-[1023px]:w-full',
					'Closed':       'dropdown-toggle bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C] text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 max-[1023px]:w-full',
					'Only by Phone':'dropdown-toggle callback bg-[#EFF6FF] border-[#BFDBFE] text-[#1E40AF] text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 max-[1023px]:w-full'
				};

				$(buttonId).attr('class', statusButtonCls[response.status] || statusButtonCls['Closed']);
				$(buttonId).children('span:first').html(statusButtonIcons[response.status] || statusButtonIcons['Closed']);
				$(`${buttonId} .bookable-status`).text(response.status);

				var slot = calApp.dayPkgSlotInfo.slotTimes.find(function(s) { return s.id == slot_id; });
				if (slot) calApp.$set(slot, 'bookable_status_2', response.status);

				swal('Slot status updated.', '', 'success');
			})
			.fail(showSubmissionError)
			.always(hideLoading);
	});
}

function updateBulkActionBar() {
	var count = $('.checkBoxClass:checked').length;
	if (count > 0) {
		$('#bulk-selected-count').text(count + ' selected');
		$('#bulk-action-bar').removeClass('hidden');
	} else {
		$('#bulk-action-bar').addClass('hidden');
	}
}

$("#all-slot-checkbox").click(function () {
	$(".checkBoxClass").prop('checked', $(this).prop('checked'));
	updateBulkActionBar();
});

$(document).on("click", ".checkBoxClass", function () {
	var total   = $('.checkBoxClass').length;
	var checked = $('.checkBoxClass:checked').length;
	$("#all-slot-checkbox").prop('checked', checked === total && total > 0);
	updateBulkActionBar();
});
$(document).on('click', '#nextSlotBtn', function () {
	// Add null checks to prevent Vue.js errors
	if (typeof calApp === 'undefined' || !calApp) {
		return;
	}

	const vueCalApp = calApp;

	// Check if dayPkgSlotInfo exists and has slotTimes
	if (!vueCalApp.dayPkgSlotInfo || !vueCalApp.dayPkgSlotInfo.slotTimes) {
		return;
	}

	// Check if currentSlotIndex is valid
	if (typeof vueCalApp.currentSlotIndex !== 'number') {
		return;
	}

	const nextIndex = vueCalApp.currentSlotIndex + 1;

	if (nextIndex < vueCalApp.dayPkgSlotInfo.slotTimes.length) {
		vueCalApp.timeSlotBookingsModal(nextIndex);
	} else {
		swal('', 'No more next slots available.', 'info');
	}
});

$(document).on('click', '#prevSlotBtn', function () {
	// Add null checks to prevent Vue.js errors
	if (typeof calApp === 'undefined' || !calApp) {
		return;
	}

	const vueCalApp = calApp;

	// Check if dayPkgSlotInfo exists and has slotTimes
	if (!vueCalApp.dayPkgSlotInfo || !vueCalApp.dayPkgSlotInfo.slotTimes) {
		return;
	}

	// Check if currentSlotIndex is valid
	if (typeof vueCalApp.currentSlotIndex !== 'number') {
		return;
	}

	const prevIndex = vueCalApp.currentSlotIndex - 1;

	if (prevIndex >= 0) {
		vueCalApp.timeSlotBookingsModal(prevIndex);
	} else {
		swal('', 'This is the first slot.', 'info');
	}
});
$(document).ready(function () {
	$("#slot-update-dropdown").hide();
	$("#time-slot-status").hide();
});

function clearBulkSelection() {
	$('.checkBoxClass').prop('checked', false);
	$('#all-slot-checkbox').prop('checked', false);
	$('#bulk-action-bar').addClass('hidden');
}

function saveTimeSlotStatusDirect(status) {
	var slotIds = [];
	$('.checkBoxClass:checked').each(function () { slotIds.push(this.value); });
	if (!slotIds.length) return;

	var statusLabels = { 'Open': 'Open', 'Closed': 'Closed', 'Only by Phone': 'Phone Only' };
	swal({
		title: 'Update Slot Status',
		text: 'Are you sure you want to set ' + slotIds.length + ' slot(s) to "' + (statusLabels[status] || status) + '"?',
		icon: 'warning',
		dangerMode: true,
		buttons: {
			no:  { text: 'Cancel',   value: false, className: 'btn-danger' },
			yes: { text: 'Continue', value: true,  className: 'btn-success' }
		}
	}).then(function (result) {
		if (!result) return;
		showLoading();
	$.post(bulkUpdateSlotBookableStatus, { slotIds, status: status })
		.done(function (response) {
			const statusButtonIcons = {
				'Open':         '<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.65546 4.09616C8.83856 4.27927 8.83856 4.57608 8.65546 4.75909L5.5108 7.90384C5.3277 8.08685 5.03098 8.08685 4.84787 7.90384L3.35089 6.40677C3.16779 6.22375 3.16779 5.92694 3.35089 5.74393C3.53391 5.56082 3.83072 5.56082 4.01373 5.74393L5.17929 6.90948L7.99252 4.09616C8.17563 3.91315 8.47244 3.91315 8.65546 4.09616ZM12.0032 6C12.0032 9.3165 9.31921 12 6.00317 12C2.68668 12 0.00317383 9.31604 0.00317383 6C0.00317383 2.6835 2.68713 0 6.00317 0C9.31967 0 12.0032 2.68396 12.0032 6ZM11.0657 6C11.0657 3.20169 8.80112 0.9375 6.00317 0.9375C3.20486 0.9375 0.940674 3.20206 0.940674 6C0.940674 8.79831 3.20523 11.0625 6.00317 11.0625C8.80148 11.0625 11.0657 8.79794 11.0657 6Z" fill="currentColor"/></svg>',
				'Closed':       '<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.86377 6H2.86377C2.31148 6 1.86377 6.44772 1.86377 7V10.5C1.86377 11.0523 2.31148 11.5 2.86377 11.5H9.86377C10.4161 11.5 10.8638 11.0523 10.8638 10.5V7C10.8638 6.44772 10.4161 6 9.86377 6Z" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/><path d="M3.86377 6V4C3.86377 3.33696 4.12716 2.70107 4.596 2.23223C5.06484 1.76339 5.70073 1.5 6.36377 1.5C7.02681 1.5 7.6627 1.76339 8.13154 2.23223C8.60038 2.70107 8.86377 3.33696 8.86377 4V6" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"/></svg>',
				'Only by Phone':'<svg class="inline-block align-text-top" width="16" height="16" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6 11.625C2.6915 11.625 0 9.1017 0 6C0 2.89828 2.6915 0.375 6 0.375C9.30848 0.375 12 2.89828 12 6C12 9.1017 9.30848 11.625 6 11.625ZM6 1.3125C3.243 1.3125 1 3.41531 1 6C1 8.58465 3.243 10.6875 6 10.6875C8.75696 10.6875 11 8.58465 11 6C11 3.41531 8.75696 1.3125 6 1.3125ZM7.644 6.52265L6.7565 7.35469C5.743 6.99234 4.996 6.31969 4.555 5.29078L5.4425 4.45875L4.0865 3.1875L3.3975 3.83344C3.1275 4.08609 3 4.4189 3 4.75078C3 6.58547 5.516 8.81295 7.333 8.81295C7.687 8.81295 8.042 8.69295 8.31152 8.44028L9.00048 7.79437L7.644 6.52265Z" fill="currentColor"/></svg>'
			};
			const statusButtonCls = {
				'Open':         'dropdown-toggle bookable bg-[#F0FDF4] border-[#BBF7D0] text-[#166534] text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 max-[1023px]:w-full',
				'Closed':       'dropdown-toggle bg-[#FEF2F2] border-[#FECACA] text-[#B91C1C] text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 max-[1023px]:w-full',
				'Only by Phone':'dropdown-toggle callback bg-[#EFF6FF] border-[#BFDBFE] text-[#1E40AF] text-xs border px-3 py-2.5 rounded-lg flex items-center min-w-36 max-[1023px]:w-full'
			};

			$.each(slotIds, function (key, val) {
				let buttonId = `#btn-dd-ts-${val}`;
				$(buttonId).attr('class', statusButtonCls[response.status] || statusButtonCls['Closed']);
				$(buttonId).children('span:first').html(statusButtonIcons[response.status] || statusButtonIcons['Closed']);
				$(`${buttonId} .bookable-status`).text(response.status);

				var slot = calApp.dayPkgSlotInfo.slotTimes.find(function(s) { return s.id == val; });
				if (slot) calApp.$set(slot, 'bookable_status_2', response.status);
			});
			clearBulkSelection();
			swal('Slot status updated.', '', 'success');
		})
		.fail(showSubmissionError)
		.always(hideLoading);
	});
}

function bulkSetVisibility(hideSlot) {
	var slotIds = [];
	$('.checkBoxClass:checked').each(function () { slotIds.push(this.value); });
	if (!slotIds.length) return;

	var action = hideSlot == 1 ? 'hide' : 'make visible';
	swal({
		title: 'Update Slot Visibility',
		text: 'Are you sure you want to ' + action + ' ' + slotIds.length + ' slot(s)?',
		icon: 'warning',
		dangerMode: true,
		buttons: {
			no:  { text: 'Cancel',   value: false, className: 'btn-danger' },
			yes: { text: 'Continue', value: true,  className: 'btn-success' }
		}
	}).then(function (result) {
		if (!result) return;
		showLoading();
		$.post(bulkUpdateSlotHideStatusUrl, { slotIds: slotIds, hide_slot: hideSlot })
		.done(function (response) {
			slotIds.forEach(function (val) {
				var slot = calApp.dayPkgSlotInfo.slotTimes.find(function(s) { return s.id == val; });
				if (slot) calApp.$set(slot, 'hide_slot', hideSlot);
			});
			clearBulkSelection();
			swal((hideSlot == 1 ? 'Slots hidden.' : 'Slots made visible.'), '', 'success');
		})
		.fail(showSubmissionError)
		.always(hideLoading);
	});
}
