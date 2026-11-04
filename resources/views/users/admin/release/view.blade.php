@extends('layouts.default-2')

@section('main-content')

@php
    $routePrefix = routePrefix();
@endphp
<style>
    h1, h2, h3, h4, h5, h6 {font-weight:700; color: #111827; margin-bottom: 12px;}
    p {font-size: 14px; color: #374151; margin-bottom: 12px;}
    h1 {font-size: 24px;}
    h2 {font-size: 22px;}
    h3 {font-size: 20px;}
    h4 {font-size: 18px;}
    h5 {font-size: 16px;}
    h6 {font-size: 14px;}
    th {font-size: 14px; font-weight: 500; color: #111827;}
    td {font-size: 14px; color: #374151;}
    table {margin-bottom: 12px;}
    .detail-section .detail-wrap .text ul {margin: 10px 0 10px 20px; padding-left: 20px; list-style: disc;}
    .detail-section .detail-wrap .text li {margin: 5px 0;}
    .detail-section .detail-wrap a {font-weight: 500; color:#2563EB;}
    .detail-section .detail-wrap a:hover, .detail-section .detail-wrap a:focus {color:#000000;}
    .detail-section .detail-wrap .text hr {margin: 20px 0; border: none; border-top: 1px solid #ccc;}
</style>
<div class="page-content py-6 bg-[#F9FAFB]">
    <div class="w-full container">
        <div class="flex flex-wrap -mx-3">
            <div class="w-full max-w-full px-3">
                <div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="content-title">
                        <h2 class="section-title">What's New</h2>
                    </div>

                    @if (isCentralDomain())
                    <div class="sub-nav flex items-center justify-end mb-8">
                        <a class="mr-2 btn-warning-small" href="{{ route("$routePrefix.release.index") }}" class=""><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                        <a class="btn-outline-primary-small" href="{{ route("$routePrefix.release.users", $release->id) }}" class=""><i class="fas fa-user sub-nav-icon mr-2"></i> Tenant Users</a>
                    </div>
                    @endif

                    <section class="detail-section">
                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3">
                                <div class="detail-wrap">
                                    @if($release->video != "")
                                        <iframe class="mb-6" width="100%" height="450" src="{{ $release->video }}"></iframe>
                                    @else
                                        <div class="img-wrap img-wrap-detail mb-6">
                                            <img src="{{ $release->image }}" alt="">
                                        </div>
                                    @endif

                                    <div class="text release-content">{!! $release->body !!}</div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css-custom')
    <link href="{{ asset('css/release/release-view.css') }}" rel="stylesheet">
@endsection
