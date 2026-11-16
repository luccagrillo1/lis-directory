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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'lis_directory_render_password_rules', 30 );
add_action( 'wp_footer', 'lis_directory_render_signup_next_steps', 30 );

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
