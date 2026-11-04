/**
 * CalendarSocketModule
 *
 * Handles all real-time calendar events on the tenant channel.
 * Registered for two features so SocketManager routes both to this module:
 *
 *   'calendar' — seat grid updates  (Calendar\SeatsUpdated events)
 *   'booking'  — booking list updates (Booking\BookingCreated etc. events)
 *
 * Events handled:
 *   calendar.seats.created           → refresh calendar grid cell (new booking)
 *   calendar.seats.cancelled         → refresh calendar grid cell (cancellation)
 *   calendar.seats.affiliate-updated → refresh calendar grid cell (affiliate change)
 *   booking.booking.created          → add/update booking in calApp.tourBookings
 *   booking.booking.cancelled        → remove booking from calApp.tourBookings
 *   booking.booking.affiliate-updated→ upsert booking in calApp.tourBookings
 */
class CalendarSocketModule {
    features = ['calendar', 'booking'];

    events = [
        // Seat grid (calendar cell) updates
        'calendar.seats.created',
        'calendar.seats.cancelled',
        'calendar.seats.affiliate-updated',
        // Booking list (Vue tourBookings array) updates
        'booking.booking.created',
        'booking.booking.cancelled',
        'booking.booking.affiliate-updated',
    ];

    /**
     * Called by SocketManager for any registered feature event.
     *
     * @param {string} entity     'seats' | 'booking'
     * @param {string} operation  'created' | 'cancelled' | 'affiliate-updated'
     * @param {Object} payload
     */
    handle(entity, operation, payload) {
        if (entity === 'seats') {
            this._handleSeatsUpdate(operation, payload);
        } else if (entity === 'booking') {
            this._handleBookingListUpdate(operation, payload);
        }
    }

    // -------------------------------------------------------------------------
    // Seat grid update — single server refresh via calApp.handleSelectChange()
    // -------------------------------------------------------------------------

    _handleSeatsUpdate(_operation, _payload) {
        if (!window.calApp || typeof window.calApp.handleSelectChange !== 'function') return;

        // Suppress the global loading overlay for socket-triggered refreshes so the
        // spinner does not interrupt user interactions (e.g. open modals, form inputs).
        // showLoading() is called synchronously inside handleSelectChange() before the
        // async POST, so we can swap it out and restore it in the same tick.
        const originalShowLoading = window.showLoading;
        window.showLoading = () => {};

        window.calApp.handleSelectChange();

        window.showLoading = originalShowLoading;
    }

    // -------------------------------------------------------------------------
    // Booking list update — keeps calApp.tourBookings in sync
    // -------------------------------------------------------------------------

    _handleBookingListUpdate(operation, payload) {
        if (!window.calApp) return;
        if (!this._passesFilters(payload)) return;

        switch (operation) {
            case 'created':
            case 'affiliate-updated':
                this._upsertBooking(payload);
                break;
            case 'cancelled':
                this._removeBooking(payload);
                break;
        }
    }

    /**
     * Replicate the filtering conditions that the old eventBooking* functions applied:
     *   - Same domain as the current page
     *   - Same environment (local/staging/production)
     *   - Admin: only show bookings for the selected tour operator (or 0 = all)
     *   - Affiliate: only show their own bookings (loggedInaffiliateId is "" for non-affiliates)
     */
    _passesFilters(payload) {
        if (payload.domain && window.calApp.domain !== payload.domain) return false;
        if (payload.env && payload.env !== window.environment) return false;

        if (window.userType === 'Admin' &&
            window.calApp.tourOperator != 0 &&
            window.calApp.tourOperator != payload.tour_operator_id) return false;

        if (window.loggedInaffiliateId &&
            window.loggedInaffiliateId != payload.cust_id) return false;

        return true;
    }

    _upsertBooking(booking) {
        const idx = window.calApp.tourBookings.findIndex((b) => b.id === booking.id);
        if (idx !== -1) {
            window.calApp.$set(window.calApp.tourBookings, idx, booking);
        } else {
            window.calApp.tourBookings.unshift(booking);
        }
    }

    _removeBooking(booking) {
        const idx = window.calApp.tourBookings.findIndex((b) => b.id === booking.id);
        if (idx !== -1) {
            window.calApp.tourBookings.splice(idx, 1);
        }
    }

}

export default CalendarSocketModule;
