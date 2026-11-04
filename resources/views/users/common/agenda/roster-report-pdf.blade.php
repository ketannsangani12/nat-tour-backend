<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="google-site-verification" content="Mh5XbOYqjxpvzLMc-VEuY2gstDIjS4_7yReDuyT0ob4" />
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="csrf-token" content="{{ csrf_token() }}" />
	<title>Roster Report</title>
    <style type="text/css">
        #roster_report_footer { font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif; }
        #roster_report { font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif; }
        .sec-width { width: 33.33%; }
        .tb-border-btm { border-bottom: 1px solid; }
        .tb-border-top-1 { border-top: 1px solid; }
        .tb-border-top-2 { border-top: 1px solid; }
        .tb-width { width: 100%; }
        .tb-collapse { border-collapse: collapse; }
        .page-break { page-break-before: always; }
        .contact-det:last-child { page-break-after: never; }
        .cont-name { position: absolute; margin-top: -2px; margin-left: 5px; }
        .p-5 { padding: 5px; }
        footer { position: fixed; bottom: -40px; left: 0px; right: 0px; height: 50px; }
    </style>
</head>

<footer>
    <table id="roster_report_footer" class="tb-width tb-border-top-2 tb-collapse">
        <tr>
            <td class="sec-width" align="left">
                <span>Date: {{ $datetime }}</span>
            </td>
            <td class="sec-width" align="right">
                <span>User: {{ $user_id }} - {{ $user_name }} ({{ $user_type }})</span>
            </td>
        </tr>
    </table>
</footer>

<main style="font-family: 'Lucida Sans', 'Lucida Sans Regular', 'Lucida Grande', 'Lucida Sans Unicode', Geneva, Verdana, sans-serif;">
    <table id="roster_report" class="tb-width tb-border-top-1 tb-collapse">
        <tr class="tb-border-btm">
            <td class="sec-width p-5" align="left">
                <span>{{ $package_name }}</span>
            </td>
            <td class="sec-width p-5"></td>
            <td class="sec-width p-5" align="right">
                <span>Roster</span>
            </td>
        </tr>
    </table>
    @if(count($contacts) > 0)
        @foreach($contacts as $key => $contact)
            <table class="tb-width tb-collapse @if($key > 0){{"tb-border-top-1"}}@endif @if($key > 0){{"page-break"}}@endif" style="margin-bottom: -10px;">
                <tr>
                    <td class="sec-width" align="left">
                        <p>
                            <span><b>Date:</b> {{ $contact['date'] }}</span><br>
                            <span><b>Time:</b> {{ $contact['time'] }}</span>
                        </p>
                    </td>
                    <td class="sec-width" align="left">
                        <p>
                            <span><b>Attributes:</b> </span><br>
                            <span>Guest Name</span><br>
                            <span>Guest Age</span><br>
                            <span>Guest Weight</span><br>
                            <span>Dietary Needs</span>
                        </p>
                    </td>
                    <td class="sec-width" align="left">
                        <p>
                            <span><b>Resources:</b> </span><br>
                            <span>{{ $package_name }}</span>
                        </p>
                    </td>
                </tr>
            </table>
            <table class="tb-width tb-border-btm">
                <tr>
                    <td class="sec-width" align="left">
                        <span><b>Attendees:</b> </span>
                    </td>
                </tr>
            </table>
            <table class="tb-width tb-collapse">
                @foreach($contact['data'] as $data)
                    <tr class="tb-border-btm contact-det">
                        <td class="sec-width" align="left">
                            <p>
                                <span><input type="checkbox"></span>
                                <span class="cont-name">{{ $data['name'] }}</span>
                            </p>
                        </td>
                        <td class="sec-width" style="padding: 0;" align="left">
                            <p>
                                <span>Booking ID - <b>{{ $data['booking_id'] }}</b></span><br>
                                <span>Age: <b>{{ $data['age'] }}</b></span><br>
                                <span>Rafter Weight: <b>{{ $data['weight'] }}</b> (lbs)</span><br>
                                <span>Special Needs: <b>{{ ($data['note'] != "") ? $data['note'] : 'None Requested' }}</b></span>
                            </p>
                        </td>
                        <td class="sec-width" align="right">
                        </td>
                    </tr>
                @endforeach
            </table>
        @endforeach
    @endif
</main>
</html>