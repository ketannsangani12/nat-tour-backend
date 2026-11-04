<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns:v="urn:schemas-microsoft-com:vml">
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<!--[if !mso]><!-->
	<meta http-equiv="x-ua-compatible" content="IE=edge">
	<!--<![endif]-->
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Thanks For Booking - Our Native Tours</title>
	<style type="text/css">
		.img-width { max-width: 100%; }
		.topimg-width{max-width:150px}
		.contact-link { font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 15px; white-space: nowrap; text-decoration: none; color: #231f20; padding: 0 10px; }
		.footer-link {display: block; font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 16px; white-space: nowrap; text-decoration: none; color: #231f20; padding: 3px 0; }
		.contact-table { padding: 10px 0 !important; }
		.col-2 { width: 50%; padding: 0; }
		.col-03 { width: 33.33%; padding: 10px 5px; vertical-align: top; }
		.details { padding-left: 30px; }
		ul.points { margin: 0; padding: 0px 20px; }
		ul.points li { font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 15px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0; }
		ul.points2 { margin: 0; padding: 0px; list-style-type: none; }
		ul.points2 li { font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 15px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0; }
		@media (max-width: 640px) {
			.col-3 {display: block; width: 100%; padding: 5px 0 !important; text-align: center; }
			.col-2 {display: block; width: 100%; text-align: center; }
			.details {padding-left: 0px; padding-top: 20px; }
			.bookinig-id-table h1 { font-size: 33px !important; }
			.bookinig-details-table p { font-size: 16px !important; }
			.next-step-table h2 { font-size: 21px !important; }
			.top-m { margin-top: 20px !important; }
			.box3-table {padding: 15px !important; }
			.col-03 { display: block; width: 100%; padding: 0; }
		}
	</style>
</head>
<body style="padding:0; background-color: #ffffff; font-family: 'Open Sans',sans-serif; margin: 0 !important;">
<div class="webkit" style="max-width:640px; margin:0 auto;">

	<!-- Header Section Start -->
	<table align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; padding: 15px;">
		<tbody>
		<tr>
			<td align="center" style="padding: 0;">
				@if(isset($arEmailData['tourOpLogo']))
					<img src="{{ $arEmailData['tourOpLogo'] }}" alt="Header Logo's" class="topimg-width">
				@else
					{{ $arEmailData['tourOperator'] }}
				@endif
				@if(isset($arEmailData['tourPkgPhoto']))
					<img src="{{ $arEmailData['tourPkgPhoto'] }}" alt="Header Logo's" class="topimg-width">
				@endif
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Header Section End -->

	<!-- Contact Section Start -->
	<table class="contact-table" align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 15px 0px;">
		<tbody>
		<tr>
			@if($arEmailData['tourOpPhone'])
				<td class="col-3" style="padding: 0;">
					<a href="tel:{{ $arEmailData['tourOpPhone'] }}" class="contact-link">
						{{ $arEmailData['tourOpPhone'] }}</a>
				</td>
			@endif
			@if($arEmailData['tourOpEmail'])
				<td class="col-3" style="padding: 0;">
					<a href="mailto:{{ $arEmailData['tourOpEmail'] }}" class="contact-link">{{ $arEmailData['tourOpEmail'] }}</a>
				</td>
			@endif
			@if($arEmailData['tourOpWebsite'])
				@php
					$http = substr($arEmailData['tourOpWebsite'], 0, 7);
				@endphp
				<td class="col-3" style="padding: 0;">
					<a href="{{ ($http == 'http://' || $http == 'https:/' ? '' : 'http://') . $arEmailData['tourOpWebsite'] }}" class="contact-link">{{ $arEmailData['tourOpWebsite'] }}</a>
				</td>
			@endif
		</tr>
		</tbody>
	</table>
	<!-- Contact Section End -->

	<!-- Bookinig Section Start -->
	<table class="bookinig-table" align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 15px">
		<tbody>
		<tr>
			<td class="col-2" style="padding: 0;">
				<h1 style="font-size: 30px; font-weight: 700; line-height: 1.2; margin: 0; color: #000000;">Thanks for <br>booking with us!</h1>
			</td>

			<td style="font-size: 14px; font-weight:600; padding: 10px 0 0;">
				<a style="font-family: Arial, sans-serif, 'Open Sans'; text-decoration: none; line-height: 1; color: #0071c2;" href="{{ $arEmailData['urlBookingInfo'] }}">View Online</a>
			</td>

			<td class="col-2" style="padding: 0;">
				<div class="details">
					<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 18px; white-space: nowrap; text-decoration: none; color: #000000; margin: 7px 0;">{{ $arEmailData['custName'] }}</p>
					<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 18px; white-space: nowrap; text-decoration: none; color: #000000; margin: 7px 0;"><a href="tel:{{ $arEmailData['custPhone'] }}">{{ $arEmailData['custPhone'] }}</a></p>
					<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 18px; white-space: nowrap; text-decoration: none; color: #000000; margin: 7px 0;"><a href="mailto:{{ $arEmailData['custEmail'] }}">{{ $arEmailData['custEmail'] }}</a></p>
				</div>
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Bookinig Section End -->

	<!-- Bookinig ID Section Start -->
	<table class="bookinig-id-table" align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 5px 15px">
		<tbody>
		<tr>
			<td align="center" style="padding: 0;">
				<h1 style="font-size: 45px;font-weight: 700;line-height: 1.2;margin: 0;color: #f16938;">BOOKING #{{ $arEmailData['bookingId'] }}</h1>
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Bookinig ID Section End -->

	<!-- Bookinig Details Section Start -->
	<table class="bookinig-details-table" align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 0 15px">
		<tbody>
		<tr>
			<td align="center" style="padding: 0;">
				<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 20px; white-space: nowrap; text-decoration: none; color: #000000; margin: 10px 0;">{{ $arEmailData['tourPackage'] }}</p>
				<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 20px; white-space: nowrap; text-decoration: none; color: #000000; margin: 10px 0;">{{ $arEmailData['tourDateTime'] }}</p>
				@if(!empty( $arEmailData['numberoftourist']))
					<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 20px; white-space: nowrap; text-decoration: none; color: #000000; margin: 10px 0;">Number of tourists: {{ $arEmailData['numberoftourist'] }}</p>
				@endif
				@foreach($tourListwithSeat as $data)
					<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 20px; white-space: nowrap; text-decoration: none; color: #000000; margin: 10px 0;">{{ $data->rate_for }}:
						{{ $data->tourists }}</p>
				@endforeach
				@if(!empty($arEmailData['deletedPeople']))
					@foreach($arEmailData['deletedPeople'] as $people)
					@php
						$details = json_decode($people->details);
						$details->deleted = sprintf('%s', $details->deleted??'');
					@endphp
						<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 20px; white-space: nowrap; text-decoration: none; color: #000000; margin: 10px 0;">
							Removed people from booking:
							{{ $details->deleted }}
							
						</p>
					@endforeach
				@endif
				@if(isset($arEmailData['refundedamount']) && !empty( $arEmailData['refundedamount']))
					<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 20px; white-space: nowrap; text-decoration: none; color: #000000; margin: 10px 0;">
						Refunded Amount:
						{{ $arEmailData['refundedamount'] }}
					</p>
				@endif

			</td>
		</tr>
		</tbody>
	</table>
	<!-- Bookinig Details Section End -->

	<!-- Next Step Section Start -->
	<table class="next-step-table" align="center" width="100%" style="background: #f8d2b3; border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 15px">
		<tbody>
		<tr>
			<td style="padding: 0;">
				<h2 style="font-size: 22px; font-weight: 900; line-height: 1; margin: 0; margin-bottom: 10px; color: #231f20;">NEXT STEP: YOUR WAIVERS</h2>
				<p style="font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 14px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0;">Hiking Tour Waiver: Please have all participants complete the online waiver prior to arrival. It's quick and easy! This email can be forwarded so other participants can sign.<br>
					<a style="min-width: 150px; max-width: 150px; font-family: Arial, sans-serif, 'Open Sans'; background-color: #666; font-size: 12px; color: #fff; display: block; white-space: nowrap; padding: 10px 15px; text-decoration: none; border-radius: 5px; text-align:center;float: right;" href="{{ $arEmailData['urlLiabilityWaiver'] }}">Sign Waiver</a>
				</p>
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Next Step Section End -->

	<!-- Check In Section Start -->
	<table class="checkin-table" align="center" width="100%" style="background: #f8d2b3; border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 15px">
		<tbody>
		<tr>
			<td style="padding: 0;">
				<h2 style="font-size: 22px; font-weight: 900; line-height: 1; margin: 0; margin-bottom: 10px; text-align: center; color: #f68d22;">PLEASE CHECK IN AT:</h2>
				<h3 style="font-size: 20px; font-weight: 700; line-height: 1; margin: 0; margin-bottom: 5px; text-align: center; color: #231f20;">Indn, Route 222</h3>
				<h3 style="font-size: 20px; font-weight: 700; line-height: 1; margin: 0; margin-bottom: 15px; text-align: center; color: #231f20;">Page, AZ 86040</h3>
				<a style="min-width: 150px; max-width: 150px; font-family: Arial, sans-serif, 'Open Sans'; background-color: #666; font-size: 12px; color: #fff; display: block; white-space: nowrap; padding: 10px 15px; text-decoration: none; border-radius: 5px; text-align:center;float: right;" href="https://www.google.com/maps/dir//Dixie+Ellis'+Lower+Antelope+Canyon+Tours,+Page,+AZ+86040,+USA/@36.9018503,-111.4127954,17z/data=!4m8!4m7!1m0!1m5!1m1!1s0x8734139684856e1f:0x2170c2722ad9e485!2m2!1d-111.410263!2d36.9013214">Get directions ></a>
				<br><br>
				<!-- DIRECTIONS -->
				<h2 style="font-size: 22px; font-weight: 900; line-height: 1; margin: 0; margin-bottom: 10px; color: #231f20;">DIRECTIONS</h2>
				<p style="font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 14px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0; margin-bottom: 5px;">Dixie’s Lower Antelope Canyon Tours can be found by driving East of Page on the scenic Highway 98 towards Antelope Point Marina turn off. Lower Antelope Canyon Visitor center location is the first left-hand turn upon entering the Navajo Tribal Park.</p>
				<p style="font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 14px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0; margin-bottom: 15px;"><span style="color: #0b9444;">To find us, use Google Maps, search Dixie's Lower Antelope Canyon, and the map will bring you directly to our location. Look for the Green roof.</span> Please arrive <span style="color: #f68d22; font-size: 16px; font-weight: 900;">45 MINUTES</span> prior to your reserved tour time. Your party MUST be fully checked in at least 20 minutes prior to your tour departure time. </p>
			@if($arEmailData['cancellationPolicy'])
				<!-- CANCELLATIONS -->
					<h2 style="font-size: 22px; font-weight: 900; line-height: 1; margin: 0; margin-bottom: 10px; color: #231f20;">CANCELLATIONS</h2>
					<p style="font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 14px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0; margin-bottom: 15px;">{!! str_replace("\n", '<br>', e($arEmailData['cancellationPolicy'])) !!}</p>
			@endif
			<!-- MISSED TOURS -->
				<h2 style="font-size: 22px; font-weight: 900; line-height: 1; margin: 0; margin-bottom: 10px; color: #231f20;">MISSED TOURS</h2>
				<p style="font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 14px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0;">Our tour departure times are restricted by the Navajo Tribal park and we are unable to depart past the scheduled tour time. If you will be late for your tour, please contact us at (928)-640-1761; we will do everything we can to accommodate your schedule but this could result in a different tour or departure time (subject to availability).</p>
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Check In Section End -->

	<!-- Map Section Start -->
	<table align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top:0; padding: 0px;">
		<tbody>
		<tr>
			<td align="center" style="padding: 0;">
				<img src="{{ asset('images/map.png') }}" alt="Map" class="img-width">
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Map Section End -->

	<!-- 3 Box Section Start -->
	<table class="box3-table" align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top:0; padding: 0 10px;">
		<tbody>
		<tr>
			@if(count($arEmailData['thingsToBring']))
				<td class="col-03">
					<h2 style="font-size:20px;font-weight:900;line-height:1;margin:0;margin-bottom:10px;color:#f68d22;">WHAT TO BRING</h2>
					<ul class="points2">
						@foreach($arEmailData['thingsToBring'] as $thing)
							<li>{{ $thing }}</li>
						@endforeach
					</ul>
				</td>
			@endif
			<td class="col-03">
				<h2 class="top-m" style="font-size:20px;font-weight:900;line-height:1;margin:0;margin-bottom:10px;color:#f68d22;">HEALTH & SAFETY</h2>
				<ul class="points2">
					<li>Protect yourself from heat stress</li>
					<li>Beware of flash floods, snakes, and scorpions</li>
					<li>The Navajo Nation is not responsible</li>
					<li>for any injuries or theft of personal property while in the park</li>
				</ul>
			</td>
			<td class="col-03">
				<h2 class="top-m" style="font-size:20px;font-weight:900;line-height:1;margin:0;margin-bottom:10px;color:#f68d22;">CLEAR BAG POLICY</h2>
				<ul class="points2">
					<li>Only clear and transparent bags are allowed on tour</li>
				</ul>
			</td>
		</tr>
		</tbody>
	</table>
	<!-- 3 Box Section End -->

	<!-- Check In Section Start -->
	<table align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 15px">
		<tbody>
		<tr>
			<td style="padding: 0;">
				<h2 style="font-size: 22px; font-weight: 900; line-height: 1.3; margin: 0; margin-bottom: 5px; color: #f68d22;">RULES AND REGULATIONS OF DIXIE'S LOWER ANTELOPE CANYON TOURS:</h2>
				<ul class="points">
					<li>All guests must remain with their designated tour guides</li>
					<li>Do not disturb or remove plants, animals or artifacts</li>
					<li>No rock climbing</li>
					<li>No writing on the Canyon walls</li>
					<li>No smoking inside of the Canyon</li>
					<li>No alcoholic beverages or drugs allowed on the Navajo Nation</li>
					<li>Littering Prohibited</li>
					<li>No selfie sticks, tripods, monopods, or go-pros</li>
					<li>No taking pictures on the stairs</li>
					<li>No service animals</li>

			</td>
		</tr>
		</tbody>
	</table>
	<!-- Check In Section End -->

	<!-- Check In Section Start -->
	<table align="center" width="100%" style="border-spacing:0; border: 1px solid #000000; border-top: 0; padding: 15px">
		<tbody>
		<tr>
			<td style="padding: 0;">
				<h2 style="font-size: 22px; font-weight: 900; line-height: 1.3; margin: 0; margin-bottom: 5px; color: #f68d22;">ABOUT THIS EMAIL:</h2>
				<p style="font-family: Arial, sans-serif, 'Open Sans';font-weight: 600;font-size: 15px;line-height: 1.4;text-decoration: none;color: #231f20;margin: 0;margin-bottom: 10px;">You are receiving this email because you provided us with your email address for updates. You can unsubscribe from all future emails.</p>
				<h2 style="font-size: 20px; font-weight: 700; line-height: 1.3; margin: 0; margin-bottom: 5px; color: #f68d22;">{{ $arEmailData['tourOperator'] }}</h2>
				@if($arEmailData['tourOpPhone'])
					<a href="tel:{{ $arEmailData['tourOpPhone'] }}" class="footer-link">{{ $arEmailData['tourOpPhone'] }}</a>
				@endif
				@if($arEmailData['tourOpEmail'])
					<a href="mailto:{{ $arEmailData['tourOpEmail'] }}" class="footer-link">{{ $arEmailData['tourOpEmail'] }}</a>
				@endif
				@if($arEmailData['tourOpWebsite'])
					<a href="{{ ($http  == 'http://' || $http == 'https:/' ? '' : 'http://') . $arEmailData['tourOpWebsite'] }}" class="footer-link">{{ $arEmailData['tourOpWebsite'] }}</a>
				@endif
				<p style="font-family: Arial, sans-serif, 'Open Sans'; font-weight: 600; font-size: 16px; text-decoration: none; color: #231f20; padding: 3px 0; margin: 0;">All prices in US dollars</p>
			</td>
		</tr>
		</tbody>
	</table>
	<!-- Check In Section End -->
</div>
</body>
</html>
