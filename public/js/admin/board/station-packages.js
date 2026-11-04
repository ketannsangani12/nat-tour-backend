$(function () {

    // Select / Deselect All
    $('#selectAllPackages').on('click', () => $('.pkg-checkbox').prop('checked', true));
    $('#deselectAllPackages').on('click', () => $('.pkg-checkbox').prop('checked', false));

    const modal = new bootstrap.Modal(document.getElementById('boardStationPackageModal'));

    // Reset Modal
    function resetForm() {
        $('#recordId').val('');
        $('#packageName').val('');
        $('.pkg-checkbox').prop('checked', false);
        $('#boardStationPackageModalLabel').text('Add Parent Package');
    }

    // Add New
    $('[data-bs-target="#boardStationPackageModal"]').on('click', resetForm);

    // Submit Form (Add / Update)
    $('#boardStationPackageForm').on('submit', function (e) {
        e.preventDefault();

        const id = $('#recordId').val();
        const url = id
            ? updateStationPackages + id
            : storeStationPackages;
        const method = 'POST';
        showLoading();

        $.ajax({
            url,
            type: method,
            data: $(this).serialize(),
            success: function (res) {
                hideLoading();

                if (!res.success) return Swal.fire('Error', res.message, 'error');

                modal.hide();
                Swal.fire('Success', res.message, 'success');

                // Refresh board data after successful add/update
                if (typeof loadBoardData === 'function') {
                    loadBoardData();
                }

                if (id) {
                    // Update existing row
                    const row = $('tr[data-id="' + id + '"]');
                    row.find('.pkg-name').text(res.data.name);

                    // Build badges for assigned packages
                    const badges = (res.data.assigned_names && res.data.assigned_names.length)
                        ? res.data.assigned_names.map(name => `<span class="badge bg-info me-1 mb-1">${name}</span>`).join('')
                        : '<span class="text-muted">-</span>';

                    row.find('.pkg-assigned').html(badges);

                } else {
                    // Add new row dynamically
                    const assignedBadges = (res.data.assigned_names && res.data.assigned_names.length)
                        ? res.data.assigned_names.map(name => `<span class="badge bg-info me-1 mb-1">${name}</span>`).join('')
                        : '<span class="text-muted">-</span>';

                    const newRow = `
                        <tr data-id="${res.data.id}">
                            <td>${$('#boardStationPackagesTable tbody tr').length + 1}</td>
                            <td class="pkg-name">${res.data.name}</td>
                            <td class="pkg-assigned">${assignedBadges}</td>
                            <td>
                                <button class="btn btn-warning btn-sm editRecord"><i class="fa fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm deleteRecord"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>`;

                    $('#boardStationPackagesTable tbody').append(newRow);
                }
            },
            error: () => {
                hideLoading();
                Swal.fire('Error', 'Something went wrong!', 'error');
            }
        });
    });

    // Edit
    $(document).on('click', '.editRecord', function () {
        const id = $(this).closest('tr').data('id');
        showLoading();
        $.get(showStationPackages + id, function (res) {
            if (res.success) {
                // Refresh board data after successful add/update
                if (typeof loadBoardData === 'function') {
                    loadBoardData();
                }
                const data = res.data;
                $('#recordId').val(data.id);
                $('#packageName').val(data.name);
                $('.pkg-checkbox').prop('checked', false);
                (data.assign_packages || []).forEach(i => $('#pkg' + i).prop('checked', true));
                $('#boardStationPackageModalLabel').text('Edit Parent Package');
                hideLoading();
                modal.show();
            }
        });
    });

    // Delete
    $(document).on('click', '.deleteRecord', function () {
        const row = $(this).closest('tr');
        const id = row.data('id');

        Swal.fire({
            title: 'Delete this record?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) {
                showLoading();
                $.ajax({
                    url: deleteStationPackages + id,
                    type: 'DELETE',
                    data: { _token: csrfToken },
                    success: function (res) {
                        hideLoading();
                        if (res.success) {
                            // Refresh board data after successful add/update
                            if (typeof loadBoardData === 'function') {
                                loadBoardData();
                            }
                            row.remove();
                            Swal.fire('Deleted!', res.message, 'success');
                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }
                    }
                });
            }
        });
    });
});