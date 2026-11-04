@if(count($bundlePackageCheckInsList['checkins']) > 0)
@if($requestPermissions['Check-ins'] == "Yes")
    <div class="btn-group relative inline-block align-middle">
        <button type="button" class="themed-button me-2 dropdown-toggle text-xs font-medium text-[#020817] border border-[#020817] px-3 py-[10px] ml-1 mb-1 rounded-lg inline-block" data-bs-toggle="dropdown" aria-expanded="false">Choose Check-In Status For All <svg class="inline-block ml-2" width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path opacity="0.5" d="M0.666748 0.666504L4.66675 4.6665L8.66675 0.666504" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <ul class="dropdown-menu ul-check-in absolute right-0 w-full bg-white shadow-lg z-50 rounded border text-sm text-left hidden" style="white-space: nowrap;">
            @foreach($bundlePackageCheckInsList['checkins'] as $package)
                <li class="redeem-ticket-{{ $package->tour_package_id }}" data-package-name="{{ $package->package_name }}" onclick="updateCheckInAndRedeemTicketStatus({{ $package->tour_package_id }},0,'all','all',0)">{{ $package->package_name }}</li>
            @endforeach
        </ul>
    </div>
@endif    
@endif
@if(count($bundlePackageCheckInsList['tickets']) > 0)
@if($requestPermissions['Ticket Redeem'] == "Yes")
    <div class="btn-group relative inline-block align-middle">
        <button type="button" class="themed-button me-2 dropdown-toggle text-xs font-medium text-[#020817] border border-[#020817] px-3 py-[10px] ml-1 mb-1 rounded-lg inline-block" data-bs-toggle="dropdown" aria-expanded="false">Redeem Tickets <svg class="inline-block ml-2" width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path opacity="0.5" d="M0.666748 0.666504L4.66675 4.6665L8.66675 0.666504" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        <ul class="dropdown-menu ul-check-in absolute right-0 w-full bg-white shadow-lg z-50 rounded border text-sm text-left hidden" style="white-space: nowrap;">
            @foreach($bundlePackageCheckInsList['tickets'] as $package)
                <li class="redeem-ticket-{{ $package->tour_package_id }}" data-package-name="{{ $package->package_name }}" onclick="updateCheckInAndRedeemTicketStatus({{ $package->tour_package_id }},0,'all','all',1)">{{ $package->package_name }}</li>
            @endforeach
        </ul>
    </div>
@endif
@endif