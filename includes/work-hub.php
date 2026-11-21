<?php
/**
 * The work board on /jobs/: one page, three columns that share one search
 * and one town filter.
 *
 *   Jobs                 openings from local employers  (_lis_job_kind = hiring)
 *   Local Gigs           one-time work posted by neighbors (_lis_job_kind = gig)
 *   Available for Work   neighbors offering their skills  (_lis_job_kind = seeking)
 *
 * All three are lis_job posts, so posting, expiry, renewals and My Job
 * Postings work the same for every kind. Filtering happens in the browser
 * (assets/js/work-hub.js): the board is small enough to send whole, and
 * filtering in place lets cards animate out instead of reloading the page.
 *
 * First-visit tour: whether someone has finished or dismissed it is kept in
 * the browser (localStorage) and, for signed-in people, on their account
 * (user meta lis_work_tour_done, via POST lis-directory/v1/work-tour) so it
 * follows them across devices. The page itself may be cached for signed-out
 * visitors, so the account flag is only printed for signed-in requests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'lis_directory_work_hub_rest' );

/** Towns offered as filters, in the order shown. Filterable for new towns. */
function lis_directory_work_hub_towns() {
	return apply_filters( 'lis_directory_work_hub_towns', array( 'Sandpoint', 'Ponderay', 'Sagle', 'Dover', 'Kootenai', 'Hope', 'Clark Fork', 'Priest River' ) );
}

/** Whether the archive should show the work board (vs. the classic filtered list). */
function lis_directory_work_hub_active() {
	if ( is_tax( 'lis_job_category' ) || ! is_post_type_archive( 'lis_job' ) ) {
		return false;
	}
	// Old filter links (?job_cat=…, ?job_type=…) still get the classic list.
	foreach ( array( 'job_cat', 'job_type', 'job_workplace', 'job_kind' ) as $key ) {
		if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return false;
		}
	}
	return (bool) apply_filters( 'lis_directory_work_hub_enabled', true );
}

function lis_directory_work_hub_rest() {
	register_rest_route(
		'lis-directory/v1',
		'/work-tour',
		array(
			'methods'             => 'POST',
			'permission_callback' => function () {
				return is_user_logged_in();
			},
			'args'                => array(
				'done' => array( 'type' => 'boolean', 'default' => true ),
			),
			'callback'            => function ( WP_REST_Request $req ) {
				$uid = get_current_user_id();
				if ( $req->get_param( 'done' ) ) {
					update_user_meta( $uid, 'lis_work_tour_done', gmdate( 'Y-m-d H:i:s' ) );
				} else {
					delete_user_meta( $uid, 'lis_work_tour_done' );
				}
				return array( 'done' => (bool) $req->get_param( 'done' ) );
			},
		)
	);
}

/** Which of the board's towns a free-text location mentions ('' if none). */
function lis_directory_work_hub_town_of( $location ) {
	foreach ( lis_directory_work_hub_towns() as $town ) {
		if ( '' !== $location && false !== stripos( $location, $town ) ) {
			return $town;
		}
	}
	return '';
}

/**
 * Everything the board shows, grouped by kind. One query; filled and expired
 * posts are already excluded by lis_directory_job_query_args().
 */
function lis_directory_work_hub_items() {
	$q     = new WP_Query( array_merge( lis_directory_job_query_args( array( 'kind' => '' ) ), array( 'no_found_rows' => true ) ) );
	$types = lis_directory_get_job_employment_types();
	$out   = array( 'hiring' => array(), 'gig' => array(), 'seeking' => array() );
	foreach ( $q->posts as $p ) {
		$id       = $p->ID;
		$kind     = lis_directory_get_job_kind( $id );
		$cats     = get_the_terms( $id, 'lis_job_category' );
		$cat      = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0] : null;
		$location = (string) get_post_meta( $id, '_lis_job_location', true );
		$type     = (string) get_post_meta( $id, '_lis_job_employment_type', true );
		$item     = array(
			'id'       => $id,
			'kind'     => $kind,
			'title'    => get_the_title( $id ),
			'url'      => get_permalink( $id ),
			'name'     => (string) get_post_meta( $id, '_lis_job_company', true ),
			'location' => $location,
			'town'     => lis_directory_work_hub_town_of( $location ),
			'type'     => isset( $types[ $type ] ) ? $type : '',
			'type_lbl' => isset( $types[ $type ] ) ? $types[ $type ] : '',
			'pay'      => lis_directory_get_job_salary_text( $id ),
			'when'     => (string) get_post_meta( $id, '_lis_job_when', true ),
			'cat'      => $cat ? $cat->term_id : 0,
			'cat_name' => $cat ? $cat->name : '',
			'logo'     => get_post_thumbnail_id( $id ),
			'ago'      => human_time_diff( get_post_time( 'U', true, $id ), time() ) . ' ago',
			'deadline' => (string) get_post_meta( $id, '_lis_job_deadline', true ),
			'excerpt'  => wp_trim_words( wp_strip_all_tags( $p->post_content ), 14, '…' ),
			'skills'   => 'seeking' === $kind ? lis_directory_job_skills( $id ) : array(),
			'areas'    => 'seeking' === $kind ? lis_directory_job_areas( $id ) : array(),
			'avail'    => 'seeking' === $kind ? (string) get_post_meta( $id, '_lis_job_availability', true ) : '',
		);
		// Every town a post belongs to: its own, plus any a profile will travel to.
		$item['towns'] = array_values( array_unique( array_filter( array_merge( array( $item['town'] ), $item['areas'] ) ) ) );
		$out[ $kind ][] = $item;
	}
	return $out;
}

function lis_directory_work_hub_initial( $text ) {
	$text = trim( (string) $text );
	return '' === $text ? '?' : strtoupper( mb_substr( $text, 0, 1 ) );
}

/** Stable pick from the brand palette so a name always gets the same color. */
function lis_directory_work_hub_tint( $text ) {
	$tints = array( '#2C5240', '#A94F2C', '#2A5B78', '#37654F', '#8C3E20', '#16281F' );
	return $tints[ abs( crc32( (string) $text ) ) % count( $tints ) ];
}

/** One row in a column. $by_cat maps category id => [ kind => count ] for the cross-links. */
function lis_directory_work_hub_row( array $it, array $by_cat, $allow_seeking ) {
	$search = mb_strtolower( implode( ' ', array_merge( array( $it['title'], $it['name'], $it['location'], $it['cat_name'], $it['type_lbl'], $it['when'], $it['excerpt'], $it['avail'] ), $it['skills'], $it['areas'] ) ) );
	$attrs  = ' data-kind="' . esc_attr( $it['kind'] ) . '" data-towns="' . esc_attr( $it['towns'] ? '|' . implode( '|', $it['towns'] ) . '|' : '' ) . '" data-type="' . esc_attr( $it['type'] ) . '" data-text="' . esc_attr( $search ) . '"';
	if ( 'seeking' === $it['kind'] ) {
		return lis_directory_work_hub_person( $it, $attrs, $by_cat );
	}
	$where  = $it['town'] ? $it['town'] : $it['location'];

	ob_start();
	echo '<li class="wh-row"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	echo '<a class="wh-main" href="' . esc_url( $it['url'] ) . '">';
	if ( 'hiring' === $it['kind'] ) {
		if ( $it['logo'] ) {
			echo '<span class="wh-av wh-av--logo">' . wp_get_attachment_image( $it['logo'], 'thumbnail', false, array( 'alt' => '' ) ) . '</span>';
		} else {
			echo '<span class="wh-av wh-av--logo" style="--c:' . esc_attr( lis_directory_work_hub_tint( $it['name'] ) ) . '" aria-hidden="true">' . esc_html( lis_directory_work_hub_initial( $it['name'] ? $it['name'] : $it['title'] ) ) . '</span>';
		}
		$sub = implode( ' · ', array_filter( array( $it['name'], $it['type_lbl'] ) ) );
	} elseif ( 'gig' === $it['kind'] ) {
		echo '<span class="wh-av wh-av--gig" aria-hidden="true">' . esc_html( lis_directory_work_hub_initial( $it['cat_name'] ? $it['cat_name'] : $it['title'] ) ) . '</span>';
		$sub = implode( ' · ', array_filter( array( $where, $it['when'] ) ) );
	} else {
		echo '<span class="wh-av" style="--c:' . esc_attr( lis_directory_work_hub_tint( $it['name'] ) ) . '" aria-hidden="true">' . esc_html( lis_directory_work_hub_initial( $it['name'] ) ) . '</span>';
		$sub = $it['title'];
	}
	$head = 'seeking' === $it['kind'] ? ( $it['name'] ? $it['name'] : $it['title'] ) : $it['title'];
	echo '<span class="wh-txt"><b>' . esc_html( $head ) . '</b><small>' . esc_html( $sub ) . '</small></span>';
	if ( $it['pay'] ) {
		echo '<em class="wh-pay">' . esc_html( $it['pay'] ) . '</em>';
	}
	echo '</a>';

	// Second line: who, where and when, so a stranger can judge it at a glance.
	$meta = array();
	if ( 'hiring' === $it['kind'] ) {
		if ( $where ) {
			$meta[] = esc_html( $where );
		}
		if ( $it['deadline'] ) {
			$meta[] = 'Apply by ' . esc_html( mysql2date( 'M j', $it['deadline'] . ' 00:00:00' ) );
		}
		$meta[] = esc_html( $it['ago'] );
	} elseif ( 'gig' === $it['kind'] ) {
		if ( $it['name'] ) {
			$meta[] = 'Posted by ' . esc_html( $it['name'] ) . ( $it['town'] ? ', a neighbor in ' . esc_html( $it['town'] ) : '' );
		}
		$meta[] = esc_html( $it['ago'] );
	} else {
		if ( $where ) {
			$meta[] = esc_html( $where );
		}
		if ( $it['cat_name'] ) {
			$meta[] = esc_html( $it['cat_name'] );
		}
	}
	echo '<p class="wh-meta">' . implode( '<i aria-hidden="true"></i>', $meta ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.

	// Cross-links: the two ways into the same local workforce.
	if ( $allow_seeking && $it['cat'] ) {
		$n = isset( $by_cat[ $it['cat'] ] ) ? $by_cat[ $it['cat'] ] : array();
		if ( 'gig' === $it['kind'] && ! empty( $n['seeking'] ) ) {
			$c = (int) $n['seeking'];
			echo '<a class="wh-bridge" href="#wh-people" data-go="people" data-q="' . esc_attr( strtolower( $it['cat_name'] ) ) . '">' . esc_html( $c . ( 1 === $c ? ' neighbor does' : ' neighbors do' ) . ' this kind of work' ) . ' &rarr;</a>';
		} elseif ( 'seeking' === $it['kind'] && ! empty( $n['gig'] ) ) {
			$c = (int) $n['gig'];
			echo '<a class="wh-bridge" href="#wh-gigs" data-go="gigs" data-q="' . esc_attr( strtolower( $it['cat_name'] ) ) . '">' . esc_html( $c . ( 1 === $c ? ' open gig fits' : ' open gigs fit' ) ) . ' &rarr;</a>';
		}
	}
	echo '</li>';
	return ob_get_clean();
}

/**
 * A profile card in Available for Work: who they are, what they do (skills
 * as chips, which are also what search matches), where they'll go, and when.
 */
function lis_directory_work_hub_person( array $it, $attrs, array $by_cat ) {
	$name   = $it['name'] ? $it['name'] : $it['title'];
	$skills = $it['skills'];
	$shown  = array_slice( $skills, 0, 4 );
	$more   = count( $skills ) - count( $shown );

	ob_start();
	echo '<li class="wh-row wh-person"' . $attrs . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the caller.
	echo '<a class="wh-main" href="' . esc_url( $it['url'] ) . '">';
	if ( $it['logo'] ) {
		echo '<span class="wh-av wh-av--photo">' . wp_get_attachment_image( $it['logo'], 'thumbnail', false, array( 'alt' => '' ) ) . '</span>';
	} else {
		echo '<span class="wh-av" style="--c:' . esc_attr( lis_directory_work_hub_tint( $name ) ) . '" aria-hidden="true">' . esc_html( lis_directory_work_hub_initial( $name ) ) . '</span>';
	}
	echo '<span class="wh-txt"><b>' . esc_html( $name ) . '</b><small>' . esc_html( $it['title'] ) . '</small></span>';
	if ( $it['pay'] ) {
		echo '<em class="wh-pay">' . esc_html( $it['pay'] ) . '</em>';
	}
	echo '</a>';

	if ( $shown ) {
		echo '<ul class="wh-skills" aria-label="Skills">';
		foreach ( $shown as $s ) {
			echo '<li>' . esc_html( $s ) . '</li>';
		}
		if ( $more > 0 ) {
			echo '<li class="wh-skills-more">+' . (int) $more . '</li>';
		}
		echo '</ul>';
	}

	$meta = array();
	if ( $it['areas'] ) {
		$areas  = $it['areas'];
		$meta[] = 'Works in ' . esc_html( count( $areas ) > 3 ? implode( ', ', array_slice( $areas, 0, 3 ) ) . ' +' . ( count( $areas ) - 3 ) : implode( ', ', $areas ) );
	} elseif ( $it['town'] || $it['location'] ) {
		$meta[] = 'Based in ' . esc_html( $it['town'] ? $it['town'] : $it['location'] );
	}
	if ( $it['avail'] ) {
		$meta[] = esc_html( $it['avail'] );
	}
	if ( $meta ) {
		echo '<p class="wh-meta">' . implode( '<i aria-hidden="true"></i>', $meta ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above.
	}

	$n = ( $it['cat'] && isset( $by_cat[ $it['cat'] ] ) ) ? $by_cat[ $it['cat'] ] : array();
	if ( ! empty( $n['gig'] ) ) {
		$c = (int) $n['gig'];
		echo '<a class="wh-bridge" href="#wh-gigs" data-go="gigs" data-q="' . esc_attr( mb_strtolower( $it['cat_name'] ) ) . '">' . esc_html( $c . ( 1 === $c ? ' open gig fits' : ' open gigs fit' ) ) . ' &rarr;</a>';
	}
	echo '</li>';
	return ob_get_clean();
}

function lis_directory_render_work_hub() {
	$items         = lis_directory_work_hub_items();
	$allow_seeking = lis_directory_jobs_allow_seeking();
	$submit        = function_exists( 'lis_directory_get_job_page_url' ) ? lis_directory_get_job_page_url( 'submit' ) : '';
	$post_url      = function ( $kind ) use ( $submit ) {
		return $submit ? add_query_arg( 'kind', $kind, $submit ) : '';
	};

	$by_cat = array();
	foreach ( $items as $kind => $list ) {
		foreach ( $list as $it ) {
			if ( $it['cat'] ) {
				$by_cat[ $it['cat'] ][ $kind ] = ( isset( $by_cat[ $it['cat'] ][ $kind ] ) ? $by_cat[ $it['cat'] ][ $kind ] : 0 ) + 1;
			}
		}
	}

	// Only offer towns that have something in them, plus "All towns".
	$towns_used = array();
	foreach ( $items as $list ) {
		foreach ( $list as $it ) {
			foreach ( $it['towns'] as $t ) {
				$towns_used[ $t ] = true;
			}
		}
	}
	$towns = array_values( array_filter( lis_directory_work_hub_towns(), function ( $t ) use ( $towns_used ) {
		return isset( $towns_used[ $t ] );
	} ) );

	// Job types that actually appear, for the chips inside the Jobs column.
	$types_used = array();
	foreach ( $items['hiring'] as $it ) {
		if ( $it['type'] ) {
			$types_used[ $it['type'] ] = $it['type_lbl'];
		}
	}

	$cols = array(
		'jobs'   => array( 'kind' => 'hiring', 'title' => 'Jobs', 'sub' => 'Steady work from local employers', 'post' => 'Post a job', 'post_kind' => 'hiring' ),
		'gigs'   => array( 'kind' => 'gig', 'title' => 'Local Gigs', 'sub' => 'One-time help, posted by neighbors', 'post' => 'Post a gig', 'post_kind' => 'gig' ),
		'people' => array( 'kind' => 'seeking', 'title' => 'Available for Work', 'sub' => 'Neighbors offering their skills', 'post' => 'List yourself', 'post_kind' => 'seeking' ),
	);
	if ( ! $allow_seeking ) {
		unset( $cols['people'] );
	}

	$tour_done = is_user_logged_in() && get_user_meta( get_current_user_id(), 'lis_work_tour_done', true );
	$q         = isset( $_GET['job_q'] ) ? sanitize_text_field( wp_unslash( $_GET['job_q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	wp_enqueue_style( 'lis-directory-work-hub', LIS_DIRECTORY_URL . 'assets/css/work-hub.css', array(), LIS_DIRECTORY_VERSION );
	wp_enqueue_script( 'lis-directory-work-hub', LIS_DIRECTORY_URL . 'assets/js/work-hub.js', array(), LIS_DIRECTORY_VERSION, true );
	wp_localize_script(
		'lis-directory-work-hub',
		'lisWorkHub',
		array(
			'rest'     => esc_url_raw( rest_url( 'lis-directory/v1/work-tour' ) ),
			'nonce'    => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'signedIn' => is_user_logged_in(),
			'tourDone' => (bool) $tour_done,
			'submit'   => $submit,
		)
	);

	ob_start();
	?>
	<section class="wh" id="work" data-col="gigs" data-tour="<?php echo $tour_done ? 'done' : ''; ?>">
		<header class="wh-head">
			<div>
				<p class="wh-kick">Work around Sandpoint</p>
				<h1 class="wh-h1">Find work.<br><span>Find a hand.</span></h1>
				<p class="wh-sub">A free, local board for jobs, odd jobs and the people who do them. No fees, no national middlemen.</p>
				<div class="wh-invite" hidden>
					<p><b>New here?</b> See how this page works in 30 seconds.</p>
					<button type="button" class="wh-tour-btn" data-tour-start>Take a tour</button>
					<button type="button" class="wh-invite-skip" data-tour-skip>No thanks</button>
				</div>
				<button type="button" class="wh-tour-btn wh-tour-again" data-tour-start hidden>Take a tour</button>
			</div>
			<?php if ( $submit ) : ?>
			<div class="wh-posts">
				<a class="wh-btn wh-btn--clay" href="<?php echo esc_url( $post_url( 'gig' ) ); ?>" data-post="gig">Post a gig</a>
				<a class="wh-btn wh-btn--ghost" href="<?php echo esc_url( $post_url( 'hiring' ) ); ?>" data-post="hiring">Post a job</a>
			</div>
			<?php endif; ?>
		</header>

		<div class="wh-tools">
			<form class="wh-search" role="search" action="<?php echo esc_url( get_post_type_archive_link( 'lis_job' ) ); ?>" data-tour-step="search">
				<label><span class="screen-reader-text">Search jobs, gigs and people</span>
				<input type="search" name="job_q" value="<?php echo esc_attr( $q ); ?>" placeholder="Try &ldquo;snow&rdquo;, &ldquo;cook&rdquo;, &ldquo;dog&rdquo;&hellip;" autocomplete="off"></label>
				<p class="wh-counts" aria-live="polite"></p>
			</form>
			<?php if ( $towns ) : ?>
			<div class="wh-towns" role="group" aria-label="Town" data-tour-step="towns">
				<span class="wh-tl">Near</span>
				<button type="button" data-town="" aria-pressed="true">All towns</button>
				<?php foreach ( $towns as $town ) : ?>
					<button type="button" data-town="<?php echo esc_attr( $town ); ?>" aria-pressed="false"><?php echo esc_html( $town ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>

		<div class="wh-tabs" role="tablist">
			<?php foreach ( $cols as $key => $c ) : ?>
				<button type="button" role="tab" data-col="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( 'people' === $key ? 'For hire' : ( 'gigs' === $key ? 'Gigs' : 'Jobs' ) ); ?></button>
			<?php endforeach; ?>
		</div>

		<div class="wh-cols wh-cols--<?php echo count( $cols ); ?>">
			<?php foreach ( $cols as $key => $c ) : ?>
				<?php $list = $items[ $c['kind'] ]; ?>
				<div class="wh-col wh-col--<?php echo esc_attr( $key ); ?>" id="wh-<?php echo esc_attr( $key ); ?>" data-sec="<?php echo esc_attr( $key ); ?>" data-tour-step="<?php echo esc_attr( $key ); ?>">
					<div class="wh-ch">
						<div><h2><?php echo esc_html( $c['title'] ); ?> <span class="wh-n"><?php echo count( $list ); ?></span></h2><p><?php echo esc_html( $c['sub'] ); ?></p></div>
						<?php if ( $submit ) : ?><a class="wh-post" href="<?php echo esc_url( $post_url( $c['post_kind'] ) ); ?>" data-post="<?php echo esc_attr( $c['post_kind'] ); ?>">+ <?php echo esc_html( $c['post'] ); ?></a><?php endif; ?>
					</div>
					<?php if ( 'jobs' === $key && count( $types_used ) > 1 ) : ?>
						<div class="wh-types" role="group" aria-label="Job type">
							<button type="button" data-type="" aria-pressed="true">All</button>
							<?php foreach ( $types_used as $v => $lbl ) : ?>
								<button type="button" data-type="<?php echo esc_attr( $v ); ?>" aria-pressed="false"><?php echo esc_html( $lbl ); ?></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<ul class="wh-list">
						<?php
						foreach ( $list as $it ) {
							echo lis_directory_work_hub_row( $it, $by_cat, $allow_seeking ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</ul>
					<div class="wh-empty" data-empty="<?php echo esc_attr( $key ); ?>" data-total="<?php echo count( $list ); ?>"<?php echo $list ? ' hidden' : ''; ?>>
						<span class="wh-empty-mark" aria-hidden="true"></span>
						<p class="wh-empty-h"></p>
						<p class="wh-empty-p"></p>
						<div class="wh-empty-acts">
							<button type="button" class="wh-empty-clear" hidden>Show everything</button>
							<?php if ( $submit ) : ?><a class="wh-empty-post" href="<?php echo esc_url( $post_url( $c['post_kind'] ) ); ?>" data-post="<?php echo esc_attr( $c['post_kind'] ); ?>"></a><?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $submit ) : ?>
		<div class="wh-postbox" id="wh-post" data-tour-step="post">
			<h2 class="wh-posth">Post something. It's free.</h2>
			<div class="wh-compose">
				<form class="wh-compose-form" method="get" action="<?php echo esc_url( $submit ); ?>">
					<div class="wh-seg" role="radiogroup" aria-label="What are you posting?">
						<label><input type="radio" name="kind" value="gig" checked> <span>A gig</span></label>
						<label><input type="radio" name="kind" value="hiring"> <span>A job</span></label>
						<?php if ( $allow_seeking ) : ?><label><input type="radio" name="kind" value="seeking"> <span>I'm available</span></label><?php endif; ?>
					</div>
					<label class="wh-f"><span data-l="title">What do you need done?</span><input type="text" name="title" maxlength="120" placeholder="Rake leaves in the front yard"></label>
					<div class="wh-f2">
						<label class="wh-f"><span>Where</span><input type="text" name="location" maxlength="80" placeholder="Sagle"></label>
						<label class="wh-f" data-only="gig"><span>When</span><input type="text" name="when" maxlength="80" placeholder="This Saturday"></label>
					</div>
					<button type="submit" class="wh-btn wh-btn--clay wh-go">Continue</button>
					<small class="wh-fine">Next you'll add the details and how people reach you. Free, and live as soon as you save it.</small>
				</form>
				<div class="wh-prev" aria-hidden="true">
					<p class="wh-prev-k">Your post will look like this</p>
					<div class="wh-prev-card"><span class="wh-prev-kind">Local gig</span><b>Rake leaves in the front yard</b><span class="wh-prev-sub">Sagle · This Saturday</span></div>
				</div>
			</div>
		</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
