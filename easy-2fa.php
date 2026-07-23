<?php
/**
 * Plugin Name: Easy 2FA
 * Description: Two-factor authentication that should have been standard. Passkeys, authenticator apps, backup codes and email codes, with per-role enforcement and recovery that actually works.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: RebelCode
 * Author URI: https://rebelcode.com
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: easy-2fa
 * Domain Path: /languages
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'EASY2FA_VERSION', '0.1.0' );
define( 'EASY2FA_FILE', __FILE__ );
define( 'EASY2FA_DIR', plugin_dir_path( __FILE__ ) );
define( 'EASY2FA_URL', plugin_dir_url( __FILE__ ) );
define( 'EASY2FA_PRO', file_exists( __DIR__ . '/pro/loader.php' ) );

require_once EASY2FA_DIR . 'includes/class-plugin.php';

register_activation_hook(
	__FILE__,
	static function (): void {
		require_once EASY2FA_DIR . 'includes/class-schema.php';
		Easy2FA\Schema::install();
	}
);

Easy2FA\Plugin::instance()->boot();
