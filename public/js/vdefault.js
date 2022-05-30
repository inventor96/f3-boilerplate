// add password requirements check
$.validator.addMethod('pwcheck', function(value) {
	return value.length > 7 // at least 8 chars
		&& value.length < 65 // no more than 64 chars
		&& /[A-Z]/.test(value) // has uppercase letter
		&& /[a-z]/.test(value) // has a lowercase letter
		&& /\d/.test(value) // has a digit
		&& /[^A-Za-z\d]/.test(value); // has something else
}, "Please use a password that meets the following requirements: <ul><li>8 - 64 characters</li><li>An uppercase letter</li><li>A lowercase letter</li><li>A number</li><li>A special character (!, @, #, etc.)</li></ul>");

// set defaults for bootstrap forms
$.validator.setDefaults({
	errorElement: 'div',
	errorClass: 'is-invalid',
	validClass: 'is-valid',
	errorPlacement: function(error, element) {
		error.addClass('invalid-feedback');
		var $form_append = element.siblings('.input-group-append');
		if ($form_append.length) {
			error.insertAfter($form_append);
		} else {
			error.insertAfter(element);
		}
	}
});