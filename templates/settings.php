<?php
/**
 * Settings page template. Variables: $policy, $roles, $updated.
 *
 * @var array  $policy
 * @var array  $roles
 * @var bool   $updated
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! isset( $policy ) || ! is_array( $policy ) ) {
	$policy = \Easy2FA\Policy::get();
}
if ( ! isset( $roles ) || ! is_array( $roles ) ) {
	$roles = array();
}
$updated = ! empty( $updated );
?>
<div class="wrap">
	<h1><?php echo esc_html__( 'Easy 2FA', 'easy-2fa' ); ?></h1>

	<?php if ( $updated ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Settings saved.', 'easy-2fa' ); ?></p></div>
	<?php endif; ?>

	<div class="notice notice-info" style="padding:12px 16px;max-width:720px;">
		<p style="margin:0 0 8px;">
			<strong><?php echo esc_html__( 'What 2FA protects', 'easy-2fa' ); ?></strong>
		</p>
		<p style="margin:0;">
			<?php
			echo esc_html__(
				'Easy 2FA protects interactive logins (the normal WordPress login form and the challenge that follows). It does not apply to application passwords. REST API and XML-RPC requests authenticated with an application password bypass two-factor authentication entirely. If a role should not use that hole, disable application passwords for that role below.',
				'easy-2fa'
			);
			?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="easy2fa_save_settings" />
		<?php wp_nonce_field( 'easy2fa_save_settings' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php echo esc_html__( 'Enforcement', 'easy-2fa' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="easy2fa_policy[enabled]" value="1" <?php checked( ! empty( $policy['enabled'] ) ); ?> />
						<?php echo esc_html__( 'Require two-factor authentication for selected roles', 'easy-2fa' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php echo esc_html__( 'Roles that must enrol', 'easy-2fa' ); ?></th>
				<td>
					<?php if ( array() === $roles ) : ?>
						<p class="description"><?php echo esc_html__( 'No roles found.', 'easy-2fa' ); ?></p>
					<?php else : ?>
						<fieldset>
							<?php foreach ( $roles as $role_slug => $role_obj ) : ?>
								<?php
								$role_slug  = (string) $role_slug;
								$role_label = is_array( $role_obj ) && isset( $role_obj['name'] ) ? translate_user_role( $role_obj['name'] ) : $role_slug;
								$checked    = ! empty( $policy['roles'][ $role_slug ] );
								?>
								<label style="display:block;margin-bottom:4px;">
									<input type="checkbox" name="easy2fa_policy[roles][<?php echo esc_attr( $role_slug ); ?>]" value="1" <?php checked( $checked ); ?> />
									<?php echo esc_html( $role_label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php endif; ?>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="easy2fa-min-capability"><?php echo esc_html__( 'Minimum capability', 'easy-2fa' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="easy2fa-min-capability" name="easy2fa_policy[min_capability]" value="<?php echo esc_attr( (string) $policy['min_capability'] ); ?>" />
					<p class="description">
						<?php echo esc_html__( 'Optional. Any user with this capability is covered regardless of role. Roles and capability are combined with OR.', 'easy-2fa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="easy2fa-grace-days"><?php echo esc_html__( 'Grace period (days)', 'easy-2fa' ); ?></label>
				</th>
				<td>
					<input type="number" min="0" step="1" id="easy2fa-grace-days" name="easy2fa_policy[grace_days]" value="<?php echo esc_attr( (string) (int) $policy['grace_days'] ); ?>" class="small-text" />
					<p class="description">
						<?php echo esc_html__( 'Days after first coverage before enrolment is forced. Zero means enrol on next login.', 'easy-2fa' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php echo esc_html__( 'Disable application passwords', 'easy-2fa' ); ?></th>
				<td>
					<?php if ( array() === $roles ) : ?>
						<p class="description"><?php echo esc_html__( 'No roles found.', 'easy-2fa' ); ?></p>
					<?php else : ?>
						<fieldset>
							<?php foreach ( $roles as $role_slug => $role_obj ) : ?>
								<?php
								$role_slug  = (string) $role_slug;
								$role_label = is_array( $role_obj ) && isset( $role_obj['name'] ) ? translate_user_role( $role_obj['name'] ) : $role_slug;
								$checked    = ! empty( $policy['block_app_passwords'][ $role_slug ] );
								?>
								<label style="display:block;margin-bottom:4px;">
									<input type="checkbox" name="easy2fa_policy[block_app_passwords][<?php echo esc_attr( $role_slug ); ?>]" value="1" <?php checked( $checked ); ?> />
									<?php echo esc_html( $role_label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description">
							<?php echo esc_html__( 'Blocks creating and using application passwords for the selected roles. Use this when interactive 2FA alone is not enough for that role.', 'easy-2fa' ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save settings', 'easy-2fa' ) ); ?>
	</form>

	<?php
	/**
	 * Fires at the end of the Easy 2FA settings screen. Extension point for
	 * add-ons (Pro license entry, trusted-device controls).
	 */
	do_action( 'easy2fa_settings_after' );
	?>
</div>
