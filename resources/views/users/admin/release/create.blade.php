@extends('layouts.default-2')

@section('main-content')

@php
    $routePrefix = routePrefix();
@endphp

<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-83px)]">
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
                                <a class="btn-outline-primary-small" href="{{ route("$routePrefix.release.index") }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                            </div>
                        </div>
					</div>

                    <div class="main-content">
                        @if($errors->any())
                            @include('layouts._errors')
                        @endif

                        @if(session('message'))
                            @include('layouts._message')
                        @endif

                        <form method="post" action="{{ route("$routePrefix.release.store") }}" enctype="multipart/form-data">
                            @csrf
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="title" class="form-label form-custom-label">Title <req>*</req></label>
                                    <input class="form-control form-custom-input" name="title" id="title" maxlength="50" value="" placeholder="Enter title" required>
                                </div>
                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="subtitle" class="form-label form-custom-label">Sub Title </label>
                                    <input class="form-control form-custom-input" name="subtitle" id="subtitle" maxlength="50" value="" placeholder="Enter subtitle">
                                </div>

                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="header_one" class="form-label form-custom-label">Image </label>
                                    <img id="img-header-one" style="max-height:200px; max-width: 300px;">
                                    <input type="file" name="image_i" id="image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkHeaderOneImgAndShowPreview(this)">
                                </div>

                                <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
                                    <label for="subtitle" class="form-label form-custom-label">Video URL </label>
                                    <input class="form-control form-custom-input" name="video" id="video" maxlength="150" value="" placeholder="Enter video url">
                                </div>

                                <div class="w-full max-w-full px-3 mb-3">
                                    <label for="body" class="form-label form-custom-label">Body <req>*</req></label>
                                    <textarea class="form-custom-input min-h-16 max-h-16" id="editor" name="body">
                                        {{ old('body', '') }}
                                    </textarea>
                                </div>
                            </div>

                            <input type="hidden" name="UploadURL" id="UploadURL" value="{{route("$routePrefix.image.upload", ['_token' => csrf_token() ])}}">

                            <div class="mt-3 text-end"><button type="submit" class="btn btn-main btn-primary-small">Save</button></div>
                        </form>
                    </div> {{-- /.main-content --}}
                </div>
            </div>
        </div>
    </div>
</div> {{-- /.page-content --}}
@endsection

@section('css-custom')
<style>
</style>
@endsection

@section('css-libraries')
    <link rel="stylesheet" href="{{ asset('plugins/jodit-4/jodit.min.css') }}" />
@endsection

@section('js-libraries')
    <script type="text/javascript" src="{{ asset('plugins/jodit-4/jodit.min.js') }}"></script>
    <script src="{{ asset('js/admin/release-add.js') }}" data-url="{{ route("$routePrefix.release.index-ahr") }}"></script>
@endsection
