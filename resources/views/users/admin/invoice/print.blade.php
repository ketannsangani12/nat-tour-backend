@extends('layouts.default-2')

@php
$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')

@if(!isset($pdf))
<div class="text-end div-print-btn">
    <button type="button" class="btn btn-primary" onclick="print()"><i class="fa fa-print"></i> Print</button>
</div>
@endif

<br>

<section class="select-edit-booking">
    <div class="row align-items-center">
        <div class="col-md-4 logo-details">
            @if (!Storage::disk('s3')->missing('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo))
				<div><img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo) }}" class="img-fluid" alt="{{ $invoice->tourOperator->name }} Logo" onerror="noPhoto(this)" style="width: 220px;"></div>
			@else
				<div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg"></div>
			@endif
        </div>

        <div class="col-md-4 user-details">
            <p>Remit to</p>

            <p><strong>{{ $invoice->tourOperator->name }}</strong></p>
            <p>{{ $invoice->tourOperator->address }}</p>
            <p>{{ $invoice->tourOperator->postal_code }}</p>

            <br><br>

            @if($invoice->tourOperator->phone_number)
            <a href="tel:{{ $invoice->tourOperator->phone_number }}" class="contactnum">{{ $invoice->tourOperator->phone_number }}</a>
            @endif

            @if($invoice->tourOperator->email)
            <a href="mailto:{{ $invoice->tourOperator->email }}" class="contactemail">{{ $invoice->tourOperator->email }}</a>
            @endif

            <a href="{{ $invoice->tourOperator->website }}" target="__blank" class="contact-link">{{ $invoice->tourOperator->website }}</a>
        </div>

        <div class="col-md-4 user-details">
            <p>Billed to</p>

            <p><strong>{{ $invoice->affiliate->name }}</strong></p>
            <p>{{ $invoice->affiliate->address }}</p>
            <p>{{ $invoice->affiliate->postal_code }}</p>

            <br><br>

            @if($invoice->affiliate->phone_number)
            <a href="tel:{{ $invoice->affiliate->phone_number }}" class="contactnum">{{ $invoice->affiliate->phone_number }}</a>
            @endif

            @if($invoice->affiliate->email)
            <a href="mailto:{{ $invoice->affiliate->email }}" class="contactemail">{{ $invoice->affiliate->email }}</a>
            @endif

            <a href="{{ $invoice->affiliate->website }}" target="__blank" class="contact-link">{{ $invoice->affiliate->website }}</a>
        </div>
    </div>
</section>

<section class="select-edit-booking">
    <div class="row align-items-center" style="    padding: 10px 20px 15px;">
        <div class="col-12 p-0">
            <div class="table-respons" id="bookingTableDiv">
                <table class="table table-bordered table-striped table-hover datatable datatable-filter w-100" id="datatableForRefundDetails" style="margin-bottom: 50px;">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Reference</th>
                            <th>Date Range</th>
                            <th>Created Date</th>
                            <th>Due Date</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="bookingTableBody">
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->reference_invoice ?? 'N/A' }}</td>
                            <td>{{ $invoice->from_date .' / '. $invoice->to_date }}</td>
                            <td>{{ $invoice->created_at }}</td>
                            <td>{{ $invoice->due_date }}</td>
                            <td>{{ moneyFormat($invoice->amount) . ' — Unpaid' }}</td>
                        </tr>
                    </tbody>
                </table>
                <table class="table table-bordered table-striped table-hover datatable datatable-filter w-100 affiliate-invoice-report" id="datatableBookingReport" style="margin-bottom: 10px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ref. No</th>
                            <th>Name</th>
                            <th>Tourists</th>
                            <th>Package</th>
                            <th>Booking Date</th>
                            <!-- <th>Created Date</th> -->
                            <th>Total Tourist</th>
                            <th>Booking Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice_data as $invoicedetail)
                        <tr>
                            <td><a href="{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.show', [ 'booking' => $invoicedetail['bookingid'] ]) }}" target="_blank">{{ $invoicedetail['bookingid'] }}</a></td>
                            <td>{{ $invoicedetail['reference_booking'] ?? 'N/A'}}</td>
                            <td>{{ $invoicedetail['name']}}</td>
                            <td>@if($invoicedetail['AdultsAges13Up']>0)
                                {{ $invoicedetail['AdultsAges13Up']." Adults (Ages 13 & Up)" }}<br />
                                @elseif($invoicedetail['AdultsMilitaryTeacher']>0)
                                {{ $invoicedetail['AdultsMilitaryTeacher']." Adults - Military/Teacher" }}<br />
                                @elseif($invoicedetail['ChildrenAges4To7']>0)
                                {{ $invoicedetail['ChildrenAges4To7']." Children (Ages 4 -7)" }}<br />
                                @elseif($invoicedetail['ChildrenAges4To7MilitaryTeachers']>0)
                                {{ $invoicedetail['ChildrenAges4To7MilitaryTeachers']." Children (Ages 4-7) - Military/Teachers" }}<br />
                                @elseif($invoicedetail['ChildrenAges8To12']>0)
                                {{ $invoicedetail['ChildrenAges8To12']." Children (Ages 8-12)" }}<br />
                                @elseif($invoicedetail['ChildrenAges8To12MilitaryTeachers']>0)
                                {{ $invoicedetail['ChildrenAges8To12MilitaryTeachers']." Children (Ages 8-12) - Military/Teachers" }}<br />
                                @elseif($invoicedetail['Teenager']>0)
                                {{ $invoicedetail['Teenager']." Teenager" }}<br />
                                @elseif($invoicedetail['infant']>0)
                                {{ $invoicedetail['infant']." infant" }}<br />
                                @elseif($invoicedetail['TLC']>0)
                                {{ $invoicedetail['TLC']." TLC Combo Spot" }}<br />
                                @elseif($invoicedetail['Locals']>0)
                                {{ $invoicedetail['Locals']." Locals" }}<br />
                                @elseif($invoicedetail['Guides']>0)
                                {{ $invoicedetail['Guides']." Tour Guides" }}<br />
                                @elseif($invoicedetail['americans']>0)
                                {{ $invoicedetail['americans']." Native Americans" }}<br />
                                @endif
                            </td>
                            <td>{{ $invoicedetail['package'] }}</td>
                            <td>{{ date('m/d/Y', strtotime($invoicedetail['bookingdate']))." @ ".$invoicedetail['time_from'] }}</td>
                            <!-- <td>{{ date('m/d/Y', strtotime($invoicedetail['created_at'])) }}</td> -->
                            <td>{{ $invoicedetail['TouristTotal'] }}</td>
                            <td class='success-text-color fw-normal'>{{ $invoicedetail['booking_status'] }}</td>
                            @if($invoicedetail['payment_status']!=null)
                            <td class="success-text-color fw-normal">
                                {{ moneyFormat($invoicedetail['amount']) }}
                            </td>
                            @elseif($invoicedetail['payment_status']==null)
                            <td class="alert-text-color fw-normal">
                                {{ moneyFormat($invoicedetail['amount']) }}
                            </td>
                            @elseif($invoicedetail['status']=='Cancelled')
                            <td class="info-text-color fw-normal">
                                {{ moneyFormat($invoicedetail['amount']) }}
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>

					<tfoot>
						<tr>
							<td colspan="100%" style="text-align: center;"> 
								@if(!empty($invoice->external_payment_link))
								<a  href="{{$invoice->external_payment_link}}"
									style="padding:10px;text-decoration:none;font-weight:bolder;background-color: green;border-radius: 5px;color: white;margin: 4px;"
									target="_blank">Pay Online</a>
								@endif
							</td>
						</tr>
					</tfoot>

                </table>
            </div>
        </div>
    </div>
</section>
@endsection

@section('css-custom')
<link href="{{ asset('css/user-common/invoice.css') }}" rel="stylesheet">
<style>
    .header-section {
        display: none;
    }

    /* Custom Styles */
</style>
@endsection