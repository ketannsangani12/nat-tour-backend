 
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
							@if($arEmailData['custPhone'])
							<td style="text-align: center; font-size: 13px; font-weight: 600; line-height: 1; padding: 0; margin:0;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; white-space: nowrap; text-decoration: none; color: #0071c2; padding: 0 10px;" href="tel:{{ $arEmailData['custPhone'] }}">{{ $arEmailData['custPhone'] }}</a>
							</td>
							@endif

							@if($arEmailData['custEmail'])
							<td style="text-align: center; font-size: 13px; font-weight: 600; line-height: 1; padding: 0; margin:0;">
								<a style="font-family: Arial, sans-serif, 'Open Sans'; white-space: nowrap; text-decoration: none; color: #0071c2; padding: 0 10px; border-left: 1px solid #ccc; border-right: 1px solid #ccc;" href="mailto:{{ $arEmailData['custEmail'] }}">{{ $arEmailData['custEmail'] }}</a>
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
																				{{ $arEmailData['receiverName'] }} <br />
																				@if(!empty($arEmailData['guidePhone']))<a href="tel:{{ $arEmailData['guidePhone'] }}">{{ $arEmailData['guidePhone'] }}</a>
																				@endif <br /><a href="mailto:{{ $arEmailData['guideEmail'] }}">{{ $arEmailData['guideEmail'] }}</a>
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
																			@if($arEmailData['custName'])
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				<!-- Directions -->
																				You'd been paid! <span style="font-weight: 600;">{{ $arEmailData['custName'] }}</span> has just left you a tip of <span style="font-weight: 600;">{{ moneyFormat($arEmailData['custPayAmount']) }}</span> for your outstanding work on {{$arEmailData['date']}}.
																			</td>
																			@endif
																		</tr>
																		<tr valign="top">
																			<td style="font-family: Arial, sans-serif, 'Open Sans'; font-size: 13px; font-weight: 400; line-height: 1.5; color: #333; padding-top:8px;">
																				Thank you!
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
	 