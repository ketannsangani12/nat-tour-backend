class PasswordStrength{

	constructor( element_id="password", len = 8 ){
		this.element_id = `#${element_id}`;
		this.element = $(this.element_id);
        this.password_len = len;
        this.label = $('#password-strength-status');
		this.number = /([0-9])/;
		this.alphabets = /([a-zA-Z])/;
		this.special_characters = /([~,!,@,#,$,%,^,&,*,-,_,+,=,?,>,<])/;
		this.applyListiners();
        this.strength = 'weak';

	}

	renderIcon(){
		return `<i class="fas fa-info-circle" title="Strong Password Required"></i> `;
	}

	applyListiners(){
		$(document).off('keyup', this.element_id ).on("keyup", this.element_id, this.validatePasswordStrength.bind(this));
	}

    getPasswordStrength(){
        return this.strength;
    }

	setFocus(){
		this.element.focus();
		//this.element.effect( "shake", {times:4}, 1000 );
	}

	validatePasswordStrength(){

		const password = this.element.val().trim();
        this.strength = 'weak';
		
		if(password.length < this.password_len) {
			this.strength = 'weak';
			this.label.removeClass();
			this.label.addClass('weak-password');
			this.label.html(`Weak ( password less than ${this.password_len} characters )`);
		} else {

			// upper and lower case letter
			const le_match = ( password.match(this.alphabets) && /[A-Z]/.test(password));
			// number 
			const nu_match = password.match(this.number);
			// special char
			const sp_match = password.match(this.special_characters);

			if(le_match && nu_match && sp_match) {
				this.strength = 'strong';
				this.label.removeClass();
				this.label.addClass('strong-password');
				this.label.html("Strong");
			}
			else {

				let le_req = le_match ? "" : "upper/lower case letters"; 
				let nu_req = nu_match ? "" : "a number";
				let sp_req = sp_match ? "" : "a special character";

				this.strength = 'medium';
				this.label.removeClass();
				this.label.addClass('medium-password');
				this.label.html(`Medium (required: ${le_req} ${nu_req} ${sp_req} )`);
			}
		}

	}

}
