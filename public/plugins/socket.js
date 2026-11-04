/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/js/socket/SocketManager.js":
/*!**********************************************!*\
  !*** ./resources/js/socket/SocketManager.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
/**
 * SocketManager
 *
 * Central hub for all real-time WebSocket communication.
 * Sits between Laravel Echo (window.listener) and feature modules.
 *
 * Usage (per page):
 *
 *   // 1. Register the feature module(s) needed on this page
 *   SocketManager.register(new CalendarSocketModule());
 *
 *   // 2. Subscribe to the tenant channel once
 *   SocketManager.subscribe('tenant.ENCRYPTED_ID');
 *
 *   // 3. Teardown when leaving (optional, e.g. SPA navigation)
 *   SocketManager.unsubscribe('tenant.ENCRYPTED_ID');
 *
 * Adding a new feature:
 *   - Create a module class implementing { feature, events, handle(entity, operation, payload) }
 *   - Register it before calling subscribe()
 *   - No changes needed here
 */
var SocketManager = /*#__PURE__*/function () {
  function SocketManager() {
    _classCallCheck(this, SocketManager);
    /** @type {Object.<string, Object>} feature name → module instance */
    this._modules = {};

    /** @type {Object.<string, Object>} channelName → Echo channel */
    this._channels = {};
  }

  /**
   * Register a feature module.
   * Must be called before subscribe() for the module to receive events.
   *
   * A module may handle one or more features:
   *   - Single feature:   { feature: 'calendar', events: [...], handle() }
   *   - Multiple features: { features: ['calendar', 'booking'], events: [...], handle() }
   *
   * @param {Object} module
   */
  return _createClass(SocketManager, [{
    key: "register",
    value: function register(module) {
      var _this = this;
      var features = Array.isArray(module.features) ? module.features : module.feature ? [module.feature] : null;
      if (!features || !Array.isArray(module.events)) {
        console.error('[SocketManager] Module must define feature/features and events', module);
        return;
      }
      features.forEach(function (f) {
        _this._modules[f] = module;
      });
    }

    /**
     * Subscribe to a channel and wire up all registered module event listeners.
     * Safe to call multiple times — reuses an existing channel subscription.
     *
     * @param {string} channelName  e.g. 'tenant.ENCRYPTED_ID'
     */
  }, {
    key: "subscribe",
    value: function subscribe(channelName) {
      var _this2 = this;
      if (this._channels[channelName]) {
        return; // already subscribed
      }
      if (!window.listener) {
        console.error('[SocketManager] window.listener (Echo) is not initialised. Load pusher.js first.');
        return;
      }
      var channel = window.listener.channel(channelName);
      this._channels[channelName] = channel;
      Object.values(this._modules).forEach(function (module) {
        module.events.forEach(function (eventName) {
          channel.listen('.' + eventName, function (data) {
            _this2._dispatch(data);
          });
        });
      });
    }

    /**
     * Unsubscribe from a channel and remove its listeners.
     *
     * @param {string} channelName
     */
  }, {
    key: "unsubscribe",
    value: function unsubscribe(channelName) {
      if (!this._channels[channelName]) return;
      window.listener.leaveChannel(channelName);
      delete this._channels[channelName];
    }

    /**
     * Route an incoming event payload to the correct feature module.
     * Called internally by each channel listener.
     *
     * @param {Object} data - { feature, entity, operation, payload, timestamp }
     */
  }, {
    key: "_dispatch",
    value: function _dispatch(data) {
      var feature = data.feature,
        entity = data.entity,
        operation = data.operation,
        payload = data.payload;
      if (!feature || !entity || !operation) {
        console.warn('[SocketManager] Received malformed event (missing feature/entity/operation)', data);
        return;
      }
      var module = this._modules[feature];
      if (!module) {
        // Not registered on this page — silently ignore
        return;
      }
      try {
        module.handle(entity, operation, payload);
      } catch (err) {
        console.error("[SocketManager] Error in module \"".concat(feature, "\" handling ").concat(entity, ".").concat(operation), err);
      }
    }
  }]);
}();
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SocketManager);

/***/ }),

/***/ "./resources/js/socket/modules/BoardSocketModule.js":
/*!**********************************************************!*\
  !*** ./resources/js/socket/modules/BoardSocketModule.js ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
/**
 * BoardSocketModule
 *
 * Handles real-time board events on the tenant channel.
 *
 * Events handled:
 *   board.guide-management.updated → refresh Guide Management modal (if open)
 *                                   or silently refresh board slot cards
 */
var BoardSocketModule = /*#__PURE__*/function () {
  function BoardSocketModule() {
    _classCallCheck(this, BoardSocketModule);
    _defineProperty(this, "feature", 'board');
    _defineProperty(this, "events", ['board.guide-management.updated']);
  }
  return _createClass(BoardSocketModule, [{
    key: "handle",
    value:
    /**
     * Called by SocketManager for any registered feature event.
     *
     * @param {string} entity    'guide-management'
     * @param {string} operation 'updated'
     * @param {Object} payload   { date, time_from }
     */
    function handle(entity, operation, payload) {
      if (entity === 'guide-management' && operation === 'updated') {
        this._handleBoardUpdate(payload);
      }
    }
  }, {
    key: "_handleBoardUpdate",
    value: function _handleBoardUpdate(payload) {
      var eventDate = payload.date;
      var eventTime = payload.time_from;
      var modalEl = document.getElementById('guideManagementModal');
      var isModalOpen = modalEl && modalEl.classList.contains('show');
      if (isModalOpen && eventDate === currentDate && eventTime === currentTimeFrom) {
        // Suppress re-render while a booking-checked POST is in-flight
        // to prevent the checkbox flickering back to its old state.
        if (!window._boardCheckedInFlight) {
          loadGuideManagement(currentSlotId, currentTimeFrom, currentDate, true);
        }
      } else {
        var activeBox = document.querySelector('.day-box.active');
        if (activeBox && activeBox.dataset.date === eventDate) {
          window.loadBoardData(eventDate, eventTime, true);
        }
      }
    }
  }]);
}();
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (BoardSocketModule);

/***/ }),

/***/ "./resources/js/socket/modules/CalendarSocketModule.js":
/*!*************************************************************!*\
  !*** ./resources/js/socket/modules/CalendarSocketModule.js ***!
  \*************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
function _typeof(o) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (o) { return typeof o; } : function (o) { return o && "function" == typeof Symbol && o.constructor === Symbol && o !== Symbol.prototype ? "symbol" : typeof o; }, _typeof(o); }
function _classCallCheck(a, n) { if (!(a instanceof n)) throw new TypeError("Cannot call a class as a function"); }
function _defineProperties(e, r) { for (var t = 0; t < r.length; t++) { var o = r[t]; o.enumerable = o.enumerable || !1, o.configurable = !0, "value" in o && (o.writable = !0), Object.defineProperty(e, _toPropertyKey(o.key), o); } }
function _createClass(e, r, t) { return r && _defineProperties(e.prototype, r), t && _defineProperties(e, t), Object.defineProperty(e, "prototype", { writable: !1 }), e; }
function _defineProperty(e, r, t) { return (r = _toPropertyKey(r)) in e ? Object.defineProperty(e, r, { value: t, enumerable: !0, configurable: !0, writable: !0 }) : e[r] = t, e; }
function _toPropertyKey(t) { var i = _toPrimitive(t, "string"); return "symbol" == _typeof(i) ? i : i + ""; }
function _toPrimitive(t, r) { if ("object" != _typeof(t) || !t) return t; var e = t[Symbol.toPrimitive]; if (void 0 !== e) { var i = e.call(t, r || "default"); if ("object" != _typeof(i)) return i; throw new TypeError("@@toPrimitive must return a primitive value."); } return ("string" === r ? String : Number)(t); }
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
var CalendarSocketModule = /*#__PURE__*/function () {
  function CalendarSocketModule() {
    _classCallCheck(this, CalendarSocketModule);
    _defineProperty(this, "features", ['calendar', 'booking']);
    _defineProperty(this, "events", [
    // Seat grid (calendar cell) updates
    'calendar.seats.created', 'calendar.seats.cancelled', 'calendar.seats.affiliate-updated',
    // Booking list (Vue tourBookings array) updates
    'booking.booking.created', 'booking.booking.cancelled', 'booking.booking.affiliate-updated']);
  }
  return _createClass(CalendarSocketModule, [{
    key: "handle",
    value:
    /**
     * Called by SocketManager for any registered feature event.
     *
     * @param {string} entity     'seats' | 'booking'
     * @param {string} operation  'created' | 'cancelled' | 'affiliate-updated'
     * @param {Object} payload
     */
    function handle(entity, operation, payload) {
      if (entity === 'seats') {
        this._handleSeatsUpdate(operation, payload);
      } else if (entity === 'booking') {
        this._handleBookingListUpdate(operation, payload);
      }
    }

    // -------------------------------------------------------------------------
    // Seat grid update — single server refresh via calApp.handleSelectChange()
    // -------------------------------------------------------------------------
  }, {
    key: "_handleSeatsUpdate",
    value: function _handleSeatsUpdate(_operation, _payload) {
      if (!window.calApp || typeof window.calApp.handleSelectChange !== 'function') return;

      // Suppress the global loading overlay for socket-triggered refreshes so the
      // spinner does not interrupt user interactions (e.g. open modals, form inputs).
      // showLoading() is called synchronously inside handleSelectChange() before the
      // async POST, so we can swap it out and restore it in the same tick.
      var originalShowLoading = window.showLoading;
      window.showLoading = function () {};
      window.calApp.handleSelectChange();
      window.showLoading = originalShowLoading;
    }

    // -------------------------------------------------------------------------
    // Booking list update — keeps calApp.tourBookings in sync
    // -------------------------------------------------------------------------
  }, {
    key: "_handleBookingListUpdate",
    value: function _handleBookingListUpdate(operation, payload) {
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
  }, {
    key: "_passesFilters",
    value: function _passesFilters(payload) {
      if (payload.domain && window.calApp.domain !== payload.domain) return false;
      if (payload.env && payload.env !== window.environment) return false;
      if (window.userType === 'Admin' && window.calApp.tourOperator != 0 && window.calApp.tourOperator != payload.tour_operator_id) return false;
      if (window.loggedInaffiliateId && window.loggedInaffiliateId != payload.cust_id) return false;
      return true;
    }
  }, {
    key: "_upsertBooking",
    value: function _upsertBooking(booking) {
      var idx = window.calApp.tourBookings.findIndex(function (b) {
        return b.id === booking.id;
      });
      if (idx !== -1) {
        window.calApp.$set(window.calApp.tourBookings, idx, booking);
      } else {
        window.calApp.tourBookings.unshift(booking);
      }
    }
  }, {
    key: "_removeBooking",
    value: function _removeBooking(booking) {
      var idx = window.calApp.tourBookings.findIndex(function (b) {
        return b.id === booking.id;
      });
      if (idx !== -1) {
        window.calApp.tourBookings.splice(idx, 1);
      }
    }
  }]);
}();
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (CalendarSocketModule);

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!********************************!*\
  !*** ./resources/js/socket.js ***!
  \********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _socket_SocketManager__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./socket/SocketManager */ "./resources/js/socket/SocketManager.js");
/* harmony import */ var _socket_modules_CalendarSocketModule__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./socket/modules/CalendarSocketModule */ "./resources/js/socket/modules/CalendarSocketModule.js");
/* harmony import */ var _socket_modules_BoardSocketModule__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./socket/modules/BoardSocketModule */ "./resources/js/socket/modules/BoardSocketModule.js");
/**
 * Socket bootstrap entry point.
 *
 * Compiled by Mix → public/plugins/socket.js
 * Include this script on any page that needs real-time features,
 * AFTER pusher.js (which sets up window.listener).
 *
 * Exposes to window:
 *   window.SocketManager        — singleton hub, shared across all features
 *   window.CalendarSocketModule — module class (instantiate per page use)
 *
 * Per-page usage in a Blade template:
 *
 *   <script>
 *     const socketModule = new CalendarSocketModule();
 *     SocketManager.register(socketModule);
 *     SocketManager.subscribe('tenant.{{ $encryptedTenantId }}');
 *   </script>
 */



window.SocketManager = new _socket_SocketManager__WEBPACK_IMPORTED_MODULE_0__["default"]();
window.CalendarSocketModule = _socket_modules_CalendarSocketModule__WEBPACK_IMPORTED_MODULE_1__["default"];
window.BoardSocketModule = _socket_modules_BoardSocketModule__WEBPACK_IMPORTED_MODULE_2__["default"];
})();

/******/ })()
;