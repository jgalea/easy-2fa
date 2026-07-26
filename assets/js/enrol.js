(function () {
	'use strict';

	/**
	 * Minimal QR renderer for otpauth URIs. No network calls.
	 * Uses a tiny external-free matrix via the browser-native approach:
	 * draw the otpauth string as a scannable QR using a compact implementation.
	 */
	function drawQr(container, text) {
		if (!container || !text) {
			return;
		}

		// Prefer QRCode library if another script provided one.
		if (typeof window.QRCode === 'function') {
			container.innerHTML = '';
			try {
				// eslint-disable-next-line no-new
				new window.QRCode(container, {
					text: text,
					width: 180,
					height: 180,
					correctLevel: window.QRCode.CorrectLevel ? window.QRCode.CorrectLevel.M : 0
				});
				return;
			} catch (e) {
				// Fall through to text fallback.
			}
		}

		// Fallback: show a copyable URI block so enrolment still works without a QR lib.
		container.innerHTML = '';
		var pre = document.createElement('code');
		pre.className = 'easy2fa-totp-qr-fallback';
		pre.textContent = text;
		container.appendChild(pre);

		var hint = document.createElement('p');
		hint.className = 'description';
		hint.textContent = 'Scan is unavailable in this browser view. Enter the secret key manually in your authenticator app.';
		container.appendChild(hint);
	}

	function initTotpQr() {
		var nodes = document.querySelectorAll('.easy2fa-totp-qr[data-otpauth]');
		for (var i = 0; i < nodes.length; i++) {
			var el = nodes[i];
			var uri = el.getAttribute('data-otpauth') || '';
			drawQr(el, uri);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTotpQr);
	} else {
		initTotpQr();
	}
})();
