<div class="row action-row">
	<div class="w-full max-w-full booking-details relative flex items-center {{ (cartView() == 1 && empty($trans) && !empty($bookingM->cart_id) && $bookingM->statusm != 'Cancelled' && $paymentsCountOfCart == 0 && !empty($cartBookingIds)) ? 'booking-actions-button-small' : 'booking-actions-button-large' }}">
		<div class="w-full booking-actions {{ (userType() != 'Affiliate' && $switchPackage == '1' && $bookingM->statusm == 'Booked') ? 'justify-content-between' : ''  }}">
			@if($bookingM->is_check_in)
				@if ($bookingM->statusm == 'Booked' && session('userType') != 'Affiliate')
					@if($bookingM->bundle_package == 0)
						@if($bookingM->checkin_type == 1)
							@include('users.common.partials.booking.tickets._tickets_all')
						@else
							@include('users.common.partials.booking.checkins._checkins_all')
						@endif
					@else
						@include('users.common.partials.booking.bundle_package._checkins_all')
					@endif
				@endif
			@endif


			@if ($managerRequestEnable['Reschedule'] == 'Yes')
				<div class="reschedule inline-block align-middle" id="reschedule-btn-wrapper">
					<button type="button" id="reschedule-btn" class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" data-bs-toggle="modal" data-bs-target="#modal-reschedule" {{ ($affiliateFeature == 0 || $bookingM->statusm=="Cancelled") ? "disabled" : "" }}><i class="fas fa-calendar-alt mr-2"></i> Reschedule</button>
				</div>
			@endif

			@if($switchPackage == '1' && $managerRequestEnable['Switch'] == 'Yes')
				@php $switchDisabled = $isBookingHasAddons || isSwitchPackageDisabled($bookingM, $trans,$paymentTrans,$refundTrans,$packageSwitchedCount,$pricedAddons,$payLaterAddons) || $pendingRemainingLink; @endphp
				<div class="reschedule inline-block align-middle" id="switch-btn-wrapper">
					<button type="button" id="switch-btn" class="text-xs {{ $switchDisabled ? '' : 'hover:bg-white hover:text-[#ED9C45]' }} font-medium text-white border border-[#ED9C45] bg-[#ED9C45] px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center"
						data-bs-toggle="modal"
						data-bs-target="#modal-switch-package"
						{{ $switchDisabled ? 'disabled' : '' }}
						{{ $pendingRemainingLink ? 'title=\'Switch is disabled while a pending payment link exists\'' : '' }}><i
						class="fas fa-exchange-alt mr-2"></i> Switch
					</button>
				</div>
			@endif
			@if($managerRequestEnable['Rebook'] == 'Yes')
			<div class="rebook inline-block align-middle">
				<button type="button" class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" onclick="newBookingForCust({{ $bookingM->customer_id . ', ' . $bookingM->id }}, 1)" {{ ($affiliateFeature == 0) ? "disabled" : "" }}><i class="fas fa-sync-alt mr-2"></i> Rebook</button>
			</div>
			@endif
			@if($managerRequestEnable['Cancel'] == 'Yes')
			<div class="cbooking inline-block align-middle">
				<button class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" form="" data-bs-toggle="modal" data-bs-target="#cancelBooking" {{ isCancelBookingDisabled($bookingM, $affiliateFeature, $customerData) ? 'disabled' : '' }}><i class="fas fa-times mr-2"></i> Cancel Booking</button>
			</div>
			@endif
			@if (isRestoreBookingDisplay($bookingM,$isRestoreForCard,$customChargePrice,$pricedAddons))
			<div class="rbooking inline-block align-middle">
				<button class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" onclick="return restoreBooking('{{ $bookingM->id }}');" {{ ($affiliateFeature == 0) ? "disabled" : "" }}><i class="fas fa-sync-alt mr-2"></i>
					Restore Booking
				</button>
			</div>
			@endif
			@if ($bookingM->is_affiliate == 'Yes')
			<div class="affilatebooking inline-block align-middle">
				<button type="button" data-bs-toggle="modal" data-bs-target="#modal_affilate" class="text-xs {{ (isMakePaymentDisabled($pay, $bok, $trans, $bookingM) && empty($pendingRemainingLink)) ? '' : 'hover:bg-white hover:text-[#ED9C45]' }} font-medium text-white  border border-[#ED9C45] bg-[#ED9C45] px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center"
						{{ (isMakePaymentDisabled($pay, $bok, $trans, $bookingM) && empty($pendingRemainingLink)) ? 'disabled' : '' }} >
							<i class="fa fa-credit-card mr-2"></i>
						Make Payment
				</button>
			</div>
			@endif

			@if ($bookingM->is_affiliate == 'No')
			<div class="newbooking inline-block align-middle">
				<button type="button" data-bs-toggle="modal" data-bs-target="#modal_admin" class="text-xs font-medium {{ (isMakePaymentDisabled($pay, $bok, $trans, $bookingM) && empty($pendingRemainingLink)) ? '' : 'hover:bg-white hover:text-[#ED9C45]' }} text-white border border-[#ED9C45] bg-[#ED9C45]  px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center"
						{{ (isMakePaymentDisabled($pay, $bok, $trans, $bookingM) && empty($pendingRemainingLink)) ? 'disabled' : '' }} >
							<i class="fa fa-credit-card mr-2"></i>
						Make Payment
				</button>
			</div>
			@endif

			@if ($bookingM->is_affiliate == 'No')
			<div class="editbooking inline-block align-middle">
				<button type="button" data-bs-toggle="modal" data-bs-target="#edit_booking" class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center"  {{ isEditBookingDisabled($bookingM, $affiliateFeature, $transP) ? 'disabled' : '' }} ><i class="fas fa-pencil-alt mr-2"></i> Edit booking</button>
			</div>
			@endif
			@if ($bookingM->is_affiliate == 'Yes')
			<div class="editbooking inline-block align-middle">
				<button type="button" data-bs-toggle="modal" data-bs-target="#edit_booking" class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" {{ isEditBookingDisabled($bookingM, $affiliateFeature, $transP) ? 'disabled' : '' }} ><i class="fas fa-pencil-alt mr-2"></i> Edit booking</button>
			</div>
			@endif
			@if (session('userType') != 'Affiliate')
				@if(!$gratuitySetting)
				<div class="tip inline-block align-middle hidden">
					<button type="button" data-bs-toggle="modal" data-bs-target="#tipModal" class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center"><i class="fa fa-credit-card mr-2"></i>Tip</button>
				</div>
				@endif
				@if($managerRequestEnable['Refund'] == 'Yes')
				<div class="crefund inline-block align-middle">
					<button class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" form="" data-bs-toggle="modal" data-bs-target="#refundBooking"
						{{ ($Refund_Amount == $paymentOnlyCheck) ? 'disabled' : '' }}
						{{ empty($transP) ? 'disabled' : '' }}
						{{(count($paymentTrans) == 0 && !empty($transP) && $transP->payment_type != 1 && $transP->payment_type != 0 && $transP->via == 'Card'|| (string)$Refund_Amount == (string)$paymentOnlyCheck)  ? 'disabled' : '' }}
						{{ (!empty($transP) && $transP->via != 'Card' && $transP->payment_type != 1 && $Refund_Amount == $bookingM->booking_total)  ? 'disabled' : '' }}
						{{ (!empty($transP) && $transP->via != 'Card' && $transP->payment_type == 1 && $Refund_Amount == $paymentOnlyCheck)  ? 'disabled' : '' }} {{ (($bookingM->via == "TripAdvisor" && $bookingM->tripadvisor_id != null) || ($bookingM->via == "GetYourGuide" && $bookingM->get_your_guide_booking_id != null) || (isset($bookingM->ota_title) && ($bookingM->via === $bookingM->ota_title) && $bookingM->ota_booking_id)) ? 'disabled' : '' }}>
						<i class="fas fa-undo mr-2"></i>
						Refund
					</button>
				</div>
				@endif
			@endif
			@php
				$isEnableCustomAddon = IsAddOnCustomChargeVisibile();
			@endphp
			{{-- Legacy TripAdvisor/GetYourGuide bookings (pre-unified ota_reference_booking field,
				 identified by viator_bookingRef/get_your_guide_reference_booking) are excluded —
				 only ota_reference_booking-based OTA bookings support custom charge. --}}
			@if($isCustomCharge == 1 && $isEnableCustomAddon['customChargeEnabled'] == 1 && ($bok <= $pay || $paymentOnlyCheck == 0) && empty($bookingM->tripadvisor_id) && empty($bookingM->get_your_guide_booking_id))
				<div class="ccustomcharge inline-block align-middle">
					<button class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center"
						id="addCustomCharge" data-bs-toggle="modal"
						data-bs-target="#customChargeBooking">
						<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
								d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z">
							</path>
						</svg>
						Custom Charge
					</button>
				</div>
			@endif
			@if (cartView() == 1 && empty($trans) && $bookingM->cart_id!='' && $bookingM->statusm != 'Cancelled' && $paymentsCountOfCart==0 && !empty($cartBookingIds))
				<div class="newbooking inline-block align-middle">
					<button type="button" class="text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg flex items-center" id="combo-payment-button" >
						<i class="fa fa-credit-card mr-2"></i>
						Make Cart Payment
					</button>
				</div>
			@endif
		</div>
	</div>
</div>