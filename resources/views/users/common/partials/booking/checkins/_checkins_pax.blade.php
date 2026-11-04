<td class="p-2">
    @php
    $oldCheckInsValues = array();
    @endphp
    @if($requestPermissions['Check-ins'] == "Yes")
        @if (!empty($checKInsTourOperator))
            <select class="form-select pl-3 pr-5 py-2 text-xs text-[#020817] rounded-lg sel-check-in check-in-status-{{ $bt->check_in_status[$a]->status }}" data-tpr-id="{{ $bt->tour_package_rate_id }}" onchange="markCheckIn(this, {{ $a }})" {{ ($bookingM->status == 'Cancelled' || session('userType') == 'Affiliate') ? 'disabled' : '' }}>
                @foreach($checKInsTourOperator as $checKInTourOperator)
                    @php
                        $checkInStatus = (isset($checkIns[$checKInTourOperator]) && $checkIns[$checKInTourOperator]!='')?$checkIns[$checKInTourOperator]:'';
                    @endphp
                    @if (isset($bt->check_in_status[$a]->status) && $bt->check_in_status[$a]->status!='' && !in_array($bt->check_in_status[$a]->status,$checKInsTourOperator))
                        @php
                            $oldCheckInsValues[] = (isset($checkIns[$bt->check_in_status[$a]->status]) && $checkIns[$bt->check_in_status[$a]->status]!='')?$bt->check_in_status[$a]->status:'';
                        @endphp
                    @endif
                    @if ($checkInStatus!='')
                        <option class="check-in-status-{{$checKInTourOperator}}" value="{{$checKInTourOperator}}" {{ $bt->check_in_status[$a]->status == $checKInTourOperator ? 'selected' : '' }}>{{ $checkIns[$checKInTourOperator] }}</option>
                    @endif
                @endforeach
                @if (!empty($oldCheckInsValues))
                    @php
                        $oldCheckInsValues = array_unique($oldCheckInsValues);
                    @endphp
                    @foreach($oldCheckInsValues as $oldCheckInsValue)
                        @php
                            $checkInStatusForOld = (isset($checkIns[$bt->check_in_status[$a]->status]) && $checkIns[$bt->check_in_status[$a]->status]!='')?$checkIns[$bt->check_in_status[$a]->status]:'';
                        @endphp
                        <option class="check-in-status-{{$oldCheckInsValue}}" value="{{$oldCheckInsValue}}" selected>{{ $checkInStatusForOld }}</option>
                    @endforeach
                @endif
            </select>
        @else
            <select class="form-select pl-3 py-2 pr-5 text-xs text-[#020817] rounded-lg sel-check-in check-in-status-{{ $bt->check_in_status[$a]->status }}" data-tpr-id="{{ $bt->tour_package_rate_id }}" onchange="markCheckIn(this, {{ $a }})" {{ $bookingM->status == 'Cancelled' ? 'disabled' : '' }}>
                <option class="check-in-status-N" value="N" {{ $bt->check_in_status[$a]->status == 'N' ? 'selected' : '' }}>Not Checked In</option>
                <option class="check-in-status-1" value="1" {{ $bt->check_in_status[$a]->status == '1' ? 'selected' : '' }}>1st Check In</option>
                <option class="check-in-status-2" value="2" {{ $bt->check_in_status[$a]->status == '2' ? 'selected' : '' }}>2nd Check In</option>
                <option class="check-in-status-A" value="A" {{ $bt->check_in_status[$a]->status == 'A' ? 'selected' : '' }}>Absent</option>
            </select>
        @endif
    @endif    
</td>