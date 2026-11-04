<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <!-- Global site tag (gtag.js) - Google Analytics --><script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script><script>  window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);}  gtag('js', new Date());   gtag('config', 'UA-223629951-1');</script>

	<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-W4CHJCP');</script><!-- End Google Tag Manager -->
 	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="csrf-token" content="{{ csrf_token() }}" />
	<title>@yield('title', $page_title ?? '') | {{ config('app.name') }}</title>
	<link href="{{ asset('images/favicon.png') }}" rel="icon">
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

	{{-- 	<link href="{{ asset('css/magnific-popup.css') }}" rel="stylesheet"> --}}
	<link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">
	<link href="{{ asset('css/app.css') }}" rel="stylesheet">

	 <style>
		input:focus, textarea:focus, input:focus-visible, textarea:focus-visible {box-shadow: 0px 0px 2px 0px rgba(249,115,22,1); -webkit-box-shadow: 0px 0px 2px 0px rgba(249,115,22,1); -moz-box-shadow: 0px 0px 2px 0px rgba(249,115,22,1); border-color: #ed9c45;}
		
		.swal-button {background-color: #264F73; border: 1px solid #264F73; color: white; font-size: 12px; font-weight: 600; padding: 7px 16px; width: 100%; border-radius: 6px; cursor: pointer; transition: background-color 0.2s ease, color 0.2s ease; outline:none; box-shadow: none !important;}
		.swal-button:hover {background-color: white !important; color: #264F73;}
		.swal-button.swal-button--yes {background-color: #E5E7EB; border: 1px solid #E5E7EB; color: #4B5563; font-size: 12px; font-weight: 600; padding: 7px 16px; width: 100%; border-radius: 6px; cursor: pointer; transition: background-color 0.2s ease, color 0.2s ease; outline:none; box-shadow: none !important;}
		.swal-button.swal-button--yes:hover {background-color: #A32624 !important; color: white;}
	 </style>

@yield('styles')
</head>
<body>
	<!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W4CHJCP"height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->

<!-- <div class="container"> -->
@yield('content')
<!-- </div> -->

@include('layouts._footer')

@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
@else
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
@endif

<x-custom-js />

@yield('scripts')
</body>
</html>
