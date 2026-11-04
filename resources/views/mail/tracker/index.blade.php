@extends('layouts.default-2')
@php
    $routePrefix = routePrefix();
	if(session('userType') == 'Admin') {
		$uTypeAdmin = true;
	} else {
		$uTypeAdmin = false;
	}
@endphp
@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap items-center -mx-3">
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="content-title">
                                <h2 class="section-title">Email Tracker</h2>
                            </div>
                        </div>
                        <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                            <form action="{{ route("$routePrefix.email-tracker-search") }}" method="post" class="form-inline flex items-center justify-end">
                                {!! csrf_field() !!}
                                <div class="form-group">
                                    <input type="search" name="search" id="search" class="form-control form-control-md form-custom-input pl-10" value="{{ session('mail-tracker-index-search') }}">
                                </div>
                                <button type="submit" class="btn btn-primary btn-md btn-save btn-primary-small ml-1">
                                    Search
                                </button>
                                <div class="btn btn-default btn-md btn-save ml-1">
                                    <a class="btn-outline-small" href="{{ route("$routePrefix.email-tracker-clear") }}" style="color: black;">
                                        Clear Search
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="main-content">
                        @if($errors->any())
                        @include('layouts._errors')
                        @endif

                        @if(session('message'))
                        @include('layouts._message')
                        @endif

                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3 mail-tracker-index-table">
                                <div class="w-full overflow-y-auto">
                                    <table class="table table-striped datatable w-full border-collapse">
                                        <tr>
                                            <!-- <th class="th-custom-css">SMTP</th> -->
                                            <th class="th-custom-css">Recipient</th>
                                            <th class="th-custom-css">Subject</th>
                                            <th class="th-custom-css">First View</th>
                                            <th class="th-custom-css">Opens</th>
                                            <th class="th-custom-css">First Click</th>
                                            <th class="th-custom-css">Clicks</th>
                                            <th class="th-custom-css">Sent At</th>
                                            <th class="th-custom-css">View Email</th>
                                            <th class="th-custom-css">Click Report</th>
                                        </tr>
                                        @foreach($emails as $email)
                                        <tr class="{{ $email->report_class }}">
                                            <td class="td-custom-css">
                                                {{$email->recipient}} 
                                                @if ($email->tour_booking_id)
                                                <br>Booking No. <a href="{{route($routePrefix . '.booking.show', $email->tour_booking_id)}}" target="__blank">{{$email->tour_booking_id}}</a>
                                                @endif
                                            </td>
                                            <td class="td-custom-css">{{$email->subject}}</td>
                                            <td class="td-custom-css">{{$email->opened_at}}</td>
                                            <td class="td-custom-css">{{$email->opens}}</td>
                                            <td class="td-custom-css">{{$email->clicked_at}}</td>
                                            <td class="td-custom-css">{{$email->clicks}}</td>
                                            <td class="td-custom-css">{{$email->created_at->format(config('mail-tracker.date-format'))}}</td>
                                            <td class="td-custom-css text-center">
                                                <a href="{{route("$routePrefix.email-tracker-show", $email->id)}}" target="_blank">
                                                    View
                                                </a>
                                            </td>
                                            <td class="td-custom-css text-center">
                                                @if($email->clicks > 0)
                                                <a href="{{route("$routePrefix.email-tracker-url", $email->id)}}">Url Report</a>
                                                @else
                                                No Clicks
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3 text-center svg-pagination">
                                {!! $emails->render() !!}
                            </div>
                        </div>

                    </div> {{-- /.main-content --}}
                </div>
            </div>
        </div>
    </div>
</div> {{-- /.page-content --}}
@endsection
