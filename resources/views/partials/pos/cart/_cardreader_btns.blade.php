<div class="mt-3 mb-3">
<button type="button" class="btn btn-small btn-primary btn-color btn-shadow w-100 connect-to-card-reader btn-primary-regular !w-full">Connect To Card Reader</button>
</div>

{{-- Primary action once connected — the Charge button — is injected here
     by StripeCardReader.setMessage(), right under Connect, so it reads as
     the next thing to do rather than getting buried under secondary
     actions below. --}}
<div class="card-reader-status-output"></div>

{{-- Secondary, de-emphasized row — both actions share one quiet "text
     link" treatment (see .card-reader-quiet-link) so a real <button> and
     an <a> read as the same kind of action instead of a boxed button
     sitting next to a plain underlined link. "Disconnect for today" is
     deliberately not styled as a destructive/danger action — reconnecting
     is just discover+connect again; that treatment is reserved for
     actually forgetting a reader in Card Reader Settings. --}}
<div class="flex items-center justify-center gap-5 mt-4">
    <button type="button" style="display: none;" class="card-reader-quiet-link dis-connect-to-card-reader">Disconnect for today</button>
    <a href="javascript:void(0);" class="switch-card-reader hidden card-reader-quiet-link">Switch reader</a>
</div>
