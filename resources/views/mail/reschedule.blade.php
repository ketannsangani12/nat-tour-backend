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
												{{ $arEmailData['receiverName'] }}
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
																				Use the button to the right to go to your tickets. Please bring a printed copy or keep them ready to open on your phone so we can check you in.
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
																				Hiking Tour Waiver: Please have all participants complete the online waiver prior to arrival. It's quick and easy! This email can be forwarded so other participants you have paid for can also sign.
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
																				Indn Route 222 <br />Page, AZ 86040
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
																				<a style="min-width: 150px; max-width: 150px; font-family: Arial, sans-serif, 'Open Sans'; background-color: #666; font-size: 12px; color: #fff; display: block; white-space: nowrap; padding: 10px 15px; text-decoration: none; border-radius: 5px; text-align:center;" href="https://www.google.com/maps/dir//Dixie+Ellis'+Lower+Antelope+Canyon+Tours,+Page,+AZ+86040,+USA/@36.9018503,-111.4127954,17z/data=!4m8!4m7!1m0!1m5!1m1!1s0x8734139684856e1f:0x2170c2722ad9e485!2m2!1d-111.410263!2d36.9013214">Get directions ></a>
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
																				Lower Antelope Canyon Tours can be found by driving East of Page on Highway 98 towards Antelope Point Marina (N22B) turn off. Our office location is the first left-hand turn upon entering the Lower Antelope Park area.
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				To find us, use Google Maps, search Dixie's Lower Antelope Canyon, and the map will bring you directly to our location.
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				Arrive 45 minutes prior to your reserved tour time. Your party MUST be fully checked in at least 20 minutes prior to your tour departure time. Failure to check in properly will result in your reservation to be cancelled or rebooked based on availability.
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
								To speed-up check-in time, please fill out the online waiver which is located above.
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
								Rules and Regulations of Navajo National Parks and Recreation:
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  Do not disturb or remove plants, animals or artifacts
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No rock climbing or hiking in or around the Canyon
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No writing on the Canyon walls
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No smoking inside of the Canyon
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No alcoholic beverages allowed on the Navajo Nation
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  Do not litter
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  Beware of flash floods, snakes, spiders, and scorpions
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  The Navajo Nation is not responsible for any injuries or theft of personal property while in the Park
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 14px; font-weight: 600; line-height: 1.5; color: #333; padding-top:20px;">
								Rules and Regulations of Dixie's Lower Antelope Canyon Tours:
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  All guests must remain with their designated tour guides
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No selfie sticks, tripods, monopods, or go-pros
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No taking pictures on the stairs
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  No service dogs
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  Attire shall be appropriate for climbing stairs (no dresses, no high heels)
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  LACT highly discourages the following from participating:
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 40px;">
								&#10146;  Any person with physical disabilities.
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 40px;">
								&#10146;  Children under the age of 4. Children should be able to walk by themselves. Adults need both hands free to navigate the canyon.
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
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1.5; color: #333; padding-top:8px;">
								Health & Safety
							</td>
						</tr>
						<tr valign="top">
							<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:3px; padding-left: 20px;">
								&#10004;  We are currently operating at 50% capacity to allow guests to maintain distance from other parties.
							</td>
						</tr>
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