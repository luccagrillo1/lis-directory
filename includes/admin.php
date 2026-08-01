<?php
/**
 * Admin list-table columns for the Vendor Showcase — status and category at a glance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'manage_lis_preferred_vendor_posts_columns', 'lis_directory_admin_columns' );
add_action( 'manage_lis_preferred_vendor_posts_custom_column', 'lis_directory_admin_column_content', 10, 2 );
add_action( 'restrict_manage_posts', 'lis_directory_status_filter_dropdown' );
add_action( 'parse_query', 'lis_directory_filter_by_status' );
add_filter( 'post_row_actions', 'lis_directory_approval_row_actions', 10, 2 );
add_action( 'admin_post_lis_directory_approve_vendor', 'lis_directory_handle_approve_vendor' );
add_action( 'admin_post_lis_directory_reject_vendor', 'lis_directory_handle_reject_vendor' );

function lis_directory_admin_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['lis_pv_category'] = 'Category';
			$new['lis_pv_status']   = 'Status';
			$new['lis_pv_term_end'] = 'Term End';
		}
	}
	return $new;
}

function lis_directory_admin_column_content( $column, $post_id ) {
	switch ( $column ) {
		case 'lis_pv_category':
			$terms = get_the_terms( $post_id, 'lis_vendor_category' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
			} else {
				echo '—';
			}
			break;

		case 'lis_pv_status':
			$status = get_post_meta( $post_id, '_lis_pv_status', true );
			$status = $status ? $status : 'pending';
			$label  = isset( LIS_DIRECTORY_STATUSES[ $status ] ) ? LIS_DIRECTORY_STATUSES[ $status ] : ucfirst( $status );
			echo esc_html( $label );
			break;

		case 'lis_pv_term_end':
			$term_end = get_post_meta( $post_id, '_lis_pv_term_end', true );
			echo $term_end ? esc_html( $term_end ) : '—';
			break;
	}
}

/**
 * "Admin approval screen (pending list, approve/reject actions)" per the build
 * brief — implemented as a status filter on the existing list table plus
 * one-click row actions, rather than a separate admin page, since the list
 * table already has the category/status/term-end columns (v0.1.0) to work from.
 */
function lis_directory_status_filter_dropdown( $post_type ) {
	if ( 'lis_preferred_vendor' !== $post_type ) {
		return;
	}

	$current = isset( $_GET['lis_pv_status_filter'] ) ? sanitize_key( $_GET['lis_pv_status_filter'] ) : '';
	?>
	<select name="lis_pv_status_filter">
		<option value="">All statuses</option>
		<?php foreach ( LIS_DIRECTORY_STATUSES as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
	<?php
}

function lis_directory_filter_by_status( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( 'lis_preferred_vendor' !== $query->get( 'post_type' ) ) {
		return;
	}
	if ( empty( $_GET['lis_pv_status_filter'] ) ) {
		return;
	}
	if ( ! array_key_exists( $_GET['lis_pv_status_filter'], LIS_DIRECTORY_STATUSES ) ) {
		return;
	}

	$query->set( 'meta_query', array(
		array(
			'key'   => '_lis_pv_status',
			'value' => sanitize_key( $_GET['lis_pv_status_filter'] ),
		),
	) );
}

function lis_directory_approval_row_actions( $actions, $post ) {
	if ( 'lis_preferred_vendor' !== $post->post_type ) {
		return $actions;
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return $actions;
	}

	$status = get_post_meta( $post->ID, '_lis_pv_status', true );
	if ( 'pending' !== $status ) {
		return $actions;
	}

	$approve_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=lis_directory_approve_vendor&post=' . $post->ID ),
		'lis_pv_approve_' . $post->ID
	);
	$reject_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=lis_directory_reject_vendor&post=' . $post->ID ),
		'lis_pv_reject_' . $post->ID
	);

	$actions['lis_pv_approve'] = '<a href="' . esc_url( $approve_url ) . '">Approve</a>';
	$actions['lis_pv_reject']  = '<a href="' . esc_url( $reject_url ) . '" style="color:#b32d2e;">Reject</a>';

	return $actions;
}

function lis_directory_handle_approve_vendor() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	check_admin_referer( 'lis_pv_approve_' . $post_id );

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( 'You are not allowed to do that.' );
	}

	if ( lis_directory_can_activate_vendor( $post_id ) ) {
		lis_directory_set_vendor_status( $post_id, 'active' );
	}
	// Else: lis_directory_can_activate_vendor() already flagged the reason
	// (category conflict or missing Directorist listing) for the notice.

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=lis_preferred_vendor' ) );
	exit;
}

function lis_directory_handle_reject_vendor() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	check_admin_referer( 'lis_pv_reject_' . $post_id );

	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( 'You are not allowed to do that.' );
	}

	// Rejecting never conflicts with the category lock — only `active` does.
	lis_directory_set_vendor_status( $post_id, 'rejected' );

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=lis_preferred_vendor' ) );
	exit;
}
