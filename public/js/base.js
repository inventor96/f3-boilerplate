class Core {
	// determines whether the input object is a function
	static isFunc(obj) {
		return typeof obj === 'function';
	}

	// checks a value and returns the value if it's not invalid
	static checkV(value, ifInvalid) {
		return (typeof value !== 'undefined' && value !== null) ? value : ifInvalid;
	}

	// sets the tz as a cookie for server-side processing
	static setTz(override) {
		let tz = Core.checkV(override, Intl.DateTimeFormat().resolvedOptions().timeZone);
		document.cookie = 'tz='+tz+'; path=/;';
	}
};

class Api {
	// parse raw response from server to maintain a consistent reponse with errors
	static #parseErrResponse(jqXHR) {
		let json = {};
		try {
			json = JSON.parse(jqXHR.responseText);
		} catch (e) {
			json = {
				code: 600,
				error: 'Server did not return a valid response.'
			};
		}
		return json;
	}

	// send a json payload to the server
	static post(url, data, successFunc, errorFunc) {
		// default options
		data = Core.checkV(data, {});
		let opts = {
			url: url,
			type: 'POST',
			data: JSON.stringify(data),
			dataType: 'json',
			contentType: 'application/json; charset=UTF-8',
			processData: false
		};

		// add functions
		if (Core.isFunc(successFunc)) {
			opts.success = successFunc;
		}
		if (Core.isFunc(errorFunc)) {
			let t = this;
			opts.error = function(jqXHR, textStatus, errorThrown) {
				// include json response from server on error
				errorFunc(t.#parseErrResponse(jqXHR), textStatus, jqXHR, errorThrown);
			};
		}

		// make request
		return $.ajax(opts);
	}

	// request data from the server
	static get(url, query, successFunc, errorFunc) {
		// default options
		query = Core.checkV(query, {});
		let opts = {
			url: url,
			type: 'GET',
			data: query,
			dataType: 'json'
		};

		// add functions
		if (Core.isFunc(successFunc)) {
			opts.success = successFunc;
		}
		if (Core.isFunc(errorFunc)) {
			let t = this;
			opts.error = function(jqXHR, textStatus, errorThrown) {
				// include json response from server on error
				errorFunc(t.#parseErrResponse(jqXHR), jqXHR, textStatus, errorThrown);
			};
		}

		// make request
		return $.ajax(opts);
	}
};

class Form {
	#form;

	constructor(selector, skipFocus) {
		this.#form = $(selector);
		let f = this;
		let inputs = this.#form.find(':input:not(:hidden):not(button)');

		// focus first input element
		if (!Core.checkV(skipFocus, false)) {
			$(inputs[0]).focus();
		}

		// when pressing enter, prioritize focusing the next input over submitting the form
		inputs.not('.no-enter-catch, textarea').on('keypress', function(e) {
			let keycode = (e.keyCode ? e.keyCode : e.which);
			if (keycode == '13') {
				e.preventDefault();
				let useNext = false;
				for (let i = 0; i < inputs.length; i++) {
					if (useNext) {
						$(inputs[i]).focus();
						break;
					} else if (i == (inputs.length - 1)) {
						f.#form.submit();
					} else {
						useNext = this === inputs[i];
					}
				}
			}
		});

		// submit form when element is classed to do so
		this.#form.find('.enter-submit').on('keypress', function(e) {
			let keycode = (e.keyCode ? e.keyCode : e.which);
			if (keycode == '13') {
				e.preventDefault();
				f.#form.submit();
			}
		});

		// automatically hide error message when submitting and resetting
		this.#form.on('submit reset', () => f.#form.find('.form-error').hide());
	}

	// convert form input to a json object
	toJson() {
		let arr = this.#form.serializeArray();
		let json = {};
		$.each(arr, function(_i, element) {
			// handle boolean elements
			let $e = $(element);
			if (['checkbox', 'radio'].includes($e.attr('type'))) {
				// handle arrays
				if (element.name.match(/\[\]$/) && typeof element.value !== 'undefined' && element.value !== null) {
					let arr_name = element.name.slice(0, -2);
					if (!Array.isArray(json[arr_name])) {
						json[arr_name] = [];
					}
					json[arr_name].push(Core.checkV(element.value, ''));
				} else {
					json[element.name] = $e.prop('checked');
				}
			} else {
				json[element.name] = Core.checkV(element.value, '');
			}
		});
		return json;
	}

	// disable all form inputs
	disable(excludeReset) {
		this.#form.find('input, textarea, select, button'+(excludeReset ? ':not([type=reset])' : '')).prop('disabled', true);
	}

	// enable all form inputs
	enable(excludeReset) {
		this.#form.find('input, textarea, select, button'+(excludeReset ? ':not([type=reset])' : '')).prop('disabled', false);
	}

	// send the form to the backend with some helpful processing
	#process(method, url, successFunc, errorFunc, options) {
		// add error element if needed
		if (this.#form.find('.form-error').length == 0) {
			this.#form.append('<div class="form-error alert alert-danger mt-3 d-none-init" role="alert"></div>');
		}

		// get data before the form gets disabled
		let data;
		switch (method) {
			case 'post':
				data = this.toJson();
				break;

			case 'get':
				data = this.#form.serialize();
				break;
		}

		// process options
		options = Core.checkV(options, {});
		let skipDisable = Core.checkV(options.skipDisable, false);
		let skipSuccessEnable = Core.checkV(options.skipSuccessEnable, false);
		let skipErrorEnable = Core.checkV(options.skipErrorEnable, false);
		let excludeReset = Core.checkV(options.excludeReset, false);
		let skipErrorDisplay = Core.checkV(options.skipErrorDisplay, false);

		// prevent further interactions
		if (!skipDisable) {
			this.disable(excludeReset);
		}

		// callback functions to restore the form
		let f = this;
		let success = function(data, textStatus, jqXHR) {
			if (!skipSuccessEnable) {
				f.enable(excludeReset);
			}
			if (Core.isFunc(successFunc)) {
				successFunc(data, textStatus, jqXHR);
			}
		};
		let error = function(data, textStatus, jqXHR, errorThrown) {
			if (!skipErrorEnable) {
				f.enable(excludeReset);
			}
			if (!skipErrorDisplay) {
				f.#form.find('.form-error').text(data.error || "There was an error while submitting your request!").show();
			}
			if (Core.isFunc(errorFunc)) {
				errorFunc(data, textStatus, jqXHR, errorThrown);
			}
		};

		// convert form values and send
		switch (method) {
			case 'post':
				return Api.post(url, data, success, error);
			case 'get':
				return Api.get(url, data, successFunc, errorFunc);
		}
		
	}

	// send the form to the backend via the POST method
	post(url, successFunc, errorFunc, options) {
		return this.#process('post', url, successFunc, errorFunc, options);
	}

	// send the form to the backend via the GET method
	get(url, successFunc, errorFunc, options) {
		return this.#process('get', url, successFunc, errorFunc, options);
	}
};

class Captcha {
	// check a recaptcha in a form, optionally specifying non-standard selectors for the input and error message
	static formCheck(input_selector, error_selector) {
		input_selector = Core.checkV(input_selector, '#recaptcha');
		error_selector = Core.checkV(error_selector, '.recaptcha-error');
		let captchaResponse = grecaptcha.getResponse();
		if (captchaResponse) {
			$(error_selector).hide();
			$(input_selector).val(captchaResponse);
			return true;
		} else {
			$(error_selector).show();
			return false;
		}
	}
};

class Pager {
	// redisplay pager list
	static redisplay(ul_selector, pager_set, path) {
		// get and sanitize path
		path = Core.checkV(path, window.location.pathname);
		let active_page = 0;
		let joiner = path+'?page=';
		if (window.location.search.match(/\?page=/)) {
			// TODO: work out the details
		} else if (window.location.search.match(/\?/)) {
			joiner = path+'&page=';
		}

		// prepare list
		let list = $(ul_selector);
		list.empty();

		// add each value
		$.each(pager_set, function(_i, page) {
			let extra_class = '';
			let link_text = '';
			switch (page.type) {
				case 'first':
				case 'last':
					extra_class = page.disabled ? 'disabled' : '';
					link_text = page.type == 'first' ? '<i class="bi arrow-left-arrow-fill"></i>' : '<i class="bi arrow-right-arrow-fill"></i>';
					break;

				case 'active':
					extra_class = 'active';
					active_page = page.value;
				case 'link':
					link_text = Number(page.value) + 1;
					break;

				default:
					console.error('Unknown page type: '+page.type);
					break;
			}

			list.append('<li class="page-item '+extra_class+'"><a class="page-link" href="'+joiner+page.value+'">'+link_text+'</a></li>');
		});

		// update url
		history.pushState(null, document.title, joiner+active_page);
	}
};

// set tz
Core.setTz();