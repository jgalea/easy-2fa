<?php
/**
 * Challenge screen. Variables set by Challenge::render_screen().
 *
 * @var string             $challenge_token
 * @var \WP_User           $challenge_user
 * @var list<\Easy2FA\Provider> $challenge_providers
 * @var \Easy2FA\Provider  $challenge_active
 * @var \WP_Error|null     $challenge_error
 * @var bool               $challenge_remember
 * @var string             $challenge_redirect
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$action_url = site_url( 'wp-login.php?action=easy2fa', 'login_post' );
?>
<div class="easy2fa-challenge">
	<h1 class="easy2fa-challenge__title"><?php echo esc_html__( 'Two-factor authentication', 'easy-2fa' ); ?></h1>
	<p class="easy2fa-challenge__intro"><?php echo esc_html__( 'Enter a verification code to finish signing in.', 'easy-2fa' ); ?></p>

	<?php if ( count( $challenge_providers ) > 1 ) : ?>
		<nav class="easy2fa-challenge__methods" aria-label="<?php echo esc_attr__( 'Authentication methods', 'easy-2fa' ); ?>">
			<ul>
				<?php foreach ( $challenge_providers as $provider ) : ?>
					<li>
						<?php if ( $provider->id() === $challenge_active->id() ) : ?>
							<span class="easy2fa-challenge__method is-active" aria-current="page"><?php echo esc_html( $provider->label() ); ?></span>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="easy2fa-challenge__method-form">
								<input type="hidden" name="easy2fa_token" value="<?php echo esc_attr( $challenge_token ); ?>" />
								<input type="hidden" name="easy2fa_provider" value="<?php echo esc_attr( $provider->id() ); ?>" />
								<input type="hidden" name="easy2fa_remember" value="<?php echo $challenge_remember ? '1' : ''; ?>" />
								<input type="hidden" name="easy2fa_redirect_to" value="<?php echo esc_attr( $challenge_redirect ); ?>" />
								<?php wp_nonce_field( 'easy2fa_challenge', 'easy2fa_nonce' ); ?>
								<button type="submit" class="button-link easy2fa-challenge__method"><?php echo esc_html( $provider->label() ); ?></button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<form name="easy2fa_challenge" id="easy2fa-challenge-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
		<input type="hidden" name="easy2fa_token" value="<?php echo esc_attr( $challenge_token ); ?>" />
		<input type="hidden" name="easy2fa_provider" value="<?php echo esc_attr( $challenge_active->id() ); ?>" />
		<input type="hidden" name="easy2fa_remember" value="<?php echo $challenge_remember ? '1' : ''; ?>" />
		<input type="hidden" name="easy2fa_redirect_to" value="<?php echo esc_attr( $challenge_redirect ); ?>" />
		<?php wp_nonce_field( 'easy2fa_challenge', 'easy2fa_nonce' ); ?>

		<div class="easy2fa-challenge__provider">
			<?php $challenge_active->render_challenge( (int) $challenge_user->ID ); ?>
		</div>

		<?php
		/**
		 * Fires inside the challenge form, below the code field. Extension point
		 * for a trusted-device add-on to render its "trust this device" control.
		 *
		 * @param \WP_User $challenge_user The user completing the challenge.
		 */
		do_action( 'easy2fa_challenge_form', $challenge_user );
		?>

		<p class="submit">
			<button type="submit" name="easy2fa_authenticate" id="easy2fa-authenticate" class="button button-primary button-large" value="1">
				<?php echo esc_html__( 'Verify', 'easy-2fa' ); ?>
			</button>
		</p>
	</form>

	<p class="easy2fa-challenge__cancel">
		<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php echo esc_html__( 'Cancel and go back to login', 'easy-2fa' ); ?></a>
	</p>
</div>
