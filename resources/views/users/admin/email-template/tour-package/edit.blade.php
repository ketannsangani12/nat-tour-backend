@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center mb-8">
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="content-title text-center md:text-left">
                                <h2 class="section-title !mb-0">Update {{ $page_title }}</h2>
                            </div>
                        </div>
                        @php
                        if (session('userType') == 'Admin') {
                            $routePrefix = 'admin.';
                        } else {
                            $routePrefix = '';
                        }
                        @endphp
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="sub-nav flex items-center justify-center md:justify-end">
                                <a class="btn-primary-small active" href="{{ route($routePrefix . 'tour-operator.email-template.index', ['tour_package' => $tourPackage->id]) }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="content-title">
                        <h2>Update {{ $page_title }}</h2>
                    </div>                    

                    <div class="sub-nav flex items-center justify-end mb-8">
                        <a class="mr-2 btn-warning-small" href="{{ route($routePrefix . 'tour-operator.email-template.create', ['tour_package' => $tourPackage->id]) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
                        <a class="btn-outline-primary-small active" href="{{ route($routePrefix . 'tour-operator.email-template.index', ['tour_package' => $tourPackage->id]) }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                    </div> -->

                    <div class="main-content">
                        @if($errors->any())
                        @include('layouts._errors')
                        @endif

                        @if(session('message'))
                        @include('layouts._message')
                        @endif
                        <form method="post" action="{{ route($routePrefix .'tour-operator.email-template.update', ['tour_package' => $tourPackage->id, 'email_template' => $tourPackageEmailTemplate->id]) }}">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-4">
                                    <label for="email_template_id" class="form-label form-custom-label">Master Email Template <req>*</req></label>
                                    <select class="form-select form-custom-input" name="email_template_id" id="email_template_id" required>
                                        <option value="">Select Master Email Templates</option>
                                        @foreach($emailTemplates as $emailTemplate)
                                        <option value="{{ $emailTemplate->id }}" {{ $tourPackageEmailTemplate->email_template_id === $emailTemplate->id ? 'selected' : '' }}>{{ $emailTemplate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-4">
                                    <label for="subject" class="form-label form-custom-label">Subject <req>*</req></label>
                                    <input class="form-control form-custom-input" name="subject" id="subject" maxlength="191" value="{{ $tourPackageEmailTemplate->subject }}" placeholder="Enter subject" required>
                                </div>
                                <div class="w-full max-w-full px-3 mb-4">
                                    <label for="tags" class="form-label form-custom-label">Available Tags</label>
                                    <div class="bg-light form-custom-input overflow-x-auto !h-auto">
                                        <small class="text-default" id="available-tags">
                                        </small>
                                    </div>
                                </div>
                                <div class="w-full max-w-full px-3 mb-4">
                                    <label for="body" class="form-label form-custom-label">Body <req>*</req></label>
                                    <textarea class="form-custom-input min-h-16" id="editor" name="body">
                                        {!! $tourPackageEmailTemplate->body !!}
                                    </textarea>
                                </div>
                                <div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4"><button type="submit" class="btn btn-main  btn-primary-small">Save</button></div>
                            </div>
                        </form>
                    </div> {{-- /.main-content --}}
                </div>
            </div>
        </div>
    </div>
</div> {{-- /.page-content --}}
@endsection



@section('css-libraries')
	<link rel="stylesheet" href="{{ asset('plugins/jodit-4/jodit.min.css') }}" />
@endsection

@section('js-libraries')
	<script type="text/javascript" src="{{ asset('plugins/jodit-4/jodit.min.js') }}"></script>
    <script src="{{ asset('js/admin/tour-package-email-template.js') }}" data-body="{{ json_encode($tourPackageEmailTemplate->body) }}" data-tags="{{ json_encode(config('constants.email_template.tags')) }}" data-templates="{{ json_encode($emailTemplates) }}" data-id="{{ $tourPackageEmailTemplate->email_template_id }}"></script>
@endsection
