

<style>

@media print {
    .pagebreak { page-break-before: always; } /* page-break-after works, as well */
}

.signature{ border: none; border-bottom: 1px solid #333; }

</style>

@php 
$logo = getLogo($bookingM);
$tourData = printTourOptCustomer($bookingM);
@endphp


<div class="row">
    <div class="col-6">
        <h3>{{ $bookingM->tour_operator }}</h3>
        <p>{!! implode('<br>', $tourData['tourOpt']) !!}</p>
    </div>
    <div class="col-6">
        @if($logo)
            <div class="text-end">
                <img src="{{ $logo }}" alt="" style="width:100; height:75px;" > <br />
                Booking ID# <strong>{{ bookingPrefix($bookingM->id) }}</strong>
            </div>
        @endif
    </div>
</div>
<hr />


<table width="100%" class="table table-sm">

    <tbody>

        <tr> 
            <td>Tour:</td> 
            <td><strong>{{ $bookingM->tour_package }}</strong></td> 
        </tr>

        @if ($bookingM->package_has_slots)
            <tr>
                <td>Tour Date/Time:</td> 
                <td><strong>{{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}</strong></td>
            </tr>
        @else
            <tr>
                <td>Tour Date:</td> 
                <td><strong>{{ formatDate($bookingM->date) }}</strong></td>
            </tr>
        @endif

        <tr>
            <td>Booking ID:</td> 
            <td><strong>{{$bookingM->id}}</strong></td>            
        </tr>
        

        <tr>
            <td width="50%"> </td>
            <td class="align-end" > 

                @include('partials.print._print_price_breakdown', ['transaction' => $bookingT,
                                                                    'booking' => $bookingM,
                                                                    'payLaterAddons' => $payLaterAddons,
                                                                    'pricedAddons' => $pricedAddons,
                                                                    'formLabels' => ["tax" => "Tax"],
                                                                    'style' => 'font-size:13px;color:#000;font-weight:bold;line-height: 1;margin: 0;' ])

                @include('partials.print._print_price_total', [ 'trans' => $bookingT,
                                                                'booking'=>$bookingM,
                                                                'payLaterAddons' => $payLaterAddons,
                                                                'pricedAddons' => $pricedAddons,
                                                                'style'=> 'font-size:13px;color:#000;font-weight:bold;line-height: 1;margin: 0;'])
            </td>
        </tr>

    </tbody>
</table>

<div class="row">
    <div class="col-12">
        <p>{{ $bookingM->customer }}</p>
        <p>{!! implode('<br>', $tourData['tourCust']) !!}</p>
        <p>Signature: <input type="text" class="signature" /></p>
    </div>
</div>

<hr />

@if( isset($tpkg) ) 
    <div class="row">
        <div class="col-12">
            <strong>Tour Cancellation Policy</strong><br />
            <small>{!! $tpkg['cancellation'] !!}</small>
        </div>
    </div>
@endif



<hr />
<div class="pagebreak"> </div>


<div class="row mt-5">
    <div class="col-6">
        <h3>{{ $bookingM->tour_operator }}</h3>
        <p>{!! implode('<br>', $tourData['tourOpt']) !!}</p>
    </div>
    <div class="col-6">
        @if($logo)
            <div class="text-end">
                <img src="{{ $logo }}" alt="" style="width:100; height:75px;" > <br />
                Booking ID# <strong>{{$bookingM->id}}</strong>
            </div>
        @endif
    </div>
</div>
<hr />


<table width="100%" class="table table-sm">

    <tbody>

        <tr> 
            <td>Tour:</td> 
            <td><strong>{{ $bookingM->tour_package }}</strong></td> 
        </tr>
        <tr>
            <td>Tour Date/Time:</td> 
            <td><strong>{{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}</strong></td>
        </tr>    
        
        <tr>
            <td>Booking ID:</td> 
            <td><strong>{{$bookingM->id}}</strong></td>            
        </tr>
        

        <tr>
            <td width="50%"> </td>
            <td class="align-end" > 

                @include('partials.print._print_price_breakdown', [ 'transaction' => $bookingT,
                                                                    'booking' => $bookingM,
                                                                    'payLaterAddons' => $payLaterAddons,
                                                                    'pricedAddons' => $pricedAddons,
                                                                    'formLabels' => ["tax" => "Tax"],
                                                                    'style' => 'font-size:13px;color:#000;font-weight:bold;line-height: 1;margin: 0;' ])

                @include('partials.print._print_price_total', [ 'trans' => $bookingT,
                                                                'booking'=> $bookingM,
                                                                'payLaterAddons' => $payLaterAddons,
                                                                'pricedAddons' => $pricedAddons,
                                                                'style' => 'font-size:13px;color:#000;font-weight:bold;line-height: 1;margin: 0;'])
            </td>
        </tr>

    </tbody>
</table>

<div class="row">
    <div class="col-12">
        <p>{{ $bookingM->customer }}</p>
        <p>{!! implode('<br>', $tourData['tourCust']) !!}</p>
    </div>
</div>

<hr />

@if( isset($tpkg) )
    <div class="row">
        <div class="col-12">
            <strong>Tour Cancellation Policy</strong><br />
            <small>{!! $tpkg['cancellation'] !!}</small>
        </div>
    </div>
@endif
