/**
 * BoardSocketModule
 *
 * Handles real-time board events on the tenant channel.
 *
 * Events handled:
 *   board.guide-management.updated → refresh Guide Management modal (if open)
 *                                   or silently refresh board slot cards
 */
class BoardSocketModule {
    feature = 'board';

    events = ['board.guide-management.updated'];

    /**
     * Called by SocketManager for any registered feature event.
     *
     * @param {string} entity    'guide-management'
     * @param {string} operation 'updated'
     * @param {Object} payload   { date, time_from }
     */
    handle(entity, operation, payload) {
        if (entity === 'guide-management' && operation === 'updated') {
            this._handleBoardUpdate(payload);
        }
    }

    _handleBoardUpdate(payload) {
        const eventDate = payload.date;
        const eventTime = payload.time_from;

        const modalEl    = document.getElementById('guideManagementModal');
        const isModalOpen = modalEl && modalEl.classList.contains('show');

        if (isModalOpen && eventDate === currentDate && eventTime === currentTimeFrom) {
            // Suppress re-render while a booking-checked POST is in-flight
            // to prevent the checkbox flickering back to its old state.
            if (!window._boardCheckedInFlight) {
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate, true);
            }
        } else {
            const activeBox = document.querySelector('.day-box.active');
            if (activeBox && activeBox.dataset.date === eventDate) {
                window.loadBoardData(eventDate, eventTime, true);
            }
        }
    }
}

export default BoardSocketModule;
