<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $arEmailData['subject'] }} | {{ config('app.name') }}</title>
</head>
<body>
<table style="max-width:600px;margin:auto">
	<thead><tr>
		<td style="text-align:center;padding-bottom:20px">
			<img src="{{ asset('images/logo.png') }}" title="{{ config('app.name') }}" alt="{{ config('app.name') }}">
		</td>
	</tr></thead>

	<tbody><tr><td>

		@yield('main-content')

	</td></tr></tbody>

	<tfoot><tr>
		<td style="text-align:center">

			@yield('footer')

		</td>
	</tr></tfoot>
</table>
</body>
</html>
