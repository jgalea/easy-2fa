<?php
/**
 * Test environment only: never attempt to send mail.
 *
 * There is no mail transport in the container, so every wp_mail() blocks until
 * an SMTP connection times out. The plugin now mails on enrolment and on
 * deadline assignment, which turned that into multi-second page loads and made
 * the browser suite time out on unrelated assertions.
 *
 * The content of those emails is asserted in the unit suite, which does not go
 * near a transport. This only removes the wait.
 */

declare( strict_types=1 );

add_filter( 'pre_wp_mail', '__return_true', 1 );
