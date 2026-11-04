<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page_title }} | {{ config('app.name') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link href="{{ asset('images/favicon.png') }}" rel="icon">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> -->

    <!-- Custom Styles -->
    <link rel="stylesheet" href="{{ asset('css/admin/board/board.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/board/calendar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin/board/custom.css') }}">

    <!-- SortableJS -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/luxon@3/build/global/luxon.min.js"></script>

</head>
<body>
    <div class="">
        @yield('main-content')
    </div> 
    <div class="loading-container" id="loading-container">
        <img class='loading-img' src="{{ asset('images/loading.gif') }}">
    </div>   

    <!-- Bootstrap Bundle (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script> -->

    <!-- App Logic -->
    <x-custom-js />
    <script src="{{ asset('plugins/pusher.js') }}"></script>
    <script src="{{ asset('plugins/socket.js') }}"></script>
    <script src="{{ asset('js/admin/board/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
