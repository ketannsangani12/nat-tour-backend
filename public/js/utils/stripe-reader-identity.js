/**
 * StripeReaderIdentity
 *
 * Shared "which physical reader is this" helpers used by both the Card
 * Reader admin settings page and the in-payment Card Reader tab, so a
 * reader's name/model/status render the same way everywhere and survive
 * a page reload instead of only appearing after a fresh discovery
 * round-trip.
 *
 * Storage:
 *   localStorage.stripeCardReader      -> reader id only (legacy key, kept
 *                                         so any older code that reads just
 *                                         the id keeps working)
 *   localStorage.stripeCardReaderInfo  -> JSON {id, label, device_type}
 */
const StripeReaderIdentity = {
	STORAGE_KEY: 'stripeCardReaderInfo',
	LEGACY_ID_KEY: 'stripeCardReader',

	/**
	 * Persist the currently connected reader's identity.
	 * @param {{id: string, label?: string, device_type?: string}} reader
	 */
	save({ id, label = null, device_type = null }) {
		if (!id) return;
		localStorage.setItem(this.LEGACY_ID_KEY, id);
		localStorage.setItem(this.STORAGE_KEY, JSON.stringify({ id, label, device_type }));
	},

	/**
	 * @returns {{id: string, label: string|null, device_type: string|null}|null}
	 */
	get() {
		const raw = localStorage.getItem(this.STORAGE_KEY);
		if (raw) {
			try {
				const parsed = JSON.parse(raw);
				if (parsed && parsed.id) return parsed;
			} catch (err) {
				// fall through to legacy lookup
			}
		}
		const legacyId = localStorage.getItem(this.LEGACY_ID_KEY);
		return (legacyId && legacyId !== 'null' && legacyId.trim() !== '')
			? { id: legacyId, label: null, device_type: null }
			: null;
	},

	clear() {
		localStorage.removeItem(this.STORAGE_KEY);
		localStorage.removeItem(this.LEGACY_ID_KEY);
	},

	// -- Device labels / icons -------------------------------------------------

	deviceTypeLabel(deviceType) {
		const map = {
			bbpos_wisepos_e: 'BBPOS WisePOS E',
			stripe_m2: 'Stripe Reader M2',
			stripe_s700: 'Stripe Reader S700',
			bbpos_chipper2x: 'BBPOS Chipper 2X',
			verifone_P400: 'Verifone P400',
			simulated_wisepos_e: 'Simulated WisePOS E',
		};
		return map[deviceType] || (deviceType || 'Unknown device');
	},

	// Rough visual grouping — countertop terminals get the "screen" icon,
	// mobile/dongle readers get the "slim" icon. Stripe does not expose
	// public product image URLs so we draw inline SVGs keyed by device_type.
	deviceIcon(deviceType, sizeClass = 'w-8 h-8') {
		const countertop = new Set(['bbpos_wisepos_e', 'simulated_wisepos_e', 'stripe_s700', 'verifone_P400']);
		const dongle = new Set(['stripe_m2', 'bbpos_chipper2x']);

		if (countertop.has(deviceType)) {
			return `
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
					stroke="currentColor" stroke-width="1.6" class="${sizeClass}">
					<rect x="5" y="3" width="14" height="18" rx="2"></rect>
					<rect x="7" y="5" width="10" height="7" rx="1"></rect>
					<circle cx="9" cy="17" r="0.8" fill="currentColor"></circle>
					<circle cx="12" cy="17" r="0.8" fill="currentColor"></circle>
					<circle cx="15" cy="17" r="0.8" fill="currentColor"></circle>
				</svg>
			`;
		}
		if (dongle.has(deviceType)) {
			return `
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
					stroke="currentColor" stroke-width="1.6" class="${sizeClass}">
					<rect x="7" y="5" width="10" height="14" rx="2"></rect>
					<line x1="9" y1="9" x2="15" y2="9"></line>
					<line x1="9" y1="12" x2="15" y2="12"></line>
				</svg>
			`;
		}
		return `
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
				stroke="currentColor" stroke-width="1.6" class="${sizeClass}">
				<rect x="4" y="6" width="16" height="12" rx="2"></rect>
				<line x1="4" y1="10" x2="20" y2="10"></line>
			</svg>
		`;
	},

	// -- Connection state -> visual tone ---------------------------------------

	STATE_TONE: {
		idle:         { bg: 'bg-[#F3F4F6]', border: 'border-[#E5E7EB]', text: 'text-[#374151]', dot: '#9CA3AF', label: 'Not connected' },
		searching:    { bg: 'bg-[#EFF6FF]', border: 'border-[#BFDBFE]', text: 'text-[#1E40AF]', dot: '#2563EB', label: 'Searching…' },
		connecting:   { bg: 'bg-[#EFF6FF]', border: 'border-[#BFDBFE]', text: 'text-[#1E40AF]', dot: '#2563EB', label: 'Connecting…' },
		reconnecting: { bg: 'bg-[#FFFBEB]', border: 'border-[#FED7AA]', text: 'text-[#B45309]', dot: '#EA6C0A', label: 'Reconnecting…' },
		connected:    { bg: 'bg-[#ECFDF5]', border: 'border-[#A7F3D0]', text: 'text-[#065F46]', dot: '#15803D', label: 'Connected' },
		needs_setup:  { bg: 'bg-[#FEF2F2]', border: 'border-[#FECACA]', text: 'text-[#B91C1C]', dot: '#DC2626', label: 'Needs setup' },
		error:        { bg: 'bg-[#FEF2F2]', border: 'border-[#FECACA]', text: 'text-[#B91C1C]', dot: '#DC2626', label: 'Disconnected' },
	},

	statusPillHtml(state, overrideText = null) {
		const tone = this.STATE_TONE[state] || this.STATE_TONE.idle;
		const text = overrideText || tone.label;
		return `
			<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium border ${tone.bg} ${tone.border} ${tone.text}">
				<span class="inline-block w-1.5 h-1.5 rounded-full" style="background-color:${tone.dot};"></span>${text}
			</span>
		`;
	},

	/**
	 * Render the identity chip (icon + name + model + status pill).
	 * Safe to call with a partial reader (label/device_type only known once
	 * connected) — falls back to generic copy otherwise.
	 */
	renderChip(container, { label, device_type, state = 'idle', statusText = null } = {}) {
		const el = (typeof container === 'string') ? document.querySelector(container) : container;
		if (!el) return;

		if (!label && !device_type) {
			el.innerHTML = '';
			el.classList.add('hidden');
			return;
		}

		el.classList.remove('hidden');
		el.innerHTML = `
			<div class="flex items-center gap-3 bg-[#F3F4F6] border border-[#E5E7EB] rounded-lg p-3">
				<div class="flex-shrink-0 w-9 h-9 rounded-md bg-white flex items-center justify-center text-[#374151]">
					${this.deviceIcon(device_type, 'w-5 h-5')}
				</div>
				<div class="flex-1 min-w-0 text-left">
					<div class="flex items-center gap-2 flex-wrap">
						<span class="font-medium text-[#111827] text-sm truncate">${label || 'Card Reader'}</span>
						${this.statusPillHtml(state, statusText)}
					</div>
					<div class="text-xs text-[#6B7280] mt-0.5">${this.deviceTypeLabel(device_type)}</div>
				</div>
			</div>
		`;
	},

	/**
	 * Render the live connection state — spinner/copy while
	 * searching/connecting/reconnecting, an error line if retries are
	 * exhausted, nothing once connected (the chip's pill already says so).
	 */
	renderStateArea(container, { state = 'idle', attempt = null, maxAttempts = null, message = null } = {}) {
		const el = (typeof container === 'string') ? document.querySelector(container) : container;
		if (!el) return;

		const spinner = `<div class="inline-block animate-spin rounded-full h-10 w-10 border-4 border-blue-600 border-t-transparent"></div>`;

		if (state === 'searching' || state === 'connecting') {
			el.innerHTML = `<div class="text-center py-2">${spinner}<p class="mt-3 text-sm text-[#6B7280]">${message || 'Looking for your reader…'}</p></div>`;
			el.classList.remove('hidden');
			return;
		}

		if (state === 'reconnecting') {
			const dots = (attempt && maxAttempts)
				? Array.from({ length: maxAttempts }).map((_, i) =>
					`<span class="inline-block w-1.5 h-1.5 rounded-full mx-0.5" style="background-color:${i < attempt ? '#EA6C0A' : '#FED7AA'};"></span>`
				).join('')
				: '';
			el.innerHTML = `
				<div class="text-center py-2">
					${spinner}
					<p class="mt-3 text-sm font-medium text-[#B45309]">Reconnecting${attempt && maxAttempts ? ` — attempt ${attempt} of ${maxAttempts}` : '…'}</p>
					${dots ? `<div class="mt-1">${dots}</div>` : ''}
					<p class="mt-1 text-xs text-[#6B7280]">${message || 'No action needed yet.'}</p>
				</div>
			`;
			el.classList.remove('hidden');
			return;
		}

		// needs_setup/error (and connected/idle, handled by applyState()
		// before this is even called): stay out of the way here. The
		// identity chip's status pill already flags "Needs setup", and
		// .card-reader-status-output (StripeCardReader.setMessage()) already
		// carries the actionable message *with* a working Retry button —
		// a second, button-less repeat of the same text here was pure
		// duplication, not a second useful thing to say.
		el.innerHTML = '';
		el.classList.add('hidden');
	},

	/**
	 * Convenience wire-up for the shared Card Reader tab markup (see
	 * partials/pos/cart/_cardreader_tab.blade.php) — pass this straight as
	 * StripeCardReader's onStateChange and the identity chip, generic
	 * placeholder, live state area, and "Switch reader" link all stay in
	 * sync from one state/meta pair.
	 *
	 * Falls back to the persisted identity for label/device_type on states
	 * that don't carry a fresh reader object (searching/reconnecting/etc.)
	 * so the reader's name stays visible through a reconnect instead of
	 * disappearing until it finishes.
	 */
	applyState(state, meta = {}) {
		const chipEl = document.getElementById('card-reader-identity-chip');
		const placeholderEl = document.getElementById('card-reader-generic-placeholder');
		const stateAreaEl = document.getElementById('card-reader-state-area');
		const switchLinkEl = document.querySelector('.switch-card-reader');

		const reader = meta.reader || this.get() || {};
		const known = !!(reader.label || reader.device_type || reader.id);

		if (chipEl) {
			if (known) {
				this.renderChip(chipEl, { label: reader.label, device_type: reader.device_type, state });
			} else {
				chipEl.innerHTML = '';
				chipEl.classList.add('hidden');
			}
		}

		if (placeholderEl) {
			placeholderEl.classList.toggle('hidden', known);
		}

		if (stateAreaEl) {
			// Once connected (or plain idle) the chip's status pill already
			// says so — keep the state area for searching/reconnecting/errors.
			if (state === 'connected' || state === 'idle') {
				stateAreaEl.innerHTML = '';
				stateAreaEl.classList.add('hidden');
			} else {
				this.renderStateArea(stateAreaEl, {
					state,
					attempt: meta.attempt,
					maxAttempts: meta.maxAttempts,
					message: meta.message,
				});
			}
		}

		if (switchLinkEl) {
			switchLinkEl.classList.toggle('hidden', !known);
		}
	},
};

if (typeof window !== 'undefined') {
	window.StripeReaderIdentity = StripeReaderIdentity;
}
