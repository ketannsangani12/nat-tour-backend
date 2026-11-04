@if($requestPermissions['Check-ins'] == "Yes")
<div class="btn-group relative inline-block align-middle">
    <button type="button" class="customDropDownButton themed-button dropdown-toggle text-xs font-medium text-white hover:text-[#ED9C45] border border-[#ED9C45] bg-[#ED9C45] hover:bg-white px-3 py-[10px] ml-1 mb-1 rounded-lg inline-block">Choose Check-In Status For All <svg class="inline-block ml-2" width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg"><path opacity="0.5" d="M0.666748 0.666504L4.66675 4.6665L8.66675 0.666504" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
    <ul class="dropdown-menu customDropDown ul-check-in absolute right-0 w-full bg-white shadow-lg z-50 rounded border text-sm text-left hidden">
        @if (!empty($checKInsTourOperator))
            @foreach($checKInsTourOperator as $checKInTourOperator)
                @php
                    $checkInStatus = (isset($checkIns[$checKInTourOperator]) && $checkIns[$checKInTourOperator]!='')?$checkIns[$checKInTourOperator]:'';
                    switch ($checKInTourOperator) {
                        case 1:
                            $bgColor = 'bg-[#19875450]';
                            break;
                        case 2:
                            $bgColor = 'bg-[#0dcaf040]';
                            break;
                        case 'N':
                            $bgColor = 'bg-[#ffc10740]';
                            break;
                        default:
                            $bgColor = 'bg-[#dc354540]';
                            break;
                    }
                @endphp
                @if ($checkInStatus!='')
                    @if (is_numeric($checKInTourOperator))
                        <li class="text-sm text-[#020817] px-3 py-1 {{ $bgColor }} check-in-status-{{$checKInTourOperator}}" onclick="markCheckIn({{$checKInTourOperator}})" tabindex="0">
                            {{ $checkIns[$checKInTourOperator] }}
                        </li>
                    @else
                        <li class="text-sm text-[#020817] px-3 py-1 {{ $bgColor }} check-in-status-{{$checKInTourOperator}}" onclick="markCheckIn('{{$checKInTourOperator}}')" tabindex="0">
                            {{ $checkIns[$checKInTourOperator] }}
                        </li>
                    @endif
                @endif
            @endforeach
        @else
            <li class="check-in-status-N text-sm text-[#020817] px-3 py-1 bg-[#ffc10740]" onclick="markCheckIn('N')" tabindex="0">Not Checked In</li>
            <li class="check-in-status-1 text-sm text-[#020817] px-3 py-1 bg-[#19875450]" onclick="markCheckIn(1)" tabindex="0">1st Check In</li>
            <li class="check-in-status-2 text-sm text-[#020817] px-3 py-1 bg-[#0dcaf040]" onclick="markCheckIn(2)" tabindex="0">2nd Check In</li>
            <li class="check-in-status-A text-sm text-[#020817] px-3 py-1 bg-[#dc354540]" onclick="markCheckIn('A')" tabindex="0">Absent</li>
        @endif
    </ul>
</div>
@endif