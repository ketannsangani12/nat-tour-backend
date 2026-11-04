@extends('layouts.default-2')

@section('main-content')
@php
    $routePrefix = routePrefix();
@endphp
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">

                    <div class="flex flex-wrap -mx-3 items-center mb-8">
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="content-title text-center md:text-left">
                                <h2 class="section-title !mb-0">Add {{ $page_title }}</h2>
                            </div>
                        </div>
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="sub-nav flex items-center justify-center md:justify-end">
                                <a class="btn-primary-small" href="{{ route($routePrefix.'.master-email-template.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                            </div>
                        </div>
                    </div>

                    <!-- <div class="content-title">
                        <h2 class="section-title">Add {{ $page_title }}</h2>
                    </div>

                    <div class="sub-nav flex items-center justify-end mb-8">
                        <a class="btn-warning-small" href="{{ route($routePrefix.'.master-email-template.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                    </div> -->

                    <div class="main-content">
                        @if($errors->any())
                        @include('layouts._errors')
                        @endif

                        @if(session('message'))
                        @include('layouts._message')
                        @endif
                        <form method="post" action="{{ route($routePrefix. '.master-email-template.store') }}">
                            @csrf
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="name" class="form-label form-custom-label">Name <req>*</req></label>
                                    <input class="form-control form-custom-input" name="name" id="name" maxlength="50" value="" placeholder="Enter name" required>
                                </div>
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="alias" class="form-label form-custom-label">Alias <req>*</req></label>
                                    <select class="form-select form-custom-input" name="alias" id="alias" required>
                                        <option value="">Select Alias</option>
                                        @foreach(Config::get('constants.email_template.alias') as $key => $alias)
                                        <option value="{{ $key }}">{{ $alias }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="class" class="form-label form-custom-label">Class <req>*</req></label>
                                    <select class="form-select form-custom-input" name="class" id="class" required>
                                        <option value="">Select Class</option>
                                        @foreach(Config::get('constants.email_template.class') as $key => $class)
                                        <option value="{{ $key }}">{{ $class }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="subject" class="form-label form-custom-label">Subject <req>*</req></label>
                                    <input class="form-control form-custom-input" name="subject" id="subject" maxlength="191" value="" placeholder="Enter subject" required>
                                </div>
                                <div class="w-full max-w-full px-3 mb-3">
                                    <label for="tags" class="form-label form-custom-label">Available Tags</label>
                                    <div class="form-custom-input !min-h-16 !max-h-16 overflow-x-auto">
                                        <small class="text-default" id="available-tags">
                                        </small>
                                    </div>
                                </div>
                                <div class="w-full max-w-full px-3">
                                    <label for="body" class="form-label form-custom-label">Body <req>*</req></label>
                                    <textarea class="w-full resize-none outline-none shadow-none border border-[#E2E8F0] rounded-md px-4 py-1.5 h-20 text-sm text-[#020817]" id="editor" name="body">
                                    </textarea>
                                </div>
                            </div>

                            <div class="mt-3 flex justify-end"><button type="submit" class="btn btn-main btn-primary-small">Save</button></div>
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
    <script src="{{ asset('js/admin/email-template.js') }}" data-tags="{{ json_encode(config('constants.email_template.tags')) }}" data-template="{{ json_encode(config('email-templates')) }}"></script>
@endsection
