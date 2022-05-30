$(function() {
	let form = new Form('#forgot-form');

	// setup form validation
	$('#forgot-form').validate({
		submitHandler: function(_f, e) {
			e.preventDefault();

			// check recaptcha
			if (!Captcha.formCheck()) {
				return false;
			}

			form.post('/forgot', () => $('.display-section').toggle());
			return false;
		}
	});
});