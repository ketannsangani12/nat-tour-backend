<table style="font-family: Arial, sans-serif; padding: 20px 15px;margin: 0 auto;max-width: 600px; border-spacing:0; border: 1px solid #C8C8C8;"  width="600" cellspacing="0" cellpadding="0" bgcolor="#fff" align="center">
	<tbody>
		<tr>
			<td>
			<table width="100%" cellspacing="0" cellpadding="0" border="0" align="center" style="padding: 0px 0px 30px 0px;">
			   <tbody>
			      <tr align="center">
			         <td>
			            <a href="#" title="Logo" target="_blank">
                            @if (!Storage::disk('s3')->missing('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo))
                                <img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $invoice->tourOperator->tenant_id . '/logo/' . $invoice->tourOperator->logo) }}" class="img-fluid" alt="{{ $invoice->tourOperator->name }} Logo" onerror="noPhoto(this)" width="135" height="122">
                            @else
                                <div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg"  width="135" height="122"></div>
                            @endif
			            </a>
			         </td>
			      </tr>
			   </tbody>
			</table>
			</td>
		</tr>

		<tr>
			<td>
				<table width="100%" cellspacing="0" cellpadding="0" border="0" style="padding: 0px 0px 25px 0px;">
				   <tbody>
				      <tr>
				         <td valign="top" style="padding:0;">
				            <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
				               <tbody>
				                  <tr>
				                     <td style="font-size: 18px;font-weight: 600;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        Remit to
                                    </td>
				                  </tr>
				                  <tr>
				                     <td style="font-size: 18px;font-weight: 400;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        {{ $invoice->tourOperator->name }}<br> {{ $invoice->tourOperator->address }} {{ $invoice->tourOperator->postal_code }}
                                    </td>
				                  </tr>
                                  @if($invoice->tourOperator->phone_number)
				                  <tr>
				                     <td style="display: flex; align-items: center; font-size: 18px;font-weight: 400;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        <img src="{{ asset('images/email/phone.png') }}" width="24px" height="24px" border="0" alt="Logo" title="Phone Icon">
                                        <a href="tel:{{ $invoice->tourOperator->phone_number }}" style="text-decoration: none; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 500; font-size: 18px; line-height: 1; color: #0071c2;margin-left: 10px;">
                                            {{ $invoice->tourOperator->phone_number }}
                                        </a>
                                    </td>
				                  </tr>
                                  @endif
                                  @if($invoice->tourOperator->email)
				                  <tr>
				                     <td style="display: flex; align-items: center; font-size: 18px;font-weight: 400;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        <img src="{{ asset('images/email/mail.png') }}" width="24px" height="24px" border="0" alt="Logo" title="Mail Icon">
                                        <a href="mailto:{{ $invoice->tourOperator->email }}" style="text-decoration: none; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 500; font-size: 18px; line-height: 1; color: #0071c2; margin-left: 10px;">
                                            {{ $invoice->tourOperator->email }}
                                        </a>
                                    </td>
				                  </tr>
                                  @endif
                                  @if($invoice->tourOperator->website)
				                  <tr>
				                     <td style="display: flex; align-items: center; padding:0 0 10px;">
                                        <img src="{{ asset('images/email/url.png') }}" width="24px" height="24px" border="0" alt="Logo" title="URL Icon">
                                        <a href="{{ $invoice->tourOperator->website }}" target="__blank" style="text-decoration: none; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 500; font-size: 18px; line-height: 1; color: #0071c2; margin-left: 10px;">
                                            {{ $invoice->tourOperator->website }}
                                        </a>
                                    </td>
				                  </tr>
                                  @endif
				               </tbody>
				            </table>
				         </td>
				         <td valign="top" style="padding:0;">
				            <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
				               <tbody>
				                  <tr>
				                     <td style="font-size: 18px;font-weight: 600;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        Billed to
                                    </td>
				                  </tr>
				                  <tr>
				                     <td style="font-size: 18px;font-weight: 400;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        {{ $invoice->affiliate->name }}<br> {{ $invoice->affiliate->address }} {{ $invoice->affiliate->postal_code }}
                                    </td>
				                  </tr>
                                  @if($invoice->affiliate->phone_number)
				                  <tr>
				                     <td style="display: flex; align-items: center; font-size: 18px;font-weight: 400;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        <img src="{{ asset('images/email/phone.png') }}" width="24px" height="24px" border="0" alt="Logo" title="Phone Icon">
                                        <a href="tel:{{ $invoice->affiliate->phone_number }}" style="text-decoration: none; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 500; font-size: 18px; line-height: 1; color: #0071c2;margin-left: 10px;">
                                            {{ $invoice->affiliate->phone_number }}
                                        </a>
                                    </td>
				                  </tr>
                                  @endif
                                  @if($invoice->affiliate->email)
				                  <tr>
				                     <td style="display: flex; align-items: center; font-size: 18px;font-weight: 400;line-height: 1.3;color: #000;font-family: Arial, sans-serif;padding:0 0 10px;">
                                        <img src="{{ asset('images/email/mail.png') }}" width="24px" height="24px" border="0" alt="Logo" title="Mail Icon">
                                        <a href="mailto:{{ $invoice->affiliate->email }}" style="text-decoration: none; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 500; font-size: 18px; line-height: 1; color: #0071c2; margin-left: 10px;">
                                            {{ $invoice->affiliate->email }}
                                        </a>
                                    </td>
				                  </tr>
                                  @endif
                                  @if($invoice->affiliate->website)
				                  <tr>
				                     <td style="display: flex; align-items: center; padding:0 0 10px;">
                                        <img src="{{ asset('images/email/url.png') }}" width="24px" height="24px" border="0" alt="Logo" title="URL Icon">
                                        <a href="{{ $invoice->affiliate->website }}" target="__blank" style="text-decoration: none; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 500; font-size: 18px; line-height: 1; color: #0071c2; margin-left: 10px;">
                                            {{ $invoice->affiliate->website }}
                                        </a>
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
			<td>
				<table width="100%" cellspacing="0" cellpadding="0">
					<tr>
						<th style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none;">Invoice</th>
						<th style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none;">Date Range</th>
						<th style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none;">Created</th>
						<th style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none;">Due Date</th>
						<th style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left;">Total</th>
					</tr>
					<tr>
						<td style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none; border-top:none;">
                            {{ $invoice->invoice_number }}
                        </td>
						<td style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none; border-top:none;">
                            {{ date('m/d/Y', strtotime($invoice->from_date)) .' - '. date('m/d/Y', strtotime($invoice->to_date)) }}
                        </td>
						<td style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none; border-top:none;">
                            {{ date('m/d/Y @ H:i:s', strtotime($invoice->created_at)) }}
                        </td>
						<td style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-right: none; border-top:none;">
                            {{ date('m/d/Y', strtotime($invoice->due_date)) }}
                        </td>
						<td style="font-size: 14px; border: 1px solid #C8C8C8; padding: 5px 10px 5px 10px; text-align: left; border-top:none;">
                            {{ moneyFormat($invoice->amount) . ' — Unpaid' }}
                        </td>
					</tr>
				</table>
			</td>
		</tr>
	</tbody>

	<tfoot>
		<tr>
			<td colspan="100%" style="text-align: center;"> 
				@if(!empty($invoice->external_payment_link))
				<a  href="{{$invoice->external_payment_link}}"
					style="padding:10px;text-decoration:none;font-weight:bolder;background-color: green;border-radius: 5px;color: white;margin: 4px;display: inline-block;"
					target="_blank">Pay Online</a>
				@endif
			</td>
		</tr>
	</tfoot>
</table>
