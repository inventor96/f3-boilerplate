$(function() {
	let form = new Form('#pwd-reset');

	// setup form validation
	$('#pwd-reset').validate({
		rules: {
			password: {
				pwcheck: true
			},
			password2: {
				equalTo: "#password"
			}
		},
		messages: {
			password2: {
				equalTo: "Please enter the same password again."
			}
		},
		submitHandler: function(_f, e) {
			e.preventDefault();

			// check recaptcha
			if (!Captcha.formCheck()) {
				return false;
			}

			form.post('/password-reset', () => $('#success-alert').show(), null, {skipSuccessEnable: true});
			return false;
		}
	});
});