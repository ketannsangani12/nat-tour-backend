
<td class="redeem-ticket-section" id="redeemSection">
	@if($requestPermissions['Ticket Redeem'] == "Yes")
	    @if(count($packageList) > 0)
	        @foreach($packageList as $key => $package)
	            <button class="py-1.5 px-5 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#047857] border rounded-full  bundle_package_{{ $package->tour_package_id }} @if($bt->redeem_ticket[$a][$key]['status'] == 'Active'){{ "redeem-ticket-success" }}@else{{ "redeem-ticket-danger" }}@endif" id="bundle_package_{{ $a }}_{{ $package->tour_package_id }}_{{ $bt->tour_package_rate_id }}" data-status="{{ $bt->redeem_ticket[$a][$key]['status'] }}" data-pax-id="{{ $a }}" data-tpr-id="{{ $bt->tour_package_rate_id }}" title="{{ $package->package_name }}" onclick="updateCheckInAndRedeemTicketStatus({{ $package->tour_package_id }},{{ $bt->tour_package_rate_id }},'index',{{ $bt->redeem_ticket[$a][$key]['ageGroupIndex'] }},1)">{{ $package->package_name }}</button>
	        @endforeach
	    @endif
	@endif    
</td>

