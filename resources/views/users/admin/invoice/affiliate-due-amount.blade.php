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
								<h2 class="section-title !mb-0">Affiliate Due Amount</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<?php
                                if(session('userType') == 'Admin'){
                                    $routePrefix = 'admin.';
                                }else{
                                    $routePrefix = '';
                                }
                                ?>
                                <a class="btn-primary-small" href="{{ route($routePrefix.'tour-operator.invoice.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> Invoices</a>
							</div>
						</div>
					</div>

                    <!-- <div class="content-title">
                        <h2 class="section-title">Affiliate Due Amount</h2>
                    </div>

                    <div class="sub-nav flex items-center justify-end mb-8">
                        <?php
                        if(session('userType') == 'Admin'){
                            $routePrefix = 'admin.';
                        }else{
                            $routePrefix = '';
                        }
                        ?>
                        <a class="mr-2 btn-warning-small" href="{{ route($routePrefix.'tour-operator.invoice.affiliate-due-amount') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                        <a class="btn-outline-primary-small" href="{{ route($routePrefix.'tour-operator.invoice.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> Invoices</a>
                    </div> -->

                    <div class="main-content">
                        @if($errors->any())
                        @include('layouts._errors')
                        @endif

                        @if(session('message'))
                        @include('layouts._message')
                        @endif
                        
                        <div class="w-full overflow-y-auto">
                            <table class="table table-bordered table-hover w-full border-collapse" id="datatable">
                                <thead>
                                    <tr>
                                        <th class="th-custom-css">Affiliate</th>
                                        <th class="th-custom-css">Amount</th>
                                    </tr>
                                </thead>

                                <tbody></tbody>
                            </table>
                        </div>
                    </div> {{-- /.main-content --}}
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

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
@else
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
@endif
@endsection

@section('js-custom')
<script>
    var dtbl = null;
    var session = '{{ session("userType") }}';
    if (session == "Admin") {
        var url = "{{ route('admin.tour-operator.invoice.affiliate-due-amount-ahr') }}";
    } else if (session == "Tour Operator") {
        var url = "{{ route('tour-operator.invoice.affiliate-due-amount-ahr') }}";
    }
    $(function() {
        dtbl = $('#datatable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                method: 'POST',
                url: url,
            },
            order: [
                [0, 'asc'],
                [1, 'asc']
            ],
            columns: [{
                    data: 'name',
                    name: 'name',
                    className: 'td-custom-css'
                },
                {
                    data: 'amount',
                    name: 'amount',
                    className: 'td-custom-css',
                    render: function(data) {
                        return currency(parseFloat(data).toFixed(2)).format();
                    }
                }
            ]
        });
    });
</script>

<x-delete-record-js-file />
@endsection
