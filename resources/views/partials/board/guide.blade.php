<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page_title }} | {{ config('app.name') }}</title>
    <link href="{{ asset('images/favicon.png') }}" rel="icon">
    <!-- Flatpickr CSS/JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <!-- Bootstrap CSS (v5) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/admin/board/calendar.css') }}">
   
</head>   
@php
    $routePrefix = routePrefix();
@endphp
<body>
    <div class="container-fluid">
        <div class="tab-content pt-3 pb-3" id="boardTabsContent">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Board Tab -->
            <div class="tab-pane fade show active" id="board" role="tabpanel">
                
                <div class="calendar-nav">
                  <button id="prevMonth">&lt;</button>
                  <div class="calendar-header" id="calendarHeader"></div>
                  <button id="nextMonth">&gt;</button>
                </div>

                <div id="calendarContainer"></div>
                <!-- This is where board data will load -->
                <div id="boardData"></div>
            </div>
        </div>
    </div>
</body>
<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/luxon@3.3.0/build/global/luxon.min.js"></script>

<script>
    let indexUrl = "{{ route($routePrefix . '.board.guide') }}";
    let timezone = "{{ $tz }}";
    let updateGuideStatusUrl = "{{ route($routePrefix . '.board.update-guide-status') }}"
</script>
<script src="{{ asset('js/admin/board/guide.js') }}"></script>

</html>    


