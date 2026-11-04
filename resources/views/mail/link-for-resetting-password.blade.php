@extends('mail.main')

@section('main-content')
<p><b>Hello {{ $arEmailData['receiverName'] }},</b></p>
<p>We received a request to reset password of your <b>{{ config('app.name') }}</b> account.</p>
<p>Please click the below link to reset your account's password.</p>

<p style="text-align:center">
	<a href="{{ $arEmailData['url'] }}" style="font-size:130%;text-decoration:underline">Reset Password</a>
</p>

<p>The link will expire in 60 minutes.</p>
<p>&nbsp;</p>
<p style="font-size:80%">If you did not request to reset password, no action is required by you.</p>
@endsection
