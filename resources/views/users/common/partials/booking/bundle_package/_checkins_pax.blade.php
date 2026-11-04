<!-- Check-ins For Bundle Package -->
@if(count($bundlePackageCheckInsList['checkins']) > 0)
<td class="redeem-ticket-section py-2 px-4">
        @if(count($packageList) > 0)
            @foreach($packageList as $key => $package)
                @foreach($bundlePackageCheckInsList['checkins'] as $checkins)
                    @if($checkins->tour_package_id == $package->tour_package_id)
                        @if($checkins->checkin_type == 0)
                            @if(count($bundlePackageCheckInsList['checkins']) > 1)
                                @if(in_array($bt->redeem_ticket[$a][$key]['status'], $checKInsTourOperator))
                                    <label for="package_name" class="col-form-label form-custom-label pt-1 bundle-package-check-in">{{ $package->package_name }}</label>
                                @endif
                            @endif
                            
                            @if (!empty($checKInsTourOperator))
                                @if($requestPermissions['Check-ins'] == "Yes")
                                    <select class="bundle-check-in form-select block pl-3 pr-5 py-2 text-xs text-[#020817] rounded-lg sel-check-in check-in-status-{{ $bt->redeem_ticket[$a][$key]['status'] }} bundle_package_checkin_{{ $package->tour_package_id }}" id="bundle_package_checkin_{{ $a }}_{{ $package->tour_package_id }}_{{ $bt->tour_package_rate_id }}" data-tpr-id="{{ $bt->tour_package_rate_id }}" onchange="updateCheckInAndRedeemTicketStatus({{ $package->tour_package_id }},{{ $bt->tour_package_rate_id }},'index',{{ $bt->redeem_ticket[$a][$key]['ageGroupIndex'] }},0)" {{ ($bookingM->status == 'Cancelled' || session('userType') == 'Affiliate') ? 'disabled' : '' }}>
                                        @foreach($checKInsTourOperator as $checKInTourOperator)
                                            @php
                                                $checkInStatus = (isset($checkIns[$checKInTourOperator]) && $checkIns[$checKInTourOperator] != '') ? $checkIns[$checKInTourOperator] : '';
                                            @endphp
                                            @if (isset($bt->redeem_ticket[$a][$key]['status']) && $bt->redeem_ticket[$a][$key]['status'] != '' && !in_array($bt->redeem_ticket[$a][$key]['status'], $checKInsTourOperator))
                                                @php
                                                    $oldCheckInsValues[] = (isset($checkIns[$bt->redeem_ticket[$a][$key]['status']]) && $checkIns[$bt->redeem_ticket[$a][$key]['status']] != '') ? $bt->redeem_ticket[$a][$key]['status'] : '';
                                                @endphp
                                            @endif
                                            @if ($checkInStatus != '')
                                                <option class="check-in-status-{{$checKInTourOperator}}" value="{{$checKInTourOperator}}" {{ $bt->redeem_ticket[$a][$key]['status'] == $checKInTourOperator ? 'selected' : '' }}>{{ $checkIns[$checKInTourOperator] }}</option>
                                            @endif
                                        @endforeach
                                        @if (!empty($oldCheckInsValues))
                                            @php
                                                $oldCheckInsValues = array_unique($oldCheckInsValues);
                                            @endphp
                                            @foreach($oldCheckInsValues as $oldCheckInsValue)
                                                @php
                                                    $checkInStatusForOld = (isset($checkIns[$bt->redeem_ticket[$a][$key]['status']]) && $checkIns[$bt->redeem_ticket[$a][$key]['status']] != '') ? $checkIns[$bt->redeem_ticket[$a][$key]['status']] : '';
                                                @endphp
                                                <option class="check-in-status-{{$oldCheckInsValue}}" value="{{$oldCheckInsValue}}" selected>{{ $checkInStatusForOld }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                               @endif 
                            @else
                                @if($requestPermissions['Check-ins'] == "Yes")
                                    <select class="bundle-check-in form-select pl-3 pr-5 py-2 text-xs text-[#020817] rounded-lg sel-check-in check-in-status-{{ $bt->redeem_ticket[$a][$key]['status'] }} bundle_package_checkin_{{ $package->tour_package_id }}" id="bundle_package_checkin_{{ $a }}_{{ $package->tour_package_id }}_{{ $bt->tour_package_rate_id }}" data-tpr-id="{{ $bt->tour_package_rate_id }}" onchange="updateCheckInAndRedeemTicketStatus({{ $package->tour_package_id }},{{ $bt->tour_package_rate_id }},'index',{{ $bt->redeem_ticket[$a][$key]['ageGroupIndex'] }},0)" {{ $bookingM->status == 'Cancelled' ? 'disabled' : '' }}>
                                        <option class="check-in-status-N" value="N" {{ $bt->redeem_ticket[$a][$key]['status'] == 'N' ? 'selected' : '' }}>Not Checked In</option>
                                        <option class="check-in-status-1" value="1" {{ $bt->redeem_ticket[$a][$key]['status'] == '1' ? 'selected' : '' }}>1st Check In</option>
                                        <option class="check-in-status-2" value="2" {{ $bt->redeem_ticket[$a][$key]['status'] == '2' ? 'selected' : '' }}>2nd Check In</option>
                                        <option class="check-in-status-A" value="A" {{ $bt->redeem_ticket[$a][$key]['status'] == 'A' ? 'selected' : '' }}>Absent</option>
                                    </select>
                                @endif    
                            @endif
                        @endif
                    @endif
                @endforeach
            @endforeach
        @endif
    </td>
@endif

@if(count($bundlePackageCheckInsList['tickets']) > 0)
        <td class="redeem-ticket-section py-2 px-4">
            @if($requestPermissions['Ticket Redeem'] == "Yes")
                @if(count($packageList) > 0)
                    @foreach($packageList as $key => $package)
                        @foreach($bundlePackageCheckInsList['tickets'] as $checkins)
                            @if($checkins->tour_package_id == $package->tour_package_id)
                                @if($checkins->checkin_type == 1)
                                    <button class="py-1.5 px-5 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#047857] border rounded-full bundle_package_{{ $package->tour_package_id }} @if($bt->redeem_ticket[$a][$key]['status'] == 'Active'){{ "redeem-ticket-success" }}@else{{ "redeem-ticket-danger" }}@endif" id="bundle_package_{{ $a }}_{{ $package->tour_package_id }}_{{ $bt->tour_package_rate_id }}" data-status="{{ $bt->redeem_ticket[$a][$key]['status'] }}" data-pax-id="{{ $a }}" data-tpr-id="{{ $bt->tour_package_rate_id }}" title="{{ $package->package_name }}" onclick="updateCheckInAndRedeemTicketStatus({{ $package->tour_package_id }},{{ $bt->tour_package_rate_id }},'index',{{ $bt->redeem_ticket[$a][$key]['ageGroupIndex'] }},1)" style="width: 80%;">{{ $package->package_name }}</button>
                                @endif
                            @endif
                        @endforeach
                    @endforeach
                @endif
             @endif   
        </td>
        
@endif