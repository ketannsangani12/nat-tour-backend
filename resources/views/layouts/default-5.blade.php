@php
	$setting = hasSetting('INVOICE_ENABLE_DISABLE');
	$routePrefix = session('userType') == 'Admin' ? 'admin.' : '';
	$currencySettings = [
		'code' => currencyFormation(true, false),
		'symbol' => currencyFormation(false, true),
		'separator' => ',',
		'decimal' => '.',
		'precision' => 2,
	];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
<script>
	// We pre-filled your app ID in the widget URL: 'https://widget.intercom.io/widget/o658qwaz'
	(function(){var w=window;var ic=w.Intercom;if(typeof ic==="function"){ic('reattach_activator');ic('update',w.intercomSettings);}else{var d=document;var i=function(){i.c(arguments);};i.q=[];i.c=function(args){i.q.push(args);};w.Intercom=i;var l=function(){var s=d.createElement('script');s.type='text/javascript';s.async=true;s.src='https://widget.intercom.io/widget/o658qwaz';var x=d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s,x);};if(document.readyState==='complete'){l();}else if(w.attachEvent){w.attachEvent('onload',l);}else{w.addEventListener('load',l,false);}}})();
</script>
	<meta charset="UTF-8">
	<meta name="google-site-verification" content="Mh5XbOYqjxpvzLMc-VEuY2gstDIjS4_7yReDuyT0ob4" />
	<!-- Global site tag (gtag.js) - Google Analytics --><script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script><script>  window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);}  gtag('js', new Date());   gtag('config', 'UA-223629951-1');</script>

	<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-W4CHJCP');</script><!-- End Google Tag Manager -->

	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}" />
	<title>{{ $page_title }} | {{ config('app.name') }}</title>
	<link href="{{ asset('images/favicon.png') }}" rel="icon">


	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css">

	@yield('css-libraries')


	<link href="{{ asset('css/style.css') }}" rel="stylesheet">

	@yield('css-custom')

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

	<header class="header-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-4 ps-0">
                    <div class="logo-wrap d-flex align-items-center">

                        <div class="search-wrap">
                            <select class="form-select" id="category-search" aria-label="Default select example">
							@foreach($categories as $key=>$category)
							<option value="{{ $category->id }}">{{ $category->name }}</option>
							@endforeach
                            </select>
                            <input type="text" class="form-control" id="searchBox" value="" placeholder="Search" autocomplete="one-time-code">
                            <a href="javascript:void(0);" id="searchPackage" style="display: unset;"><i class="fa fa-search" aria-hidden="true"></i></a>
                        </div>
                    </div>
                </div>
				<div class="col-4 text-center">
				<a href="javascript:void(0);">
                  <img src="{{ asset('images/pos-logo.svg') }}" width="138" height="58" alt="Logo">
                </a>
                </div>
                <div class="col-4">
                    <div class="header-options d-flex justify-content-end">
                        <a href="javascript:void(0);" id="refresh"><i class="fa fa-repeat" aria-hidden="true"></i></a>
						@if(Auth::check())
						<a href="javascript:void(0);" id="setting"><i class="fa fa-gear" aria-hidden="true"></i></a>
						@endif
                        <a href="javascript:void(0);" class="hold-btn" id="onhold-orders"><span id = "on-hold-orders-count">0</span> On-Hold</a>
                        <a href="javascript:void(0);" class="js-toggle-fullscreen-btn"><i class="fa fa-arrows-alt" aria-hidden="true"></i></a>
                        <a href="javascript:void(0);" class="d-none"><i class="fa fa-wifi" aria-hidden="true"></i></a>
                        <a href="javascript:void(0);" class="d-none"><i class="fa fa-user" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </header>

	<div class="main-btn text-center">
        <div class="hamburger-menu d-none">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>
        <ul>
            <li class="sidebarmenu active">
                <a href="javascript:void(0);" id="home"><svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M8.02 1.82258L2.63 6.02258C1.73 6.72258 1 8.21258 1 9.34258V16.7526C1 19.0726 2.89 20.9726 5.21 20.9726H16.79C19.11 20.9726 21 19.0726 21 16.7626V9.48258C21 8.27258 20.19 6.72258 19.2 6.03258L13.02 1.70258C11.62 0.722584 9.37 0.772584 8.02 1.82258Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M11 16.9727V13.9727" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg> Tours </a>
            </li>
			@if(checkPermission('pos-orders') || !Auth::check())
			<li class="sidebarmenu">
                <a href="javascript:void(0);" id="orders"><svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M8.02 1.82258L2.63 6.02258C1.73 6.72258 1 8.21258 1 9.34258V16.7526C1 19.0726 2.89 20.9726 5.21 20.9726H16.79C19.11 20.9726 21 19.0726 21 16.7626V9.48258C21 8.27258 20.19 6.72258 19.2 6.03258L13.02 1.70258C11.62 0.722584 9.37 0.772584 8.02 1.82258Z" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> <path d="M11 16.9727V13.9727" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/> </svg> Orders </a>
            </li>
			@endif
        </ul>
    </div>
	<input type="hidden" id="currency" value='@json($currencySettings)'>

@yield('main-content')

<div class="loading-container hidden fixed inset-0 z-[999999] bg-black/50" id="loading-container">
	<img class='loading-img fixed top-1/2 left-1/2 -ml-2 -mt-2' src="{{ asset('images/loading.gif') }}">
</div>

@if(App::environment('local'))
	<script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
	<script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
	<script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
	<script src="{{ asset('plugins/currency.min.js') }}"></script>
@else
	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
	<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
	<script src="https://unpkg.com/currency.js@2.0.4/dist/currency.min.js"></script>
@endif

<script src="{{ asset('plugins/dark-editable.js') }}"></script>
<script src="{{ asset('js/utils/currency-setup.js') }}"></script>

<script type="text/javascript">
	const currencyFormation = JSON.parse(document.getElementById('currency').value);
	setGlobalCurrencyOptions(currencyFormation);
</script>

@yield('js-libraries')

<x-custom-js />

@yield('js-custom')

</body>
</html>
