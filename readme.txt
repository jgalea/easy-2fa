=== Sigil – Passkeys and Two-Factor Authentication ===
Contributors: jeangalea
Tags: two-factor, 2fa, passkeys, authentication, security
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.3
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Add two-factor authentication to WordPress logins: passkeys, authenticator apps, backup codes and email codes, with per-role enforcement.

== Description ==

Sigil adds a second authentication step to WordPress logins. Users enrol a passkey, an authenticator app, backup codes or email codes from their profile, and administrators can require 2FA for chosen roles with a grace period.

**Methods**

* Passkeys (WebAuthn), using a platform authenticator such as Face ID, Touch ID or Windows Hello, or a hardware security key. Requires PHP 8.0 or newer.
* Authenticator app (TOTP), compatible with any RFC 6238 application.
* Backup codes: ten single-use codes, generated the first time any method is set up.
* Email codes: a six-digit code sent to the account email address.

**Enforcement**

* Require 2FA per role, or for everyone with a chosen capability.
* Set a grace period so existing users get time to enrol instead of being locked out on the next login.
* A "2FA" column on the Users screen shows who has set it up and who has not.

**Recovery**

Three ways back in if a second factor is lost:

* Backup codes are generated and displayed at first enrolment.
* A user with the `edit_users` capability can reset another user's 2FA from the Users screen.
* `wp sigil reset <user>` clears a user's second factor from the command line when no one can reach the dashboard.

**Application passwords**

Two-factor authentication does not apply to application passwords, which authenticate REST API and XML-RPC requests. The settings screen documents this, and application passwords can be disabled per role.

== Installation ==

1. Install through Plugins → Add New and search for "Sigil", or upload the plugin files to `/wp-content/plugins/sigil-2fa/`.
2. Activate it through the Plugins menu.
3. Go to Users → Two-Factor Setup and enrol your first method. Save the backup codes it shows you.
4. To require 2FA for other users, open Settings → Sigil and choose the roles and grace period.

== Frequently Asked Questions ==

= What happens if I lose my phone and get locked out? =

Use one of the backup codes shown when you first set up 2FA. If you did not keep those, another administrator can reset your account from the Users screen. If nobody can get in at all, anyone with server access runs `wp sigil reset <your-username>` and your second factor is cleared.

= Does the plugin require an external account or service? =

No. All authentication happens on your own site. The plugin does not contact any external service and does not require an account.

= Does this work with application passwords, the REST API, and XML-RPC? =

Application passwords bypass 2FA by design, as that is how WordPress authenticates automated requests. The settings screen documents this, and application passwords can be disabled per role to close that path.

= Which PHP version do I need for passkeys? =

Passkeys need PHP 8.0 or newer. On older PHP the plugin still runs and offers authenticator apps, backup codes, and email; only the passkey method is hidden.

= Can I enforce 2FA only for administrators? =

Yes. Settings → Sigil lets you pick exactly which roles are required, and set a grace period so people are prompted to enrol rather than locked out immediately.

== Changelog ==

= 0.1.3 =
* Fixed the two-factor screen at login: it had no stylesheet, so the method switcher rendered as a bare list and the buttons overlapped.
* Fixed passkey sign-in, which could never complete. The browser prompt was requested through an authenticated endpoint the logged-out challenge screen could not reach.
* Email codes are now actually sent when the screen asks for one, and a wrong code no longer invalidates the code already in your inbox.
* A refresh or a second tab no longer invalidates an outstanding passkey prompt.

= 0.1.2 =
* Author attribution updated to Jean Galea.

= 0.1.1 =
* Renamed the plugin to Sigil. The text domain is now `sigil-2fa` and the WP-CLI command is `wp sigil`.
* Translations are no longer bundled; they come from translate.wordpress.org.

= 0.1.0 =
* First release: passkeys, authenticator apps, backup codes, and email codes.
* Per-role enforcement with a configurable grace period.
* Lockout recovery: backup codes, admin reset, and a WP-CLI reset command.
* Per-role application-password control.
