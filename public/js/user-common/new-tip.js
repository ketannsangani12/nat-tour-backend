var cardElement = null;
var cardInputValid = false;
$(document).ready(function () {
	const slider = $("#tip-percentage-slider");

	slider.slider({
		range: false,
		max: 50,
		step: 1,
		create: function () {
			$("#percentage-value").text(slider.slider("value") + "%");
			$('#tipPercentage').val(slider.slider("value"));
		},
		slide: function (event, ui) {
			$("#percentage-value").text(ui.value + "%");
			$('#tipPercentage').val(ui.value);
			document.querySelectorAll('.amount-btn').forEach(btn => {
				if (ui.value == btn.dataset.amount) {
					btn.classList.add('active');
				} else {
					btn.classList.remove('active');
				}
			});
			var tipPercentage = parseInt(ui.value);
			var tipAmount = roundout((remainingTicketCost * tipPercentage) / 100, 2);

			updateSendButton(tipAmount);
		}
	});
});
$(document).on("submit", "#tipForm", function (e) {
	const amount = parseFloat($("#tipAmount").val());

	if (isNaN(amount) || amount <= 0) {
		e.preventDefault(); // stop form submission
		swal('Sorry!', 'Please select a valid tip amount greater than 0.', 'warning');
		return false;
	}
});
$(document).on('input change', '.custom-amount', (evt) => {
	const inputElement = $(evt.currentTarget);
	const customAmount = parseFloat(inputElement.val());
	var tipFees = 0;
	if (customAmount > 0) {
		// Remove active class from all buttons
		document.querySelectorAll('.amount-btn').forEach(btn => {
			btn.classList.remove('active');
		});
		$("#tip-percentage-slider").slider("value", 0);
		$("#percentage-value").text("0%");
		$('#tipPercentage').val(0);
		$('#tipAmount').val(customAmount);
		tipFees = roundout((customAmount * natServiceCommission) / 100, 2);
		$('#tipFees').val(tipFees);
		$('#feesAmount').text(`${currencyFormation.symbol + tipFees.toFixed(2)}`);
	}
	const sendTipBtn = document.querySelector('.send-tip-btn');
	const btnText = sendTipBtn.querySelector('.btn-text');

	if (customAmount > 0) {
		var totalTipAmount = roundout(customAmount + tipFees, 2);
		btnText.textContent = `Send Tip - ${currencyFormation.symbol + totalTipAmount.toFixed(2)}`;
	}
});
$(document).off('click', '.amount-btn').on('click', '.amount-btn', (evt) => {
	var selectedButton = evt.currentTarget;
	// Remove active class from all buttons
	document.querySelectorAll('.amount-btn').forEach(btn => {
		btn.classList.remove('active');
	});

	// Add active class to selected button
	selectedButton.classList.add('active');
	var tipAmount = 0;
	// Set selected amount
	if (gratuityType === 'fixed_percentage') {
		var tipPercentage = parseInt(selectedButton.dataset.amount);
		$("#tip-percentage-slider").slider("value", tipPercentage);
		$("#percentage-value").text(tipPercentage + "%");
		$('#tipPercentage').val(tipPercentage);
		tipAmount = roundout((remainingTicketCost * tipPercentage) / 100, 2);
	} else {
		tipAmount = parseInt(selectedButton.dataset.amount);
	}
	// Clear custom amount input
	document.querySelector('.custom-amount').value = '';
	updateSendButton(tipAmount);
});

function updateSendButton(gratuityAmount) {
	const sendTipBtn = document.querySelector('.send-tip-btn');
	const btnText = sendTipBtn.querySelector('.btn-text');


	// Enable/disable button based on amount
	if (gratuityAmount > 0) {
		sendTipBtn.classList.remove('disabled');
		sendTipBtn.style.opacity = '1';
	} else {
		sendTipBtn.style.opacity = '0.7';
	}
	$('#tipAmount').val(gratuityAmount);
	var tipFees = roundout((gratuityAmount * natServiceCommission) / 100, 2);
	$('#tipFees').val(tipFees);
	$('#feesAmount').text(`${currencyFormation.symbol + tipFees.toFixed(2)}`);
	var totalTipAmount = roundout(gratuityAmount + tipFees, 2);
	btnText.textContent = `Send Tip - ${currencyFormation.symbol + totalTipAmount.toFixed(2)}`;
}
function validateAndSubmitForm3(form, paymentIntentId = "") {
	console.log(form);
	if (typeof $(form).data('booking-token') != 'undefined') {
		$.post(createPaymentIntentUrl, {
			'booking_id': $(form).data('booking-id'),
			'booking_token': $(form).data('booking-token'),
		})
			.then(function (response) {
				payWithCard(form, response);
			})
			.fail(function (jqXHR) {
				hideLoading();
				showSubmissionError(jqXHR);
			});

	}
	var feature = 'tip';
	$(form).append(`<input type="hidden" name="feature" value="tip">`);
	if (!paymentIntentId) {
		$('#tipModal').modal('hide');
		initTourBookingStripePayment.initializeStripe(form, feature);
		return false;
	} else {
		submitTip(form);
	}
}

function tourtip(form, response) {
	if (response.error && response.errors.length) {
		if (response.errors.indexOf('payment') != -1)
			swal('Payment initialization error', 'Tip payment has been initializing payment on the server. You may try again to make a payment.', 'error');

	} else {
		swal('Tip Payment Done', 'Page will reload', 'success')
			.then(function () {
				location = location; // location.reload() keeps data in form which isn't desirable
			});
	}
}

function submitTip(form) {
	$(form).serializeArray();
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: $(form).serialize(),

	})

		.done(function (response) {
			tourtip(form, response);
		})

		.fail(function (response) {
			hideLoading();
			var message = response.responseJSON.message;
			swal('Ooop!', message);
		})
		.always(hideLoading);
}
function validateAndSubmitTipPaymentCustomer(form, paymentIntentId = "") {

}
function roundout(amount, places = 0) {
	if (places < 0) {
		places = 0;
	}

	let x = Math.pow(10, places);
	return (amount >= 0 ? Math.ceil(amount * x) : Math.floor(amount * x)) / x;
}