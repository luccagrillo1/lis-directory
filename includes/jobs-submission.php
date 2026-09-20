<?php
/**
 * Job board employer side: [lis_job_submit] (post + edit form), the save
 * handler, and [lis_job_dashboard] (an employer's own postings, with pay /
 * renew / mark-filled / delete). Separate from the business-directory
 * submission wizard and dashboard — different form, handler, nonces and
 * shortcodes, and jobs never show up in the listing dashboard.
 *
 * Flow for a new job: saved as a draft flagged `_lis_job_awaiting_payment`
 * and sent to WooCommerce checkout for the Job Posting product; payment
 * publishes it (includes/jobs-pricing.php). If that product isn't purchasable
 * yet the job goes to `pending` and the site admin is emailed to approve it.
 * Staff who can edit others' posts publish directly, no payment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'lis_job_submit', 'lis_directory_render_job_form_shortcode' );
add_shortcode( 'lis_job_dashboard', 'lis_directory_render_job_dashboard_shortcode' );
add_action( 'admin_post_lis_directory_save_job', 'lis_directory_handle_job_save' );
add_action( 'admin_post_lis_directory_job_action', 'lis_directory_handle_job_action' );

/**
 * Whether the current user may edit / pay for / close this job — its author,
 * or staff who can edit it in wp-admin.
 */
function lis_directory_user_can_manage_job( $job_id ) {
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$job = get_post( $job_id );
	if ( ! $job || 'lis_job' !== $job->post_type ) {
		return false;
	}
	return (int) $job->post_author === get_current_user_id() || current_user_can( 'edit_post', $job_id );
}

function lis_directory_get_job_edit_url( $job_id ) {
	$submit = lis_directory_get_job_page_url( 'submit' );
	if ( $submit ) {
		return add_query_arg( 'job_id', (int) $job_id, $submit );
	}
	return current_user_can( 'edit_post', $job_id ) ? get_edit_post_link( $job_id, 'raw' ) : '';
}

function lis_directory_job_error_message( $key ) {
	$messages = array(
		'title'           => 'A job title is required.',
		'company'         => 'The company name is required.',
		'description'     => 'Please describe the job.',
		'category'        => 'Please choose a category.',
		'employment_type' => 'Please choose an employment type.',
		'workplace'       => 'Please choose on-site, hybrid, or remote.',
		'apply'           => 'Tell applicants how to apply — add an application link or an email address.',
		'apply_url'       => 'The application link must start with http:// or https://.',
		'apply_email'     => 'That application email address doesn\'t look right.',
		'salary'          => 'Check the pay range — it can\'t be negative, and the maximum can\'t be below the minimum.',
		'logo_type'       => 'The logo must be a real PNG or JPG file.',
		'logo_too_large'  => 'The logo must be under 2MB.',
		'logo_failed'     => 'The logo failed to upload — please try again.',
		'save_failed'     => 'Something went wrong saving the job — please try again.',
	);
	return isset( $messages[ $key ] ) ? $messages[ $key ] : 'Please check the form and try again.';
}

/**
 * Human status for the dashboard.
 */
function lis_directory_get_job_status_label( $job_id ) {
	$status = get_post_status( $job_id );
	if ( get_post_meta( $job_id, '_lis_job_filled', true ) ) {
		return 'Filled';
	}
	if ( 'publish' === $status ) {
		return 'Live';
	}
	if ( get_post_meta( $job_id, '_lis_job_expired', true ) ) {
		return 'Expired';
	}
	if ( 'pending' === $status ) {
		return 'Pending review';
	}
	if ( get_post_meta( $job_id, '_lis_job_awaiting_payment', true ) ) {
		return 'Awaiting payment';
	}
	return 'Draft';
}

/* ---------- The form ---------- */

function lis_directory_render_job_form_shortcode() {
	wp_enqueue_style( 'lis-directory-jobs', LIS_DIRECTORY_URL . 'assets/css/jobs.css', array(), LIS_DIRECTORY_VERSION );

	if ( ! is_user_logged_in() ) {
		return '<p class="lis-job-notice">You need an account to post a job. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log in</a> or <a href="' . esc_url( wp_registration_url() ) . '">register</a> first.</p>';
	}

	$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$job    = null;
	if ( $job_id ) {
		if ( ! lis_directory_user_can_manage_job( $job_id ) ) {
			return '<p class="lis-job-notice lis-job-notice--error">You can only edit your own job posts.</p>';
		}
		$job = get_post( $job_id );
	}

	// After a failed save, re-show what the person typed.
	$prefill = array();
	$errors  = array();
	if ( ! empty( $_GET['lis_job_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$errors = array_map( 'sanitize_key', explode( ',', wp_unslash( $_GET['lis_job_error'] ) ) );
		$stash  = get_transient( 'lis_job_form_' . get_current_user_id() );
		if ( is_array( $stash ) ) {
			$prefill = $stash;
		}
		delete_transient( 'lis_job_form_' . get_current_user_id() );
	}

	$meta = function ( $key ) use ( $job, $prefill ) {
		if ( isset( $prefill[ 'lis_job_' . $key ] ) ) {
			return $prefill[ 'lis_job_' . $key ];
		}
		return $job ? get_post_meta( $job->ID, '_lis_job_' . $key, true ) : '';
	};
	$title       = isset( $prefill['lis_job_title'] ) ? $prefill['lis_job_title'] : ( $job ? $job->post_title : '' );
	$description = isset( $prefill['lis_job_description'] ) ? $prefill['lis_job_description'] : ( $job ? $job->post_content : '' );
	$cats        = $job ? get_the_terms( $job->ID, 'lis_job_category' ) : false;
	$cat_id      = isset( $prefill['lis_job_category'] ) ? (int) $prefill['lis_job_category'] : ( ( $cats && ! is_wp_error( $cats ) ) ? (int) $cats[0]->term_id : 0 );
	$company     = $meta( 'company' );
	if ( '' === $company && ! $job ) {
		$company = ''; // Don't guess a company from the user's name.
	}
	$current_url = remove_query_arg( array( 'lis_job_error', 'lis_job_updated', 'lis_job_submitted' ) );
	$price       = $job ? '' : lis_directory_get_job_price_text();

	ob_start();
	echo '<div class="lis-job-formwrap">';

	if ( isset( $_GET['lis_job_updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="lis-job-notice lis-job-notice--success">Job updated.</div>';
	}
	if ( isset( $_GET['lis_job_submitted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="lis-job-notice lis-job-notice--success">Thanks — your job is submitted and will go live once we approve it.</div>';
	}
	if ( $errors ) {
		echo '<div class="lis-job-notice lis-job-notice--error"><p>Please fix the following:</p><ul>';
		foreach ( $errors as $key ) {
			echo '<li>' . esc_html( lis_directory_job_error_message( $key ) ) . '</li>';
		}
		echo '</ul></div>';
	}
	if ( $job && get_post_meta( $job->ID, '_lis_job_awaiting_payment', true ) ) {
		$pay = lis_directory_get_job_checkout_url( $job->ID );
		if ( $pay ) {
			echo '<div class="lis-job-notice">This job isn\'t live yet. <a class="lis-job-btn lis-job-btn--primary" href="' . esc_url( $pay ) . '">Pay &amp; publish</a></div>';
		}
	}
	if ( $price ) {
		$days = lis_directory_get_job_duration_days();
		echo '<p class="lis-job-price-note">Posting a job is <strong>' . esc_html( $price ) . '</strong>' . ( $days ? ' for ' . (int) $days . ' days' : '' ) . '. You\'ll pay at checkout after you save it.</p>';
	}
	?>
	<form class="lis-job-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lis_directory_save_job" />
		<input type="hidden" name="job_id" value="<?php echo (int) $job_id; ?>" />
		<input type="hidden" name="lis_job_redirect_to" value="<?php echo esc_url( $current_url ); ?>" />
		<?php wp_nonce_field( 'lis_job_save_' . $job_id, 'lis_job_save_nonce' ); ?>

		<fieldset>
			<legend>The job</legend>
			<p><label for="lis_job_title">Job title *</label>
			<input type="text" id="lis_job_title" name="lis_job_title" value="<?php echo esc_attr( $title ); ?>" required /></p>

			<p><label for="lis_job_category">Category *</label>
			<select id="lis_job_category" name="lis_job_category" required>
				<option value="">— Select a category —</option>
				<?php foreach ( get_terms( array( 'taxonomy' => 'lis_job_category', 'hide_empty' => false ) ) as $term ) : ?>
					<option value="<?php echo (int) $term->term_id; ?>" <?php selected( $cat_id, $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select></p>

			<div class="lis-job-form-row">
				<p><label for="lis_job_employment_type">Type *</label>
				<select id="lis_job_employment_type" name="lis_job_employment_type" required>
					<?php foreach ( lis_directory_get_job_employment_types() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta( 'employment_type' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>
				<p><label for="lis_job_workplace">Workplace *</label>
				<select id="lis_job_workplace" name="lis_job_workplace" required>
					<?php foreach ( lis_directory_get_job_workplaces() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta( 'workplace' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select></p>
			</div>

			<p><label for="lis_job_location">Location</label>
			<input type="text" id="lis_job_location" name="lis_job_location" value="<?php echo esc_attr( $meta( 'location' ) ); ?>" placeholder="e.g. Sandpoint, ID" /></p>

			<p><label>Pay <span class="lis-job-optional">(optional, but posts with pay get more applicants)</span></label></p>
			<div class="lis-job-form-row lis-job-form-row--pay">
				<input type="number" step="0.01" min="0" name="lis_job_salary_min" value="<?php echo esc_attr( $meta( 'salary_min' ) ); ?>" placeholder="Min $" aria-label="Minimum pay" />
				<input type="number" step="0.01" min="0" name="lis_job_salary_max" value="<?php echo esc_attr( $meta( 'salary_max' ) ); ?>" placeholder="Max $" aria-label="Maximum pay" />
				<select name="lis_job_salary_period" aria-label="Pay period">
					<option value="">Period</option>
					<?php foreach ( lis_directory_get_job_salary_periods() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $meta( 'salary_period' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="lis-job-form-editor">
				<label for="lis_job_description">Description *</label>
				<?php
				wp_editor( $description, 'lis_job_description', array(
					'textarea_name' => 'lis_job_description',
					'textarea_rows' => 10,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => true,
				) );
				?>
			</div>
		</fieldset>

		<fieldset>
			<legend>The company</legend>
			<p><label for="lis_job_company">Company name *</label>
			<input type="text" id="lis_job_company" name="lis_job_company" value="<?php echo esc_attr( $company ); ?>" required /></p>
			<p><label for="lis_job_company_website">Company website <span class="lis-job-optional">(optional)</span></label>
			<input type="url" id="lis_job_company_website" name="lis_job_company_website" value="<?php echo esc_attr( $meta( 'company_website' ) ); ?>" placeholder="https://" /></p>
			<p><label for="lis_job_logo">Logo <span class="lis-job-optional">(optional — PNG or JPG, under 2MB)</span></label>
			<?php if ( $job && has_post_thumbnail( $job->ID ) ) : ?>
				<span class="lis-job-form-logo"><?php echo get_the_post_thumbnail( $job->ID, 'thumbnail' ); ?> <label><input type="checkbox" name="lis_job_remove_logo" value="1" /> Remove</label></span>
			<?php endif; ?>
			<input type="file" id="lis_job_logo" name="lis_job_logo" accept="image/png,image/jpeg" /></p>
		</fieldset>

		<fieldset>
			<legend>How to apply</legend>
			<p><label for="lis_job_apply_url">Application link</label>
			<input type="url" id="lis_job_apply_url" name="lis_job_apply_url" value="<?php echo esc_attr( $meta( 'apply_url' ) ); ?>" placeholder="https://" /></p>
			<p><label for="lis_job_apply_email">Application email</label>
			<input type="email" id="lis_job_apply_email" name="lis_job_apply_email" value="<?php echo esc_attr( $meta( 'apply_email' ) ); ?>" /></p>
			<p class="lis-job-hint">Add at least one. Applicants go straight to you — we don't collect applications.</p>
			<p><label for="lis_job_deadline">Application deadline <span class="lis-job-optional">(optional)</span></label>
			<input type="date" id="lis_job_deadline" name="lis_job_deadline" value="<?php echo esc_attr( $meta( 'deadline' ) ); ?>" /></p>
		</fieldset>

		<p><button type="submit" class="lis-job-btn lis-job-btn--primary"><?php echo $job ? 'Save changes' : 'Save &amp; continue'; ?></button></p>
	</form>
	<?php
	echo '</div>';
	return ob_get_clean();
}

/* ---------- Save ---------- */

/**
 * Upload the optional logo. Returns the attachment id, or 0 (with an error key
 * appended when a file WAS supplied but is bad).
 */
function lis_directory_job_handle_logo_upload( array &$errors ) {
	if ( empty( $_FILES['lis_job_logo'] ) || empty( $_FILES['lis_job_logo']['name'] ) || UPLOAD_ERR_NO_FILE === $_FILES['lis_job_logo']['error'] ) {
		return 0;
	}
	$file = $_FILES['lis_job_logo'];
	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		$errors[] = 'logo_failed';
		return 0;
	}
	if ( $file['size'] > 2 * MB_IN_BYTES ) {
		$errors[] = 'logo_too_large';
		return 0;
	}
	$type = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
	if ( empty( $type['ext'] ) || ! in_array( $type['ext'], array( 'jpg', 'jpeg', 'png' ), true ) || false === @getimagesize( $file['tmp_name'] ) ) { // phpcs:ignore -- failure handled here.
		$errors[] = 'logo_type';
		return 0;
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$restrict = function () {
		return array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png' );
	};
	add_filter( 'upload_mimes', $restrict );
	$attachment_id = media_handle_sideload( $file, 0 );
	remove_filter( 'upload_mimes', $restrict );

	if ( is_wp_error( $attachment_id ) ) {
		$errors[] = 'logo_failed';
		return 0;
	}
	return (int) $attachment_id;
}

function lis_directory_handle_job_save() {
	$fallback = home_url( '/' );
	$back     = isset( $_POST['lis_job_redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['lis_job_redirect_to'] ) ), $fallback ) : $fallback;

	if ( ! is_user_logged_in() ) {
		wp_die( 'You must be logged in to post a job.' );
	}
	$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
	if ( ! isset( $_POST['lis_job_save_nonce'] ) || ! wp_verify_nonce( $_POST['lis_job_save_nonce'], 'lis_job_save_' . $job_id ) ) {
		wp_die( 'Security check failed. Please go back and try again.' );
	}
	if ( $job_id && ! lis_directory_user_can_manage_job( $job_id ) ) {
		wp_die( 'You can only edit your own job posts.' );
	}

	$src    = wp_unslash( $_POST );
	$errors = lis_directory_validate_job_fields( $src );
	if ( '' === trim( isset( $src['lis_job_description'] ) ? wp_strip_all_tags( $src['lis_job_description'] ) : '' ) ) {
		$errors[] = 'description';
	}
	$logo_id = $errors ? 0 : lis_directory_job_handle_logo_upload( $errors );

	if ( $errors ) {
		set_transient( 'lis_job_form_' . get_current_user_id(), $src, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'lis_job_error', implode( ',', array_unique( $errors ) ), $back ) );
		exit;
	}

	$title       = sanitize_text_field( $src['lis_job_title'] );
	$description = wp_kses_post( $src['lis_job_description'] );

	if ( $job_id ) {
		wp_update_post( array( 'ID' => $job_id, 'post_title' => $title, 'post_content' => $description ) );
		lis_directory_save_job_fields( $job_id, $src );
		wp_set_post_terms( $job_id, array( (int) $src['lis_job_category'] ), 'lis_job_category' );
		lis_directory_job_apply_logo( $job_id, $logo_id, ! empty( $src['lis_job_remove_logo'] ) );
		wp_safe_redirect( add_query_arg( array( 'job_id' => $job_id, 'lis_job_updated' => '1' ), $back ) );
		exit;
	}

	$staff    = current_user_can( 'edit_others_posts' );
	$new_id   = wp_insert_post( array(
		'post_type'    => 'lis_job',
		'post_status'  => 'draft',
		'post_title'   => $title,
		'post_content' => $description,
		'post_author'  => get_current_user_id(),
	), true );
	if ( is_wp_error( $new_id ) || ! $new_id ) {
		set_transient( 'lis_job_form_' . get_current_user_id(), $src, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'lis_job_error', 'save_failed', $back ) );
		exit;
	}
	lis_directory_save_job_fields( $new_id, $src );
	wp_set_post_terms( $new_id, array( (int) $src['lis_job_category'] ), 'lis_job_category' );
	lis_directory_job_apply_logo( $new_id, $logo_id, false );

	if ( $staff ) {
		wp_update_post( array( 'ID' => $new_id, 'post_status' => 'publish' ) );
		wp_safe_redirect( get_permalink( $new_id ) );
		exit;
	}

	$checkout = lis_directory_get_job_checkout_url( $new_id );
	if ( $checkout ) {
		update_post_meta( $new_id, '_lis_job_awaiting_payment', 1 );
		wp_safe_redirect( $checkout );
		exit;
	}

	// No purchasable product yet — hold for approval and tell the admin.
	wp_update_post( array( 'ID' => $new_id, 'post_status' => 'pending' ) );
	wp_mail(
		get_option( 'admin_email' ),
		'New job awaiting approval: ' . $title,
		"A new job was submitted and is waiting for review:\n\n" . $title . "\n" . admin_url( 'post.php?post=' . $new_id . '&action=edit' ) . "\n"
	);
	wp_safe_redirect( add_query_arg( 'lis_job_submitted', '1', $back ) );
	exit;
}

function lis_directory_job_apply_logo( $job_id, $new_logo_id, $remove ) {
	if ( $new_logo_id ) {
		set_post_thumbnail( $job_id, $new_logo_id );
	} elseif ( $remove ) {
		delete_post_thumbnail( $job_id );
	}
}

/* ---------- Dashboard ---------- */

function lis_directory_render_job_dashboard_shortcode() {
	wp_enqueue_style( 'lis-directory-jobs', LIS_DIRECTORY_URL . 'assets/css/jobs.css', array(), LIS_DIRECTORY_VERSION );

	if ( ! is_user_logged_in() ) {
		return '<p class="lis-job-notice">Please <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">log in</a> to see your job postings.</p>';
	}

	$jobs = get_posts( array(
		'post_type'      => 'lis_job',
		'post_status'    => array( 'publish', 'draft', 'pending', 'future' ),
		'author'         => get_current_user_id(),
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );

	$messages = array(
		'fill'   => 'Marked as filled — it\'s off the job board.',
		'reopen' => 'Reopened.',
		'delete' => 'Job deleted.',
	);
	$msg = isset( $_GET['lis_job_msg'] ) ? sanitize_key( wp_unslash( $_GET['lis_job_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	ob_start();
	echo '<div class="lis-job-dashboard">';
	if ( isset( $messages[ $msg ] ) ) {
		echo '<div class="lis-job-notice lis-job-notice--success">' . esc_html( $messages[ $msg ] ) . '</div>';
	}
	$post_url = lis_directory_get_job_page_url( 'submit' );
	if ( $post_url ) {
		echo '<p><a class="lis-job-btn lis-job-btn--primary" href="' . esc_url( $post_url ) . '">Post a new job</a></p>';
	}

	if ( ! $jobs ) {
		echo '<p class="lis-job-empty">You haven\'t posted any jobs yet.</p></div>';
		return ob_get_clean();
	}

	echo '<div class="lis-job-dash-list">';
	foreach ( $jobs as $job ) {
		$id      = $job->ID;
		$label   = lis_directory_get_job_status_label( $id );
		$filled  = (bool) get_post_meta( $id, '_lis_job_filled', true );
		$expiry  = (string) get_post_meta( $id, '_lis_job_expiry', true );
		$edit    = lis_directory_get_job_edit_url( $id );
		$pay     = lis_directory_get_job_checkout_url( $id );
		$action  = function ( $do ) use ( $id ) {
			return wp_nonce_url( add_query_arg( array( 'action' => 'lis_directory_job_action', 'do' => $do, 'job_id' => $id ), admin_url( 'admin-post.php' ) ), 'lis_job_action_' . $id );
		};
		echo '<div class="lis-job-dash-item">';
		echo '<div class="lis-job-dash-main">';
		echo 'publish' === $job->post_status
			? '<a class="lis-job-dash-title" href="' . esc_url( get_permalink( $id ) ) . '">' . esc_html( $job->post_title ) . '</a>'
			: '<span class="lis-job-dash-title">' . esc_html( $job->post_title ) . '</span>';
		echo ' <span class="lis-job-status lis-job-status--' . esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $label ) ) ) ) . '">' . esc_html( $label ) . '</span>';
		if ( $expiry && 'publish' === $job->post_status && ! $filled ) {
			echo '<span class="lis-job-dash-meta">Live until ' . esc_html( mysql2date( get_option( 'date_format' ), $expiry ) ) . '</span>';
		}
		echo '</div><div class="lis-job-dash-actions">';
		if ( $edit ) {
			echo '<a href="' . esc_url( $edit ) . '">Edit</a>';
		}
		if ( $pay && get_post_meta( $id, '_lis_job_awaiting_payment', true ) ) {
			echo '<a class="lis-job-dash-pay" href="' . esc_url( $pay ) . '">Pay &amp; publish</a>';
		} elseif ( $pay && ! $filled && ( 'publish' === $job->post_status || get_post_meta( $id, '_lis_job_expired', true ) ) ) {
			echo '<a class="lis-job-dash-pay" href="' . esc_url( $pay ) . '">' . ( 'publish' === $job->post_status ? 'Extend' : 'Renew' ) . '</a>';
		}
		if ( 'publish' === $job->post_status || $filled ) {
			echo $filled
				? '<a href="' . esc_url( $action( 'reopen' ) ) . '">Reopen</a>'
				: '<a href="' . esc_url( $action( 'fill' ) ) . '">Mark filled</a>';
		}
		echo '<a class="lis-job-dash-delete" href="' . esc_url( $action( 'delete' ) ) . '" onclick="return confirm(\'Delete this job post?\');">Delete</a>';
		echo '</div></div>';
	}
	echo '</div></div>';
	return ob_get_clean();
}

/**
 * Dashboard actions: mark filled / reopen / delete (to trash). Plain
 * nonce-protected GET links, same style as the plugin's other admin-post links.
 */
function lis_directory_handle_job_action() {
	$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
	$do     = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
	if ( ! $job_id || ! lis_directory_user_can_manage_job( $job_id ) ) {
		wp_die( 'You can only manage your own job posts.' );
	}
	check_admin_referer( 'lis_job_action_' . $job_id );

	if ( 'fill' === $do ) {
		update_post_meta( $job_id, '_lis_job_filled', 1 );
	} elseif ( 'reopen' === $do ) {
		delete_post_meta( $job_id, '_lis_job_filled' );
	} elseif ( 'delete' === $do ) {
		wp_trash_post( $job_id );
	} else {
		wp_die( 'Unknown action.' );
	}

	$dash = lis_directory_get_job_page_url( 'dashboard' );
	wp_safe_redirect( add_query_arg( 'lis_job_msg', $do, $dash ? $dash : home_url( '/' ) ) );
	exit;
}
