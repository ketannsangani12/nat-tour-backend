{{-- Payment Breakdown Report - JavaScript Functions --}}

<script>
	// ============================================
	// ROUTE PREFIX CONFIGURATION
	// ============================================
	const routePrefix = '{{ routePrefixForJs() }}';
	const pbTenantId = '{{ tenant()->id }}';
	const disputeReversalEnabled = {{ hasSetting('DISPUTE_REVERSAL') ? 'true' : 'false' }};

	// ============================================
	// FILTER PRESET MANAGER
	// ============================================

	const FilterPresetManager = {
		// API base URL - Dynamic based on user type
		apiUrl: `/${routePrefix}/report/filter-presets`,
		reportType: 'payment_breakdown',

		// Capture current filter state
		captureFilterState: function() {
			const paymentTypes = [];
			$('.pb-payment-type:checked').each(function() {
				paymentTypes.push($(this).val());
			});

			const tourPackages = [];
			$('.pb-tour-package-checkbox:checked').each(function() {
				tourPackages.push($(this).val());
			});

			// Only capture affiliates if they exist
			const hasAffiliates = window.pbAffiliates && window.pbAffiliates.length > 0;
			const affiliates = [];
			if (hasAffiliates) {
				$('.pb-affiliate-checkbox:checked').each(function() {
					affiliates.push($(this).val());
				});
			}

			const checkInStatuses = [];
			$('.pb-checkin-status-checkbox:checked').each(function() {
				checkInStatuses.push($(this).val());
			});

			// Only capture third party platforms if they're enabled
			const hasEnabledPlatforms = window.pbEnabledThirdPartyPlatforms && window.pbEnabledThirdPartyPlatforms.length > 0;
			const thirdPartyPlatforms = [];
			if (hasEnabledPlatforms) {
				$('.pb-third-party-checkbox:checked').each(function() {
					thirdPartyPlatforms.push($(this).val());
				});
			}

			const excludeAllAffiliates = hasAffiliates ? ($('#pbExcludeAllAffiliates').val() === '1') : false;
			const excludeAllThirdParty = hasEnabledPlatforms ? ($('#pbExcludeAllThirdParty').val() === '1') : false;

			const chargeTypes = [];
			$('.pb-charge-type-checkbox:checked').each(function() {
				chargeTypes.push($(this).val());
			});

			return {
				tour_operator: window.pbTourOperatorId || null,
				tour_packages: tourPackages.length > 0 ? tourPackages : null,
				affiliates: hasAffiliates && affiliates.length > 0 ? affiliates : null,
				exclude_all_affiliates: excludeAllAffiliates ? true : false,
				third_party_platforms: hasEnabledPlatforms && thirdPartyPlatforms.length > 0 ? thirdPartyPlatforms : null,
				exclude_all_third_party: excludeAllThirdParty ? true : false,
				date_from: $('#pbDateFrom').val() || null,
				date_to: $('#pbDateTo').val() || null,
				payment_types: paymentTypes.length > 0 ? paymentTypes : null,
				view_by: $('#pbViewBy').val() || 'tourdate',
				booking_status: $('#pbBookingStatus').val() || null,
				checkin_statuses: checkInStatuses.length > 0 ? checkInStatuses : null,
				waiver_status: $('#pbWaiverStatus').val() || null,
				amount_min: $('#pbMinAmount').val() || null,
				amount_max: $('#pbMaxAmount').val() || null,
				rate: $('#pbRate').val() || null,
				permit_fee: $('#pbPermitFee').val() || null,
				tax: $('#pbTax').val() || null,
				soft_blocks: $('#pbSoftBlocks').val() || null,
				reallocated_funds: $('#pbReallocatedFunds').val() || null,
				charge_types: chargeTypes.length > 0 ? chargeTypes : null
			};
		},

		// Apply filter state to UI
		applyFilterState: function(filters) {
			try {
				// First, reset all filters to default/empty state
				$('.pb-tour-package-checkbox').prop('checked', false);
				$('.pb-affiliate-checkbox').prop('checked', false);
				$('#pbExcludeAllAffiliates').val('0');
				$('.pb-third-party-checkbox').prop('checked', false);
				$('#pbExcludeAllThirdParty').val('0');
				$('.pb-checkin-status-checkbox').prop('checked', false);
				$('#pbDateRange').val('');
				$('#pbDateFrom').val('');
				$('#pbDateTo').val('');
				$('.pb-payment-type').prop('checked', false);
				$('#pbViewBy').val('tourdate').trigger('change');
				$('#pbBookingStatus').val('').trigger('change');
				$('#pbWaiverStatus').val('').trigger('change');
				$('#pbMinAmount').val('');
				$('#pbMaxAmount').val('');
				$('#pbRate').val('');
				$('#pbPermitFee').val('');
				$('#pbTax').val('');
				$('#pbSoftBlocks').val('').trigger('change');
				$('#pbReallocatedFunds').val('').trigger('change');
				$('.pb-charge-type-checkbox').prop('checked', false);
				updateChargeTypeLabel();

				// Now apply the preset values
				// Tour Packages (multi-select)
				if (filters.tour_packages && Array.isArray(filters.tour_packages)) {
					filters.tour_packages.forEach(pkgId => {
						$(`.pb-tour-package-checkbox[value="${pkgId}"]`).prop('checked', true);
					});
					updateTourPackageLabel();
				} else if (filters.tour_package) {
					// Backward compatibility with old single-select format
					$(`.pb-tour-package-checkbox[value="${filters.tour_package}"]`).prop('checked', true);
					updateTourPackageLabel();
				}

				// Affiliates (multi-select) - only apply if affiliates exist
				const hasAffiliates = window.pbAffiliates && window.pbAffiliates.length > 0;
				if (hasAffiliates) {
					if (filters.exclude_all_affiliates === true || filters.exclude_all_affiliates === 1 || filters.exclude_all_affiliates === 'true' || filters.exclude_all_affiliates === '1') {
						// Exclude all affiliates mode
						$('#pbExcludeAllAffiliates').val('1');
						$('.pb-affiliate-checkbox').prop('checked', false);
						updateAffiliateLabel();
					} else if (filters.affiliates && Array.isArray(filters.affiliates) && filters.affiliates.length > 0) {
						$('#pbExcludeAllAffiliates').val('0');
						filters.affiliates.forEach(affId => {
							$(`.pb-affiliate-checkbox[value="${affId}"]`).prop('checked', true);
						});
						updateAffiliateLabel();
					} else if (filters.affiliate) {
						// Backward compatibility with old single-select format
						$('#pbExcludeAllAffiliates').val('0');
						$(`.pb-affiliate-checkbox[value="${filters.affiliate}"]`).prop('checked', true);
						updateAffiliateLabel();
					} else {
						// No affiliates selected and not excluding all - reset to default (All Affiliates)
						$('#pbExcludeAllAffiliates').val('0');
						$('.pb-affiliate-checkbox').prop('checked', false);
						updateAffiliateLabel();
					}
				}

				// Date Range
				if (filters.date_from && filters.date_to) {
					const startDate = moment(filters.date_from);
					const endDate = moment(filters.date_to);
					$('#pbDateRange').val(startDate.format('MM/DD/YYYY') + ' - ' + endDate.format('MM/DD/YYYY'));
					$('#pbDateFrom').val(filters.date_from);
					$('#pbDateTo').val(filters.date_to);
				}

				// Payment Types
				if (filters.payment_types && Array.isArray(filters.payment_types)) {
					filters.payment_types.forEach(type => {
						$(`.pb-payment-type[value="${type}"]`).prop('checked', true);
					});
				} else {
					// If no payment types specified, check all by default
					$('.pb-payment-type').prop('checked', true);
				}

				// View By
				if (filters.view_by) {
					$('#pbViewBy').val(filters.view_by).trigger('change');
				}

				// Booking Status
				if (filters.booking_status) {
					$('#pbBookingStatus').val(filters.booking_status).trigger('change');
				}

				// Check-in Statuses (multi-select)
				if (filters.checkin_statuses && Array.isArray(filters.checkin_statuses)) {
					filters.checkin_statuses.forEach(status => {
						$(`.pb-checkin-status-checkbox[value="${status}"]`).prop('checked', true);
					});
					updateCheckInStatusLabel();
				} else if (filters.checkin_status) {
					// Backward compatibility with old single-select format
					$(`.pb-checkin-status-checkbox[value="${filters.checkin_status}"]`).prop('checked', true);
					updateCheckInStatusLabel();
				}

				// Waiver Status
				if (filters.waiver_status) {
					$('#pbWaiverStatus').val(filters.waiver_status).trigger('change');
				}

				// Third Party Platforms (multi-select) - only apply if platforms are enabled
				const hasEnabledPlatforms = window.pbEnabledThirdPartyPlatforms && window.pbEnabledThirdPartyPlatforms.length > 0;
				if (hasEnabledPlatforms) {
					if (filters.exclude_all_third_party === true || filters.exclude_all_third_party === 1 || filters.exclude_all_third_party === 'true' || filters.exclude_all_third_party === '1') {
						// Exclude all third party platforms mode
						$('#pbExcludeAllThirdParty').val('1');
						$('.pb-third-party-checkbox').prop('checked', false);
						updateThirdPartyLabel();
					} else if (filters.third_party_platforms && Array.isArray(filters.third_party_platforms) && filters.third_party_platforms.length > 0) {
						$('#pbExcludeAllThirdParty').val('0');
						filters.third_party_platforms.forEach(platform => {
							$(`.pb-third-party-checkbox[value="${platform}"]`).prop('checked', true);
						});
						updateThirdPartyLabel();
					} else if (filters.third_party_platform) {
						// Backward compatibility with old single-select format
						$('#pbExcludeAllThirdParty').val('0');
						if (filters.third_party_platform === 'Direct') {
							$('#pbExcludeAllThirdParty').val('1');
							$('.pb-third-party-checkbox').prop('checked', false);
						} else {
							$(`.pb-third-party-checkbox[value="${filters.third_party_platform}"]`).prop('checked', true);
						}
						updateThirdPartyLabel();
					} else {
						// No third party platforms selected and not excluding all - reset to default (All Platforms)
						$('#pbExcludeAllThirdParty').val('0');
						$('.pb-third-party-checkbox').prop('checked', false);
						updateThirdPartyLabel();
					}
				}

				// Amount Range
				if (filters.amount_min) {
					$('#pbMinAmount').val(filters.amount_min);
				}
				if (filters.amount_max) {
					$('#pbMaxAmount').val(filters.amount_max);
				}

				// Rate
				if (filters.rate) {
					$('#pbRate').val(filters.rate);
				}

				// Permit Fee
				if (filters.permit_fee) {
					$('#pbPermitFee').val(filters.permit_fee);
				}

				// Tax
				if (filters.tax) {
					$('#pbTax').val(filters.tax);
				}

				// Soft Blocks
				if (filters.soft_blocks !== null && filters.soft_blocks !== undefined) {
					$('#pbSoftBlocks').val(filters.soft_blocks).trigger('change');
				}

				// Reallocated Funds
				if (filters.reallocated_funds !== null && filters.reallocated_funds !== undefined) {
					$('#pbReallocatedFunds').val(filters.reallocated_funds).trigger('change');
				}

				// Charge Types (multi-select)
				if (filters.charge_types && Array.isArray(filters.charge_types) && filters.charge_types.length > 0) {
					filters.charge_types.forEach(type => {
						$(`.pb-charge-type-checkbox[value="${type}"]`).prop('checked', true);
					});
					updateChargeTypeLabel();
				}

				console.log('Filter state applied successfully');
			} catch (error) {
				console.error('Error applying filter state:', error);
			}
		},

		// Save preset
		savePreset: function(name, filters) {
			return $.ajax({
				url: this.apiUrl,
				method: 'POST',
				data: {
					name: name,
					report_type: this.reportType,
					filters: filters,
					_token: $('meta[name="csrf-token"]').attr('content')
				},
				dataType: 'json'
			});
		},

		// Load preset list
		loadPresetList: function() {
			return $.ajax({
				url: this.apiUrl,
				method: 'GET',
				data: { report_type: this.reportType },
				dataType: 'json'
			});
		},

		// Load single preset
		loadPreset: function(id) {
			return $.ajax({
				url: `${this.apiUrl}/${id}`,
				method: 'GET',
				dataType: 'json'
			});
		},

		// Delete preset
		deletePreset: function(id) {
			return $.ajax({
				url: `${this.apiUrl}/${id}`,
				method: 'DELETE',
				data: {
					_token: $('meta[name="csrf-token"]').attr('content')
				},
				dataType: 'json'
			});
		},

		// Update preset dropdown UI
		updatePresetDropdown: function(presets) {
			const $list = $('#pbPresetList');
			$list.empty();

			if (!presets || presets.length === 0) {
				$list.append(`
					<div class="px-4 py-3 text-sm text-gray-500 text-center">
						No saved presets
					</div>
				`);
				return;
			}

			presets.forEach(preset => {
				$list.append(`
					<div class="preset-item flex items-center justify-between px-4 py-2 hover:bg-gray-50 cursor-pointer group">
						<a href="#" class="preset-load flex-1 text-sm text-gray-700 hover:text-orange-600" data-id="${preset.id}">
							<i class="fas fa-bookmark mr-2 text-gray-400 group-hover:text-orange-500"></i>
							${preset.name}
						</a>
						<button class="preset-delete text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity" data-id="${preset.id}">
							<i class="fas fa-trash text-xs"></i>
						</button>
					</div>
				`);
			});
		}
	};

	// ============================================
	// PAYMENT BREAKDOWN REPORT - JAVASCRIPT
	// ============================================

	// Global variables for table state
	let pbTableData = [];
	let pbFilteredData = [];
	let pbCurrentPage = 1;
	let pbPageSize = 100;
	let pbSortColumn = null;
	let pbSortDirection = 'asc';
	let pbSearchTerm = '';
	let pbVisibleColumns = {
		'booking-id': true,
		'tour-date': true,
		'tour-time': false,
		'created-date': false,
		'purchase-date': false,
		'tour-package': false,
		'customer': true,
		'customer-email': false,
		'customer-phone': false,
		'payment-type': true,
		'payment': true,
		'unpaid': false,
		'fees': true,
		'refund': true,
		'discount': true,
		'net': true,
		'pax': false,
		'rate': false,
		'tax': false,
		'permit-fee': false,
		'status': true,
		'checkin': false,
		'waiver': false,
		'platform': false,
		'affiliate': false,
		'reallocated': false
	};

	// Global function to update tour package label
	function updateTourPackageLabel() {
		const selected = $('.pb-tour-package-checkbox:checked');
		const total = $('.pb-tour-package-checkbox').length;

		if (selected.length === 0 || selected.length === total) {
			$('#pbTourPackageLabel').text('All Packages');
		} else if (selected.length === 1) {
			$('#pbTourPackageLabel').text(selected.first().data('name'));
		} else {
			$('#pbTourPackageLabel').text(`${selected.length} Packages Selected`);
		}
	}

	// Global function to update charge type label
	function updateChargeTypeLabel() {
		const selected = $('.pb-charge-type-checkbox:checked');
		const total = $('.pb-charge-type-checkbox').length;

		if (selected.length === 0 || selected.length === total) {
			$('#pbChargeTypeLabel').text('All Charge Types');
		} else if (selected.length === 1) {
			$('#pbChargeTypeLabel').text(selected.first().parent().find('span').text());
		} else {
			$('#pbChargeTypeLabel').text(`${selected.length} Selected`);
		}
	}

	// Global function to update affiliate label
	function updateAffiliateLabel() {
		const selected = $('.pb-affiliate-checkbox:checked');
		const total = $('.pb-affiliate-checkbox').length;
		const excludeAll = $('#pbExcludeAllAffiliates').val() === '1';
		
		if (excludeAll) {
			$('#pbAffiliateLabel').text('Exclude All Affiliates').css('color', '#dc2626');
		} else {
			$('#pbAffiliateLabel').css('color', '');
			if (selected.length === 0) {
				// Nothing selected = no affiliate filter (shows all bookings)
				$('#pbAffiliateLabel').text('All Affiliates');
			} else if (selected.length === total) {
				// All affiliates selected = filter to only affiliate bookings
				$('#pbAffiliateLabel').text('Affiliates Only').css('color', '#2563eb');
			} else if (selected.length === 1) {
				$('#pbAffiliateLabel').text(selected.first().data('name'));
			} else {
				$('#pbAffiliateLabel').text(`${selected.length} Affiliates Selected`);
			}
		}
	}

	// Global function to update third party platform label
	function updateThirdPartyLabel() {
		const selected = $('.pb-third-party-checkbox:checked');
		const total = $('.pb-third-party-checkbox').length;
		const excludeAll = $('#pbExcludeAllThirdParty').val() === '1';

		// If no platforms are enabled, hide the dropdown section
		if (total === 0) {
			$('#pbThirdPartyLabel').text('No Platforms Available');
			return;
		}

		if (excludeAll) {
			$('#pbThirdPartyLabel').text('Exclude All Platforms').css('color', '#dc2626');
		} else {
			$('#pbThirdPartyLabel').css('color', '');
			if (selected.length === 0) {
				// Nothing selected = no third party filter (shows all bookings)
				$('#pbThirdPartyLabel').text('All Platforms');
			} else if (selected.length === total) {
				// All platforms selected = filter to only third party bookings
				$('#pbThirdPartyLabel').text('Third Party Only').css('color', '#2563eb');
			} else if (selected.length === 1) {
				$('#pbThirdPartyLabel').text(selected.first().data('name'));
			} else {
				$('#pbThirdPartyLabel').text(`${selected.length} Platforms Selected`);
			}
		}
	}

	// Global function to update check-in status label
	function updateCheckInStatusLabel() {
		const selected = $('.pb-checkin-status-checkbox:checked');
		const total = $('.pb-checkin-status-checkbox').length;
		
		if (selected.length === 0 || selected.length === total) {
			$('#pbCheckInStatusLabel').text('All Check-in Status');
		} else if (selected.length === 1) {
			$('#pbCheckInStatusLabel').text(selected.first().data('name'));
		} else {
			$('#pbCheckInStatusLabel').text(`${selected.length} Selected`);
		}
	}

	// Initialize Payment Breakdown Report
	function initializePaymentBreakdownReport() {
		// Get tour operator timezone from backend
		const tourOperatorTimezone = window.pbTourOperatorTimezone || "{{ config('app.timezone') }}";

		// Get current date using moment.js (without timezone support)
		const currentDate = moment();
		const startOfMonth = moment().startOf('month');

		// Initialize date range picker with default dates and preset ranges
		if (typeof $('#pbDateRange').daterangepicker === 'function') {
			$('#pbDateRange').daterangepicker({
				autoUpdateInput: false,
				startDate: startOfMonth,
				endDate: currentDate,
				locale: {
					cancelLabel: 'Clear',
					format: 'MM/DD/YYYY'
				},
				ranges: {
					'Today': [moment(), moment()],
					'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
					'Last 7 Days': [moment().subtract(6, 'days'), moment()],
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
					'This Year': [moment().startOf('year'), moment().endOf('year')],
					'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
				},
				alwaysShowCalendars: true,
				showCustomRangeLabel: true
			});

			// Set default date range (start of month to today)
			$('#pbDateRange').val(startOfMonth.format('MM/DD/YYYY') + ' - ' + currentDate.format('MM/DD/YYYY'));
			$('#pbDateFrom').val(startOfMonth.format('YYYY-MM-DD'));
			$('#pbDateTo').val(currentDate.format('YYYY-MM-DD'));

			$('#pbDateRange').on('apply.daterangepicker', function(ev, picker) {
				$(this).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
				$('#pbDateFrom').val(picker.startDate.format('YYYY-MM-DD'));
				$('#pbDateTo').val(picker.endDate.format('YYYY-MM-DD'));
			});

			$('#pbDateRange').on('cancel.daterangepicker', function(ev, picker) {
				$(this).val('');
				$('#pbDateFrom').val('');
				$('#pbDateTo').val('');
			});
		}

		// Populate tour packages multi-select dropdown
		const tourPackages = window.pbTourPackages || [];

		if (tourPackages && tourPackages.length > 0) {
			tourPackages.forEach(pkg => {
				$('#pbTourPackageList').append(`
					<label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
						<input type="checkbox" class="pb-tour-package-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
							value="${pkg.id}" data-name="${pkg.name}">
						<span class="text-sm text-gray-700">${pkg.name}</span>
					</label>
				`);
			});
		}

		// Tour Package dropdown toggle
		$('#pbTourPackageBtn').on('click', function(e) {
			e.stopPropagation();
			$('#pbTourPackageDropdown').toggleClass('hidden');
		});

		// Select/Deselect all tour packages
		$('#pbSelectAllPackages').on('click', function() {
			$('.pb-tour-package-checkbox').prop('checked', true);
			updateTourPackageLabel();
		});

		$('#pbDeselectAllPackages').on('click', function() {
			$('.pb-tour-package-checkbox').prop('checked', false);
			updateTourPackageLabel();
		});

		// Update label when checkboxes change
		$(document).on('change', '.pb-tour-package-checkbox', function() {
			updateTourPackageLabel();
		});

		// Close tour package dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbTourPackageBtn').length && !$(e.target).closest('#pbTourPackageDropdown').length) {
				$('#pbTourPackageDropdown').addClass('hidden');
			}
		});

		// Charge Type dropdown toggle
		$('#pbChargeTypeBtn').on('click', function(e) {
			e.stopPropagation();
			$('#pbChargeTypeDropdown').toggleClass('hidden');
		});

		// Select/Deselect all charge types
		$('#pbSelectAllChargeTypes').on('click', function() {
			$('.pb-charge-type-checkbox').prop('checked', true);
			updateChargeTypeLabel();
		});

		$('#pbDeselectAllChargeTypes').on('click', function() {
			$('.pb-charge-type-checkbox').prop('checked', false);
			updateChargeTypeLabel();
		});

		// Update label when checkboxes change
		$(document).on('change', '.pb-charge-type-checkbox', function() {
			updateChargeTypeLabel();
		});

		// Close charge type dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbChargeTypeBtn').length && !$(e.target).closest('#pbChargeTypeDropdown').length) {
				$('#pbChargeTypeDropdown').addClass('hidden');
			}
		});

		// Populate affiliates multi-select dropdown
		const affiliates = window.pbAffiliates || [];

		if (affiliates && affiliates.length > 0) {
			affiliates.forEach(affiliate => {
				$('#pbAffiliateList').append(`
					<label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
						<input type="checkbox" class="pb-affiliate-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
							value="${affiliate.id}" data-name="${affiliate.name}">
						<span class="text-sm text-gray-700">${affiliate.name}</span>
					</label>
				`);
			});
		} else {
			// Hide the entire Affiliate dropdown container when no affiliates exist
			$('#pbAffiliateBtn').closest('.flex-1').hide();
		}

		// Affiliate dropdown toggle
		$('#pbAffiliateBtn').on('click', function(e) {
			e.stopPropagation();
			$('#pbAffiliateDropdown').toggleClass('hidden');
		});

		// Select/Deselect all affiliates
		$('#pbSelectAllAffiliates').on('click', function() {
			$('#pbExcludeAllAffiliates').val('0');
			$('.pb-affiliate-checkbox').prop('checked', true);
			updateAffiliateLabel();
		});

		$('#pbExcludeAllAffiliatesBtn').on('click', function() {
			$('#pbExcludeAllAffiliates').val('1');
			$('.pb-affiliate-checkbox').prop('checked', false);
			updateAffiliateLabel();
		});

		$('#pbDeselectAllAffiliates').on('click', function() {
			$('#pbExcludeAllAffiliates').val('0');
			$('.pb-affiliate-checkbox').prop('checked', false);
			updateAffiliateLabel();
		});

		// Update label when checkboxes change
		$(document).on('change', '.pb-affiliate-checkbox', function() {
			// If user manually checks/unchecks, clear the exclude all flag
			$('#pbExcludeAllAffiliates').val('0');
			updateAffiliateLabel();
		});

		// Close affiliate dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbAffiliateBtn').length && !$(e.target).closest('#pbAffiliateDropdown').length) {
				$('#pbAffiliateDropdown').addClass('hidden');
			}
		});

		// Populate third party platforms multi-select dropdown
		const enabledThirdPartyPlatforms = window.pbEnabledThirdPartyPlatforms || [];

		if (enabledThirdPartyPlatforms && enabledThirdPartyPlatforms.length > 0) {
			enabledThirdPartyPlatforms.forEach(platform => {
				$('#pbThirdPartyList').append(`
					<label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
						<input type="checkbox" class="pb-third-party-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
							value="${platform}" data-name="${platform}">
						<span class="text-sm text-gray-700">${platform}</span>
					</label>
				`);
			});
		} else {
			// Hide the entire Third Party Platform dropdown container when no platforms are enabled
			$('#pbThirdPartyBtn').closest('.flex-1').hide();
		}

		// Third Party Platform dropdown toggle
		$('#pbThirdPartyBtn').on('click', function(e) {
			e.stopPropagation();
			$('#pbThirdPartyDropdown').toggleClass('hidden');
		});

		// Select/Deselect all third party platforms
		$('#pbSelectAllThirdParty').on('click', function() {
			$('#pbExcludeAllThirdParty').val('0');
			$('.pb-third-party-checkbox').prop('checked', true);
			updateThirdPartyLabel();
		});

		$('#pbExcludeAllThirdPartyBtn').on('click', function() {
			$('#pbExcludeAllThirdParty').val('1');
			$('.pb-third-party-checkbox').prop('checked', false);
			updateThirdPartyLabel();
		});

		$('#pbDeselectAllThirdParty').on('click', function() {
			$('#pbExcludeAllThirdParty').val('0');
			$('.pb-third-party-checkbox').prop('checked', false);
			updateThirdPartyLabel();
		});

		// Update label when checkboxes change
		$(document).on('change', '.pb-third-party-checkbox', function() {
			// If user manually checks/unchecks, clear the exclude all flag
			$('#pbExcludeAllThirdParty').val('0');
			updateThirdPartyLabel();
		});

		// Close third party dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbThirdPartyBtn').length && !$(e.target).closest('#pbThirdPartyDropdown').length) {
				$('#pbThirdPartyDropdown').addClass('hidden');
			}
		});

		// Check-in Status dropdown toggle
		$('#pbCheckInStatusBtn').on('click', function(e) {
			e.stopPropagation();
			$('#pbCheckInStatusDropdown').toggleClass('hidden');
		});

		// Select/Deselect all check-in statuses
		$('#pbSelectAllCheckInStatus').on('click', function() {
			$('.pb-checkin-status-checkbox').prop('checked', true);
			updateCheckInStatusLabel();
		});

		$('#pbDeselectAllCheckInStatus').on('click', function() {
			$('.pb-checkin-status-checkbox').prop('checked', false);
			updateCheckInStatusLabel();
		});

		// Update label when checkboxes change
		$(document).on('change', '.pb-checkin-status-checkbox', function() {
			updateCheckInStatusLabel();
		});

		// Close check-in status dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbCheckInStatusBtn').length && !$(e.target).closest('#pbCheckInStatusDropdown').length) {
				$('#pbCheckInStatusDropdown').addClass('hidden');
			}
		});

		// Select/Deselect all payment types
		$('#pbSelectAllPayments').on('click', function() {
			$('.pb-payment-type').prop('checked', true);
		});

		$('#pbDeselectAllPayments').on('click', function() {
			$('.pb-payment-type').prop('checked', false);
		});

		// Clear filters
		$('#pbClearFilters').on('click', function() {
			$('#pbFilterForm')[0].reset();
			// Reset to default dates when clearing
			$('#pbDateRange').val(startOfMonth.format('MM/DD/YYYY') + ' - ' + currentDate.format('MM/DD/YYYY'));
			$('#pbDateFrom').val(startOfMonth.format('YYYY-MM-DD'));
			$('#pbDateTo').val(currentDate.format('YYYY-MM-DD'));
			$('.pb-payment-type').prop('checked', true);
			$('.pb-tour-package-checkbox').prop('checked', false);
			updateTourPackageLabel();
			$('.pb-affiliate-checkbox').prop('checked', false);
			$('#pbExcludeAllAffiliates').val('0');
			updateAffiliateLabel();
			$('.pb-third-party-checkbox').prop('checked', false);
			$('#pbExcludeAllThirdParty').val('0');
			updateThirdPartyLabel();
			$('.pb-checkin-status-checkbox').prop('checked', false);
			updateCheckInStatusLabel();
			// Clear new filters
			$('#pbBookingStatus').val('');
			$('#pbWaiverStatus').val('');
			$('#pbMinAmount').val('');
			$('#pbMaxAmount').val('');
			$('#pbRate').val('');
			$('#pbPermitFee').val('');
			$('#pbTax').val('');
			$('#pbSoftBlocks').val('');
			$('#pbReallocatedFunds').val('');
			$('.pb-charge-type-checkbox').prop('checked', false);
			updateChargeTypeLabel();
		});

		// Form submission
		$('#pbFilterForm').on('submit', function(e) {
			e.preventDefault();
			generatePaymentBreakdownReport();
		});

		// Toggle Additional Filters
		$('#pbToggleAdditionalFilters').on('click', function() {
			const section = $('#pbAdditionalFiltersSection');
			const icon = $('#pbAdditionalFiltersIcon');
			
			section.toggleClass('hidden');
			
			// Rotate icon
			if (section.hasClass('hidden')) {
				icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
			} else {
				icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
			}
		});

		// Export confirm button
		$('#pbConfirmExportBtn').on('click', function() {
			const format = $('#pbExportFormat').val();
			exportPaymentBreakdownReport(format);
		});

		// Show/hide booking details checkbox based on format
		$('#pbExportFormat').on('change', function() {
			const format = $(this).val();
			if (format === 'csv') {
				$('#pbIncludeBookingDetailsWrapper').show();
			} else {
				$('#pbIncludeBookingDetailsWrapper').hide();
				$('#pbIncludeBookingDetails').prop('checked', false);
			}
		});

		// Handle modal close buttons
		$('[data-bs-dismiss="modal"]').on('click', function() {
			$('#pbExportModal').addClass('hidden').css('display', 'none');
		});

		// Show empty state initially
		$('#pbEmptyState').show();

		// Column toggle functionality
		$('.pb-column-toggle').on('change', function() {
			const column = $(this).data('column');
			const isChecked = $(this).is(':checked');

			// Update visibility state
			pbVisibleColumns[column] = isChecked;

			if (isChecked) {
				$(`.pb-col-${column}`).removeClass('hidden');
			} else {
				$(`.pb-col-${column}`).addClass('hidden');
			}
		});

		// Close column dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbColumnDropdown').length && !$(e.target).closest('button').length) {
				$('#pbColumnDropdown').addClass('hidden');
			}
		});

		// Page size change handler
		$('#pbPageSize').on('change', function() {
			pbPageSize = parseInt($(this).val());
			pbCurrentPage = 1;
			renderPaymentBreakdownTable();
		});

		// Table search handler
		$('#pbTableSearch').on('keyup', function() {
			const searchTerm = $(this).val();
			applyTableSearch(searchTerm);
		});

		// Column header click for sorting - use event delegation for reliability
		$('#pbDataTable').on('click', 'thead th[data-sort]', function() {
			const column = $(this).data('sort');
			sortPaymentBreakdownTable(column);
		});

		// ============================================
		// FILTER PRESET EVENT HANDLERS
		// ============================================

		// Load preset list on page load
		FilterPresetManager.loadPresetList()
			.done(function(response) {
				if (response.success && response.data) {
					FilterPresetManager.updatePresetDropdown(response.data);
				}
			})
			.fail(function(xhr) {
				console.error('Failed to load preset list:', xhr);
			});

		// Save Preset Button - Show modal
		$('#pbSavePresetBtn').on('click', function() {
			$('#pbPresetNameInput').val('');
			$('#pbSavePresetModal')
				.removeClass('hidden')
				.css('display', 'flex')
				.attr('aria-hidden', 'false');
			// Focus on input for better UX
			setTimeout(() => $('#pbPresetNameInput').focus(), 100);
		});

		// Cancel Save Button - Hide modal
		$('#pbCancelSaveBtn, #pbCloseSaveModalBtn').on('click', function() {
			$('#pbSavePresetModal')
				.addClass('hidden')
				.css('display', 'none')
				.attr('aria-hidden', 'true');
		});

		// Confirm Save Button - Save preset
		$('#pbConfirmSaveBtn').on('click', function() {
			const presetName = $('#pbPresetNameInput').val().trim();

			if (!presetName) {
				swal('Please enter a preset name', '', 'warning');
				return;
			}

			// Capture current filter state
			const filters = FilterPresetManager.captureFilterState();

			// Save preset
			FilterPresetManager.savePreset(presetName, filters)
				.done(function(response) {
					if (response.success) {
						$('#pbSavePresetModal')
							.addClass('hidden')
							.css('display', 'none')
							.attr('aria-hidden', 'true');
						swal('Success', 'Filter preset saved successfully', 'success');
						
						// Refresh preset list
						FilterPresetManager.loadPresetList()
							.done(function(listResponse) {
								if (listResponse.success && listResponse.data) {
									FilterPresetManager.updatePresetDropdown(listResponse.data);
								}
							});
					} else {
						swal('Error', response.message || 'Failed to save preset', 'error');
					}
				})
				.fail(function(xhr) {
					// Handle 409 Conflict (duplicate name)
					if (xhr.status === 409) {
						// Show overwrite confirmation
						$('#pbSavePresetModal')
							.addClass('hidden')
							.css('display', 'none')
							.attr('aria-hidden', 'true');
						$('#pbConfirmOverwriteModal')
							.removeClass('hidden')
							.css('display', 'flex')
							.attr('aria-hidden', 'false');
					}
					// Handle 403 Permission Denied
					else if (xhr.status === 403) {
						const response = xhr.responseJSON;
						const message = response?.message || 'User does not have the right permissions to save filter presets.';
						swal('Permission Denied', message, 'error');
					}
					// Handle 401 Unauthorized
					else if (xhr.status === 401) {
						swal('Unauthorized', 'Please log in to save filter presets.', 'error');
					}
					// Handle 422 Validation Error
					else if (xhr.status === 422) {
						const response = xhr.responseJSON;
						const message = response?.message || 'Invalid preset data';
						swal('Validation Error', message, 'error');
					}
					// Handle 400 Bad Request (preset limit)
					else if (xhr.status === 400) {
						const response = xhr.responseJSON;
						if (response?.message?.includes('maximum')) {
							swal('Limit Reached', 'You have reached the maximum of 50 presets. Please delete some presets before creating new ones.', 'warning');
						} else {
							swal('Error', response?.message || 'Failed to save preset', 'error');
						}
					}
					// Handle other errors
					else {
						const response = xhr.responseJSON;
						const message = response?.message || 'Failed to save preset. Please try again.';
						swal('Error', message, 'error');
					}
				});
		});

		// Cancel Overwrite Button - Hide modal
		$('#pbCancelOverwriteBtn, #pbCloseOverwriteModalBtn').on('click', function() {
			$('#pbConfirmOverwriteModal')
				.addClass('hidden')
				.css('display', 'none')
				.attr('aria-hidden', 'true');
			$('#pbSavePresetModal')
				.removeClass('hidden')
				.css('display', 'flex')
				.attr('aria-hidden', 'false');
			// Focus back on input
			setTimeout(() => $('#pbPresetNameInput').focus(), 100);
		});

		// Confirm Overwrite Button - Update existing preset
		$('#pbConfirmOverwriteBtn').on('click', function() {
			const presetName = $('#pbPresetNameInput').val().trim();
			const filters = FilterPresetManager.captureFilterState();

			// Find the preset ID by name
			FilterPresetManager.loadPresetList()
				.done(function(listResponse) {
					if (listResponse.success && listResponse.data) {
						const existingPreset = listResponse.data.find(p => p.name === presetName);
						
						if (existingPreset) {
							// Update the existing preset
							$.ajax({
								url: `${FilterPresetManager.apiUrl}/${existingPreset.id}`,
								method: 'PUT',
								data: {
									name: presetName,
									filters: filters,
									_token: $('meta[name="csrf-token"]').attr('content')
								},
								dataType: 'json'
							})
							.done(function(response) {
								if (response.success) {
									$('#pbConfirmOverwriteModal')
										.addClass('hidden')
										.css('display', 'none')
										.attr('aria-hidden', 'true');
									swal('Success', 'Filter preset updated successfully', 'success');
									
									// Refresh preset list
									FilterPresetManager.loadPresetList()
										.done(function(refreshResponse) {
											if (refreshResponse.success && refreshResponse.data) {
												FilterPresetManager.updatePresetDropdown(refreshResponse.data);
											}
										});
								} else {
									swal('Error', response.message || 'Failed to update preset', 'error');
								}
							})
							.fail(function(xhr) {
								// Handle 403 Permission Denied
								if (xhr.status === 403) {
									const response = xhr.responseJSON;
									const message = response?.message || 'User does not have the right permissions to update filter presets.';
									swal('Permission Denied', message, 'error');
								}
								// Handle 401 Unauthorized
								else if (xhr.status === 401) {
									swal('Unauthorized', 'Please log in to update filter presets.', 'error');
								}
								// Handle other errors
								else {
									const response = xhr.responseJSON;
									const message = response?.message || 'Failed to update preset. Please try again.';
									swal('Error', message, 'error');
								}
							});
						}
					}
				});
		});

		// Load Preset Button - Toggle dropdown
		$('#pbLoadPresetBtn').on('click', function(e) {
			e.stopPropagation();
			$('#pbPresetDropdown').toggleClass('hidden');
			
			// Refresh preset list when opening
			if (!$('#pbPresetDropdown').hasClass('hidden')) {
				FilterPresetManager.loadPresetList()
					.done(function(response) {
						if (response.success && response.data) {
							FilterPresetManager.updatePresetDropdown(response.data);
						}
					});
			}
		});

		// Close preset dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('#pbLoadPresetBtn').length && !$(e.target).closest('#pbPresetDropdown').length) {
				$('#pbPresetDropdown').addClass('hidden');
			}
		});

		// Load Preset - Click handler (delegated)
		$(document).on('click', '.preset-load', function(e) {
			e.preventDefault();
			const presetId = $(this).data('id');

			FilterPresetManager.loadPreset(presetId)
				.done(function(response) {
					if (response.success && response.data) {
						FilterPresetManager.applyFilterState(response.data.filters);
						$('#pbPresetDropdown').addClass('hidden');
						swal('Success', `Preset "${response.data.name}" loaded successfully`, 'success');
					} else {
						swal('Error', 'Failed to load preset', 'error');
					}
				})
				.fail(function() {
					swal('Error', 'Failed to load preset. Please try again.', 'error');
				});
		});

		// Delete Preset - Click handler (delegated)
		$(document).on('click', '.preset-delete', function(e) {
			e.preventDefault();
			e.stopPropagation();
			const presetId = $(this).data('id');

			swal({
				title: 'Delete Preset?',
				text: 'Are you sure you want to delete this preset? This action cannot be undone.',
				icon: 'warning',
				buttons: {
					cancel: {
						text: 'Cancel',
						visible: true,
						closeModal: true
					},
					confirm: {
						text: 'Delete',
						closeModal: false
					}
				},
				dangerMode: true
			})
			.then((willDelete) => {
				if (willDelete) {
					FilterPresetManager.deletePreset(presetId)
						.done(function(response) {
							if (response.success) {
								swal('Deleted', 'Preset deleted successfully', 'success');
								
								// Refresh preset list
								FilterPresetManager.loadPresetList()
									.done(function(listResponse) {
										if (listResponse.success && listResponse.data) {
											FilterPresetManager.updatePresetDropdown(listResponse.data);
										}
									});
							} else {
								swal('Error', response.message || 'Failed to delete preset', 'error');
							}
						})
						.fail(function(xhr) {
							// Handle 403 Permission Denied
							if (xhr.status === 403) {
								const response = xhr.responseJSON;
								const message = response?.message || 'User does not have the right permissions to delete filter presets.';
								swal('Permission Denied', message, 'error');
							}
							// Handle 401 Unauthorized
							else if (xhr.status === 401) {
								swal('Unauthorized', 'Please log in to delete filter presets.', 'error');
							}
							// Handle other errors
							else {
								const response = xhr.responseJSON;
								const message = response?.message || 'Failed to delete preset. Please try again.';
								swal('Error', message, 'error');
							}
						});
				}
			});
		});

		// Close modals when clicking outside
		$('#pbSavePresetModal, #pbConfirmOverwriteModal').on('click', function(e) {
			if ($(e.target).is('#pbSavePresetModal') || $(e.target).is('#pbConfirmOverwriteModal')) {
				$(this)
					.addClass('hidden')
					.css('display', 'none')
					.attr('aria-hidden', 'true');
			}
		});

		// Allow Enter key to save preset
		$('#pbPresetNameInput').on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#pbConfirmSaveBtn').click();
			}
		});
	}

	// Generate Payment Breakdown Report
	function generatePaymentBreakdownReport() {
		// Validate inputs
		const dateFrom = $('#pbDateFrom').val();
		const dateTo = $('#pbDateTo').val();

		if (!dateFrom || !dateTo) {
			swal('Please select a date range', '', 'warning');
			return;
		}

		// Get selected payment types
		const paymentTypes = [];
		$('.pb-payment-type:checked').each(function() {
			paymentTypes.push($(this).val());
		});

		if (paymentTypes.length === 0) {
			swal('Please select at least one payment type', '', 'warning');
			return;
		}

		// Get selected tour packages
		const tourPackages = [];
		$('.pb-tour-package-checkbox:checked').each(function() {
			tourPackages.push($(this).val());
		});

		// Get selected affiliates (only if affiliates exist)
		const hasAffiliates = window.pbAffiliates && window.pbAffiliates.length > 0;
		const affiliates = [];
		if (hasAffiliates) {
			$('.pb-affiliate-checkbox:checked').each(function() {
				affiliates.push($(this).val());
			});
		}

		const excludeAllAffiliates = hasAffiliates ? ($('#pbExcludeAllAffiliates').val() === '1') : false;

		// Get selected check-in statuses
		const checkInStatuses = [];
		$('.pb-checkin-status-checkbox:checked').each(function() {
			checkInStatuses.push($(this).val());
		});

		// Get selected third party platforms (only if enabled)
		const hasEnabledPlatforms = window.pbEnabledThirdPartyPlatforms && window.pbEnabledThirdPartyPlatforms.length > 0;
		const thirdPartyPlatforms = [];
		if (hasEnabledPlatforms) {
			$('.pb-third-party-checkbox:checked').each(function() {
				thirdPartyPlatforms.push($(this).val());
			});
		}

		const excludeAllThirdParty = hasEnabledPlatforms ? ($('#pbExcludeAllThirdParty').val() === '1') : false;

		// Get selected charge types
		const chargeTypes = [];
		$('.pb-charge-type-checkbox:checked').each(function() {
			chargeTypes.push($(this).val());
		});

		// Prepare request data
		const requestData = {
			tourOperator: $('#tour_operator').val() || window.pbTourOperatorId || 0,
			tourPackages: tourPackages.length > 0 ? tourPackages : [],
			affiliates: hasAffiliates && affiliates.length > 0 ? affiliates : [],
			excludeAllAffiliates: excludeAllAffiliates ? 1 : 0,
			dateFrom: dateFrom,
			dateTo: dateTo,
			paymentTypes: paymentTypes,
			viewBy: $('#pbViewBy').val(),
			bookingStatus: $('#pbBookingStatus').val() || '',
			checkInStatuses: checkInStatuses.length > 0 ? checkInStatuses : [],
			waiverStatus: $('#pbWaiverStatus').val() || '',
			thirdPartyPlatforms: hasEnabledPlatforms && thirdPartyPlatforms.length > 0 ? thirdPartyPlatforms : [],
			excludeAllThirdParty: excludeAllThirdParty ? 1 : 0,
			minAmount: $('#pbMinAmount').val() || '',
			maxAmount: $('#pbMaxAmount').val() || '',
			rate: $('#pbRate').val() || '',
			permitFee: $('#pbPermitFee').val() || '',
			tax: $('#pbTax').val() || '',
			softBlocks: $('#pbSoftBlocks').val() || '',
			reallocatedFunds: $('#pbReallocatedFunds').val() || '',
			chargeTypes: chargeTypes.length > 0 ? chargeTypes : []
		};

		// Show loading
		$('#pbLoadingIndicator').show();
		$('#pbEmptyState').hide();
		$('#pbResultsContainer').hide();

		// Make AJAX request
		$.ajax({
			url: `/${routePrefix}/report/payment-breakdown-report/data`,
			method: 'GET',
			data: requestData,
			success: function(response) {
				$('#pbLoadingIndicator').hide();

				if (response.success) {
					updatePaymentBreakdownDisplay(response);
				} else {
					swal('Error', response.message || 'Failed to generate report', 'error');
					$('#pbEmptyState').show();
				}
			},
			error: function(xhr) {
				$('#pbLoadingIndicator').hide();
				const errorMsg = xhr.responseJSON?.message || 'An error occurred while generating the report';
				swal('Error', errorMsg, 'error');
				$('#pbEmptyState').show();
			}
		});
	}

	// Update Payment Breakdown Display
	function updatePaymentBreakdownDisplay(response) {
		// Get currency symbol from global currencyFormation or default to $
		const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';

		// Helper function to format currency with exactly 2 decimal places and thousand separators
		const formatCurrency = (value) => {
			return currencySymbol + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		};

		// Update summary cards
		$('#pbTotalBookings').text(response.summary?.totalBookings || 0);
		$('#pbTotalPax').text(response.summary?.totalPax || 0);
		$('#pbTotalRevenue').text(formatCurrency(response.summary?.totalRevenue));
		$('#pbTotalRefunds').text(formatCurrency(response.summary?.totalRefunds));
		$('#pbTotalFees').text(formatCurrency(response.summary?.totalFees));
		$('#pbTotalDiscount').text(formatCurrency(response.summary?.totalDiscount));
		const totalDisputeReversals = disputeReversalEnabled ? parseFloat(response.summary?.totalDisputeReversals || 0) : 0;
		if (disputeReversalEnabled) {
			$('#pbTotalDisputes').text(formatCurrency(totalDisputeReversals));
		}

		const netAfterDisputes = (response.summary?.netRevenue || 0) - totalDisputeReversals;
		$('#pbTotalNet').text(formatCurrency(netAfterDisputes));

		// Update check-in status from checkInData
		const checkInData = response.checkInData || {};

		// Check-in data might be objects with count/pax properties, or simple numbers
		// We want to display PAX count, not booking count
		const getCheckInValue = (data) => {
			if (typeof data === 'number') return data;
			if (typeof data === 'object' && data !== null) {
				// Return pax count (passenger count), not booking count
				return data.pax || data.total || data.value || 0;
			}
			return 0;
		};

		// Format numbers with thousand separators
		const formatNumber = (value) => parseInt(value || 0).toLocaleString();

		$('#pbCheckedIn2nd').text(formatNumber(getCheckInValue(checkInData['2nd Check-in'])));
		$('#pbCheckedIn1st').text(formatNumber(getCheckInValue(checkInData['1st Check-in'])));
		$('#pbNotCheckedIn').text(formatNumber(getCheckInValue(checkInData['Not Check-In'])));
		$('#pbAbsent').text(formatNumber(getCheckInValue(checkInData['Absent'])));

		// Update table with booking details
		updatePaymentBreakdownTable(response.data || []);

		// Store booking data for modals
		storeModalBookingsData(response.data || []);
		disputeReversalsData = response.disputeReversals || [];

		// Update summary breakdown details with backend payment type breakdown
		updateSummaryBreakdownDetails(response.data || [], response.paymentTypeBreakdown || {}, response.summary || {});

		// Update footer totals
		$('#pbFooterPayment').text(formatCurrency(response.summary?.totalRevenue));
		$('#pbFooterUnpaid').text(formatCurrency(response.summary?.totalUnpaid));
		$('#pbFooterFees').text(formatCurrency(response.summary?.totalFees));
		$('#pbFooterRefund').text(formatCurrency(response.summary?.totalRefunds));
		$('#pbFooterDiscount').text(formatCurrency(response.summary?.totalDiscount));
		$('#pbFooterNet').text(formatCurrency(netAfterDisputes));

		// Show results container and hide empty state
		$('#pbResultsContainer').show();
		$('#pbEmptyState').hide();

		// Populate report header
		populateReportHeader();

		// Initialize or update charts
		initializePaymentBreakdownCharts(response);
	}

	// Populate Report Header with date range, filters, and timestamp
	function populateReportHeader() {
		const dateFrom = $('#pbDateFrom').val();
		const dateTo = $('#pbDateTo').val();
		const viewBy = $('#pbViewBy').val();
		
		// Format date range
		if (dateFrom && dateTo) {
			const startDate = moment(dateFrom).format('MMM D, YYYY');
			const endDate = moment(dateTo).format('MMM D, YYYY');
			const viewByLabel = viewBy === 'tourdate' ? 'Tour Date' : (viewBy === 'createddate' ? 'Created Date' : 'Purchase Date');
			$('#pbReportDateRange').html(`<i class="fas fa-calendar-alt mr-1"></i> ${startDate} - ${endDate} <span class="text-gray-400 mx-2">|</span> View by: ${viewByLabel}`);
		}
		
		// Set generated timestamp
		const now = moment().format('MMM D, YYYY h:mm A');
		$('#pbReportGeneratedAt').html(`<i class="fas fa-clock mr-1"></i> Generated: ${now}`);
		
		// Build applied filters summary
		const filters = [];
		
		// Tour Packages
		const selectedPackages = $('.pb-tour-package-checkbox:checked');
		const totalPackages = $('.pb-tour-package-checkbox').length;
		if (selectedPackages.length > 0 && selectedPackages.length < totalPackages) {
			if (selectedPackages.length === 1) {
				filters.push(`Package: ${selectedPackages.first().data('name')}`);
			} else {
				filters.push(`Packages: ${selectedPackages.length} selected`);
			}
		}
		
		// Affiliates
		const hasAffiliates = window.pbAffiliates && window.pbAffiliates.length > 0;
		if (hasAffiliates) {
			const excludeAllAffiliates = $('#pbExcludeAllAffiliates').val() === '1';
			if (excludeAllAffiliates) {
				filters.push('Affiliates: Excluded');
			} else {
				const selectedAffiliates = $('.pb-affiliate-checkbox:checked');
				const totalAffiliates = $('.pb-affiliate-checkbox').length;
				if (selectedAffiliates.length > 0 && selectedAffiliates.length < totalAffiliates) {
					if (selectedAffiliates.length === 1) {
						filters.push(`Affiliate: ${selectedAffiliates.first().data('name')}`);
					} else {
						filters.push(`Affiliates: ${selectedAffiliates.length} selected`);
					}
				}
			}
		}
		
		// Third Party Platforms
		const hasEnabledPlatforms = window.pbEnabledThirdPartyPlatforms && window.pbEnabledThirdPartyPlatforms.length > 0;
		if (hasEnabledPlatforms) {
			const excludeAllThirdParty = $('#pbExcludeAllThirdParty').val() === '1';
			if (excludeAllThirdParty) {
				filters.push('Third Party: Excluded');
			} else {
				const selectedPlatforms = $('.pb-third-party-checkbox:checked');
				const totalPlatforms = $('.pb-third-party-checkbox').length;
				if (selectedPlatforms.length > 0 && selectedPlatforms.length < totalPlatforms) {
					filters.push(`Third Party: ${selectedPlatforms.length} selected`);
				}
			}
		}
		
		// Payment Types
		const selectedPaymentTypes = $('.pb-payment-type:checked');
		const totalPaymentTypes = $('.pb-payment-type').length;
		if (selectedPaymentTypes.length > 0 && selectedPaymentTypes.length < totalPaymentTypes) {
			filters.push(`Payment Types: ${selectedPaymentTypes.length} selected`);
		}
		
		// Booking Status
		const bookingStatus = $('#pbBookingStatus').val();
		if (bookingStatus) {
			filters.push(`Status: ${bookingStatus}`);
		}
		
		// Display filters
		if (filters.length > 0) {
			$('#pbAppliedFilters').html('<i class="fas fa-filter mr-1"></i> <strong>Filters:</strong> ' + filters.join(' <span class="text-gray-300">|</span> '));
		} else {
			$('#pbAppliedFilters').html('<i class="fas fa-filter mr-1"></i> <strong>Filters:</strong> None (showing all data)');
		}
	}

	// ============================================
	// SUMMARY BREAKDOWN FUNCTIONS
	// ============================================

	// Update Summary Breakdown Details - Aggregate by payment type and rate_id
	function updateSummaryBreakdownDetails(bookings, backendPaymentTypeBreakdown, periodSummary) {
		// Get currency symbol from global currencyFormation or default to $
		const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';

		const formatCurrency = (value) => currencySymbol + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		const formatNumber = (value) => parseInt(value || 0).toLocaleString();

		// Validate backend data exists or if no bookings, reset all values to $0
		const hasPaymentTypeData = backendPaymentTypeBreakdown && typeof backendPaymentTypeBreakdown === 'object' && Object.keys(backendPaymentTypeBreakdown).length > 0;
		if (!hasPaymentTypeData || !bookings || bookings.length === 0) {
			// Reset Financial Summary to $0
			$('#pbFinancialSubtotal').text(formatCurrency(0));
			$('#pbFinancialTax').text(formatCurrency(0));
			$('#pbFinancialPermit').text(formatCurrency(0));
			$('#pbFinancialGrossTotal').text(formatCurrency(0));
			$('#pbFinancialCustomCharge').text(formatCurrency(0));
			$('#pbFinancialPriorPayments').text(formatCurrency(0));
			$('#pbFinancialRefunded').text(formatCurrency(0));
			$('#pbFinancialUnpaid').text(formatCurrency(0));
			$('#pbFinancialOverpayment').text(formatCurrency(0));
			$('#pbFinancialOtherPeriodPayments').text(formatCurrency(0));
			$('#pbFinancialDiscount').text(formatCurrency(0));
			$('#pbFinancialGrandTotal').text(formatCurrency(0));

			// Reset Tax Information to 0
			$('#pbTaxRate').text('0.0%');
			$('#pbTaxableAmount').text(formatCurrency(0));
			$('#pbTotalTaxCollected').text(formatCurrency(0));

			// Reset Permit Fee Information to 0
			$('#pbPermitFeePerPax').text(formatCurrency(0));
			$('#pbTotalPaxWithPermit').text(formatNumber(0));
			$('#pbTotalPermitFees').text(formatCurrency(0));

			// Reset Total PAX count
			$('#pbTotalPax').text(formatNumber(0));

			// Clear By Payment Type table
			$('#pbFinancialByPaymentType').empty();

			// Hide all payment type sections
			$('#pbSummaryCardSection').hide();
			$('#pbSummaryBillableSection').hide();
			$('#pbSummaryInvoicedSection').hide();
			$('#pbSummaryCashSection').hide();
			$('#pbSummaryHeadoutSection').hide();
			$('#pbSummaryTripAdvisorSection').hide();
			$('#pbSummaryGetYourGuideSection').hide();
			$('#pbSummaryFareHarborSection').hide();
			$('#pbSummaryUnpaidSection').hide();

			if (!hasPaymentTypeData) {
				console.error('Backend payment type breakdown data is missing or empty');
			}
			return;
		}

		// Calculate financial summary values from backend payment type breakdown
		let grandTotalSubtotal = 0;
		let grandTotalTax = 0;
		let grandTotalPermit = 0;
		let grandTotalAmount = 0;
		let totalPaxAllTypes = 0;
		let grandTotalPaxWithPermit = 0;
		let grandTotalDiscount = 0;

		// Calculate discount from bookings data
		bookings.forEach(booking => {
			const discount = parseFloat(booking.discount || 0);
			grandTotalDiscount += discount;
		});

		// Aggregate totals from backend payment type breakdown
		// Include ALL payment types selected by the user
		Object.keys(backendPaymentTypeBreakdown).forEach(paymentType => {
			const typeData = backendPaymentTypeBreakdown[paymentType];

			// Add to total PAX (all payment types)
			totalPaxAllTypes += typeData.totalPax || 0;

			// Include all payment types in gross totals
			grandTotalSubtotal += typeData.totalRate || 0;
			grandTotalTax += typeData.totalTax || 0;
			grandTotalAmount += typeData.totalAmount || 0;
		});

		// Calculate permit fee from bookings data to properly exclude absent pax.
		// Only count pax from paid bookings — entirely unpaid bookings (no payment
		// collected) and their pax are excluded from permit fee totals.
		bookings.forEach(booking => {
			if ((booking.purchase_type || 'Unpaid') === 'Unpaid') return;

			const rateBreakdown = booking.rate_breakdown || [];
			const checkInDetails = booking.check_in_details || {};
			const rateStatusBreakdown = checkInDetails.rate_status_breakdown || {};
			const bookingStatusBreakdown = checkInDetails.status_breakdown || {};
			const totalPax = parseInt(booking.total_pax || 0);

			// Process each rate group using per-rate-group status breakdown
			rateBreakdown.forEach(rateGroup => {
				const pax = parseInt(rateGroup.pax || 0);
				let permitFee = parseFloat(rateGroup.permit_fee || 0);
				const rateFor = rateGroup.rate_for || '';
				const rateId = rateGroup.rate_id;

				if (pax <= 0) return;

				// Apply Dixies-specific logic: children rate groups have $0 permit fee
				if (pbTenantId === 'dixies' && rateFor.toLowerCase().includes('children')) {
					permitFee = 0;
				}

				if (permitFee <= 0) return;

				// Use per-rate-group absent count when available, fall back to proportional estimate
				let nonAbsentPaxForGroup;
				const perRateBreakdown = (rateId && rateStatusBreakdown[rateId]) ? rateStatusBreakdown[rateId] : null;
				if (perRateBreakdown) {
					const absentForRate = parseInt(perRateBreakdown['Absent'] || 0);
					nonAbsentPaxForGroup = pax - absentForRate;
				} else {
					let totalStatusPax = 0;
					Object.values(bookingStatusBreakdown).forEach(count => {
						totalStatusPax += parseInt(count || 0);
					});
					const absentCount = parseInt(bookingStatusBreakdown['Absent'] || 0);
					const nonAbsentRatio = totalStatusPax > 0 ? (totalStatusPax - absentCount) / totalStatusPax : 1;
					nonAbsentPaxForGroup = Math.round(pax * nonAbsentRatio);
				}

				if (nonAbsentPaxForGroup > 0) {
					grandTotalPermit += nonAbsentPaxForGroup * permitFee;
					grandTotalPaxWithPermit += nonAbsentPaxForGroup;
				}
			});
		});

		// Calculate net totals from bookings
		let netTotalFromAllBookings = 0;
		let totalRefundedAmount = 0;
		let totalUnpaidAmount = 0;
		let totalCustomChargeAmount = 0;
		let totalPriorPaymentsAmount = 0;
		let totalOverpaymentAmount = 0;
		let totalOtherPeriodPayments = 0;

		bookings.forEach(booking => {
			const netTotal = parseFloat(booking.net_total || 0);
			const rate = parseFloat(booking.rate || 0);
			const tax = parseFloat(booking.tax || 0);
			const permitFee = parseFloat(booking.permit_fee || 0);
			const discount = parseFloat(booking.discount || 0);
			const gross = rate + tax + permitFee;
			// What customer actually owes after discount
			const actualOwed = gross - discount;
			const paymentType = booking.purchase_type || '';
			const refundAmount = parseFloat(booking.refund_amount || 0);
			const amount = parseFloat(booking.amount || 0);
			const feeAmount = parseFloat(booking.fee_amount || 0);

			netTotalFromAllBookings += netTotal;

			if (paymentType === 'Unpaid') {
				// Only count as unpaid if they owe more than they paid (after discount)
				const unpaidAmount = Math.max(0, actualOwed - netTotal);
				totalUnpaidAmount += unpaidAmount;
			} else {
				const hasCustomCharge = booking.has_custom_charge || false;

				// Custom Charge vs Overpayment: both have net > actualOwed
				// This means we collected more than what the customer owed
				if (netTotal > actualOwed + 0.01) {
					const gainAmount = netTotal - actualOwed;

					// True Custom Charge: explicitly marked as custom_charge
					if (hasCustomCharge) {
						totalCustomChargeAmount += gainAmount;
					} else {
						// Overpayment: net > actualOwed but not a custom charge
						totalOverpaymentAmount += gainAmount;
					}
				}

				// Refunded: only count refunds that actually reduced revenue below what was owed
				// If net >= actualOwed, the refund was just returning an overpayment
				if (refundAmount > 0.01 && netTotal < actualOwed - 0.01) {
					// The actual revenue reduction is: actualOwed - net (capped at refund amount)
					const revenueReduction = Math.min(refundAmount, actualOwed - netTotal);
					totalRefundedAmount += revenueReduction;
				}

				// Prior Payments: bookings where payment was made before the date range
				// These have Amount = 0 AND net_total < 0 (refund issued)
				if (amount < 0.01 && netTotal < -0.01) {
					totalPriorPaymentsAmount += actualOwed;
				}

				// Other Period Payments: bookings where net < actualOwed but not due to refund or unpaid
				// This happens when payment was split across periods (purchase date view)
				// The gap represents payments made in other periods
				if (amount > 0.01 && netTotal < actualOwed - 0.01) {
					// Calculate what's already accounted for
					let accountedLoss = 0;
					if (refundAmount > 0.01) {
						accountedLoss = Math.min(refundAmount, actualOwed - netTotal);
					}
					// The remaining gap is payments in other periods
					const otherPeriodGap = actualOwed - netTotal - accountedLoss;
					if (otherPeriodGap > 0.01) {
						totalOtherPeriodPayments += otherPeriodGap;
					}
				}
			}
		});

		// Formula: Net = Gross - Discount + CustomCharge + Overpayment - PriorPayments - Refunded - Unpaid - OtherPeriodPayments

		// Render each payment type section using backend data
		renderPaymentTypeSection('Card', backendPaymentTypeBreakdown.Card, 'pbSummaryCard');
		renderPaymentTypeSection('Billable', backendPaymentTypeBreakdown.Billable, 'pbSummaryBillable');
		renderPaymentTypeSection('Invoiced', backendPaymentTypeBreakdown.Invoiced, 'pbSummaryInvoiced');
		renderPaymentTypeSection('Cash', backendPaymentTypeBreakdown.Cash, 'pbSummaryCash');
		renderPaymentTypeSection('Headout', backendPaymentTypeBreakdown.Headout, 'pbSummaryHeadout');
		renderPaymentTypeSection('TripAdvisor', backendPaymentTypeBreakdown.TripAdvisor, 'pbSummaryTripAdvisor');
		renderPaymentTypeSection('GetYourGuide', backendPaymentTypeBreakdown.GetYourGuide, 'pbSummaryGetYourGuide');
		renderPaymentTypeSection('FareHarbor', backendPaymentTypeBreakdown.FareHarbor, 'pbSummaryFareHarbor');
		renderPaymentTypeSection('Unpaid', backendPaymentTypeBreakdown.Unpaid, 'pbSummaryUnpaid');

		// Update Financial Summary with separated values
		$('#pbFinancialSubtotal').text(formatCurrency(grandTotalSubtotal));
		$('#pbFinancialTax').text(formatCurrency(grandTotalTax));
		$('#pbFinancialPermit').text(formatCurrency(grandTotalPermit));
		$('#pbFinancialGrossTotal').text(formatCurrency(grandTotalAmount));
		$('#pbFinancialCustomCharge').text(formatCurrency(totalCustomChargeAmount));
		$('#pbFinancialOverpayment').text(formatCurrency(totalOverpaymentAmount));
		$('#pbFinancialPriorPayments').text(formatCurrency(totalPriorPaymentsAmount));
		$('#pbFinancialRefunded').text(formatCurrency(totalRefundedAmount));
		$('#pbFinancialUnpaid').text(formatCurrency(totalUnpaidAmount));
		$('#pbFinancialOtherPeriodPayments').text(formatCurrency(totalOtherPeriodPayments));
		$('#pbFinancialDiscount').text(formatCurrency(grandTotalDiscount));
		const periodDisputeReversals = parseFloat(periodSummary?.totalDisputeReversals || 0);
		$('#pbFinancialDisputeReversals').text(formatCurrency(periodDisputeReversals));
		if (periodDisputeReversals > 0) {
			$('#pbFinancialDisputeReversalsRow').show();
		} else {
			$('#pbFinancialDisputeReversalsRow').hide();
		}

		$('#pbFinancialGrandTotal').text(formatCurrency(netTotalFromAllBookings - periodDisputeReversals));

		// Update By Payment Type table using backend data
		renderFinancialByPaymentType(backendPaymentTypeBreakdown, periodDisputeReversals);

		// Update total PAX count
		$('#pbTotalPax').text(formatNumber(totalPaxAllTypes));

		// Calculate tax information
		const taxRate = grandTotalSubtotal > 0 ? (grandTotalTax / grandTotalSubtotal * 100) : 0;
		$('#pbTaxRate').text(taxRate.toFixed(1) + '%');
		$('#pbTaxableAmount').text(formatCurrency(grandTotalSubtotal));
		$('#pbTotalTaxCollected').text(formatCurrency(grandTotalTax));

		// Calculate permit fee information
		const permitFeePerPax = grandTotalPaxWithPermit > 0 ? (grandTotalPermit / grandTotalPaxWithPermit) : 0;
		$('#pbPermitFeePerPax').text(formatCurrency(permitFeePerPax));
		$('#pbTotalPaxWithPermit').text(formatNumber(grandTotalPaxWithPermit));
		$('#pbTotalPermitFees').text(formatCurrency(grandTotalPermit));
	}

	// Render Payment Type Section (Card, Cash, Billable, Unpaid) with Accordion
	function renderPaymentTypeSection(paymentType, data, tablePrefix) {
		// Get currency symbol from global currencyFormation or default to $
		const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';

		const formatCurrency = (value) => currencySymbol + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		const formatNumber = (value) => parseInt(value || 0).toLocaleString();

		const section = $(`#${tablePrefix}Section`);
		const tbody = $(`#${tablePrefix}Body`);

		// Hide section if no data
		if (!data || (!data.rateCategories && Object.keys(data.rateGroups || {}).length === 0)) {
			section.hide();
			return;
		}

		// Show section
		const sectionElement = document.getElementById(`${tablePrefix}Section`);
		if (sectionElement) {
			sectionElement.style.display = 'block';
		}

		tbody.empty();

		// Use rateCategories if available (new accordion structure), otherwise fall back to rateGroups
		if (data.rateCategories && Object.keys(data.rateCategories).length > 0) {
			// Render accordion structure

			// Sort categories by mapping_key (ascending)
			const sortedCategories = Object.keys(data.rateCategories).sort((a, b) => {
				const catA = data.rateCategories[a];
				const catB = data.rateCategories[b];

				// Sort by mapping_key (0 = Default comes first, then numeric keys)
				const mappingKeyA = catA.mappingKey || 0;
				const mappingKeyB = catB.mappingKey || 0;

				// If both are 0 or both are non-zero, sort by category name
				if ((mappingKeyA === 0 && mappingKeyB === 0) || (mappingKeyA !== 0 && mappingKeyB !== 0)) {
					return catA.categoryName.localeCompare(catB.categoryName);
				}

				return mappingKeyA - mappingKeyB;
			});

			// Render each category as an accordion
			sortedCategories.forEach(categoryKey => {
				const category = data.rateCategories[categoryKey];
				const accordionId = `${tablePrefix}_accordion_${categoryKey}`;

				// Accordion Header Row
				const headerRow = `
					<tr class="accordion-header-row ${tablePrefix}-accordion-header" data-accordion-id="${accordionId}">
						<td colspan="6" class="px-3 py-2">
							<div class="flex items-center justify-between">
								<div class="flex items-center gap-2">
									<i class="fas fa-chevron-right accordion-icon text-gray-600" style="font-size: 0.7rem;"></i>
									<span class="font-semibold text-gray-800">${category.categoryName}</span>
								</div>
								<div class="flex items-center gap-4">
									<span class="text-gray-700"><strong>${formatNumber(category.totalPax)}</strong> tourists</span>
									<span class="font-bold text-gray-900">${formatCurrency(category.totalAmount)}</span>
								</div>
							</div>
						</td>
					</tr>
				`;
				tbody.append(headerRow);

				// Accordion Content (initially hidden)
				// Backend now ensures no duplicates, so we can directly render rate groups
				const contentRows = category.rateGroups.map(rateGroup => {
					// Get the actual rate group name from rateGroup data
					const rateGroupName = rateGroup.rateFor || category.categoryName;

					return `
						<tr class="accordion-content-row hidden" data-accordion-id="${accordionId}">
							<td class="px-3 py-2 text-left text-gray-700">${rateGroupName}</td>
							<td class="px-3 py-2 text-center"><strong class="text-gray-900">${formatNumber(rateGroup.pax)}</strong></td>
							<td class="px-3 py-2 text-right text-gray-700">${formatCurrency(rateGroup.perPersonRate)}</td>
							<td class="px-3 py-2 text-right text-gray-700">${formatCurrency(rateGroup.perPersonTax)}</td>
							<td class="px-3 py-2 text-right text-gray-700">${formatCurrency(rateGroup.perPersonPermitFee)}</td>
							<td class="px-3 py-2 text-right font-semibold text-gray-900">${formatCurrency(rateGroup.totalAmount)}</td>
						</tr>
					`;
				}).join('');

				tbody.append(contentRows);
			});

			// Add click handlers for accordion toggle
			tbody.find('.accordion-header-row').on('click', function() {
				const accordionId = $(this).data('accordion-id');
				const icon = $(this).find('.accordion-icon');
				const contentRows = tbody.find(`.accordion-content-row[data-accordion-id="${accordionId}"]`);

				// Toggle visibility
				contentRows.toggleClass('hidden');

				// Rotate icon
				if (contentRows.hasClass('hidden')) {
					icon.removeClass('fa-chevron-down').addClass('fa-chevron-right');
				} else {
					icon.removeClass('fa-chevron-right').addClass('fa-chevron-down');
				}
			});
		} else {
			// FALLBACK: Render flat structure (backward compatibility)
			// Sort rate groups by mapping_key (ascending), then by rateFor name, then by total price (descending)
			const sortedRateGroups = Object.keys(data.rateGroups).sort((a, b) => {
				const groupA = data.rateGroups[a];
				const groupB = data.rateGroups[b];

				// First sort by mapping_key (0 = Default comes first, then 1 = Adults, etc.)
				const mappingKeyA = groupA.mappingKey || 0;
				const mappingKeyB = groupB.mappingKey || 0;
				if (mappingKeyA !== mappingKeyB) {
					return mappingKeyA - mappingKeyB;
				}

				// Then sort by name
				const nameA = groupA.rateFor || '';
				const nameB = groupB.rateFor || '';
				const nameCompare = nameA.localeCompare(nameB);
				if (nameCompare !== 0) return nameCompare;

				// If names are the same, sort by total price (descending - highest first)
				const priceA = groupA.perPersonTotal || 0;
				const priceB = groupB.perPersonTotal || 0;
				return priceB - priceA;
			});

			// Render rows
			sortedRateGroups.forEach(groupKey => {
				const item = data.rateGroups[groupKey];
				const row = `
				<tr class="hover:bg-gray-50">
					<td class="px-3 py-2 text-left text-gray-700">${item.rateFor}</td>
					<td class="px-3 py-2 text-center text-gray-700">${formatNumber(item.pax)}</td>
					<td class="px-3 py-2 text-right text-gray-700">${formatCurrency(item.perPersonRate)}</td>
					<td class="px-3 py-2 text-right text-gray-700">${formatCurrency(item.perPersonTax)}</td>
					<td class="px-3 py-2 text-right text-gray-700">${formatCurrency(item.perPersonPermitFee)}</td>
					<td class="px-3 py-2 text-right font-semibold text-gray-900">${formatCurrency(item.totalAmount)}</td>
				</tr>
			`;
				tbody.append(row);
			});
		}

		// Update totals
		$(`#${tablePrefix}TotalPax`).text(formatNumber(data.totalPax));
		$(`#${tablePrefix}TotalRate`).text('-');
		$(`#${tablePrefix}TotalTax`).text('-');
		$(`#${tablePrefix}TotalPermit`).text('-');
		$(`#${tablePrefix}TotalAmount`).text(formatCurrency(data.totalAmount));
	}

	// Render Financial By Payment Type
	function renderFinancialByPaymentType(byPaymentType, disputeAmount = 0) {
		// Get currency symbol from global currencyFormation or default to $
		const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';

		const formatCurrency = (value) => currencySymbol + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		const formatNumber = (value) => parseInt(value || 0).toLocaleString();

		const tbody = $('#pbFinancialByPaymentType');
		tbody.empty();

		const paymentTypes = ['Card', 'Billable', 'Invoiced', 'Cash', 'Headout', 'TripAdvisor', 'GetYourGuide', 'FareHarbor', 'Unpaid'];
		const colors = {
			'Card': '#3b82f6',
			'Billable': '#8b5cf6',
			'Invoiced': '#f59e0b',
			'Cash': '#10b981',
			'Headout': '#ec4899',
			'TripAdvisor': '#14b8a6',
			'GetYourGuide': '#8b5cf6',
			'FareHarbor': '#f97316',
			'Unpaid': '#6b7280'
		};

		// Track totals
		let totalPax = 0;
		let totalGross = 0;
		let totalNet = 0;

		paymentTypes.forEach(type => {
			if (byPaymentType[type] && byPaymentType[type].totalAmount > 0) {
				const data = byPaymentType[type];
				
				// Add to totals
				totalPax += data.totalPax || 0;
				totalGross += data.totalAmount || 0;
				totalNet += data.netAmount || 0;

				const row = `
				<tr class="border-b border-gray-100">
					<td class="py-1 text-left">
						<span class="inline-flex items-center gap-1">
							<span class="w-2 h-2 rounded-full" style="background-color: ${colors[type]};"></span>
							${type}
						</span>
					</td>
					<td class="py-1 text-center text-gray-700">${formatNumber(data.totalPax)}</td>
					<td class="py-1 text-right text-gray-700">${formatCurrency(data.totalAmount)}</td>
					<td class="py-1 text-right font-semibold text-gray-900">${formatCurrency(data.netAmount)}</td>
				</tr>
			`;
				tbody.append(row);
			}
		});

		// Add totals row
		if (totalPax > 0 || totalGross > 0) {
			const totalsRow = `
			<tr class="border-t-2 border-gray-300 bg-gray-50">
				<td class="py-1.5 text-left font-semibold text-gray-800">Total</td>
				<td class="py-1.5 text-center font-semibold text-gray-800">${formatNumber(totalPax)}</td>
				<td class="py-1.5 text-right font-semibold text-gray-800">${formatCurrency(totalGross)}</td>
				<td class="py-1.5 text-right font-bold text-gray-900">${formatCurrency(totalNet)}</td>
			</tr>
		`;
			tbody.append(totalsRow);

			if (disputeReversalEnabled && disputeAmount > 0) {
				const disputeRow = `
				<tr class="border-b border-gray-100">
					<td class="py-1.5 text-left text-red-600 font-medium" colspan="3">Dispute Amount</td>
					<td class="py-1.5 text-right font-semibold text-red-600">-${formatCurrency(disputeAmount)}</td>
				</tr>
			`;
				tbody.append(disputeRow);

				const netAfterDisputeRow = `
				<tr class="bg-green-50">
					<td class="py-1.5 text-left font-bold text-gray-900" colspan="3">Net After Dispute</td>
					<td class="py-1.5 text-right font-bold text-green-700">${formatCurrency(totalNet - disputeAmount)}</td>
				</tr>
			`;
				tbody.append(netAfterDisputeRow);
			}
		}
	}

	// ============================================
	// TABLE MANAGEMENT FUNCTIONS
	// ============================================

	// Update Payment Breakdown Table - Store data and initialize
	function updatePaymentBreakdownTable(bookings) {
		pbTableData = bookings || [];
		pbFilteredData = [...pbTableData];
		pbCurrentPage = 1;
		pbSearchTerm = '';
		renderPaymentBreakdownTable();
	}

	// Apply table search filter
	function applyTableSearch(searchTerm) {
		pbSearchTerm = searchTerm;
		pbCurrentPage = 1;

		if (!pbSearchTerm) {
			pbFilteredData = [...pbTableData];
		} else {
			pbFilteredData = pbTableData.filter(row => {
				const searchLower = pbSearchTerm.toLowerCase();
				return (
					(row.booking_id && String(row.booking_id).toLowerCase().includes(searchLower)) ||
					(row.tour_date && String(row.tour_date).toLowerCase().includes(searchLower)) ||
					(row.created_date && String(row.created_date).toLowerCase().includes(searchLower)) ||
					(row.purchase_date && String(row.purchase_date).toLowerCase().includes(searchLower)) ||
					(row.tour_package && String(row.tour_package).toLowerCase().includes(searchLower)) ||
					(row.customer_name && String(row.customer_name).toLowerCase().includes(searchLower)) ||
					(row.customer_email && String(row.customer_email).toLowerCase().includes(searchLower)) ||
					(row.customer_phone && String(row.customer_phone).toLowerCase().includes(searchLower)) ||
					(row.payment_type && String(row.payment_type).toLowerCase().includes(searchLower)) ||
					(row.booking_status && String(row.booking_status).toLowerCase().includes(searchLower)) ||
					(row.check_in_status && String(row.check_in_status).toLowerCase().includes(searchLower)) ||
					(row.waiver_status && String(row.waiver_status).toLowerCase().includes(searchLower)) ||
					(row.third_party_platform && String(row.third_party_platform).toLowerCase().includes(searchLower)) ||
					(row.affiliate_name && String(row.affiliate_name).toLowerCase().includes(searchLower))
				);
			});
		}

		renderPaymentBreakdownTable();
	}

	// Sort table by column
	function sortPaymentBreakdownTable(column) {
		if (pbSortColumn === column) {
			pbSortDirection = pbSortDirection === 'asc' ? 'desc' : 'asc';
		} else {
			pbSortColumn = column;
			pbSortDirection = 'asc';
		}

		pbFilteredData.sort((a, b) => {
			let aVal = a[column];
			let bVal = b[column];

			// Handle null/undefined values - put them at the end
			if (aVal === null || aVal === undefined || aVal === '') {
				return pbSortDirection === 'asc' ? 1 : -1;
			}
			if (bVal === null || bVal === undefined || bVal === '') {
				return pbSortDirection === 'asc' ? -1 : 1;
			}

			// Handle numeric values
			if (!isNaN(aVal) && !isNaN(bVal) && aVal !== '' && bVal !== '') {
				aVal = parseFloat(aVal);
				bVal = parseFloat(bVal);
			} else {
				aVal = String(aVal).toLowerCase();
				bVal = String(bVal).toLowerCase();
			}

			if (pbSortDirection === 'asc') {
				return aVal > bVal ? 1 : -1;
			} else {
				return aVal < bVal ? 1 : -1;
			}
		});

		updateSortIcons(column);
		renderPaymentBreakdownTable();
	}

	// Update sort icons
	function updateSortIcons(activeColumn) {
		$('#pbDataTable thead th[data-sort]').each(function() {
			const column = $(this).data('sort');
			const icon = $(this).find('i');

			if (column === activeColumn) {
				icon.removeClass('fa-sort fa-sort-up fa-sort-down');
				icon.addClass(pbSortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
			} else {
				icon.removeClass('fa-sort-up fa-sort-down');
				icon.addClass('fa-sort');
			}
		});
	}

	// Render table with current page data
	function renderPaymentBreakdownTable() {
		const tbody = $('#pbTableBody');
		tbody.empty();

		// Calculate pagination
		const totalRecords = pbFilteredData.length;
		const totalPages = Math.ceil(totalRecords / pbPageSize);
		const startIndex = (pbCurrentPage - 1) * pbPageSize;
		const endIndex = Math.min(startIndex + pbPageSize, totalRecords);
		const pageData = pbFilteredData.slice(startIndex, endIndex);

		// Update record count
		$('#pbRecordCount').text(`Showing ${totalRecords} records`);

		if (pageData.length === 0) {
			tbody.append('<tr><td colspan="22" class="px-3 py-3 text-center text-gray-500 text-sm">No bookings found</td></tr>');
		} else {
			pageData.forEach(booking => {
				const status = booking.booking_status || 'Unknown';
				let statusStyle = '';

				// Status badge styling with inline styles
				if (status === 'Booked') {
					statusStyle = 'background-color: #22c55e; color: white;';
				} else if (status === 'Cancelled') {
					statusStyle = 'background-color: #ef4444; color: white;';
				} else {
					statusStyle = 'background-color: #6b7280; color: white;';
				}

				const paymentType = booking.purchase_type || 'Unknown';
				let paymentTypeStyle = '';

				// Payment Type badge styling with inline styles
				if (paymentType === 'Card') {
					paymentTypeStyle = 'background-color: #3b82f6; color: white;';
				} else if (paymentType === 'Cash') {
					paymentTypeStyle = 'background-color: #10b981; color: white;';
				} else if (paymentType === 'Invoice' || paymentType === 'Invoiced') {
					paymentTypeStyle = 'background-color: #f59e0b; color: white;';
				} else if (paymentType === 'Billable') {
					paymentTypeStyle = 'background-color: #8b5cf6; color: white;';
				} else if (paymentType === 'Wire' || paymentType === 'Check/Wire') {
					paymentTypeStyle = 'background-color: #6366f1; color: white;';
				} else if (paymentType === 'Unpaid') {
					paymentTypeStyle = 'background-color: #ef4444; color: white;';
				} else {
					paymentTypeStyle = 'background-color: #6b7280; color: white;';
				}

				const checkInStatus = booking.check_in_status || 'Not Check-In';
				let checkInStyle = '';

				// Check-in badge styling with inline styles
				if (checkInStatus === '2nd Check-in') {
					checkInStyle = 'background-color: #22c55e; color: white;';
				} else if (checkInStatus === '1st Check-in') {
					checkInStyle = 'background-color: #eab308; color: white;';
				} else if (checkInStatus === 'Absent') {
					checkInStyle = 'background-color: #ef4444; color: white;';
				} else {
					checkInStyle = 'background-color: #9ca3af; color: white;';
				}

				const waiverStatus = booking.waiver_status || 'Not Signed';
				let waiverStyle = '';

				// Waiver badge styling with inline styles
				if (waiverStatus === 'Signed') {
					waiverStyle = 'background-color: #22c55e; color: white;';
				} else {
					waiverStyle = 'background-color: #f97316; color: white;';
				}

				// Helper function to add hidden class based on visibility state
				const getHiddenClass = (column) => pbVisibleColumns[column] ? '' : 'hidden';

				// Create booking URL using the route prefix (already defined at top of file)
				const bookingUrl = `/${routePrefix}/booking/${booking.booking_id}`;

				const row = `
				<tr class="hover:bg-gray-50 transition-colors text-xs">
					<td class="pb-col-booking-id px-3 py-2 text-left ${getHiddenClass('booking-id')}">
						<a href="${bookingUrl}" target="_blank" class="text-blue-600 hover:text-blue-800 hover:underline font-medium">
							${booking.booking_id || ''}
						</a>
					</td>
					<td class="pb-col-tour-date px-3 py-2 text-left text-gray-900 ${getHiddenClass('tour-date')}">${booking.tour_date || ''}</td>
					<td class="pb-col-tour-time px-3 py-2 text-left text-gray-900 ${getHiddenClass('tour-time')}">${booking.tour_time || ''}</td>
					<td class="pb-col-created-date px-3 py-2 text-left text-gray-900 ${getHiddenClass('created-date')}">${booking.created_date || ''}</td>
					<td class="pb-col-purchase-date px-3 py-2 text-left text-gray-900 ${getHiddenClass('purchase-date')}">${booking.purchase_date || ''}</td>
					<td class="pb-col-tour-package px-3 py-2 text-left text-gray-900 ${getHiddenClass('tour-package')}">${booking.tour_package || ''}</td>
					<td class="pb-col-customer px-3 py-2 text-left text-gray-900 ${getHiddenClass('customer')}">${booking.customer_name || ''}</td>
					<td class="pb-col-customer-email px-3 py-2 text-left text-gray-900 ${getHiddenClass('customer-email')}">${booking.customer_email || ''}</td>
					<td class="pb-col-customer-phone px-3 py-2 text-left text-gray-900 ${getHiddenClass('customer-phone')}">${booking.customer_phone || ''}</td>
					<td class="pb-col-payment-type px-3 py-2 text-left ${getHiddenClass('payment-type')}">
						<span style="${paymentTypeStyle} display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px;">${paymentType}</span>
					</td>
					<td class="pb-col-payment px-3 py-2 text-right text-gray-900 ${getHiddenClass('payment')}">${parseFloat(booking.amount || 0).toFixed(2)}</td>
					<td class="pb-col-unpaid px-3 py-2 text-right text-gray-900 ${getHiddenClass('unpaid')}">${parseFloat(booking.unpaid_total || 0).toFixed(2)}</td>
					<td class="pb-col-fees px-3 py-2 text-right text-gray-900 ${getHiddenClass('fees')}">${parseFloat(booking.fee_amount || 0).toFixed(2)}</td>
					<td class="pb-col-refund px-3 py-2 text-right text-gray-900 ${getHiddenClass('refund')}">${parseFloat(booking.refund_amount || 0).toFixed(2)}</td>
					<td class="pb-col-discount px-3 py-2 text-right text-gray-900 ${getHiddenClass('discount')}">${parseFloat(booking.discount || 0).toFixed(2)}</td>
					<td class="pb-col-net px-3 py-2 text-right font-semibold text-gray-900 ${getHiddenClass('net')}">${parseFloat(booking.net_total ?? 0).toFixed(2)}</td>
					<td class="pb-col-pax px-3 py-2 text-center text-gray-900 ${getHiddenClass('pax')}">${booking.total_pax || 0}</td>
					<td class="pb-col-rate px-3 py-2 text-right text-gray-900 ${getHiddenClass('rate')}">${parseFloat(booking.pax_rate || 0).toFixed(2)}</td>
					<td class="pb-col-tax px-3 py-2 text-right text-gray-900 ${getHiddenClass('tax')}">${parseFloat(booking.pax_tax || 0).toFixed(2)}</td>
					<td class="pb-col-permit-fee px-3 py-2 text-right text-gray-900 ${getHiddenClass('permit-fee')}">${parseFloat(booking.pax_permit_fee || 0).toFixed(2)}</td>
					<td class="pb-col-status px-3 py-2 text-left ${getHiddenClass('status')}">
						<span style="${statusStyle} display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px;">${status}</span>
					</td>
					<td class="pb-col-checkin px-3 py-2 text-left ${getHiddenClass('checkin')}">
						<span style="${checkInStyle} display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px;">${checkInStatus}</span>
					</td>
					<td class="pb-col-waiver px-3 py-2 text-left ${getHiddenClass('waiver')}">
						<span style="${waiverStyle} display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px;">${waiverStatus}</span>
					</td>
					<td class="pb-col-platform px-3 py-2 text-left text-gray-900 ${getHiddenClass('platform')}">${booking.third_party_platform || 'Direct'}</td>
					<td class="pb-col-affiliate px-3 py-2 text-left text-gray-900 ${getHiddenClass('affiliate')}">${booking.affiliate_name || ''}</td>
					<td class="pb-col-reallocated px-3 py-2 text-center ${getHiddenClass('reallocated')}">
						<span style="${booking.is_reallocated ? 'background-color: #dc2626; color: white;' : 'background-color: #16a34a; color: white;'} display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px;">
							${booking.is_reallocated ? 'True' : 'False'}
						</span>
					</td>
					<td class="pb-col-soft-block px-3 py-2 text-center ${getHiddenClass('soft-block')}">
						<span style="${booking.is_soft_block ? 'background-color: #dc2626; color: white;' : 'background-color: #16a34a; color: white;'} display: inline-block; padding: 4px 8px; font-size: 11px; font-weight: 600; border-radius: 4px;">
							${booking.is_soft_block ? 'True' : 'False'}
						</span>
					</td>
				</tr>
			`;
				tbody.append(row);
			});
		}

		// Update pagination
		updatePaginationInfo(startIndex + 1, endIndex, totalRecords);
		renderPagination(totalPages);
	}

	// Change page
	function changePage(page) {
		if (page >= 1 && page <= Math.ceil(pbFilteredData.length / pbPageSize)) {
			pbCurrentPage = page;
			renderPaymentBreakdownTable();
		}
	}

	// Update pagination info text
	function updatePaginationInfo(start, end, total) {
		$('#pbPaginationInfo').text(`Showing ${start} to ${end} of ${total} entries`);
	}

	// Render pagination controls
	function renderPagination(totalPages) {
		const pagination = $('#pbPagination');
		pagination.empty();

		if (totalPages <= 1) return;

		// Previous button
		pagination.append(`
		<li>
			<button class="px-3 py-1 text-sm border border-gray-300 rounded-l-md hover:bg-gray-50 ${pbCurrentPage === 1 ? 'opacity-50 cursor-not-allowed' : ''}" 
			        onclick="changePage(${pbCurrentPage - 1})" 
			        ${pbCurrentPage === 1 ? 'disabled' : ''}>
				<i class="fas fa-chevron-left"></i>
			</button>
		</li>
	`);

		// Page numbers
		const startPage = Math.max(1, pbCurrentPage - 2);
		const endPage = Math.min(totalPages, pbCurrentPage + 2);

		if (startPage > 1) {
			pagination.append(`
			<li>
				<button class="px-3 py-1 text-sm border-t border-b border-gray-300 hover:bg-gray-50" 
				        onclick="changePage(1)">1</button>
			</li>
		`);
			if (startPage > 2) {
				pagination.append(`
				<li>
					<span class="px-3 py-1 text-sm border-t border-b border-gray-300 text-gray-400">...</span>
				</li>
			`);
			}
		}

		for (let i = startPage; i <= endPage; i++) {
			const isActive = i === pbCurrentPage;
			pagination.append(`
			<li>
				<button class="px-3 py-1 text-sm border-t border-b border-gray-300 hover:bg-gray-50 ${isActive ? 'bg-orange-500 text-white hover:bg-orange-600' : ''}" 
				        onclick="changePage(${i})">${i}</button>
			</li>
		`);
		}

		if (endPage < totalPages) {
			if (endPage < totalPages - 1) {
				pagination.append(`
				<li>
					<span class="px-3 py-1 text-sm border-t border-b border-gray-300 text-gray-400">...</span>
				</li>
			`);
			}
			pagination.append(`
			<li>
				<button class="px-3 py-1 text-sm border-t border-b border-gray-300 hover:bg-gray-50" 
				        onclick="changePage(${totalPages})">${totalPages}</button>
			</li>
		`);
		}

		// Next button
		pagination.append(`
		<li>
			<button class="px-3 py-1 text-sm border border-gray-300 rounded-r-md hover:bg-gray-50 ${pbCurrentPage === totalPages ? 'opacity-50 cursor-not-allowed' : ''}" 
			        onclick="changePage(${pbCurrentPage + 1})" 
			        ${pbCurrentPage === totalPages ? 'disabled' : ''}>
				<i class="fas fa-chevron-right"></i>
			</button>
		</li>
	`);
	}

	// ============================================
	// CHART FUNCTIONS
	// ============================================

	// Initialize Payment Breakdown Charts
	function initializePaymentBreakdownCharts(response) {
		// Check if Chart.js is available
		if (typeof Chart === 'undefined') {
			console.warn('Chart.js is not loaded. Charts will not be displayed.');
			return;
		}

		// Get currency symbol from global currencyFormation or default to $
		const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';

		// Get payment type data from charts object in response
		const chartsData = response.charts || {};
		const paymentTypeChart = chartsData.payment_types || {};
		const paymentLabels = paymentTypeChart.labels || [];
		const paymentValues = paymentTypeChart.values || [];

		const paymentColors = {
			'Card': '#3B82F6',
			'Cash': '#10B981',
			'Invoice': '#F59E0B',
			'Invoiced': '#F59E0B',
			'Billable': '#8B5CF6',
			'Wire': '#6366F1',
			'Check/Wire': '#6366F1',
			'Headout': '#EC4899',
			'TripAdvisor': '#14B8A6',
			'GetYourGuide': '#8B5CF6',
			'FareHarbor': '#F97316',
			'Unpaid': '#EF4444'
		};

		// Payment Type Distribution Chart
		const paymentTypeCtx = document.getElementById('pbPaymentTypeChart');
		if (paymentTypeCtx) {
			// Destroy existing chart if it exists
			if (window.pbPaymentTypeChart && typeof window.pbPaymentTypeChart.destroy === 'function') {
				window.pbPaymentTypeChart.destroy();
			}

			// Only create chart if there's data
			if (paymentLabels.length > 0 && paymentValues.length > 0) {
				const colors = paymentLabels.map(label => paymentColors[label] || '#6B7280');

				window.pbPaymentTypeChart = new Chart(paymentTypeCtx, {
					type: 'doughnut',
					data: {
						labels: paymentLabels,
						datasets: [{
							data: paymentValues,
							backgroundColor: colors
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						legend: {
							position: 'bottom',
							labels: {
								fontSize: 10,
								padding: 10
							}
						},
						tooltips: {
							enabled: true,
							callbacks: {
								label: function(tooltipItem, data) {
									const label = data.labels[tooltipItem.index] || '';
									const value = data.datasets[0].data[tooltipItem.index] || 0;
									// Format with exactly 2 decimal places and thousand separators
									const formattedValue = currencySymbol + Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
									return label + ': ' + formattedValue;
								}
							}
						}
					}
				});
			}
		}

		// Revenue vs Refunds Chart
		const revenueRefundCtx = document.getElementById('pbRevenueRefundChart');
		if (revenueRefundCtx) {
			// Destroy existing chart if it exists
			if (window.pbRevenueRefundChart && typeof window.pbRevenueRefundChart.destroy === 'function') {
				window.pbRevenueRefundChart.destroy();
			}

			const revenueData = chartsData.revenue_refunds || {};

			const revenueChartLabels = ['Revenue', 'Refunds', 'Fees'];
			const revenueChartData   = [revenueData.revenue || 0, revenueData.refunds || 0, revenueData.fees || 0];
			const revenueChartColors = ['#10B981', '#EF4444', '#F59E0B'];
			if (disputeReversalEnabled) {
				revenueChartLabels.push('Disputes');
				revenueChartData.push(revenueData.dispute_reversals || 0);
				revenueChartColors.push('#F97316');
			}
			revenueChartLabels.push('Net');
			revenueChartData.push(revenueData.net_revenue || 0);
			revenueChartColors.push('#3B82F6');

			window.pbRevenueRefundChart = new Chart(revenueRefundCtx, {
				type: 'bar',
				data: {
					labels: revenueChartLabels,
					datasets: [{
						label: 'Amount',
						data: revenueChartData,
						backgroundColor: revenueChartColors
					}]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					legend: {
						display: false
					},
					tooltips: {
						enabled: true,
						callbacks: {
							label: function(tooltipItem, data) {
								const label = data.datasets[tooltipItem.datasetIndex].label || '';
								const value = tooltipItem.yLabel || 0;
								// Format with exactly 2 decimal places and thousand separators
								const formattedValue = currencySymbol + Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
								return label + ': ' + formattedValue;
							}
						}
					},
					scales: {
						yAxes: [{
							ticks: {
								beginAtZero: true,
								callback: function(value) {
									// Format Y-axis labels with 2 decimal places
									return currencySymbol + Number(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
								}
							}
						}]
					}
				}
			});
		}

		// Check-in Status Chart
		const checkInCtx = document.getElementById('pbCheckInChart');
		if (checkInCtx) {
			// Destroy existing chart if it exists
			if (window.pbCheckInChart && typeof window.pbCheckInChart.destroy === 'function') {
				window.pbCheckInChart.destroy();
			}

			const checkInData = response.checkInData || {};

			// Helper function to extract PAX count from check-in data (not booking count)
			const getCheckInCount = (data) => {
				if (typeof data === 'number') return data;
				if (typeof data === 'object' && data !== null) {
					// Return PAX count, not booking count
					return parseInt(data.pax || data.total || data.value || 0);
				}
				return 0;
			};

			const checkInValues = [
				getCheckInCount(checkInData['2nd Check-in']),
				getCheckInCount(checkInData['1st Check-in']),
				getCheckInCount(checkInData['Not Check-In']),
				getCheckInCount(checkInData['Absent'])
			];

			// Only create chart if there's data
			if (checkInValues.some(val => val > 0)) {
				window.pbCheckInChart = new Chart(checkInCtx, {
					type: 'pie',
					data: {
						labels: ['2nd Check-in', '1st Check-in', 'Not Check-In', 'Absent'],
						datasets: [{
							data: checkInValues,
							backgroundColor: [
								'#10B981', // Green
								'#F59E0B', // Yellow
								'#6B7280', // Gray
								'#EF4444' // Red
							]
						}]
					},
					options: {
						responsive: true,
						maintainAspectRatio: false,
						legend: {
							position: 'bottom',
							labels: {
								fontSize: 10,
								padding: 10
							}
						},
						tooltips: {
							enabled: true,
							callbacks: {
								label: function(tooltipItem, data) {
									const label = data.labels[tooltipItem.index] || '';
									const value = data.datasets[0].data[tooltipItem.index] || 0;
									// Format with thousand separators
									const formattedValue = Number(value).toLocaleString();
									return label + ': ' + formattedValue + ' PAX';
								}
							}
						}
					}
				});
			}
		}
	}

	// Export Payment Breakdown Report
	function exportPaymentBreakdownReport(format) {
		const dateFrom = $('#pbDateFrom').val();
		const dateTo = $('#pbDateTo').val();

		if (!dateFrom || !dateTo) {
			swal('Please generate a report first', '', 'warning');
			return;
		}

		const paymentTypes = [];
		$('.pb-payment-type:checked').each(function() {
			paymentTypes.push($(this).val());
		});

		const chargeTypes = [];
		$('.pb-charge-type-checkbox:checked').each(function() {
			chargeTypes.push($(this).val());
		});

		const tourPackages = [];
		$('.pb-tour-package-checkbox:checked').each(function() {
			tourPackages.push($(this).val());
		});

		// Get selected affiliates (only if affiliates exist)
		const hasAffiliates = window.pbAffiliates && window.pbAffiliates.length > 0;
		const affiliates = [];
		if (hasAffiliates) {
			$('.pb-affiliate-checkbox:checked').each(function() {
				affiliates.push($(this).val());
			});
		}

		const excludeAllAffiliates = hasAffiliates ? ($('#pbExcludeAllAffiliates').val() === '1') : false;

		// Get selected check-in statuses
		const checkInStatuses = [];
		$('.pb-checkin-status-checkbox:checked').each(function() {
			checkInStatuses.push($(this).val());
		});

		// Get selected third party platforms (only if enabled)
		const hasEnabledPlatforms = window.pbEnabledThirdPartyPlatforms && window.pbEnabledThirdPartyPlatforms.length > 0;
		const thirdPartyPlatforms = [];
		if (hasEnabledPlatforms) {
			$('.pb-third-party-checkbox:checked').each(function() {
				thirdPartyPlatforms.push($(this).val());
			});
		}

		const excludeAllThirdParty = hasEnabledPlatforms ? ($('#pbExcludeAllThirdParty').val() === '1') : false;

		// Build URL with proper array format for paymentTypes, tourPackages, affiliates, and checkInStatuses
		const params = new URLSearchParams();
		params.append('tourOperator', $('#tour_operator').val() || window.pbTourOperatorId || 0);
		
		// Add tour packages as array
		if (tourPackages.length > 0) {
			tourPackages.forEach(pkgId => {
				params.append('tourPackages[]', pkgId);
			});
		}
		
		// Add affiliates as array (only if affiliates exist)
		if (hasAffiliates && affiliates.length > 0) {
			affiliates.forEach(affId => {
				params.append('affiliates[]', affId);
			});
		}
		
		params.append('excludeAllAffiliates', excludeAllAffiliates ? '1' : '0');
		params.append('dateFrom', dateFrom);
		params.append('dateTo', dateTo);

		// Add payment types as array
		paymentTypes.forEach(type => {
			params.append('paymentTypes[]', type);
		});

		params.append('viewBy', $('#pbViewBy').val());
		params.append('bookingStatus', $('#pbBookingStatus').val() || '');

		// Add check-in statuses as array
		if (checkInStatuses.length > 0) {
			checkInStatuses.forEach(status => {
				params.append('checkInStatuses[]', status);
			});
		}

		params.append('waiverStatus', $('#pbWaiverStatus').val() || '');
		
		// Add third party platforms as array (only if enabled)
		if (hasEnabledPlatforms && thirdPartyPlatforms.length > 0) {
			thirdPartyPlatforms.forEach(platform => {
				params.append('thirdPartyPlatforms[]', platform);
			});
		}
		params.append('excludeAllThirdParty', excludeAllThirdParty ? '1' : '0');
		
		params.append('minAmount', $('#pbMinAmount').val() || '');
		params.append('maxAmount', $('#pbMaxAmount').val() || '');
		params.append('rate', $('#pbRate').val() || '');
		params.append('permitFee', $('#pbPermitFee').val() || '');
		params.append('tax', $('#pbTax').val() || '');
		params.append('softBlocks', $('#pbSoftBlocks').val() || '');
		params.append('reallocatedFunds', $('#pbReallocatedFunds').val() || '');

		// Add charge types as array
		if (chargeTypes.length > 0) {
			chargeTypes.forEach(type => {
				params.append('chargeTypes[]', type);
			});
		}

		params.append('format', format);
		params.append('includeCharts', $('#pbIncludeCharts').is(':checked') ? 1 : 0);
		params.append('includeFilters', $('#pbIncludeFilters').is(':checked') ? 1 : 0);
		params.append('includeBookingDetails', $('#pbIncludeBookingDetails').is(':checked') ? 1 : 0);

		const exportUrl = `/${routePrefix}/report/payment-breakdown-report/export`;

		// Check permission first with AJAX HEAD request
		$.ajax({
			url: exportUrl + "?" + params.toString(),
			method: 'HEAD',
			success: function() {
				// Permission granted, proceed with download
				window.location.href = exportUrl + "?" + params.toString();

				// Close modal
				$('#pbExportModal').addClass('hidden').css('display', 'none');
			},
			error: function(xhr) {
				// Handle permission errors
				if (xhr.status === 403) {
					swal('Permission Denied', 'User does not have the right permissions to export reports.', 'error');
				} else if (xhr.status === 401) {
					swal('Unauthorized', 'Please log in to export reports.', 'error');
				} else {
					const response = xhr.responseJSON;
					const message = response?.message || 'Failed to export report. Please try again.';
					swal('Error', message, 'error');
				}

				// Close modal
				$('#pbExportModal').addClass('hidden').css('display', 'none');
			}
		});
	}

	// ============================================
	// TAB SWITCHING FUNCTION
	// ============================================

	// Switch between Summary and Payment Breakdown tabs
	function switchPaymentBreakdownTab(tabName) {
		// Get tab buttons and content
		const summaryTab = document.getElementById('pbSummaryTab');
		const paymentTab = document.getElementById('pbPaymentTab');
		const summaryContent = document.getElementById('pbSummaryTabContent');
		const paymentContent = document.getElementById('pbPaymentTabContent');

		if (tabName === 'summary') {
			// Activate Summary tab
			summaryTab.classList.add('border-orange-500', 'text-orange-600');
			summaryTab.classList.remove('border-transparent', 'text-gray-500');

			// Deactivate Payment tab
			paymentTab.classList.remove('border-orange-500', 'text-orange-600');
			paymentTab.classList.add('border-transparent', 'text-gray-500');

			// Show Summary content, hide Payment content
			summaryContent.classList.remove('hidden');
			paymentContent.classList.add('hidden');
		} else if (tabName === 'payment') {
			// Activate Payment tab
			paymentTab.classList.add('border-orange-500', 'text-orange-600');
			paymentTab.classList.remove('border-transparent', 'text-gray-500');

			// Deactivate Summary tab
			summaryTab.classList.remove('border-orange-500', 'text-orange-600');
			summaryTab.classList.add('border-transparent', 'text-gray-500');

			// Show Payment content, hide Summary content
			paymentContent.classList.remove('hidden');
			summaryContent.classList.add('hidden');
		}
	}

	// ============================================
	// Permit Fee Check-In Breakdown Modal
	// ============================================

	// Helper functions for formatting
	const pbFormatCurrency = (value) => {
		const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';
		return currencySymbol + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	};

	const pbFormatNumber = (value) => {
		return parseInt(value || 0).toLocaleString();
	};

	// Global state for expanded rows
	let expandedStatuses = new Set();

	// Open Permit Fee Breakdown Modal
	$('#pbPermitFeeBreakdownBtn').on('click', function() {
		openPermitFeeBreakdownModal();
	});

	// Close Modal
	$('#pbCloseBreakdownModal').on('click', function() {
		closePermitFeeBreakdownModal();
	});

	// Close modal when clicking outside
	$('#pbPermitFeeBreakdownModal').on('click', function(e) {
		if ($(e.target).is('#pbPermitFeeBreakdownModal')) {
			closePermitFeeBreakdownModal();
		}
	});

	// Expand All
	$('#pbExpandAll').on('click', function() {
		$('.pb-status-row').each(function() {
			const status = $(this).data('status');
			expandStatus(status);
		});
	});

	// Collapse All
	$('#pbCollapseAll').on('click', function() {
		$('.pb-status-row').each(function() {
			const status = $(this).data('status');
			collapseStatus(status);
		});
	});

	function openPermitFeeBreakdownModal() {
		// Show modal
		$('#pbPermitFeeBreakdownModal').removeClass('hidden');

		// Reset expanded state
		expandedStatuses.clear();

		// Show loading state
		$('#pbBreakdownLoading').show();
		$('#pbBreakdownError').hide();
		$('#pbBreakdownContent').hide();

		// Check if we have booking data
		if (!allBookingsData || allBookingsData.length === 0) {
			showBreakdownError('No booking data available. Please generate the report first.');
			return;
		}

		// Build permit fee breakdown from stored booking data
		const breakdownData = buildPermitFeeBreakdownFromBookings(allBookingsData);
		
		// Render the breakdown
		renderPermitFeeBreakdown(breakdownData);
	}

	/**
	 * Build permit fee breakdown data from bookings
	 * This processes the same data that the main page uses, ensuring consistency
	 * Uses check_in_details.status_breakdown and rate_breakdown from booking data
	 */
	function buildPermitFeeBreakdownFromBookings(bookings) {
		// Initialize breakdown structure
		const breakdown = {
			'2nd Check In': {
				status: '2nd Check In',
				total_pax: 0,
				total_pax_with_permit: 0,
				total_permit_fees: 0,
				rate_groups: {}
			},
			'1st Check In': {
				status: '1st Check In',
				total_pax: 0,
				total_pax_with_permit: 0,
				total_permit_fees: 0,
				rate_groups: {}
			},
			'Not Checked In': {
				status: 'Not Checked In',
				total_pax: 0,
				total_pax_with_permit: 0,
				total_permit_fees: 0,
				rate_groups: {}
			},
			'Absent': {
				status: 'Absent',
				total_pax: 0,
				total_pax_with_permit: 0,
				total_permit_fees: 0,
				rate_groups: {}
			}
		};

		// Map status names from check_in_details.status_breakdown to our breakdown keys
		const statusMap = {
			'2nd Check-in': '2nd Check In',
			'1st Check-in': '1st Check In',
			'Not Check-In': 'Not Checked In',
			'Absent': 'Absent'
		};

		// Process each booking
		bookings.forEach(booking => {
			const rateBreakdown = booking.rate_breakdown || [];
			const checkInDetails = booking.check_in_details || {};
			const rateStatusBreakdown = checkInDetails.rate_status_breakdown || {};
			const bookingStatusBreakdown = checkInDetails.status_breakdown || {};
			const totalPax = parseInt(booking.total_pax || 0);

			// Process each rate group in the booking
			rateBreakdown.forEach(rateGroup => {
				const pax = parseInt(rateGroup.pax || 0);
				let permitFee = parseFloat(rateGroup.permit_fee || 0);
				const rateFor = rateGroup.rate_for || 'Unknown';
				const rateId = rateGroup.rate_id;

				if (pax <= 0) return;

				// Apply Dixies-specific logic: children rate groups have $0 permit fee
				if (pbTenantId === 'dixies' && rateFor.toLowerCase().includes('children')) {
					permitFee = 0;
				}

				// Use per-rate-group status breakdown when available, fall back to booking-level
				const perRateBreakdown = (rateId && rateStatusBreakdown[rateId]) ? rateStatusBreakdown[rateId] : null;
				const effectiveStatusBreakdown = perRateBreakdown || bookingStatusBreakdown;
				let totalStatusPax = 0;
				Object.values(effectiveStatusBreakdown).forEach(count => {
					totalStatusPax += parseInt(count || 0);
				});

				// If no status breakdown data, put all pax for this rate group in "Not Checked In"
				if (totalStatusPax === 0 && pax > 0) {
					effectiveStatusBreakdown['Not Check-In'] = pax;
					totalStatusPax = pax;
				}

				Object.keys(effectiveStatusBreakdown).forEach(statusKey => {
					const statusCount = parseInt(effectiveStatusBreakdown[statusKey] || 0);
					if (statusCount <= 0) return;

					const breakdownKey = statusMap[statusKey] || 'Not Checked In';

					// When using per-rate-group breakdown, counts are exact — no proportion needed.
					// When falling back to booking-level, distribute proportionally.
					const paxForStatus = perRateBreakdown
						? statusCount
						: Math.round(pax * (totalStatusPax > 0 ? statusCount / totalStatusPax : 0));
					if (paxForStatus <= 0) return;
					
					// Determine effective permit fee:
					// 1. Absent pax have $0 permit fee (for all tenants)
					// 2. For Dixies, children have $0 permit fee regardless of status
					let effectivePermitFee = permitFee;
					if (breakdownKey === 'Absent') {
						effectivePermitFee = 0;
					} else if (pbTenantId === 'dixies' && rateFor.toLowerCase().includes('children')) {
						effectivePermitFee = 0;
					}

					// Update status totals
					breakdown[breakdownKey].total_pax += paxForStatus;
					breakdown[breakdownKey].total_permit_fees += paxForStatus * effectivePermitFee;

					if (effectivePermitFee > 0) {
						breakdown[breakdownKey].total_pax_with_permit += paxForStatus;
					}

					// Update rate group within status
					if (!breakdown[breakdownKey].rate_groups[rateFor]) {
						breakdown[breakdownKey].rate_groups[rateFor] = {
							rate_for: rateFor,
							total_pax: 0,
							pax_with_permit: 0,
							permit_fees: 0,
							per_person_permit_fee: effectivePermitFee
						};
					}

					breakdown[breakdownKey].rate_groups[rateFor].total_pax += paxForStatus;
					breakdown[breakdownKey].rate_groups[rateFor].permit_fees += paxForStatus * effectivePermitFee;
					if (effectivePermitFee > 0) {
						breakdown[breakdownKey].rate_groups[rateFor].pax_with_permit += paxForStatus;
					}
				});
			});
		});

		// Convert rate_groups objects to arrays and calculate totals
		const breakdownArray = [];
		const totals = {
			total_pax: 0,
			total_pax_with_permit: 0,
			total_permit_fees: 0,
			rate_groups: []
		};

		const allRateGroups = {};

		Object.keys(breakdown).forEach(status => {
			const statusData = breakdown[status];
			statusData.rate_groups = Object.values(statusData.rate_groups);
			statusData.total_permit_fees = Math.round(statusData.total_permit_fees * 100) / 100;

			breakdownArray.push(statusData);

			// Aggregate totals
			totals.total_pax += statusData.total_pax;
			totals.total_pax_with_permit += statusData.total_pax_with_permit;
			totals.total_permit_fees += statusData.total_permit_fees;

			// Aggregate rate groups
			statusData.rate_groups.forEach(rg => {
				if (!allRateGroups[rg.rate_for]) {
					allRateGroups[rg.rate_for] = {
						rate_for: rg.rate_for,
						total_pax: 0,
						pax_with_permit: 0,
						permit_fees: 0,
						per_person_permit_fee: rg.per_person_permit_fee
					};
				}
				allRateGroups[rg.rate_for].total_pax += rg.total_pax;
				allRateGroups[rg.rate_for].pax_with_permit += rg.pax_with_permit;
				allRateGroups[rg.rate_for].permit_fees += rg.permit_fees;
			});
		});

		totals.total_permit_fees = Math.round(totals.total_permit_fees * 100) / 100;
		totals.rate_groups = Object.values(allRateGroups);

		return {
			breakdown: breakdownArray,
			totals: totals
		};
	}

	function closePermitFeeBreakdownModal() {
		$('#pbPermitFeeBreakdownModal').addClass('hidden');
		expandedStatuses.clear();
	}

	function renderPermitFeeBreakdown(data) {
		const breakdown = data.breakdown || [];
		const totals = data.totals || {};

		// Hide loading, show content
		$('#pbBreakdownLoading').hide();
		$('#pbBreakdownContent').show();

		// Clear table body
		const $tbody = $('#pbBreakdownTableBody');
		$tbody.empty();

		// Render rows for each check-in status
		breakdown.forEach(statusData => {
			renderStatusRow(statusData, $tbody);
		});

		// Update grand totals
		updateGrandTotals(totals);
	}

	function renderStatusRow(statusData, $tbody) {
		const status = statusData.status;
		const statusClass = getStatusClass(status);
		const hasRateGroups = statusData.rate_groups && statusData.rate_groups.length > 0;
		const expandIcon = hasRateGroups ? 'fa-chevron-right' : '';

		// Create a sanitized status ID for CSS classes (remove spaces and special chars)
		const statusId = status.replace(/\s+/g, '-').replace(/[^a-zA-Z0-9-]/g, '');

		// Main status row
		const $statusRow = $(`
			<tr class="pb-status-row hover:bg-gray-50 ${hasRateGroups ? 'cursor-pointer font-semibold' : ''}" 
				data-status="${statusId}">
				<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
					${hasRateGroups ? `<i class="fas ${expandIcon} pb-expand-icon mr-2 text-gray-400"></i>` : ''}
					<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
						${status}
					</span>
				</td>
				<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
					${pbFormatNumber(statusData.total_pax)}
				</td>
				<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
					${pbFormatNumber(statusData.total_pax_with_permit)}
				</td>
				<td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
					${pbFormatCurrency(statusData.total_permit_fees)}
				</td>
			</tr>
		`);

		$tbody.append($statusRow);

		// Add click handler for expandable rows
		if (hasRateGroups) {
			$statusRow.on('click', function() {
				toggleStatus(statusId);
			});
		}

		// Render rate group rows (initially hidden)
		if (hasRateGroups) {
			statusData.rate_groups.forEach((rateGroup, index) => {
				const $rateGroupRow = $(`
					<tr class="pb-rate-group-row pb-rate-group-${statusId} hidden bg-gray-50" data-status="${statusId}">
						<td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 pl-16">
							<i class="fas fa-level-up-alt fa-rotate-90 mr-2 text-gray-400"></i>
							${rateGroup.rate_for}
						</td>
						<td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right">
							${pbFormatNumber(rateGroup.total_pax)}
						</td>
						<td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right">
							${pbFormatNumber(rateGroup.pax_with_permit)}
						</td>
						<td class="px-6 py-3 whitespace-nowrap text-sm text-gray-700 text-right">
							${pbFormatCurrency(rateGroup.permit_fees)}
						</td>
					</tr>
				`);
				$tbody.append($rateGroupRow);
			});
		}
	}

	function toggleStatus(statusId) {
		if (expandedStatuses.has(statusId)) {
			collapseStatus(statusId);
		} else {
			expandStatus(statusId);
		}
	}

	function expandStatus(statusId) {
		expandedStatuses.add(statusId);
		const $rateGroups = $(`.pb-rate-group-${statusId}`);
		$rateGroups.removeClass('hidden');
		$(`.pb-status-row[data-status="${statusId}"] .pb-expand-icon`)
			.removeClass('fa-chevron-right')
			.addClass('fa-chevron-down');
	}

	function collapseStatus(statusId) {
		expandedStatuses.delete(statusId);
		$(`.pb-rate-group-${statusId}`).addClass('hidden');
		$(`.pb-status-row[data-status="${statusId}"] .pb-expand-icon`)
			.removeClass('fa-chevron-down')
			.addClass('fa-chevron-right');
	}

	function updateGrandTotals(totals) {
		$('#pbBreakdownTotalPax').text(pbFormatNumber(totals.total_pax));

		// Use total_pax_with_permit from backend (already calculated)
		$('#pbBreakdownTotalWithPermit').text(pbFormatNumber(totals.total_pax_with_permit || 0));
		$('#pbBreakdownTotalFees').text(pbFormatCurrency(totals.total_permit_fees));
	}

	function getStatusClass(status) {
		switch(status) {
			case '2nd Check In':
				return 'bg-green-100 text-green-800';
			case '1st Check In':
				return 'bg-blue-100 text-blue-800';
			case 'Not Checked In':
				return 'bg-yellow-100 text-yellow-800';
			case 'Absent':
				return 'bg-red-100 text-red-800';
			default:
				return 'bg-gray-100 text-gray-800';
		}
	}

	function showBreakdownError(message) {
		$('#pbBreakdownLoading').hide();
		$('#pbBreakdownContent').hide();
		$('#pbBreakdownError').show();
		$('#pbBreakdownErrorMessage').text(message);
	}

	// ============================================
	// REFUNDED / UNPAID / DISCOUNT MODALS
	// ============================================

	// Global helper functions for modals
	function getModalCurrencySymbol() {
		return (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';
	}

	function formatCurrency(value) {
		const currencySymbol = getModalCurrencySymbol();
		return currencySymbol + parseFloat(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
	}

	function formatNumber(value) {
		return parseInt(value || 0).toLocaleString();
	}

	// Global variables for modal data
	let refundedBookingsData = [];
	let excludedRefundsBookingsData = [];
	let unpaidBookingsData = [];
	let discountBookingsData = [];
	let customChargeBookingsData = [];
	let priorPaymentsBookingsData = [];
	let overpaymentBookingsData = [];
	let otherPeriodPaymentsBookingsData = [];
	let allBookingsData = [];
	let disputeReversalsData = [];

	// Store booking data when report is generated
	function storeModalBookingsData(bookings) {
		refundedBookingsData = [];
		excludedRefundsBookingsData = [];
		unpaidBookingsData = [];
		discountBookingsData = [];
		customChargeBookingsData = [];
		priorPaymentsBookingsData = [];
		overpaymentBookingsData = [];
		otherPeriodPaymentsBookingsData = [];
		allBookingsData = bookings;

		bookings.forEach(booking => {
			const rate = parseFloat(booking.rate || 0);
			const tax = parseFloat(booking.tax || 0);
			const permitFee = parseFloat(booking.permit_fee || 0);
			const refundAmount = parseFloat(booking.refund_amount || 0);
			const netTotal = parseFloat(booking.net_total || 0);
			const discount = parseFloat(booking.discount || 0);
			const amount = parseFloat(booking.amount || 0);
			const feeAmount = parseFloat(booking.fee_amount || 0);
			const gross = rate + tax + permitFee;
			// What customer actually owes after discount
			const actualOwed = gross - discount;
			const paymentType = booking.purchase_type || '';
			const tourists = parseInt(booking.total_pax || 0);

			// Unpaid bookings: only count if they owe more than they paid (after discount)
			if (paymentType === 'Unpaid') {
				const unpaidAmount = Math.max(0, actualOwed - netTotal);
				if (unpaidAmount > 0.01) {
					unpaidBookingsData.push({
						booking_id: booking.booking_id,
						customer_name: booking.customer_name,
						tour_date: booking.tour_date,
						booking_status: booking.booking_status,
						tourists: tourists,
						gross: gross,
						discount: discount,
						actual_owed: actualOwed,
						unpaid_amount: unpaidAmount
					});
				}
			}

			// Custom Charge vs Overpayment:
			// - Custom Charge: booking has any transaction with charge_type = 'custom_charge'
			// - Overpayment: net > actualOwed but NOT a custom charge (customer paid more than currently owed)
			const hasCustomCharge = booking.has_custom_charge || false;

			// True Custom Charge: explicitly marked as custom_charge
			if (hasCustomCharge && netTotal > actualOwed + 0.01) {
				customChargeBookingsData.push({
					booking_id: booking.booking_id,
					customer_name: booking.customer_name,
					tour_date: booking.tour_date,
					payment_type: paymentType,
					booking_status: booking.booking_status,
					tourists: tourists,
					gross: gross,
					discount: discount,
					actual_owed: actualOwed,
					net: netTotal,
					gain_amount: netTotal - actualOwed
				});
			}
			// Overpayment: net > actualOwed but not a custom charge
			else if (paymentType !== 'Unpaid' && netTotal > actualOwed + 0.01 && !hasCustomCharge) {
				overpaymentBookingsData.push({
					booking_id: booking.booking_id,
					customer_name: booking.customer_name,
					tour_date: booking.tour_date,
					payment_type: paymentType,
					booking_status: booking.booking_status,
					tourists: tourists,
					gross: gross,
					discount: discount,
					actual_owed: actualOwed,
					net: netTotal,
					overpayment_amount: netTotal - actualOwed
				});
			}

			// Refunded bookings: only show refunds that actually reduced revenue below what was owed
			// If net >= actualOwed, the refund was just returning an overpayment
			if (paymentType !== 'Unpaid' && refundAmount > 0.01) {
				if (netTotal < actualOwed - 0.01) {
					// The actual revenue reduction is: min(refund, actualOwed - net)
					const revenueReduction = Math.min(refundAmount, actualOwed - netTotal);
					refundedBookingsData.push({
						booking_id: booking.booking_id,
						customer_name: booking.customer_name,
						tour_date: booking.tour_date,
						payment_type: paymentType,
						booking_status: booking.booking_status,
						tourists: tourists,
						gross: gross,
						discount: discount,
						actual_owed: actualOwed,
						net: netTotal,
						refunded_amount: revenueReduction
					});
				} else {
					// Excluded refunds: net >= actualOwed, refund was just returning overpayment
					excludedRefundsBookingsData.push({
						booking_id: booking.booking_id,
						customer_name: booking.customer_name,
						tour_date: booking.tour_date,
						payment_type: paymentType,
						booking_status: booking.booking_status,
						tourists: tourists,
						gross: gross,
						discount: discount,
						actual_owed: actualOwed,
						net: netTotal,
						refund_amount: refundAmount,
						reason: 'Net equals or exceeds gross - refund was returning overpayment'
					});
				}
			}

			// Prior Payments bookings: bookings where payment was made before the date range
			// These have Amount = 0 (no payment in this period) AND net_total < 0 (refund issued)
			// The prior payment amount = actualOwed (what was originally owed after discount)
			if (paymentType !== 'Unpaid' && amount < 0.01 && netTotal < -0.01) {
				// This booking had no payment in this date range but has a negative net
				// meaning payment was collected in a prior period and refund issued now
				priorPaymentsBookingsData.push({
					booking_id: booking.booking_id,
					customer_name: booking.customer_name,
					tour_date: booking.tour_date,
					payment_type: paymentType,
					booking_status: booking.booking_status,
					tourists: tourists,
					gross: gross,
					discount: discount,
					prior_payment: actualOwed
				});
			}

			// Other Period Payments: bookings where net < actualOwed but not due to refund or unpaid
			// This happens when payment was split across periods (purchase date view)
			if (paymentType !== 'Unpaid' && amount > 0.01 && netTotal < actualOwed - 0.01) {
				// Calculate what's already accounted for by refund
				let accountedLoss = 0;
				if (refundAmount > 0.01) {
					accountedLoss = Math.min(refundAmount, actualOwed - netTotal);
				}
				// The remaining gap is payments in other periods
				const otherPeriodGap = actualOwed - netTotal - accountedLoss;
				if (otherPeriodGap > 0.01) {
					otherPeriodPaymentsBookingsData.push({
						booking_id: booking.booking_id,
						customer_name: booking.customer_name,
						tour_date: booking.tour_date,
						payment_type: paymentType,
						booking_status: booking.booking_status,
						tourists: tourists,
						gross: gross,
						discount: discount,
						actual_owed: actualOwed,
						net: netTotal,
						other_period_amount: otherPeriodGap
					});
				}
			}

			// Discount bookings
			if (discount > 0) {
				discountBookingsData.push({
					booking_id: booking.booking_id,
					customer_name: booking.customer_name,
					tour_date: booking.tour_date,
					payment_type: paymentType,
					booking_status: booking.booking_status,
					tourists: tourists,
					gross: gross,
					discount: discount,
					net: netTotal
				});
			}
		});
	}

	// Refunded Modal Handlers
	$('#pbShowRefundedModal').on('click', function() {
		openRefundedModal();
	});

	$('#pbCloseRefundedModal').on('click', function() {
		$('#pbRefundedModal').addClass('hidden');
		// Reset to first tab when closing
		switchRefundedTab('included');
	});

	$('#pbRefundedModal').on('click', function(e) {
		if ($(e.target).is('#pbRefundedModal')) {
			$('#pbRefundedModal').addClass('hidden');
			// Reset to first tab when closing
			switchRefundedTab('included');
		}
	});

	// Refunded Modal Tab Switching
	$('.pb-refunded-tab').on('click', function() {
		const tab = $(this).data('tab');
		switchRefundedTab(tab);
	});

	function switchRefundedTab(tab) {
		// Update tab buttons
		$('.pb-refunded-tab').removeClass('border-red-500 text-red-600').addClass('border-transparent text-gray-500');
		if (tab === 'included') {
			$('#pbRefundedTabIncluded').removeClass('border-transparent text-gray-500').addClass('border-red-500 text-red-600');
		} else {
			$('#pbRefundedTabExcluded').removeClass('border-transparent text-gray-500').addClass('border-red-500 text-red-600');
		}

		// Show/hide tab content
		$('.pb-refunded-tab-content').addClass('hidden');
		if (tab === 'included') {
			$('#pbRefundedTabContentIncluded').removeClass('hidden');
		} else {
			$('#pbRefundedTabContentExcluded').removeClass('hidden');
		}
	}

	// Custom Charge Modal Handlers
	$('#pbShowCustomChargeModal').on('click', function() {
		openCustomChargeModal();
	});

	$('#pbCloseCustomChargeModal').on('click', function() {
		$('#pbCustomChargeModal').addClass('hidden');
	});

	$('#pbCustomChargeModal').on('click', function(e) {
		if ($(e.target).is('#pbCustomChargeModal')) {
			$('#pbCustomChargeModal').addClass('hidden');
		}
	});

	// Unpaid Modal Handlers
	$('#pbShowUnpaidModal').on('click', function() {
		openUnpaidModal();
	});

	$('#pbCloseUnpaidModal').on('click', function() {
		$('#pbUnpaidModal').addClass('hidden');
	});

	$('#pbUnpaidModal').on('click', function(e) {
		if ($(e.target).is('#pbUnpaidModal')) {
			$('#pbUnpaidModal').addClass('hidden');
		}
	});

	// Discount Modal Handlers
	$('#pbShowDiscountModal').on('click', function() {
		openDiscountModal();
	});

	$('#pbCloseDiscountModal').on('click', function() {
		$('#pbDiscountModal').addClass('hidden');
	});

	$('#pbDiscountModal').on('click', function(e) {
		if ($(e.target).is('#pbDiscountModal')) {
			$('#pbDiscountModal').addClass('hidden');
		}
	});

	// Dispute Reversals Modal Handlers
	$('#pbShowDisputeReversalsModal').on('click', function() {
		openDisputeReversalsModal();
	});

	$('#pbCloseDisputeReversalsModal').on('click', function() {
		$('#pbDisputeReversalsModal').addClass('hidden');
	});

	$('#pbDisputeReversalsModal').on('click', function(e) {
		if ($(e.target).is('#pbDisputeReversalsModal')) {
			$('#pbDisputeReversalsModal').addClass('hidden');
		}
	});

	// Prior Payments Modal Handlers
	$('#pbShowPriorPaymentsModal').on('click', function() {
		openPriorPaymentsModal();
	});

	$('#pbClosePriorPaymentsModal').on('click', function() {
		$('#pbPriorPaymentsModal').addClass('hidden');
	});

	$('#pbPriorPaymentsModal').on('click', function(e) {
		if ($(e.target).is('#pbPriorPaymentsModal')) {
			$('#pbPriorPaymentsModal').addClass('hidden');
		}
	});

	// Overpayment Modal Handlers
	$('#pbShowOverpaymentModal').on('click', function() {
		openOverpaymentModal();
	});

	$('#pbCloseOverpaymentModal').on('click', function() {
		$('#pbOverpaymentModal').addClass('hidden');
	});

	$('#pbOverpaymentModal').on('click', function(e) {
		if ($(e.target).is('#pbOverpaymentModal')) {
			$('#pbOverpaymentModal').addClass('hidden');
		}
	});

	// Other Period Payments Modal Handlers
	$('#pbShowOtherPeriodPaymentsModal').on('click', function() {
		openOtherPeriodPaymentsModal();
	});

	$('#pbCloseOtherPeriodPaymentsModal').on('click', function() {
		$('#pbOtherPeriodPaymentsModal').addClass('hidden');
	});

	$('#pbOtherPeriodPaymentsModal').on('click', function(e) {
		if ($(e.target).is('#pbOtherPeriodPaymentsModal')) {
			$('#pbOtherPeriodPaymentsModal').addClass('hidden');
		}
	});

	// Open Refunded Modal
	function openRefundedModal() {
		$('#pbRefundedModal').removeClass('hidden');
		renderRefundedModal();
	}

	// Open Custom Charge Modal
	function openCustomChargeModal() {
		$('#pbCustomChargeModal').removeClass('hidden');
		renderCustomChargeModal();
	}

	// Open Unpaid Modal
	function openUnpaidModal() {
		$('#pbUnpaidModal').removeClass('hidden');
		renderUnpaidModal();
	}

	// Open Discount Modal
	function openDiscountModal() {
		$('#pbDiscountModal').removeClass('hidden');
		renderDiscountModal();
	}

	// Open Dispute Reversals Modal
	function openDisputeReversalsModal() {
		$('#pbDisputeReversalsModal').removeClass('hidden');
		renderDisputeReversalsModal();
	}

	// Open Prior Payments Modal
	function openPriorPaymentsModal() {
		$('#pbPriorPaymentsModal').removeClass('hidden');
		renderPriorPaymentsModal();
	}

	// Open Overpayment Modal
	function openOverpaymentModal() {
		$('#pbOverpaymentModal').removeClass('hidden');
		renderOverpaymentModal();
	}

	// Open Other Period Payments Modal
	function openOtherPeriodPaymentsModal() {
		$('#pbOtherPeriodPaymentsModal').removeClass('hidden');
		renderOtherPeriodPaymentsModal();
	}

	// Render Refunded Modal
	function renderRefundedModal() {
		const data = refundedBookingsData;
		const excludedData = excludedRefundsBookingsData;
		
		// Calculate totals for included refunds
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.refunded_amount, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Calculate totals for excluded refunds
		const excludedTotalBookings = excludedData.length;
		const excludedTotalAmount = excludedData.reduce((sum, b) => sum + b.refund_amount, 0);
		const excludedTotalPax = excludedData.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards (for included tab)
		$('#pbRefundedTotalBookings').text(formatNumber(totalBookings));
		$('#pbRefundedTotalAmount').text(formatCurrency(totalAmount));
		$('#pbRefundedTotalPax').text(formatNumber(totalPax));

		// Update summary cards (for excluded tab)
		$('#pbExcludedRefundsTotalBookings').text(formatNumber(excludedTotalBookings));
		$('#pbExcludedRefundsTotalAmount').text(formatCurrency(excludedTotalAmount));
		$('#pbExcludedRefundsTotalPax').text(formatNumber(excludedTotalPax));

		// Update tab badges
		$('#pbRefundedTabBadge').text(totalBookings);
		$('#pbExcludedRefundsTabBadge').text(excludedTotalBookings);

		// Render included refunds table
		renderModalTable('Refunded', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'net', label: 'Net', align: 'right', format: 'currency' },
			{ key: 'refunded_amount', label: 'Refunded', align: 'right', format: 'currency', highlight: true }
		]);

		// Render excluded refunds table
		renderModalTable('ExcludedRefunds', excludedData, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'actual_owed', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'net', label: 'Net', align: 'right', format: 'currency' },
			{ key: 'refund_amount', label: 'Refund', align: 'right', format: 'currency', highlight: true, highlightColor: 'gray' }
		]);
	}

	// Render Custom Charge Modal
	function renderCustomChargeModal() {
		const data = customChargeBookingsData;
		
		// Calculate totals
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.gain_amount, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards
		$('#pbCustomChargeTotalBookings').text(formatNumber(totalBookings));
		$('#pbCustomChargeTotalAmount').text(formatCurrency(totalAmount));
		$('#pbCustomChargeTotalPax').text(formatNumber(totalPax));

		// Render table with pagination
		renderModalTable('CustomCharge', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'net', label: 'Net', align: 'right', format: 'currency' },
			{ key: 'gain_amount', label: 'Gain', align: 'right', format: 'currency', highlight: true, highlightColor: 'green' }
		]);
	}

	// Render Unpaid Modal
	function renderUnpaidModal() {
		const data = unpaidBookingsData;
		
		// Calculate totals
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.unpaid_amount, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards
		$('#pbUnpaidTotalBookings').text(formatNumber(totalBookings));
		$('#pbUnpaidTotalAmount').text(formatCurrency(totalAmount));
		$('#pbUnpaidTotalPax').text(formatNumber(totalPax));

		// Render table with pagination
		renderModalTable('Unpaid', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'discount', label: 'Discount', align: 'right', format: 'currency' },
			{ key: 'unpaid_amount', label: 'Unpaid', align: 'right', format: 'currency', highlight: true }
		]);
	}

	// Render Dispute Reversals Modal
	function renderDisputeReversalsModal() {
		const data = disputeReversalsData;

		$('#pbDisputeReversalsTotalCount').text(formatNumber(data.length));
		$('#pbDisputeReversalsTotalAmount').text(formatCurrency(data.reduce((sum, d) => sum + d.reversed_amount, 0)));

		renderModalTable('DisputeReversals', data, [
			{ key: 'booking_id',      label: 'Booking ID' },
			{ key: 'customer_name',   label: 'Customer' },
			{ key: 'dispute_amount',  label: 'Dispute Amount',  align: 'right', format: 'currency' },
			{ key: 'reversed_amount', label: 'Reversed Amount', align: 'right', format: 'currency', highlight: true },
			{ key: 'reason',          label: 'Reason' },
			{ key: 'lost_at',         label: 'Lost At' },
		]);
	}

	// Render Discount Modal
	function renderDiscountModal() {
		const data = discountBookingsData;
		
		// Calculate totals
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.discount, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards
		$('#pbDiscountTotalBookings').text(formatNumber(totalBookings));
		$('#pbDiscountTotalAmount').text(formatCurrency(totalAmount));
		$('#pbDiscountTotalPax').text(formatNumber(totalPax));

		// Render table with pagination
		renderModalTable('Discount', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'discount', label: 'Discount', align: 'right', format: 'currency', highlight: true },
			{ key: 'net', label: 'Net', align: 'right', format: 'currency' }
		]);
	}

	// Render Prior Payments Modal
	function renderPriorPaymentsModal() {
		const data = priorPaymentsBookingsData;
		
		// Calculate totals
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.prior_payment, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards
		$('#pbPriorPaymentsTotalBookings').text(formatNumber(totalBookings));
		$('#pbPriorPaymentsTotalAmount').text(formatCurrency(totalAmount));
		$('#pbPriorPaymentsTotalPax').text(formatNumber(totalPax));

		// Render table with pagination
		renderModalTable('PriorPayments', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'prior_payment', label: 'Prior Payment', align: 'right', format: 'currency', highlight: true, highlightColor: 'blue' }
		]);
	}

	// Render Overpayment Modal
	function renderOverpaymentModal() {
		const data = overpaymentBookingsData;

		// Calculate totals
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.overpayment_amount, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards
		$('#pbOverpaymentTotalBookings').text(formatNumber(totalBookings));
		$('#pbOverpaymentTotalAmount').text(formatCurrency(totalAmount));
		$('#pbOverpaymentTotalPax').text(formatNumber(totalPax));

		// Render table with pagination
		renderModalTable('Overpayment', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'net', label: 'Net', align: 'right', format: 'currency' },
			{ key: 'overpayment_amount', label: 'Overpayment', align: 'right', format: 'currency', highlight: true, highlightColor: 'green' }
		]);
	}

	// Render Other Period Payments Modal
	function renderOtherPeriodPaymentsModal() {
		const data = otherPeriodPaymentsBookingsData;

		// Calculate totals
		const totalBookings = data.length;
		const totalAmount = data.reduce((sum, b) => sum + b.other_period_amount, 0);
		const totalPax = data.reduce((sum, b) => sum + b.tourists, 0);

		// Update summary cards
		$('#pbOtherPeriodPaymentsTotalBookings').text(formatNumber(totalBookings));
		$('#pbOtherPeriodPaymentsTotalAmount').text(formatCurrency(totalAmount));
		$('#pbOtherPeriodPaymentsTotalPax').text(formatNumber(totalPax));

		// Render table with pagination
		renderModalTable('OtherPeriodPayments', data, [
			{ key: 'booking_id', label: 'Booking ID' },
			{ key: 'customer_name', label: 'Customer' },
			{ key: 'tour_date', label: 'Tour Date' },
			{ key: 'payment_type', label: 'Payment Type' },
			{ key: 'booking_status', label: 'Status' },
			{ key: 'tourists', label: 'Tourist', align: 'right', format: 'number' },
			{ key: 'gross', label: 'Gross', align: 'right', format: 'currency' },
			{ key: 'net', label: 'Net (This Period)', align: 'right', format: 'currency' },
			{ key: 'other_period_amount', label: 'Other Period', align: 'right', format: 'currency', highlight: true }
		]);
	}

	// Generic table renderer with pagination and search
	function renderModalTable(modalType, data, columns) {
		const tableBodyId = `pb${modalType}TableBody`;
		const paginationId = `pb${modalType}Pagination`;
		const paginationInfoId = `pb${modalType}PaginationInfo`;
		const searchId = `pb${modalType}Search`;
		const pageSizeId = `pb${modalType}PageSize`;

		let currentPage = 1;
		let pageSize = 25;
		let searchTerm = '';
		let filteredData = data;

		// Helper functions for badges
		function getPaymentTypeBadgeStyle(type) {
			const styles = {
				'Card': 'background-color: #2563eb; color: white;', // blue-600
				'Cash': 'background-color: #16a34a; color: white;', // green-600
				'Invoiced': 'background-color: #ca8a04; color: white;', // yellow-600
				'Billable': 'background-color: #0891b2; color: white;', // cyan-600
				'Wire': 'background-color: #4b5563; color: white;', // gray-600
				'Unpaid': 'background-color: #dc2626; color: white;', // red-600
				'TripAdvisor': 'background-color: #9333ea; color: white;', // purple-600
				'Headout': 'background-color: #4f46e5; color: white;', // indigo-600
				'GetYourGuide': 'background-color: #db2777; color: white;', // pink-600
				'FareHarbor': 'background-color: #0d9488; color: white;' // teal-600
			};
			return styles[type] || 'background-color: #6b7280; color: white;'; // gray-500
		}

		function getStatusBadgeStyle(status) {
			const styles = {
				'Booked': 'background-color: #16a34a; color: white;', // green-600
				'Cancelled': 'background-color: #dc2626; color: white;' // red-600
			};
			return styles[status] || 'background-color: #6b7280; color: white;'; // gray-500
		}

		function getBookingUrl(bookingId) {
			return `/${routePrefix}/booking/${bookingId}`;
		}

		// Search handler
		$(`#${searchId}`).off('keyup').on('keyup', function() {
			searchTerm = $(this).val().toLowerCase();
			filteredData = data.filter(row => {
				return Object.values(row).some(val => 
					String(val).toLowerCase().includes(searchTerm)
				);
			});
			currentPage = 1;
			renderPage();
		});

		// Page size handler
		$(`#${pageSizeId}`).off('change').on('change', function() {
			pageSize = parseInt($(this).val());
			currentPage = 1;
			renderPage();
		});

		function renderPage() {
			const totalPages = Math.ceil(filteredData.length / pageSize);
			const startIdx = (currentPage - 1) * pageSize;
			const endIdx = Math.min(startIdx + pageSize, filteredData.length);
			const pageData = filteredData.slice(startIdx, endIdx);

			// Render table rows
			const $tbody = $(`#${tableBodyId}`);
			$tbody.empty();

			if (pageData.length === 0) {
				$tbody.append(`
					<tr>
						<td colspan="${columns.length}" class="px-4 py-8 text-center text-gray-500">
							No bookings found
						</td>
					</tr>
				`);
			} else {
				pageData.forEach(row => {
					let rowHtml = '<tr class="hover:bg-gray-50">';
					columns.forEach(col => {
						const value = row[col.key];
						let displayValue = value;

						// Special handling for booking_id - make it clickable
						if (col.key === 'booking_id') {
							displayValue = `<a href="${getBookingUrl(value)}" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">${value}</a>`;
						}
						// Special handling for payment_type - add badge
						else if (col.key === 'payment_type') {
							const badgeStyle = getPaymentTypeBadgeStyle(value);
							displayValue = `<span class="inline-block px-2 py-1 text-xs font-semibold rounded whitespace-nowrap" style="${badgeStyle}">${value || ''}</span>`;
						}
						// Special handling for booking_status - add badge
						else if (col.key === 'booking_status') {
							const badgeStyle = getStatusBadgeStyle(value);
							displayValue = `<span class="inline-block px-2 py-1 text-xs font-semibold rounded whitespace-nowrap" style="${badgeStyle}">${value || ''}</span>`;
						}
						// Format currency
						else if (col.format === 'currency') {
							displayValue = formatCurrency(value);
						}
						// Format number
						else if (col.format === 'number') {
							displayValue = formatNumber(value);
						}

						const align = col.align || 'left';
						
						// Support dynamic highlight colors
						let highlightClass = '';
						if (col.highlight) {
							if (col.highlightColor === 'green') {
								highlightClass = 'font-semibold text-green-600';
							} else if (col.highlightColor === 'orange') {
								highlightClass = 'font-semibold text-orange-600';
							} else {
								highlightClass = 'font-semibold text-red-600';
							}
						}
						
						rowHtml += `<td class="px-4 py-3 text-${align} text-sm ${highlightClass}">${displayValue}</td>`;
					});
					rowHtml += '</tr>';
					$tbody.append(rowHtml);
				});
			}

			// Update pagination info
			$(`#${paginationInfoId}`).text(`Showing ${startIdx + 1}-${endIdx} of ${filteredData.length} bookings`);

			// Render pagination buttons
			renderPaginationButtons(paginationId, currentPage, totalPages);
		}

		function renderPaginationButtons(containerId, current, total) {
			const $container = $(`#${containerId}`);
			$container.empty();

			if (total <= 1) return;

			const maxButtons = 7;
			let startPage = Math.max(1, current - Math.floor(maxButtons / 2));
			let endPage = Math.min(total, startPage + maxButtons - 1);

			if (endPage - startPage < maxButtons - 1) {
				startPage = Math.max(1, endPage - maxButtons + 1);
			}

			// Previous button
			$container.append(`
				<button class="px-3 py-1 mx-1 rounded ${current === 1 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-white border hover:bg-gray-50'}" 
					${current === 1 ? 'disabled' : ''} data-page="${current - 1}">
					<i class="fas fa-chevron-left"></i>
				</button>
			`);

			// First page
			if (startPage > 1) {
				$container.append(`
					<button class="px-3 py-1 mx-1 rounded bg-white border hover:bg-gray-50" data-page="1">1</button>
				`);
				if (startPage > 2) {
					$container.append(`<span class="px-2">...</span>`);
				}
			}

			// Page numbers
			for (let i = startPage; i <= endPage; i++) {
				$container.append(`
					<button class="px-3 py-1 mx-1 rounded ${i === current ? 'bg-orange-500 text-white' : 'bg-white border hover:bg-gray-50'}" 
						data-page="${i}">${i}</button>
				`);
			}

			// Last page
			if (endPage < total) {
				if (endPage < total - 1) {
					$container.append(`<span class="px-2">...</span>`);
				}
				$container.append(`
					<button class="px-3 py-1 mx-1 rounded bg-white border hover:bg-gray-50" data-page="${total}">${total}</button>
				`);
			}

			// Next button
			$container.append(`
				<button class="px-3 py-1 mx-1 rounded ${current === total ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-white border hover:bg-gray-50'}" 
					${current === total ? 'disabled' : ''} data-page="${current + 1}">
					<i class="fas fa-chevron-right"></i>
				</button>
			`);

			// Pagination click handlers
			$container.find('button').off('click').on('click', function() {
				if (!$(this).prop('disabled')) {
					currentPage = parseInt($(this).data('page'));
					renderPage();
				}
			});
		}

		// Initial render
		renderPage();
	}
</script>