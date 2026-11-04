 
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
			<td style="padding: 15px;">
				<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
					<tbody>
						<tr>
							<td width="100%" style="padding: 0; margin:0; border: 1px solid #ccc; border-radius: 5px;">
								<table width="100%" border="0" cellpadding="0" cellspacing="0" align="center">
									<tbody>
										<tr>
											<td style="text-align: right; font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 600; line-height: 1; color: #333; padding: 10px 15px; border-bottom: 1px solid #ccc;">Booking #{{ $arEmailData['bookingId'] }}</td>
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
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 600; line-height: 1.5; color: #333; padding-top:8px;">
																				{{ $arEmailData['receiverName'] }} <br /><a href="tel:{{ $arEmailData['custPhone'] }}">{{ $arEmailData['custPhone'] }}</a> <br /><a href="mailto:{{ $arEmailData['custEmail'] }}">{{ $arEmailData['custEmail'] }}</a>
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
																			@if($arEmailData['guideName'])
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				<!-- Directions -->
																				Your tip to <span style="font-weight: 600;">{{ $arEmailData['guideName'] }}</span> has been sent.
																			</td>
																			@endif
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				Thank you!
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																			</td>
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				We hope you enjoyed your tour, and hope to see you again soon!
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
	</tbody>
</table>
	 