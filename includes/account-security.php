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
