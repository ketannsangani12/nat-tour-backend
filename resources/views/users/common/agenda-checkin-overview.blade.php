<section class="py-6 bg-white border-b border-[#E5E7EB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full md:w-2/3 lg:w-3/4 min-[1351px]:w-[79%] max-w-full text-center px-3">
				<div class="flex flex-wrap -mx-3">
					<div class="w-full lg:w-2/5 max-w-full px-3 max-[1023px]:mb-2">
						<div class="date-select relative">
							<input type="text" id="agenda-checkin-overview-date" name="date" class="relative w-full px-5 py-2 text-lg max-[1350px]:text-base font-medium text-[#111827] shadow-none leading-none outline-none text-center">
							<button type="button" class="prev-day-o datepicker-btn absolute top-[calc(50%-10px)] w-5 h-5 text-center left-0" data-incr-decr="-1"><svg class="mx-auto" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M6.5 11L1.5 6L6.5 1" stroke="#9CA3AF" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
							<button type="button" class="next-day-o datepicker-btn absolute top-[calc(50%-10px)] w-5 h-5 text-center right-0" data-incr-decr="1"><svg class="mx-auto" width="8" height="12" viewBox="0 0 8 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 11L6.5 6L1.5 1" stroke="#9CA3AF" stroke-width="1.66667" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
						</div>
					</div>
					<div class="w-full sm:w-1/3 lg:w-1/5 max-w-full px-3 max-[639px]:mb-2">
						@if ($agendaView == 1)
							@if ($packageHasSlots)
								<div class="date-select-box">
									<select id="time-slots-overview" class="select-tour-time form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto">
										<option value="">Show All Time</option>
									</select>
								</div>
							@endif
						@else
							<div class="date-select-box">
								<select id="booking-hotels-overview" class="select-tour-time form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto">
									<option value="">Show All Hotels</option>
								</select>
							</div>
						@endif
					</div>
					<div class="w-full sm:w-1/3 lg:w-1/5 max-w-full px-3 max-[639px]:mb-2">
						@if(isset($tourPackages))
							<div class="date-select-box">
								<select name="tour_package" id="tour_package_overview" class="form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto">
									<option value="0">Show All Package</option>
									@foreach($tourPackages as $tp)
										<option value="{{ $tp->id }}" @if($tp->id == $tourPackagesId) selected @endif >{{ $tp->name }}</option>
									@endforeach
								</select>
							</div>
						@endif
					</div>
					<div class="w-full sm:w-1/3 lg:w-1/5 max-w-full px-3">
						@if(isset($paymentStatus))
							<div>
								<select name="payment_status" id="payment_status_overview" class="form-select border border-black rounded-md p-[10px] pr-[15px] pl-[7px] bg-[#FAFAFA] text-[14px] font-semibold text-black w-full h-auto">
									<option selected value="" {{($paymentStatus === 0) ? 'selected' : ''}}>Select Payment Status</option>
									<option value="2" {{($paymentStatus === "Completed") ? 'selected' : ''}}>Paid</option>
									<option value="3" {{($paymentStatus === "Failed") ? 'selected' : ''}}>Unpaid</option>
								</select>
							</div>
						@endif
					</div>
				</div>
			</div>
			<div class="w-full md:w-1/3 lg:w-1/4 min-[1351px]:w-[21%] max-w-full text-center px-3" id="checkinDropdown">
				<div class="flex gap-2 items-center">
					<div class="relative flex-1" id="checkinDropdown">
						<button type="button" id="checkinToggle"
							class="w-full h-auto bg-[#FAFAFA] border border-black rounded-md 
								p-[8px] pr-[15px] pl-[7px]
								text-[14px] font-semibold text-black
								flex items-center justify-between">
							Check-in status
							<svg width="12" height="12" viewBox="0 0 24 24">
								<path d="M7 10l5 5 5-5z"></path>
							</svg>
						</button>

						<div class="absolute z-50 mt-1 bg-white border rounded shadow-md hidden w-56" id="checkinMenu">
							@php
								$checkins = [
									'N' => 'Not yet checked in',
									'1' => '1st Checked In',
									'2' => '2nd Checked In',
									'A' => 'Absent',
								];
								$selected = request('checkinStatus', []);
							@endphp

							@foreach($checkins as $key => $label)
								<label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 cursor-pointer">
									<input type="checkbox"
										class="checkin-checkbox"
										value="{{ $key }}"
										{{ in_array($key, $selected) ? 'checked' : '' }}>
									{{ $label }}
								</label>
							@endforeach
						</div>
					</div>
					@if(count($dayBookings) > 0)
						@can('agenda')
							<button class="btn btn-main btn-main-sm btn-theme short-btn flex flex-col items-center justify-center py-3 px-3 border border-solid bg-[#60BEBD] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] text-white rounded-lg shadow-none text-center transition-all" id="backToAgenda">
								<svg class="inline-block max-[1350px]:max-w-3 max-[1350px]:me-1" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316] " d="M8 4.66406V13.9974" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path class="group-hover:!stroke-[#F97316] " d="M2.0026 12C1.82579 12 1.65622 11.9298 1.5312 11.8047C1.40618 11.6797 1.33594 11.5101 1.33594 11.3333V2.66667C1.33594 2.48986 1.40618 2.32029 1.5312 2.19526C1.65622 2.07024 1.82579 2 2.0026 2H5.33594C6.04318 2 6.72146 2.28095 7.22156 2.78105C7.72165 3.28115 8.0026 3.95942 8.0026 4.66667C8.0026 3.95942 8.28356 3.28115 8.78365 2.78105C9.28375 2.28095 9.96203 2 10.6693 2H14.0026C14.1794 2 14.349 2.07024 14.474 2.19526C14.599 2.32029 14.6693 2.48986 14.6693 2.66667V11.3333C14.6693 11.5101 14.599 11.6797 14.474 11.8047C14.349 11.9298 14.1794 12 14.0026 12H10.0026C9.47217 12 8.96346 12.2107 8.58839 12.5858C8.21332 12.9609 8.0026 13.4696 8.0026 14C8.0026 13.4696 7.79189 12.9609 7.41682 12.5858C7.04175 12.2107 6.53304 12 6.0026 12H2.0026Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
							</button>
						@endcan
					@endif
				</div>
			</div>
		</div>
	</div>
</section>

<section class="min-h-[calc(100vh-295px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full text-center px-3">
				@php
					$labels = [
						'N' => 'Not Checked In',
						'1' => '1st Checked In',
						'2' => '2nd Checked In',
						'A' => 'Absent',
						'M' => 'Mixed Check-Ins',
					];

					$groupedBookings = ['N' => [], '1' => [], '2' => [], 'A' => [], 'M' => []];
					$mixedTotals     = ['N' => 0, '1' => 0, '2' => 0, 'A' => 0];
					$masterRateTotals   = [];
					$masterStatusTotals = ['N' => 0, '1' => 0, '2' => 0, 'A' => 0];
					$masterPaidTotal = 0;
					$masterUnpaidTotal = 0;

					foreach ($dayBookings as $booking) {

						// ---------- Grouping ----------
						$checkCounts = $booking->checkInsCount ?? [];
						$activeStatuses = collect($checkCounts)->filter(fn($v) => $v > 0)->keys()->values();

						if ($activeStatuses->isEmpty()) {
							$groupedBookings['N'][] = $booking;
						} elseif ($activeStatuses->count() === 1) {
							$groupedBookings[$activeStatuses[0]][] = $booking;
						} else {
							$groupedBookings['M'][] = $booking;
						}

						// ---------- Master Rate Totals ----------
						foreach (($booking->groupRates ?? []) as $label => $count) {
							$masterRateTotals[$label] = ($masterRateTotals[$label] ?? 0) + $count;
						}

						// ---------- Master Status Totals ----------
						$totalPax = ($booking->tourists ?? 0) + ($booking->infants ?? 0);

						$first  = $checkCounts['1'] ?? 0;
						$second = $checkCounts['2'] ?? 0;
						$absent = $checkCounts['A'] ?? 0;
						$notChecked = max(0, $totalPax - ($first + $second + $absent));

						$masterStatusTotals['1'] += $first;
						$masterStatusTotals['2'] += $second;
						$masterStatusTotals['A'] += $absent;
						$masterStatusTotals['N'] += $notChecked;

						// ---------- Paid / Unpaid Totals ----------
						$amount = (float) $booking->booking_total;
						$isPaid = in_array($booking->via, array_merge(['Card','Cash','Invoiced'], $otaPlatforms))
								&& $booking->booking_total <= $booking->paids;

						if ($isPaid) {
							$masterPaidTotal += $amount;
						} else {
							$masterUnpaidTotal += $amount;
						}
					}

					// ---------- Mixed Status Totals ----------
					foreach ($groupedBookings['M'] as $b) {
						foreach (($b->checkInsCount ?? []) as $k => $v) {
							if (isset($mixedTotals[$k])) {
								$mixedTotals[$k] += $v;
							}
						}
					}
				@endphp

				{{-- MASTER SORT HEADER --}}
				<table id="master-sort-table" class="tabtable table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left agenda-checkin-overview-table">
					<thead>
						<tr class="align-middle">
							<th class="w-[120px] th-custom-css">Time Slot</th>
							<th class="w-[120px] th-custom-css">Booking #</th>
							<th class="w-[120px] th-custom-css">
								@foreach($masterRateTotals as $label => $count)
									<div class="text-sm leading-tight">
										{{ $count }} {{ \Illuminate\Support\Str::plural($label, $count) }}
									</div>
								@endforeach
							</th>
							<th class="w-[120px] th-custom-css">Pax</th>
							<th class="w-[120px] th-custom-css">
								<div class="text-sm leading-tight">
									<div>{{ $masterStatusTotals['N'] }} Not Checked In</div>
									<div>{{ $masterStatusTotals['1'] }} 1st Checked In</div>
									<div>{{ $masterStatusTotals['2'] }} 2nd Checked In</div>
									<div>{{ $masterStatusTotals['A'] }} Absent</div>
								</div>
							</th>
							<th class="w-[120px] th-custom-css">
								Total
								<br>
								<span class="text-[#2ea135]">
									{{ currencyFormation(false, true) }}{{ number_format($masterPaidTotal, 2) }}
								</span>
								<br>
								<span class="text-[#ff0000]">
									{{ currencyFormation(false, true) }}{{ number_format($masterUnpaidTotal, 2) }}
								</span>
							</th>
							<th class="w-[120px] th-custom-css">Affiliate</th>
						</tr>
					</thead>
				</table>

				@foreach(['N','1','2','A','M'] as $statusKey)
					@if(count($groupedBookings[$statusKey]) || in_array($statusKey, request('checkinStatus', [])))
						@php
							$paxTotals = [];

							foreach ($groupedBookings[$statusKey] as $booking) {
								foreach(($booking->groupRates ?? []) as $label => $count) {
									if (! isset($paxTotals[$label])) {
										$paxTotals[$label] = 0;
									}
									$paxTotals[$label] += $count;
								}
							}

							$totalPax = collect($groupedBookings[$statusKey])
								->sum(fn($b) => ($b->tourists + $b->infants));
						@endphp
						<div class="mt-5 max-[1350px]:mt-4">
							{{-- removed classes from below: datatable datatable-filter --}}
							<h4 class="toggle-checkin flex items-center justify-between font-bold text-xl mb-3 text-left cursor-pointer select-none" data-target="#checkin_table_{{ $statusKey }}">
								<span>
									Check-in status: {{ $labels[$statusKey] }} ({{ count($groupedBookings[$statusKey]) }})
								</span>
								<svg class="collapse-icon w-4 h-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none">
									<path d="M7 10l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</h4>
							<div id="checkin_table_{{ $statusKey }}" class="checkin-collapse">
								<table class="table table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left agenda-checkin-overview-table">
									<thead>
										<tr class="align-middle">
											<th class="w-[120px] th-custom-css">Time Slot</th>
											<th class="w-[120px] th-custom-css">Booking #</th>
											<th class="w-[120px] th-custom-css">
												@foreach($paxTotals as $label => $count)
													<div class="font-semibold">
														{{ $count }}
														{{ \Illuminate\Support\Str::plural($label, $count) }}
													</div>
												@endforeach
											</th>
											<th class="w-[120px] th-custom-css">Pax</th>
											@php
												$hasCheckIn = collect($groupedBookings[$statusKey])->contains(fn($b) => $b->is_check_in == 1);
												$isRedeemType = collect($groupedBookings[$statusKey])->contains(fn($b) => $b->checkin_type == 1);
											@endphp
											@if ($hasCheckIn || $statusKey === 'M')
												@if ($isRedeemType)
													<th class="w-[180px] th-custom-css"><span id="check-in-count-{{ $slotId }}">0</span> Tickets Redeemed</th>
												@else
													@if ($statusKey === 'M')
														<th class="w-[220px] th-custom-css">
															<div class="text-sm">
																{{ $mixedTotals['1'] }} 1st Checked In<br>
																{{ $mixedTotals['2'] }} 2nd Checked In<br>
																{{ $mixedTotals['N'] }} Not Checked In<br>
																{{ $mixedTotals['A'] }} Absent
															</div>
														</th>
													@else
														<th class="w-[180px] th-custom-css">
															{{ $totalPax }} {{ $labels[$statusKey] }}
														</th>
													@endif
												@endif
											@endif
											<th class="w-[120px] th-custom-css">
												Total
												<br>
												<span class="paid-total text-[#2ea135]">$0.00</span>
												<br>
												<span class="unpaid-total text-[#ff0000]">$0.00</span>
											</th>
											<th class="w-[120px] th-custom-css">Affiliate</th>
										</tr>
									</thead>
									<tbody>
										@foreach($groupedBookings[$statusKey] as $booking)
											@php
												$agendaViewId = $agendaView == 1 ? 'tour_slot_id' : 'hotel_id';
												$slotId = $booking->$agendaViewId;
											@endphp
											<tr class="align-top <?php echo $booking->background_colour_class ?>"
												data-time="{{ $booking->time_from }}-{{ $booking->time_to }}"
												data-slot-id="{{ $slotId }}">
												<td class="px-3 py-2 border" data-order="{{ \Carbon\Carbon::parse($booking->time_from)->format('H:i') }}">
													{{ \Carbon\Carbon::parse($booking->time_from)->format('h:i A') }}
												</td>
												<td class="px-3 py-2 border"><a class="font-medium text-blue-600 text-sm whitespace-nowrap" href="{{ route(prefixedRouteName($routePrefix.'booking.show'), [ 'booking' => $booking->booking_id ]) }}" target="_blank">{{ bookingPrefix($booking->booking_id) }}</a></td>
												<td class="px-3 py-2 border">
													@php
														foreach ($booking->touristsAgeInfo as $ageInfo) {
															echo "<span class='font-medium text-black text-sm block whitespace-nowrap'>$ageInfo</span>";
														}
													@endphp
												</td>
												<td class="px-3 py-2 border">
													{{ $booking->tourists + $booking->infants }}
												</td>
												@if ($booking->is_check_in == 1)
												<td class="px-5 py-2 border" data-order="{{$booking->tourists}}">
													@if($booking->checkin_type == 1)
														
													@else
														@if($statusKey === 'N')
															Not Checked In
														@elseif($statusKey === 'A')
															All Absent
														@else
															@if ($statusKey === 'M')
																@php $c = $booking->checkInsCount ?? []; @endphp

																@if(($c['N'] ?? 0) > 0)
																	<div>{{ $c['N'] }} Not Checked In</div>
																@endif

																@if(($c['1'] ?? 0) > 0)
																	<div>{{ $c['1'] }} 1st Checked In</div>
																@endif

																@if(($c['2'] ?? 0) > 0)
																	<div>{{ $c['2'] }} 2nd Checked In</div>
																@endif

																@if(($c['A'] ?? 0) > 0)
																	<div>{{ $c['A'] }} Absent</div>
																@endif
															@else
																All {{ $labels[$statusKey] }}
															@endif
														@endif
													@endif
												</td>
												@endif
												<td class="px-3 py-2 border total-amount"
													data-paid="{{ (in_array($booking->via, array_merge(['Card','Cash','Invoiced'], $otaPlatforms)) && $booking->booking_total <= $booking->paids) ? 1 : 0 }}">
													@if (in_array($booking->via, array_merge(['Card','Cash','Invoiced'], $otaPlatforms)) && $booking->booking_total <= $booking->paids)
														<span class="text-[#2ea135]">{{ currencyFormation(false, true) }}{{ $booking->booking_total }}</span>
													@else
														<span class="text-[#ff0000]">{{ currencyFormation(false, true) }}{{ $booking->booking_total }}</span>
													@endif
												</td>
												<td class="px-3 py-2 border">
													{{ ($booking->is_affiliate == 'Yes' ? $booking->affiliate_name : '') }}</td>
											</tr>
										@endforeach
									</tbody>
								</table>
							</div>
						</div> {{-- /.tour-detail-table-wrap --}}
					@endif
				@endforeach
			</div>
		</div>
	</div>
</section>