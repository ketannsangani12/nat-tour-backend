@php
	$routePrefix = session('userType') == 'Admin' ? 'admin.' : '';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
 <!-- Global site tag (gtag.js) - Google Analytics --><script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script><script>  window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);}  gtag('js', new Date());   gtag('config', 'UA-223629951-1');</script>

<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-W4CHJCP');</script><!-- End Google Tag Manager -->
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ $page_title }} | {{ config('app.name') }}</title>
<link href="{{ asset('images/favicon.png') }}" rel="icon">

@if(App::environment('local'))
<link href="{{ asset('plugins/bootstrap-5.1.1/css/bootstrap.min.css') }}" rel="stylesheet">
@else
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet">
@endif
<link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">

@yield('css-libraries')

<x-style-2-css />

<link href="{{ asset('css/style.css') }}" rel="stylesheet">

@yield('css-custom')
@yield('styles')
	<script>
var rootPath = "{{ asset('') }}";

function noPhoto(elImg) {
	elImg.onerror = null;
	elImg.src = (typeof rootPath == 'undefined' ? '/' : rootPath) + 'images/no-photo.png';
}
	</script>
</head>
<body>
	<!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W4CHJCP"height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->
<header class="header-section {{ request('modal') ? 'd-none' : '' }}">
	<div class="container">
		<div class="row align-items-center">
			<div class="text-start logo-wrp order-1">
				<a href="{{ route(prefixedRouteName('dashboard')) }}"><img id="mainLogo" src="{{ asset('images/logo.png') }}"><img src="" style="display:none" id="mainLogoPrint"></a>
			</div>

			<div class="text-end d-flex justify-content-start login-wrp order-2 order-lg-3">
				<div class="branch-menu-wrp">
					<div class="dropdown">
						<a class="dropdown-toggle" href="#" role="button" id="dropdownMenuBranch" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="branch-img"><img src="{{ tourOperatorNavigationLogo() }}" height="40" width="40" alt="Menu"></span>
						</a>

						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuBranch">
							<!-- li><a class="dropdown-item text-center" href="#"><img src="images/dixies_logo.png" alt="dixies_logo"></a></li -->
							@if(session('userType') !== 'Admin')
								@if(Session::get('roleNames'))
									@if(Session::get('roleNames')->contains("id", "4"))
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.booking.create') }}"><i class="fas fa-fw fa-suitcase-rolling"></i> New Booking</a></li>
									@elseif(Session::get('roleNames')->contains("id", "2"))
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.booking.create') }}"><i class="fas fa-fw fa-suitcase-rolling"></i> New Booking</a></li>
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.slot.index', [ 'date' => date('Ymd') ]) }}"><i class="fas fa-fw fa-calendar-alt"></i> Tour Slots</a></li>
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.promotion.index') }}"><i class="fas fa-fw fa-ticket-alt"></i> Tour Promotions</a></li>
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.package.index') }}"><i class="fab fa-fw fa-dropbox"></i> Tour Packages</a></li>
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.package-rate.index') }}"><i class="fas fa-fw fa-credit-card"></i> Dynamic Pricing</a></li>
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.affiliate.index') }}"><i class="far fa-fw fa-handshake"></i> Affiliates</a></li>
										<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.staff.index') }}"><i class="fas fa-fw fa-user"></i> @if(session('userType') == 'Admin') Tour Operator @endif Staff Users</a></li>
										<li><a class="dropdown-item" href="{{ route('admin.country.index') }}"><i class="far fa-fw fa-flag"></i> Countries</a></li>
										<li><a class="dropdown-item" href="{{ route('admin.state.index') }}"><i class="fab fa-fw fa-fort-awesome"></i> States / Provinces</a></li>
										<li><a class="dropdown-item" href="{{ route('admin.city.index') }}"><i class="fas fa-fw fa-city"></i> Cities</a></li>
										<li><a class="dropdown-item" href="{{ route('admin.tax.index') }}"><i class="fas fa-fw fa-credit-card"></i> Taxes</a></li>
										<li><a class="dropdown-item" href="{{ route('admin.role.index') }}"><i class="fas fa-fw fa-user-shield"></i> Tour Operator Staff Roles</a></li>
										<li><a class="dropdown-item" href="{{ route('admin.tour-operator.index') }}"><i class="fas fa-fw fa-bus"></i> Tour Operators</a></li>
									@endif
								@endif
							@else
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.booking.create') }}"><i class="fas fa-fw fa-suitcase-rolling"></i> New Booking</a></li>
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.slot.index', [ 'date' => date('Ymd') ]) }}"><i class="fas fa-fw fa-calendar-alt"></i> Tour Slots</a></li>
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.promotion.index') }}"><i class="fas fa-fw fa-ticket-alt"></i> Tour Promotions</a></li>
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.package.index') }}"><i class="fab fa-fw fa-dropbox"></i> Tour Packages</a></li>
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.package-rate.index') }}"><i class="fas fa-fw fa-credit-card"></i> Dynamic Pricing</a></li>
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.affiliate.index') }}"><i class="far fa-fw fa-handshake"></i> Affiliates</a></li>
								<li><a class="dropdown-item" href="{{ route($routePrefix . 'tour-operator.staff.index') }}"><i class="fas fa-fw fa-user"></i> @if(session('userType') == 'Admin') Tour Operator @endif Staff Users</a></li>
							@endif

							@if(session('userType') == 'Admin')
							<li><a class="dropdown-item" href="{{ route('admin.admin-user.index') }}"><i class="fas fa-fw fa-user-secret"></i> Admin Users</a></li>
							<li><a class="dropdown-item" href="{{ route('admin.country.index') }}"><i class="far fa-fw fa-flag"></i> Countries</a></li>
							<li><a class="dropdown-item" href="{{ route('admin.state.index') }}"><i class="fab fa-fw fa-fort-awesome"></i> States / Provinces</a></li>
							<li><a class="dropdown-item" href="{{ route('admin.city.index') }}"><i class="fas fa-fw fa-city"></i> Cities</a></li>
							<li><a class="dropdown-item" href="{{ route('admin.tax.index') }}"><i class="fas fa-fw fa-credit-card"></i> Taxes</a></li>
							<li><a class="dropdown-item" href="{{ route('admin.role.index') }}"><i class="fas fa-fw fa-user-shield"></i> Tour Operator Staff Roles</a></li>
							<li><a class="dropdown-item" href="{{ route('admin.tour-operator.index') }}"><i class="fas fa-fw fa-bus"></i> Tour Operators</a></li>
							@endif
						</ul>
					</div>
				</div>

				<div class="myprofile-menu-wrp">
					<div class="dropdown">
						<a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
							<span class="profile-img">
								<span class="profile-img-text">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
							</span>

							<span class="name-text">{{ auth()->user()->name }}</span>
						</a>

						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuLink">
							<li><a class="dropdown-item" href="#">My Profile</a></li>
							<li><a class="dropdown-item" href="{{ route(prefixedRouteName('logout')) }}" onclick="event.preventDefault();$('#logout-form').submit()">Sign Out</a></li>
						</ul>

						<form method="post" action="{{ route(prefixedRouteName('logout')) }}"id="logout-form" class="hidden">
							@csrf
						</form>
					</div>
				</div>
			</div>

			<div class="navbar-wrp order-3 order-lg-2">
				<nav class="navbar navbar-expand-lg navbar-light justify-content-end justify-content-lg-start">
					<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavAltMarkup" aria-controls="navbarNavAltMarkup" aria-expanded="false" aria-label="Toggle navigation">
						<span class="navbar-toggler-icon"></span>
					</button>

					<div class="collapse navbar-collapse" id="navbarNavAltMarkup">
						<div class="navbar-nav">
							@if(session('userType') !== 'Admin')
								@if(Session::get('roleNames'))
									@if(Session::get('roleNames')->contains("id", "4"))
										<a aria-current="page" href="{{ route(prefixedRouteName('dashboard')) }}" class="nav-link {{ Request::routeIs(prefixedRouteName('dashboard')) ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/1.png') }}">Dashboard</a>
										<a href="{{ route($routePrefix . 'tour-operator.booking.calendar') }}" class="nav-link {{ Request::routeIs($routePrefix . 'tour-operator.booking.calendar') ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/2.png') }}">Calendar</a>
									@elseif(Session::get('roleNames')->contains("id", "2"))
									<a aria-current="page" href="{{ route(prefixedRouteName('dashboard')) }}" class="nav-link {{ Request::routeIs(prefixedRouteName('dashboard')) ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/1.png') }}">Dashboard</a>
									<a href="{{ route($routePrefix . 'tour-operator.booking.calendar') }}" class="nav-link {{ Request::routeIs($routePrefix . 'tour-operator.booking.calendar') ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/2.png') }}">Calendar</a>
									<a href="{{ route($routePrefix . 'tour-operator.booking.day-time-slots', [ 'date' => date('Ymd') ]) }}" class="nav-link {{ Request::routeIs($routePrefix . 'tour-operator.booking.day-time-slots', [ 'date' => date('Ymd') ]) ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/3.png') }}">Booking</a>
									<a href="{{ route('admin.tour-operator.report.reportop') }}" class="nav-link {{ Request::routeIs('admin.tour-operator.report.reportop') ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/4.png') }}">Reports</a>
									<a class="nav-link" href="#"><img class="icon-img" src="{{ asset('images/6.png') }}">Help</a>
									@endif
								@endif
							@else
								<a aria-current="page" href="{{ route(prefixedRouteName('dashboard')) }}" class="nav-link {{ Request::routeIs(prefixedRouteName('dashboard')) ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/1.png') }}">Dashboard</a>
								<a href="{{ route($routePrefix . 'tour-operator.booking.calendar') }}" class="nav-link {{ Request::routeIs($routePrefix . 'tour-operator.booking.calendar') ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/2.png') }}">Calendar</a>
								<a href="{{ route($routePrefix . 'tour-operator.booking.day-time-slots', [ 'date' => date('Ymd') ]) }}" class="nav-link {{ Request::routeIs($routePrefix . 'tour-operator.booking.day-time-slots', [ 'date' => date('Ymd') ]) ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/3.png') }}">Booking</a>
								<a href="{{ route('admin.tour-operator.report.reportop') }}" class="nav-link {{ Request::routeIs('admin.tour-operator.report.reportop') ? 'active' : '' }}"><img class="icon-img" src="{{ asset('images/4.png') }}">Reports</a>
								<a class="nav-link" href="#"><img class="icon-img" src="{{ asset('images/6.png') }}">Help</a>
							@endif
						</div>
					</div>
				</nav>
			</div>
		</div>
	</div>
</header>

<div class="container py-3">
@yield('main-content')
</div>

<div class="loading-container hidden fixed inset-0 z-[999999] bg-black/50" id="loading-container">
	<img class='loading-img fixed top-1/2 left-1/2 -ml-2 -mt-2' src="{{ asset('images/loading.gif') }}">
</div>

@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
<script src="{{ asset('plugins/psl.min.js') }}"></script>
@else
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
<script src="https://raw.githubusercontent.com/wrangr/psl/master/dist/psl.min.js"></script>
@endif

@yield('js-libraries')

	<script src="{{ asset('js/custom.js') }}"></script>

@yield('js-custom')
</body>
</html>
