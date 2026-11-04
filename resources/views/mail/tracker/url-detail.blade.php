@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="main-content">
                        <div class="content-title">
                            <h2 class="section-title">Email Tracker</h2>
                        </div>
                        <div class="sub-nav flex items-center justify-end mb-8">
                            <a class="mr-2 btn-warning-small btn btn-primary btn-md btn-save" href="{{ route('admin.email-tracker-show', $details->first()->email->id) }}" target="_blank">
                                View Message
                            </a>
                            <a href="{{ route('admin.email-tracker', ['page' => session('mail-tracker-index-page')]) }}" class="btn btn-default btn-md btn-save btn-outline-primary-small" style="border-color: #ccc;">
                                All Sent Emails
                            </a>
                        </div>
                        <div style="margin: 0 auto; width: 33%;">
                            <h3 class="font-medium text-[#020817] text-base mt-5 mb-2">Clicked URLs for Email ID {{ $details->first()->email->id }}</h3>
                        </div>
                        <div class="font-medium text-[#020817] text-sm mt-5 mb-3">
                            Recipient: {{ $details->first()->email->recipient }} <br>
                            Subject: {{ $details->first()->email->subject }} <br>
                            Sent At: {{ $details->first()->email->created_at->format(config('mail-tracker.date-format')) }}
                        </div>
                        <div class="mail-tracker-url-link-table w-full overflow-x-auto">
                            <table class="table table-striped custom-table w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th class="th-custom-css">Url</th>
                                        <th class="th-custom-css">Clicks</th>
                                        <th class="th-custom-css">First Click At</th>
                                        <th class="th-custom-css">Last Click At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($details as $detail)
                                    <tr>
                                        <td class="td-custom-css"><a href="{{ $detail->url }}" target="_blank" class="url-link">{{ $detail->url }}</a></td>
                                        <td class="td-custom-css">{{ $detail->clicks }}</td>
                                        <td class="td-custom-css">{{ $detail->created_at->format(config('mail-tracker.date-format')) }}</td>
                                        <td class="td-custom-css">{{ $detail->updated_at->format(config('mail-tracker.date-format')) }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection