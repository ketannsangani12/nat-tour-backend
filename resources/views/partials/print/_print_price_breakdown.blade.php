@foreach($transaction as $bt)
@if (isset($bt->tourists) && ($bt->tourists > 0 || $bt->infants > 0))
<table  class="table table-sm table-bordered table-hover mt-3 mb-0 tbl--price" style="{{ $style }}">
	<tbody>
		<tr>
			<td>{{$bt->infants > 0 ? $bt->infants : $bt->tourists}} : {{ ($booking->is_group_booking == '1') ? 'Persons' : $bt->rate_for }} </td>
		</tr>
		@if($booking->is_group_booking != '1' && ($booking->is_affiliate == 'No' ||  ($booking->is_affiliate == 'Yes' && $booking->is_price_show == 'Yes')))
		<tr>
			<td>Price </td>
			<td class="td-amount">{{ moneyFormat($bt->rate) }}</td>
		</tr>
		@if($booking->package_has_permit)
		<tr>
			<td>Permit Fee</td>
			<td class="td-amount">{{ moneyFormat($bt->permit_fee) }}</td>
		</tr>
		@endif
		@if($booking->is_partner_booking == 1 && $bt->partner_fees_amount > 0)
		<tr>
			<td>Partner Fee</td>
			<td class="td-amount">{{ moneyFormat($bt->partner_fees_amount) }}</td>
		</tr>
		@endif
		<tr>
			<td>{{ $formLabels["tax"] ?? taxLabel() }}</td>
			<td class="td-amount">{{ moneyFormat($bt->additional_charge) }}</td>
		</tr>
			<!-- @if($bt->processing_charge_percentage != 0)
				<tr>
					<td>Processing Fee</td>
					<td class="td-amount">{{ $bt->processing_charge_percentage }}%</td>
				</tr>
			@endif -->
		@endif
	</tbody>
</table>
@endif
@endforeach
