@extends('mail.main')

@section('main-content')
{!! $arEmailData['emailContent'] !!}
@endsection

@section('footer')
<hr>
<div>
	<p><img src="{{ asset( $arEmailData['tourOpLogo']) }}" alt="" style="max-width:80px;max-height:80px"></p>
	<p style="font-size:x-large"><b>{{ $arEmailData['tourOperator'] }}</b></p>

	<p>
	@if($arEmailData['tourOpPhone'])
		<a href="tel:{{ $arEmailData['tourOpPhone'] }}">{{ $arEmailData['tourOpPhone'] }}</a> &bull;
	@endif

		{{ $arEmailData['tourOpEmail'] ?? '' }} 
	</p>

	<p>
		{{
			($arEmailData['tourOpAddress'] ? $arEmailData['tourOpAddress'] : '') .' '.
			$arEmailData['tourOpCity'] .
			($arEmailData['tourOpPostalCode'] ? ' ' . $arEmailData['tourOpPostalCode'] : '') . ', ' .
			$arEmailData['tourOpState'] . ', ' . $arEmailData['tourOpCountry']
		}}
	</p>
</div>
@endsection
