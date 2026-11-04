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
        body {
            padding: 10px;
        }

        p {
            margin-bottom: 0.2rem;
        }

        .tbl-price {
            font-size: 80%;
        }

        .td-amount {
            width: 90px;
            text-align: right;
        }

        @media print {
            body {
                padding: 0;
            }

            .div-print-btn {
                display: none;
            }
        }

        body {
            padding: 0px;
            color: #000;
            font-size: 50px;
            size: A4 landscape;
            font-weight: bold;
            max-width: 96%;
        }

        p {
            margin-bottom: 0.2rem;
        }

        .tbl-price {
            font-size: 100%;
        }

        .td-amount {
            width: 90px;
            text-align: right;
        }

        @media print {
            body {
                padding: 0;
            }

            .div-print-btn {
                display: none;
            }
        }

        @media print and (orientation: landscape) {
            .print-area {
                max-width: 90% !important;
            }

            .print-area * {
                font-size: 42px !important;
            }
        }
    </style>
</head>

<body>
    <div class="text-end div-print-btn">
        <button type="button" class="btn btn-primary" onclick="print()"><i class="fa fa-print"></i> Print</button>
    </div>

    <div class="print-area"> 
        @include('users.common.pos-bookings-info-print-2')
    </div>
</body>

</html>