<table bgcolor="#fff" align="center" width="600" cellpadding="0" cellspacing="0" style="padding: 0; margin: 0 auto; border: 1px solid #ccc; border-radius: 5px; max-width: 600px;">
	<tbody>

		<tr>
			<td>
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr>
							<td style="text-align: center; padding: 20px 0;">
								<a href="#" title="Logo" target="_blank">
@if(isset($arEmailData['tourOpLogo']))
									<img src="{{ $arEmailData['tourOpLogo'] }}" style="max-width: 150px;" alt="logo">
@else
									{{ $arEmailData['tourOperator'] }}
@endif
								</a>
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>

		<tr>
			<td style="padding: 10px 0; border-top: 1px solid #ccc; border-bottom: 1px solid #ccc;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr>
@if($arEmailData['tourOpPhone'])
							<td style="text-align: center; font-size: 13px; font-weight: 600; line-height: 1; padding: 0; margin:0;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; white-space: nowrap; text-decoration: none; color: #0071c2; padding: 0 10px;" href="tel:{{ $arEmailData['tourOpPhone'] }}">{{ $arEmailData['tourOpPhone'] }}</a>
							</td>
@endif

@if($arEmailData['tourOpEmail'])
							<td style="text-align: center; font-size: 13px; font-weight: 600; line-height: 1; padding: 0; margin:0;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; white-space: nowrap; text-decoration: none; color: #0071c2; padding: 0 10px; border-left: 1px solid #ccc; border-right: 1px solid #ccc;" href="mailto:{{ $arEmailData['tourOpEmail'] }}">{{ $arEmailData['tourOpEmail'] }}</a>
							</td>
@endif

@if($arEmailData['tourOpWebsite'])
	@php
		$http = substr($arEmailData['tourOpWebsite'], 0, 7);
	@endphp
							<td style="text-align: center; font-size: 13px; font-weight: 600; line-height: 1; padding: 0; margin:0;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; white-space: nowrap; text-decoration: none; color: #0071c2; padding: 0 10px;" href="{{ ($http == 'http://' || $http == 'https:/' ? '' : 'http://') . $arEmailData['tourOpWebsite'] }}">{{ $arEmailData['tourOpWebsite'] }}</a>
							</td>
@endif
						</tr>
					</tbody>
				</table>
			</td>
		</tr>

		<tr>
			<td style="padding: 15px; border-bottom: 1px solid #ccc; background-color: #eee;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
					<tbody>
						<tr valign="top">
							<td width="100%">
								<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
									<tbody>
										<tr>
											<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 27px; font-weight:700; line-height: 1; color: #333;">
												Thanks for <br />booking with us!
											</td>
										</tr>
										<tr>
											<td style="font-size: 14px; font-weight:600; padding: 10px 0 0;">
												<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: none; line-height: 1; color: #0071c2;" href="{{ $arEmailData['urlBookingInfo'] }}">View Online</a>
											</td>
										</tr>
									</tbody>
								</table>
							</td>
							<td style="font-size: 13px; font-weight:700; color: #333;">
								<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
									<tbody>
										<tr>
											<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight:600; line-height: 1; color: #333;">
												{{ $arEmailData['custName'] }}
											</td>
										</tr>

                                    @if($arEmailData['custPhone'])
										<tr>
											<td style="font-size: 13px; font-weight:600; padding: 3px 0 0;">
												<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: none; color: #0071c2;" href="tel:{{ $arEmailData['custPhone'] }}">{{ $arEmailData['custPhone'] }}</a>
											</td>
										</tr>
									@endif

										<tr>
											<td style="font-size: 13px; font-weight:600; padding: 3px 0 0;">
												<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: none; color: #0071c2;" href="mailto:{{ $arEmailData['custEmail'] }}">{{ $arEmailData['custEmail'] }}</a>
											</td>
										</tr>
									</tbody>
								</table>
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>

		<tr>
			<td style="padding: 15px;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr>
							<td width="100%" style="padding: 0; margin:0; border: 1px solid #ccc; border-radius: 5px;">
								<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
									<tbody>
										<tr>
											<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 27px; font-weight: 700; line-height: 1; color: #ff0000; padding: 10px 15px; border-bottom: 1px solid #ccc; text-align: center;">Booking #{{ $arEmailData['bookingId'] }}</td>
										</tr>
										<tr>
											<td width="100%" style="padding: 0; margin:0; padding: 15px; border-bottom: 1px solid #ccc;">
												<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
													<tbody>
														<tr valign="top">

														@if(isset($arEmailData['tourPkgPhoto']))
															<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 600; line-height: 1; color: #333; padding-right: 15px;">
																<img style="display: inline-block; max-width: 125px; border-radius: 5px;" src="{{ $arEmailData['tourPkgPhoto'] }}" alt="images">
															</td>
														@endif

															<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 600; line-height: 1; color: #333; padding-right: 15px;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
																	<tbody>
																		<tr>
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 12px; font-weight: 400; line-height: 1; color: #333;">
																				{{ $arEmailData['tourPackage'] }}
																			</td>
																		</tr>
																		@if(!empty($arEmailData['dateFrom']))
																		<tr>
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: #333; padding-top: 5px">
																				Reschedule From
																				{{ $arEmailData['dateFrom'] }} @ {{ $arEmailData['timeFrom'] }}
																			</td>
																		</tr>
																		<tr>
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: #333; padding-top: 5px">
																				To
																				{{ $arEmailData['tourDateTime'] }} @ {{ $arEmailData['tourtime'] }}
																			</td>
																		</tr>
																		@else
																		<tr>
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: #333; padding-top: 5px">
																				{{ $arEmailData['tourDateTime'] }}
																			</td>
																		</tr>
																		@endif
																		@if(!empty( $arEmailData['numberoftourist']))
																		<tr>

																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: #333; padding-top: 5px">Number of tourists:
																				{{ $arEmailData['numberoftourist'] }}
																			</td>
																		</tr>
																		@endif
																		@foreach($tourListwithSeat as $data)
																		<tr>

																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: #333; padding-top: 5px">
																				{{ $data->rate_for }}:
																				{{ $data->tourists }}
																			</td>
																		</tr>
																		@endforeach
																		@if(!empty( $arEmailData['deletedFor']))
																		<tr>

																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: red; padding-top: 5px">
																				Remove people from booking:
																				{{ $arEmailData['deletedFor'] }}: 
																				 {{ $arEmailData['deletedPeople'] }}
																			</td>
																		</tr>
																		@endif
																	</tbody>
																</table>
															</td>
															
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
										<tr>
											<td width="100%" style="padding: 0; margin:0; padding: 15px; border-bottom: 1px solid #ccc; background-color: #ffeec4;">
												<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
													<tbody>
														<tr valign="middle">
															<td width="100%" style="padding: 0; margin:0; padding-right: 15px;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1; color: #333;">
																				Important: Your Tickets
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				Tour (30, 60 or 90 minutes, or Sunset or Sunrise Tour)number of peopleadults, childrenamount paiddiscount for 4+ people (if applicable)
																				A copy of your tickets can be found here. Please print them out or have them open on your phone so we can get you checked in..
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
															<td style="padding: 0; margin:0;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1; color: #333; text-align:right;">
																				<a style="width: 150px; font-family: Arial, sans-serif, 'Open Sans'; background-color: #666; font-size: 12px; color: #fff; display: block; white-space: nowrap; padding: 10px 15px; text-decoration: none; border-radius: 5px; text-align:center;" href="{{ $arEmailData['urlBookingInfo'] }}">Go to your tickets ></a>
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
										<tr>
											<td width="100%" style="padding: 0; margin:0; padding: 15px; border-bottom: 1px solid #ccc; background-color: #daf3db;">
												<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
													<tbody>
														<tr valign="middle">
															<td width="100%" style="padding: 0; margin:0; padding-right: 15px;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1; color: #2a622a;">
																				Next Step: Your Waivers
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				Please have all participants fill out this short online waiver prior to arrival. It can be easily emailed to anyone that is coming, and signed and dated electronically.
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
															<td style="padding: 0; margin:0;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1; color: #333; text-align:right;">
																				<a style="min-width: 150px; max-width: 150px; font-family: Arial, sans-serif, 'Open Sans'; background-color: #666; font-size: 12px; color: #fff; display: block; white-space: nowrap; padding: 10px 15px; text-decoration: none; border-radius: 5px; text-align:center;" href="{{ $arEmailData['urlLiabilityWaiver'] }}">Sign Waiver ></a>
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
										<tr>
											<td width="100%" style="padding: 0; margin:0; padding: 15px 15px 0 15px; background-color: #eaecfa;">
												<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
													<tbody>
														<tr valign="middle">
															<td width="100%" style="padding: 0; margin:0; padding-right: 15px;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1; color: #36489a;">
																				Please check in at:
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 600; line-height: 1.5; color: #333; padding-top:8px;">
																				Monument Valley KOA Journey <br />Milepost 2, US-163, Monument Valley, UT 84536
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
															<td style="padding: 0; margin:0;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="left">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1; color: #333; text-align:right;">
																				<a style="min-width: 150px; max-width: 150px; font-family: Arial, sans-serif, 'Open Sans'; background-color: #666; font-size: 12px; color: #fff; display: block; white-space: nowrap; padding: 10px 15px; text-decoration: none; border-radius: 5px; text-align:center;" href="https://www.google.com/maps/place/Monument+Valley+KOA+Journey/@37.017498,-110.153563,15z/data=!4m8!3m7!1s0x0:0xe42ec6fd1ab56132!5m2!4m1!1i2!8m2!3d37.017498!4d-110.153563">Get directions ></a>
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
										<tr>
											<td width="100%" style="padding: 0; margin:0; padding: 15px; background-color: #eaecfa;">
												<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
													<tbody>
														<tr valign="middle">
															<td style="padding: 0; margin:0; padding-right: 15px;">
																<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
																	<tbody>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				Directions
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				We meet on the North side of the KOA Campground by the horse coral. Please arrive at least 30 minutes prior to your scheduled tour so we can check your party in.
What to bring
Bottled waterShoes appropriate for horseback ridingSunscreenLong pantsHat
Rules and regulations
The minimum age to ride horseback is 8 years old Guests must remain with their designated tour guides
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				
																			</td>
																		</tr>
																	</tbody>
																</table>
															</td>
														</tr>
													</tbody>
												</table>
											</td>
										</tr>
									</tbody>
								</table>
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>

		<tr>
			<td style="padding: 15px 15px 20px 15px; border-bottom: 1px solid #ccc;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333;">
								Do not disturb or remove plants, animals or artifacts
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1.5; color: #333; padding-top:8px;">
								Important Tour Information:
							</td>
						</tr>

@if($arEmailData['importantNotes'])
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
								{!! str_replace("\n", '<br>', e($arEmailData['importantNotes'])) !!}
							</td>
						</tr>
@endif

@if(!empty($arEmailData['thingsToBring']))
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1.5; color: #333; padding-top:10px;">
								What to Bring
							</td>
						</tr>
	@foreach($arEmailData['thingsToBring'] as $thing)
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004; {{ $thing }}
							</td>
						</tr>
	@endforeach
@endif

					</tbody>
				</table>
			</td>
		</tr>
		<tr>
			<td style="padding: 20px 15px 15px 15px; border-bottom: 1px solid #ccc;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1.5; color: #333; padding-top:8px;">
								Important Rules & Regulations:
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1.5; color: #333; padding-top:10px;">
								Rules and Regulations:
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No littering
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No smoking
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No alcoholic beverages
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  We accommodate beginners to advanced riders. We will match you with a horse based on your experience.
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  Helmets are available at customer request
							</td>
						</tr>
						
@if($arEmailData['cancellationPolicy'])
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1.5; color: #333; padding-top:20px;">
								Cancellations
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								{!! str_replace("\n", '<br>', e($arEmailData['cancellationPolicy'])) !!}
							</td>
						</tr>
@endif

					</tbody>
				</table>
			</td>
		</tr>
		<tr>
			<td style="padding: 15px; border-bottom: 1px solid #ccc; background-color: #eee;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px;">
								<strong>About this email:</strong> You are receiving this email because you provided us with your email address for updates. You can <a style="color:#0071c2; text-decoration: none;" href="#">unsubscribe</a> from all future emails.
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 600; line-height: 1.5; color: #333; padding-top:8px;">
								{{ $arEmailData['tourOperator'] }}
							</td>
						</tr>

@if($arEmailData['tourOpPhone'])
						<tr>
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 12px; font-weight: 600; line-height: 1; color: #333; padding-top: 3px;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: underline; color: #0071c2;" href="tel:{{ $arEmailData['tourOpPhone'] }}">{{ $arEmailData['tourOpPhone'] }}</a>
							</td>
						</tr>
@endif

@if($arEmailData['tourOpEmail'])
						<tr>
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 12px; font-weight: 600; line-height: 1; color: #333; padding-top: 3px;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: underline; color: #0071c2;" href="mailto:{{ $arEmailData['tourOpEmail'] }}">{{ $arEmailData['tourOpEmail'] }}</a>
							</td>
						</tr>
@endif

@if($arEmailData['tourOpWebsite'])
						<tr>
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 12px; font-weight: 600; line-height: 1; color: #333; padding-top: 3px;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: underline; color: #0071c2;" href="{{ ($http == 'http://' || $http == 'https:/' ? '' : 'http://') . $arEmailData['tourOpWebsite'] }}">{{ $arEmailData['tourOpWebsite'] }}</a>
							</td>
						</tr>
@endif

						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:15px;">
								All prices in US dollars
							</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>
<div class="mt-2">
	<img src="{{ asset('images/D_D_tour_map.png') }}" alt="map" width="542" height="291">
</div>