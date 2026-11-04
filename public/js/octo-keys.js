var OctoKeyManager = (function () {

    var tenantId  = null;
    var baseUrl   = null;
    var csrfToken = null;

    function init(config) {
        baseUrl   = config.baseUrl;
        csrfToken = config.csrfToken;
        bindEvents();
    }

    function bindEvents() {
        $(document).on('click', '.btn-ota-keys',   onOpenModal);
        $(document).on('click', '.btn-revoke-key', onRevokeKey);
        $(document).on('click', '.btn-delete-key', onDeleteKey);

        $('#btnShowIssueForm').on('click', function () {
            $('#otaResellerInput').val('');
            $('#otaKeyPreview').text('...');
            showPanel('issue');
        });

        // Back / Cancel buttons on the issue form
        $('#btnCancelIssue, #btnCancelIssue2').on('click', function () { showPanel('list'); });

        // Live preview of the key name as the user types
        $('#otaResellerInput').on('input', function () {
            var slug = toResellerSlug($(this).val()) || '...';
            $('#otaKeyPreview').text('...-' + slug + '-test');
        });

        $('#btnGenerateKey').on('click',  onGenerateKey);
        $('#btnCopyKey').on('click',      onCopyKey);
        $('#btnRevealDone').on('click',   onRevealDone);

        $('#otaCopiedCheck').on('change', function () {
            var checked = $(this).is(':checked');
            $('#btnRevealDone').prop('disabled', !checked);
            $('#btnRevealDone').toggleClass('opacity-50 cursor-not-allowed', !checked);
        });

        // Close buttons — header X, footer Cancel, and clicking outside the modal
        $('#otaModalCloseBtn, #otaFooterCloseBtn').on('click', tryClose);

        $('#otaKeysModal').on('click', function (e) {
            if (!$(e.target).closest('#otaModalContent').length) {
                tryClose();
            }
        });
    }

    // ── Open / close ──────────────────────────────────────────────────────────

    function openModal() {
        $('#otaKeysModal').removeClass('hidden');
        $('body').addClass('overflow-hidden');
    }

    function closeModal() {
        $('#otaKeysModal').addClass('hidden');
        $('body').removeClass('overflow-hidden');
    }

    function tryClose() {
        if ($('#otaPanel-reveal').is(':visible') && !$('#otaCopiedCheck').is(':checked')) {
            swal('Key not saved', 'Please copy the key and tick the confirmation box before closing.', 'warning');
            return;
        }
        closeModal();
    }

    function onOpenModal() {
        tenantId = $(this).data('tenant-id');
        var tenantName = $.trim($(this).data('tenant-name'));
        var label = 'OTA Keys — ' + tenantId + (tenantName ? ' | ' + tenantName : '');
        $('#otaKeysModalLabel').text(label);
        showPanel('list');
        loadKeys();
        openModal();
    }

    // ── Load key list ─────────────────────────────────────────────────────────

    function loadKeys() {
        $('#otaKeysList').html(
            '<p class="text-center text-gray-400 py-8"><i class="fas fa-spinner fa-spin mr-2"></i>Loading...</p>'
        );

        $.get(baseUrl + '/' + tenantId + '/ota-keys')
            .done(function (keys) {
                if (!keys.length) {
                    $('#otaKeysList').html(
                        '<div class="text-center py-10 text-gray-400">' +
                        '<i class="fas fa-key text-3xl mb-3 block"></i>' +
                        '<p class="text-sm">No keys issued yet for this tenant.</p></div>'
                    );
                    return;
                }

                var rows = keys.map(function (k) {
                    var badge = k.is_active
                        ? '<span class="ota-badge-active">Active</span>'
                        : '<span class="ota-badge-revoked">Revoked</span>';
                    var action = k.is_active
                        ? '<button class="btn-danger-small btn-revoke-key" data-id="' + k.id + '"><i class="fas fa-ban mr-1"></i>Revoke</button>'
                        : '<button class="btn-danger-small btn-delete-key" data-id="' + k.id + '"><i class="fas fa-trash mr-1"></i>Delete</button>';
                    return '<tr class="border-b border-[#F3F4F6] hover:bg-gray-50">' +
                        '<td class="px-4 py-3 text-sm font-medium text-gray-800">' + escHtml(k.reseller) + '</td>' +
                        '<td class="px-4 py-3"><code class="text-xs bg-gray-100 px-2 py-1 rounded text-gray-700 font-mono">' + escHtml(k.masked_key) + '</code></td>' +
                        '<td class="px-4 py-3">' + badge + '</td>' +
                        '<td class="px-4 py-3 text-sm text-gray-500">' + escHtml(k.created_at) + '</td>' +
                        '<td class="px-4 py-3">' + action + '</td>' +
                        '</tr>';
                });

                $('#otaKeysList').html(
                    '<div class="overflow-x-auto rounded-lg border border-[#E5E7EB]">' +
                    '<table class="w-full text-left text-sm">' +
                    '<thead><tr class="bg-gray-50 border-b border-[#E5E7EB]">' +
                    '<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Reseller</th>' +
                    '<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Key (masked)</th>' +
                    '<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>' +
                    '<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Issued</th>' +
                    '<th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Action</th>' +
                    '</tr></thead>' +
                    '<tbody>' + rows.join('') + '</tbody>' +
                    '</table></div>'
                );
            })
            .fail(function () {
                $('#otaKeysList').html(
                    '<p class="text-center text-red-500 py-6"><i class="fas fa-exclamation-circle mr-2"></i>Failed to load keys.</p>'
                );
            });
    }

    // ── Revoke key ────────────────────────────────────────────────────────────

    function onRevokeKey() {
        var id = $(this).data('id');
        swal({
            title: 'Revoke this key?',
            text: 'The reseller will lose API access immediately. You can issue a new key afterwards.',
            icon: 'warning',
            buttons: { cancel: 'Cancel', confirm: { text: 'Yes, Revoke', className: 'btn-danger' } },
            dangerMode: true,
        }).then(function (confirmed) {
            if (!confirmed) return;
            showLoading();
            $.post(baseUrl + '/' + tenantId + '/ota-keys/' + id + '/revoke', { _token: csrfToken })
                .done(function () { hideLoading(); loadKeys(); })
                .fail(function () { hideLoading(); swal('Error', 'Could not revoke key.', 'error'); });
        });
    }

    // ── Delete key ────────────────────────────────────────────────────────────

    function onDeleteKey() {
        var id = $(this).data('id');
        swal({
            title: 'Delete this record?',
            text: 'The revoked key record will be permanently removed.',
            icon: 'warning',
            buttons: { cancel: 'Cancel', confirm: { text: 'Yes, Delete', className: 'btn-danger' } },
            dangerMode: true,
        }).then(function (confirmed) {
            if (!confirmed) return;
            showLoading();
            $.ajax({
                type: 'POST',
                url: baseUrl + '/' + tenantId + '/ota-keys/' + id,
                data: { _token: csrfToken, _method: 'DELETE' },
            })
            .done(function () { hideLoading(); loadKeys(); })
            .fail(function () { hideLoading(); swal('Error', 'Could not delete key.', 'error'); });
        });
    }

    // ── Generate key ──────────────────────────────────────────────────────────

    function onGenerateKey() {
        var reseller = $.trim($('#otaResellerInput').val());
        if (!reseller) {
            swal('Required', 'Please enter a reseller name.', 'warning');
            return;
        }
        if (/[^a-zA-Z0-9\s]/.test(reseller)) {
            swal('Invalid Name', 'Reseller name may only contain letters, numbers and spaces — no hyphens or underscores.', 'warning');
            return;
        }

        showLoading();
        $.post(baseUrl + '/' + tenantId + '/ota-keys', { _token: csrfToken, reseller: reseller })
            .done(function (res) {
                hideLoading();
                $('#otaRevealedKey').val(res.key);
                $('#otaCopiedCheck').prop('checked', false);
                $('#btnRevealDone').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                $('#btnCopyKey').html('<i class="fas fa-copy mr-1"></i> Copy');
                showPanel('reveal');
            })
            .fail(function (xhr) {
                hideLoading();
                var msg = (xhr.responseJSON && xhr.responseJSON.errors)
                    ? Object.values(xhr.responseJSON.errors).flat().join(' ')
                    : 'Failed to generate key.';
                swal('Error', msg, 'error');
            });
    }

    // ── Copy key ──────────────────────────────────────────────────────────────

    function onCopyKey() {
        var key = $('#otaRevealedKey').val();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(key).then(function () {
                $('#btnCopyKey').html('<i class="fas fa-check mr-1"></i> Copied!');
                setTimeout(function () { $('#btnCopyKey').html('<i class="fas fa-copy mr-1"></i> Copy'); }, 2000);
            });
        } else {
            $('#otaRevealedKey').select();
            document.execCommand('copy');
            $('#btnCopyKey').html('<i class="fas fa-check mr-1"></i> Copied!');
        }
    }

    // ── Reveal done ───────────────────────────────────────────────────────────

    function onRevealDone() {
        showPanel('list');
        loadKeys();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function showPanel(name) {
        $('#otaPanel-list, #otaPanel-issue, #otaPanel-reveal').hide();
        $('#otaPanel-' + name).show();
    }

    function toResellerSlug(name) {
        return name.trim().toLowerCase()
            .replace(/[\s\-_]+/g, '-')
            .replace(/[^a-z0-9\-]/g, '')
            .replace(/^\-+|\-+$/g, '');
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    return { init: init };

}());
