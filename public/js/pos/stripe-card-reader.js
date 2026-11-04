/**
 * Setup:
 * go to /admin/tour-operator/setting/card-readers
 * 1. Create a Stripe Location
 * 2. Pair Stripe Location with Stripe Terminal
 * 3. Save Reader id to localstorage
 * 4. Connect to the reader for payments
 *
 * Usage:
 *
 * // Pass your backend API endpoint to the constructor.
 * const cardReader = new StripeCardReader('/admin/tour-operator/setting/card-readers-actions?action=connection_token');
 * // Connect to a Reader:
 * cardReader.connectToReader('reader_1AbCDeFgHiJkLmNoPqr');
 *
 * // Process a Payment:
 * const clientSecret = 'pi_1XYZ..._secret_abc';
 * cardReader.processPayment(clientSecret).then((paymentIntent) => {
 *   console.log('Payment successful:', paymentIntent);
 *	});
 *
 * // Handledisconnection:
 * cardReader.disconnectReader();
 *
 * Reliability notes:
 * - The Terminal SDK object is rebuilt (StripeTerminal.create(...)) on every
 *   connect/reconnect attempt rather than reused. A stale Terminal instance
 *   is what makes plain discover+connect intermittently report
 *   `requires_pairing` even though the reader is still validly registered
 *   with Stripe — rebuilding it is what a full page refresh used to fix by
 *   accident, and what the admin Card Reader Settings debug console already
 *   does for the same reason.
 * - An unexpected disconnect no longer waits on a human clicking "Reconnect".
 *   It retries automatically (3 attempts, 2s/5s/10s backoff) before falling
 *   back to a manual retry control.
 * - Pass `onStateChange(state, meta)` in the constructor props to drive a
 *   status UI. States: idle | searching | connecting | reconnecting |
 *   connected | needs_setup | error.
 */

 class StripeCardReader {
	/**
	 * Constructor
	 * @param {object} props - API endpoint for backend operations
	 */
    constructor(props={simulated: false,
					   endpoint: null,
					   messageDiv: "#card-reader-status",
					   connectElement: ".stripe-card-reader-reconnect",
					   disconnectElement: ".stripe-card-reader-disconnect",
					   onStateChange: null,
					}) {

		// set to true if you dont have a test card.
		this.simulated 	 = props?.simulated || false;

		// payment intent url path
		this.apiEndpoint = this.getEndpoint(props?.endpoint || false)

		// the class name or id of the element to display messages
		this.messageDiv  = props.messageDiv || "#card-reader-status";

		// optional callback(state, meta) — lets the UI show identity/status
		// without every caller re-implementing the reconnect state machine.
		this.onStateChange = typeof props?.onStateChange === 'function' ? props.onStateChange : null;

		this.terminal = null; // Stripe Terminal instance
        this.reader = null; // Connected reader instance
        this.csrfToken = $('meta[name="csrf-token"]').attr("content"); // CSRF token

		// auto-reconnect tuning — 3 tries with backoff before we ask a human
		this.maxReconnectAttempts = 3;
		this.reconnectDelaysMs = [2000, 5000, 10000];
		this._reconnecting = false;

		// Remembers the elementId/displayDisconnect/amount from the last real
		// connect request (i.e. from the actual payment UI). Any *internal*
		// reconnect — after a cancelled payment, an unexpected disconnect, or
		// a manual "Reconnect" click — restores this exact context instead of
		// falling back to a generic "Card Reader Payment" button that's lost
		// track of the amount.
		this.lastConnectContext = { elementId: 'make-payment-with-card-reader', displayDisconnect: false, amount: null, readerId: null };

		// the class name of the connect button element
		this.connectElement = props?.connectElement || ".stripe-card-reader-reconnect";
		$(document).off('click', this.connectElement).on('click', this.connectElement, () => {
			const ctx = this.lastConnectContext || {};
            this.connectToReader(ctx.elementId, ctx.displayDisconnect, ctx.readerId, ctx.amount);
        });

		// the class name of the disconnect button element
		this.disconnectElement = props?.disconnectElement || ".stripe-card-reader-disconnect";
		$(document).off('click', this.disconnectElement).on('click', this.disconnectElement, ()=> {
			this.disconnectReader();
		});

		// "Switch reader" — takes staff to Card Reader Settings to discover
		// or pair a different unit, rather than trying to rebuild that whole
		// discover/assign flow a second time inside the payment modal.
		$(document).off('click', '.switch-card-reader').on('click', '.switch-card-reader', () => {
			window.open(this.getSettingsUrl(), '_blank');
		});

		// reset the card reader message
        this.setMessage(null);

		// create the card reader terminal object
        this.initTerminal();
    }

    /**
	 * Initialize the Stripe Terminal instance
	 */
    initTerminal() {
        if (typeof StripeTerminal === 'undefined') {
            throw new Error('Stripe Terminal SDK failed to load.');
        }
        this.terminal = StripeTerminal.create({
            onFetchConnectionToken: this.fetchConnectionToken.bind(this),
            onUnexpectedReaderDisconnect: this.handleReaderDisconnect.bind(this),
        });
    }

    /**
	 * Fetch connection token from the backend
	 * void
	 */
    async fetchConnectionToken() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=connection_token`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) throw new Error('Failed to fetch connection token');
            const data = await response.json();
            this.setMessage(`Connection token fetched successfully`);
            return data.result;
        } catch (error) {
            // No native alert() here — it blocks the whole page until
            // dismissed, on top of the in-context message right below.
            this.setMessage(`Couldn't reach Stripe: ${this.friendlyReaderError(error.message)}`, "danger");
            throw error;
        }
    }

    /**
	 * Handle unexpected reader disconnection — retry automatically instead
	 * of dropping straight to a manual "Reconnect" prompt.
	 */
    handleReaderDisconnect() {
        this.reader = null;
        this.autoReconnect(1);
    }

	/**
	 * Retry connecting to the last-known reader with backoff, rebuilding the
	 * Terminal SDK object on every attempt. Falls back to a manual retry
	 * control once maxReconnectAttempts is exhausted.
	 * @param {number} attempt 1-indexed attempt number
	 */
	async autoReconnect(attempt = 1) {
		if (this._reconnecting) return;
		this._reconnecting = true;

		const savedReader = this.getSavedReader();
		if (!savedReader?.id) {
			this._reconnecting = false;
			this._emitState('needs_setup', { message: 'No reader configured for this workstation yet.' });
			return;
		}

		this._emitState('reconnecting', {
			attempt,
			maxAttempts: this.maxReconnectAttempts,
			message: `Reader disconnected — reconnecting automatically (attempt ${attempt} of ${this.maxReconnectAttempts})…`,
		});

		const delay = this.reconnectDelaysMs[attempt - 1] ?? this.reconnectDelaysMs[this.reconnectDelaysMs.length - 1];
		await new Promise((resolve) => setTimeout(resolve, delay));

		try {
			await this.terminal?.disconnectReader();
		} catch (err) {
			// ignore — we're about to discard this terminal instance anyway
		}
		this.initTerminal();

		try {
			const discoverResult = await this.terminal.discoverReaders({ simulated: this.simulated });
			if (discoverResult.error) throw new Error(discoverResult.error.message);

			const selected = discoverResult.discoveredReaders.find(
				(r) => r.id === savedReader.id || (this.simulated && r.id === 'SIMULATOR')
			);
			if (!selected) throw new Error('Reader not visible yet');

			const connectResult = await this.terminal.connectReader(selected);
			if (connectResult.error) throw new Error(connectResult.error.message);

			this.reader = connectResult.reader;
			this.saveReader(this.reader);
			this._reconnecting = false;
			// Restore the same Charge button the user had before the
			// unexpected disconnect — not just a "Reconnected" message with
			// nothing left to actually take the payment with.
			const ctx = this.lastConnectContext || {};
			this.setMessage(this.connectedActionsHtml(ctx.elementId, ctx.displayDisconnect, ctx.amount), 'plain');
			this._emitState('connected', { reader: this.reader });
		} catch (err) {
			this._reconnecting = false;
			if (attempt < this.maxReconnectAttempts) {
				return this.autoReconnect(attempt + 1);
			}
			const retryBtn = '<button class="btn-success-regular stripe-card-reader-reconnect" type="button">Retry</button>';
			this.setMessage(`Couldn't reconnect automatically. ${retryBtn}`, "danger");
			this._emitState('needs_setup', {
				attempt,
				maxAttempts: this.maxReconnectAttempts,
				message: "Couldn't reconnect automatically. Tap Retry, or re-pair from Card Reader Settings if this keeps happening.",
			});
		}
	}

    /**
	 * Discover and connect to a reader
	 * @param {string} elementId this adds a event listner to a element with this id
	 * @param {string} readerId stripe reader id if null pulls from local-storage.
	 * @param {number} amount the amount to display on the button
	 */
    async connectToReader(elementId="make-payment-with-card-reader", displayDisconnect=false, readerId=null, amount=null) {
		this._reconnecting = false;

		// Resolve which reader we're actually attempting *before* storing
		// the context — a manual "Reconnect"/Retry click needs to target
		// this exact reader even if this very attempt fails, not whichever
		// reader was last *successfully* saved (which could be a different
		// one, or none, the first time a newly-selected reader is tried).
		if (!readerId) {
			readerId = this.getSavedReader()?.id || null;
		}
		this.lastConnectContext = { elementId, displayDisconnect, amount, readerId };

		// Best-effort disconnect on whatever terminal object we currently
		// hold, then rebuild it fresh below. Reusing a Terminal instance
		// across a disconnect is what makes reconnects intermittently report
		// `requires_pairing` even though the reader is still validly
		// registered — a brand new instance is what actually fixes it.
		try {
			await this.terminal?.disconnectReader();
		} catch (err) {
			// ignore — about to discard this terminal instance anyway
		}
		this.initTerminal();

		this._emitState('searching', { message: 'Looking for your reader…' });

        try {

            if (!readerId){
				let refUrl = window.location.host + '/setting/card-readers';
				let config = '<a href='+refUrl+' target="_blank">Settings</a>';
				this.setMessage(`Card Reader Not Configured ${config}`, "danger");
				this._emitState('needs_setup', { message: 'No reader configured for this workstation yet.' });
				return false
            }

            const discoverResult = await this.terminal.discoverReaders({ simulated: false });
            if (discoverResult.error) throw new Error(discoverResult.error.message);

			let selectedReader = null;

			if (this.simulated){
				selectedReader = discoverResult.discoveredReaders.find((reader) => (reader.id === readerId || reader.id === "SIMULATOR"));
			}else{
				selectedReader = discoverResult.discoveredReaders.find((reader) => reader.id === readerId);
			}

            if (!selectedReader) throw new Error('Reader not found');

			this._emitState('connecting', { message: 'Connecting…' });

            const connectResult = await this.terminal.connectReader(selectedReader);
            if (connectResult.error) throw new Error(connectResult.error.message);

            this.reader = connectResult.reader;
			this.saveReader(this.reader);
            this.setMessage(this.connectedActionsHtml(elementId, displayDisconnect, amount), 'plain');
			this._emitState('connected', { reader: this.reader });

        } catch (error) {
			const friendly = this.friendlyReaderError(error.message);
            this.setMessage(`Reader not connected: ${friendly}`, "danger");
			this._emitState('needs_setup', { message: friendly });
        }
    }

	/**
	 * Build the "connected" action HTML — the Charge button (and optional
	 * inline Disconnect) for a given amount. Shared by connectToReader() and
	 * autoReconnect() so that ANY reconnect — user-cancelled or unexpected —
	 * restores the same button instead of falling back to generic
	 * "Card Reader Payment" placeholder text once the amount is known.
	 * @param {string} elementId
	 * @param {boolean} displayDisconnect
	 * @param {number|string|null} amount
	 * @returns {string}
	 */
	connectedActionsHtml(elementId, displayDisconnect, amount) {
		let buttonText = 'Card Reader Payment';
		if (amount && amount > 0) {
			const formattedAmount = parseFloat(amount).toFixed(2);
			buttonText = `Charge ${currencyFormation.symbol}${formattedAmount}`;
		}

		// Just the action — the identity chip above already shows which
		// reader this is and that it's connected, so repeating the label
		// here would only duplicate it. The lock icon mirrors the Card
		// Online tab's "Pay Securely" button, so both tabs read as equally
		// trustworthy rather than the reader flow looking like an
		// afterthought next to it. Inline SVG, not a Font Awesome class —
		// this markup renders on every page that uses this class regardless
		// of which FA version (or none) that page happens to load; POS uses
		// FA4 (bare "fa fa-*" only) while the booking modals use FA5
		// ("fas fa-*" only, no v4 shim loaded), so no single icon-font class
		// works on both.
		// Spacing/alignment via inline style, not Tailwind classes (mr-2,
		// align-middle) — this button renders on pages that don't load
		// Tailwind at all (e.g. POS/layouts.default-5), where those classes
		// are inert and the icon would sit flush against the text.
		const lockIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true" style="display:inline-block; vertical-align:middle; margin-top:-2px; margin-right:8px;"><rect x="5" y="11" width="14" height="9" rx="1.5"></rect><path d="M8 11V7a4 4 0 0 1 8 0v4"></path></svg>`;
		let btn = `<button class="confirm-btn ${elementId} w-full mt-3 btn-success-regular card-reader-charge-btn" type="button">${lockIcon}${buttonText}</button>`;
		if (displayDisconnect === true) {
			btn += `<button class="card-reader-quiet-link stripe-card-reader-disconnect text-nowrap w-auto mt-3 block mx-auto" type="button">Disconnect</button>`;
		}
		return btn;
	}

	/**
	 * Get the payment Intent
	 * @todo move this to the app/Repositories/Pos/PosRepository.
	 * @param {object} props
	 * @param {string} currency
	 */
	async getPaymentIntent({amount, description, fees, meta}, currency='usd') {
        try {
			const payload = {
				amount: amount,
				description: description,
				currency: currency,
				fees: fees,
				metadata: meta,
				payment_method_types:"card,card_present",
				save_card_on_file: $('#save_card_on_file').is(':checked') ? 1 : 0
			}

            const response = await fetch(`${this.apiEndpoint}?action=get_payment_intent`, {
                method: 'POST',
				body: JSON.stringify(payload),
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) throw new Error('Failed to fetch connection token');
            const data = await response.json();
			// Same spinner treatment as the searching/connecting/reconnecting
			// states above the chip — one consistent "in progress" look
			// instead of a mismatched alert box with an oddly-justified
			// button next to plain text.
			const pendingHtml = `
				<div class="text-center py-2">
					<div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-blue-600 border-t-transparent"></div>
					<p class="mt-3 text-sm font-medium text-gray-700">Waiting for card…</p>
					<button class="cancel-card-reader-payment mt-3 btn-default-small" type="button">Cancel</button>
				</div>
			`;
            this.setMessage(pendingHtml, 'plain');
            return data.result;
        } catch (error) {
			const retry = '<button class="btn-success-regular stripe-card-reader-reconnect">Reconnect</button>';
            this.setMessage(`Couldn't start the payment: ${this.friendlyReaderError(error.message)} ${retry}`, "danger");
            throw error;
        }
    }

	/**
	 * Start a new payment
	 * @param {*} amount
	 * @param {*} description
	 * @param {*} fees
	 * @param {*} meta
	 * @param {*} onSuccss
	 * @param {*} onError
	 * @returns void
	 */
	async makePayment(	amount=0,
						description="",
						fees=0,
						meta={},
						onSuccss=(d)=>console.log(d),
						onError=(d)=>console.log(d)){
			// The whole round trip (create payment intent, wait for tap,
			// process) can take a few seconds. Freeze the Charge button the
			// instant it's clicked so it visibly shows it's working — a
			// separate spinner elsewhere on the page doesn't stop someone
			// clicking an unchanged-looking button again.
			this.setChargeButtonBusy(true);
			try{

				const payload = {amount: this.toStripeTotal(amount), fees: this.toStripeTotal(fees), description, meta};
				const paymentIntent = await this.getPaymentIntent( {...payload}, currencyFormation.code );
				if (paymentIntent.clientSecret){
					const confirmResult = await this.processPayment(paymentIntent.clientSecret);
					onSuccss(confirmResult);
					return;
				}else{
					if(amount == ""){
						this.setMessage('Amount is required.','danger');
					} else if (isNaN(amount) || amount.trim() === '') {
						this.setMessage('Amount must be a number.','danger');
					} else {
						// The backend returns {error: "<message>"} on failure
						// (see CardReaderRepository::getPaymentIntent()), not
						// {message: ...} — display it here so this path
						// doesn't leave "Waiting for card…" showing forever
						// with no visible reason why.
						this.setMessage(this.friendlyReaderError(paymentIntent.error) || 'Payment failed', 'danger');
						onError(paymentIntent);
					}
				}
			}catch(err){
				onError(err);
			}finally{
				// No-ops harmlessly if the button's already gone — e.g.
				// processPayment() replaced it with a success message, or
				// cancelPayment()'s reconnect already rebuilt a fresh one.
				this.setChargeButtonBusy(false);
			}
		}

	/**
	 * Freeze/restore the Charge button in place so re-clicking it while a
	 * payment is already in flight is visibly pointless instead of silently
	 * pointless. Targets .card-reader-charge-btn — the class
	 * connectedActionsHtml() always puts on the Charge button specifically
	 * (not Connect/Disconnect), so this can't accidentally disable the
	 * wrong control.
	 * @param {boolean} busy
	 */
	setChargeButtonBusy(busy) {
		const $btn = $('.card-reader-charge-btn');
		if (!$btn.length) return;

		if (busy) {
			if ($btn.data('idle-html') === undefined) {
				$btn.data('idle-html', $btn.html());
			}
			// border-color set inline, not via a border-white class — same
			// reasoning as readerStatusDotColor()'s dot colors (in the Card
			// Reader Settings page): this file isn't scanned by Tailwind's
			// JIT, so a one-off class like border-white (unused anywhere
			// else) would silently fail to compile.
			$btn.prop('disabled', true)
				.addClass('opacity-70 cursor-not-allowed')
				.html('<span class="inline-block animate-spin rounded-full h-4 w-4 border-2 align-middle mr-2" style="border-color:#ffffff; border-top-color:transparent;"></span>Processing…');
		} else {
			const idleHtml = $btn.data('idle-html');
			if (idleHtml !== undefined) {
				$btn.html(idleHtml);
			}
			$btn.prop('disabled', false).removeClass('opacity-70 cursor-not-allowed');
		}
	}

	/**
	 * Process payment
	 * @param {string} clientSecret // client secret from payment intent.
	 * @returns
	 */
    async processPayment(clientSecret) {
        try {
            const paymentResult = await this.terminal.collectPaymentMethod(clientSecret);
            if (paymentResult.error) throw new Error(paymentResult.error.message);
            const confirmResult = await this.terminal.processPayment(paymentResult.paymentIntent);
            if (confirmResult.error) throw new Error(confirmResult.error.message);
			const successMsg = `
				<div class="text-center py-2">
					<i class="fa fa-check-circle" style="color:#15803D; font-size:28px;"></i>
					<p class="mt-2 text-sm font-medium text-gray-700">Payment successful — resetting reader…</p>
				</div>
			`;
            this.setMessage(successMsg, 'plain');
			setTimeout (()=> {
				this.disconnectReader("Payment processed successfully Reader in Rest Mode");
			}, 1000 );
            //Add success logic here
            return confirmResult.paymentIntent;
        } catch (error) {
            //Handle errors here.
            this.setMessage(`Payment couldn't be processed: ${this.friendlyReaderError(error.message)}`, "danger");
            throw error;
        }
    }

	/**
	 * Cancel a payment if no tap.
	 * @param {function} onSuccess
	 * @param {function} onError
	 */
	async cancelPayment(onSuccess=(d)=>console.log(d), onError=(e)=>console.log(e)) {
		try {
			const cancelResult = await this.terminal.cancelCollectPaymentMethod();
			if (cancelResult.error) {
				throw new Error(cancelResult.error.message);
			}
			onSuccess(cancelResult);
		} catch (error) {
			this.setMessage(`Couldn't cancel: ${this.friendlyReaderError(error.message)}`, 'danger');
			onError(error);
		}

		// Reconnect with the exact same elementId/displayDisconnect/amount/
		// readerId the payment was started with, so the Charge button comes
		// back showing the right amount instead of generic placeholder text.
		// connectToReader() already disconnects + rebuilds the Terminal
		// object itself, so there's no need to disconnect again first here.
		try {
			const ctx = this.lastConnectContext || {};
			await this.connectToReader(ctx.elementId, ctx.displayDisconnect, ctx.readerId, ctx.amount);
		} catch (err) {
			console.log(err);
		}
	}

    /**
	 * Manually disconnect the card reader to re-init reader
	 */
    async disconnectReader(msg=null) {
        try {
            await this.terminal.disconnectReader();
			console.log("Reader disconnected successfully.");
            this.setMessage( msg || 'Reader disconnected successfully.');
			this._emitState('idle', { message: msg || 'Disconnected.' });
        } catch (error) {
            this.setMessage(`Error disconnecting reader ${error.message}`, "danger");
        }
    }

	/**
	 * Display a message in the ui
	 * @param {string} message
	 * @param {string} type success|error|warning|danger|plain — "plain"
	 *   renders the content on its own, with no alert-box wrapper. Used for
	 *   the connected/charge-button state, where the identity chip above
	 *   already communicates status — wrapping the Charge button in a
	 *   second colored box on top of that would just duplicate it.
	 * @returns
	 */
    setMessage(message, type="success"){
		if (message == null){
			$(this.messageDiv).html("");
		}else if (type === 'plain'){
			$(this.messageDiv).html(`<div class="w-100">${message}</div>`);
		}else{
			$(this.messageDiv).html(`<div class="alert alert-${type} w-100 d-sm-flex align-items-center" role="alert">${message}</div>`);
		}
	}

	/**
	 * Read the reader identity (id + label + device_type when known) saved
	 * for this workstation. Falls back to the bare legacy id if the shared
	 * identity helper isn't loaded on a given page.
	 * @returns {{id: string, label?: string, device_type?: string}|null}
	 */
	getSavedReader() {
		if (typeof StripeReaderIdentity !== 'undefined') {
			return StripeReaderIdentity.get();
		}
		const id = localStorage.getItem('stripeCardReader');
		return (id && id !== 'null' && id.trim() !== '') ? { id } : null;
	}

	/**
	 * Translates known raw Stripe Terminal / backend error strings into
	 * actionable copy a non-technical user can act on, instead of SDK
	 * jargon like "The POS is no longer authenticated." Falls back to the
	 * raw message untouched for anything not recognized, so unfamiliar
	 * failures are never silently hidden behind a generic string.
	 * @param {string} raw
	 * @returns {string}
	 */
	friendlyReaderError(raw) {
		const message = String(raw || '').trim();
		const lower = message.toLowerCase();
		if (lower.includes('no longer authenticated') || lower.includes('not authenticated')) {
			return "This reader is already connected somewhere else — another browser tab, workstation, or the Card Reader Settings page. Disconnect it there, then try again here.";
		}
		if (lower.includes('requires_pairing') || lower.includes('requires pairing')) {
			return 'This reader needs a new pairing code — go to Card Reader Settings to re-pair it.';
		}
		if (lower.includes('not visible') || lower.includes('reader not found')) {
			return "Can't reach the reader — check it's powered on and connected to the network.";
		}
		return message || 'Something went wrong connecting to the reader.';
	}

	/**
	 * Persist the connected reader's identity so its name/model can render
	 * immediately next time, before any discovery round-trip finishes.
	 * @param {{id: string, label?: string, device_type?: string}} reader
	 */
	saveReader(reader) {
		if (!reader?.id) return;
		if (typeof StripeReaderIdentity !== 'undefined') {
			StripeReaderIdentity.save({ id: reader.id, label: reader.label, device_type: reader.device_type });
		} else {
			localStorage.setItem('stripeCardReader', reader.id);
		}
	}

	/**
	 * Notify the optional onStateChange listener. Never throws — a broken
	 * UI callback should not be able to break the reconnect logic.
	 * @param {string} state idle|searching|connecting|reconnecting|connected|needs_setup|error
	 * @param {object} meta
	 */
	_emitState(state, meta = {}) {
		if (!this.onStateChange) return;
		try {
			this.onStateChange(state, meta);
		} catch (err) {
			console.error('StripeCardReader onStateChange handler failed:', err);
		}
	}

	/**
	 * return the api endpoint to fetch the client secret
	 * return {string} url with or without admin prefix
	 */
	getEndpoint(endpoint = false){
		const isAdmin = window.location.pathname.includes('/admin/') ? "/admin/tour-operator" : "/tour-operator";
		const isAffiliate = window.location.pathname.includes('/affiliate/') ? "/affiliate" : null;
	    const prefix = (isAffiliate) ? isAffiliate : isAdmin;
		return (endpoint) ? endpoint : `https://${window.location.host}${prefix}/setting/card-readers-actions`;
	}

	/**
	 * Card Reader Settings page for this same workstation/prefix — used by
	 * "Switch reader" so staff land on the discover/pair/assign flow instead
	 * of us re-building it a second time inside the payment modal.
	 * @returns {string}
	 */
	getSettingsUrl() {
		return this.apiEndpoint.replace(/-actions$/, '');
	}

	/**
	 * Round to 2 decimal places and convert to cents
	 * @param {number} amount
	 * @returns
	 */
	toStripeTotal(amount){
		return Math.round(parseFloat(amount) * 100);
	}

}
