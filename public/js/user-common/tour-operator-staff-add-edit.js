function validateAndSubmitForm(form) {

	if(window.hasOwnProperty("_PasswordStrength") ){
		if( window._PasswordStrength.getPasswordStrength() !== "strong") {
			window._PasswordStrength.setFocus()
			return false;
		}
	}

	if(!validateEmailAddressFormat()){
		return false;
	}
	
	if(!validateCharacters('#first_name, #last_name, #phone_number')){
		return false;
	}
		
	
	return submitForm(form);
}

function updateRole(chkRole) {
	showLoading();
	
	$.ajax({
		method: 'post',
		url: updateRoleUrl,
		data: { role: chkRole.value }
	})
	.done(function() {
		swal('Role ' + (chkRole.checked ? 'assigned.' : 'unassigned.'), '', 'info');
		window.location.reload();
	})
	.fail(function() {
		swal('Unable to ' + (chkRole.checked ? 'assign' : 'unassign') + ' the role.', '', 'error');
		chkRole.checked = !chkRole.checked;
	})
	.always(hideLoading);
}

document.addEventListener('DOMContentLoaded', function () {

    document.addEventListener('change', function (e) {

        /* ===============================
         * Helpers
         * =============================== */
        function getPermissionLabel(cb) {
            return cb.parentElement.textContent.replace(/\s+/g, ' ').trim();
        }

        function isBoardAccordion(el) {
            const item = el.closest('.accordion-item');
            if (!item) return false;

            const btn = item.querySelector('.accordion-button');
            return btn && btn.childNodes[0].textContent.trim() === 'Board';
        }

        /* ===============================
         * HEADER CHECKBOX (Board)
         * =============================== */
        if (e.target.classList.contains('header-permission')) {

            if (!isBoardAccordion(e.target)) return;

            const accordion = e.target.closest('.accordion-item');
            const permissions = accordion.querySelectorAll('.permission-checkbox');

            permissions.forEach(cb => {
                const label = getPermissionLabel(cb);

                if (e.target.checked) {
                    cb.checked = (label !== 'Guide Board');
                } else {
                    cb.checked = false;
                }
            });

            return;
        }

        /* ===============================
         * MUTUAL EXCLUSION
         * =============================== */
        if (!e.target.classList.contains('permission-checkbox')) return;
        if (!e.target.checked) return;
        if (!isBoardAccordion(e.target)) return;

        const label = getPermissionLabel(e.target);
        const accordion = e.target.closest('.accordion-item');

        if (label === 'Admin Board' || label === 'Guide Board') {
            accordion.querySelectorAll('.permission-checkbox').forEach(cb => {
                if (cb === e.target) return;

                const otherLabel = getPermissionLabel(cb);

                if (
                    (label === 'Admin Board' && otherLabel === 'Guide Board') ||
                    (label === 'Guide Board' && otherLabel === 'Admin Board')
                ) {
                    cb.checked = false;
                }
            });
        }
    });

});