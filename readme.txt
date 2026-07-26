=== Easy 2FA ===
Contributors: rebelcode
Tags: two-factor, 2fa, passkeys, authentication, security
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Passkeys, authenticator apps, backup codes and email 2FA, with per-role enforcement and recovery that actually works. All free.

== Description ==

Easy 2FA adds a second step to WordPress logins, and gives away everything a single site needs to be properly protected. Passkeys, authenticator apps, backup codes, per-role enforcement, and recovery that actually gets a locked-out user back in. None of it is held back for a paid tier.

Most 2FA plugins either bury role enforcement behind a paywall, cap the free tier at a handful of users, or ship as one piece of a heavy security suite. Easy 2FA does one job and does it completely.

**Methods**

* Passkeys (WebAuthn) — log in with Face ID, Touch ID, Windows Hello, or a hardware security key. This is the method the setup wizard recommends first.
* Authenticator app (TOTP) — works with Google Authenticator, 1Password, Authy, and any RFC 6238 app.
* Backup codes — ten single-use codes, generated automatically the first time you set up any method, so you are never one lost phone away from being locked out.
* Email codes — a six-digit code sent to your account email, as a fallback for people who will not set up anything else.

**Enforcement**

* Require 2FA per role, or for everyone with a chosen capability.
* Set a grace period so existing users get time to enrol instead of being locked out on the next login.
* A "2FA" column on the Users screen shows who has set it up and who has not.

**Recovery**

Getting locked out is the reason most people never turn 2FA on. Easy 2FA takes it seriously:

* Backup codes are generated and shown at first enrolment, not offered as an afterthought.
* Any administrator can reset another user's 2FA from the Users screen.
* `wp 2fa reset <user>` resets a user from the command line when nobody can get into the dashboard at all.

**Application passwords**

2FA does not apply to application passwords, which authenticate the REST API and XML-RPC. Easy 2FA says so plainly on its settings screen, and lets you disable application passwords per role if you want that surface closed.

== Installation ==

1. Install through Plugins → Add New and search for "Easy 2FA", or upload the plugin files to `/wp-content/plugins/easy-2fa/`.
2. Activate it through the Plugins menu.
3. Go to Users → Two-Factor Setup and enrol your first method. Save the backup codes it shows you.
4. To require 2FA for other users, open Settings → Easy 2FA and choose the roles and grace period.

== Frequently Asked Questions ==

= What happens if I lose my phone and get locked out? =

Use one of the backup codes shown when you first set up 2FA. If you did not keep those, another administrator can reset your account from the Users screen. If nobody can get in at all, anyone with server access runs `wp 2fa reset <your-username>` and your second factor is cleared.

= Do I need an account or a paid plan for passkeys or enforcement? =

No. Passkeys, per-role enforcement, backup codes, and recovery are all in the free plugin. There is no account to create and nothing phones home.

= Does this work with application passwords, the REST API, and XML-RPC? =

Application passwords bypass 2FA by design — that is how WordPress authenticates automated requests. Easy 2FA documents this on its settings screen and lets you disable application passwords per role if you would rather close that path.

= Which PHP version do I need for passkeys? =

Passkeys need PHP 8.0 or newer. On older PHP the plugin still runs and offers authenticator apps, backup codes, and email; only the passkey method is hidden.

= Can I enforce 2FA only for administrators? =

Yes. Settings → Easy 2FA lets you pick exactly which roles are required, and set a grace period so people are prompted to enrol rather than locked out immediately.

== Changelog ==

= 0.1.0 =
* First release: passkeys, authenticator apps, backup codes, and email codes.
* Per-role enforcement with a configurable grace period.
* Lockout recovery: backup codes, admin reset, and a WP-CLI reset command.
* Per-role application-password control.
