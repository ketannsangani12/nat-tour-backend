@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6">
					<h4 class="text-xl font-semibold mb-4">Epson Printer Configuration</h4>
					<div class="flex items-center justify-between mb-6">
						<p class="text-sm text-gray-500 mb-0">Configure your Epson TM Series thermal printer for network printing. Settings are saved locally on this device.</p>
						<i class="fas fa-print"></i>
					</div>
					 <hr style="margin-top: 1rem; margin-bottom: 1rem; border: 1px solid #f19d3e;" />
					<!-- Status Alert -->
					<div id="status-alert" class="hidden mb-4 p-4 rounded-lg"></div>

					<!-- Configuration Form -->
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6" style="background-color: #e7e7e7ff; padding: 15px; border-radius: 8px;">
						<!-- Left Column - Settings -->
						<div>
							<div class="mb-4">
								<label class="form-custom-label" for="printer_name">Printer Name</label>
								<input type="text" id="printer_name" class="form-control form-custom-input" placeholder="e.g., Front Counter Printer">
								<small class="text-gray-500">A friendly name to identify this printer</small>
							</div>

							<div class="mb-4">
								<label class="form-custom-label" for="printer_ip">Printer IP Address</label>
								<div class="flex gap-2">
									<input type="text" id="printer_ip" class="form-control form-custom-input flex-1" placeholder="e.g., 192.168.1.100">
									<button type="button" id="btn-test-connectivity" class="btn btn-outline-secondary">Test</button>
									<button type="button" id="btn-setup-cert" class="btn btn-warning" title="Accept printer certificate (one-time setup)">
										<i class="fas fa-certificate" style="color:#5ea4d3;"></i>
									</button>
								</div>
								<small id="connectivity-status" class="text-gray-500">Enter the IP address of your Epson printer</small>
							</div>

							<div class="mb-4">
								<label class="form-custom-label" for="paper_size">Paper Size</label>
								<input type="text" id="paper_size" class="form-control form-custom-input" value="80mm" readonly >
							</div>

							<div class="mb-4">
								<label class="form-custom-label" for="protocol">Connection Protocol</label>
								<input type="text" id="protocol" class="form-control form-custom-input" value="https" readonly>
							</div>

							<div class="mb-4">
								<div class="form-check">
									<input type="checkbox" id="auto_cut" class="form-check-input" checked>
									<label class="form-check-label" for="auto_cut">Auto-cut paper after printing</label>
								</div>
							</div>

							<div class="mb-4">
								<div class="form-check">
									<input type="checkbox" id="open_drawer" class="form-check-input" checked>
									<label class="form-check-label" for="open_drawer">Open cash drawer on cash receipts</label>
								</div>
								<small class="text-gray-500">Requires drawer connected via RJ-12 cable</small>
							</div>

							<button type="button" id="btn-save-settings" 
							class="btn btn-sm btn-primary btn-primary-small float-end">Save Settings</button>

						
						</div>

						<!-- Right Column - Connection & Test -->
						<div>


							<!-- Connection Status -->
							<div class="m-4 p-4 border rounded-lg bg-white">
								<h5 class="font-semibold mb-3">Connection Status</h5>
								<div id="connection-status" class="flex items-center gap-2 mb-3">
									<span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span>
									<span>Not Connected</span>
								</div>
								<div class="flex flex-wrap gap-2">
									<button type="button" id="btn-connect" class="btn btn-sm btn-success btn-success-small">Connect</button>
									<button type="button" id="btn-disconnect" class="btn btn-secondary btn-secondary-small" disabled>Disconnect</button>
									<button type="button" id="btn-test-print" class="btn btn-info btn-info-small" disabled>Print Test Receipt</button>

									<button type="button" id="delete-printer-config" href="#" class="btn btn-sm btn-danger btn-danger-small float-end" disabled
									onclick="localStorage.removeItem('epsonPrinterConfig'); alert('Printer configuration deleted from local storage. Please refresh the page.'); return false;">
										Delete Printer Configuration
									</button>


								</div>
							</div>

							<!-- Printer Web Config Link -->
							<div id="printer-config-link" class="mb-4 p-4 bg-blue-50 rounded-lg hidden">
								<h5 class="font-semibold mb-2">Printer Web Configuration</h5>
								<p class="text-sm mb-2">Login: <code>epson</code> / Password: <code>serial number</code> (on back of printer)</p>
								<a id="printer-web-link" href="#" target="_blank" class="btn btn-sm btn-outline-primary">Open Printer Settings</a>


							</div>

							<!-- Setup Instructions -->
							<div class="p-4 border rounded-lg bg-white">
								<h5 class="font-semibold mb-3">Setup Instructions</h5>
								<ol class="list-decimal list-inside text-sm text-gray-700">
									<li>Ensure your Epson TM series printer is powered on and connected to the same network as this device.</li>
									<li>Find the printer's IP address via the printer's network settings or by printing a network status sheet.</li>
									<li>Enter the printer's IP address above and test connectivity.</li>
									<li>click the certificate <i class="fas fa-certificate" style="color:#5ea4d3;"></i> button to accept the printer's SSL certificate (one-time setup).</li>
									<li>Click "Connect" to establish a connection to the printer.</li>
									<li>Print a test receipt to verify everything is working correctly.</li>
								</ol>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

@endsection

@section('js-libraries')
<script src="{{ asset('js/vendor/epos-2.27.0.js') }}"></script>
@endsection

@section('js-custom')
<script>
class PrinterSettings {
	constructor() {
		this.ePosDev = null;
		this.printer = null;
		this.isConnected = false;
		this.STORAGE_KEY = 'epsonPrinterConfig';
		this.config = {
			printerName: '',
			printerIP: '',
			paperSize: '80mm',
			protocol: 'https',
			autoCut: true,
			openDrawer: true
		};
		this.init();
	}

	init() {
		this.loadSettings();
		this.initEvents();
		this.updatePrinterConfigLink();
	}

	loadSettings() {
		const saved = localStorage.getItem(this.STORAGE_KEY);
		if (saved) {
			try {
				this.config = { ...this.config, ...JSON.parse(saved) };
			} catch (e) {
				console.error('Failed to parse printer config:', e);
			}
			
			$('#delete-printer-config').prop('disabled', false);
		}
		$('#printer_name').val(this.config.printerName);
		$('#printer_ip').val(this.config.printerIP);
		$('#paper_size').val(this.config.paperSize);
		$('#protocol').val(this.config.protocol);
		$('#auto_cut').prop('checked', this.config.autoCut);
		$('#open_drawer').prop('checked', this.config.openDrawer);
	}

	saveSettings() {
		this.config = {
			printerName: $('#printer_name').val(),
			printerIP: $('#printer_ip').val(),
			paperSize: $('#paper_size').val(),
			protocol: $('#protocol').val(),
			autoCut: $('#auto_cut').is(':checked'),
			openDrawer: $('#open_drawer').is(':checked')
		};
		localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.config));
		this.showAlert('Settings saved successfully!', 'success');
		$("#delete-printer-config").prop('disabled', false);
		this.updatePrinterConfigLink();
	}

	getConfig() {
		return this.config;
	}

	initEvents() {
		$('#btn-save-settings').on('click', () => this.saveSettings());
		$('#btn-test-connectivity').on('click', () => this.testConnectivity());
		$('#btn-scan-network').on('click', () => this.scanNetwork());
		$('#btn-connect').on('click', () => this.connect());
		$('#btn-disconnect').on('click', () => this.disconnect());
		$('#btn-test-print').on('click', () => this.printTestReceipt());
		$('#btn-setup-cert').on('click', () => this.openCertificateSetup());
		$('#printer_ip').on('change', () => this.updatePrinterConfigLink());
	}

	openCertificateSetup() {
		const ip = $('#printer_ip').val();
		if (!ip) {
			this.showAlert('Please enter a printer IP address first', 'warning');
			return;
		}
		const port = $('#protocol').val() === 'https' ? 8043 : 8008;
		const protocol = $('#protocol').val();
		window.open(`${protocol}://${ip}:${port}`, '_blank');
	}

	updatePrinterConfigLink() {
		const ip = $('#printer_ip').val();
		if (ip) {
			$('#printer-config-link').removeClass('hidden');
			$('#printer-web-link').attr('href', `https://${ip}`);
		} else {
			$('#printer-config-link').addClass('hidden');
		}
	}

	showAlert(message, type = 'info') {
		const alertClass = {
			'success': 'bg-green-100 text-green-800 border-green-200',
			'error': 'bg-red-100 text-red-800 border-red-200',
			'info': 'bg-blue-100 text-blue-800 border-blue-200',
			'warning': 'bg-yellow-100 text-yellow-800 border-yellow-200'
		};
		$('#status-alert')
			.removeClass('hidden bg-green-100 bg-red-100 bg-blue-100 bg-yellow-100 text-green-800 text-red-800 text-blue-800 text-yellow-800')
			.addClass(alertClass[type] || alertClass.info)
			.html(message);
		setTimeout(() => $('#status-alert').addClass('hidden'), 5000);
	}

	updateConnectionStatus(connected, message = '') {
		this.isConnected = connected;
		const statusHtml = connected
			? `<span class="inline-block w-3 h-3 rounded-full bg-green-500"></span><span class="text-green-700">Connected${message ? ' - ' + message : ''}</span>`
			: `<span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span><span>${message || 'Not Connected'}</span>`;
		$('#connection-status').html(statusHtml);
		$('#btn-connect').prop('disabled', connected);
		$('#btn-disconnect').prop('disabled', !connected);
		$('#btn-test-print').prop('disabled', !connected);
		
	}

	async testConnectivity() {
		const ip = $('#printer_ip').val();
		if (!ip) {
			this.showAlert('Please enter an IP address first', 'warning');
			return;
		}

		$('#connectivity-status').html('<span class="text-blue-600">Testing connectivity...</span>');

		try {
			const controller = new AbortController();
			const timeoutId = setTimeout(() => controller.abort(), 3000);

			await fetch(`https://${ip}:8043/`, { method: 'HEAD', mode: 'no-cors', signal: controller.signal });
			clearTimeout(timeoutId);

			$('#connectivity-status').html('<span class="text-green-600">Printer is reachable!</span>');
		} catch (e) {
			try {
				const controller = new AbortController();
				const timeoutId = setTimeout(() => controller.abort(), 3000);
				await fetch(`http://${ip}:8008/`, { method: 'HEAD', mode: 'no-cors', signal: controller.signal });
				clearTimeout(timeoutId);
				$('#connectivity-status').html('<span class="text-green-600">Printer is reachable (HTTP)</span>');
			} catch {
				$('#connectivity-status').html('<span class="text-red-600">Cannot reach printer. Check IP and network.</span>');
			}
		}
	}

	async scanNetwork() {
		const prefix = $('#network_prefix').val() || '192.168.1';
		$('#scan-results').html('<span class="text-blue-600">Scanning network...</span>');
		$('#btn-scan-network').prop('disabled', true);

		const commonOctets = [1, 2, 3, 100, 101, 102, 150, 151, 152, 200, 201, 202, 250, 251, 252, 253, 254];
		const found = [];

		for (let i = 0; i < commonOctets.length; i += 5) {
			const batch = commonOctets.slice(i, i + 5);
			const results = await Promise.all(batch.map(async (octet) => {
				const ip = `${prefix}.${octet}`;
				try {
					const controller = new AbortController();
					setTimeout(() => controller.abort(), 2000);
					await fetch(`https://${ip}:80/`, { method: 'HEAD', mode: 'no-cors', signal: controller.signal });
					return ip;
				} catch {
					return null;
				}
			}));
			found.push(...results.filter(Boolean));
		}

		$('#btn-scan-network').prop('disabled', false);

		if (found.length > 0) {
			const html = found.map(ip =>
				`<div class="flex items-center justify-between py-1">
					<span>${ip}</span>
					<button type="button" class="btn btn-sm btn-outline-success" onclick="printerSettings.selectIP('${ip}')">Select</button>
				</div>`
			).join('');
			$('#scan-results').html(`<div class="text-green-600 mb-2">Found ${found.length} device(s):</div>${html}`);
		} else {
			$('#scan-results').html('<span class="text-gray-500">No devices found. Try entering the IP manually.</span>');
		}
	}

	selectIP(ip) {
		$('#printer_ip').val(ip);
		this.updatePrinterConfigLink();
		this.showAlert(`Selected printer at ${ip}`, 'success');
	}

	connect() {
		const ip = $('#printer_ip').val();
		const protocol = $('#protocol').val();

		if (!ip) {
			this.showAlert('Please enter a printer IP address', 'warning');
			return;
		}

		if (typeof epson === 'undefined' || typeof epson.ePOSDevice === 'undefined') {
			this.showAlert('Epson SDK not loaded. Please refresh the page.', 'error');
			return;
		}

		this.updateConnectionStatus(false, 'Connecting...');
		$('#btn-connect').prop('disabled', true);

		const port = protocol === 'https' ? 8043 : 8008;

		try {
			this.ePosDev = new epson.ePOSDevice();

			this.ePosDev.connect(ip, port, (data) => {
				if (data === 'OK' || data === 'SSL_CONNECT_OK') {
					this.ePosDev.createDevice('local_printer', this.ePosDev.DEVICE_TYPE_PRINTER,
						{ crypto: false, buffer: false },
						(deviceObj, errorCode) => {
							if (errorCode === 'OK') {
								this.printer = deviceObj;
								this.updateConnectionStatus(true, 'Ready');
								this.showAlert('Printer connected successfully!', 'success');
							} else {
								this.updateConnectionStatus(false, `Device error: ${errorCode}`);
								this.showAlert(`Failed to create printer device: ${errorCode}`, 'error');
							}
						}
					);
				} else {
					this.updateConnectionStatus(false, `Connection failed: ${data}`);
					this.showAlert(`Connection failed: ${data}`, 'error');
				}
			});

			this.ePosDev.onerror = (error) => {
				this.updateConnectionStatus(false, `Error: ${error.status}`);
				this.showAlert(`Connection error: ${error.status}`, 'error');
			};
		} catch (e) {
			this.updateConnectionStatus(false, 'Connection error');
			this.showAlert(`Error: ${e.message}`, 'error');
		}
	}

	disconnect() {
		if (this.ePosDev) {
			this.ePosDev.disconnect();
			this.ePosDev = null;
			this.printer = null;
			this.updateConnectionStatus(false, 'Disconnected');
			this.showAlert('Printer disconnected', 'info');
		}
	}

	printTestReceipt() {
		if (!this.printer) {
			this.showAlert('Printer not connected', 'error');
			return;
		}

		const printerName = $('#printer_name').val() || 'TEST STORE';
		const paperSize = $('#paper_size').val();
		const autoCut = $('#auto_cut').is(':checked');

		try {
			this.printer.onreceive = (response) => {
				if (response.success) {
					this.showAlert('Test receipt printed successfully!', 'success');
				} else {
					this.showAlert(`Print error: ${response.code}`, 'error');
				}
			};

			this.printer.onerror = (error) => {
				this.showAlert(`Printer error: ${error.status}`, 'error');
			};

			// Build receipt
			this.printer.addTextAlign(this.printer.ALIGN_CENTER);
			this.printer.addTextSize(2, 2);
			this.printer.addText(`${printerName}\n`);
			this.printer.addTextSize(1, 1);
			this.printer.addText('123 Main Street\n');
			this.printer.addText('City, State 12345\n');
			this.printer.addFeedLine(1);

			this.printer.addTextAlign(this.printer.ALIGN_LEFT);
			this.printer.addText('--------------------------------\n');

			this.printer.addTextAlign(this.printer.ALIGN_CENTER);
			this.printer.addTextStyle(false, false, true, this.printer.COLOR_1);
			this.printer.addText('TEST RECEIPT\n');
			this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
			this.printer.addFeedLine(1);

			const now = new Date();
			this.printer.addTextAlign(this.printer.ALIGN_LEFT);
			this.printer.addText(`Date: ${now.toLocaleDateString()}\n`);
			this.printer.addText(`Time: ${now.toLocaleTimeString()}\n`);
			this.printer.addText(`Paper: ${paperSize}\n`);
			this.printer.addText('--------------------------------\n');
			this.printer.addFeedLine(1);

			this.printer.addText('General Tour (2 pax)	$100.00\n');
			this.printer.addText('Add On				$20.00\n');
			this.printer.addText('--------------------------------\n');

			this.printer.addTextAlign(this.printer.ALIGN_RIGHT);
			this.printer.addText('Subtotal: $120.00\n');
			this.printer.addText('Tax (7%): $8.40\n');
			this.printer.addFeedLine(1);

			this.printer.addTextSize(2, 2);
			this.printer.addTextStyle(false, false, true, this.printer.COLOR_1);
			this.printer.addText('TOTAL: $128.40\n');
			this.printer.addTextStyle(false, false, false, this.printer.COLOR_1);
			this.printer.addTextSize(1, 1);
			this.printer.addFeedLine(1);

			this.printer.addTextAlign(this.printer.ALIGN_CENTER);
			this.printer.addText('Thank you!\n');
			this.printer.addFeedLine(2);

			if (autoCut) {
				this.printer.addCut(this.printer.CUT_FEED);
			}

			this.printer.send();
		} catch (e) {
			this.showAlert(`Print error: ${e.message}`, 'error');
		}
	}
}

let printerSettings;
$(document).ready(function() {
	printerSettings = new PrinterSettings();
});
</script>
@endsection
