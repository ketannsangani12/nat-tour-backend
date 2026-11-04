$(function () {
	if (action == 'add')
		$('#btn-add-social-media').trigger('click');
	else
		fillSocialMedia();

	$('#city').select2({ tags: true });

	$("#subdomain").on('change keyup paste', function (e) {

		//preserving the position of the cursor
		var start = e.target.selectionStart;

		//Converting text to lowercase
		$(this).val($(this).val().toLowerCase());

		//Jumping back to the old position of the cursor
		e.target.selectionStart = e.target.selectionEnd = start;

		$(this).val($(this).val().replace(/[0-9]/g, "").replace(/[^\w]|_/g, ""));
	})
});

function addSocialMedia() {
	var trTmpl = $('#tmpl-social-media tr')[0].cloneNode(true);
	txts = trTmpl.getElementsByClassName('form-control');

	for (var i = 0; i < txts.length; i++) {
		txts[i].setAttribute('name', txts[i].getAttribute('data-name'));
		txts[i].setAttribute('required', 1);
	}

	$('#tbl-social-media').append(trTmpl);
	return trTmpl;
}

function removeSocialMedia(btn) {
	$(btn).parents('tr').remove();
}

function fillSocialMedia() {
	for (sm in socialMedia) {
		var inputs = addSocialMedia().getElementsByTagName('input');
		inputs[0].value = sm;
		inputs[1].value = socialMedia[sm];
	}
}

function validateForm() {
	if (!validateEmailAddressFormat())
		return false;

	if (!validateCharacters('#name, #phone_number, #postal_code'))
		return false;

	return true;
}


function addUpdateConnectedAccount(btn) {
	$tr = $(btn).parents('tr');
	showLoading();
	$packageId = $tr.find('.package_id');

	$.ajax({
		method: 'post',
		url: addUpdateConnectedAccountUrl,
		data: {
			package_id: $packageId.val(),
			stripe_destination_id_package: $tr.find('.stripe_destination_id_package').val()
		}
	})
		.done(function (response) {
			swal('Stripe Connected Account ID saved.', '', 'success');
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}
function showConnectedAccountId(btn) {
	$td = $(btn).parents('td');
	const input = $td.find('.toggle-encrypted-value-package');
	const icon = $td.find('.toggleIconPackage');
	if (input.attr("type") === 'password') {
		input.attr("type", 'text');
		icon.removeClass('fa-eye');
		icon.addClass('fa-eye-slash');
	} else {
		input.attr("type", 'password');
		icon.removeClass('fa-eye-slash');
		icon.addClass('fa-eye');
	}
}
function getConnectedAccountIdInfo(btn) {
	$tr = $(btn).parents('tr');
	showLoading();
	
	$.ajax({
		method: 'post',
		url: getConnectedAccountInfoUrl,
		data: {
			stripe_destination_id: $tr.find('.stripe_destination_id_package').val()
		}
	})
		.done(function (response) {
			var accountName = response.account_name;
			$tr.find('.connected_account_name').removeClass('hidden');
			$tr.find('.connected_account_name').text('Account Name : ' + accountName);
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}
function getTourOperatorConnectedAccountIdInfo(btn) {
	showLoading();
	
	$.ajax({
		method: 'post',
		url: getConnectedAccountInfoUrl,
		data: {
			stripe_destination_id: $('#stripe_destination_id').val()
		}
	})
		.done(function (response) {
			var accountName = response.account_name;
			$('#connected_account_name').removeClass('hidden');
			$('#connected_account_name').text('Account Name : ' + accountName);
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

// Checkout Terms Management
function validateCheckoutTermKeys() {
	var keys = [];
	var duplicateFound = false;
	$('#checkout-terms-container .checkout-term-row').each(function() {
		var keyInput = $(this).find('input[name*="[key]"]');
		var key = keyInput.val().toLowerCase().replace(/[^a-z0-9_]/g, '');
		if (key) {
			if (keys.indexOf(key) !== -1) {
				duplicateFound = true;
				keyInput.addClass('border-red-500');
			} else {
				keys.push(key);
				keyInput.removeClass('border-red-500');
			}
		}
	});
	return !duplicateFound;
}

function validateCheckoutTermsForm() {
	if (!validateCheckoutTermKeys()) {
		swal('Duplicate Key', 'Each checkout term must have a unique key.', 'error');
		return false;
	}
	return true;
}

function addCheckoutTerm() {
	var container = document.getElementById('checkout-terms-container');
	var html = '<tr class="checkout-term-row">' +
		'<td class="td-custom-css align-top">' +
			'<input type="text" class="form-control form-custom-input" name="checkout_terms[' + checkoutTermIndex + '][key]" ' +
				'placeholder="e.g., permit_ack" required ' +
				'pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores" ' +
				'onblur="validateCheckoutTermKeys()">' +
		'</td>' +
		'<td class="td-custom-css align-top">' +
			'<textarea class="form-control form-custom-input" name="checkout_terms[' + checkoutTermIndex + '][label]" ' +
				'rows="4" style="min-height: 80px;" placeholder="Text displayed to customer" required></textarea>' +
		'</td>' +
		'<td class="td-custom-css align-top">' +
			'<select class="form-select form-custom-input" name="checkout_terms[' + checkoutTermIndex + '][required]">' +
				'<option value="1" selected>Yes</option>' +
				'<option value="0">No</option>' +
			'</select>' +
		'</td>' +
		'<td class="td-custom-css align-top">' +
			'<select class="form-select form-custom-input" name="checkout_terms[' + checkoutTermIndex + '][status]">' +
				'<option value="active" selected>Active</option>' +
				'<option value="inactive">Inactive</option>' +
			'</select>' +
		'</td>' +
		'<td class="td-custom-css align-top text-center">' +
			'<button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="removeCheckoutTerm(this)">' +
				'<i class="fa fa-times"></i>' +
			'</button>' +
		'</td>' +
	'</tr>';
	container.insertAdjacentHTML('beforeend', html);
	checkoutTermIndex++;
}

function removeCheckoutTerm(button) {
	var row = button.closest('.checkout-term-row');
	var keyInput = row.querySelector('input[name*="[key]"]');
	var termKey = keyInput ? keyInput.value : '';

	swal({
		title: 'Are you sure?',
		text: 'This checkout term will be permanently deleted.',
		icon: 'warning',
		buttons: {
			cancel: {
				text: 'Cancel',
				value: null,
				visible: true,
				closeModal: true,
			},
			confirm: {
				text: 'Yes, delete it',
				value: true,
				visible: true,
				closeModal: true
			}
		},
		dangerMode: true,
	}).then(function(willDelete) {
		if (willDelete) {
			// If term has a key (existing term), delete from database
			if (termKey && typeof deleteCheckoutTermUrl !== 'undefined') {
				showLoading();
				$.ajax({
					method: 'post',
					url: deleteCheckoutTermUrl,
					data: { key: termKey }
				})
				.done(function(response) {
					row.remove();
					swal('Deleted!', 'Checkout term has been deleted.', 'success');
				})
				.fail(showSubmissionError)
				.always(hideLoading);
			} else {
				// New term not yet saved, just remove from DOM
				row.remove();
			}
		}
	});
}