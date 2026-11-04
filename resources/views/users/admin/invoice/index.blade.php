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
								<h2 class="section-title !mb-0">Invoices</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route(routePrefix() . '.invoice.affiliate-due-amount') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> Total Due Amount</a>
							</div>
						</div>
					</div>

                    <!-- <div class="content-title">
                        <h2 class="section-title">Invoices</h2>
                    </div>

                    <div class="sub-nav flex items-center justify-end mb-8">
                        <a class="mr-2 btn-warning-small" href="{{ route(routePrefix() . '.invoice.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                        <a class="btn-outline-primary-small" href="{{ route(routePrefix() . '.invoice.affiliate-due-amount') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> Total Due Amount</a>
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
                                        <!-- <th>Tour Operator</th> -->
                                        <th class="th-custom-css">Affiliate</th>
                                        <th class="th-custom-css">Invoice Number</th>
                                        <th class="th-custom-css">From Date</th>
                                        <th class="th-custom-css">To Date</th>
                                        <th class="th-custom-css">Amount</th>
                                        <th class="th-custom-css">Due Date</th>
                                        <th class="th-custom-css">Email Sent</th>
                                        <th class="th-custom-css">Status</th>
                                        <th class="th-custom-css">Action</th>
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

<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-invoice-status" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
            <form method="post" action="" id="form-invoice-status">
                @method('patch')
                @csrf
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-[#111827]">Update Invoice</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="status" class="form-label form-custom-label">Status</label>
                        <select name="status" id="status" class="form-control form-custom-input" required>
                            @foreach(config('constants.invoice_status') as $statusKey => $statusValue)
                            <option value="{{ $statusKey }}">{{ $statusValue }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="modal-footer px-4 pb-4">
                    <button type="submit" class="btn btn-main btn-main-sm btn-primary-small">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>
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
    
    var url = "{{ route(routePrefix() . '.invoice.index-ahr') }}";
    $(function() {
        dtbl = $('#datatable').DataTable({
            processing: true,
            // serverSide: true,
            responsive: true,
            bStateSave: true,
            ajax: {
                method: 'POST',
                url: url,
            },
            order: [
                [0, 'asc'],
                [1, 'asc'],
                [2, 'asc']
            ],
            columns: [
                // {
                //     data: 'tour_operator_id',
                //     name: 'tour_operator_id'
                // },
                {
                    data: 'customer_name',
                    name: 'customer_name',
                    className: 'td-custom-css'
                },
                {
                    data: 'invoice_number',
                    name: 'invoice_number',
                    className: 'td-custom-css'
                },
                {
                    data: 'from_date',
                    name: 'from_date',
                    className: 'td-custom-css'
                },
                {
                    data: 'to_date',
                    name: 'to_date',
                    className: 'td-custom-css'
                },
                {
                    data: 'amount',
                    name: 'amount',
                    className: 'td-custom-css',
                    render: function(data) {
                        return currency(parseFloat(data).toFixed(2)).format();
                    }
                },
                {
                    data: 'due_date',
                    name: 'due_date',
                    className: 'td-custom-css'
                },
                {
                    data: 'email_sent',
                    name: 'email_sent',
                    className: 'td-custom-css'
                },
                {
                    data: 'status',
                    name: 'status',
                    className: 'text-center td-custom-css'
                },
                {
                    data: 'action',
                    className: 'text-center td-custom-css whitespace-nowrap',
                    orderable: false,
                    searchable: false,
                    width: '20%'
                },
            ]
        });
    });

    function changeStatus(id) {
        var session = '{{ session("userType") }}';
        var url = "{{ route(routePrefix() . '.invoice.update', ':id') }}";
        
        url = url.replace(':id', id);
        $('#form-invoice-status').attr('action', url)
        $('#modal-invoice-status').modal('toggle');
    }
</script>

<x-delete-record-js-file />
@endsection
