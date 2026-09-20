<?php
/**
 * Job board — `lis_job` CPT, taxonomy, fields, admin UI and expiry.
 *
 * Deliberately a SEPARATE system from `lis_listing` (the business directory):
 * its own post type (/jobs/), its own category taxonomy (/job-category/), its
 * own WooCommerce product, shortcodes, templates and stylesheet. The older
 * "Job Listing" *type* on `lis_listing` (includes/listings-meta.php) is left
 * alone and shares nothing with this.
 *
 * Files: jobs-cpt.php (this), jobs-template.php (routing/board/JSON-LD),
 * jobs-submission.php (post/edit form + employer dashboard), jobs-pricing.php
 * (settings, product, checkout, payment → publish).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'lis_directory_register_job_cpt' );
add_action( 'init', 'lis_directory_register_job_taxonomy' );
add_action( 'init', 'lis_directory_seed_job_categories', 20 );
add_action( 'init', 'lis_directory_schedule_job_expiry_check' );
add_action( 'lis_directory_job_daily_expiry', 'lis_directory_run_job_expiry_check' );
add_action( 'transition_post_status', 'lis_directory_job_set_expiry_on_publish', 10, 3 );
add_action( 'add_meta_boxes', 'lis_directory_add_job_meta_box' );
add_action( 'save_post_lis_job', 'lis_directory_save_job_meta_box' );
add_filter( 'manage_lis_job_posts_columns', 'lis_directory_job_admin_columns' );
add_action( 'manage_lis_job_posts_custom_column', 'lis_directory_job_admin_column_content', 10, 2 );

function lis_directory_register_job_cpt() {
	register_post_type( 'lis_job', array(
		'labels'          => array(
			'name'               => 'Jobs',
			'singular_name'      => 'Job',
			'menu_name'          => 'LIS Jobs',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Job',
			'new_item'           => 'New Job',
			'edit_item'          => 'Edit Job',
			'view_item'          => 'View Job',
			'all_items'          => 'All Jobs',
			'search_items'       => 'Search Jobs',
			'not_found'          => 'No jobs found.',
			'not_found_in_trash' => 'No jobs found in Trash.',
			'featured_image'     => 'Company Logo',
			'set_featured_image' => 'Set company logo',
			'remove_featured_image' => 'Remove company logo',
			'use_featured_image' => 'Use as company logo',
		),
		'public'          => true,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'show_in_rest'    => true,
		'menu_position'   => 22,
		'menu_icon'       => 'dashicons-businessperson',
		'has_archive'     => 'jobs',
		'rewrite'         => array( 'slug' => 'jobs', 'with_front' => false ),
		'supports'        => array( 'title', 'editor', 'thumbnail', 'author', 'revisions', 'excerpt' ),
		'capability_type' => 'post',
		'hierarchical'    => false,
		'taxonomies'      => array( 'lis_job_category' ),
	) );
}

function lis_directory_register_job_taxonomy() {
	register_taxonomy( 'lis_job_category', array( 'lis_job' ), array(
		'labels'            => array(
			'name'          => 'Job Categories',
			'singular_name' => 'Job Category',
			'search_items'  => 'Search Job Categories',
			'all_items'     => 'All Job Categories',
			'edit_item'     => 'Edit Job Category',
			'update_item'   => 'Update Job Category',
			'add_new_item'  => 'Add New Job Category',
			'new_item_name' => 'New Job Category Name',
			'menu_name'     => 'Job Categories',
		),
		'hierarchical'      => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'job-category', 'with_front' => false ),
	) );
}

/**
 * One-time starter set so the category dropdown isn't empty on day one. Flagged
 * by an option, so deleting or renaming any of them later sticks.
 */
function lis_directory_seed_job_categories() {
	if ( get_option( 'lis_directory_job_categories_seeded' ) || ! taxonomy_exists( 'lis_job_category' ) ) {
		return;
	}
	foreach ( array( 'Restaurant & Hospitality', 'Retail', 'Skilled Trades & Construction', 'Healthcare', 'Office & Administration', 'Education & Childcare', 'Outdoors & Recreation', 'Sales & Marketing', 'Transportation & Delivery', 'Seasonal', 'Other' ) as $name ) {
		if ( ! term_exists( $name, 'lis_job_category' ) ) {
			wp_insert_term( $name, 'lis_job_category' );
		}
	}
	update_option( 'lis_directory_job_categories_seeded', 1 );
}

/* ---------- Option lists + formatting ---------- */

function lis_directory_get_job_employment_types() {
	return array(
		'full-time'  => 'Full-time',
		'part-time'  => 'Part-time',
		'contract'   => 'Contract',
		'temporary'  => 'Temporary',
		'seasonal'   => 'Seasonal',
		'internship' => 'Internship',
		'volunteer'  => 'Volunteer',
	);
}

function lis_directory_get_job_workplaces() {
	return array(
		'onsite' => 'On-site',
		'hybrid' => 'Hybrid',
		'remote' => 'Remote',
	);
}

function lis_directory_get_job_salary_periods() {
	return array(
		'hour'  => 'per hour',
		'day'   => 'per day',
		'week'  => 'per week',
		'month' => 'per month',
		'year'  => 'per year',
	);
}

/**
 * "$18 – $22 per hour", "$45,000 per year", or '' when no pay is given.
 */
function lis_directory_get_job_salary_text( $job_id ) {
	$min = (float) get_post_meta( $job_id, '_lis_job_salary_min', true );
	$max = (float) get_post_meta( $job_id, '_lis_job_salary_max', true );
	if ( $min <= 0 && $max <= 0 ) {
		return '';
	}
	$fmt = function ( $n ) {
		return '$' . number_format_i18n( $n, floor( $n ) == $n ? 0 : 2 );
	};
	if ( $min > 0 && $max > 0 && $max !== $min ) {
		$text = $fmt( $min ) . ' – ' . $fmt( $max );
	} else {
		$text = $fmt( $min > 0 ? $min : $max );
	}
	$periods = lis_directory_get_job_salary_periods();
	$period  = get_post_meta( $job_id, '_lis_job_salary_period', true );
	return isset( $periods[ $period ] ) ? $text . ' ' . $periods[ $period ] : $text;
}

function lis_directory_get_job_duration_days() {
	$days = get_option( 'lis_directory_job_duration_days', 30 );
	return max( 0, (int) $days );
}

/**
 * Whether a job is live, open and unexpired — what the public board shows.
 */
function lis_directory_is_job_open( $job_id ) {
	return 'publish' === get_post_status( $job_id ) && ! get_post_meta( $job_id, '_lis_job_filled', true );
}

/* ---------- Expiry ---------- */

function lis_directory_schedule_job_expiry_check() {
	if ( ! wp_next_scheduled( 'lis_directory_job_daily_expiry' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'lis_directory_job_daily_expiry' );
	}
}

/**
 * Daily sweep: a published job past its expiry drops to draft, flagged
 * `_lis_job_expired`, so the employer's dashboard can offer a renewal and it
 * leaves every public view.
 */
function lis_directory_run_job_expiry_check() {
	$due = get_posts( array(
		'post_type'      => 'lis_job',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'     => '_lis_job_expiry',
				'value'   => current_time( 'mysql' ),
				'compare' => '<',
				'type'    => 'DATETIME',
			),
		),
	) );
	foreach ( $due as $job_id ) {
		wp_update_post( array( 'ID' => $job_id, 'post_status' => 'draft' ) );
		update_post_meta( $job_id, '_lis_job_expired', 1 );
	}
}

/**
 * First publish (or an admin republishing an expired job) gets the standard
 * posting window, unless a paid order or an admin already set one.
 */
function lis_directory_job_set_expiry_on_publish( $new_status, $old_status, $post ) {
	if ( 'lis_job' !== $post->post_type || 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	$days   = lis_directory_get_job_duration_days();
	$expiry = (string) get_post_meta( $post->ID, '_lis_job_expiry', true );
	$stale  = '' !== $expiry && strtotime( $expiry ) < current_time( 'timestamp' );
	if ( $days > 0 && ( '' === $expiry || $stale ) ) {
		update_post_meta( $post->ID, '_lis_job_expiry', gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) + $days * DAY_IN_SECONDS ) );
	}
	delete_post_meta( $post->ID, '_lis_job_expired' );
	delete_post_meta( $post->ID, '_lis_job_awaiting_payment' );
}

/* ---------- Saving fields (shared by wp-admin and the front-end form) ---------- */

/**
 * Validate the shared job field set. Returns error keys (empty = valid).
 * Reads the same field names the admin meta box and the front-end form both
 * post, so one rule set governs both.
 */
function lis_directory_validate_job_fields( array $src ) {
	$errors = array();

	if ( '' === trim( isset( $src['lis_job_title'] ) ? $src['lis_job_title'] : '' ) ) {
		$errors[] = 'title';
	}
	if ( '' === trim( isset( $src['lis_job_company'] ) ? $src['lis_job_company'] : '' ) ) {
		$errors[] = 'company';
	}
	$term_id = isset( $src['lis_job_category'] ) ? (int) $src['lis_job_category'] : 0;
	$term    = $term_id ? get_term( $term_id, 'lis_job_category' ) : null;
	if ( ! $term || is_wp_error( $term ) ) {
		$errors[] = 'category';
	}
	if ( ! isset( lis_directory_get_job_employment_types()[ isset( $src['lis_job_employment_type'] ) ? $src['lis_job_employment_type'] : '' ] ) ) {
		$errors[] = 'employment_type';
	}
	if ( ! isset( lis_directory_get_job_workplaces()[ isset( $src['lis_job_workplace'] ) ? $src['lis_job_workplace'] : '' ] ) ) {
		$errors[] = 'workplace';
	}

	$url   = isset( $src['lis_job_apply_url'] ) ? trim( $src['lis_job_apply_url'] ) : '';
	$email = isset( $src['lis_job_apply_email'] ) ? trim( $src['lis_job_apply_email'] ) : '';
	if ( '' === $url && '' === $email ) {
		$errors[] = 'apply';
	}
	if ( '' !== $url && ! preg_match( '#^https?://#i', $url ) ) {
		$errors[] = 'apply_url';
	}
	if ( '' !== $email && ! is_email( $email ) ) {
		$errors[] = 'apply_email';
	}

	$min = isset( $src['lis_job_salary_min'] ) && '' !== $src['lis_job_salary_min'] ? (float) $src['lis_job_salary_min'] : 0;
	$max = isset( $src['lis_job_salary_max'] ) && '' !== $src['lis_job_salary_max'] ? (float) $src['lis_job_salary_max'] : 0;
	if ( $min < 0 || $max < 0 || ( $min > 0 && $max > 0 && $max < $min ) ) {
		$errors[] = 'salary';
	}

	return $errors;
}

/**
 * Persist the shared job fields onto an existing job post. $src is already
 * unslashed. Assumes lis_directory_validate_job_fields() passed. The category
 * is NOT set here: wp-admin uses WordPress's own Job Categories box (a second
 * writer in the meta box would clobber it), and the front-end handler sets it.
 */
function lis_directory_save_job_fields( $job_id, array $src ) {
	$text = function ( $key ) use ( $src ) {
		return isset( $src[ $key ] ) ? sanitize_text_field( $src[ $key ] ) : '';
	};

	update_post_meta( $job_id, '_lis_job_company', $text( 'lis_job_company' ) );
	update_post_meta( $job_id, '_lis_job_company_website', isset( $src['lis_job_company_website'] ) ? esc_url_raw( $src['lis_job_company_website'] ) : '' );
	update_post_meta( $job_id, '_lis_job_location', $text( 'lis_job_location' ) );
	update_post_meta( $job_id, '_lis_job_employment_type', $text( 'lis_job_employment_type' ) );
	update_post_meta( $job_id, '_lis_job_workplace', $text( 'lis_job_workplace' ) );

	$min = isset( $src['lis_job_salary_min'] ) && '' !== $src['lis_job_salary_min'] ? (float) $src['lis_job_salary_min'] : '';
	$max = isset( $src['lis_job_salary_max'] ) && '' !== $src['lis_job_salary_max'] ? (float) $src['lis_job_salary_max'] : '';
	update_post_meta( $job_id, '_lis_job_salary_min', $min );
	update_post_meta( $job_id, '_lis_job_salary_max', $max );
	$period = $text( 'lis_job_salary_period' );
	update_post_meta( $job_id, '_lis_job_salary_period', isset( lis_directory_get_job_salary_periods()[ $period ] ) ? $period : '' );

	update_post_meta( $job_id, '_lis_job_apply_url', isset( $src['lis_job_apply_url'] ) ? esc_url_raw( $src['lis_job_apply_url'] ) : '' );
	update_post_meta( $job_id, '_lis_job_apply_email', isset( $src['lis_job_apply_email'] ) ? sanitize_email( $src['lis_job_apply_email'] ) : '' );

	$deadline = $text( 'lis_job_deadline' );
	update_post_meta( $job_id, '_lis_job_deadline', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $deadline ) ? $deadline : '' );
}

/* ---------- wp-admin meta box ---------- */

function lis_directory_add_job_meta_box() {
	add_meta_box( 'lis_job_details', 'Job Details', 'lis_directory_render_job_meta_box', 'lis_job', 'normal', 'high' );
}

function lis_directory_render_job_meta_box( $post ) {
	wp_nonce_field( 'lis_job_meta', 'lis_job_meta_nonce' );
	$m = function ( $key ) use ( $post ) {
		return get_post_meta( $post->ID, '_lis_job_' . $key, true );
	};
	$expiry  = (string) $m( 'expiry' );
	$expiry_date = $expiry ? substr( $expiry, 0, 10 ) : '';
	?>
	<table class="form-table" role="presentation">
		<tr><th><label for="lis_job_company">Company</label></th><td><input type="text" class="regular-text" id="lis_job_company" name="lis_job_company" value="<?php echo esc_attr( $m( 'company' ) ); ?>" /></td></tr>
		<tr><th><label for="lis_job_company_website">Company website</label></th><td><input type="url" class="regular-text" id="lis_job_company_website" name="lis_job_company_website" value="<?php echo esc_attr( $m( 'company_website' ) ); ?>" placeholder="https://" /></td></tr>
		<tr><th><label for="lis_job_employment_type">Employment type</label></th><td>
			<select id="lis_job_employment_type" name="lis_job_employment_type">
				<?php foreach ( lis_directory_get_job_employment_types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $m( 'employment_type' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td></tr>
		<tr><th><label for="lis_job_workplace">Workplace</label></th><td>
			<select id="lis_job_workplace" name="lis_job_workplace">
				<?php foreach ( lis_directory_get_job_workplaces() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $m( 'workplace' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td></tr>
		<tr><th><label for="lis_job_location">Location</label></th><td><input type="text" class="regular-text" id="lis_job_location" name="lis_job_location" value="<?php echo esc_attr( $m( 'location' ) ); ?>" placeholder="e.g. Sandpoint, ID" /></td></tr>
		<tr><th>Pay</th><td>
			$<input type="number" step="0.01" min="0" style="width:110px" name="lis_job_salary_min" value="<?php echo esc_attr( $m( 'salary_min' ) ); ?>" placeholder="min" />
			&ndash; $<input type="number" step="0.01" min="0" style="width:110px" name="lis_job_salary_max" value="<?php echo esc_attr( $m( 'salary_max' ) ); ?>" placeholder="max" />
			<select name="lis_job_salary_period">
				<option value="">—</option>
				<?php foreach ( lis_directory_get_job_salary_periods() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $m( 'salary_period' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</td></tr>
		<tr><th><label for="lis_job_apply_url">Apply URL</label></th><td><input type="url" class="regular-text" id="lis_job_apply_url" name="lis_job_apply_url" value="<?php echo esc_attr( $m( 'apply_url' ) ); ?>" placeholder="https://" /></td></tr>
		<tr><th><label for="lis_job_apply_email">Apply email</label></th><td><input type="email" class="regular-text" id="lis_job_apply_email" name="lis_job_apply_email" value="<?php echo esc_attr( $m( 'apply_email' ) ); ?>" /><p class="description">At least one of Apply URL / Apply email.</p></td></tr>
		<tr><th><label for="lis_job_deadline">Application deadline</label></th><td><input type="date" id="lis_job_deadline" name="lis_job_deadline" value="<?php echo esc_attr( $m( 'deadline' ) ); ?>" /> <span class="description">Optional.</span></td></tr>
		<tr><th><label for="lis_job_expiry_date">Listing expires</label></th><td><input type="date" id="lis_job_expiry_date" name="lis_job_expiry_date" value="<?php echo esc_attr( $expiry_date ); ?>" /><input type="hidden" name="lis_job_expiry_prev" value="<?php echo esc_attr( $expiry_date ); ?>" /> <span class="description">Set automatically on publish (Jobs &rarr; Job Settings). Clear it to never expire.</span></td></tr>
		<tr><th>Status</th><td><label><input type="checkbox" name="lis_job_filled" value="1" <?php checked( (bool) $m( 'filled' ) ); ?> /> Position filled (hides it from the job board)</label></td></tr>
	</table>
	<?php
}

function lis_directory_save_job_meta_box( $post_id ) {
	if ( ! isset( $_POST['lis_job_meta_nonce'] ) || ! wp_verify_nonce( $_POST['lis_job_meta_nonce'], 'lis_job_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	// Admin drafts may be saved half-finished, so only sanitize here — the
	// strict validation applies to the public form.
	lis_directory_save_job_fields( $post_id, wp_unslash( $_POST ) );

	// Only touch the expiry if the admin actually changed the field: the very
	// first publish stamps a default expiry (transition_post_status) BEFORE this
	// runs, and a blank field rendered before that must not wipe it out.
	$expiry_date = isset( $_POST['lis_job_expiry_date'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_job_expiry_date'] ) ) : '';
	$expiry_prev = isset( $_POST['lis_job_expiry_prev'] ) ? sanitize_text_field( wp_unslash( $_POST['lis_job_expiry_prev'] ) ) : '';
	if ( $expiry_date !== $expiry_prev ) {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $expiry_date ) ) {
			update_post_meta( $post_id, '_lis_job_expiry', $expiry_date . ' 23:59:59' );
		} else {
			delete_post_meta( $post_id, '_lis_job_expiry' );
		}
	}
	if ( ! empty( $_POST['lis_job_filled'] ) ) {
		update_post_meta( $post_id, '_lis_job_filled', 1 );
	} else {
		delete_post_meta( $post_id, '_lis_job_filled' );
	}
}

/* ---------- wp-admin list columns ---------- */

function lis_directory_job_admin_columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['lis_job_company'] = 'Company';
			$out['lis_job_type']    = 'Type';
			$out['lis_job_expires'] = 'Expires';
		}
	}
	return $out;
}

function lis_directory_job_admin_column_content( $column, $post_id ) {
	if ( 'lis_job_company' === $column ) {
		echo esc_html( get_post_meta( $post_id, '_lis_job_company', true ) );
	} elseif ( 'lis_job_type' === $column ) {
		$types = lis_directory_get_job_employment_types();
		$type  = get_post_meta( $post_id, '_lis_job_employment_type', true );
		echo esc_html( isset( $types[ $type ] ) ? $types[ $type ] : '' );
		if ( get_post_meta( $post_id, '_lis_job_filled', true ) ) {
			echo ' <em>(filled)</em>';
		}
	} elseif ( 'lis_job_expires' === $column ) {
		$expiry = (string) get_post_meta( $post_id, '_lis_job_expiry', true );
		echo $expiry ? esc_html( substr( $expiry, 0, 10 ) ) : '&mdash;';
		if ( get_post_meta( $post_id, '_lis_job_expired', true ) ) {
			echo ' <em>(expired)</em>';
		}
	}
}
