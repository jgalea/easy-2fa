<?php
/**
 * Challenge screen. Variables set by Challenge::render_screen().
 *
 * @var string             $challenge_token
 * @var \WP_User           $challenge_user
 * @var list<\Sigil\Provider> $challenge_providers
 * @var \Sigil\Provider  $challenge_active
 * @var \WP_Error|null     $challenge_error
 * @var bool               $challenge_remember
 * @var string             $challenge_redirect
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

// This template is require()d from inside a method, so the variables below are
// function-scoped, not globals. The PrefixAllGlobals sniff cannot see the
// including scope and reports them as unprefixed globals.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$action_url = site_url( 'wp-login.php?action=sigil', 'login_post' );
?>
<div class="sigil-challenge">
	<h1 class="sigil-challenge__title"><?php echo esc_html__( 'Two-factor authentication', 'sigil-2fa' ); ?></h1>
	<p class="sigil-challenge__intro"><?php echo esc_html__( 'Enter a verification code to finish signing in.', 'sigil-2fa' ); ?></p>

	<?php if ( count( $challenge_providers ) > 1 ) : ?>
		<nav class="sigil-challenge__methods" aria-label="<?php echo esc_attr__( 'Authentication methods', 'sigil-2fa' ); ?>">
			<ul>
				<?php foreach ( $challenge_providers as $provider ) : ?>
					<li>
						<?php if ( $provider->id() === $challenge_active->id() ) : ?>
							<span class="sigil-challenge__method is-active" aria-current="page"><?php echo esc_html( $provider->label() ); ?></span>
						<?php else : ?>
							<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="sigil-challenge__method-form">
								<input type="hidden" name="sigil_token" value="<?php echo esc_attr( $challenge_token ); ?>" />
								<input type="hidden" name="sigil_provider" value="<?php echo esc_attr( $provider->id() ); ?>" />
								<input type="hidden" name="sigil_remember" value="<?php echo $challenge_remember ? '1' : ''; ?>" />
								<input type="hidden" name="sigil_redirect_to" value="<?php echo esc_attr( $challenge_redirect ); ?>" />
								<?php wp_nonce_field( 'sigil_challenge', 'sigil_nonce' ); ?>
								<button type="submit" class="button-link sigil-challenge__method"><?php echo esc_html( $provider->label() ); ?></button>
							</form>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<form name="sigil_challenge" id="sigil-challenge-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
		<input type="hidden" name="sigil_token" value="<?php echo esc_attr( $challenge_token ); ?>" />
		<input type="hidden" name="sigil_provider" value="<?php echo esc_attr( $challenge_active->id() ); ?>" />
		<input type="hidden" name="sigil_remember" value="<?php echo $challenge_remember ? '1' : ''; ?>" />
		<input type="hidden" name="sigil_redirect_to" value="<?php echo esc_attr( $challenge_redirect ); ?>" />
		<?php wp_nonce_field( 'sigil_challenge', 'sigil_nonce' ); ?>

		<div class="sigil-challenge__provider">
			<?php $challenge_active->render_challenge( (int) $challenge_user->ID ); ?>
		</div>

		<?php
		/**
		 * Fires inside the challenge form, below the code field. Extension point
		 * for a trusted-device add-on to render its "trust this device" control.
		 *
		 * @param \WP_User $challenge_user The user completing the challenge.
		 */
		do_action( 'sigil_challenge_form', $challenge_user );
		?>

		<p class="submit">
			<button type="submit" name="sigil_authenticate" id="sigil-authenticate" class="button button-primary button-large" value="1">
				<?php echo esc_html__( 'Verify', 'sigil-2fa' ); ?>
			</button>
		</p>
	</form>

	<p class="sigil-challenge__cancel">
		<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php echo esc_html__( 'Cancel and go back to login', 'sigil-2fa' ); ?></a>
	</p>
</div>
