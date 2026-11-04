$(document).ready(function () {
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        localStorage.setItem('activeReleaseNoteTab', $(e.target).attr('data-bs-target'));
    });

    var activeTab = localStorage.getItem('activeReleaseNoteTab');
    if (activeTab) {
        $('button[data-bs-target="' + activeTab + '"]').tab('show');
    }
});

$(function () {
    // Select/Deselect all checkboxes per group
    $('.select-all').on('change', function () {
        let group = $(this).data('group');
        let checked = $(this).prop('checked');
        $('.child-checkbox[data-group="' + group + '"]').prop('checked', checked).trigger('change');
    });

    // Update select-all checkbox and total count badge when a child toggled
    $('.child-checkbox').on('change', function () {
        let group = $(this).data('group');

        // update group select-all
        let total = $('.child-checkbox[data-group="' + group + '"]').length;
        let checkedCount = $('.child-checkbox[data-group="' + group + '"]:checked').length;
        $('.select-all[data-group="' + group + '"]').prop('checked', total === checkedCount);

        // find tenant ID of this checkbox
        let tenantId = $(this).data('tenant-id');

        // count total selected checkboxes for this tenant
        let tenantSelectedCount = $('.child-checkbox[data-tenant-id="' + tenantId + '"]:checked').length;

        // update the badge count for this tenant
        $('.total-selected-count[data-tenant-id="' + tenantId + '"]').text(tenantSelectedCount);
    });

    // Send Notifications per tenant
    $('.send-notification-btn').on('click', function () {
        var tenantId = $(this).data('tenant-id');
        var userIds = {};

        $('.child-checkbox[data-tenant-id="' + tenantId + '"]:checked').each(function () {
            var userType = $(this).data('user-type');
            var userId = $(this).val();

            if (!userIds[userType]) {
                userIds[userType] = [];
            }

            userIds[userType].push(userId);
        });

        if ($.isEmptyObject(userIds)) {
            swal('Error!', 'Please select at least one user to send notification.', 'error');
            return;
        }

        // Build final data object with tenant id as key
        var finalUserIds = {};
        finalUserIds[tenantId] = userIds;

        swal({
            title: 'Are you sure?',
            text: "Notify selected tenant users by email?",
            icon: 'warning',
            buttons: {
                no: {
                    text: 'No',
                    value: false,
                    className: 'btn-danger',
                },
                yes: {
                    text: 'Yes, send them!',
                    value: true,
                }
            }
        }).then((result) => {
            if (result) {
                $.ajax({
                    url: sendNotificationUrl,
                    method: 'POST',
                    data: {
                        user_ids: finalUserIds,
                        tenant_id: tenantId,
                        from: 'notall',
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        swal('Success!', response.message, 'success');
                    },
                    error: function (xhr) {
                        console.error(xhr.responseText);
                        swal('Error!', 'Something went wrong while sending notifications.', 'error');
                    }
                });
            }
        });
    });
});
