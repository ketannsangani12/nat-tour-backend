@extends('layouts.default-2')


@section('main-content')
<div class="py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="form-control">
						<h2 for="booking-id" class="section-title mb-4">QR Code Check-In Flow</h2>
						@if($errors->any())
						@include('layouts._errors')
						@endif

						@if(session('message'))
						@include('layouts._message')
						@endif
						<div class="flex flex-wrap -mx-3">
							<div class="w-full md:w-1/2 max-w-full px-3 input-group mb-3">
								<span class="input-group-text form-custom-label">Booking ID: ( <span id="parsed-qr-code-content"> - </span> )</span>
								<div class="flex items-center">
									<input type="text" class="form-control form-custom-input" id="booking-id" />
									<button class="btn btn-outline-secondary btn-primary-small ml-2" type="button" id="init-qr-code-checkin">Search</button>
								</div>
							</div>
							@if(tenant()->id == 'gcw')
							<div class="w-full md:w-1/2 max-w-full px-3 input-group mb-3">
								<span class="input-group-text form-custom-label">Group ID: ( <span id="parsed-qr-code-content-gcw"> - </span> )</span>
								<div class="flex items-center">
									<input type="text" class="form-control form-custom-input" id="booking-group-id" />
									<input type="hidden" class="form-control" id="tour_package_id" value="{{ (isset($tourPackageId) ? $tourPackageId : 0) }}" />
									<button class="btn btn-outline-secondary btn-primary-small ml-2" type="button" onclick="checkBookingInfo({{ (isset($tourPackageId) ? $tourPackageId : 0) }})">Search</button>
								</div>
							</div>
							@endif
						</div>
						<div id="qr-reader" style="width: 500px;"></div>
						<div id="qr-reader-results"></div>
						<!-- <video id="preview"></video> -->
					</div>

					<div id="check-in-dev-tools" style="display: none; min-height:40px;">
						<button class="btn btn-default accordion-button-wrap btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#exampleQrCode" aria-expanded="false" aria-controls="exampleQrCode">
							Display Example Qr Code
						</button>
						<div class="collapse accordion-button-wrap" id="exampleQrCode">
							<div id="qrcode"></div>
						</div>
					</div>

					<div id="check-in-flow-display"> </div>
					<div id="print-view-display"> </div>
					<input type="hidden" name="csrf_token" id="csrf_token" value="{{ csrf_token() }}" />
				</div>
			</div>
		</div>
	</div>
</div>
@endsection


@section('js-libraries')
<script src="{{ asset('plugins/html5-qrcode/html5-qrcode.js')  }}"></script>
<script src="{{ asset('js/user-common/checkin/qr-cd-checkin.js') }}"></script>
<script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
<script src="{{ asset('js/utils/helpers.js')  }}"></script>
@endsection


@section('css-custom')
<style>
	#qrcode {
		width: 160px;
		height: 160px;
		margin-top: 15px;
	}

	.restricted_items img {
		width: 150px;
		min-width: 150px;
		max-width: 150px;
		height: 150px;
		padding: 3px;
		margin: auto;
		background-color: #fff;
	}

	.allowed_items img {
		width: 250px;
		min-width: 250px;
		height: 120px;
		padding: 3px;
		margin: auto;
		background-color: #fff;
	}

	.flex-break {
		flex-basis: 100%;
		height: 0;
	}
</style>
@endsection

@section('js-custom')
<script type="text/javascript">
	var tenant = "{{ getSubdomainFromHost() }}";

	// $(() => {
	const qrcodeCheckin = new WebQRCodeCheckIn({
		admin: '{{ session("userType") }}',
		media: '{{ Storage::disk('s3')->url("images/tour-operator/logo") }}'
	});

	function onScanSuccess(decodedText, decodedResult) {
		// Stop the scanner after first scan
		html5QrCode.stop().then(() => {
			const content = decodedText;
			const bookingid = content.toString().replace(/[^0-9]/gi, "");

			if (isNaN(bookingid)) {
				swal('Invalid QR Code', 'You scanned an incorrect QR code.', 'warning');
				return;
			}

			if (tenant === 'gcw') {
				$("#parsed-qr-code-content-gcw").html(content.replace(/(<([^>]+)>)/gi, ""));
				const packageId = $("#tour_package_id").val();
				$("#booking-group-id").val(bookingid);
				qrcodeCheckin.checkAndredirectToBookingInfo(packageId);
			} else {
				$("#parsed-qr-code-content").html(content.replace(/(<([^>]+)>)/gi, ""));
				$("#booking-id").val(bookingid);
				const isAdmin = ('{{ session("userType") }}' && '{{ session("userType") }}' === 'Admin') ? '/admin' : '';
				const url = `${isAdmin}/tour-operator/booking/${bookingid}`;
				window.location = url;
			}

		}).catch(err => {
			console.error("Failed to stop scanner:", err);
		});
	}

	function onScanFailure(error) {
		//swal('Error', 'Something went wrong. Please try again after sometime.', 'error');
	}
	const html5QrCode = new Html5Qrcode("qr-reader");
	html5QrCode.start({
			facingMode: "environment"
		}, // use back camera
		{
			fps: 10,
			qrbox: 250
		},
		onScanSuccess,
		onScanFailure
	).catch(err => {
		console.error("Camera start failed", err);
		swal('Camera Error', 'Unable to access the camera. Please allow permissions and try again.', 'error');
	});

	function checkBookingInfo(packageId) {
		qrcodeCheckin.checkAndredirectToBookingInfo(packageId);
	}
</script>
@endsection