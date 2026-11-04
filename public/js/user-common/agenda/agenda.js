class Agenda {
    constructor(from, to, packageId, customerId) {
        this.from = moment(from);
        this.to = moment(to);
        this.packageId = packageId;
        this.customerId = customerId;
        this.initDateRangePicker();

        $(document).off('change', '#packages').on('change', '#packages', (evt) => {
            this.packageId = evt.target.value;
        });

        $(document).off('change', '#customers').on('change', '#customers', (evt) => {
            this.customerId = evt.target.value;
        });

        $(document).on('click', '#filter-submit', (evt) => {            
            this.updateDynamicUrlSegments({
                fromDate: this.from,
                toDate: this.to,
                tourPackageId: parseInt(this.packageId),
                tourCustomerId: parseInt(this.customerId)
            });

            // Navigate to the new URL with the updated parameters
            let currentRouteInput = $('#filter-current-route').val();

            window.location.href = currentRouteInput;
        });

        $(document).on('click', '#filter-clear', (evt) => {            
            this.updateDynamicUrlSegments({
                fromDate: moment().format('YYYYMMDD'),
                toDate: moment().format('YYYYMMDD'),
                tourPackageId: 0,
                tourCustomerId: 0
            });

            // Navigate to the new URL with the updated parameters
            let currentRouteInput = $('#filter-current-route').val();

            window.location.href = currentRouteInput;
        });
    }

    /**
     * Update route param data for filter.
     */
    updateDynamicUrlSegments(newSegments) {
        // Get the hidden input elements
        let currentRouteInput = document.getElementById('filter-current-route');
        let filterRouteInput = document.getElementById('filter-updated-route');

        // Get the current URL from the filterRouteInput's value
        let currentUrl = new URL(filterRouteInput.value, window.location.origin);

        // Break down the pathname into segments
        let pathSegments = currentUrl.pathname.split('/');

        // Assuming the segments are in the positions you mentioned:
        // /admin/tour-operator/agenda/{from}/{to}/{packageId}/{customerId}
        let agendaIndex = pathSegments.indexOf('agenda');

        if (agendaIndex !== -1) {
            // Update the relevant segments with the new values
            if (newSegments.fromDate) pathSegments[agendaIndex + 1] = newSegments.fromDate;
            if (newSegments.toDate) pathSegments[agendaIndex + 2] = newSegments.toDate;
            if (typeof newSegments.tourPackageId === 'number') pathSegments[agendaIndex + 3] = newSegments.tourPackageId;
            if (typeof newSegments.tourCustomerId === 'number') pathSegments[agendaIndex + 4] = newSegments.tourCustomerId;

            // Join the segments back together
            currentUrl.pathname = pathSegments.join('/');

            // Update the hidden input's value with the new URL
            currentRouteInput.value = currentUrl.toString();
        } else {
            console.error("The 'agenda' segment was not found in the URL path.");
        }
    }

    /**
     * Initializes the date range picker.
     */
    initDateRangePicker() {
        const setStartAndEndDate = (from, to) => {
            // Update the class properties
            this.from = moment(from).format('YYYYMMDD');
            this.to = moment(to).format('YYYYMMDD');

            $('#agendadaterange span').html(from.format('MMMM D, YYYY') + ' - ' + to.format('MMMM D, YYYY'));
        }

        $('#agendadaterange').daterangepicker({
            startDate: this.from,
            endDate: this.to,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, setStartAndEndDate);

        setStartAndEndDate(this.from, this.to);
    }
}
