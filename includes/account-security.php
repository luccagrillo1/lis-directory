<?php
/**
 * Site-account hardening shared by LIS Directory and LIS Events.
 *
 * This exact file ships in BOTH plugins (includes/account-security.php). The
 * guard below makes the first copy to load win, so the hooks register once
 * whichever plugin is active. Keep the two copies identical — edit one, copy
 * it over the other, bump LIS_ACCOUNT_SECURITY_VERSION.
 *
 * Registration and its welcome email belong to WP User Manager (third-party,
 * wp-user-manager). Nothing here edits its files: everything goes through
 * WPUM's own filters (wpum_get_email, wpum_email_tags).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'LIS_ACCOUNT_SECURITY_VERSION' ) ) {
	return;
}
define( 'LIS_ACCOUNT_SECURITY_VERSION', '1.0.0' );

/* -------------------------------------------------------------------------
 * 1. No plain-text passwords in the WPUM welcome email.
 *
 * WPUM's default "Registration confirmation" email ends with
 * "Password: {password}". The saved template lives in WPUM's settings, so
 * the content is cleaned at send time: any text run containing {password}
 * is dropped, and a password-reset link is added when the template has none.
 * The {password} tag itself is also neutered so a template edited later
 * can't bring it back.
 * ---------------------------------------------------------------------- */

add_filter( 'wpum_get_email', 'lis_account_strip_password_from_wpum_email', 20, 2 );
add_filter( 'wpum_email_tags', 'lis_account_neuter_wpum_password_tag', 20 );

function lis_account_strip_password_from_wpum_email( $email, $email_id ) {
	if ( ! is_array( $email ) || empty( $email['content'] ) || ! is_string( $email['content'] ) ) {
		return $email;
	}

	$content = $email['content'];

	if ( false !== stripos( $content, '{password}' ) ) {
		// "<br />Password: {password}" or a line/paragraph containing the tag.
		$content = preg_replace( '/(<br\s*\/?>\s*)?[^<>\r\n]*\{password\}[^<>\r\n]*/i', '', $content );
		// Drop paragraphs the removal left empty.
		$content = preg_replace( '/<p>\s*(<br\s*\/?>\s*)*<\/p>/i', '', $content );
	}

	if ( 'registration_confirmation' === $email_id
		&& false === stripos( $content, '{recovery_link}' )
		&& false === stripos( $content, '{recovery_url}' ) ) {
		$content .= "\n<p>We never email passwords. Sign in here: {login_page_link}<br />Forgot it or need to set one? Reset it here: {recovery_link}</p>";
	}

	$email['content'] = $content;
	return $email;
}

function lis_account_neuter_wpum_password_tag( $tags ) {
	if ( ! is_array( $tags ) ) {
		return $tags;
	}
	foreach ( $tags as $i => $tag ) {
		if ( is_array( $tag ) && isset( $tag['tag'] ) && 'password' === $tag['tag'] ) {
			$tags[ $i ]['function'] = 'lis_account_empty_email_tag';
		}
	}
	return $tags;
}

function lis_account_empty_email_tag() {
	return '';
}

/* -------------------------------------------------------------------------
 * 2. Email confirmation before an account can submit anything.
 *
 * Every account registered from now on is flagged unconfirmed
 * (user meta _lis_email_unconfirmed) and emailed a one-time link. Until it's
 * clicked, lis_account_email_confirmed() is false, and the event, listing
 * and job submit forms/handlers refuse new submissions with a "confirm your
 * email" notice and a resend button. Accounts that existed before this
 * shipped have no flag, so they're treated as confirmed — nobody already
 * using the site gets locked out. Editors/admins always pass.
 *
 * Signing in, browsing, saving listings and editing your profile are NOT
 * blocked; only creating public content is.
 * ---------------------------------------------------------------------- */

add_action( 'user_register', 'lis_account_flag_new_user_unconfirmed', 20 );
add_action( 'init', 'lis_account_handle_confirm_link' );
add_action( 'admin_post_lis_account_resend_confirmation', 'lis_account_handle_resend' );
add_action( 'wp_footer', 'lis_account_render_confirm_toast' );

/**
 * Whether a user has confirmed their email (or never needed to).
 */
function lis_account_email_confirmed( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( user_can( $user_id, 'edit_others_posts' ) ) {
		return true;
	}
	$confirmed = ! get_user_meta( $user_id, '_lis_email_unconfirmed', true );
	return (bool) apply_filters( 'lis_account_email_confirmed', $confirmed, $user_id );
}

function lis_account_flag_new_user_unconfirmed( $user_id ) {
	// Accounts an admin creates by hand in wp-admin are trusted as typed.
	if ( is_admin() && current_user_can( 'create_users' ) && ! wp_doing_ajax() ) {
		return;
	}
	if ( ! apply_filters( 'lis_account_require_email_confirmation', true, $user_id ) ) {
		return;
	}
	update_user_meta( $user_id, '_lis_email_unconfirmed', '1' );
	lis_account_send_confirmation( $user_id );
}

/**
 * Generate a fresh key (only its hash is stored) and email the link.
 */
function lis_account_send_confirmation( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return false;
	}

	$key = wp_generate_password( 32, false );
	update_user_meta( $user_id, '_lis_email_confirm_key', wp_hash_password( $key ) );
	update_user_meta( $user_id, '_lis_email_confirm_sent', time() );

	$url = add_query_arg( array(
		'lis_confirm_email' => $user_id,
		'key'               => $key,
	), home_url( '/' ) );

	$site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject   = '[' . $site_name . '] Confirm your email address';
	$message   = 'Hi ' . ( $user->display_name ? $user->display_name : $user->user_login ) . ",\n\n";
	$message  .= "Thanks for creating an account on $site_name. Please confirm this is your email address:\n\n";
	$message  .= $url . "\n\n";
	$message  .= "Once it's confirmed you can submit events, listings and job posts.\n\n";
	$message  .= "If you didn't create this account, you can ignore this email.\n\n";
	$message  .= "— $site_name\n";

	return wp_mail( $user->user_email, $subject, $message );
}

function lis_account_handle_confirm_link() {
	if ( empty( $_GET['lis_confirm_email'] ) || empty( $_GET['key'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$user_id = absint( $_GET['lis_confirm_email'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$key     = sanitize_text_field( wp_unslash( $_GET['key'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$hash    = $user_id ? get_user_meta( $user_id, '_lis_email_confirm_key', true ) : '';

	$ok = false;
	if ( ! get_user_meta( $user_id, '_lis_email_unconfirmed', true ) && $user_id && get_userdata( $user_id ) ) {
		$ok = true; // Already confirmed — a second click is harmless.
	} elseif ( $hash && wp_check_password( $key, $hash ) ) {
		delete_user_meta( $user_id, '_lis_email_unconfirmed' );
		delete_user_meta( $user_id, '_lis_email_confirm_key' );
		update_user_meta( $user_id, '_lis_email_confirmed_at', time() );
		do_action( 'lis_account_after_email_confirmed', $user_id );
		$ok = true;
	}

	$dest = apply_filters( 'lis_account_email_confirmed_redirect', home_url( '/' ), $user_id, $ok );
	wp_safe_redirect( add_query_arg( 'lis_email_confirmed', $ok ? '1' : '0', $dest ) );
	exit;
}

function lis_account_handle_resend() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to do that.', 'Error', array( 'response' => 403 ) );
	}
	if ( ! isset( $_POST['lis_account_resend_nonce'] ) || ! wp_verify_nonce( $_POST['lis_account_resend_nonce'], 'lis_account_resend_confirmation' ) ) {
		wp_die( 'Security check failed. Please go back and try again.', 'Error', array( 'response' => 403 ) );
	}

	$user_id = get_current_user_id();
	$back    = wp_get_referer() ? wp_get_referer() : home_url( '/' );
	$back    = remove_query_arg( 'lis_confirm_resent', $back );
	$last    = (int) get_user_meta( $user_id, '_lis_email_confirm_sent', true );

	$state = 'wait';
	if ( lis_account_email_confirmed( $user_id ) ) {
		$state = 'done';
	} elseif ( time() - $last >= 2 * MINUTE_IN_SECONDS ) {
		$state = lis_account_send_confirmation( $user_id ) ? '1' : 'fail';
	}

	wp_safe_redirect( add_query_arg( 'lis_confirm_resent', $state, $back ) );
	exit;
}

/**
 * Notice shown in place of a submit form while the email is unconfirmed.
 * $what is a short noun phrase: "events", "a listing", "a job post".
 */
function lis_account_confirm_notice_html( $what = 'content' ) {
	$user   = wp_get_current_user();
	$resent = isset( $_GET['lis_confirm_resent'] ) ? sanitize_key( wp_unslash( $_GET['lis_confirm_resent'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$status = array(
		'1'    => 'Sent — check your inbox (and spam folder).',
		'wait' => 'We just sent one. Give it a couple of minutes before asking again.',
		'fail' => "We couldn't send the email. Please contact us.",
		'done' => 'Your email is already confirmed — reload this page.',
	);

	ob_start();
	?>
	<div class="lis-account-confirm-notice" role="status" style="border:1px solid #d9c7a3;background:#fbf6ea;border-radius:8px;padding:16px 18px;margin:0 0 20px;">
		<p style="margin:0 0 8px;"><strong>Please confirm your email address first.</strong></p>
		<p style="margin:0 0 12px;">
			Before you can submit <?php echo esc_html( $what ); ?>, click the link we emailed to
			<strong><?php echo esc_html( $user->user_email ); ?></strong>.
		</p>
		<?php if ( isset( $status[ $resent ] ) ) : ?>
			<p style="margin:0 0 12px;"><em><?php echo esc_html( $status[ $resent ] ); ?></em></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
			<input type="hidden" name="action" value="lis_account_resend_confirmation">
			<?php wp_nonce_field( 'lis_account_resend_confirmation', 'lis_account_resend_nonce' ); ?>
			<button type="submit" class="lis-btn lis-btn-outline">Resend confirmation email</button>
		</form>
	</div>
	<?php
	return ob_get_clean();
}

function lis_account_render_confirm_toast() {
	if ( ! isset( $_GET['lis_email_confirmed'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$ok  = '1' === $_GET['lis_email_confirmed']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$msg = $ok
		? 'Thanks — your email is confirmed. You can now submit events, listings and jobs.'
		: 'That confirmation link is invalid or out of date. Sign in and use "Resend confirmation email" to get a new one.';
	?>
	<div class="lis-account-confirm-toast" role="status" style="position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:99999;max-width:calc(100vw - 32px);background:<?php echo $ok ? '#2c5240' : '#8a3a2a'; ?>;color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.18);font-size:15px;">
		<?php echo esc_html( $msg ); ?>
	</div>
	<?php
}
