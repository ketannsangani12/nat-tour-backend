<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="google-site-verification" content="Mh5XbOYqjxpvzLMc-VEuY2gstDIjS4_7yReDuyT0ob4" />
 <!-- Global site tag (gtag.js) - Google Analytics --><script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script><script>  window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);}  gtag('js', new Date());   gtag('config', 'UA-223629951-1');</script>

<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-W4CHJCP');</script><!-- End Google Tag Manager -->

<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}" />
<title>{{ $page_title }} | {{ config('app.name') }}</title>
<link href="{{ asset('images/favicon.png') }}" rel="icon">

<link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">
  
<link href="{{ asset('plugins/affiliate-login-font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link href="{{ asset('css/app.css') }}" rel="stylesheet">

<style>

</style>

@yield('css-custom')

</head>
<body class="font-inter">
	<!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W4CHJCP"height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->

<section class="login-sec py-14 lg:py-20 bg-[#f7f7f7] min-h-screen">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">

				@yield('main-content')

			</div>
		</div>
	</div>
</section>

<div class="loading-container hidden fixed inset-0 z-[999999] bg-black/50" id="loading-container">
	<img class='loading-img fixed top-1/2 left-1/2 -ml-2 -mt-2' src="{{ asset('images/loading.gif') }}">
</div>

@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
@else
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
<script src="https://live.nativeamericantours.com/plugins/psl.min.js"></script>
<script src="https://live.nativeamericantours.com/js/custom.js"></script>
@endif

@yield('js-libraries')

<x-custom-js />

@yield('js-custom')
</body>
</html>
