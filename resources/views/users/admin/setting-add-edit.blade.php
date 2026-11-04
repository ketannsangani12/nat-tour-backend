@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
	@endphp
	<div class="page-content py-6 bg-[#F9FAFB]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full max-w-full px-3">
					<div
						class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
						<div class="d-flex align-items-start">
							<div class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-y-auto"
								id="v-pills-tab" role="tablist" aria-orientation="vertical">
								<button class="nav-link tabs-custom-link active" id="v-pills-settings-tab"
									data-bs-toggle="pill" data-bs-target="#v-pills-settings" type="button" role="tab"
									aria-controls="v-pills-settings" aria-selected="true">Settings</button>
							</div>
							<div class="tab-content" id="v-pills-tabContent">
								<div class="tab-pane fade show active" id="v-pills-settings" role="tabpanel"
									aria-labelledby="v-pills-settings-tab" tabindex="0">
									<div class="page-content p-5 border border-[#E5E7EB] rounded-b-md">
										<div class="content-title">
											<h2 class="section-title">Settings</h2>
										</div>
										<div class="main-content">
											@if(isCentralDomain())
												@foreach($settings as $setting)
													@if($setting->key === 'MAINTENANCE_MODE')
														<div class="form-switch form-switch-wrap document-panel">
															<label class="form-custom-label inline-block">Maintenance Mode</label>
															<input class="form-check-input setting" type="checkbox"
																id="{{ $setting->id }}" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
														</div>
													@endif
												@endforeach

												@php $stripeSecretSetting = $settings->firstWhere('key', 'STRIPE_WEBHOOK_SECRET'); @endphp
												@if($stripeSecretSetting)
													<form method="post"
														class="mt-2"
														action="{{ route(prefixedRouteName($routePrefix . 'setting.update')) }}"
														onsubmit="return submitForm(this)"
														data-redirect-on-success="{{ route(prefixedRouteName($routePrefix . 'setting.edit')) }}"
														data-on-success-msg="Stripe Webhook Secret updated successfully">
														<div class="flex flex-wrap -mx-3">
															<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
																<label class="form-label form-custom-label">Stripe Webhook Secret <req>*</req></label>
																<sup class="text-xs">Central signing secret from Stripe dashboard (whsec_...)</sup>
																<div class="input-group flex items-center">
																	<input class="form-control form-custom-input" type="password"
																		name="{{ $stripeSecretSetting->id }}"
																		id="stripe_webhook_secret_input"
																		value="{{ $stripeSecretSetting->value }}"
																		placeholder="whsec_..." required>
																	<div class="input-group-text ml-1">
																		<button type="button" class="btn btn-sm toggle-password btn-info-small"
																			data-toggle="#stripe_webhook_secret_input">
																			<i class="fa fa-fw fa-eye"></i>
																		</button>
																	</div>
																</div>
															</div>
														</div>
														<div class="mt-3 flex justify-end">
															<button class="btn btn-main btn-primary-small">Update Webhook Secret</button>
														</div>
													</form>
												@endif
											@else
											<form method="post" action="{{ $formSubmitRoute }}"
												onsubmit="return submitForm(this)"
												data-redirect-on-success="{{ route(prefixedRouteName($routePrefix . 'setting.edit')) }}"
												data-on-success-msg="Setting updated successfully">

												<div class="flex flex-wrap -mx-3">
													@foreach($settings as $setting)

														@if(in_array($setting->key, ['API_VERSION', 'OLD_BOOKING_ID', 'KIOSK_API_VERSION', 'BOOKING_ID_PREFIX', 'REMINDER_EMAIL_DAYS', 'AUTO_CHECKINS', 'PAYMENT_LINK_DAYS', 'PAYMENT_LINK_REMINDER_EMAIL_DAYS', 'GA4_CODE']))
															<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
																<label for="name" class="form-label form-custom-label">
																	@if($setting->key == 'API_VERSION')
																		NAT_API_VERSION
																	@elseif($setting->key == 'IS_NO_HIDE')
																		NAT_FORCE_UPDATE
																	@else
																		{{ $setting->display_name }}
																	@endif
																	@if ($setting->key != 'GA4_CODE')
																		<req>*</req>
																	@endif
																</label>
																<sup class="text-xs">{{ $setting->comment }}</sup>
																<input class="form-control form-custom-input"
																	name="{{ $setting->id }}" id="{{ $setting->id }}" maxlength="30"
																	value="{{ $setting->value }}" placeholder="Enter Value"
																	{{ $setting->key != 'GA4_CODE' ? 'required' : '' }}>
															</div>
														@elseif($setting->key === 'DEFAULT_BOOKING_SOURCE')
															<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
																<label for="{{ $setting->id }}" class="form-label form-custom-label">
																	{{ $setting->display_name }}
																</label>
																<sup class="text-xs">{{ $setting->comment }}</sup>
																<select class="form-control form-custom-input"
																	name="{{ $setting->id }}" id="{{ $setting->id }}">
																	<option value="" {{ in_array($setting->value, ['phone_call','walk_in','email','other']) ? '' : 'selected' }}>— Please select —</option>
																	<option value="phone_call" {{ $setting->value === 'phone_call' ? 'selected' : '' }}>Phone Call</option>
																	<option value="walk_in"   {{ $setting->value === 'walk_in'   ? 'selected' : '' }}>Walk-In</option>
																	<option value="email"     {{ $setting->value === 'email'     ? 'selected' : '' }}>Email</option>
																	<option value="other"     {{ $setting->value === 'other'     ? 'selected' : '' }}>Other</option>
																</select>
															</div>
														@endif

													@endforeach
												</div>
												<div class="mt-3 flex justify-end"><button
														class="btn btn-main btn-primary-small">Update</button></div>
											</form>
											<hr>
											<div class="flex flex-wrap -mx-3 mt-3">
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<h5
														class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">
														General</h5>
													@foreach($settings as $setting)
														{{-- PLATFORM_HELD_PAYOUT_ENABLED (Payout Simplification, 2027+ bookings) is
														deliberately NOT rendered here — toggling it by mistake from this generic
														on/off switch would change how live payments settle, so it's changed only
														via a direct database update (see SettingController::update()'s matching
														guard, which also refuses to accept it from this form). --}}
														@if(in_array($setting->key, ['IS_NO_HIDE', 'KIOSK_FORCE_UPDATE', 'SIGNED_RECEIPT', 'CART_VIEW', 'IS_NO_HIDE', 'CASH_FEES', 'INVOICE_ENABLE_DISABLE', 'SOLDOUT_SLOT_VISIBILITY', 'SLOT_VISIBILITY', 'PACKAGE_RELATION', 'SWITCH_PACKAGE', 'CUSTOM_CHARGE', 'HOTEL_PICKUP', 'CUSTOM_FIELDS', 'CALL_TO_BOOK', 'DEPARTMENT_PACKAGE', 'MANAGER_REQUEST', 'PAYMENT_LINK', 'BLACKOUT_DATES_ENABLED', 'BOARD', 'AGENDA_CHECKIN_OVERVIEW', 'AGENDA_GUIDE_COUNT', 'DISABLE_AFFILIATE_CARD_SAVE', 'DISABLE_CARD_SAVE', 'CASH_PRICE', 'AUTO_PAYOUT', 'DISPUTE_REVERSAL', 'BOOKING_SOURCE_AFFILIATE', 'ALLOW_NEXT_YEAR_BOOKINGS']))
															<div class="form-switch form-switch-wrap document-panel">
																<label class="form-custom-label inline-block">
																	@if($setting->key == 'IS_NO_HIDE')
																		NAT_FORCE_UPDATE
																	@else
																		{{ $setting->display_name }}
																	@endif</label>
																<input class="form-check-input setting" type="checkbox"
																	id="{{ $setting->id }}" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
															</div>
														@endif
													@endforeach
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													@foreach($settings as $setting)
														@if(in_array($setting->key, ['AGENDA_VIEW', 'MIN_MAX_APPLY']))
															@if($setting->key == 'AGENDA_VIEW')
																<h5
																	class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">
																	{{ $setting->display_name }}
																</h5>
																<div class="form-switch form-switch-wrap document-panel">
																	<label class="form-custom-label inline-block">Time Slots</label>
																	<input class="form-check-input setting-categorized" type="checkbox"
																		value="1" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
																</div>
																<div class="form-switch form-switch-wrap document-panel">
																	<label class="form-custom-label inline-block">Hotels</label>
																	<input class="form-check-input setting-categorized" type="checkbox"
																		value="2" name="{{ $setting->id }}" {{ $setting->value == 2 ? 'checked' : '' }}>
																</div>
															@elseif($setting->key == 'MIN_MAX_APPLY')
																<h5
																	class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">
																	{{ $setting->display_name }}
																</h5>
																<div class="form-switch form-switch-wrap document-panel">
																	<label class="form-custom-label inline-block">All</label>
																	<input class="form-check-input setting-categorized" type="checkbox"
																		value="1" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
																</div>
																<div class="form-switch form-switch-wrap document-panel">
																	<label class="form-custom-label inline-block">Backend</label>
																	<input class="form-check-input setting-categorized" type="checkbox"
																		value="2" name="{{ $setting->id }}" {{ $setting->value == 2 ? 'checked' : '' }}>
																</div>
																<div class="form-switch form-switch-wrap document-panel">
																	<label class="form-custom-label inline-block">Frontend</label>
																	<input class="form-check-input setting-categorized" type="checkbox"
																		value="3" name="{{ $setting->id }}" {{ $setting->value == 3 ? 'checked' : '' }}>
																</div>
																<div class="form-switch form-switch-wrap document-panel">
																	<label class="form-custom-label inline-block">POS</label>
																	<input class="form-check-input setting-categorized" type="checkbox"
																		value="4" name="{{ $setting->id }}" {{ $setting->value == 4 ? 'checked' : '' }}>
																</div>
															@else
																<div class="form-switch form-switch-wrap document-panel">
																	<label
																		class="form-custom-label inline-block">{{ $setting->display_name }}</label>
																	<input class="form-check-input setting" type="checkbox"
																		id="{{ $setting->id }}" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
																</div>
															@endif
														@endif

														@if(in_array($setting->key, ['POS_VIEW']))
															<h5
																class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">
																POS</h5>

															<div class="form-switch form-switch-wrap document-panel">
																<label
																	class="form-custom-label inline-block">{{ $setting->key }}</label>
																<input class="form-check-input setting" type="checkbox"
																	id="{{ $setting->id }}" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
															</div>
														@endif
														@if(in_array($setting->key, ['CUSTOMER_INFO_MANDATORY']))
															<div class="form-switch form-switch-wrap document-panel">
																<label
																	class="form-custom-label inline-block">{{ $setting->key }}</label>
																<input class="form-check-input setting" type="checkbox"
																	id="{{ $setting->id }}" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
															</div>
														@endif

														@if(in_array($setting->key, ['CDR_PACKAGES']))
															<h5
																class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">
																Standard Date Rate</h5>

															<div class="form-switch form-switch-wrap document-panel">
																<label
																	class="form-custom-label inline-block">{{ $setting->display_name }}</label>
																<input class="form-check-input setting-categorized" type="checkbox"
																	value="1" data-setting-name="{{ $setting->key }}"
																	name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
															</div>
														@endif
														@if(in_array($setting->key, ['CDR_AFFILIATES']))
															<div class="form-switch form-switch-wrap document-panel">
																<label
																	class="form-custom-label inline-block">{{ $setting->display_name }}</label>
																<input class="form-check-input setting-categorized" type="checkbox"
																	value="1" data-setting-name="{{ $setting->key }}"
																	name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
															</div>
														@endif
														

													@endforeach

													{{-- Third Party Platforms Section --}}
													<h5
														class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">
														Third Party Platforms</h5>
													@foreach($settings as $setting)
														@if(in_array($setting->key, ['ENABLE_TRIPADVISOR', 'ENABLE_GETYOURGUIDE', 'ENABLE_FAREHARBOR', 'ENABLE_HEADOUT', 'ENABLE_CHD', 'ENABLE_LAGUNACREEK']))
															<div class="form-switch form-switch-wrap document-panel">
																<label class="form-custom-label inline-block">
																	@if($setting->key == 'ENABLE_TRIPADVISOR')
																		TripAdvisor
																	@elseif($setting->key == 'ENABLE_HEADOUT')
																		Headout
																	@elseif($setting->key == 'ENABLE_GETYOURGUIDE')
																		GetYourGuide
																	@elseif($setting->key == 'ENABLE_FAREHARBOR')
																		FareHarbor
																	@elseif($setting->key == 'ENABLE_CHD')
																		CHD
																	@elseif($setting->key == 'ENABLE_LAGUNACREEK')
																		Lagunacreek
																	@endif
																</label>
																<input class="form-check-input setting" type="checkbox"
																	id="{{ $setting->id }}" name="{{ $setting->id }}" {{ $setting->value == 1 ? 'checked' : '' }}>
															</div>
														@endif
													@endforeach
												</div>
											</div>
										@endif {{-- isCentralDomain --}}
										</div> {{-- /.main-content --}}
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
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"
	integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN"
	crossorigin="anonymous"></script>
@section('css-custom')

@section('css-libraries')
	@if(App::environment('local'))
		<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
		<link href="{{ asset('plugins/bootstrap-notify/animate.min.css') }}" rel="stylesheet">
	@else
		<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.2.3/animate.min.css" rel="stylesheet">
	@endif
@endsection

@section('js-custom')
	<script type="text/javascript">
		var updateSettingUrl = "{{ route(prefixedRouteName($routePrefix . 'setting.update')) }}";
	</script>
	<script src="{{ asset('js/admin/settings.js') }}"></script>
	<x-delete-record-js-file />
@endsection

@section('js-libraries')
	@if(App::environment('local'))
		<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
		<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
		<script src="{{ asset('plugins/bootstrap-notify/bootstrap-notify.min.js') }}"></script>
	@else
		<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
		<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/mouse0270-bootstrap-notify/3.1.5/bootstrap-notify.min.js"></script>
	@endif

	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
@endsection