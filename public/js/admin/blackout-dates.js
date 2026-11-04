$(function () {
    // Initialize main "from" datepicker
    $("#txt-from_date").datepicker({
        dateFormat: "mm/dd/yy",
        onClose: function () {
            const fromDate = $(this).datepicker('getDate');
            if (fromDate) {
                const toDate = new Date(fromDate);
                toDate.setDate(toDate.getDate() + 7);
                $("#txt-to_date").datepicker("setDate", toDate);
            }
        }
    });

    // Initialize main "to" datepicker
    $("#txt-to_date").datepicker({
        dateFormat: "mm/dd/yy"
    });

    // Modal event binding
    $(document).on('shown.bs.modal', '#editBlackoutDate', function (event) {
        const button = $(event.relatedTarget);
        const modal = $(this);

        // Extract data attributes
        const description = button.data('description');
        const fromDate = button.data('from_date');
        const toDate = button.data('to_date');
        const id = button.data('id');

        // Populate modal fields
        modal.find('#description-modal').val(description);
        modal.find('#txt-from_date-modal').val(fromDate);
        modal.find('#txt-to_date-modal').val(toDate);
        modal.find('#blackout-date-id').val(id);

        // Re-initialize datepickers in modal (destroy first to avoid duplicate init)
        modal.find('#txt-from_date-modal, #txt-to_date-modal').datepicker('destroy').datepicker({
            dateFormat: "mm/dd/yy"
        });
    });

    // Blackout date form show loading
    $('#blackoutDateForm').on('submit', function () {
        showLoading();
    });


    // Blackout date edit form show loading
    $('#editBlackoutDateForm').on('submit', function () {
        showLoading();
    });


});
