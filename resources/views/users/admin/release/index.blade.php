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
								<h2 class="section-title !mb-0">What's New</h2>
							</div>
						</div>
						@if (isCentralDomain())
                            <div class="w-full md:w-1/2 max-w-full px-3">
                                <div class="sub-nav flex items-center justify-center md:justify-end">
                                    <a class="mr-2 btn-warning-small" href="{{ route("$routePrefix.release.create") }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
                                </div>
                            </div>
                        @endif
					</div>

                    <section class="listing-section">
                        @if($errors->any())
                            @include('layouts._errors')
                        @endif

                        @if(session('message'))
                            @include('layouts._message')
                        @endif
                        <div class="flex flex-wrap -mx-3">
                            @if(count($release['release']) > 0)
                                <div class="w-full max-w-full px-3" id="content">
                                    @foreach($release['release'] as $key => $post)
                                        <div class="list-wrap lg:flex post mb-6 pt-6 border-t border-[#E5E7EB]">
                                            <div class="date-wrap lg:text-center lg:mr-6">
                                                <div class="date text-7xl text-[#111827] font-semibold">{{ date('d', strtotime($post->operation_at)) }}</div>
                                                <span class="month text-base text-[#4B5563]">{{ date('M, Y', strtotime($post->operation_at)) }}</span>
                                            </div>

                                            <div class="content-wrap">
                                                <div class="flex justify-between">
                                                    <div class="list-title">
                                                        <a class="text-xl font-medium text-[#2563EB] hover:text-black mb-2" href="{{ route("$routePrefix.release.view",  $post->id) }}">
                                                            {{ $post->title}}
                                                            @if(isCentralDomain() === false && $post->id == $latestUnreadReleaseId)
                                                                <span class="latest-release-text">New</span>
                                                            @endif
                                                        </a>
                                                    </div>
                                                    @if (isCentralDomain())
                                                        <div class="btn-wrap flex items-center">
                                                            @if ($key === 0)
                                                                <a href="javascript:void(0)" 
                                                                    class="btn-send-emails text-xl font-medium text-[#2563EB] hover:text-black mb-2 mx-1 cursor-pointer" 
                                                                    data-url="{{ route("$routePrefix.release.send-notifications", $post->id) }}">
                                                                    <i class="fa fa-upload"></i>
                                                                </a>
                                                                <a href="{{ route("$routePrefix.release.edit",  $post->id) }}" class="edit text-xl font-medium text-[#2563EB] hover:text-black mb-2 mx-1 cursor-pointer">
                                                                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M8.15659 2.68684H2.59035C2.16857 2.68684 1.76405 2.85439 1.4658 3.15264C1.16755 3.45089 1 3.8554 1 4.27719V15.4096C1 15.8314 1.16755 16.2359 1.4658 16.5342C1.76405 16.8324 2.16857 17 2.59035 17H13.7228C14.1446 17 14.5491 16.8324 14.8474 16.5342C15.1456 16.2359 15.3132 15.8314 15.3132 15.4096V9.84342" stroke="#C4C4C4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M14.1208 1.49406C14.4372 1.17772 14.8662 1 15.3136 1C15.761 1 16.19 1.17772 16.5064 1.49406C16.8227 1.8104 17.0004 2.23945 17.0004 2.68682C17.0004 3.1342 16.8227 3.56325 16.5064 3.87959L8.95219 11.4338L5.77148 12.2289L6.56666 9.04823L14.1208 1.49406Z" stroke="#C4C4C4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                    </svg>
                                                                </a>
                                                                <a data-reload-on-success='true' data-method="delete" data-url="{{ route("$routePrefix.release.destroy",  $post->id) }}" class="delete btn-delete text-xl font-medium text-[#2563EB] hover:text-black mb-2 mx-1 cursor-pointer">
                                                                    <svg width="16" height="18" viewBox="0 0 16 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                        <path d="M1 4.2H2.55556H15" stroke="#C4C4C4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M4.889 4.2V2.6C4.889 2.17565 5.05289 1.76869 5.34461 1.46863C5.63633 1.16857 6.03199 1 6.44455 1H9.55566C9.96822 1 10.3639 1.16857 10.6556 1.46863C10.9473 1.76869 11.1112 2.17565 11.1112 2.6V4.2M13.4446 4.2V15.4C13.4446 15.8243 13.2807 16.2313 12.9889 16.5314C12.6972 16.8314 12.3016 17 11.889 17H4.11122C3.69866 17 3.303 16.8314 3.01128 16.5314C2.71955 16.2313 2.55566 15.8243 2.55566 15.4V4.2H13.4446Z" stroke="#C4C4C4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M6.44434 8.2V13" stroke="#C4C4C4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M9.55566 8.2V13" stroke="#C4C4C4" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                                                    </svg>
                                                                </a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="list-subtitle text-lg font-medium text-[#111827] mb-2">{{ $post->subtitle}}</div>

                                                <p class="list-text text-sm text-[#374151] font-normal overflow-hidden line-clamp-3">
                                                    {{ str_replace("&nbsp;","",strip_tags($post->body)) }}
                                                </p>
                                            </div>

                                            @if($post->image == "" && $post->video != "")
                                                <div class="img-wrap">
                                                    <iframe width="200px" height="110" src="{{ $post->video }}"></iframe>
                                                </div>
                                            @endif

                                            @if($post->image != "")
                                                <div class="img-wrap">
                                                    <img src="{{ $post->image }}" alt="">
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach

                                    <input type="hidden" id="start" value="0">
                                    <input type="hidden" id="rowperpage" value="{{ $release['rowperpage'] }}">
                                    <input type="hidden" id="totalrecords" value="{{ $release['totalrecords'] }}">
                                    <input type="hidden" name="url" id="url" value="{{ route("$routePrefix.get-release") }}">
                                </div>
                            @else
                                <p class="text-lg font-medium text-[#111827] lg:text-center">Data not found</p>
                            @endif
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
    @if(App::environment('local'))
        <link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
    @else
        <link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    @endif
@endsection

@section('css-custom')
    <link href="{{ asset('css/release/release.css') }}" rel="stylesheet">
@endsection

@section('js-libraries')
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="{{ asset('js/admin/release.js') }}" data-url="{{ route($routePrefix .'.release.index-ahr') }}"></script>
    @if(App::environment('local'))
        <script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
        <script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
    @else
        <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
    @endif
@endsection

@section('js-custom')
    <x-delete-record-js-file />
@endsection
