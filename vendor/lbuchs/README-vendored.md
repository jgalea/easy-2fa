# Vendored: lbuchs/webauthn

- Package: [lbuchs/webauthn](https://github.com/lbuchs/WebAuthn)
- Version: v2.2.0
- Commit: 20adb4a240c3997bd8cac7dc4dde38ab0bea0ed1
- Released: 2024-07-04
- License: MIT
- Why vendored: WordPress.org plugins must ship dependencies; this library has no Composer runtime deps and supports PHP 8.0+. Loaded only when `PHP_VERSION_ID >= 80000` so the plugin stays parse-safe on PHP 7.4.

To update: download a new release, replace `vendor/lbuchs/webauthn/`, update this file, and re-run the passkey test suite.
