@component('mail::message', ['domain' => "https://nativeamericantours.com"])

# {{ $release->title }}

@if($release->subtitle)
## {{ $release->subtitle }}
@endif

<div style="margin: 20px 0;">
{!! $release->body !!}
</div>

@component('mail::button', ['url' => tenant_route($domain, "$routePrefix.release.view", $release->id)])
View Full Release Notes
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
