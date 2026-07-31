(function () {
	'use strict';

	function cfg() {
		return window.sigilPasskey || {};
	}

	function i18n(key) {
		var map = cfg().i18n || {};
		return map[key] || '';
	}

	function setStatus(root, message) {
		var el = root.querySelector('.sigil-passkey-status');
		if (el) {
			el.textContent = message || '';
		}
	}

	function isSupported() {
		return !!(
			window.PublicKeyCredential &&
			navigator.credentials &&
			typeof navigator.credentials.create === 'function' &&
			typeof navigator.credentials.get === 'function' &&
			window.isSecureContext
		);
	}

	function base64UrlToBuffer(base64url) {
		if (typeof base64url !== 'string') {
			return base64url;
		}
		var padding = '='.repeat((4 - (base64url.length % 4)) % 4);
		var base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
		var raw = window.atob(base64);
		var buffer = new ArrayBuffer(raw.length);
		var view = new Uint8Array(buffer);
		for (var i = 0; i < raw.length; i++) {
			view[i] = raw.charCodeAt(i);
		}
		return buffer;
	}

	function bufferToBase64Url(buffer) {
		var bytes = new Uint8Array(buffer);
		var str = '';
		for (var i = 0; i < bytes.byteLength; i++) {
			str += String.fromCharCode(bytes[i]);
		}
		return window
			.btoa(str)
			.replace(/\+/g, '-')
			.replace(/\//g, '_')
			.replace(/=+$/g, '');
	}

	function reviveBinaryFields(value) {
		if (!value || typeof value !== 'object') {
			return value;
		}
		if (Array.isArray(value)) {
			for (var i = 0; i < value.length; i++) {
				value[i] = reviveBinaryFields(value[i]);
			}
			return value;
		}
		var keys = Object.keys(value);
		for (var k = 0; k < keys.length; k++) {
			var key = keys[k];
			var item = value[key];
			if (typeof item === 'string' && (
				key === 'challenge' ||
				key === 'id' ||
				key === 'userHandle' ||
				key === 'x5c' ||
				key.endsWith('Id') ||
				key === 'authData' ||
				key === 'signature' ||
				key === 'clientDataJSON' ||
				key === 'attestationObject' ||
				key === 'authenticatorData'
			)) {
				// Only convert fields that look like base64url binary, not plain strings like "public-key".
				if (/^[A-Za-z0-9_-]+=*$/.test(item) && item.length >= 16) {
					value[key] = base64UrlToBuffer(item);
					continue;
				}
			}
			if (key === 'user' && item && typeof item.id === 'string') {
				item.id = base64UrlToBuffer(item.id);
			}
			if (key === 'excludeCredentials' || key === 'allowCredentials') {
				reviveBinaryFields(item);
				continue;
			}
			if (item && typeof item === 'object') {
				reviveBinaryFields(item);
			}
		}
		return value;
	}

	function postForm(action, fields) {
		var body = new window.FormData();
		body.append('action', action);
		body.append('nonce', cfg().nonce || '');
		body.append('user_id', String(cfg().userId || ''));
		Object.keys(fields || {}).forEach(function (key) {
			var val = fields[key];
			if (Array.isArray(val)) {
				body.append(key, JSON.stringify(val));
			} else if (val !== undefined && val !== null) {
				body.append(key, String(val));
			}
		});
		return window.fetch(cfg().ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		}).then(function (res) {
			return res.json();
		});
	}

	function register(root) {
		if (!isSupported()) {
			setStatus(root, i18n('unsupported'));
			return;
		}

		setStatus(root, i18n('registering'));

		postForm('sigil_passkey_register_options', {})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error((json && json.data && json.data.message) || i18n('failed'));
				}
				var createOptions = reviveBinaryFields(json.data);
				// Server returns the PublicKeyCredentialCreationOptions root; navigator expects { publicKey: ... }.
				var publicKey = createOptions.publicKey ? createOptions.publicKey : createOptions;
				return navigator.credentials.create({ publicKey: publicKey });
			})
			.then(function (cred) {
				if (!cred || !cred.response) {
					throw new Error(i18n('failed'));
				}
				var labelInput = root.querySelector('#sigil-passkey-label');
				var transports = [];
				if (typeof cred.response.getTransports === 'function') {
					transports = cred.response.getTransports() || [];
				}
				return postForm('sigil_passkey_register', {
					clientDataJSON: bufferToBase64Url(cred.response.clientDataJSON),
					attestationObject: bufferToBase64Url(cred.response.attestationObject),
					label: labelInput ? labelInput.value : '',
					transports: transports
				});
			})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error((json && json.data && json.data.message) || i18n('failed'));
				}
				setStatus(root, (json.data && json.data.message) || i18n('registered'));
				root.dispatchEvent(new window.CustomEvent('sigil:passkey-registered', { bubbles: true }));
			})
			.catch(function (err) {
				setStatus(root, (err && err.message) || i18n('failed'));
			});
	}

	function authenticate(root) {
		if (!isSupported()) {
			setStatus(root, i18n('unsupported'));
			return;
		}

		setStatus(root, i18n('authenticating'));

		postForm('sigil_passkey_auth_options', {})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error((json && json.data && json.data.message) || i18n('failed'));
				}
				var getOptions = reviveBinaryFields(json.data);
				var publicKey = getOptions.publicKey ? getOptions.publicKey : getOptions;
				return navigator.credentials.get({ publicKey: publicKey });
			})
			.then(function (cred) {
				if (!cred || !cred.response) {
					throw new Error(i18n('failed'));
				}
				var payload = {
					id: bufferToBase64Url(cred.rawId),
					clientDataJSON: bufferToBase64Url(cred.response.clientDataJSON),
					authenticatorData: bufferToBase64Url(cred.response.authenticatorData),
					signature: bufferToBase64Url(cred.response.signature)
				};
				var hidden = root.querySelector('#sigil-passkey-assertion');
				if (hidden) {
					hidden.value = JSON.stringify(payload);
				}
				return postForm('sigil_passkey_auth', payload);
			})
			.then(function (json) {
				if (!json || !json.success) {
					throw new Error((json && json.data && json.data.message) || i18n('failed'));
				}
				setStatus(root, (json.data && json.data.message) || '');
				root.dispatchEvent(new window.CustomEvent('sigil:passkey-authenticated', { bubbles: true }));
			})
			.catch(function (err) {
				setStatus(root, (err && err.message) || i18n('failed'));
			});
	}

	function onClick(event) {
		var target = event.target;
		if (!(target instanceof Element)) {
			return;
		}
		var regBtn = target.closest('.sigil-passkey-register');
		if (regBtn) {
			var regRoot = regBtn.closest('.sigil-passkey-enrol') || document;
			register(regRoot);
			return;
		}
		var authBtn = target.closest('.sigil-passkey-authenticate');
		if (authBtn) {
			var authRoot = authBtn.closest('.sigil-passkey-challenge') || document;
			authenticate(authRoot);
		}
	}

	document.addEventListener('click', onClick);
})();
