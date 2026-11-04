<?php ini_set('memory_limit', '-1'); ?>
<html>
<head>
	<link href="http://netdna.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.css" rel="stylesheet">
</head>
<body>
<style>

	 @page { margin: 0px; padding: 0px; }
	body, html { margin: 0px; padding: 0px; }
	.detail-text{ font-size: 13px;}
	.bordered{ border: 1px solid #ccc;}
	.pdf-btn{
		padding:10px; 
		cursor: pointer;
		text-decoration:none;
		font-weight:bolder;
		background-color: green;
		border-radius: 5px;color: white;
		margin: 4px;
	}
	table {
		font-size: 12px !important;
	}

	thead{
		font-size: 12px !important;
	}

	.align-items-center {
		align-items: center!important;
	}
/*

	.select-edit-booking {
		border: none;
		padding: 5px;
		margin: 0px;
	}

	img {
		width: auto;
		max-width: 100%;
	}
	p {
		margin: 0px;
	}
	.select-edit-booking .user-details .contactnum {
		font-weight: 400;
		color: #333333;
		line-height: 1;
		padding-left: 2px;
		position: relative;
		top: -6px;
	}
	.select-edit-booking .user-details .contactemail {
		font-weight: 400;
		color: #333333;
		line-height: 1;
		padding-left: 2px;
		position: relative;
		top: -6px;
	}
	.select-edit-booking .user-details .contact-link {
		font-weight: 400;
		color: #333333;
		line-height: 1;
		padding-left: 2px;
		position: relative;
		top: -6px;
	}
	.booking-note {
		margin-bottom: 	px;
	}
	.booking-note:first-child {
		margin-top: 5px;
		border-top: 1px solid #ddd;
		padding-top: 5px;
	}
	.booking-note .note-text {
		white-space: break-spaces;
	}
	/* .booking-history.table> :not(caption)>*:last-child>* {
		border-bottom: 0 none;
	} */
	/* 
	.booking-history .book-hist-d-t {
		width: 100%;
	}
	.w-100 {
		width: 100%!important;
	}
	.table {
		width: 100%;
		margin-bottom: 1rem;
		color: #212529;
		vertical-align: top;
		border-color: #dee2e6;
	}
	table {
		caption-side: bottom;
		border-collapse: collapse;
	}
	.table>thead {
		vertical-align: bottom;
	}
	tbody, td, tfoot, th, thead, tr {
		border-color: inherit;
		border-style: solid;
		border-width: 0;
	}
	.table-bordered>:not(caption)>* {
		border-width: 1px 0;
	}
	.table>:not(:last-child)>:last-child>* {
		border-bottom-color: currentColor;
	}
	.table-bordered>:not(caption)>*>* {
		border-width: 0 1px;
	}
	.table>:not(caption)>*>* {
		padding: 0.5rem 0.5rem;
		border-bottom-width: 1px;
	}
	.table>tbody {
		vertical-align: inherit;
	}
	.table-striped>tbody>tr:nth-of-type(odd) {
		--bs-table-accent-bg: var(--bs-table-striped-bg);
		color: var(--bs-table-striped-color);
	}
	.table-bordered>:not(caption)>* {
		border-width: 1px 0;
	}
	.table {
		color: #444;
	}
	.success-text-color {
		color: #2fa136 !important;
	}
	.alert-text-color {color: #ff5e5e !important;}
	.info-text-color {color: #0000c9 !important;}
	.fw-normal {
		font-weight: 400!important;
	} */
</style>
<br>

<div class="container-fluid" style="margin: 10px !important; ">


	<div class="row justify-content-center">
		<div class="col-xs-12 text-center">
			@if (!Storage::disk('s3')->missing('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo))
				<div><img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo) }}" class="img-fluid" alt="{{ $invoice->tourOperator->name }} Logo" onerror="noPhoto(this)" width="135" height="122"></div>
			@else
				<div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg"></div>
			@endif
		</div>
	</div>



	<div class="row align-items-center bordered" style="margin-bottom: 10px;">
		<div class="col-xs-6 user-details detail-text">
			<p>Remit to <br />

			<strong>{{ $invoice->tourOperator->name }}</strong>
			<br />{{ $invoice->tourOperator->address }}
			<br />{{ $invoice->tourOperator->postal_code }}
			</p>

			@if($invoice->tourOperator->phone_number)
			<div style="margin-bottom: 5px;">
					<span>
				<img src="{{public_path('images/email/phone.png')}}" style="width: 16px; height: 16px;">
					</span>
					<span>
				<a href="tel:{{ $invoice->tourOperator->phone_number }}" class="contactnum">{{ $invoice->tourOperator->phone_number }}</a>
					</span>
			</div>

			@endif

			@if($invoice->tourOperator->email)
			<div style="margin-bottom: 5px;">
					<span>
				<img src="{{public_path('images/email/mail.png')}}" style="width: 16px; height: 16px;">
					</span>
					<span>
				<a href="mailto:{{ $invoice->tourOperator->email }}" class="contactemail">{{ $invoice->tourOperator->email }}</a>
					</span>
			</div>
			@endif

			@if($invoice->tourOperator->website)
			<div style="margin-bottom: 5px;">
					<span>
				<img src="{{public_path('images/email/url.png')}}" style="width: 16px; height: 16px;">
					</span>
					<span>
				<a href="{{ $invoice->tourOperator->website }}" target="__blank" class="contact-link">{{ $invoice->tourOperator->website }}</a>
					</span>
			</div>
			@endif
		</div>

		<div class="col-xs-6 user-details">
			<p>Billed to<br />
			<strong>{{ $invoice->affiliate->name }}</strong>
			<br />{{ $invoice->affiliate->address }}
			<br />{{ $invoice->affiliate->postal_code }}
			</p>
			
			@if($invoice->affiliate->phone_number)
				<div style="margin-bottom: 5px;">
					<span>
						<img src="{{public_path('images/email/phone.png')}}" style="width: 16px; height: 16px;">
					</span>
					<span>
						<a href="tel:{{ $invoice->affiliate->phone_number }}" class="contactnum">{{ $invoice->affiliate->phone_number }}</a>
					</span>
				</div>
			@endif

			@if($invoice->affiliate->email)
				<div style="margin-bottom: 5px;">
					<span>
						<img src="{{public_path('images/email/mail.png')}}" style="width: 16px; height: 16px;">
					</span>
					<span>
						<a href="mailto:{{ $invoice->affiliate->email }}" class="contactemail">{{ $invoice->affiliate->email }}</a>
					</span>
				</div>
			@endif

			@if($invoice->affiliate->website)
				<div style="margin-bottom: 5px;">
					<span>
						<img src="{{public_path('images/email/url.png')}}" style="width: 16px; height: 16px;">
					</span>
					<span>
						<a href="{{ $invoice->affiliate->website }}" target="__blank" class="contact-link">{{ $invoice->affiliate->website }}</a>
					</span>
				</div>
			@endif
		</div>
	</div>


	<div class="row align-items-center">
		<div class="col-12 p-0">
			<div class="table-respons" id="bookingTableDiv">
				<table class="table table-bordered thead-sm w-100"  style="margin-bottom: 10px;">
					<thead>
					<tr>
						<th>Invoice</th>
						<th>Reference</th>
						<th>Date Range</th>
						<th>Created Date</th>
						<th>Due Date</th>
						<th>Total Tourists</th>
						<th>Total Infants</th>
						<th>Total</th>
					</tr>
					</thead>
					<tbody id="bookingTableBody" class="tbody-sm">
						<tr>
							<td>{{ $invoice->invoice_number }}</td>
							<td>{{ $invoice->reference_invoice ?? 'N/A' }}</td>
							<td>{{ date('m/d/Y', strtotime($invoice->from_date)) .' - '. date('m/d/Y', strtotime($invoice->to_date)) }}</td>
							<td>{{ date('m/d/Y @ H:i:s', strtotime($invoice->created_at)) }}</td>
							<td>{{ date('m/d/Y', strtotime($invoice->due_date)) }}</td>
							<td>{{ $touristGrandTotal }}</td>
							<td>{{ $infantsGrandTotal }}</td>
							<td>{{ moneyFormat($invoice->amount) }}</td>
						</tr>
					</tbody>
					@if(!empty($invoice->external_payment_link))
					<tfoot>
						<tr>
							<td colspan="100%" style="text-align: right; padding:10px;"> 
								<p>
								<a  href="{{$invoice->external_payment_link}}"
									style="padding:3px;text-decoration:none;font-weight:bolder;background-color: green;border-radius: 1px;color: white;margin: 2px;"
									target="_blank">Pay Online</a>
								</p>
							</td>
						</tr>
					</tfoot>
					@endif
				</table>
				<table class="table thead-sm table-bordered w-100" style="margin-bottom: 10px;">
					<thead>
					<tr>
						<th>ID</th>
						<th>Ref. No</th>
						<th>Name</th>
						<th>Tourists</th>
						<th>Package</th>
						<th>Booking Date</th>
						<!-- <th style="width:13%;">Created Date</th> -->
						<th>Total Tourist</th>
						<th>Total Infants</th>
						<th>Booking Status</th>
						<th>Amount</th>
					</tr>
					</thead>
					<tbody class="tbody-sm">
					@foreach($invoice_data as $invoicedetail)
						<tr>
							<td>{{ $invoicedetail['bookingid'] }}</td>
							<td>{{ $invoicedetail['reference_booking'] ?? 'N/A' }}</td>
							<td>{{ Str::limit($invoicedetail['name'], 20, ' ...') }}</td>
							<td>@if(!empty($invoicedetail['ageGroups']))
									@foreach($invoicedetail['ageGroups'] as $ageGroup)
										{{ ($ageGroup['tourists'] > 0) ? $ageGroup['tourists']." ".$ageGroup['rate_for'] : $ageGroup['infants']." ".$ageGroup['rate_for'] }} <br/>
									@endforeach
								@endif
							</td>
							<td>{{ Str::limit($invoicedetail['package'], 20, ' ...') }}</td>
							<td>{{ date('m/d/Y', strtotime($invoicedetail['bookingdate']))." @ ".$invoicedetail['time_from'] }}</td>
							{{-- <!-- <td>{{ date('m/d/Y', strtotime($invoicedetail['created_at'])) }}</td> --> --}}
							<td>{{ $invoicedetail['TouristTotal'] }}</td>
							<td>{{ $invoicedetail['InfantsTotal'] }}</td>
							<td class='success-text-color fw-normal'>{{ $invoicedetail['booking_status'] }}</td>
							@if($invoicedetail['payment_status']!=null)
								<td class="success-text-color fw-normal">
									{{ moneyFormat($invoicedetail['amount'] + $invoicedetail['custom_charge_amount'] - $invoicedetail['custom_charge_refund_amount']) }}
								</td>
							@elseif($invoicedetail['payment_status']==null)
								<td class="alert-text-color fw-normal">
									{{ moneyFormat($invoicedetail['amount'] + $invoicedetail['custom_charge_amount'] - $invoicedetail['custom_charge_refund_amount']) }}
								</td>
							@elseif($invoicedetail['status']=='Cancelled')
								<td class="info-text-color fw-normal">
									{{ moneyFormat($invoicedetail['amount'] + $invoicedetail['custom_charge_amount'] - $invoicedetail['custom_charge_refund_amount']) }}
								</td>
							@endif
						</tr>
					@endforeach

					</tbody>
				</table>




				
					@if(!empty($invoice->external_payment_link))
					<hr />
					<p class="text-right mr-5"> 
						 <strong>Pay Online:</strong> <span class="pdf-btn"> {{$invoice->external_payment_link}} </span>
					</p>
					@endif
			
			</div>
		</div>
	</div>


</div>
</body>
</html>
