@extends('layouts.default-2')
@php
    use Illuminate\Support\Facades\Auth;

    $displayName = Auth::user()->roles->pluck("display_name")->first();
    if (count(Auth::user()->roles) == 2) {
        $displayName = "Manager";
    } 
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
                        <h2 class="section-title">{{$displayName}} Dashboard</h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection