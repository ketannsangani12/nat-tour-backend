function forgotPwdSubmitReply(form, response) {
	$('.hide-after-form-submit').hide();
	$('.show-after-form-submit').removeClass('hidden');

	$('#user-email-addr')
		.attr('href', 'mailto:' + $('#email').val())
		.html($('#email').val());
}

function resetPwdSubmitReply(form, response) {
	$('.hide-after-form-submit').hide();
	$('.show-after-form-submit').removeClass('hidden');
}
