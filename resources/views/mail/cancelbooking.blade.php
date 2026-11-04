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
												Your Booking has been <br />cancelled
												and you can expect a refund within 7-10 business days
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
																<img style="display: inline-block; max-width: 125px; border-radius: 5px;" src="{{$arEmailData['tourPkgPhoto'] }}" alt="images">
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
																				{{ $arEmailData['tourDateTime'] }} 
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
																		@if(!empty($arEmailData['cardNumber']))
																		<tr>

																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 16px; font-weight: 600; line-height: 1; color: #333; padding-top: 5px">
																				Card Number:
																				{{ $arEmailData['cardNumber'] }}
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
										
										
					</tbody>
				</table>
			</td>
		</tr>
		<tr>
			<td style="padding-top: 10px">
				Your purchase was successfully refunded. Should you have any questions or concerns, or if this cancellation was made in error, please call us at 928-640-1761.
			</td>
		</tr>
	</tbody>
</table>