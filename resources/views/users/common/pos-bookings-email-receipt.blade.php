<!DOCTYPE html>
<html lang="en">

<head>
    <link href="http://netdna.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.css" rel="stylesheet">
</head>

<body>
    <div class="container">
        <div class="card" style="max-width: 630px;margin: 0 auto;">
            <div class="card-body">
                <div class="print-area">
                    @include('users.common.pos-bookings-info-print')
                </div>
                <div class="separator"></div>
            </div>
            @section('styles')
            <link href="{{ asset('css/cust-booking-info.css') }}" rel="stylesheet">
            @endsection
        </div>
    </div>
</body>

</html>