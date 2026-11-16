<?php
/**
 * Small helpers for WP User Manager's account forms (0.55.0).
 *
 * - Password rules: the register, change-password and reset-password forms
 *   list WPUM's strong-password rules under the password box and tick them
 *   off as the user types. The rules come from WPUM's own filters, so the
 *   list always matches what the server will accept. It never blocks submit;
 *   WPUM still validates.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'lis_directory_render_password_rules', 30 );

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
