// broadcast.js

class Broadcast {
    /**
     * Broadcast a message using the pusher method
     * @param {*} props 
     */
    constructor(props) {
        this.broadcastingChannel = window.listener.channel('bookings-request');

        // Handle the real-time message
        this.broadcastingChannel.listen('.booking-request-notification', (data) => {
            // console.log(data.bookingRequest);
            // Notify to the receiver
            if (data?.bookingRequest?.manager_id == props?.userId && data?.tenant?.tenant_id == props?.tenantId) {
                // Get the notification count element
                const notificationCountElement = document.getElementById('notification-count');

                // Parse the current count and increment it
                let currentCount = parseInt(notificationCountElement.textContent) || 0;
                notificationCountElement.textContent = currentCount + 1;

                // Message for the notification
                let message = 'You have a new request to ' + data.bookingRequest.request_type + ' the booking.';
                this.notify(message);
            }

            // Notify to the sender
            if (data?.bookingRequest?.staff_id == props?.userId && data?.tenant?.tenant_id == props?.tenantId) {
                // Message for the notification
                let message = 'Your ' + data.bookingRequest.request_type + ' booking (ID:' + data.bookingRequest.tour_booking_id + ') request has been ' + data.bookingRequest.status + '.';
                this.notify(message);
            }
        });
    }

    /**
     * Notify the user that the booking request has been sent or deny or approved.
     * @param {*} message 
     */
    notify(message) {
        $.notify({
            // Notification content
            title: '<strong style="color: #055160; font-size: 16px;">Information!</strong>',
            message: '<br><span style="color: #055160; font-size: 16px;">'+message+'</span>',
            icon: 'glyphicon glyphicon-info-sign'
        }, {
            // Notification settings
            element: 'body',
            type: "info", // Changed to "info" for information style
            allow_dismiss: true,
            showProgressbar: false,
            placement: {
                from: "top",
                align: "right"
            },
            offset: 20,
            spacing: 10,
            z_index: 1031,
            delay: 3300,
            timer: 1000,
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutRight'
            },
            template: `
            <div data-notify="container" class="col-xs-11 col-sm-4 alert alert-info" role="alert" style="background-color: #d1ecf1; border-color: #bee5eb; color: #055160;">
                <button type="button" aria-hidden="true" class="close" data-notify="dismiss" style="color: #055160;">×</button>
                <span data-notify="icon"></span>
                <span data-notify="title" style="font-weight: bold;">{1}</span>
                <span data-notify="message">{2}</span>
            </div>`
        });
    }
}
