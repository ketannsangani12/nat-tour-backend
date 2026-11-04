<script src="{{ asset('js/octo-keys.js') }}"></script>
<script>
    OctoKeyManager.init({
        baseUrl:   '{{ url("admin/tenant") }}',
        csrfToken: '{{ csrf_token() }}',
    });
</script>
