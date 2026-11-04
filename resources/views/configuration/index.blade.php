@extends('layouts.default-2')

@section('main-content')

<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="content-title">
                        <h2 class="section-title">Configuration {{ date('Y') + 1 }}</h2>
                    </div>

                    <div class="main-content">
                        @if($errors->any())
                            @include('layouts._errors')
                        @endif

                        @if(session('message'))
                            @include('layouts._message')
                        @endif

                        <form class="form" id="configuration" enctype="multipart/form-data" method="POST" action="{{ $initializeConfigurationRoute }}">
                            @csrf
                            <div class="flex flex-wrap -mx-3">
                                <div class="card border border-[#E5E7EB] rounded-lg p-3" style="width: 20rem; margin: 2px 2px 2px 12px;">
                                    <div class="card-body">
                                        <label for="package" class="form-label form-custom-label">Package Rates for {{ date('Y') + 1 }} <req>*</req></label>
                                        <select class="form-select form-custom-input" name="package_id" id="dm-package-id">
                                            <option value="0">Select Option</option>
                                            <option value="all">ALL</option>
                                            @foreach ($packages as $package)
                                                <option value="{{ $package->id }}">{{ $package->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="card border border-[#E5E7EB] rounded-lg p-3" style="width: 20rem; margin: 2px 2px 2px 12px;">
                                    <div class="card-body">
                                        <label for="affiliate" class="form-label form-custom-label">Affiliate Rates for {{ date('Y') + 1 }} <req>*</req></label>
                                        <select class="form-select form-custom-input" name="affiliate_id" id="dm-affiliate-id">
                                            <option value="0">Select Option</option>
                                            <option value="all" style="font-weight: bolder">ALL</option>
                                            @foreach ($affiliates as $affiliate)
                                                <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 text-end">
                                <button type="submit" class="btn btn-primary btn-primary-small" id="configurationCreateButton"><span>Start</span></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css-libraries')
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
    <link href="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('js-libraries')
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    <script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('js-custom')
    <script type="text/javascript">
        //
    </script>
@endsection