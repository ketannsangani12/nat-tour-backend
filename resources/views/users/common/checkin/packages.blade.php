@extends('layouts.default-2')


@section('main-content')
<div>

    @if($errors->any())
		@include('layouts._errors')
	@endif

    @if(session('message'))
        @include('layouts._message')
    @endif

    <label for="booking-id" class="form-label">Tour Packages | Locations</label>
    
    <div class="redeem-ticket-section">
        @if(!empty($tourPackages))
            @foreach($tourPackages as $package)
                <button class="form-control tour-packages" type="button" title="{{ $package->name }}" onclick="redirectToSearchBookingWithPackage({{ $package->id }})">
                    <span>{{ $package->name }}</span> <i class="arrow right"></i>
                </button>
            @endforeach
        @endif
    </div>
	<input type="hidden" name="csrf_token" id="csrf_token" value="{{ csrf_token() }}" />
</div>
@endsection

@section('js-libraries')
    <script src="{{ asset('js/user-common/checkin/qr-cd-checkin.js') }}"></script>
@endsection

@section('css-custom')
<style>
	.tour-packages {
        cursor: pointer;
        margin-bottom: 10px;
        padding: 12px;
    }

    .tour-packages span {
        float: left;
    }

    .arrow {
        border: solid #0000009e;
        border-width: 0 3px 3px 0;
        display: inline-block;
        padding: 3px;
        float: right;
        margin: 8px;
    }

    .right {
        transform: rotate(-45deg);
        -webkit-transform: rotate(-45deg);
    }

    .tour-packages:active, .tour-packages:hover {
        background: #d3d3d34f;
    }
</style>
@endsection

@section('js-custom')
<script type="text/javascript">
	const qrcodeCheckin = new WebQRCodeCheckIn({
        admin: '{{ session("userType") }}',
        media: '{{ Storage::disk('s3')->url("images/tour-operator/logo") }}'
    });

    function redirectToSearchBookingWithPackage(package_id) {
		qrcodeCheckin.redirectToSearchBookingWithPackage(package_id);
	}
</script>
@endsection
