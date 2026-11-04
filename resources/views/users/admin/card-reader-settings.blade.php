@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">

					<!-- Page header -->
					<div class="mb-6">
						<h1 class="text-lg font-semibold text-[#020817]">Card Reader</h1>
						<p class="text-sm text-[#6B7280] mt-1 max-w-2xl">
							Connect a Stripe reader to <strong>this workstation</strong> so it can take in-person payments.
							Each computer remembers its own reader — pairing one here won't change what another workstation uses.
						</p>
					</div>

					<!-- Status strip — warnings/errors only now that the hero card and
					     reader list carry the routine status. Hidden until setStatus() is called. -->
					<div id="status-strip" class="hidden mb-5 p-4 rounded-md border flex items-start gap-3">
						<div id="status-icon" class="flex-shrink-0 mt-1">
							<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
								<path fill-rule="evenodd"
									d="M18 10A8 8 0 11 2 10a8 8 0 0116 0zM9 9a1 1 0 112 0v4a1 1 0 11-2 0V9zm1-4a1 1 0 100 2 1 1 0 000-2z"
									clip-rule="evenodd" />
							</svg>
						</div>
						<div class="flex-1 min-w-0">
							<div id="status-title" class="font-medium"></div>
							<div id="status-message" class="text-sm mt-1 break-all"></div>
						</div>
					</div>

					<!-- HERO: this workstation's live connection status -->
					<div id="hero-card"></div>

					<!-- Readers at this location -->
					<div class="flex items-baseline justify-between gap-4 mt-8 mb-3 flex-wrap">
						<div>
							<h2 class="text-[15.5px] font-semibold text-[#020817]">Readers at this location</h2>
							<div id="location-sub" class="text-xs text-[#6B7280] mt-0.5">Loading…</div>
						</div>
						<div class="flex items-center gap-5">
							<button id="refresh-readers" type="button"
								class="text-xs font-semibold text-[#6B7280] hover:text-[#020817]">↻ Refresh</button>
							<button id="pair-reader" type="button" class="btn btn-sm btn-primary btn-primary-small">+ Pair a new
								reader</button>
						</div>
					</div>
					<div id="reader-list" class="flex flex-col gap-2.5"></div>

					<div id="loading" style="margin: auto; text-align:center; padding: 10px;"></div>

					<!-- Reference panels — supporting info, tucked away until needed -->
					<details class="mt-8 pt-4 border-t border-[#E5E7EB]">
						<summary class="cursor-pointer text-sm font-semibold text-[#111827] select-none">▸ Account &amp;
							Location</summary>
						<div id="account-location-body" class="mt-3"></div>
					</details>

					<details class="mt-4">
						<summary class="cursor-pointer text-sm text-[#6B7280] select-none">▸ Debug console</summary>
						<div id="console"
							class="mt-2 h-32 border border-[#555753] bg-[#2e3436] shadow-md rounded-md p-3 text-xs text-[#eeeeec] overflow-x-auto">
						</div>
					</details>

					<div id="page-elements"></div>

					<!-- Not shown — StripeCardReader still writes its own status text here
					     internally, but this page renders its own hero/list UI instead. -->
					<div id="card-reader-message-sink" class="hidden"></div>

					<template id="location-details-template">
						<div id="location-details-body">
							<div class="text-sm text-[#6B7280]">Loading location details…</div>
						</div>
					</template>

					<template id="pair-reader-template">
						<form action="#" id="pair-reader-form" method="POST">
							<p class="text-sm text-[#6B7280] mb-3">
								Put the reader into pairing mode and enter the code it displays. The location is pulled from your
								Tour Operator settings. Choose a human-friendly label so this workstation can recognize the reader
								later.
							</p>
							<div class="form-floating pb-2">
								<label class="form-custom-label" for="pairing_code">Pairing Code</label>
								<small class="block text-[#6B7280]">Admin Pin: 07139</small>
								<input class="form-control form-custom-input" type="text" id="pairing_code" name="pairing_code"
									placeholder="e.g. apple-banana-cherry" required>
							</div>
							<div class="form-floating pb-2">
								<label class="form-custom-label" for="label">Reader Label</label>
								<small class="block text-[#6B7280]">e.g. Front-Desk or Kiosk-1</small>
								<input class="form-control form-custom-input" type="text" id="label" name="label" required>
							</div>
							<input type="hidden" id="location_id" name="location_id" value="">
						</form>
					</template>

				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-custom')

@section('js-libraries')
<script src="https://js.stripe.com/terminal/v1/"></script>
<script src="{{ asset('js/utils/helpers.js')  }}"></script>
<script src="{{ asset('js/utils/stripe-reader-identity.js')  }}"></script>
<script src="{{ asset('js/pos/stripe-card-reader.js')  }}"></script>
@endsection

<script>
	/**
	 * Card Reader Settings — one status card ("This workstation") plus one
	 * reader list ("Readers at this location"), replacing the old 5-tab bar
	 * that mixed views with one-shot actions and duplicated the same reader
	 * across two separate cards.
	 *
	 * Connection/reconnection itself is delegated to the shared
	 * StripeCardReader class (public/js/pos/stripe-card-reader.js) — the
	 * same engine the payment modal uses — via onStateChange, rather than
	 * reimplementing terminal-rebuild/auto-retry logic a third time on this
	 * page. Its own setMessage() output is pointed at a hidden sink div;
	 * this page renders its own hero/list UI from the state callback instead.
	 */
	class CardReaderSettings {
		constructor() {
			this.API = "{{ route(routePrefix() . '.setting.card-readers-actions') }}";
			this.csrf = {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
				'Content-Type': 'application/json'
			};
			this.terminalLocationId = null;
			this.readers = [];          // last discovered readers at this location
			this.activeReaderId = null; // id actually live-connected in this browser tab right now
			this.heroState = 'idle';    // idle | searching | connecting | reconnecting | connected | needs_setup | error
			this.heroMeta = {};

			this.cardReader = new StripeCardReader({
				endpoint: this.API,
				messageDiv: '#card-reader-message-sink',
				onStateChange: (state, meta) => this.handleReaderState(state, meta),
			});

			this.initDisplay();
			this.initEvents();
			this.renderHero();
			this.bootstrap();
		}

		// -- Bootstrap ------------------------------------------------------

		async bootstrap() {
			this.logConsole('info', 'Page loaded', this.API);

			// Show whatever reader this workstation is set up to use, but do
			// NOT auto-connect to it: a Stripe Terminal reader only holds one
			// authenticated session at a time, so connecting here would
			// silently steal the reader away from any *other* tab or
			// workstation currently mid-payment on it — the exact cause of
			// "The POS is no longer authenticated" errors showing up in the
			// payment modal. Actually claiming the reader now requires an
			// explicit "Connect" click below.
			const saved = this.cardReader.getSavedReader();
			this.logConsole('info', 'Saved reader for this workstation', saved?.id ? `${saved.label || saved.id} (${saved.id})` : 'none');
			if (saved?.id) {
				this.heroState = 'assigned';
				this.heroMeta = { reader: saved };
				this.renderHero();
			}

			await this.getAccountSettings();
			if (this.terminalLocationId) {
				await this.refreshReaders(true);
			}
		}

		// -- Networking ------------------------------------------------------

		async postAction(endpoint, body = {}) {
			const response = await fetch(endpoint, {
				method: 'POST',
				headers: this.csrf,
				body: JSON.stringify(body),
			});
			if (!response.ok) {
				throw new Error(`Error: ${response.statusText}`);
			}
			return response.json();
		}

		// -- Status strip — warnings/errors only ------------------------------

		setStatus(title, message, tone = 'warning') {
			const tones = {
				info:    { bg: 'bg-[#EFF6FF]', border: 'border-[#BFDBFE]', text: 'text-[#1E40AF]' },
				warning: { bg: 'bg-[#FFFBEB]', border: 'border-[#FED7AA]', text: 'text-[#B45309]' },
				error:   { bg: 'bg-[#FEF2F2]', border: 'border-[#FECACA]', text: 'text-[#B91C1C]' },
			};
			const palette = tones[tone] || tones.warning;
			const strip = $('#status-strip');
			strip.removeClass((_, c) => (c.match(/(^|\s)(bg|border|text)-\[[^\]]+\]/g) || []).join(' '));
			strip.removeClass('hidden').addClass(`${palette.bg} ${palette.border} ${palette.text} mb-5 p-4 rounded-md border flex items-start gap-3`);
			$('#status-title').text(title);
			$('#status-message').html(message);
			CardReaderSettings.writeToConsole(`[${tone}] ${title}: ${typeof message === 'string' ? message : ''}`);
		}

		hideStatus() {
			$('#status-strip').addClass('hidden');
		}

		// Logs to the debug console independently of the (now warnings/errors
		// only) status strip — routine events like "reader connected" or
		// "3 readers found" still belong in the debug trail even though they
		// no longer pop a banner.
		logConsole(tone, title, message = '') {
			CardReaderSettings.writeToConsole(`[${tone}] ${title}${message ? `: ${message}` : ''}`);
		}

		displayLoading(display = true, ele = "#loading", timeout = 3000) {
			if (display) {
				$(ele).html(`<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>`);
				setTimeout(() => $(ele).html(``), timeout);
			} else {
				$(ele).html(``);
			}
		}

		// -- Init --------------------------------------------------------

		initDisplay() {
			const mainDiv = $("#page-elements");
			mainDiv.append(bootstrapModalHtml({
				id: "pair-reader-modal",
				size: "lg",
				title: 'Pair New Reader',
				content: $("#pair-reader-template").html(),
				footer: "",
				action: '<button id="pair-reader-submit" class=" btn btn-sm btn-primary btn-primary-small">Pair</button>'
			}));
			mainDiv.append(bootstrapModalHtml({
				id: "location-details-modal",
				size: "md",
				title: 'Terminal Location',
				content: $("#location-details-template").html(),
				footer: "",
				action: ''
			}));
		}

		initEvents() {
			$("#pair-reader").click(this.pairReader.bind(this));
			$("#pair-reader-submit").click(this.pairReaderSubmit.bind(this));
			$("#refresh-readers").click(() => this.refreshReaders(false));
			$(document).on("click", ".use-this-reader", this.useThisReader.bind(this));
			$(document).on("click", ".forget-reader", this.forgetReader.bind(this));
			$(document).on("click", ".overflow-toggle", this.toggleOverflow.bind(this));
			$(document).on("click", ".show-location-details", this.showLocationDetails.bind(this));
			// Close any open overflow ("⋯") menu when clicking outside of it.
			$(document).on("click", (evt) => {
				if (!$(evt.target).closest('.overflow-wrap').length) {
					$('.overflow-menu').addClass('hidden');
				}
			});
			$(document).on("keyup", "#label", (evt) => {
				evt.target.value = this.slug(evt.target.value);
			});
		}

		slug(str) {
			const parts = str.toString().split(/[^a-z0-9]+/i);
			return parts
				.map(word => word.length === 0 ? '' : word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
				.join('-');
		}

		// -- Reader connection state (driven by StripeCardReader) -------------

		handleReaderState(state, meta) {
			this.heroState = state;
			this.heroMeta = meta || {};
			if (state === 'connected' && meta?.reader) {
				this.activeReaderId = meta.reader.id;
				// This page's job is to verify a reader is set up
				// correctly, not to hold its one-and-only Stripe Terminal
				// session hostage — a physical reader accepts exactly one
				// connected session at a time. Leaving this page's
				// connection open (e.g. a tab left open after checking
				// setup in the morning) blocks that same reader from
				// connecting anywhere else all day, surfacing there as a
				// cryptic "The POS is no longer authenticated" error.
				// Release the underlying session right after confirming
				// it works, without touching the "Connected / Verified"
				// display itself — calling terminal.disconnectReader()
				// directly here, not this.disconnectReader(), so
				// onStateChange doesn't also fire and revert the UI back
				// to idle.
				setTimeout(() => {
					this.cardReader.terminal?.disconnectReader()?.catch(() => {});
				}, 500);
			} else if (state === 'idle' || state === 'needs_setup' || state === 'error') {
				// A real disconnect (as opposed to "about to retry") means
				// nothing is actually live right now — the list shouldn't
				// keep badging a reader we're not connected to anymore.
				this.activeReaderId = null;
			}
			this.logReaderState(state, meta);
			this.renderHero();
			this.renderReaderList();
		}

		// Every connect/reconnect/disconnect transition belongs in the debug
		// trail — this is the log an admin actually reaches for when
		// troubleshooting a flaky reader, so it stays complete even though
		// the visible status strip above no longer shows one for each step.
		logReaderState(state, meta = {}) {
			switch (state) {
				case 'searching':
					this.logConsole('info', 'Searching', meta.message || 'Looking for reader…');
					break;
				case 'connecting':
					this.logConsole('info', 'Connecting', meta.message || '');
					break;
				case 'connected':
					this.logConsole('success', 'Connected', meta.reader ? `${meta.reader.label || meta.reader.id} (${meta.reader.id})` : '');
					break;
				case 'reconnecting':
					this.logConsole('warning', 'Reconnecting', (meta.attempt && meta.maxAttempts) ? `attempt ${meta.attempt} of ${meta.maxAttempts}` : (meta.message || ''));
					break;
				case 'needs_setup':
				case 'error':
					this.logConsole('error', "Couldn't connect", meta.message || '');
					break;
				case 'idle':
					this.logConsole('info', 'Disconnected', meta.message || '');
					break;
			}
		}

		useThisReader(evt) {
			const readerId = $(evt.currentTarget).closest('[data-readerid]').data('readerid');
			if (!readerId) return;
			this.cardReader.connectToReader('settings-page', false, readerId, null);
		}

		// -- Hero: "This workstation" ------------------------------------

		renderHero() {
			const el = $('#hero-card');
			const state = this.heroState;
			const meta = this.heroMeta || {};

			if (state === 'connected' && meta.reader) {
				el.html(this.heroConnectedHtml(meta.reader));
				return;
			}
			if (state === 'assigned' && meta.reader) {
				el.html(this.heroAssignedHtml(meta.reader));
				return;
			}
			if (state === 'searching') {
				el.html(this.heroBusyHtml('Checking connection…'));
				return;
			}
			if (state === 'connecting') {
				el.html(this.heroBusyHtml('Connecting…'));
				return;
			}
			if (state === 'reconnecting') {
				const label = (meta.attempt && meta.maxAttempts)
					? `Reconnecting — attempt ${meta.attempt} of ${meta.maxAttempts}…`
					: 'Reconnecting…';
				el.html(this.heroBusyHtml(label));
				return;
			}
			if (state === 'needs_setup' || state === 'error') {
				el.html(this.heroErrorHtml(meta.message));
				return;
			}
			el.html(this.heroEmptyHtml());
		}

		heroEmptyHtml() {
			return `
				<div class="rounded-xl border-2 border-dashed border-[#E5E7EB] p-5 flex items-center gap-4">
					<div class="flex-shrink-0 w-[52px] h-[52px] rounded-lg border border-[#E5E7EB] bg-white flex items-center justify-center text-[#9CA3AF]">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-6 h-6">
							<rect x="4" y="6" width="16" height="12" rx="2"></rect><line x1="4" y1="10" x2="20" y2="10"></line>
						</svg>
					</div>
					<div>
						<div class="text-[11px] font-bold tracking-wide uppercase text-[#6B7280] mb-0.5">This workstation</div>
						<div class="text-base font-semibold text-[#374151]">No reader connected yet</div>
						<div class="text-sm text-[#6B7280] mt-0.5">Pick one from the list below, or pair a new device.</div>
					</div>
				</div>
			`;
		}

		// This workstation has a reader on file, but nothing has actually
		// claimed its session yet this visit — an explicit "Connect" click
		// is what triggers connectToReader(), never the page load itself.
		heroAssignedHtml(reader) {
			const modelLabel = CardReaderSettings.deviceTypeLabel(reader.device_type);
			const icon = StripeReaderIdentity.deviceIcon(reader.device_type, 'w-6 h-6');
			return `
				<div class="rounded-xl border border-[#E5E7EB] bg-white p-5 flex items-center gap-4">
					<div class="flex-shrink-0 w-[52px] h-[52px] rounded-lg border border-[#E5E7EB] bg-[#F9FAFB] flex items-center justify-center text-[#6B7280]">
						${icon}
					</div>
					<div class="flex-1 min-w-0">
						<div class="text-[11px] font-bold tracking-wide uppercase text-[#6B7280] mb-0.5">This workstation</div>
						<div class="flex items-center gap-2.5 flex-wrap">
							<span class="text-lg font-bold text-[#020817]">${reader.label || reader.id}</span>
							<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6B7280] bg-[#F3F4F6] border border-[#E5E7EB] rounded-full px-2.5 py-1">
								<span class="inline-block w-1.5 h-1.5 rounded-full bg-[#9CA3AF]"></span>Not connected yet
							</span>
						</div>
						<div class="text-sm text-[#6B7280] mt-0.5">${modelLabel} · <code class="text-xs">${reader.id}</code></div>
					</div>
					<button type="button" data-readerid="${reader.id}"
						class="use-this-reader btn btn-sm btn-primary btn-primary-small flex-shrink-0">Connect</button>
				</div>
			`;
		}

		heroBusyHtml(label) {
			return `
				<div class="rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] p-5 flex items-center gap-4">
					<div class="flex-shrink-0 inline-block animate-spin rounded-full h-9 w-9 border-4 border-blue-600 border-t-transparent"></div>
					<div>
						<div class="text-[11px] font-bold tracking-wide uppercase text-[#1E40AF] mb-0.5">This workstation</div>
						<div class="text-base font-semibold text-[#1E40AF]">${label}</div>
					</div>
				</div>
			`;
		}

		heroErrorHtml(message) {
			return `
				<div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] p-5 flex items-center gap-4">
					<div class="flex-shrink-0 w-[52px] h-[52px] rounded-lg bg-white border border-[#FECACA] flex items-center justify-center text-[#B91C1C] font-bold text-lg">!</div>
					<div class="flex-1 min-w-0">
						<div class="text-[11px] font-bold tracking-wide uppercase text-[#B91C1C] mb-0.5">This workstation</div>
						<div class="text-base font-semibold text-[#B91C1C]">${message || "Couldn't connect"}</div>
						<button type="button" class="stripe-card-reader-reconnect card-reader-quiet-link mt-2">Retry</button>
					</div>
				</div>
			`;
		}

		heroConnectedHtml(reader) {
			const modelLabel = CardReaderSettings.deviceTypeLabel(reader.device_type);
			const icon = StripeReaderIdentity.deviceIcon(reader.device_type, 'w-6 h-6');
			return `
				<div class="rounded-xl border border-[#A7F3D0] bg-[#ECFDF5] p-5 flex items-start gap-4">
					<div class="flex-shrink-0 w-[52px] h-[52px] rounded-lg bg-white shadow-sm flex items-center justify-center text-[#065F46]">
						${icon}
					</div>
					<div class="flex-1 min-w-0">
						<div class="text-[11px] font-bold tracking-wide uppercase text-[#065F46] opacity-80 mb-0.5">This workstation</div>
						<div class="flex items-center gap-2.5 flex-wrap">
							<span class="text-lg font-bold text-[#020817]">${reader.label || reader.id}</span>
							<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#065F46] bg-white border border-[#A7F3D0] rounded-full px-2.5 py-1">
								<span class="inline-block w-1.5 h-1.5 rounded-full bg-[#15803D]"></span>Connected
							</span>
						</div>
						<div class="text-sm text-[#6B7280] mt-0.5">${modelLabel} · <code class="text-xs">${reader.id}</code></div>
						<div class="text-xs text-[#6B7280] mt-1.5">Verified just now</div>
						<div class="mt-3">
							<button type="button" class="stripe-card-reader-disconnect card-reader-quiet-link">Disconnect for today</button>
						</div>
					</div>
					${this.renderOverflowMenu(reader.id)}
				</div>
			`;
		}

		// -- Readers at this location --------------------------------------

		async refreshReaders(silent = false) {
			if (!this.terminalLocationId) return;
			if (!silent) this.displayLoading();
			try {
				const discoverResult = await this.cardReader.terminal.discoverReaders({
					method: 'internet',
					location: this.terminalLocationId,
				});
				if (discoverResult.error) {
					this.setStatus('Discovery failed', discoverResult.error.message || 'Stripe returned an error.', 'error');
					this.displayLoading(false);
					return;
				}
				this.readers = discoverResult.discoveredReaders || [];
				this.renderReaderList();
				this.logConsole('info', 'Discovery', `${this.readers.length} reader${this.readers.length === 1 ? '' : 's'} found at ${this.terminalLocationId}`);
				if (!silent) this.hideStatus();
			} catch (err) {
				this.setStatus('Discovery error', err.message || String(err), 'error');
			}
			this.displayLoading(false);
		}

		renderReaderList() {
			if (!this.readers.length) {
				$('#reader-list').html(`
					<div class="text-sm text-[#6B7280] border-2 border-dashed border-[#E5E7EB] rounded-lg p-6 text-center">
						${this.terminalLocationId ? 'No readers registered to this location yet. Pair one to get started.' : 'Set a Stripe Terminal Location on the Tour Operator record to see readers here.'}
					</div>
				`);
				return;
			}
			$('#reader-list').html(this.readers.map((r) => this.renderReaderRow(r)).join(''));
		}

		renderReaderRow(reader) {
			const isActive = reader.id === this.activeReaderId;
			const statusDotColor = CardReaderSettings.readerStatusDotColor(reader.status);
			const modelLabel = CardReaderSettings.deviceTypeLabel(reader.device_type);
			const icon = StripeReaderIdentity.deviceIcon(reader.device_type, 'w-5 h-5');

			return `
				<div data-readerid="${reader.id}"
					class="flex items-center gap-3.5 p-3.5 rounded-lg border ${isActive ? 'border-[#A7F3D0] bg-[#ECFDF5]' : 'border-[#E5E7EB] bg-white'}">
					<div class="flex-shrink-0 w-10 h-10 rounded-md bg-white border ${isActive ? 'border-[#A7F3D0] text-[#065F46]' : 'border-[#E5E7EB] text-[#6B7280]'} flex items-center justify-center">
						${icon}
					</div>
					<div class="flex-1 min-w-0">
						<div class="font-semibold text-sm text-[#020817] truncate">${reader.label || '(no label)'}</div>
						<div class="text-xs text-[#6B7280] mt-0.5 flex items-center gap-1.5">
							<span class="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0" style="background-color:${statusDotColor};"></span>
							<span>${reader.status || 'unknown'} · ${modelLabel} · SN ${reader.serial_number || 'n/a'}</span>
						</div>
					</div>
					<div class="flex items-center gap-2.5 flex-shrink-0">
						${isActive
							? `<span class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#065F46] bg-white border border-[#A7F3D0] rounded-full px-3 py-1.5"><span class="inline-block w-1.5 h-1.5 rounded-full bg-[#15803D]"></span>Connected</span>`
							: `<button type="button" class="use-this-reader btn-outline-primary-small">Use this reader</button>`
						}
						${this.renderOverflowMenu(reader.id)}
					</div>
				</div>
			`;
		}

		// Shared by the hero card and each reader row — the only place
		// "Forget this reader" lives, tucked behind ⋯ rather than sitting at
		// the same visual weight as the safe, reversible actions.
		renderOverflowMenu(readerId) {
			return `
				<div class="relative overflow-wrap">
					<button type="button" class="overflow-toggle w-8 h-8 rounded-md text-[#6B7280] hover:bg-[#F3F4F6] hover:text-[#020817] text-lg leading-none">⋯</button>
					<div class="overflow-menu hidden absolute right-0 top-9 z-10 w-64 bg-white border border-[#E5E7EB] rounded-lg shadow-lg p-3">
						<div class="text-xs bg-[#FFFBEB] border border-[#FED7AA] text-[#B45309] rounded-md p-2 mb-2">
							Forgetting removes this reader's Stripe registration — it'll need a brand-new pairing code to reconnect.
						</div>
						<button type="button" data-readerid="${readerId}"
							class="forget-reader block w-full text-left text-xs font-semibold text-[#B91C1C] underline">
							Forget this reader…
						</button>
					</div>
				</div>
			`;
		}

		toggleOverflow(evt) {
			evt.stopPropagation();
			const menu = $(evt.currentTarget).siblings('.overflow-menu');
			$('.overflow-menu').not(menu).addClass('hidden');
			menu.toggleClass('hidden');
		}

		async forgetReader(evt) {
			const readerId = $(evt.currentTarget).data('readerid');
			if (!readerId) return;
			if (!confirm(`Forget reader ${readerId}? This removes its registration from Stripe — the physical device will need a brand-new pairing code before it can connect again. This cannot be undone.`)) {
				return;
			}

			try {
				await this.postAction(`${this.API}?action=unpair_reader`, { reader_id: readerId });
				this.readers = this.readers.filter((r) => r.id !== readerId);

				if (this.activeReaderId === readerId || this.cardReader.getSavedReader()?.id === readerId) {
					StripeReaderIdentity.clear();
					this.activeReaderId = null;
					this.heroState = 'idle';
					this.heroMeta = {};
				}

				$('.overflow-menu').addClass('hidden');
				this.hideStatus();
				this.logConsole('info', 'Reader forgotten', `${readerId} removed from Stripe`);
				this.renderHero();
				this.renderReaderList();
			} catch (err) {
				this.setStatus('Delete failed', err.message || String(err), 'error');
			}
		}

		// -- Pair a new reader ---------------------------------------------

		pairReader() {
			if (!this.terminalLocationId) {
				this.setStatus(
					'No terminal location configured',
					'Set <strong>Stripe Terminal Location ID</strong> on the Tour Operator record before pairing a reader.',
					'warning'
				);
				return;
			}
			$("#pair-reader-modal #location_id").val(this.terminalLocationId);
			$("#pair-reader-modal").modal("show");
		}

		async pairReaderSubmit() {
			const formData = $("#pair-reader-form").serializeArray();
			let validationErrors = [];
			const payload = formData.reduce((acc, curr) => {
				if (curr.value.trim() === "") {
					validationErrors.push(`${curr.name} can not be blank`);
				}
				acc[curr.name] = curr.value.trim();
				return acc;
			}, {});

			if (validationErrors.length > 0) {
				alert(validationErrors.join("\n"));
				validationErrors.forEach((e) => CardReaderSettings.writeToConsole(e));
				return false;
			}

			payload.label = this.slug(payload.label);

			try {
				const resp = await this.postAction(`${this.API}?action=pair_reader`, payload);
				$("#pair-reader-modal").modal("hide");
				const newId = resp.result.reader_id;
				StripeReaderIdentity.save({
					id: newId,
					label: resp.result.reader_label,
					device_type: resp.result.reader_device_type,
				});
				this.hideStatus();
				this.logConsole('success', 'Reader paired', `${resp.result.reader_label || newId} (${newId})`);
				// Refresh the list so the new reader appears, then connect to
				// it immediately — pairing and using it is one step, not two.
				await this.refreshReaders(true);
				await this.cardReader.connectToReader('settings-page', false, newId, null);
			} catch (err) {
				this.setStatus('Pairing failed', err.message || String(err), 'error');
			}
		}

		// -- Account & Location (collapsed reference panel) ------------------

		async getAccountSettings() {
			try {
				const response = await this.postAction(`${this.API}?action=get_account_settings`);
				this.terminalLocationId = response.result.terminal_location_id || null;

				$('#account-location-body').html(this.renderAccountLocationBody(response.result));
				await this.renderLocationSubtitle();

				if (!this.terminalLocationId) {
					this.setStatus(
						'No terminal location configured',
						'Set <strong>Stripe Terminal Location ID</strong> on the Tour Operator record before discovering or pairing readers.',
						'warning'
					);
				} else {
					this.hideStatus();
					this.logConsole('info', 'Account loaded', `terminal location ${this.terminalLocationId}`);
				}
			} catch (err) {
				this.setStatus('Account settings failed', err.message || String(err), 'error');
			}
		}

		// Looks up the terminal location's real display name (e.g. "Antelope
		// Point Marina") for the "Readers at this location" subtitle, rather
		// than showing just the raw location id or a hardcoded name.
		async renderLocationSubtitle() {
			if (!this.terminalLocationId) {
				$('#location-sub').text('No terminal location configured yet');
				return;
			}
			$('#location-sub').text(`Location ${this.terminalLocationId} · pick one to use on this workstation`);
			try {
				const response = await this.postAction(`${this.API}?action=get_location`, { location_id: this.terminalLocationId });
				const displayName = response.result?.display_name;
				if (displayName) {
					$('#location-sub').text(`${displayName} · pick one to use on this workstation`);
				}
			} catch (err) {
				// Keep the location-id fallback already shown — this is
				// supporting copy, not worth surfacing an error banner for.
			}
		}

		renderAccountLocationBody(result) {
			const s = result.settings || {};
			const locationId = result.terminal_location_id;

			const row = (label, value) => `
				<div class="flex border-b border-[#E5E7EB] py-2 last:border-b-0">
					<div class="w-1/2 text-[#6B7280] text-sm">${label}</div>
					<div class="w-1/2 text-[#020817] text-sm break-all">${value ?? '—'}</div>
				</div>
			`;

			const boolBadge = (v) => v
				? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#DCFCE7] text-[#065F46]">enabled</span>'
				: '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#FEE2E2] text-[#B91C1C]">disabled</span>';

			const locationRow = locationId
				? row('Terminal Location', `<a href="javascript:void(0);" class="show-location-details text-[#1E40AF] underline" data-locationid="${locationId}"><code>${locationId}</code></a>`)
				: row('Terminal Location', '<span class="text-[#B91C1C]">not configured on Tour Operator</span>');

			return `
				<div class="border border-[#E5E7EB] rounded-lg bg-white p-4">
					${row('Account ID', `<code>*****</code>`)}
					${row('Business Name', s.business_name)}
					${row('Country', s.country ? s.country.toUpperCase() : '—')}
					${row('Default Currency', s.default_currency ? s.default_currency.toUpperCase() : '—')}
					${row('Charges Enabled', boolBadge(s.charges_enabled))}
					${locationRow}
				</div>
			`;
		}

		// -- Location details modal --------------------------------------

		async showLocationDetails(evt) {
			const link = $(evt.target).closest('[data-locationid]');
			const locationId = link.data('locationid');
			if (!locationId) return;

			const body = $('#location-details-modal #location-details-body');
			body.html('<div class="text-sm text-[#6B7280]">Loading location details…</div>');
			$('#location-details-modal').modal('show');

			try {
				const response = await this.postAction(`${this.API}?action=get_location`, { location_id: locationId });
				const loc = response.result;
				if (!loc) {
					body.html(`<div class="text-sm text-[#B91C1C]">Could not load location. ${(response.errors || []).join(' ') || ''}</div>`);
					return;
				}
				body.html(this.renderLocationDetailsBody(loc));
			} catch (err) {
				body.html(`<div class="text-sm text-[#B91C1C]">Error: ${err.message || err}</div>`);
			}
		}

		renderLocationDetailsBody(loc) {
			const a = loc.address || {};
			const cityLine = [a.city, a.state, a.postal_code].filter(Boolean).join(', ');
			const addressLines = [a.line1, a.line2, cityLine, (a.country || '').toUpperCase()]
				.filter(Boolean)
				.map((line) => `<div>${line}</div>`)
				.join('');
			const modeBadge = loc.livemode === true
				? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#DCFCE7] text-[#065F46]">live</span>'
				: loc.livemode === false
					? '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#FEE2E2] text-[#B91C1C]">test</span>'
					: '';

			return `
				<div class="flex flex-col gap-3">
					<div>
						<div class="text-xs text-[#6B7280]">Display Name</div>
						<div class="font-medium text-[#020817]">${loc.display_name || '—'} ${modeBadge}</div>
					</div>
					<div>
						<div class="text-xs text-[#6B7280]">Location ID</div>
						<div class="text-sm break-all"><code>${loc.id}</code></div>
					</div>
					<div>
						<div class="text-xs text-[#6B7280]">Address</div>
						<div class="text-sm text-[#020817]">${addressLines || '—'}</div>
					</div>
				</div>
			`;
		}

		// -- Static helpers ----------------------------------------------

		// Delegates to the shared StripeReaderIdentity module (see
		// public/js/utils/stripe-reader-identity.js) so the reader
		// name/model/icon logic has one source of truth shared with the
		// in-payment Card Reader tab, instead of two copies drifting apart.
		static deviceTypeLabel(deviceType) {
			return StripeReaderIdentity.deviceTypeLabel(deviceType);
		}

		static readerStatusDotColor(status) {
			// Inline-styled rather than a Tailwind class because the
			// bg-[#...] variants for these greens/reds/ambers are not in
			// the project's compiled app.css.
			switch ((status || '').toLowerCase()) {
				case 'online':
					return '#15803D';
				case 'offline':
					return '#DC2626';
				default:
					return '#EA6C0A';
			}
		}

		static writeToConsole(message) {
			const consoleDiv = document.getElementById('console');
			if (!consoleDiv) return;
			const newMessage = document.createElement('div');
			newMessage.textContent = message;
			consoleDiv.appendChild(newMessage);
			consoleDiv.scrollTop = consoleDiv.scrollHeight;
		}
	}

	$(function () {
		new CardReaderSettings();
	});
</script>
@endsection
