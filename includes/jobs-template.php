<?php
/**
 * Job board front end: template routing, the filterable board (archive +
 * [lis_job_board] shortcode), job cards, and schema.org JobPosting output for
 * Google for Jobs. Everything here is keyed to `lis_job` — none of it touches
 * the business-directory (`lis_listing`) templates or URLs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'single_template', 'lis_directory_job_single_template' );
add_filter( 'archive_template', 'lis_directory_job_archive_template' );
add_filter( 'taxonomy_template', 'lis_directory_job_category_template' );
add_action( 'pre_get_posts', 'lis_directory_job_board_main_query' );
add_action( 'wp_head', 'lis_directory_job_output_meta' );
add_shortcode( 'lis_job_board', 'lis_directory_render_job_board_shortcode' );

function lis_directory_job_single_template( $template ) {
	if ( ! is_singular( 'lis_job' ) ) {
		return $template;
	}
	$theme_template = locate_template( array( 'single-lis_job.php' ) );
	return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/single-job.php';
}

function lis_directory_job_archive_template( $template ) {
	if ( ! is_post_type_archive( 'lis_job' ) ) {
		return $template;
	}
	$theme_template = locate_template( array( 'archive-lis_job.php' ) );
	return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/archive-job.php';
}

function lis_directory_job_category_template( $template ) {
	if ( ! is_tax( 'lis_job_category' ) ) {
		return $template;
	}
	$theme_template = locate_template( array( 'taxonomy-lis_job_category.php' ) );
	return $theme_template ? $theme_template : LIS_DIRECTORY_PATH . 'templates/archive-job.php';
}

/* ---------- Filtering ---------- */

/**
 * The board's filter inputs, sanitized against the real option lists so a
 * hand-edited URL can't inject anything into the query.
 */
function lis_directory_job_read_filters( array $src ) {
	$types      = lis_directory_get_job_employment_types();
	$workplaces = lis_directory_get_job_workplaces();
	$type       = isset( $src['job_type'] ) ? sanitize_key( wp_unslash( $src['job_type'] ) ) : '';
	$workplace  = isset( $src['job_workplace'] ) ? sanitize_key( wp_unslash( $src['job_workplace'] ) ) : '';
	return array(
		'q'         => isset( $src['job_q'] ) ? sanitize_text_field( wp_unslash( $src['job_q'] ) ) : '',
		'cat'       => isset( $src['job_cat'] ) ? sanitize_title( wp_unslash( $src['job_cat'] ) ) : '',
		'type'      => isset( $types[ $type ] ) ? $type : '',
		'workplace' => isset( $workplaces[ $workplace ] ) ? $workplace : '',
	);
}

/**
 * WP_Query args for the public board: published, not filled, plus any filters.
 */
function lis_directory_job_query_args( array $filters, $count = -1 ) {
	$meta = array(
		'relation' => 'AND',
		array(
			'relation' => 'OR',
			array( 'key' => '_lis_job_filled', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_lis_job_filled', 'value' => '1', 'compare' => '!=' ),
		),
	);
	if ( ! empty( $filters['type'] ) ) {
		$meta[] = array( 'key' => '_lis_job_employment_type', 'value' => $filters['type'] );
	}
	if ( ! empty( $filters['workplace'] ) ) {
		$meta[] = array( 'key' => '_lis_job_workplace', 'value' => $filters['workplace'] );
	}

	$args = array(
		'post_type'      => 'lis_job',
		'post_status'    => 'publish',
		'posts_per_page' => $count,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'meta_query'     => $meta,
	);
	if ( ! empty( $filters['q'] ) ) {
		$args['s'] = $filters['q'];
	}
	if ( ! empty( $filters['cat'] ) ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'lis_job_category', 'field' => 'slug', 'terms' => $filters['cat'] ) );
	}
	return $args;
}

/**
 * Apply the same filters to the main query on /jobs/ and /job-category/…,
 * showing every open job on one page (same "scroll, don't paginate" choice
 * the business directory made).
 */
function lis_directory_job_board_main_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! $query->is_post_type_archive( 'lis_job' ) && ! $query->is_tax( 'lis_job_category' ) ) {
		return;
	}
	$filters = lis_directory_job_read_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filters, sanitized above.
	$args    = lis_directory_job_query_args( $filters );

	$query->set( 'posts_per_page', -1 );
	$query->set( 'meta_query', $args['meta_query'] );
	$query->set( 'orderby', 'date' );
	$query->set( 'order', 'DESC' );
	if ( isset( $args['s'] ) ) {
		$query->set( 's', $args['s'] );
	}
	if ( isset( $args['tax_query'] ) && ! $query->is_tax( 'lis_job_category' ) ) {
		$query->set( 'tax_query', $args['tax_query'] );
	}
}

/* ---------- Rendering ---------- */

function lis_directory_render_job_filter_form( $action_url, array $filters ) {
	$cats       = get_terms( array( 'taxonomy' => 'lis_job_category', 'hide_empty' => false ) );
	$cats       = is_wp_error( $cats ) ? array() : $cats;
	$current_cat = $filters['cat'];
	if ( '' === $current_cat && is_tax( 'lis_job_category' ) ) {
		$current_cat = get_queried_object() ? get_queried_object()->slug : '';
	}
	ob_start();
	?>
	<form class="lis-job-filters" method="get" action="<?php echo esc_url( $action_url ); ?>">
		<input type="search" name="job_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Search jobs" aria-label="Search jobs" />
		<select name="job_cat" aria-label="Category">
			<option value="">All categories</option>
			<?php foreach ( $cats as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_cat, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="job_type" aria-label="Job type">
			<option value="">Any type</option>
			<?php foreach ( lis_directory_get_job_employment_types() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="job_workplace" aria-label="Workplace">
			<option value="">Any workplace</option>
			<?php foreach ( lis_directory_get_job_workplaces() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['workplace'], $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="lis-job-btn">Search</button>
	</form>
	<?php
	return ob_get_clean();
}

/**
 * One job card. Uses the current post in a loop, or $job_id explicitly.
 */
function lis_directory_render_job_card( $job_id ) {
	$types      = lis_directory_get_job_employment_types();
	$workplaces = lis_directory_get_job_workplaces();
	$company    = get_post_meta( $job_id, '_lis_job_company', true );
	$location   = get_post_meta( $job_id, '_lis_job_location', true );
	$type       = get_post_meta( $job_id, '_lis_job_employment_type', true );
	$workplace  = get_post_meta( $job_id, '_lis_job_workplace', true );
	$salary     = lis_directory_get_job_salary_text( $job_id );
	$logo_id    = get_post_thumbnail_id( $job_id );
	$cats       = get_the_terms( $job_id, 'lis_job_category' );
	$cat        = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;

	ob_start();
	?>
	<a class="lis-job-card" href="<?php echo esc_url( get_permalink( $job_id ) ); ?>">
		<span class="lis-job-card-logo">
			<?php if ( $logo_id ) : ?>
				<?php echo wp_get_attachment_image( $logo_id, 'thumbnail', false, array( 'alt' => '' ) ); ?>
			<?php else : ?>
				<span class="lis-job-card-logo-fallback" aria-hidden="true"><?php echo esc_html( strtoupper( mb_substr( $company ? $company : get_the_title( $job_id ), 0, 1 ) ) ); ?></span>
			<?php endif; ?>
		</span>
		<span class="lis-job-card-body">
			<span class="lis-job-card-title"><?php echo esc_html( get_the_title( $job_id ) ); ?></span>
			<span class="lis-job-card-company"><?php echo esc_html( implode( ' · ', array_filter( array( $company, $location ) ) ) ); ?></span>
			<span class="lis-job-chips">
				<?php if ( isset( $types[ $type ] ) ) : ?><span class="lis-job-chip"><?php echo esc_html( $types[ $type ] ); ?></span><?php endif; ?>
				<?php if ( isset( $workplaces[ $workplace ] ) && 'onsite' !== $workplace ) : ?><span class="lis-job-chip"><?php echo esc_html( $workplaces[ $workplace ] ); ?></span><?php endif; ?>
				<?php if ( $salary ) : ?><span class="lis-job-chip lis-job-chip--pay"><?php echo esc_html( $salary ); ?></span><?php endif; ?>
				<?php if ( $cat ) : ?><span class="lis-job-chip lis-job-chip--cat"><?php echo esc_html( $cat->name ); ?></span><?php endif; ?>
			</span>
		</span>
		<span class="lis-job-card-date"><?php echo esc_html( human_time_diff( get_post_time( 'U', true, $job_id ), time() ) . ' ago' ); ?></span>
	</a>
	<?php
	return ob_get_clean();
}

function lis_directory_render_job_post_cta() {
	$url = function_exists( 'lis_directory_get_job_page_url' ) ? lis_directory_get_job_page_url( 'submit' ) : '';
	return $url ? '<a class="lis-job-btn lis-job-btn--primary" href="' . esc_url( $url ) . '">Post a job</a>' : '';
}

/**
 * [lis_job_board count="-1" category="slug" type="full-time" search="yes|no"]
 * — the board on an ordinary page. The archive template renders the same
 * pieces against the main query instead.
 */
function lis_directory_render_job_board_shortcode( $atts ) {
	wp_enqueue_style( 'lis-directory-jobs', LIS_DIRECTORY_URL . 'assets/css/jobs.css', array(), LIS_DIRECTORY_VERSION );

	$atts    = shortcode_atts( array( 'count' => -1, 'category' => '', 'type' => '', 'search' => 'yes' ), $atts, 'lis_job_board' );
	$filters = lis_directory_job_read_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( '' !== $atts['category'] && '' === $filters['cat'] ) {
		$filters['cat'] = sanitize_title( $atts['category'] );
	}
	$types = lis_directory_get_job_employment_types();
	if ( '' === $filters['type'] && isset( $types[ $atts['type'] ] ) ) {
		$filters['type'] = $atts['type'];
	}

	$query = new WP_Query( lis_directory_job_query_args( $filters, (int) $atts['count'] ) );

	ob_start();
	echo '<div class="lis-job-board">';
	if ( 'no' !== $atts['search'] ) {
		echo lis_directory_render_job_filter_form( get_permalink(), $filters ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	$cta = lis_directory_render_job_post_cta();
	if ( $cta ) {
		echo '<p class="lis-job-board-cta">' . $cta . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( $query->have_posts() ) {
		echo '<div class="lis-job-list">';
		foreach ( $query->posts as $job ) {
			echo lis_directory_render_job_card( $job->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	} else {
		echo '<p class="lis-job-empty">No open positions match right now.</p>';
	}
	echo '</div>';
	wp_reset_postdata();
	return ob_get_clean();
}

/* ---------- Apply button ---------- */

function lis_directory_render_job_apply_buttons( $job_id ) {
	$url   = get_post_meta( $job_id, '_lis_job_apply_url', true );
	$email = get_post_meta( $job_id, '_lis_job_apply_email', true );
	$out   = '';
	if ( $url ) {
		$out .= '<a class="lis-job-btn lis-job-btn--primary" href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">Apply now</a> ';
	}
	if ( $email ) {
		$subject = 'Application: ' . get_the_title( $job_id );
		$out    .= '<a class="lis-job-btn' . ( $url ? '' : ' lis-job-btn--primary' ) . '" href="mailto:' . esc_attr( antispambot( $email ) ) . '?subject=' . rawurlencode( $subject ) . '">' . ( $url ? 'Apply by email' : 'Apply now' ) . '</a>';
	}
	return $out;
}

/* ---------- Structured data + robots ---------- */

/**
 * schema.org JobPosting for Google for Jobs. Only for a live, open job —
 * a filled or expired posting must not be marked up.
 */
function lis_directory_get_job_jsonld( $job_id ) {
	$post = get_post( $job_id );
	if ( ! $post || ! lis_directory_is_job_open( $job_id ) ) {
		return array();
	}
	$type_map = array(
		'full-time'  => 'FULL_TIME',
		'part-time'  => 'PART_TIME',
		'contract'   => 'CONTRACTOR',
		'temporary'  => 'TEMPORARY',
		'seasonal'   => 'TEMPORARY',
		'internship' => 'INTERN',
		'volunteer'  => 'VOLUNTEER',
	);
	$type      = get_post_meta( $job_id, '_lis_job_employment_type', true );
	$workplace = get_post_meta( $job_id, '_lis_job_workplace', true );
	$location  = get_post_meta( $job_id, '_lis_job_location', true );
	$country   = apply_filters( 'lis_directory_job_country', 'US' );
	$expiry    = (string) get_post_meta( $job_id, '_lis_job_expiry', true );
	$deadline  = (string) get_post_meta( $job_id, '_lis_job_deadline', true );

	$org = array( '@type' => 'Organization', 'name' => get_post_meta( $job_id, '_lis_job_company', true ) );
	$site = get_post_meta( $job_id, '_lis_job_company_website', true );
	if ( $site ) {
		$org['sameAs'] = $site;
	}
	$logo = get_the_post_thumbnail_url( $job_id, 'medium' );
	if ( $logo ) {
		$org['logo'] = $logo;
	}

	$data = array(
		'@context'           => 'https://schema.org/',
		'@type'              => 'JobPosting',
		'title'              => get_the_title( $job_id ),
		'description'        => wpautop( wp_kses_post( $post->post_content ) ),
		'datePosted'         => get_post_time( 'Y-m-d', false, $job_id ),
		'hiringOrganization' => $org,
		'directApply'        => false,
		'url'                => get_permalink( $job_id ),
	);
	if ( isset( $type_map[ $type ] ) ) {
		$data['employmentType'] = $type_map[ $type ];
	}
	if ( $deadline ) {
		$data['validThrough'] = $deadline . 'T23:59:59';
	} elseif ( $expiry ) {
		$data['validThrough'] = str_replace( ' ', 'T', $expiry );
	}
	if ( 'remote' === $workplace ) {
		$data['jobLocationType']                = 'TELECOMMUTE';
		$data['applicantLocationRequirements']  = array( '@type' => 'Country', 'name' => $country );
	}
	if ( $location && 'remote' !== $workplace ) {
		$data['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => array( '@type' => 'PostalAddress', 'addressLocality' => $location, 'addressCountry' => $country ),
		);
	}

	$min = (float) get_post_meta( $job_id, '_lis_job_salary_min', true );
	$max = (float) get_post_meta( $job_id, '_lis_job_salary_max', true );
	$per = array( 'hour' => 'HOUR', 'day' => 'DAY', 'week' => 'WEEK', 'month' => 'MONTH', 'year' => 'YEAR' );
	$period = get_post_meta( $job_id, '_lis_job_salary_period', true );
	if ( ( $min > 0 || $max > 0 ) && isset( $per[ $period ] ) ) {
		$value = array( '@type' => 'QuantitativeValue', 'unitText' => $per[ $period ] );
		if ( $min > 0 && $max > 0 && $max !== $min ) {
			$value['minValue'] = $min;
			$value['maxValue'] = $max;
		} else {
			$value['value'] = $min > 0 ? $min : $max;
		}
		$data['baseSalary'] = array( '@type' => 'MonetaryAmount', 'currency' => 'USD', 'value' => $value );
	}
	return $data;
}

function lis_directory_job_output_meta() {
	if ( ! is_singular( 'lis_job' ) ) {
		return;
	}
	$job_id = get_queried_object_id();
	$json   = lis_directory_get_job_jsonld( $job_id );
	if ( $json ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $json ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON-encoded; wp_json_encode escapes "</".
	} else {
		echo '<meta name="robots" content="noindex, follow" />' . "\n";
	}
}
