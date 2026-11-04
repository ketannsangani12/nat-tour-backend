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
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Add Tour Package</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.package.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Add Tour Package</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.package.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.package.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($errors->any())
						@include('layouts._errors')
						@endif

						@if(session('message'))
						@include('layouts._message')
						@endif

						<form method="post" action="{{ route($routePrefix . '.package.store') }}" enctype="multipart/form-data" onsubmit="return validateAddPckgForm()">
							@csrf

							<div class="mb-3">
								<label for="name" class="form-label form-custom-label">Package Name <req>*</req></label>
								<input class="form-control form-custom-input" name="name" id="name" maxlength="100" value="{{ old('name') }}" placeholder="Enter package name" onblur="validateCharacters('#name')" required>
							</div>

							<div class="mb-3">
								<label for="short_description" class="form-label form-custom-label">Short Description</label>
								<textarea class="form-control form-custom-input max-h-16 min-h-16" name="short_description" id="short_description" placeholder="Enter short description of the package">{{ old('short_description') }}</textarea>
							</div>

							<div class="mb-3">
								<label for="long_description" class="form-label form-custom-label">Full Description</label>
								<textarea class="form-control form-custom-input max-h-16 min-h-16" name="long_description" id="long_description" placeholder="Enter full description">{{ old('long_description') }}</textarea>
							</div>

							<div><label class="form-label form-custom-label">Duration</label></div>
							<div class="flex flex-wrap -mx-3 mb-3">
								<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
									<label for="hours" class="input-group-text  bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours <req>*</req></label>
									<input type="number" class="form-control form-custom-input !rounded-l-none" name="hours" id="hours" min="0" max="255" value="{{ old('hours') ?: 0 }}" placeholder="Enter hours" required>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
									<label for="minutes" class="input-group-text  bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Minutes <req>*</req></label>
									<input type="number" class="form-control form-custom-input !rounded-l-none" name="minutes" id="minutes" min="0" max="59" value="{{ old('minutes') ?: 0 }}" placeholder="Enter minutes" required>
								</div>
							</div>
							<div class="flex flex-wrap -mx-3">
								@if(!hasSetting('CALL_TO_BOOK'))
								<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center"><label class="form-label form-custom-label">Block timeslot duration </label></div>
								@endif
								@if(hasSetting('CALL_TO_BOOK'))
								<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center"><label class="form-label form-custom-label">Block call to book duration </label></div>
								@endif
							</div>	
							<div class="flex flex-wrap -mx-3 mb-3">
								@if(!hasSetting('CALL_TO_BOOK'))
								<div class="w-full md:w-1/2 max-w-full px-3 flex items-center">
									<label for="hours" class="input-group-text  bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours <req>*</req></label>
									<input type="number" class="form-control form-custom-input !rounded-l-none" name="duration" id="duration" min="0" value="" placeholder="Enter day or hours" required>
								</div>
								@endif
								@if(hasSetting('CALL_TO_BOOK'))
								<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
									<label for="hours" class="input-group-text  bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours </label>
									<input type="number" class="form-control form-custom-input !rounded-l-none" name="block_ctb_duration" id="block_ctb_duration" min="0" value="" placeholder="Enter block call to book duration">
								</div>
								@endif
							</div>
							
							@if(hasSetting('CALL_TO_BOOK'))
							<div class="mb-3">
								<label class="form-label form-custom-label">Call To Book Description</label>
								<div id="quillArea2" style="height: 200px;">

								</div>
								<textarea style="display:none" id="ctb_description" name="ctb_description"></textarea>
							</div>
							@endif
							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3"><label class="form-label form-custom-label">Block timeslot duration (For OTAs)</label></div>
							</div>
							<div class="flex flex-wrap -mx-3 mb-3">
								<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
									<label class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours</label>
									<input type="number" class="form-control form-custom-input !rounded-l-none" name="ota_duration" id="ota_duration" min="0" value="{{ old('ota_duration') ?? 0 }}" placeholder="Enter OTA block duration">
								</div>
							</div>
							<div class="mb-3">
								<label for="things_to_bring" class="form-label form-custom-label">Things to be Brought by Tourists</label> <small>(Press Enter key after writing each thing)</small>
								<textarea class="form-control form-custom-input max-h-16 min-h-16" name="things_to_bring" id="things_to_bring">{{ old('things_to_bring') }}</textarea>
							</div>

							<div class="mb-3">
								<label for="important_notes" class="form-label form-custom-label">Important Notes for Tourists</label>
								<textarea class="form-control form-custom-input max-h-16 min-h-16" name="important_notes" id="important_notes">{{ old('important_notes') }}</textarea>
							</div>

							@include('layouts._list-package-rate-for')

							<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">Add</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script type="text/javascript">
	var getPackageListUrl = "{{ route($routePrefix . '.get-packages', ':id') }}";
	var isCurrentTourPackage = 0;
</script>

@section('css-libraries')
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/quill-1.3.6/quill.bubble.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/quill-1.3.6/quill.snow.css') }}">
@if(App::environment('local'))
<link href="{{ asset('plugins/tagify-4.8.1/tagify.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
@else
<link href="https://unpkg.com/@yaireo/tagify@4.8.1/dist/tagify.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endif
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/tagify-4.8.1/tagify.min.js') }}"></script>
<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
@else
<script src="https://unpkg.com/@yaireo/tagify@4.8.1/dist/tagify.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endif
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
<script type="text/javascript" src="{{ asset('plugins/quill-1.3.6/quill.js') }}"></script>
@endsection

@section('css-custom')
<style>
	.pckg-rate-grp tr:last-child td {
		padding-bottom: 10px;
	}
</style>
@endsection

@section('js-custom')
<script>
	var action = 'Add';

	$(document).ready(function() {
		var scheduling_year = $('#scheduling_year').val();
		$('#schedulingYear').val(scheduling_year);
		$(".travel_duration").inputmask({
			"mask": "99:99"
		});
	});
</script>
<script >
	const CALL_TO_BOOK = "{{ hasSetting('CALL_TO_BOOK') }}";
	if (CALL_TO_BOOK) {
		var quill2 = new Quill('#quillArea2', {
			modules: {
				toolbar: [
					[{
						header: [1, 2, false]
					}],
					['bold', 'italic', 'underline'],
					['image', 'code-block']
				]
			},
			placeholder: 'Enter call to book description',
			theme: 'snow' // or 'bubble'
		});

		quill2.on('text-change', function() {
			const content = quill2.root.innerHTML.trim();
			$('#ctb_description').html(content);
		});
	}
</script>
@if(session('userType') == 'Admin')
<x-admin-js />
@endif

<script src="{{ asset('js/user-common/tour-package-add-edit.js') }}?v=7"></script>
@endsection