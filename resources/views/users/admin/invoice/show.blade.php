@extends($layout)

@php
$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')

@if($errors->any())
@include('layouts._errors')
@endif

@if(session('message'))
@include('layouts._message')
@endif

<section class="select-edit-booking border border-[#E5E7EB] rounded-lg overflow-hidden">
    <div class="flex flex-wrap -mx-3">

         <h3 class="w-full px-6 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label">
            {{ $invoice->invoice_number }} {{ $invoice->reference_invoice ? '(Ref. '.$invoice->reference_invoice.')' : '' }}
        </h3>
       

    </div>
    <div class="flex flex-wrap p-4">
        <div class="w-full md:w-1/6 px-3">
            @if (!Storage::disk('s3')->missing('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo))
				<div><img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo) }}" class="w-full" alt="{{ $invoice->tourOperator->name }} Logo" onerror="noPhoto(this)" style="max-width: 150px;"></div>
			@else
				<div><img src="{{ asset('images/no-photo.png') }}" class="w-full" style="max-width: 150px;"></div>
			@endif
        </div>
        <div class="w-full md:w-1/4 px-3 user-details">
            <p class="text-xs text-[#020817] font-medium mb-2">Remit to</p>

            <p class="text-xs text-[#020817] mb-1"><strong>{{ $invoice->tourOperator->name }}</strong></p>
            <p class="text-xs text-[#020817] mb-1">{{ $invoice->tourOperator->address }}</p>
            <p class="text-xs text-[#020817] mb-1">{{ $invoice->tourOperator->postal_code }}</p>

            <br>

            @if($invoice->tourOperator->phone_number)
            <a href="tel:{{ $invoice->tourOperator->phone_number }}" class="contact text-xs text-[#2563EB] mb-1">
                <span class="text-[#020817] mr-2 fa fa-phone"></span>
                {{ $invoice->tourOperator->phone_number }}
            </a><br>
            @endif

            @if($invoice->tourOperator->email)
            <a href="mailto:{{ $invoice->tourOperator->email }}" class="contact text-xs text-[#2563EB] mb-1">
                <span class="text-[#020817] mr-2 fa fa-envelope"></span>
                {{ $invoice->tourOperator->email }}
            </a><br>
            @endif

            @if($invoice->tourOperator->website)
            <a href="{{ $invoice->tourOperator->website }}" target="__blank" class="contact text-xs text-[#2563EB] mb-1">
                <span class="text-[#020817] mr-2 fa fa-link"></span>
                {{ $invoice->tourOperator->website }}
            </a><br>
            @endif

        </div>

        <div class="w-full md:w-1/4 px-3 user-details">
            <p class="text-xs text-[#020817] font-medium mb-2">Billed to</p>

            <p class="text-xs text-[#020817] mb-1"><strong>{{ $invoice->affiliate->name }}</strong></p>
            <p class="text-xs text-[#020817] mb-1">{!! $invoice->affiliate->address ?? "<br>" !!}</p>
            <p class="text-xs text-[#020817] mb-1">{!! $invoice->affiliate->postal_code ?? "<br>" !!}</p>

            <br>

            @if($invoice->affiliate->phone_number)
            <a href="tel:{{ $invoice->affiliate->phone_number }}" class="contact text-xs text-[#2563EB] mb-1">
                <span class="text-[#020817] mr-2 fa fa-phone"></span>
                {{ $invoice->affiliate->phone_number }}
            </a><br>
            @endif

            @if($invoice->affiliate->email)
            <a href="mailto:{{ $invoice->affiliate->email }}" class="contact text-xs text-[#2563EB] mb-1">
                <span class="text-[#020817] mr-2 fa fa-envelope"></span>
                {{ $invoice->affiliate->email }}
            </a><br>
            @endif

            @if($invoice->affiliate->website)
            <a href="{{ $invoice->affiliate->website }}" target="__blank" class="contact text-xs text-[#2563EB] mb-1">
                <span class="text-[#020817] mr-2 fa fa-link"></span>
                {{ $invoice->affiliate->website }}
            </a><br>
            @endif
        </div>

        <div class="w-full md:w-1/3 px-3 flex flex-col items-end">
            <div class="flex flex-wrap gap-2 justify-end items-center">
                {{-- Generate PDF + Print segmented button group.
                     display:inline-flex is set inline so it beats .btn-info-small's
                     inline-block (equal specificity, but that rule loads later),
                     which is what was pushing the icon above the label. --}}
                <div class="inline-flex" role="group" aria-label="Invoice document actions">
                    <a class="print-booking-btn btn-info-small"
                    onclick="generatePDFAsync(event)"
                    href="{{ route(routePrefix() . '.invoice.save-pdf', [ 'uuid' => $invoice->uuid, 'reload' => 1 ]) }}"
                    style="display:inline-flex; align-items:center; justify-content:center; min-width:100px; border-top-right-radius:0; border-bottom-right-radius:0;">
                    <img src="{{ asset('images/rebook.png') }}" height="14" alt="" class="mr-2">
                    @if(isset($pdfReady) && !$pdfReady) Generate @else Re-Generate @endif PDF
                
                </a>
                    
                    <a class="print-booking-btn btn-info-small"
                        href="{{ route(routePrefix() . '.invoice.print-info', [ 'uuid' => $invoice->uuid ]) }}" target="_blank"
                        style="display:inline-flex; align-items:center; justify-content:center; min-width:100px; margin-left:-1px; border-top-left-radius:0; border-bottom-left-radius:0;">
                        <img src="{{ asset('images/pring-icon.png') }}" height="14" alt="" class="mr-2"> Print </a>
                 
                </div>

                @if($layout != "layouts.blank")
                    <button type="button" class="print-booking-btn btn-info-small" id="send-invoice-email" data-bs-target="#modal-send-invoice" data-bs-toggle="modal" style="display:inline-flex; align-items:center; justify-content:center;"><img src="{{ asset('images/email.png') }}" alt="" class="mr-2"> Send Invoice</button>
                    <button type="button" class="print-booking-btn btn-info-small" data-bs-target="#modal-reference-invoice" data-bs-toggle="modal" style="display:inline-flex; align-items:center; justify-content:center;"><i class="fa fa-receipt mr-2"></i> Reference Invoice</button>
                @endif



    

            </div>

            @if(isset($pdfReady) && !$pdfReady) <div id="pdf-not-generated" style=" margin-top: 7px; font-size: 11px; color: #805f04;">
                    PDF not yet generated.
                </div> @endif
        </div>
    </div>
</section>



@if( $layout == "layouts.blank")
<section class="select-edit-booking py-5">
<div class="flex flex-wrap -mx-3">
    <div class="w-full md:w-1/2 max-w-full px-3">
        <div class="border border-[#E5E7EB] rounded-lg overflow-hidden">
            <!-- Inline Form for loading as async view -->
            <div class="block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full" id="sendInvoiceEmailAsyncFormMessage"><strong>Send Invoice</strong></div>
            <form method="post" action="{{ route(routePrefix() . '.invoice.send-email', [ 'uuid' => $invoice->uuid ]) }}" id="sendInvoiceEmailAsyncForm">
                @csrf
                <div class="input-group flex items-center p-4">
                    <input type="text" name="email" id="email-async" placeholder="Email Address" class="form-control form-custom-input mr-2" value="{{ $invoice->affiliate->email }}" required>
                    <input type="submit" class="btn btn-outline-primary btn-outline-small" value="Send">
                </div>
            </form>
        </div>
    </div>

    <div class="w-full md:w-1/2 max-w-full px-3">
        <div class="border border-[#E5E7EB] rounded-lg overflow-hidden">
            <div class="block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full" id="refNumberInvoiceAsyncFormMessage"><strong>Reference Invoice</strong></div>
            <form method="post" action="{{ route(routePrefix() . '.invoice.reference-invoice', [ 'uuid' => $invoice->uuid ]) }}" id="refNumberInvoiceAsyncForm">
                @csrf
                <div class="input-group flex items-center p-4">
                    <input type="text" name="reference_invoice" id="reference_invoice-async" placeholder="Reference Invoice Number" maxlength="50" class="form-control form-custom-input mr-2" value="{{ $invoice->reference_invoice }}" required>
                    <input type="submit" class="btn btn-outline-primary btn-outline-small" value="Update">
                </div>
            </form>
        </div>
    </div>
</div>
</section>
@endif

<section class="select-edit-booking pb-5">
    <div class="flex flex-wrap -mx-3">
        <div class="w-full max-w-full px-3">
            <div class="table-respons" id="bookingTableDiv">
                <table class="table table-bordered table-striped table-hover datatable datatable-filter w-full" id="datatableForRefundDetails" style="margin-bottom: 50px;">
                    <thead>
                        <tr>
                            <th class="th-custom-css">Invoice</th>
                            <th class="th-custom-css">Reference</th>
                            <th class="th-custom-css">Date Range</th>
                            <th class="th-custom-css">Created Date</th>
                            <th class="th-custom-css">Due Date</th>
                            <th class="th-custom-css">Total Tourists</th>
                            <th class="th-custom-css">Total Infants</th>
                            <th class="th-custom-css">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody id="bookingTableBody">
                        <tr>
                            <td class="td-custom-css">{{ $invoice->invoice_number }}</td>
                            <td class="td-custom-css">{{ $invoice->reference_invoice ?? 'N/A' }}</td>
                            <td class="td-custom-css">{{ date('m/d/Y', strtotime($invoice->from_date)) .' - '. date('m/d/Y', strtotime($invoice->to_date)) }}</td>
                            <td class="td-custom-css">{{ date('m/d/Y @ H:i:s', strtotime($invoice->created_at)) }}</td>
                            <td class="td-custom-css">{{ date('m/d/Y', strtotime($invoice->due_date)) }}</td>
                            <td class="td-custom-css">{{ $touristGrandTotal }}</td>
                            <td class="td-custom-css">{{ $infantsGrandTotal }}</td>
                            <td class="td-custom-css">
                                {{ moneyFormat($invoice->amount) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <table class="table thead-sm table-bordered table-striped table-hover datatable datatable-filter w-full affiliate-invoice-report" id="datatableBookingReport" style="margin-bottom: 10px;">
                    <thead>
                        
                        <tr>
                            <th class="th-custom-css">ID</th>
                            <th class="th-custom-css">Ref. No</th>
                            <th class="th-custom-css">Name</th>
                            <th class="th-custom-css">Tourists</th>
                            <th class="th-custom-css">Package</th>
                            <th class="th-custom-css">Booking Date</th>
                            <!-- <th class="th-custom-css">Created Date</th> -->
                            <th class="th-custom-css">Total Tourist</th>
                            <th class="th-custom-css">Total Infants</th>
                            <th class="th-custom-css">Booking Status</th>
                            <th class="th-custom-css">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="tbody-sm">
                        @foreach($invoice_data as $invoicedetail)
                        <tr>
                            <td class="td-custom-css"><a href="{{ route(routePrefix() . '.booking.show', [ 'booking' => $invoicedetail['bookingid'] ]) }}" target="_blank">{{ $invoicedetail['bookingid'] }}</a></td>
                            <td class="td-custom-css">{{ $invoicedetail['reference_booking'] ?? 'N/A'}}</td>
                            <td class="td-custom-css">{{ Str::limit($invoicedetail['name'], 20, ' ...') }}</td>
                            <td class="td-custom-css">@if(!empty($invoicedetail['ageGroups']))
									@foreach($invoicedetail['ageGroups'] as $ageGroup)
                                    {{ ($ageGroup['tourists'] > 0) ? $ageGroup['tourists']." ".$ageGroup['rate_for'] : $ageGroup['infants']." ".$ageGroup['rate_for'] }} <br/>
									@endforeach
                                @endif
                            </td>
                            <td class="td-custom-css">{{ Str::limit($invoicedetail['package'], 33, ' ...') }}</td>
                            <td class="td-custom-css">{{ date('m/d/Y', strtotime($invoicedetail['bookingdate']))." @ ".$invoicedetail['time_from'] }}</td>
                            <!-- <td class="td-custom-css">date('m/d/Y', strtotime($invoicedetail['created_at']))</td> -->
                            <td class="td-custom-css">{{ $invoicedetail['TouristTotal'] }}</td>
                            <td class="td-custom-css">{{ $invoicedetail['InfantsTotal'] }}</td>
                            <td class='td-custom-css success-text-color fw-normal'>{{ $invoicedetail['booking_status'] }}</td>
                            @if($invoicedetail['payment_status']!=null)
                            <td class="td-custom-css success-text-color fw-normal">
                                {{ moneyFormat($invoicedetail['amount'] + $invoicedetail['custom_charge_amount'] - $invoicedetail['custom_charge_refund_amount']) }}
                            </td>
                            @elseif($invoicedetail['payment_status']==null || $invoicedetail['payment_status']=='Failed')
                            <td class="td-custom-css alert-text-color fw-normal">
                                {{ moneyFormat($invoicedetail['amount'] + $invoicedetail['custom_charge_amount'] - $invoicedetail['custom_charge_refund_amount']) }}
                            </td>
                            @elseif($invoicedetail['status']=='Cancelled')
                            <td class="td-custom-css info-text-color fw-normal">
                                {{ moneyFormat($invoicedetail['amount'] + $invoicedetail['custom_charge_amount'] - $invoicedetail['custom_charge_refund_amount']) }}
                            </td>
                            @endif

                        </tr>
                        @endforeach
						<tr>
                            @php
                                $finalAmount = 0;
                            @endphp
                            @if(count($invoice_data) > 0)
                                @php
                                    $finalAmount = array_sum(array_column($invoice_data, 'amount'));
                                    $finalAmount = $finalAmount + array_sum(array_column($invoice_data, 'custom_charge_amount'));
                                    $finalAmount = $finalAmount - array_sum(array_column($invoice_data, 'custom_charge_refund_amount'));
                                @endphp
                            @endif
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <!-- <td class="td-custom-css"></td> -->
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css"></td>
                            <td class="td-custom-css success-text-color fw-normal">{{ moneyFormat($finalAmount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- Resend Modal open -->
	<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-send-invoice" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
					<h4 class="modal-title text-lg font-semibold text-[#111827]">Send Invoice Information</h4>
					<button type="button" class="btn-close refresh-current-invoice" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				</div>

				<div class="modal-body p-4">
					<form method="post" action="{{ route(routePrefix() . '.invoice.send-email', [ 'uuid' => $invoice->uuid ]) }}" data-on-success-msg="Send Invoice Details">
                        @csrf
						<div class="mb-3">
							<label for="email" class="form-label form-custom-label">Email Address</label>
							<input type="text" name="email" id="email" maxlength="50" class="form-control form-custom-input" value="{{ $invoice->affiliate->email }}" required>
						</div>
						<div><button class="btn btn-main btn-main-sm btn-primary-small">Send</button></div>
					</form>
				</div>
			</div>
		</div>
	</div>
    <!-- Reference Invoice Modal open -->
	<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-reference-invoice" tabindex="-1" role="dialog" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
					<h4 class="modal-title text-lg font-semibold text-[#111827]">Reference Invoice</h4>
					<button type="button" class="btn-close refresh-current-invoice" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				</div>

				<div class="modal-body p-4">
					<form method="post" action="{{ route(routePrefix() . '.invoice.reference-invoice', [ 'uuid' => $invoice->uuid ]) }}" data-on-success-msg="Reference Invoice">
                        @csrf
						<div class="mb-3">
							<label for="reference_invoice" class="form-label form-custom-label">Reference Invoice Number</label>
							<input type="text" name="reference_invoice" id="reference_invoice" maxlength="50" class="form-control form-custom-input" placeholder="Add reference invoice number" value="{{ $invoice->reference_invoice }}" required>
						</div>
						<div><button class="btn btn-main btn-main-sm btn-primary-small">Update</button></div>
					</form>
				</div>
			</div>
		</div>
	</div>
</section>
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endif
@endsection

@section('css-custom')
<!-- <link href="{{ asset('css/user-common/invoice.css') }}" rel="stylesheet"> -->
<style>
    /* Custom Styles */
    .compact-table-style thead th {
        padding: 6px 8px;
        font-size: 12px;
    }
</style>
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
@else
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
@endif
<script src="https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.8-beta.47/jquery.inputmask.min.js"></script>
@endsection

@section('js-custom')
<script src="{{ asset('js/admin/invoice.js') }}?v={{ @filemtime(public_path('js/admin/invoice.js')) ?: time() }}"></script>
<script type="text/javascript">
    /* Custom JS */
</script>
@endsection
