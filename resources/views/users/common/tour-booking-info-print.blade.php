<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<meta name="csrf-token" content="{{ csrf_token() }}" />
	<title>@yield('title', $page_title ?? '') | {{ config('app.name') }}</title>
	<link href="{{ asset('images/favicon.png') }}" rel="icon">

	@if(App::environment('local'))
		<link rel="stylesheet" href="{{ asset('plugins/bootstrap-5.1.1/css/bootstrap.min.css') }}">
	@else
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
	@endif

	<link href="{{ asset('plugins/font-awesome-5.15.4/css/all.min.css') }}" rel="stylesheet">
	<link href="{{ asset('css/style.css') }}" rel="stylesheet">
	<style>
		body { padding: 10px; }
		p { margin-bottom: 0.2rem; }
		.tbl-price { font-size: 80%; }
		.td-amount { width: 90px; text-align: right; }

		@media print {
			body { padding: 0; }
			.div-print-btn { display: none; }
		}
	</style>
</head>
<body>
	<div class="pt-4 pb-4">
		<div class="container">
			<div class="row">
				<div class="col-12 user-details">
					<div class="border border-[#E5E7EB] rounded p-3">
						<div class="div-print-btn mb-3 text-end">
							<button type="button" class="btn btn-primary btn-info-small" onclick="print()"><i class="fa fa-print"></i> Print</button>
						</div>

						<div class="print-area">
							@if( hasSetting("SIGNED_RECEIPT") ) 
								@include('users.common.tour-booking-info-print-fullsize')
							@else
								@include('users.common.tour-booking-info-print-3')
							@endif
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
</html>
