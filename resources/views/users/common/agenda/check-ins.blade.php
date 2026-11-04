@php
    if (isset($dayBookings[$i]->bookingCheckIn) && in_array($dayBookings[$i]->bookingCheckIn, [ 'All 1st Check In', 'All 2nd Check In', 'All Absent' ])) {
        echo $bookingCheckIn;
    } else {
        $bookingCheckIns = [];

        if ($dayBookings[$i]->notCheckInsTotal) {
            $notCheckInStatus = "N";
            $bookingCheckIns[] = '<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-orange-50 rounded-[100px] border border-[#a32624] font-normal inline-block" id="allcheckedin_'.$dayBookings[$i]->booking_id.'_4" onclick="showCheckInOptions('.$dayBookings[$i]->booking_id.',4, event);" data-tourists="'.$dayBookings[$i]->notCheckInsTotal.'"  style="border: 1px solid #ffc107; background-color: #ffc10720"><span class="font-semibold text-[#a32624]">'. $dayBookings[$i]->notCheckInsTotal . '</span> <span class="text-black">Not Checked In</span>' . '</div>';
            
            if (!empty($tourOperatorCheckInStatues)) {
                $strNotCheckIns = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_'.$dayBookings[$i]->booking_id.'_4" style="min-width:9rem;">';
                foreach ($tourOperatorCheckInStatues as $key=>$tourOperatorCheckInStatus) {
                    if ($tourOperatorCheckInStatus != $notCheckInStatus) {
                        $checkInStatusName = (isset($checkInStatues[$tourOperatorCheckInStatus]) && $checkInStatues[$tourOperatorCheckInStatus] != '') ? $checkInStatues[$tourOperatorCheckInStatus] : '';

                        if ($tourOperatorCheckInStatus == 'A') {
                            $strNotCheckIns .= '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-'.$tourOperatorCheckInStatus.'" onclick="markCheckIn(3,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->notCheckInsTotal.','.$dayBookings[$i]->allNotCheckedIn.',4);" tabindex="0">'.$checkInStatusName.'</li>';
                        } else {
                            switch ($tourOperatorCheckInStatus) {
                                case 1:
                                    $bgColor = 'bg-[#19875430]';
                                    break;
                                case 2:
                                    $bgColor = 'bg-[#0dcaf020]';
                                    break;
                                case 'N':
                                    $bgColor = 'bg-[#ffc10720]';
                                    break;
                                default:
                                    $bgColor = 'bg-[#cff4fc]';
                                    break;
                            }

                            // Append the HTML string
                            $strNotCheckIns .= '<li class="px-4 py-1 cursor-pointer ' . $bgColor . ' check-in-status-' . $tourOperatorCheckInStatus . '" onclick="markCheckIn(' . $tourOperatorCheckInStatus . ',' . $dayBookings[$i]->booking_id . ',' . $dayBookings[$i]->notCheckInsTotal . ',' . $dayBookings[$i]->allNotCheckedIn . ',4);" tabindex="0">' . $checkInStatusName . '</li>';

                            // $strNotCheckIns .= '<li class="px-4 py-1 cursor-pointer bg-[] check-in-status-'.$tourOperatorCheckInStatus.'" onclick="markCheckIn('.$tourOperatorCheckInStatus.','.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->notCheckInsTotal.','.$dayBookings[$i]->allNotCheckedIn.',4);" tabindex="0">'.$checkInStatusName.'</li>';
                        }
                    }
                }
                $strNotCheckIns .= '</ul>';
                $bookingCheckIns[] = $strNotCheckIns;
            } else {
                $bookingCheckIns[] = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_'.$dayBookings[$i]->booking_id.'_4" style="min-width:9rem;">
                    <li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->notCheckInsTotal.','.$dayBookings[$i]->allNotCheckedIn.',4);" tabindex="0">1st Check In</li>
                    <li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->notCheckInsTotal.','.$dayBookings[$i]->allNotCheckedIn.',4);" tabindex="0">2nd Check In</li>
                    <li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->notCheckInsTotal.','.$dayBookings[$i]->allNotCheckedIn.',4);" tabindex="0">Absent</li>
                </ul>';
            }
        }

        if (isset($dayBookings[$i]->checkInsCount['1']) && $dayBookings[$i]->checkInsCount['1']) {
            $bookingCheckIns[] = '<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-[#19875430] rounded-[100px] border border-[#198754] font-normal inline-block" id="allcheckedin_'.$dayBookings[$i]->booking_id.'_1" onclick="showCheckInOptions('.$dayBookings[$i]->booking_id.',1, event);" data-tourists="'.$dayBookings[$i]->checkInsCount['1'].'"  style="border: 1px solid #198754; background-color: #19875430"><span class="font-semibold text-[#0f5132]">'. $dayBookings[$i]->checkInsCount['1'] . '</span> <span class="text-black">1st Check In</span>' . '</div>';

            if (!empty($tourOperatorCheckInStatues)) {
                $strFirstCheckIns = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_'.$dayBookings[$i]->booking_id.'_1" style="min-width:9rem;">';

                foreach ($tourOperatorCheckInStatues as $key => $tourOperatorCheckInStatus){
                    if ($tourOperatorCheckInStatus != 1) {
                        $checkInStatusName = (isset($checkInStatues[$tourOperatorCheckInStatus]) && $checkInStatues[$tourOperatorCheckInStatus] != '') ? $checkInStatues[$tourOperatorCheckInStatus] : '';
                        if ($tourOperatorCheckInStatus == 'N') {
                            $strFirstCheckIns .= '<li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->checkInsCount['1'].','.$dayBookings[$i]->allFirstCheckedIn.',1);" tabindex="0">'.$checkInStatusName.'</li>';
                        } else if($tourOperatorCheckInStatus == 'A') {
                            $strFirstCheckIns .= '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->checkInsCount['1'].','.$dayBookings[$i]->allFirstCheckedIn.',1);" tabindex="0">'.$checkInStatusName.'</li>';
                        } else {
                            switch ($tourOperatorCheckInStatus) {
                                case 1:
                                    $bgColor = 'bg-[#19875430]';
                                    break;
                                case 2:
                                    $bgColor = 'bg-[#0dcaf020]';
                                    break;
                                case 'N':
                                    $bgColor = 'bg-[#ffc10720]';
                                    break;
                                default:
                                    $bgColor = 'bg-[#cff4fc]';
                                    break;
                            }
                            $strFirstCheckIns .= '<li class="px-4 py-1 cursor-pointer ' . $bgColor . ' check-in-status-'.$tourOperatorCheckInStatus.'" onclick="markCheckIn('.$tourOperatorCheckInStatus.','.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->checkInsCount['1'].','.$dayBookings[$i]->allFirstCheckedIn.',1);" tabindex="0">'.$checkInStatusName.'</li>';
                        }
                    }
                }
                $strFirstCheckIns .= '</ul>';
                $bookingCheckIns[] = $strFirstCheckIns;
            } else {
                $bookingCheckIns[] = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_'.$dayBookings[$i]->booking_id.'_1" style="min-width:9rem;">
                    <li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->checkInsCount['1'].','.$dayBookings[$i]->allFirstCheckedIn.',1);" tabindex="0">Not Checked In</li>
                    <li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->checkInsCount['1'].','.$dayBookings[$i]->allFirstCheckedIn.',1);" tabindex="0">2nd Check In</li>
                    <li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,'.$dayBookings[$i]->booking_id.','.$dayBookings[$i]->checkInsCount['1'].','.$dayBookings[$i]->allFirstCheckedIn.',1);" tabindex="0">Absent</li>
                </ul>';
            }
        }
        
        if (isset($dayBookings[$i]->checkInsCount['2']) && $dayBookings[$i]->checkInsCount['2']) {
            $bookingCheckIns[] =  '<div class="checkins px-[13px] py-[5px] mb-2 mt-2 text-sm whitespace-nowrap bg-[#0dcaf020] rounded-[100px] border border-[#b6effb] font-normal inline-block" id="allcheckedin_'.$dayBookings[$i]->booking_id.'_2" data-tourists="'.$dayBookings[$i]->checkInsCount['2'].'" style="border: 1px solid #b6effb; background-color: #0dcaf020"><span class="font-semibold text-[#055160]">'. $dayBookings[$i]->checkInsCount['2'] . '</span> <span class="text-black">2nd Check In</span>' . '</div>';
        }

        if (isset($dayBookings[$i]->checkInsCount['A']) && $dayBookings[$i]->checkInsCount['A']) {
            $bookingCheckIns[] = '<div class="checkins px-[13px] py-[5px] mb-2 text-sm whitespace-nowrap bg-[#dc354520] rounded-[100px] border border-[#f5c2c7] font-normal inline-block" id="allcheckedin_'.$dayBookings[$i]->booking_id.'_3" data-tourists="'.$dayBookings[$i]->checkInsCount['A'].'" style="border: 1px solid #f5c2c7; background-color: #dc354520"><span class="font-semibold text-[#842029]">'. $dayBookings[$i]->checkInsCount['A'] . '</span> <span class="text-black">Absent</span>' . '</div>';
        }

        echo implode('', $bookingCheckIns);
    }
@endphp