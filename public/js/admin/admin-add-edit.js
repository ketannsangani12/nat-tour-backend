function validateAndSubmitForm(form) {

	if(window.hasOwnProperty("_PasswordStrength") ){
		if( window._PasswordStrength.getPasswordStrength() !== "strong") {
			window._PasswordStrength.setFocus()
			return false;
		}
	}

	if(!validateEmailAddressFormat())
		return false;
	
	if(!validateCharacters('#name'))
		return false;
	
	return submitForm(form);
}
