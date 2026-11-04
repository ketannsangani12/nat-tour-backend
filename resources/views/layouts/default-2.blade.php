@php
$setting = hasSetting('INVOICE_ENABLE_DISABLE');
$posSetting = hasSetting('POS_VIEW');
$boardSetting = hasSetting('BOARD');
$autoPayoutSetting = hasSetting('AUTO_PAYOUT');
$routePrefix = routePrefix();
$userType = userType();
$userRole = checkUserRole();
$currencySettings = [
	'code' => currencyFormation(true, false),
	'symbol' => currencyFormation(false, true),
	'separator' => ',',
	'decimal' => '.',
	'precision' => 2,
];
$notificationCount = auth()->user()->unreadNotifications()->count();
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<script>
		// We pre-filled your app ID in the widget URL: 'https://widget.intercom.io/widget/o658qwaz'
		(function() {
			var w = window;
			var ic = w.Intercom;
			if (typeof ic === "function") {
				ic('reattach_activator');
				ic('update', w.intercomSettings);
			} else {
				var d = document;
				var i = function() {
					i.c(arguments);
				};
				i.q = [];
				i.c = function(args) {
					i.q.push(args);
				};
				w.Intercom = i;
				var l = function() {
					var s = d.createElement('script');
					s.type = 'text/javascript';
					s.async = true;
					s.src = 'https://widget.intercom.io/widget/o658qwaz';
					var x = d.getElementsByTagName('script')[0];
					x.parentNode.insertBefore(s, x);
				};
				if (document.readyState === 'complete') {
					l();
				} else if (w.attachEvent) {
					w.attachEvent('onload', l);
				} else {
					w.addEventListener('load', l, false);
				}
			}
		})();
	</script>
	
	<meta name="google-site-verification" content="Mh5XbOYqjxpvzLMc-VEuY2gstDIjS4_7yReDuyT0ob4" />
	<!-- Global site tag (gtag.js) - Google Analytics -->
	<script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script>
	<script>
		window.dataLayer = window.dataLayer || [];

		function gtag() {
			dataLayer.push(arguments);
		}
		gtag('js', new Date());
		gtag('config', 'UA-223629951-1');
	</script>

	<!-- Google Tag Manager -->
	<script>
		(function(w, d, s, l, i) {
			w[l] = w[l] || [];
			w[l].push({
				'gtm.start': new Date().getTime(),
				event: 'gtm.js'
			});
			var f = d.getElementsByTagName(s)[0],
				j = d.createElement(s),
				dl = l != 'dataLayer' ? '&l=' + l : '';
			j.async = true;
			j.src = 'https://www.googletagmanager.com/gtm.js?id=' + i + dl;
			f.parentNode.insertBefore(j, f);
		})(window, document, 'script', 'dataLayer', 'GTM-W4CHJCP');
	</script><!-- End Google Tag Manager -->

	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}" />
	<title>{{ $page_title }} | {{ config('app.name') }}</title>
	<link href="{{ asset('images/favicon.png') }}" rel="icon">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

	<link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">

	@yield('css-libraries')

	<script>
		var rootPath = "{{ asset('') }}";

		function noPhoto(elImg) {
			elImg.onerror = null;
			elImg.src = (typeof rootPath == 'undefined' ? '/' : rootPath) + 'images/no-photo.png';
		}
	</script>
	<link href="{{ asset('css/app.css') }}" rel="stylesheet">

	<style>
		[v-cloak] {
			display: none !important;
		}
		#tour_slot_date {background-image:url('{{ asset('images/calendar.png') }}');}
		#affiliatecust {background-image:url('{{ asset('images/arrowDown.png') }}');}
		#search, #searchhelp {background-image:url('{{ asset('images/search.png') }}');}
		.select2-container--default .select2-selection .select2-selection__arrow b, 
		.select2-container--default.select2-container--open .select2-selection .select2-selection__arrow b {background-image:url('{{ asset('images/arrowDown.png') }}');}
		.alert-dismissible .btn-close {position: absolute; top: 0; right: 0; z-index: 2; padding: 1.25rem 1rem; box-sizing: content-box; width: 1em; height: 1em; padding: .25em .25em; color: #000; background: transparent url(data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e) center / 1em auto no-repeat; border: 0; border-radius: .25rem; opacity: .5;}
	</style>
</head>

<body class="font-inter">
	<!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W4CHJCP" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->
	@if(Request::routeIs($routePrefix . '.booking.day-time-slots'))
	<div class="h-[81px]">
	</div>	
	@endif
	<header class="border-b border-[#E5E7EB] py-4 {{ Request::routeIs($routePrefix . '.booking.day-time-slots') ? 'fixed top-0 w-full bg-white z-[1050] ' : 'relative z-20  ' }} {{ request('modal') ? 'hidden' : '' }}">
        <div class="container">
            <div class="flex flex-wrap items-center">
                <!-- Logo & Hamburger -->
				<div class="flex items-center order-1 text-left max-w-[80px]">
					<div class="me-4 cursor-pointer ">
						<div id="hamburgerToggle" class="space-y-1">
							<span class="line1 block w-5 h-0.5 bg-[#4B5563] transition-all duration-300 ease-in-out relative"></span>
							<span class="line2 block w-5 h-0.5 bg-[#4B5563] transition-all duration-300 ease-in-out relative"></span>
							<span class="line3 block w-5 h-0.5 bg-[#4B5563] transition-all duration-300 ease-in-out relative"></span>
						</div>
					</div>

					<div id="sidebarMenu" class="fixed top-[75px] left-[-500px] z-[9999] w-[115px] h-full overflow-y-auto bg-[#2F9DD5] transition-all duration-300">
						<ul>
							@if($posSetting == 1)
								@can('pos')
								<li class="max-w-[98px] text-center h-[86px] mx-auto p-2">
									<a href="{{ route(prefixedRouteName('pos')) }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
										<svg class="mx-auto mb-2.5" width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8.02 1.82258L2.63 6.02258C1.73 6.72258 1 8.21258 1 9.34258V16.7526C1 19.0726 2.89 20.9726 5.21 20.9726H16.79C19.11 20.9726 21 19.0726 21 16.7626V9.48258C21 8.27258 20.19 6.72258 19.2 6.03258L13.02 1.70258C11.62 0.722584 9.37 0.772584 8.02 1.82258Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 16.9727V13.9727" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										POS
									</a>
								</li>
								@endcan
							@endif
							@if($userRole != 'tour-guide')
								@can('tips-center')
									<li class="max-w-[98px] text-center h-[100px] mx-auto p-2 hidden">
										<a href="{{ route(prefixedRouteName('tip-center')) }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
											<svg class="mx-auto mb-2.5" width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M8.02 1.82258L2.63 6.02258C1.73 6.72258 1 8.21258 1 9.34258V16.7526C1 19.0726 2.89 20.9726 5.21 20.9726H16.79C19.11 20.9726 21 19.0726 21 16.7626V9.48258C21 8.27258 20.19 6.72258 19.2 6.03258L13.02 1.70258C11.62 0.722584 9.37 0.772584 8.02 1.82258Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
												<path d="M11 16.9727V13.9727" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
											</svg>
											Tip Center
										</a>
									</li>
								@endcan
							@endif
							@if($userRole == 'tour-guide')
							<li class="max-w-[98px] text-center h-[100px] mx-auto p-2 hidden">
								<a href="{{ route(prefixedRouteName('tip-center')) }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
									<svg class="mx-auto mb-2.5" width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M8.02 1.82258L2.63 6.02258C1.73 6.72258 1 8.21258 1 9.34258V16.7526C1 19.0726 2.89 20.9726 5.21 20.9726H16.79C19.11 20.9726 21 19.0726 21 16.7626V9.48258C21 8.27258 20.19 6.72258 19.2 6.03258L13.02 1.70258C11.62 0.722584 9.37 0.772584 8.02 1.82258Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
										<path d="M11 16.9727V13.9727" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
									</svg>
									Tip Center
								</a>
							</li>
							@endif
							@if($autoPayoutSetting)
							@can('auto-payout')
							{{-- Auto-payout is admin-only; only render when the route exists for this user type to avoid a "route not defined" 500 for staff who hold the permission. --}}
							@if(Route::has(prefixedRouteName('auto-payout')))
							<li class="max-w-[98px] text-center h-[100px] mx-auto p-2">
								<a href="{{ route(prefixedRouteName('auto-payout')) }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
									<svg class="mx-auto mb-2.5" xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6m18 0v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6m18 0H3" /><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" /></svg>
									Auto Payouts
								</a>
							</li>
							@endif
							@endcan
							@endif
							@if($boardSetting)
							@if($userType == "Tour Operator")
								@can('admin-board')
								<li class="max-w-[98px] text-center h-[86px] mx-auto p-2">
									<a href="{{ route($routePrefix . '.board.index') }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
										<i class="fas fa-chess-board" style="font-size: 24px; color: white; display: block; margin-bottom: 6px;"></i>
										<span>Board</span>
									</a>
								</li>
								@endcan
								@can('guide-board')
								<li class="max-w-[98px] text-center h-[86px] mx-auto p-2">
									<a href="{{ route($routePrefix . '.board.guide') }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
										<i class="fas fa-chess-board" style="font-size: 24px; color: white; display: block; margin-bottom: 6px;"></i>
										<span>Board</span>
									</a>
								</li>
								@endcan
							@endif
							@if($userType == "Admin")
								@can('admin-board')
								<li class="max-w-[98px] text-center h-[86px] mx-auto p-2">
									<a href="{{ route($routePrefix . '.board.index') }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
										<i class="fas fa-chess-board" style="font-size: 24px; color: white; display: block; margin-bottom: 6px;"></i>
										<span>Board</span>
									</a>
								</li>
								@endcan
							@endif
							@endif
							<!-- @can('view-payment-breakdown-report')
							<li class="max-w-[98px] text-center h-[86px] mx-auto p-2">
								<a href="{{ route($routePrefix . '.report.payment-breakdown-report') }}" target="_blank" class="block w-full h-full p-2.5 rounded text-white text-[14px] leading-[17px] font-semibold bg-white/20 hover:bg-[#264F73]">
									<i class="fas fa-chart-pie" style="font-size: 24px; color: white; display: block; margin-bottom: 6px;"></i>
									<span>Payment Report</span>
								</a>
							</li>
							@endcan -->
						</ul>
					</div>
					@if (isPermissionGranted() === false)
						<a href="{{ route(prefixedRouteName('dashboard')) }}"><img id="mainLogo" src="{{ asset('images/logo.png') }}" class="max-w-full"><img src="" style="display:none" id="mainLogoPrint" class="max-w-full"></a>
					@else
						<a href="{{ route($routePrefix . '.tenant.index') }}"><img id="mainLogo" src="{{ asset('images/logo.png') }}" class="max-w-full"><img src="" style="display:none" id="mainLogoPrint" class="max-w-full"></a>
					@endif
				</div>

                <!-- Profile & Branch -->
                <div class="order-2 lg:order-3 text-end flex justify-start lg:justify-end max-w-[200px] ml-auto">
                    <!-- Branch Dropdown -->
					<div class="flex items-center mr-5">
                        <div class="relative">
                            <button id="branchDropdownBtn" class="flex items-center focus:outline-none group">
								<img class="max-w-full min-w-8 -mr-2.5" src="{{ tourOperatorNavigationLogo() }}" alt="Menu" height="50" width="50">
								<!-- <svg class="inline-block" width="20" height="21" viewBox="0 0 20 21" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316]" d="M10.1824 2.16406H9.81569C9.37366 2.16406 8.94974 2.33966 8.63718 2.65222C8.32462 2.96478 8.14902 3.3887 8.14902 3.83073V3.98073C8.14872 4.273 8.07157 4.56005 7.92531 4.81309C7.77904 5.06613 7.56881 5.27626 7.31569 5.4224L6.95736 5.63073C6.70399 5.77701 6.41659 5.85402 6.12402 5.85402C5.83146 5.85402 5.54406 5.77701 5.29069 5.63073L5.16569 5.56406C4.78325 5.34345 4.32889 5.2836 3.90236 5.39765C3.47583 5.51171 3.11198 5.79034 2.89069 6.1724L2.70736 6.48906C2.48674 6.87151 2.42689 7.32587 2.54095 7.75239C2.655 8.17892 2.93364 8.54277 3.31569 8.76406L3.44069 8.8474C3.69259 8.99282 3.90204 9.20164 4.04823 9.45309C4.19443 9.70454 4.27227 9.98987 4.27402 10.2807V10.7057C4.27519 10.9994 4.19873 11.2882 4.05239 11.5428C3.90606 11.7974 3.69503 12.0089 3.44069 12.1557L3.31569 12.2307C2.93364 12.452 2.655 12.8159 2.54095 13.2424C2.42689 13.6689 2.48674 14.1233 2.70736 14.5057L2.89069 14.8224C3.11198 15.2044 3.47583 15.4831 3.90236 15.5971C4.32889 15.7112 4.78325 15.6513 5.16569 15.4307L5.29069 15.3641C5.54406 15.2178 5.83146 15.1408 6.12402 15.1408C6.41659 15.1408 6.70399 15.2178 6.95736 15.3641L7.31569 15.5724C7.56881 15.7185 7.77904 15.9287 7.92531 16.1817C8.07157 16.4347 8.14872 16.7218 8.14902 17.0141V17.1641C8.14902 17.6061 8.32462 18.03 8.63718 18.3426C8.94974 18.6551 9.37366 18.8307 9.81569 18.8307H10.1824C10.6244 18.8307 11.0483 18.6551 11.3609 18.3426C11.6734 18.03 11.849 17.6061 11.849 17.1641V17.0141C11.8493 16.7218 11.9265 16.4347 12.0727 16.1817C12.219 15.9287 12.4292 15.7185 12.6824 15.5724L13.0407 15.3641C13.2941 15.2178 13.5815 15.1408 13.874 15.1408C14.1666 15.1408 14.454 15.2178 14.7074 15.3641L14.8324 15.4307C15.2148 15.6513 15.6692 15.7112 16.0957 15.5971C16.5222 15.4831 16.8861 15.2044 17.1074 14.8224L17.2907 14.4974C17.5113 14.1149 17.5712 13.6606 17.4571 13.2341C17.343 12.8075 17.0644 12.4437 16.6824 12.2224L16.5574 12.1557C16.303 12.0089 16.092 11.7974 15.9457 11.5428C15.7993 11.2882 15.7229 10.9994 15.724 10.7057V10.2891C15.7229 9.99538 15.7993 9.7066 15.9457 9.45197C16.092 9.19734 16.303 8.9859 16.5574 8.83906L16.6824 8.76406C17.0644 8.54277 17.343 8.17892 17.4571 7.75239C17.5712 7.32587 17.5113 6.87151 17.2907 6.48906L17.1074 6.1724C16.8861 5.79034 16.5222 5.51171 16.0957 5.39765C15.6692 5.2836 15.2148 5.34345 14.8324 5.56406L14.7074 5.63073C14.454 5.77701 14.1666 5.85402 13.874 5.85402C13.5815 5.85402 13.2941 5.77701 13.0407 5.63073L12.6824 5.4224C12.4292 5.27626 12.219 5.06613 12.0727 4.81309C11.9265 4.56005 11.8493 4.273 11.849 3.98073V3.83073C11.849 3.3887 11.6734 2.96478 11.3609 2.65222C11.0483 2.33966 10.6244 2.16406 10.1824 2.16406Z" stroke="#9CA3AF" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316]" d="M10 13C11.3807 13 12.5 11.8807 12.5 10.5C12.5 9.11929 11.3807 8 10 8C8.61929 8 7.5 9.11929 7.5 10.5C7.5 11.8807 8.61929 13 10 13Z" stroke="#9CA3AF" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg> -->
								<!-- <img src="{{ asset('images/logo-navigation-1.jpg') }}" alt="nat" class="max-w-full min-w-8 -mr-2.5" height="40" width="40"> -->
                            </button>
                            <ul id="branchDropdown" class="dropdown-menu hidden absolute left-1/2 -translate-x-1/2 mt-2 w-64 bg-white shadow-lg z-50 rounded border text-sm text-left">
								@if(isPermissionGranted() === false)
									@can('new-booking')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.booking.create') }}"><i class="fas fa-fw fa-suitcase-rolling"></i> New Booking</a></li>
									@endcan
									@can('tour-slots')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.slot.index') }}"><i class="fas fa-fw fa-calendar-alt"></i> Tour Slots</a></li>
									@endcan
									@can('tour-promotions')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.promotion.index') }}"><i class="fas fa-fw fa-ticket-alt"></i> Tour Promotions </a></li>
									@endcan
									@can('tour-packages')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.package.index') }}"><i class="fab fa-fw fa-dropbox"></i> Tour Packages</a></li>
									@endcan
									@can('mass-bookings')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix. '.soft-block.index') }}"><i class="fas fa-fw fa-lock"></i> Mass Bookings</a></li>
									@endcan
									@can('mass-cancellation')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix. '.mass-cancellation.index') }}"><i class="fas fa-fw fa-ban"></i> Mass Cancellation</a></li>
									@endcan
									@can('dynamic-pricing')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix. '.package-rate.index') }}"><i class="fas fa-fw fa-credit-card"></i> Dynamic Pricing</a></li>
									@endcan
									@if($setting)
										@can('invoices')
											<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.invoice.index') }}"><i class="fas fa-fw fa-receipt"></i> Invoices</a></li>
										@endcan
									@endif
									@can('admin-users')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.admin-user.index') }}"><i class="fas fa-fw fa-user-secret"></i> Admin Users</a></li>
									@endcan
									@can('tour-operator-staff-users')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.staff.index') }}"><i class="fas fa-fw fa-user"></i> Staff Users</a></li>
									@endcan
									@can('affiliates')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.affiliate.index') }}"><i class="far fa-fw fa-handshake"></i> Affiliate Users</a></li>
									@endcan
									@can('affiliates-time-slots')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.time-slot.index') }}"><i class="far fa-fw fa-calendar-check"></i> Affiliate Time Slots</a></li>
									@endcan
									@can('countries')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.country.index') }}"><i class="far fa-fw fa-flag"></i> Countries</a></li>
									@endcan
									@can('states-provinces')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.state.index') }}"><i class="fab fa-fw fa-fort-awesome"></i> States / Provinces</a></li>
									@endcan
									@can('cities')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.city.index') }}"><i class="fas fa-fw fa-city"></i> Cities</a></li>
									@endcan
									@can('taxes')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.tax.index') }}"><i class="fas fa-fw fa-credit-card"></i> Taxes</a></li>
									@endcan
									@can('roles')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.role.index') }}"><i class="fas fa-fw fa-user-shield"></i> Roles</a></li>
									@endcan
									{{--
									@can('permissions')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.permission.index') }}"><i class="fas fa-fw fa-user-shield"></i> Permissions</a></li>
									@endcan --}}
									@can('categories')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.category.index') }}"><i class="fab fa-fw fa-dropbox"></i> Categories</a></li>
									@endcan
									@can('blackout-dates')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.blackout-date') }}"><i class="fas fa-calendar-times"></i> Blackout Dates</a></li>
									@endcan
									@can('department')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.department.index') }}"><i class="fas fa-fw fa-folder-open"></i> Departments</a></li>
									@endcan
									@can('master-email-templates')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.master-email-template.index') }}"><i class="fas fa-fw fa-envelope"></i> Master Email Templates</a></li>
									@endcan
									@can('mail-tracker')
										<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.email-tracker') }}"><i class="fas fa-fw fa-envelope-open"></i> Email Tracker</a></li>
									@endcan
								@endif
								@if(isPermissionGranted() === true)
									<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.admin-user.index') }}"><i class="fas fa-fw fa-user-secret"></i> Admin Users</a></li>
									<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.tenant.index') }}"><i class="fas fa-fw fa-bus"></i> Tenants</a></li>
									<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.networks.index') }}"><i class="fas fa-fw fa-crosshairs"></i> Networks</a></li>
									<li><a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route($routePrefix . '.octo-whitelisted-ips.page') }}"><i class="fas fa-fw fa-shield-alt"></i> OCTO IP Whitelist</a></li>
									<li>
										<a class="block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route("$routePrefix.release.index") }}">
											<i class="fas fa-fw fa-bell"></i> What's New 
											@if(isCentralDomain() === false && $notificationCount > 0)
												<span class="notification-count notification-badge what-new-badge">{{ $notificationCount }}</span>
											@endif
										</a>
									</li>
								@endif
                            </ul>
                        </div>
                    </div>

                    <!-- My Profile Dropdown -->
                    <div class="flex items-center px-3 py-2">
                        <div class="relative">
                            <button id="profileDropdownBtn" class="flex items-center focus:outline-none group">
                                <span class="relative">
									@if(Auth::user() && !empty(Auth::user()->profile_photo_path))
										<img src="{{ Auth::user()->profile_photo_path }}"
											 alt="{{ Auth::user()->name }}"
											 class="inline-block w-8 h-8 mr-2 rounded-full object-cover border border-gray-300">
									@else
										<span class="inline-block text-white text-sm font-bold text-center bg-orange-500 border border-gray-300 rounded-full w-8 h-8 leading-8 mr-2">
											@if(Auth::user()){{ strtoupper(substr(Auth::user()->name, 0, 1)) }}@endif
										</span>
									@endif
									@if(isCentralDomain() === false && $notificationCount > 0)
										<span class="absolute -top-2 right-0 text-center leading-[20px] text-xs bg-[#EF4444] text-white h-5 w-5 max-[1350px]:w-4 max-[1350px]:h-4 max-[1350px]:leading-[16px] rounded-full font-medium">{{ $notificationCount }}</span>
									@endif
								</span>
                                <span class="truncate max-w-[80px] text-[#111827] font-medium text-sm group-hover:text-[#F97316]">@if(Auth::user()){{ auth()->user()->name }}@endif</span>
                                <svg class="inline-block ms-1" width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path  class="group-hover:!stroke-[#F97316]" d="M1.4001 4.70019L7.0001 10.3002L12.6001 4.7002" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <ul id="profileDropdown" class="dropdown-menu hidden absolute right-0 mt-2 w-56 bg-white shadow-lg z-50 rounded border text-sm text-left">
								@if(isPermissionGranted() === false)
									@if(session('userType') == 'Admin')
										@can('profile')
										<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route('admin.editadminprofile',Auth::user()->id)}}">My Profile</a></li>
										@endcan
										@can('tour-operator')
											<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route($routePrefix . '.tour-operator.index')}}">Tour Operator</a></li>
										@endcan
										@can('settings')
											<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route($routePrefix.'.setting.edit')}}">Global Settings</a></li>
										@endcan
										@can('card-reader-settings')
											<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route($routePrefix.'.setting.card-readers')}}">Card Reader Settings</a></li>
											<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route($routePrefix.'.setting.printers')}}">Printer Settings</a></li>
										@endcan
										@if($autoPayoutSetting)
											@can('auto-payout')
												{{-- Admin-only route; guard against staff who hold the permission. --}}
												@if(Route::has(prefixedRouteName('auto-payout')))
												<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route(prefixedRouteName('auto-payout'))}}" target="_blank">Auto Payout Settings</a></li>
												@endif
											@endcan
										@endif
										@if(!isCentralDomain())
											@can('view-payment-dispute')
												<li>
													<a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route(prefixedRouteName('disputes.index'))}}">
														Payment Disputes
														@php $activeDisputeCount = getActiveDisputeCount(); @endphp
														@if($activeDisputeCount > 0)
															<span class="inline-block text-center leading-[20px] ml-1 text-xs bg-[#EF4444] text-white h-5 w-5 max-[1350px]:w-4 max-[1350px]:h-4 max-[1350px]:leading-[16px] rounded-full font-medium">{{ $activeDisputeCount }}</span>
														@endif
													</a>
												</li>
											@endcan
										@endif
									@elseif(session('userType') == "Tour Operator")
										@can('profile')
										<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route('tour-operator.editoperatorprofile',Auth::user()->id)}}">My Profile</a></li>
										@endcan
										@can('card-reader-settings')
											<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route($routePrefix.'.setting.card-readers')}}">Card Reader Settings</a></li>
											<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route($routePrefix.'.setting.printers')}}">Printer Settings</a></li>
										@endcan
										@if(!isCentralDomain())
											@can('view-payment-dispute')
												<li>
													<a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route(prefixedRouteName('disputes.index'))}}">
														Payment Disputes
														@php $activeDisputeCount = getActiveDisputeCount(); @endphp
														@if($activeDisputeCount > 0)
															<span class="inline-block text-center leading-[20px] ml-1 text-xs bg-[#EF4444] text-white h-5 w-5 max-[1350px]:w-4 max-[1350px]:h-4 max-[1350px]:leading-[16px] rounded-full font-medium">{{ $activeDisputeCount }}</span>
														@endif
													</a>
												</li>
											@endcan
										@endif
									@else
										@can('profile')
										<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route('affiliate.profileedit', Auth::user()->id)}}">My Profile</a></li>
										@endcan
									@endif

									@if(session('guard') == "customer")
										<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route('affiliate.logout') }}" onclick="event.preventDefault();$('#logout-form').submit()">Sign Out</a></li>
									@else
										@can('whats-new')
											<li>
												<a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route("$routePrefix.release.index")}}">
													<i class="fas fa-fw fa-bell"></i> What's New 
													@if(isCentralDomain() === false && $notificationCount > 0)
														<span class="notification-count notification-badge what-new-badge">{{ $notificationCount }}</span>
													@endif
												</a>
											</li>
										@endcan

										<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route(prefixedRouteName('logout')) }}" onclick="event.preventDefault();$('#logout-form').submit()">Sign Out</a></li>
									@endif
								@else
									<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route("admin.editadminprofile",Auth::user()->id)}}">My Profile</a></li>
									<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route("$routePrefix.setting.edit")}}">Global Settings</a></li>
									@if(!isCentralDomain())
										@can('view-payment-dispute')
											<li>
												<a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{route(prefixedRouteName('disputes.index'))}}">
													Payment Disputes
													@php $activeDisputeCount = getActiveDisputeCount(); @endphp
													@if($activeDisputeCount > 0)
														<span class="inline-block text-center leading-[20px] ml-1 text-xs bg-[#EF4444] text-white h-5 w-5 max-[1350px]:w-4 max-[1350px]:h-4 max-[1350px]:leading-[16px] rounded-full font-medium">{{ $activeDisputeCount }}</span>
													@endif
												</a>
											</li>
										@endcan
									@endif
									<li><a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="{{ route(prefixedRouteName('logout')) }}" onclick="event.preventDefault();$('#logout-form').submit()">Sign Out</a></li>
								@endif
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Navbar Links -->
                <div class="order-3 lg:order-2 max-w-[calc(100%-290px)] ps-7 max-[1350px]:ps-3">
                    <nav class="flex flex-wrap items-center justify-end lg:justify-start">
                        <button class="lg:hidden space-y-1" id="navToggle">
                            <span class="line1 block w-5 h-0.5 bg-[#4B5563] transition-all duration-300 ease-in-out relative"></span>
                            <span class="line2 block w-5 h-0.5 bg-[#4B5563] transition-all duration-300 ease-in-out relative"></span>
                            <span class="line3 block w-5 h-0.5 bg-[#4B5563] transition-all duration-300 ease-in-out relative"></span>
                        </button>

                        <div id="navbarNav" class="absolute hidden lg:flex max-[1023px]:flex-col [width:calc(100%-40px)] items-start [top:calc(100%-5px)] left-[20px] p-[15px] border border-[#e9e9e9] rounded-[7px] bg-white lg:static lg:flex lg:flex-wrap lg:items-center lg:border-0 lg:p-0 lg:bg-transparent lg:rounded-none lg:w-auto lg:top-auto lg:left-auto">
							@if(isPermissionGranted() === false)
								@can('dashboard')
									@if (tenant()->id != 'gcw')
										<a aria-current="page" href="{{ route(prefixedRouteName('dashboard')) }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs(prefixedRouteName('dashboard')) ? 'active !text-[#F97316]' : '' }}">
											<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1 {{ Request::routeIs(prefixedRouteName('dashboard')) ? '!stroke-[#F97316]' : '' }}" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs(prefixedRouteName('dashboard')) ? '!stroke-[#F97316]' : '' }}" d="M2 2V12.6667C2 13.0203 2.14048 13.3594 2.39052 13.6095C2.64057 13.8595 2.97971 14 3.33333 14H14" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs(prefixedRouteName('dashboard')) ? '!stroke-[#F97316]' : '' }}" d="M12 11.3333V6" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs(prefixedRouteName('dashboard')) ? '!stroke-[#F97316]' : '' }}" d="M8.66406 11.3359V3.33594" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs(prefixedRouteName('dashboard')) ? '!stroke-[#F97316]' : '' }}" d="M5.33594 11.3359V9.33594" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
											Dashboard
										</a>
									@endif
								@endcan
								@can('manager-request')
									<a href="{{ route($routePrefix. '.booking-request') }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix. '.booking-request') ? 'active !text-[#F97316]' : '' }}">
										<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix. '.booking-request') ? '!stroke-[#F97316]' : '' }}" d="M13 9C13 9.35362 12.8595 9.69276 12.6095 9.94281C12.3594 10.1929 12.0203 10.3333 11.6667 10.3333H3.66667L1 13V2.33333C1 1.97971 1.14048 1.64057 1.39052 1.39052C1.64057 1.14048 1.97971 1 2.33333 1H11.6667C12.0203 1 12.3594 1.14048 12.6095 1.39052C12.8595 1.64057 13 1.97971 13 2.33333V9Z" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										Request 
										<span id="notification-count" class="inline-block text-center leading-[20px] ml-1 text-xs bg-[#EF4444] text-white h-5 w-5 max-[1350px]:w-4 max-[1350px]:h-4 max-[1350px]:leading-[16px] rounded-full font-medium">0</span>
									</a>
								@endcan
								@can('calendar')
									<a href="{{ route($routePrefix . '.booking.calendar') }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix . '.booking.calendar') ? 'active text-[#F97316]' : '' }}">
										<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.calendar') ? '!stroke-[#F97316]' : '' }}" d="M5.33594 1.33594V4.0026" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.calendar') ? '!stroke-[#F97316]' : '' }}" d="M10.6641 1.33594V4.0026" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.calendar') ? '!stroke-[#F97316]' : '' }}" d="M12.6667 2.66406H3.33333C2.59695 2.66406 2 3.26102 2 3.9974V13.3307C2 14.0671 2.59695 14.6641 3.33333 14.6641H12.6667C13.403 14.6641 14 14.0671 14 13.3307V3.9974C14 3.26102 13.403 2.66406 12.6667 2.66406Z" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.calendar') ? '!stroke-[#F97316]' : '' }}" d="M2 6.66406H14" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										Calendar
									</a>
								@endcan
								@can('agenda')
									<a href="{{ route($routePrefix . '.booking.day-time-slots', [ 'date' => date('Ymd') ]) }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix . '.booking.day-time-slots', [ 'date' => date('Ymd') ]) ? 'active !text-[#F97316]' : '' }}">
										<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.day-time-slots', [ 'date' => date('Ymd') ]) ? '!stroke-[#F97316]' : '' }}" d="M8 4.66406V13.9974" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.day-time-slots', [ 'date' => date('Ymd') ]) ? '!stroke-[#F97316]' : '' }}" d="M2.0026 12C1.82579 12 1.65622 11.9298 1.5312 11.8047C1.40618 11.6797 1.33594 11.5101 1.33594 11.3333V2.66667C1.33594 2.48986 1.40618 2.32029 1.5312 2.19526C1.65622 2.07024 1.82579 2 2.0026 2H5.33594C6.04318 2 6.72146 2.28095 7.22156 2.78105C7.72165 3.28115 8.0026 3.95942 8.0026 4.66667C8.0026 3.95942 8.28356 3.28115 8.78365 2.78105C9.28375 2.28095 9.96203 2 10.6693 2H14.0026C14.1794 2 14.349 2.07024 14.474 2.19526C14.599 2.32029 14.6693 2.48986 14.6693 2.66667V11.3333C14.6693 11.5101 14.599 11.6797 14.474 11.8047C14.349 11.9298 14.1794 12 14.0026 12H10.0026C9.47217 12 8.96346 12.2107 8.58839 12.5858C8.21332 12.9609 8.0026 13.4696 8.0026 14C8.0026 13.4696 7.79189 12.9609 7.41682 12.5858C7.04175 12.2107 6.53304 12 6.0026 12H2.0026Z" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										Agenda
									</a>
								@endcan
								@if (tenant()->id == 'gcw')
									@can('affiliate-agenda')
										<a href="{{ route($routePrefix . '.agenda', [ 'fromDate' => date('Ymd'), 'toDate' => date('Ymd') ]) }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix . '.agenda', [ 'fromDate' => date('Ymd'), 'toDate' => date('Ymd') ]) ? 'active !text-[#F97316]' : '' }}">
											<i class="fa fa-user inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" aria-hidden="true"></i>
											Affiliate
										</a>
									@endcan
								@endif
								@can('reports')
									<a href="{{ route($routePrefix. '.report.reportop') }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix. '.report.reportop') ? 'active !text-[#F97316]' : '' }}">
										<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix. '.report.reportop') ? '!stroke-[#F97316]' : '' }}" d="M9.9974 1.33594H3.9974C3.64377 1.33594 3.30464 1.47641 3.05459 1.72646C2.80454 1.97651 2.66406 2.31565 2.66406 2.66927V13.3359C2.66406 13.6896 2.80454 14.0287 3.05459 14.2787C3.30464 14.5288 3.64377 14.6693 3.9974 14.6693H11.9974C12.351 14.6693 12.6902 14.5288 12.9402 14.2787C13.1903 14.0287 13.3307 13.6896 13.3307 13.3359V4.66927L9.9974 1.33594Z" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix. '.report.reportop') ? '!stroke-[#F97316]' : '' }}" d="M9.33594 1.33594V4.0026C9.33594 4.35623 9.47641 4.69536 9.72646 4.94541C9.97651 5.19546 10.3156 5.33594 10.6693 5.33594H13.3359" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix. '.report.reportop') ? '!stroke-[#F97316]' : '' }}" d="M6.66927 6H5.33594" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix. '.report.reportop') ? '!stroke-[#F97316]' : '' }}" d="M10.6693 8.66406H5.33594" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix. '.report.reportop') ? '!stroke-[#F97316]' : '' }}" d="M10.6693 11.3359H5.33594" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
										Reports
									</a>
								@endcan
								@can('help')
									<a href="{{ route($routePrefix . '.booking.help') }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix . '.booking.help') ? 'active !text-[#F97316]' : '' }}">
										<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.help') ? '!stroke-[#F97316]' : '' }}" d="M8.0026 14.6693C11.6845 14.6693 14.6693 11.6845 14.6693 8.0026C14.6693 4.32071 11.6845 1.33594 8.0026 1.33594C4.32071 1.33594 1.33594 4.32071 1.33594 8.0026C1.33594 11.6845 4.32071 14.6693 8.0026 14.6693Z" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.help') ? '!stroke-[#F97316]' : '' }}" d="M6.0625 6.00038C6.21924 5.55483 6.5286 5.17912 6.9358 4.9398C7.343 4.70049 7.82176 4.61301 8.28728 4.69285C8.75281 4.7727 9.17504 5.01473 9.47922 5.37607C9.78339 5.7374 9.94987 6.19473 9.94917 6.66705C9.94917 8.00038 7.94917 8.66705 7.94917 8.66705" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.booking.help') ? '!stroke-[#F97316]' : '' }}" d="M8 11.3359H8.00667" stroke="#4B5563" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
										Help
									</a>
								@endcan
								@can('qr')
									<a href="{{ route($routePrefix . '.checkin.index') }}" class="nav-custom-link flex items-center group nav-link {{ Request::routeIs($routePrefix . '.checkin.index') ? 'active !text-[#F97316]' : '' }}">
										<svg class="inline-block max-[1350px]:max-w-3 me-2 max-[1350px]:me-1" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M4.66667 2H2.66667C2.29848 2 2 2.29848 2 2.66667V4.66667C2 5.03486 2.29848 5.33333 2.66667 5.33333H4.66667C5.03486 5.33333 5.33333 5.03486 5.33333 4.66667V2.66667C5.33333 2.29848 5.03486 2 4.66667 2Z" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M13.3307 2H11.3307C10.9625 2 10.6641 2.29848 10.6641 2.66667V4.66667C10.6641 5.03486 10.9625 5.33333 11.3307 5.33333H13.3307C13.6989 5.33333 13.9974 5.03486 13.9974 4.66667V2.66667C13.9974 2.29848 13.6989 2 13.3307 2Z" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M4.66667 10.6641H2.66667C2.29848 10.6641 2 10.9625 2 11.3307V13.3307C2 13.6989 2.29848 13.9974 2.66667 13.9974H4.66667C5.03486 13.9974 5.33333 13.6989 5.33333 13.3307V11.3307C5.33333 10.9625 5.03486 10.6641 4.66667 10.6641Z" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M13.9974 10.6641H11.9974C11.6438 10.6641 11.3046 10.8045 11.0546 11.0546C10.8045 11.3046 10.6641 11.6438 10.6641 11.9974V13.9974" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M14 14V14.0067" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M7.9974 4.66406V6.66406C7.9974 7.01768 7.85692 7.35682 7.60687 7.60687C7.35682 7.85692 7.01768 7.9974 6.66406 7.9974H4.66406" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M2 8H2.00667" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M8 2H8.00667" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M8 10.6641V10.6707" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M10.6641 8H11.3307" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M14 8V8.00667" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#F97316] {{ Request::routeIs($routePrefix . '.checkin.index') ? '!stroke-[#F97316]' : '' }}" d="M8 14.0026V13.3359" stroke="#4B5563" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
										QR
									</a>
								@endcan
								@can('new-booking')
									<a href="{{ route($routePrefix . '.booking.create') }}" class="new-booking-btn flex text-white min-[1351px]:ms-auto text-md max-[1350px]:text-sm bg-[#ED9C45] border border-[#ED9C45] hover:border-[#4B5563] hover:bg-white font-medium px-6 py-2 max-[1350px]:px-3 max-[1350px]:py-1 hover:text-[#4B5563] transition-all duration-300 ease-in-out group rounded-[7px] nav-link">
										<svg class="inline-block max-[1350px]:max-w-3 me-1 max-[1350px]:me-1" width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#4B5563] transition-all duration-300 ease-in-out" d="M1.0614 8.49854H14.9281" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#4B5563] transition-all duration-300 ease-in-out" d="M7.99475 1.56543V15.4321" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
										New Booking
									</a>
								@endcan
							@endif
                        </div>
                    </nav>
                </div>
            </div>
        </div>
		@if(session('guard') == "customer")
			<form method="post" action="{{ route('affiliate.logout') }}" id="logout-form" class="d-none">
				@csrf
			</form>
		@else
			<form method="post" action="{{ route(prefixedRouteName('logout')) }}" id="logout-form" class="d-none">
				@csrf
			</form>
		@endif
		<input type="hidden" id="currency" value='@json($currencySettings)'>
    </header>
	<span class="bg-[url(\'data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e\')]" style="padding:0 !important; margin: 0 !important; min-height:0 !important; min-width:0 !important; height:0 !important; width:0 !important;"></span>

	
		@yield('main-content')
	

	<div class="loading-container hidden fixed inset-0 z-[999999] bg-black/50" id="loading-container">
		<img class='loading-img fixed top-1/2 left-1/2 -ml-2 -mt-2' src="{{ asset('images/loading.gif') }}">
	</div>

	@if(App::environment('local'))
		<script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
		<script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
		<script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
		<script src="{{ asset('plugins/bootstrap-notify/bootstrap-notify.min.js') }}"></script>
		<script src="{{ asset('plugins/currency.min.js') }}"></script>
	@else
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
		<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
		<script src="https://cdnjs.cloudflare.com/ajax/libs/mouse0270-bootstrap-notify/3.1.5/bootstrap-notify.min.js"></script>
		<script src="https://unpkg.com/currency.js@2.0.4/dist/currency.min.js"></script>
	@endif

	<script src="{{ asset('plugins/dark-editable.js') }}"></script>
	<script src="{{ asset('plugins/pusher.js') }}"></script>
	<script src="{{ asset('plugins/socket.js') }}"></script>
	<script src="{{ asset('js/utils/broadcast.js') }}"></script>
	<script src="{{ asset('js/utils/currency-setup.js') }}"></script>

	@if(auth()->user()->id != '')
		<?php $chatAuth = hash_hmac('sha256',  \Illuminate\Support\Facades\Auth::user()->email, 'xaTax7A5rkNrc6mUG_0KFe1se_sDWbzdUMGP-HKF');	?>
		<!-- Menu JS -->
		<script type="text/javascript">
			$(document).on('hidden.bs.modal', function () {
				// Check if any modals are still open
				if ($('.modal.show').length === 0) {
					$('body').removeClass('modal-open');
					$('body').css({ overflow: '', paddingRight: '' });
				}
			});

			$(document).on('hidden.bs.modal', '.modal', function () {
				if ($('.modal.show').length) {
					$('body').addClass('modal-open');

					if ($('.modal-backdrop').length === 0) {
						$('<div class="modal-backdrop fade show"></div>').appendTo('body');
					}
				}
			});

			$(document).ready(function () {
				function closeAll() {
					// Hamburger
					$('#hamburgerToggle').removeClass('open')
					.find('.line1').removeClass('rotate-45').end()
					.find('.line2').removeClass('hidden').end()
					.find('.line3').removeClass('-rotate-45 !m-0 -top-0.5');
					$('#sidebarMenu').removeClass('!left-0');

					// Profile dropdown
					$('#profileDropdown').addClass('hidden');

					// Branch dropdown
					$('#branchDropdown').addClass('hidden');

					// Nav toggle
					$('#navToggle').find('.line1').removeClass('rotate-45').end()
					.find('.line2').removeClass('hidden').end()
					.find('.line3').removeClass('-rotate-45 !m-0 -top-0.5');
					$('#navbarNav').removeClass('!flex');

					// Custome dropdown
					$('.customDropDown').addClass('hidden');
				}

				$('#hamburgerToggle').on('click', function (e) {
					e.stopPropagation();
					const isOpen = $(this).hasClass('open');
					closeAll();
					if (!isOpen) {
						$(this).toggleClass('open')
						.find('.line1').toggleClass('rotate-45').end()
						.find('.line2').toggleClass('hidden').end()
						.find('.line3').toggleClass('-rotate-45 !m-0 -top-0.5');
						$('#sidebarMenu').toggleClass('!left-0');
					}
				});

				$('#profileDropdownBtn').on('click', function (e) {
					e.stopPropagation();
					const isVisible = !$('#profileDropdown').hasClass('hidden');
					closeAll();
					if (!isVisible) {
						$('#profileDropdown').removeClass('hidden');
					}
				});

				$('#branchDropdownBtn').on('click', function (e) {
					e.stopPropagation();
					const isVisible = !$('#branchDropdown').hasClass('hidden');
					closeAll();
					if (!isVisible) {
						$('#branchDropdown').removeClass('hidden');
					}
				});

				$('#navToggle').on('click', function (e) {
					e.stopPropagation();
					const isVisible = $('#navbarNav').hasClass('!flex');
					closeAll();
					if (!isVisible) {
						$(this).find('.line1').toggleClass('rotate-45').end()
						.find('.line2').toggleClass('hidden').end()
						.find('.line3').toggleClass('-rotate-45 !m-0 -top-0.5');
						$('#navbarNav').addClass('!flex');
					}
				});

				// Click outside to close all
				$(document).on('click', function () {
					closeAll();
				});

				// Prevent dropdowns from closing when clicking inside
				$('#sidebarMenu, #profileDropdown, #branchDropdown, #navbarNav').on('click', function (e) {
					e.stopPropagation();
				});

				$('input[type="radio"][name="discount_type"]').on('change', function() {
					$('.discountType').addClass('opacity-50'); // Add to all
					$(this).closest('.discountType').removeClass('opacity-50'); // Remove from selected
				});

				$('input[name="payment_method"]').on('change', function() {
					$('input[name="payment_method"]').each(function() {
						const container = $(this).next();
						container.removeClass('bg-[#264f731a] border-[#264f73]').addClass('bg-white border-gray-300');
					});

					if ($(this).is(':checked')) {
						const container = $(this).next();
						container.removeClass('bg-white border-gray-300').addClass('bg-[#264f731a] border-[#264f73]');
					}
				});

				$(document).on('click', function (e) {
					const $target = $(e.target);
					if (!$target.closest('.view-all-booking').length && !$target.closest('.day-schedules-popup').length) {
						$('.day-schedules-popup').addClass('hidden');
					}
				});
				$(document).on('click','.view-all-booking', function (e) {
					e.stopPropagation();
					const $popup = $(this).closest('.cal-month-day').find('.day-schedules-popup');
					const isVisible = !$popup.hasClass('hidden');
					$('.day-schedules-popup').addClass('hidden');
					if (!isVisible) {
						$popup.removeClass('hidden');
					}
				});
				$(document).on('click','.close-schedules-popup', function (e) {
					e.stopPropagation();
					$(this).closest('.day-schedules-popup').addClass('hidden');
				});

				$('.customDropDownButton').on('click', function (e) {
					e.stopPropagation();
					const isVisible = !$('.customDropDown').hasClass('hidden');
					closeAll();
					if (!isVisible) {
						$('.customDropDown').removeClass('hidden');
					}
				});

			});
			// Desktop Hamburger
			// $('.hamburger-icon').on('click', function() {
			// 	$('.open-sidebar').toggleClass('open');
			// 	$('.sidebar-menu').toggleClass('open');
			// });

			if ((window.self !== window.top) === false) {
				const chatUser = {
					name: "{{\Illuminate\Support\Facades\Auth::user()->name}}",
					email: "{{\Illuminate\Support\Facades\Auth::user()->email}}",
					auth: "{{$chatAuth}}"
				};

				window.Intercom("boot", {
					api_base: "https://api-iam.intercom.io",
					app_id: "o658qwaz",
					name: chatUser.name,
					email: chatUser.email,
					user_hash: chatUser.auth,
					created_at: "{{\Illuminate\Support\Facades\Auth::user()->created_at}}",
				});
			}

			var timeOut = "{{ config('session.lifetime ') }}";
			if (timeOut != '' && timeOut > 0) {
				idletimeConvertToMiliseconds = Math.floor(timeOut * 60 * 1000) - 300000;
				if (idletimeConvertToMiliseconds > 0) {
					const myTimeout = setTimeout(showLogOutAlert, idletimeConvertToMiliseconds);

					function showLogOutAlert() {
						swal({
							title: 'You will be automatically logged out.',
							text: '',
							button: {
								text: "I'M STILL HERE",
							},
							icon: "warning"
						}).then(function() {
							location = location; // location.reload() keeps data in form which isn't desirable
						});
					}
				}
			}

			var tenantId = "{{ tenant()?->id }}";			
			if (tenantId) {
				var loggedInManagerId = "{{ auth()->guard('tour_operator_staff')->check() ? auth()->guard('tour_operator_staff')->user()->id : 0 }}";
				const broadcast = new Broadcast({
					tenantId: tenantId,
					userId: loggedInManagerId
				});
			}
		</script>
	@endif

	@yield('js-libraries')

	<x-custom-js />

	@yield('js-custom')
	<script>
		const currencyFormation = JSON.parse(document.getElementById('currency').value);
		setGlobalCurrencyOptions(currencyFormation);
		var managerRequestCount = "{{ managerRequestCount() }}"
		$('#notification-count').text(managerRequestCount);
		window.switchTenants = @json(switchTenant());
		let url = "{{ route(routePrefix() . '.networks.switch-tenant') }}"
		if (window.self !== window.top) {
			// Page is inside an iframe
			const header = document.querySelector('header');
			if (header) header.style.display = 'none';
		}
	</script>
	<script src="{{ asset('js/utils/switch-company.js') }}"></script>
	<script src="{{ asset('js/app.js') }}"></script>
</body>

</html>
