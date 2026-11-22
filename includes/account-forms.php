<?php
/**
 * Small helpers for WP User Manager's account forms (0.55.0).
 *
 * - Password rules: the register, change-password and reset-password forms
 *   list WPUM's strong-password rules under the password box and tick them
 *   off as the user types. The rules come from WPUM's own filters, so the
 *   list always matches what the server will accept. It never blocks submit;
 *   WPUM still validates.
 * - After sign-up: WPUM's "Registration complete" page gets next steps —
 *   back to the page the visitor came from (remembered in sessionStorage
 *   from ?redirect_to or a same-site referrer), Browse this weekend, and
 *   Submit an event.
 * - [lis_account_settings] for /account/settings/: profile + password
 *   links, an email-preferences placeholder, and "Delete my account", which
 *   files a WordPress privacy erasure request (Tools → Erase Personal Data)
 *   and emails the user a confirmation link. An admin then erases the data
 *   and deletes the user; nothing is removed automatically.
 * - Public profiles are off: WPUM's profile page redirects (signed in → the
 *   account page, guests → home) and the "View Profile" tab is gone. Filter
 *   `lis_directory_hide_public_profiles` (return false) to bring them back.
 * - Photos uploaded through WPUM forms (avatar, cover) are capped at 5 MB
 *   (filter `lis_directory_wpum_max_upload_bytes`).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'lis_directory_render_password_rules', 30 );
add_action( 'wp_footer', 'lis_directory_render_signup_next_steps', 30 );
add_shortcode( 'lis_account_settings', 'lis_directory_render_account_settings_shortcode' );
add_action( 'admin_post_lis_directory_request_deletion', 'lis_directory_handle_request_deletion' );
add_action( 'template_redirect', 'lis_directory_redirect_public_profiles', 5 );
add_filter( 'wpum_get_account_page_tabs', 'lis_directory_remove_profile_tab' );
add_filter( 'wpum_upload_file_pre_upload', 'lis_directory_cap_wpum_upload_size', 10, 2 );

/**
 * WPUM's password rules as a list of array( 'id', 'label', 'test' ), where
 * test is a JS regex source or 'len:<n>'. Empty when strong passwords are off.
 */
function lis_directory_password_rules() {
	if ( ! function_exists( 'wpum_get_option' ) || wpum_get_option( 'disable_strong_passwords' ) ) {
		return array();
	}
	$rules = array();
	if ( apply_filters( 'wpum_strong_password_check_length', true ) ) {
		$min     = max( 1, (int) apply_filters( 'wpum_strong_password_min_length', 8 ) );
		$rules[] = array( 'id' => 'len', 'label' => sprintf( 'At least %d characters', $min ), 'test' => 'len:' . $min );
	}
	if ( apply_filters( 'wpum_strong_password_check_uppercase', true ) ) {
		$rules[] = array( 'id' => 'upper', 'label' => 'One capital letter', 'test' => '[A-Z]' );
	}
	if ( apply_filters( 'wpum_strong_password_check_letter', false ) ) {
		$rules[] = array( 'id' => 'letter', 'label' => 'One letter', 'test' => '[a-zA-Z]' );
	}
	if ( apply_filters( 'wpum_strong_password_check_digit', true ) ) {
		$rules[] = array( 'id' => 'digit', 'label' => 'One number', 'test' => '\\d' );
	}
	if ( apply_filters( 'wpum_strong_password_check_special', true ) ) {
		$rules[] = array( 'id' => 'special', 'label' => 'One symbol (like ! or #)', 'test' => '[^a-zA-Z\\d]' );
	}
	return (array) apply_filters( 'lis_directory_password_rules', $rules );
}

function lis_directory_render_password_rules() {
	if ( is_admin() ) {
		return;
	}
	$rules = lis_directory_password_rules();
	if ( ! $rules ) {
		return;
	}
	?>
	<style id="lis-pw-rules-css">
	.lis-pw-rules { list-style: none; margin: 8px 0 0; padding: 0; font-size: 14px; line-height: 1.5; }
	.lis-pw-rules li { display: flex; align-items: center; gap: 8px; color: #555; }
	.lis-pw-rules li::before { content: ""; flex: 0 0 auto; width: 14px; height: 14px; border: 2px solid #9a9a9a; border-radius: 50%; box-sizing: border-box; }
	.lis-pw-rules li.is-met { color: #1d6b38; }
	.lis-pw-rules li.is-met::before { border-color: #1d6b38; background: #1d6b38 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12'%3E%3Cpath d='M2.5 6.2l2.2 2.2 4.8-4.8' fill='none' stroke='%23fff' stroke-width='1.8'/%3E%3C/svg%3E") center/10px no-repeat; }
	</style>
	<script>
	(function () {
		var RULES = <?php echo wp_json_encode( $rules ); ?>;
		var inputs = document.querySelectorAll('form[id^="wpum-submit-"] input[name="user_password"], form[id^="wpum-submit-password"] input[name="password"]');
		inputs.forEach(function (input, n) {
			var list = document.createElement('ul');
			list.className = 'lis-pw-rules';
			list.id = 'lis-pw-rules-' + n;
			list.setAttribute('aria-live', 'polite');
			var items = RULES.map(function (r) {
				var li = document.createElement('li');
				li.textContent = r.label;
				list.appendChild(li);
				var test = r.test.indexOf('len:') === 0
					? (function (min) { return function (v) { return v.length >= min; }; })(parseInt(r.test.slice(4), 10))
					: (function (re) { return function (v) { return re.test(v); }; })(new RegExp(r.test));
				return { li: li, test: test, label: r.label };
			});
			var box = input.closest('fieldset');
			if (box) { box.appendChild(list); } else { input.parentNode.insertBefore(list, input.nextSibling); }
			var described = input.getAttribute('aria-describedby');
			input.setAttribute('aria-describedby', (described ? described + ' ' : '') + list.id);
			function update() {
				var v = input.value;
				items.forEach(function (it) {
					var ok = it.test(v);
					it.li.classList.toggle('is-met', ok);
					it.li.setAttribute('aria-label', it.label + (ok ? ' (done)' : ''));
				});
			}
			input.addEventListener('input', update);
			update();
		});
	})();
	</script>
	<?php
}

/**
 * Next-step links for the "Registration complete" page. Filter
 * `lis_directory_signup_next_steps` to change them.
 */
function lis_directory_signup_next_steps() {
	$calendar = function_exists( 'lis_events_calendar_page_url' ) ? lis_events_calendar_page_url() : '';
	if ( ! $calendar ) {
		$calendar = home_url( '/services/event-calendar/' );
	}
	$submit = function_exists( 'lis_events_find_submit_page_url' ) ? lis_events_find_submit_page_url() : '';
	$steps  = array(
		array( 'label' => 'Browse this weekend', 'url' => add_query_arg( 'when', 'weekend', $calendar ) ),
	);
	if ( $submit && $submit !== $calendar ) {
		$steps[] = array( 'label' => 'Submit an event', 'url' => $submit );
	}
	return (array) apply_filters( 'lis_directory_signup_next_steps', $steps );
}

function lis_directory_render_signup_next_steps() {
	$register = function_exists( 'wpum_get_core_page_id' ) ? (int) wpum_get_core_page_id( 'register' ) : 0;
	if ( is_admin() || ! $register || ! is_page( $register ) ) {
		return;
	}
	$steps = lis_directory_signup_next_steps();
	?>
	<style id="lis-signup-next-css">
	.lis-signup-next { margin: 18px 0 8px; }
	.lis-signup-next p { margin: 0 0 12px; }
	.lis-signup-next-actions { display: flex; flex-wrap: wrap; gap: 10px; }
	.lis-signup-next-actions a { display: inline-block; padding: 10px 18px; border-radius: 999px; border: 1px solid currentColor; text-decoration: none; font-weight: 600; line-height: 1.3; }
	.lis-signup-next-actions a.is-primary { background: var(--ast-global-color-0, #1d4f5c); border-color: var(--ast-global-color-0, #1d4f5c); color: #fff; }
	</style>
	<script>
	(function () {
		var KEY = 'lis_after_signup';
		var SKIP = /\/account\/(login|register|password-reset)\/?|\/wp-login\.php/;
		function sameSite(u) {
			try { var x = new URL(u, location.href); return x.origin === location.origin && !SKIP.test(x.pathname) ? x.href : ''; } catch (e) { return ''; }
		}
		var form = document.getElementById('wpum-submit-registration-form');
		if (form) {
			var q = new URLSearchParams(location.search);
			var back = sameSite(q.get('redirect_to') || '') || sameSite(document.referrer || '');
			try { if (back) sessionStorage.setItem(KEY, back); } catch (e) {}
			return;
		}
		if (new URLSearchParams(location.search).get('registration') !== 'success') return;
		var ok = document.querySelector('.wpum-message.success');
		if (!ok || document.querySelector('.lis-signup-next')) return;
		var steps = <?php echo wp_json_encode( $steps ); ?>;
		var stored = '';
		try { stored = sameSite(sessionStorage.getItem(KEY) || ''); sessionStorage.removeItem(KEY); } catch (e) {}
		if (stored) steps.unshift({ label: 'Continue where you left off', url: stored });
		var box = document.createElement('div');
		box.className = 'lis-signup-next';
		var p = document.createElement('p');
		p.textContent = 'Check your inbox to confirm your email address. In the meantime:';
		box.appendChild(p);
		var row = document.createElement('div');
		row.className = 'lis-signup-next-actions';
		steps.forEach(function (s, i) {
			var a = document.createElement('a');
			a.href = s.url;
			a.textContent = s.label;
			if (i === 0) a.className = 'is-primary';
			row.appendChild(a);
		});
		box.appendChild(row);
		ok.parentNode.insertBefore(box, ok.nextSibling);
	})();
	</script>
	<?php
}

function lis_directory_account_settings_url() {
	$page = get_page_by_path( 'account/settings' );
	return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : home_url( '/account/settings/' );
}

/**
 * Is there an open (pending or confirmed) erasure request for this email?
 */
function lis_directory_pending_deletion_request( $email ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'user_request',
			'post_name__in'  => array( 'remove_personal_data' ),
			'title'          => $email,
			'post_status'    => array( 'request-pending', 'request-confirmed' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return ! empty( $q->posts );
}

function lis_directory_render_account_settings_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '';
	}
	wp_enqueue_style( 'lis-directory-listings', LIS_DIRECTORY_URL . 'assets/css/listings.css', array(), LIS_DIRECTORY_VERSION );
	$user    = wp_get_current_user();
	$account = get_page_by_path( 'account' );
	$base    = ( $account && 'publish' === $account->post_status ) ? get_permalink( $account ) : home_url( '/account/' );
	$status  = isset( $_GET['lis_deletion'] ) ? sanitize_key( wp_unslash( $_GET['lis_deletion'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$pending = lis_directory_pending_deletion_request( $user->user_email );
	$is_staff = user_can( $user, 'edit_others_posts' );
	ob_start();
	?>
	<div class="lis-account-settings">
		<section class="lis-account-settings-section">
			<h2>Profile and sign-in</h2>
			<p class="lis-account-settings-links">
				<a href="<?php echo esc_url( add_query_arg( 'lis_view', 'profile', $base ) ); ?>">Edit name, email and photo</a>
				<span class="lis-account-overview-sep" aria-hidden="true">&middot;</span>
				<a href="<?php echo esc_url( add_query_arg( 'lis_view', 'password', $base ) ); ?>">Change password</a>
			</p>
		</section>

		<section class="lis-account-settings-section">
			<h2>Email preferences</h2>
			<label class="lis-account-settings-check is-disabled">
				<input type="checkbox" disabled>
				<span>Weekly &ldquo;This weekend in Sandpoint&rdquo; email <em>(coming soon)</em></span>
			</label>
			<p class="lis-account-settings-note">We only email you about your account, listings and events you submit.</p>
		</section>

		<section class="lis-account-settings-section lis-account-settings-danger">
			<h2>Delete my account</h2>
			<?php if ( 'sent' === $status ) : ?>
				<p class="lis-account-settings-msg is-ok" role="status">Check your inbox: we sent a link to <?php echo esc_html( $user->user_email ); ?> to confirm the request.</p>
			<?php elseif ( 'error' === $status ) : ?>
				<p class="lis-account-settings-msg is-error" role="alert">Something went wrong sending the request. Please try again or contact us.</p>
			<?php endif; ?>
			<?php if ( $is_staff ) : ?>
				<p>Staff accounts can&rsquo;t be deleted from here. Ask a site administrator.</p>
			<?php elseif ( $pending ) : ?>
				<p>Your deletion request is in progress. Once you&rsquo;ve confirmed it from the email, we&rsquo;ll remove your account and personal data.</p>
			<?php else : ?>
				<p>We&rsquo;ll email you a link to confirm. After you confirm, a site admin removes your account and personal data, usually within a few days.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Request deletion of your account? We will email you a link to confirm.');">
					<input type="hidden" name="action" value="lis_directory_request_deletion">
					<?php wp_nonce_field( 'lis_directory_request_deletion', 'lis_directory_deletion_nonce' ); ?>
					<button type="submit" class="lis-account-settings-delete">Request account deletion</button>
				</form>
			<?php endif; ?>
		</section>
	</div>
	<?php
	return ob_get_clean();
}

function lis_directory_handle_request_deletion() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to do that.' );
	}
	if ( ! isset( $_POST['lis_directory_deletion_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lis_directory_deletion_nonce'] ) ), 'lis_directory_request_deletion' ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}
	$user = wp_get_current_user();
	$back = lis_directory_account_settings_url();
	if ( user_can( $user, 'edit_others_posts' ) ) {
		wp_safe_redirect( $back );
		exit;
	}
	if ( lis_directory_pending_deletion_request( $user->user_email ) ) {
		wp_safe_redirect( $back );
		exit;
	}
	$request_id = wp_create_user_request( $user->user_email, 'remove_personal_data' );
	$status     = 'error';
	if ( ! is_wp_error( $request_id ) ) {
		$sent   = wp_send_user_request( $request_id );
		$status = is_wp_error( $sent ) ? 'error' : 'sent';
	}
	wp_safe_redirect( add_query_arg( 'lis_deletion', $status, $back ) );
	exit;
}

function lis_directory_hide_public_profiles() {
	return (bool) apply_filters( 'lis_directory_hide_public_profiles', true );
}

function lis_directory_redirect_public_profiles() {
	if ( ! lis_directory_hide_public_profiles() || ! function_exists( 'wpum_get_core_page_id' ) ) {
		return;
	}
	$profile = (int) wpum_get_core_page_id( 'profile' );
	if ( ! $profile || ! is_page( $profile ) ) {
		return;
	}
	if ( is_user_logged_in() ) {
		$account = get_page_by_path( 'account' );
		$dest    = ( $account && 'publish' === $account->post_status ) ? get_permalink( $account ) : home_url( '/account/' );
	} else {
		$dest = home_url( '/' );
	}
	wp_safe_redirect( $dest, 302 );
	exit;
}

function lis_directory_remove_profile_tab( $tabs ) {
	if ( lis_directory_hide_public_profiles() && is_array( $tabs ) ) {
		unset( $tabs['view'] );
	}
	return $tabs;
}

function lis_directory_cap_wpum_upload_size( $file, $args = array() ) {
	if ( is_wp_error( $file ) || ! is_array( $file ) || ! isset( $file['size'] ) ) {
		return $file;
	}
	$max = (int) apply_filters( 'lis_directory_wpum_max_upload_bytes', 5 * MB_IN_BYTES );
	if ( $max > 0 && (int) $file['size'] > $max ) {
		return new WP_Error( 'upload', sprintf( 'That photo is %1$s. Please choose one under %2$s.', size_format( (int) $file['size'], 1 ), size_format( $max ) ) );
	}
	return $file;
}

/**
 * 0.69.0 (phone audit item 29): WordPress's link dialog for the front-end
 * editors (Add Listing, Edit Listing, Post a Job) prints a hidden
 * <h1 id="link-modal-title">Insert/edit link</h1>, giving those pages a second
 * h1. Swap it for an h2 once the page has loaded; the dialog looks the same.
 */
function lis_directory_demote_link_dialog_heading() {
	// Only on pages that printed an editor (wp_enqueue_editor fires from wp_editor()).
	if ( is_admin() || ! did_action( 'wp_enqueue_editor' ) ) {
		return;
	}
	?>
	<script>
	document.addEventListener( 'DOMContentLoaded', function () {
		var h = document.getElementById( 'link-modal-title' );
		if ( ! h || 'H1' !== h.tagName ) {
			return;
		}
		var h2 = document.createElement( 'h2' );
		for ( var i = 0; i < h.attributes.length; i++ ) {
			h2.setAttribute( h.attributes[ i ].name, h.attributes[ i ].value );
		}
		while ( h.firstChild ) {
			h2.appendChild( h.firstChild );
		}
		h.parentNode.replaceChild( h2, h );
	} );
	</script>
	<?php
}
add_action( 'wp_footer', 'lis_directory_demote_link_dialog_heading', 99 );
