<?php
/**
 * Enrolment UI. Variables set by Enrolment::render_enrol_ui().
 *
 * @var int                $user_id
 * @var bool               $is_setup_page
 * @var list<\Easy2FA\Provider> $providers
 * @var array<string, array<string, mixed>> $methods
 * @var array{type: string, code: string, message: string} $notices
 * @var bool               $show_backup
 * @var string             $active
 * @var \Easy2FA\Provider|null $backup_provider
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$user_id       = isset( $user_id ) ? (int) $user_id : 0;
$is_setup_page = ! empty( $is_setup_page );
$providers     = isset( $providers ) && is_array( $providers ) ? $providers : array();
$methods       = isset( $methods ) && is_array( $methods ) ? $methods : array();
$notices       = isset( $notices ) && is_array( $notices ) ? $notices : array( 'type' => '', 'code' => '', 'message' => '' );
$show_backup   = ! empty( $show_backup );
$active        = isset( $active ) ? (string) $active : '';
$post_url      = admin_url( 'admin-post.php' );
?>
<div class="easy2fa-enrol<?php echo $is_setup_page ? ' wrap' : ''; ?>">
	<?php if ( $is_setup_page ) : ?>
		<h1><?php echo esc_html__( 'Two-Factor Authentication', 'easy-2fa' ); ?></h1>
		<p class="easy2fa-enrol__intro">
			<?php echo esc_html__( 'Add a second step to your sign-in. Passkeys are the simplest option when your device supports them; an authenticator app works everywhere.', 'easy-2fa' ); ?>
		</p>
	<?php endif; ?>

	<?php if ( 'success' === ( $notices['type'] ?? '' ) ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			switch ( $notices['code'] ?? '' ) {
				case 'enrolled_backup':
					echo esc_html__( 'Method saved. Save your backup codes below — they will not be shown again.', 'easy-2fa' );
					break;
				case 'enrolled':
					echo esc_html__( 'Authentication method saved.', 'easy-2fa' );
					break;
				case 'removed':
					echo esc_html__( 'Authentication method removed.', 'easy-2fa' );
					break;
				default:
					echo esc_html__( 'Saved.', 'easy-2fa' );
			}
			?>
		</p></div>
	<?php elseif ( 'error' === ( $notices['type'] ?? '' ) ) : ?>
		<div class="notice notice-error is-dismissible"><p>
			<?php
			if ( ! empty( $notices['message'] ) ) {
				echo esc_html( (string) $notices['message'] );
			} else {
				echo esc_html__( 'Could not update two-factor authentication.', 'easy-2fa' );
			}
			?>
		</p></div>
	<?php endif; ?>

	<?php if ( $show_backup && isset( $backup_provider ) && $backup_provider instanceof \Easy2FA\Provider ) : ?>
		<div class="easy2fa-enrol__backup-once notice notice-warning">
			<?php $backup_provider->render_enrol( $user_id ); ?>
		</div>
	<?php endif; ?>

	<?php
	$enrolled_labels = array();
	foreach ( $providers as $provider ) {
		if ( isset( $methods[ $provider->id() ] ) ) {
			$enrolled_labels[] = $provider->label();
		}
	}
	?>
	<?php if ( array() !== $enrolled_labels ) : ?>
		<div class="easy2fa-enrol__status">
			<p>
				<strong><?php echo esc_html__( 'Active methods:', 'easy-2fa' ); ?></strong>
				<?php echo esc_html( implode( ', ', $enrolled_labels ) ); ?>
			</p>
		</div>
	<?php else : ?>
		<div class="easy2fa-enrol__status">
			<p><?php echo esc_html__( 'No authentication methods are set up yet.', 'easy-2fa' ); ?></p>
		</div>
	<?php endif; ?>

	<nav class="easy2fa-enrol__tabs" aria-label="<?php echo esc_attr__( 'Authentication methods', 'easy-2fa' ); ?>">
		<ul>
			<?php foreach ( $providers as $provider ) : ?>
				<?php
				$id      = $provider->id();
				$is_on   = $id === $active;
				$tab_url = $is_setup_page
					? add_query_arg(
						array(
							'page'   => \Easy2FA\Enrolment::PAGE_SLUG,
							'method' => $id,
						),
						admin_url( 'users.php' )
					)
					: add_query_arg( 'method', $id );
				?>
				<li>
					<a class="easy2fa-enrol__tab<?php echo $is_on ? ' is-active' : ''; ?>" href="<?php echo esc_url( $tab_url ); ?>"<?php echo $is_on ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $provider->label() ); ?>
						<?php if ( isset( $methods[ $id ] ) ) : ?>
							<span class="easy2fa-enrol__badge"><?php echo esc_html__( 'On', 'easy-2fa' ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>

	<?php
	$active_provider = null;
	foreach ( $providers as $provider ) {
		if ( $provider->id() === $active ) {
			$active_provider = $provider;
			break;
		}
	}
	?>

	<?php if ( $active_provider ) : ?>
		<div class="easy2fa-enrol__panel" data-provider="<?php echo esc_attr( $active_provider->id() ); ?>">
			<h3><?php echo esc_html( $active_provider->label() ); ?></h3>

			<?php if ( 'backup' === $active_provider->id() ) : ?>
				<?php if ( ! $show_backup ) : ?>
					<?php $active_provider->render_enrol( $user_id ); ?>
				<?php endif; ?>

				<?php if ( isset( $methods['backup'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="easy2fa-enrol__form">
						<input type="hidden" name="action" value="easy2fa_regenerate_backup" />
						<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
						<?php wp_nonce_field( 'easy2fa_regenerate_backup_' . $user_id ); ?>
						<p>
							<button type="submit" class="button">
								<?php echo esc_html__( 'Generate new backup codes', 'easy-2fa' ); ?>
							</button>
						</p>
						<p class="description">
							<?php echo esc_html__( 'Generating new codes invalidates any codes you still have written down.', 'easy-2fa' ); ?>
						</p>
					</form>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="easy2fa-enrol__form">
						<input type="hidden" name="action" value="easy2fa_enrol" />
						<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
						<input type="hidden" name="provider" value="backup" />
						<?php wp_nonce_field( 'easy2fa_enrol_' . $user_id ); ?>
						<p>
							<button type="submit" class="button button-primary">
								<?php echo esc_html__( 'Generate backup codes', 'easy-2fa' ); ?>
							</button>
						</p>
					</form>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( isset( $methods[ $active_provider->id() ] ) ) : ?>
					<p class="easy2fa-enrol__enrolled">
						<?php echo esc_html__( 'This method is set up on your account.', 'easy-2fa' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="easy2fa-enrol__form easy2fa-enrol__form--remove">
						<input type="hidden" name="action" value="easy2fa_remove_method" />
						<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
						<input type="hidden" name="provider" value="<?php echo esc_attr( $active_provider->id() ); ?>" />
						<?php wp_nonce_field( 'easy2fa_remove_' . $user_id . '_' . $active_provider->id() ); ?>
						<p>
							<button type="submit" class="button button-link-delete">
								<?php echo esc_html__( 'Remove this method', 'easy-2fa' ); ?>
							</button>
						</p>
					</form>
					<?php if ( 'passkey' === $active_provider->id() ) : ?>
						<p class="description"><?php echo esc_html__( 'You can register another passkey below.', 'easy-2fa' ); ?></p>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="easy2fa-enrol__form" id="easy2fa-enrol-form-passkey">
							<input type="hidden" name="action" value="easy2fa_enrol" />
							<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
							<input type="hidden" name="provider" value="passkey" />
							<?php wp_nonce_field( 'easy2fa_enrol_' . $user_id ); ?>
							<?php $active_provider->render_enrol( $user_id ); ?>
						</form>
					<?php endif; ?>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="easy2fa-enrol__form" id="easy2fa-enrol-form-<?php echo esc_attr( $active_provider->id() ); ?>">
						<input type="hidden" name="action" value="easy2fa_enrol" />
						<input type="hidden" name="user_id" value="<?php echo esc_attr( (string) $user_id ); ?>" />
						<input type="hidden" name="provider" value="<?php echo esc_attr( $active_provider->id() ); ?>" />
						<?php wp_nonce_field( 'easy2fa_enrol_' . $user_id ); ?>
						<?php $active_provider->render_enrol( $user_id ); ?>
						<?php if ( 'passkey' !== $active_provider->id() ) : ?>
							<p class="submit">
								<button type="submit" class="button button-primary">
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: method label */
											__( 'Enable %s', 'easy-2fa' ),
											$active_provider->label()
										)
									);
									?>
								</button>
							</p>
						<?php endif; ?>
					</form>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
