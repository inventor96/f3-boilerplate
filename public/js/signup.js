$(function() {
	// load the timezone
	$('#tz').val(Intl.DateTimeFormat().resolvedOptions().timeZone);

	let form = new Form('#signup-form');

	// setup form validation
	let validator = $('#signup-form').validate({
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

			form.post('/signup', () => window.location = '/signup-confirm');
			return false;
		}
	});

	// resetting
	$('#signup-form').on('reset', () => validator.resetForm());
});