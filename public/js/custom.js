$(function ($) {
	$(".toggle-password").on('click', function () {
		$(this).find('i').toggleClass("fa-eye fa-eye-slash");
		var input = $($(this).data("toggle"));

		if (input.attr("type") == "password")
			input.attr("type", "text");
		else
			input.attr("type", "password");
	});

	$('#toggleEncryptedValue').on('click', function () {
		const input = $('.toggle-encrypted-value');
		const icon = $('#toggleIcon');
		if (input.attr("type") === 'password') {
			input.attr("type", 'text');
			icon.removeClass('fa-eye');
			icon.addClass('fa-eye-slash');
		} else {
			input.attr("type", 'password');
			icon.removeClass('fa-eye-slash');
			icon.addClass('fa-eye');
		}
	});
	$.ajaxSetup({
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		}
	});

	$(window).on('keyup', function (event) {
		if (event.key == 'Escape')
			if ($('#loading-container').css('display') == 'block')
				hideLoading();
	});
});

// encode Less Than character to prevent intentional/unintentional HTML injection
function encLT(str) { return str.replace(/</g, '&lt;') }

/* generate random password*/
function randomPassword(length) {
	var chars = "abcdefghijklmnopqrstuvwxyz!@#$%^&*()-+<>ABCDEFGHIJKLMNOP1234567890";
	var pass = "";
	for (var x = 0; x < length; x++) {
		var i = Math.floor(Math.random() * chars.length);
		pass += chars.charAt(i);
	}
	return pass;
}

function generate() {
	adminfrm.password.value = randomPassword(adminfrm.length.value);
}

function previewMedia(file, previewEl) {
	var reader = new FileReader();

	reader.onload = function () {
		previewEl.src = reader.result;
	}

	reader.readAsDataURL(file);
}

function chkFileExt(elFile) {
	var allowedExts = elFile.getAttribute('data-allowed-ext').toLowerCase().split(',');
	var valid = [];

	for (var i = 0; i < elFile.files.length; i++) {
		var fileExt = elFile.files[i].name.substr(elFile.files[i].name.lastIndexOf('.') + 1).toLowerCase();
		valid[i] = 0;

		for (var j = 0; j < allowedExts.length; j++) {
			if (fileExt == allowedExts[j]) {
				valid[i] = 1;
				break;
			}
		}
	}

	if (valid.join('').indexOf('0') != -1) {
		swal('Invalid file selected', 'Please select file(s) of allowed type.', 'error');
		elFile.value = '';
		return false;
	}

	return true;
}

function getStates(selCountry, selStatesSelector) {
	var countryId = selCountry.options[selCountry.selectedIndex].value;

	if (countryId == '') {
		$(selStatesSelector).html('<option value="">Select a Country First</option>');
		$('#city').html('<option value="">Select a State First</option>');
		return;
	}

	showLoading();

	$.getJSON(getStatesUrl + countryId)
		.done(function (states) {
			$('#city').html('<option value="">Select a State First</option>');
			var arStates = ['<option value="">Select State</option>'];

			for (var i = 0; i < states.length; i++)
				arStates.push('<option value="' + states[i].id + '">' + states[i].name + '</option>');

			$(selStatesSelector).html(arStates.join(''));
		})
		.fail(function () {
			swal('Error occurred', 'Unable to get states of the country from server.', 'error');
		})
		.always(hideLoading);
}

function getCities(selState, selCitySelector) {
	var stateId = selState.options[selState.selectedIndex].value;

	if (stateId == '') {
		$(selCitySelector).html('<option value="">Select a State First</option>');
		return;
	}

	showLoading();

	$.getJSON(getCitiesUrl + stateId)
		.done(function (cities) {
			var arCities = ['<option value="">Select City</option>'];

			for (var i = 0; i < cities.length; i++)
				arCities.push('<option value="' + cities[i].id + '">' + cities[i].name + '</option>');

			$(selCitySelector).html(arCities.join(''));
		})
		.fail(function () {
			swal('Error occurred', 'Unable to get fetch cities of the state from server.', 'error');
		})
		.always(hideLoading);
}

function showLoading() {
	$('#loading-container').removeClass('hidden').css('display', 'block');
}

function hideLoading() {
	$('#loading-container').addClass('hidden').css('display', 'none');
}

function submitForm(form) {
	var $form = $(form);
	showLoading();

	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: $form.serialize(),
	})
		.done(function (data) {

			if (typeof $form.data('call-on-success') !== 'undefined')
				window[$form.data('call-on-success')](form, data);

			if (typeof $form.data('reset-form-on-success') !== 'undefined')
				form.reset();

			if (typeof $form.data('on-success-blank-inputs') != 'undefined')
				$($form.data('on-success-blank-inputs')).val('');

			if (typeof $form.data('on-success-msg') !== 'undefined') {
				if (typeof $form.data('redirect-on-success') !== 'undefined' && data.data != "exists_as_customer") {
					swal($form.data('on-success-msg'), '', 'success')
						.then(function () {
							showLoading();
							setTimeout(function () {
								location = $form.data('redirect-on-success');
							}, 5000);
						});
					setTimeout(function () {
						location = $form.data('redirect-on-success');
					}, 5000);
					return;
				} else if (data.data == "exists_as_customer") {
					swal({
						title: "Are you sure?",
						text: "Email address is exist as a customer, Do you want to change as Affiliate",
						icon: "warning",
						buttons: {
							no: {
								text: 'No',
								value: false,
							},
							yes: {
								text: 'Yes',
								className: 'btn-danger',
							}
						}
					}).then(function (result) {
						if (result) {
							$.ajax({
								headers: { Accept: 'application/json' },
								dataType: 'json',
								type: 'POST',
								url: data.url,
								data: {
									data: data
								},
							})
								.done(function (data) {
									swal("Customer is converted as Affiliate", 'Page will reload', 'success').then(function () {
										window.location.replace(data.url);
									});
								})
								.fail(showSubmissionError)
								.always(hideLoading);
						}
					});
				} else {
					swal($form.data('on-success-msg'), '', 'success');
				}
			}

			if (typeof $form.data('redirect-on-success') !== 'undefined') {
				showLoading();
				setTimeout(function () {
					location = $form.data('redirect-on-success');
				}, 100000);
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);

	return false;
}
function submitWaiverForm(form) {
	var $form = $(form);
	showLoading();
	// Sync Quill content to textarea
	if (typeof quill !== "undefined") {
		document.getElementById('waiver_content').value = quill.root.innerHTML;
	}

	var formData = new FormData(form);


	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: formData,
		processData: false,
		contentType: false
	})
		.done(function (data) {

			if (typeof $form.data('call-on-success') !== 'undefined')
				window[$form.data('call-on-success')](form, data);

			if (typeof $form.data('reset-form-on-success') !== 'undefined')
				form.reset();

			if (typeof $form.data('on-success-blank-inputs') != 'undefined')
				$($form.data('on-success-blank-inputs')).val('');

			if (typeof $form.data('on-success-msg') !== 'undefined') {
				if (typeof $form.data('redirect-on-success') !== 'undefined' && data.data != "exists_as_customer") {
					swal($form.data('on-success-msg'), '', 'success')
						.then(function () {
							showLoading();
							setTimeout(function () {
								location = $form.data('redirect-on-success');
							}, 5000);
						});
					setTimeout(function () {
						location = $form.data('redirect-on-success');
					}, 5000);
					return;
				} else {
					swal($form.data('on-success-msg'), '', 'success');
				}
			}

			if (typeof $form.data('redirect-on-success') !== 'undefined') {
				showLoading();
				setTimeout(function () {
					location = $form.data('redirect-on-success');
				}, 100000);
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);

	return false;
}

function showSubmissionError(data) {
	try {
		JSON.parse(data.responseText);
	}
	catch {
		swal('', 'Unable to perform the action. Error occurred on server.', 'error');
		return;
	}

	if (typeof data.responseJSON.errors != 'undefined') {
		var errors = data.responseJSON.errors;
		var div = document.createElement('div');

		for (err in errors) {
			for (var i = 0; i < errors[err].length; i++) {
				var p = document.createElement('p');
				p.innerHTML = errors[err][i];
				div.appendChild(p);
			}
		}

		swal({
			title: 'Error(s) Occurred',
			content: div
		});

		return;
	}

	swal('Error occurred', 'Unable to perform the action', 'error');
}

function formatDate(strDate) {
	if (strDate == '0000-00-00')
		return '-';

	if (!(strDate[4] == '-' && strDate[7] == '-'))
		return strDate;

	var arDt = strDate.split('-');
	return arDt[1] + '/' + arDt[2] + '/' + arDt[0];
}

function formatTime(strTime) {
	if (strTime == null)
		return '-';

	if (!(strTime[2] == ':' && strTime[5] == ':' && strTime.length == 8))
		return strTime;

	var ap = 'pm';
	var arTm = strTime.split(':');
	arTm[0] = parseInt(arTm[0]);

	if (arTm[0] < 12) {
		ap = 'am';

		if (arTm[0] == 0) // 12 am/pm
			arTm[0] = 12;
	}
	else if (arTm[0] > 12)
		arTm[0] = arTm[0] - 12;

	return arTm[0] + ':' + arTm[1] + ' ' + ap;
}

function formatDates($arElDates) {
	if (typeof $arElDates == 'undefined')
		var $arElDates = $('.locale-date');

	$arElDates.each(function (index, el) {
		el.innerHTML = formatDate(el.innerHTML);
	});
}

function formatTimes($arElTimes) {
	if (typeof $arElTimes == 'undefined')
		var $arElTimes = $('.locale-time');

	$arElTimes.each(function (index, el) {
		el.innerHTML = formatTime(el.innerHTML);
	});
}

// opt : a JavaScript object : { inputId: 'email', showMsg: 1 }
function validateEmailAddressFormat(opt) {
	if (typeof opt == 'undefined')
		var opt = { inputId: 'email', showMsg: 1 };
	else {
		if (typeof opt.inputId == 'undefined')
			opt.inputId = 'email';

		if (typeof opt.showMsg == 'undefined')
			opt.showMsg = 1;
	}

	try {
		var emailAddress = $('#' + opt.inputId).val();
	}
	catch (ex) {
		if (opt.showMsg)
			swal('Input control for entering email address not found.', '', 'warning');
		else
			console.log('Input control for entering email address not found.');

		return false;
	}

	if (emailAddress === '' && typeof $('#' + opt.inputId).attr('required') == 'undefined')
		return true;

	var atPosition = emailAddress.lastIndexOf('@');

	if (atPosition == -1) {
		if (opt.showMsg)
			swal('Please enter a valid email address.', '', 'warning');

		return false;
	}

	var dotPosition = emailAddress.indexOf('.', atPosition + 1);

	if (dotPosition == -1 || dotPosition == emailAddress.length - 1) {
		// dot not found after '@' or dot is as last character
		if (opt.showMsg)
			swal('Please enter a valid email address.', '', 'warning');

		return false;
	}

	return true;
}

// inputIds : jQuery supported selector format
function validateCharacters(inputIds) {
	var $inputs = $(inputIds);

	for (var a = 0; a < $inputs.length; a++) {
		if ($inputs[a].getAttribute('data-allowed-characters') == null)
			continue;

		// in input tag, data-allowed-characters attribute value should contain characters being allowed
		// write allowed characters separated by spaces in the attrib value
		// write string 'space' in the attrib value to allow space
		// write string 'A-Z a-z' in attrib value to allow alphabets
		// write string '0-9' in the attrib value to allow numbers
		// set attrib value to blank (data-allowed-characters="") to allow only english alphabets

		var allowedChars = $inputs[a].getAttribute('data-allowed-characters');
		var isAlphabetAllowed = allowedChars.indexOf('A-Z a-z');
		var isSmallAlphabetAllowed = allowedChars.indexOf('a-z');
		var isNumberAllowed = allowedChars.indexOf('0-9');
		var isSpaceAllowed = allowedChars.toLowerCase().indexOf('space');
		var char = '';

		if (isAlphabetAllowed != -1)
			allowedChars = allowedChars.replace('A-Z a-z', '');

		if (isSmallAlphabetAllowed != -1)
			allowedChars = allowedChars.replace('a-z', '');

		if (isNumberAllowed != -1)
			allowedChars = allowedChars.replace('0-9', '0123456789');

		if (isSpaceAllowed != -1)
			allowedChars = allowedChars.replace('space', ' ');

		for (var b = 0; b < $inputs[a].value.length; b++) {
			char = $inputs[a].value[b];

			if (!(
				(isAlphabetAllowed != -1 && ((char >= 'A' && char <= 'Z') || (char >= 'a' && char <= 'z'))) ||
				(isSmallAlphabetAllowed != -1 && ((char >= 'a' && char <= 'z'))) ||
				allowedChars.indexOf(char) != -1
			)) {
				var label = '';

				// label for the input field to display to the user in popup/alert message
				if ($inputs[a].getAttribute('data-user-display-label') != null)
					label = ' in ' + $inputs[a].getAttribute('data-user-display-label');
				else {
					// get label for the input
					var $inputLabel = $('label[for="' + $inputs[a].id + '"]');

					if ($inputLabel.length)
						label = ' in ' + $inputLabel[0].innerText.trim().replace('*', '');
				}

				swal({
					title: 'Only the following characters are allowed' + label + ':',
					text: $inputs[a].getAttribute('data-allowed-characters'),
					icon: "warning"
				})
					.then(function () {
						$inputs[a].focus();
					});

				return false;
			}
		}
	}

	return true;
}

function validateAffiliateServiceCommissionPercentage() {
	var affiliateServiceCommissionPercentage = $('#affiliate_processing_percentage').val();

	if (affiliateServiceCommissionPercentage == '') {
		swal('Please enter Service Commission Percentage.', '', 'warning');
		return false;
	}

	if (affiliateServiceCommissionPercentage != '') {
		if (affiliateServiceCommissionPercentage <= 0) {
			swal('Service Commission Percentage should be greater than 0.', '', 'warning');
			return false;
		}
	}

	return true;
}

function calculatePartnerFees(rateGroup, permitFee) {
	var partnerFee = 0;
	if (rateGroup.partner_fee_type == 'Percentage') {
		partnerFee = roundout(((parseFloat(rateGroup.rate)
			+ parseFloat(rateGroup.additional_charge)
			+ parseFloat(permitFee)) * parseFloat(rateGroup.partner_fee) / 100), 2);
	} else {
		partnerFee = rateGroup.partner_fee;
	}

	return partnerFee;
}
function validateAffiliateStripeCommissionPercentage() {
	var affiliateStripeCommissionPercentage = $('#stripe_affiliate_percentage').val();

	if (affiliateStripeCommissionPercentage == '') {
		swal('Please enter Stripe Commission Percentage.', '', 'warning');
		return false;
	}

	if (affiliateStripeCommissionPercentage != '') {
		if (affiliateStripeCommissionPercentage <= 0) {
			swal('Stripe Commission Percentage should be greater than 0.', '', 'warning');
			return false;
		}
	}

	return true;
}
function validateOtaAffiliatePercentage() {
	var otaAffiliatePercentage = $('#ota_affiliate_percentage_settings').val();

	if (otaAffiliatePercentage == '') {
		swal('Please enter OTA Commission Percentage.', '', 'warning');
		return false;
	}

	if (otaAffiliatePercentage <= 0) {
		swal('OTA Commission Percentage should be greater than 0.', '', 'warning');
		return false;
	}

	return true;
}
function validateNextYearOtaAffiliatePercentage() {
	var nextYearOtaAffiliatePercentage = $('#next_year_ota_affiliate_percentage_settings').val();

	if (nextYearOtaAffiliatePercentage == '') {
		swal('Please enter next year OTA Commission Percentage.', '', 'warning');
		return false;
	}

	if (nextYearOtaAffiliatePercentage <= 0) {
		swal('Next year OTA Commission Percentage should be greater than 0.', '', 'warning');
		return false;
	}

	return true;
}
function validateCardnumber(opt) {
	if (typeof opt == 'undefined')
		var opt = { inputId: 'cardnumber', showMsg: 1 };
	else {
		if (typeof opt.inputId == 'undefined')
			opt.inputId = 'cardnumber';

		if (typeof opt.showMsg == 'undefined')
			opt.showMsg = 1;
	}

	try {
		var cardNumber = $('#' + opt.inputId).val();
	}
	catch (ex) {
		if (opt.showMsg)
			swal('Input control for entering card number not found.', '', 'warning');
		else
			console.log('Input control for entering card number not found.');

		return false;
	}
	if (cardNumber == '') {
		if (opt.showMsg)
			swal('Please enter a Card Number.', '', 'warning');

		return false;
	}
	if (cardNumber != '') {

		if (/^[0-9]+$/.test(cardNumber) == false) {
			if (opt.showMsg)
				swal('Please enter Valid Card Number.', '', 'warning');
			return false;
		}

	}

	return true;
}

function validateCvv(opt) {
	if (typeof opt == 'undefined')
		var opt = { inputId: 'cvv', showMsg: 1 };
	else {
		if (typeof opt.inputId == 'undefined')
			opt.inputId = 'cvv';

		if (typeof opt.showMsg == 'undefined')
			opt.showMsg = 1;
	}

	try {
		var cvv = $('#' + opt.inputId).val();
	}
	catch (ex) {
		if (opt.showMsg)
			swal('Input control for entering CVV not found.', '', 'warning');
		else
			console.log('Input control for entering CVV not found.');

		return false;
	}
	if (cvv == '') {
		if (opt.showMsg)
			swal('Please enter a CVV.', '', 'warning');

		return false;
	}
	if (cvv != '') {

		if (/^[0-9]+$/.test(cvv) == false) {
			if (opt.showMsg)
				swal('Please enter Valid CVV.', '', 'warning');
			return false;
		}

	}

	return true;
}

function validateCardName(opt) {
	if (typeof opt == 'undefined')
		var opt = { inputId: 'nameoncard', showMsg: 1 };
	else {
		if (typeof opt.inputId == 'undefined')
			opt.inputId = 'nameoncard';

		if (typeof opt.showMsg == 'undefined')
			opt.showMsg = 1;
	}

	try {
		var nameOnCard = $('#' + opt.inputId).val();
	}
	catch (ex) {
		if (opt.showMsg)
			swal('Input control for entering card name not found.', '', 'warning');
		else
			console.log('Input control for entering card name not found.');

		return false;
	}
	if (nameOnCard == '') {
		if (opt.showMsg)
			swal('Please enter a card name.', '', 'warning');
		return false;
	}


	return true;
}

function validateCardExpiryDate(opt) {
	if (typeof opt == 'undefined')
		var opt = { inputId: 'nameoncard', showMsg: 1 };
	else {
		if (typeof opt.inputId == 'undefined')
			opt.inputId = 'nameoncard';

		if (typeof opt.showMsg == 'undefined')
			opt.showMsg = 1;
	}

	try {
		var expiryDate = $('#' + opt.inputId).val();
	}
	catch (ex) {
		if (opt.showMsg)
			swal('Input control for entering card expiry date not found.', '', 'warning');
		else
			console.log('Input control for entering card expiry date not found.');

		return false;
	}
	if (expiryDate == '') {
		if (opt.showMsg)
			swal('Please enter a card expiry date.', '', 'warning');
		return false;
	}

	return true;
}

function tenantForm(e) {
	var tenantId = $(e).val();
	var loginNormal = $("#login-normal");
	var tenantContinue = $("#div-tenant-continue");
	var loginButton = $("#btn-login");

	if (tenantId.length) {
		loginNormal.hide();
		tenantContinue.show();
		loginButton.prop('disabled', true);
	} else {
		loginNormal.show();
		tenantContinue.hide();
		loginButton.prop('disabled', false);
	}
}

function tenantContinue() {
	var parsed = psl.parse(location.hostname);
	var hostname = parsed.domain;
	var pathArray = location.pathname.split("/");
	var tenantId = $("#domain").val().trim();
	var url = $("#urlToCheckTenantExist").val();
	url = url.replace(':id', tenantId);

	showLoading();

	$.ajax({
		headers: {
			Accept: 'application/json'
		},
		dataType: 'json',
		type: 'GET',
		url: url,
	}).done(function (data) {
		var newUrl = `https://${data.id}.${hostname}/${pathArray[1]}`;
		window.location = newUrl;
	})
		.fail(function () {
			swal('No workspace found.', '', 'error');
		})
		.always(hideLoading);
}

$("#domain").on('change keyup paste', function (e) {

	//preserving the position of the cursor
	var start = e.target.selectionStart;

	//Converting text to lowercase
	$(this).val($(this).val().toLowerCase());

	//Jumping back to the old position of the cursor
	e.target.selectionStart = e.target.selectionEnd = start;

	$(this).val($(this).val().replace(/[0-9]/g, "").replace(/[^\w]|_/g, ""));
})

window.addEventListener("pageshow", () => {
	$("#domain").val("");
});

function validateNumbers(inputIds) {
	var $inputs = $(inputIds);

	for (var a = 0; a < $inputs.length; a++) {
		if ($inputs[a].getAttribute('data-allowed-numbers') == null)
			continue;

		// in input tag, data-allowed-numbers attribute value should contain numbers being allowed
		// write string '0-9' in the attrib value to allow numbers
		// set attrib value to blank (data-allowed-numbers="") to allow only 0-9 numbers

		var allowedChars = $inputs[a].getAttribute('data-allowed-numbers');
		var isNumberAllowed = allowedChars.indexOf('0-9');
		var char = '';

		if (isNumberAllowed != -1)
			allowedChars = allowedChars.replace('0-9', '0123456789');

		for (var b = 0; b < $inputs[a].value.length; b++) {
			char = $inputs[a].value[b];

			if (!(
				(isNumberAllowed != -1 && ((char >= 0 && char <= 9)) ||
					allowedChars.indexOf(char) != -1
				))) {
				var label = '';

				// label for the input field to display to the user in popup/alert message
				if ($inputs[a].getAttribute('data-user-display-label') != null)
					label = ' in ' + $inputs[a].getAttribute('data-user-display-label');
				else {
					// get label for the input
					var $inputLabel = $('label[for="' + $inputs[a].id + '"]');

					if ($inputLabel.length)
						label = ' in ' + $inputLabel[0].innerText.trim().replace('*', '');
				}

				swal({
					title: 'Only the numbers are allowed' + label + ':',
					text: $inputs[a].getAttribute('data-allowed-numbers'),
					icon: "warning"
				})
					.then(function () {
						$inputs[a].focus();
					});

				return false;
			}
		}
	}

	return true;
}