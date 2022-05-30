$(function() {
	let form = new Form('#login-form');

	// setup form validation
	$('#login-form').validate({
		submitHandler: function(_f, e) {
			e.preventDefault();

			form.post('/login', (response) => window.location = response.redirect);
			return false;
		}
	});
});