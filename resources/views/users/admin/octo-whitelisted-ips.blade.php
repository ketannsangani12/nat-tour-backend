@extends('layouts.default-2', ['wrapclass' => 'container-fluid'])

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-83px)]">
    <div class="w-full container">
        <div class="flex flex-wrap -mx-3">
            <div class="w-full max-w-full px-3">
                <div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center mb-8">
                        <div class="w-full max-w-full px-3">
                            <h2 class="section-title !mb-0">OCTO API IP Whitelist</h2>
                            <p class="text-sm text-gray-500 mt-1 mb-0">
                                IPs added here are checked alongside the static list in <code>config/octo.php</code> — no code deploy needed to add or remove one.
                                Entries auto-expire 48 hours after being added.
                            </p>
                        </div>
                    </div>

                    <div id="whitelistIpErrors" style="display:none;" class="border border-[#FF0000] text-sm text-[#FF0000] py-1 px-4 bg-[#FFE9E9] mb-4"></div>
                    <div id="whitelistIpMessage" style="display:none;" class="border border-[#2EA135] text-sm text-[#2EA135] py-1 px-4 bg-[#E9FFEB] mb-4"></div>

                    <form id="addWhitelistIpForm" class="flex flex-wrap items-end gap-3 mb-6">
                        <div>
                            <label class="form-custom-label">IP / CIDR <span class="text-red-500">*</span></label>
                            <input type="text" id="wlIpInput" class="form-custom-input" placeholder="e.g. 203.0.113.45 or 203.0.113.0/24" required>
                        </div>
                        <div>
                            <label class="form-custom-label">Label <span class="text-red-500">*</span></label>
                            <input type="text" id="wlLabelInput" class="form-custom-input" placeholder="e.g. Jane - QA testing" required>
                        </div>
                        <button type="submit" id="wlSubmitBtn" class="btn-primary-small transition-opacity duration-150">
                            <i class="fas fa-plus mr-2" id="wlSubmitIcon"></i><span id="wlSubmitLabel">Whitelist IP</span>
                        </button>
                    </form>

                    <div class="w-full overflow-y-auto">
                        <table class="table table-bordered table-hover w-full border-collapse">
                            <thead>
                                <tr>
                                    <th class="th-custom-css">IP / CIDR</th>
                                    <th class="th-custom-css">Label</th>
                                    <th class="th-custom-css">Expires</th>
                                    <th class="th-custom-css" width="10%">Action</th>
                                </tr>
                            </thead>
                            <tbody id="whitelistIpTableBody">
                                <tr><td colspan="5" class="text-center text-gray-400 py-6">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-custom')
<script>
@php $routePrefix = routePrefix(); @endphp
const wlIndexUrl           = "{{ route($routePrefix . '.octo-whitelisted-ips.index') }}";
const wlStoreUrl           = "{{ route($routePrefix . '.octo-whitelisted-ips.store') }}";
const wlDestroyUrlTemplate = "{{ route($routePrefix . '.octo-whitelisted-ips.destroy', ['octoWhitelistedIp' => '__ID__']) }}";
const wlCsrfToken          = $('meta[name="csrf-token"]').attr('content');
const wlAppTimezone        = "{{ config('app.timezone') }}";

function wlEscape(str) {
    return $('<div>').text(str == null ? '' : str).html();
}

function wlFormatExpiry(value) {
    if (!value) return '&mdash;';
    const date = new Date(value);
    if (isNaN(date)) return wlEscape(value);

    // Always render in the app's configured timezone, not the viewer's browser
    // timezone, so every admin sees the same expiry time regardless of location.
    return wlEscape(date.toLocaleString('en-US', {
        timeZone: wlAppTimezone,
        year: 'numeric', month: 'short', day: 'numeric',
        hour: 'numeric', minute: '2-digit', hour12: true,
        timeZoneName: 'short',
    }));
}

function wlRowHtml(row) {
    return `
        <tr data-row-id="${row.id}">
            <td class="th-custom-css">${wlEscape(row.ip)}</td>
            <td class="th-custom-css">${wlEscape(row.label)}</td>
            <td class="th-custom-css">${wlFormatExpiry(row.expires_at)}</td>
            <td class="th-custom-css">
                <button type="button" class="btn-danger-small btn-remove-wl-ip" data-id="${row.id}"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `;
}

function loadWhitelist() {
    $.get(wlIndexUrl, function (rows) {
        if (!rows.length) {
            $('#whitelistIpTableBody').html('<tr><td colspan="5" class="text-center text-gray-400 py-6">No IPs whitelisted yet.</td></tr>');
            return;
        }

        $('#whitelistIpTableBody').html(rows.map(wlRowHtml).join(''));
    }).fail(function () {
        $('#whitelistIpTableBody').html('<tr><td colspan="5" class="text-center text-red-500 py-6">Failed to load.</td></tr>');
    });
}

$(document).ready(function () {
    loadWhitelist();

    $('#addWhitelistIpForm').submit(function (e) {
        e.preventDefault();
        $('#whitelistIpErrors, #whitelistIpMessage').fadeOut(150);

        const $btn = $('#wlSubmitBtn');
        $btn.prop('disabled', true).css('opacity', 0.6);
        $('#wlSubmitIcon').removeClass('fa-plus').addClass('fa-spinner fa-spin');
        $('#wlSubmitLabel').text('Whitelisting...');

        $.ajax({
            type: 'POST',
            url: wlStoreUrl,
            headers: { 'X-CSRF-TOKEN': wlCsrfToken },
            data: {
                ip: $('#wlIpInput').val().trim(),
                label: $('#wlLabelInput').val().trim(),
            },
            success: function (entry) {
                $('#whitelistIpMessage')
                    .html(`IP whitelisted — expires ${wlFormatExpiry(entry.expires_at)} (48 hours from now).`)
                    .hide().fadeIn(200);
                $('#addWhitelistIpForm')[0].reset();

                // Update the table from the response directly — no need to
                // re-fetch the whole list for a single row we already have.
                $('#whitelistIpTableBody tr[data-row-id="' + entry.id + '"]').remove();
                $('#whitelistIpTableBody tr:not([data-row-id])').remove();
                $(wlRowHtml(entry)).hide().prependTo('#whitelistIpTableBody').fadeIn(200);
            },
            error: function (xhr) {
                const errors = xhr.responseJSON && xhr.responseJSON.errors;
                const msg = (errors && Object.values(errors)[0][0])
                    || (xhr.responseJSON && (xhr.responseJSON.message || xhr.responseJSON.error))
                    || 'Failed to whitelist IP.';
                $('#whitelistIpErrors').html(msg).hide().fadeIn(200);
            },
        }).always(function () {
            $btn.prop('disabled', false).css('opacity', 1);
            $('#wlSubmitIcon').removeClass('fa-spinner fa-spin').addClass('fa-plus');
            $('#wlSubmitLabel').text('Whitelist IP');
        });
    });

    $(document).on('click', '.btn-remove-wl-ip', function () {
        const id = $(this).data('id');
        const $row = $(this).closest('tr');

        swal({
            title: 'Remove this whitelisted IP?',
            text: 'It will no longer be permitted to call the OCTO API.',
            icon: 'warning',
            buttons: {
                no: { text: 'Cancel', value: false },
                yes: { text: 'Remove', className: 'btn-danger' },
            },
        }).then(function (confirmed) {
            if (!confirmed) return;

            $('#whitelistIpErrors, #whitelistIpMessage').fadeOut(150);

            // Optimistic removal — don't wait on the network round trip before
            // the table reflects it. Only resync from the server if it actually fails.
            $row.fadeOut(150, function () {
                $(this).remove();
                if (!$('#whitelistIpTableBody tr[data-row-id]').length) {
                    $('#whitelistIpTableBody').html('<tr><td colspan="5" class="text-center text-gray-400 py-6">No IPs whitelisted yet.</td></tr>');
                }
            });

            $.ajax({
                type: 'DELETE',
                url: wlDestroyUrlTemplate.replace('__ID__', id),
                headers: { 'X-CSRF-TOKEN': wlCsrfToken },
                error: function () {
                    $('#whitelistIpErrors').show().text('Failed to remove IP — refreshing the list.');
                    loadWhitelist();
                },
            });
        });
    });
});
</script>
@endsection
