<div align="center">

# Easy 2FA

[![License](https://img.shields.io/badge/LICENSE-GPL--3.0-5C9E31?style=for-the-badge)](LICENSE)
[![Built by](https://img.shields.io/badge/BUILT%20BY-REBELCODE-8A2BE2?style=for-the-badge)](https://rebelcode.com)

**Two-factor authentication for WordPress: passkeys, authenticator apps, backup codes and email codes, with per-role enforcement and recovery that actually works.**

</div>

Easy 2FA does one job completely instead of holding half of it back. Passkeys, per-role enforcement, backup codes and lockout recovery are all in the free plugin. There is no account to create and nothing phones home.

## Methods

- Passkeys (WebAuthn) — Face ID, Touch ID, Windows Hello, or a hardware key. Needs PHP 8.0+; on older PHP the method is hidden and the rest still works.
- Authenticator app (TOTP) — any RFC 6238 app.
- Backup codes — ten single-use codes, generated at first enrolment.
- Email codes — six digits to the account email, as a fallback.

## Enforcement

Require 2FA per role or by capability, with a grace period so existing users get time to enrol. A "2FA" column on the Users screen shows who has set it up.

## Recovery

Backup codes are shown at first enrolment. Any administrator can reset another user's 2FA from the Users screen, and `wp 2fa reset <user>` clears a second factor from the command line when nobody can reach the dashboard.

## Application passwords

Application passwords authenticate the REST API and XML-RPC, and 2FA does not apply to them. Easy 2FA says so on its settings screen and lets you disable them per role.

## Development

```
composer install && npm install
composer test                             # unit tests
npx wp-env start && npx playwright test    # end-to-end, on localhost:8877
./build/build-free.sh                      # dist/easy-2fa.zip
```

Requires WordPress 6.9+ and PHP 7.4+.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
