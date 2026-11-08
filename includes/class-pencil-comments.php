<?php
/**
 * Private element comments, separate from public WordPress comments and changes.
 *
 * @package Pencilino
 */
defined( 'ABSPATH' ) || exit;

final class Pencil_Comments {
	const DB_VERSION = '2';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_action( 'admin_post_pencil_resolve_comment', array( __CLASS__, 'admin_resolve' ) );
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'pencil_comments';
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( 'pencil_comments_db_version' ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = self::table_name();
		$collate = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			comment_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			page_url text NOT NULL,
			page_key char(64) NOT NULL,
			page_title varchar(191) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NOT NULL DEFAULT 0,
			target longtext NOT NULL,
			comment_text text NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_name varchar(191) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			resolved_at datetime DEFAULT NULL,
			resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			edited_at datetime DEFAULT NULL,
			PRIMARY KEY  (comment_id),
			KEY page_key (page_key),
			KEY created_at (created_at),
			KEY resolved_at (resolved_at)
		) {$collate};" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time schema verification.
		if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			update_option( 'pencil_comments_db_version', self::DB_VERSION, true );
		}
	}

	public static function can_comment() {
		return Pencil_Plugin::can_edit();
	}

	public static function register_routes() {
		register_rest_route( 'pencil/v1', '/comments', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'read_page' ), 'permission_callback' => array( __CLASS__, 'can_comment' ), 'args' => array( 'page_url' => array( 'required' => true, 'type' => 'string' ) ) ),
			array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'create' ), 'permission_callback' => array( __CLASS__, 'can_comment' ), 'args' => array(
				'page_url' => array( 'required' => true, 'type' => 'string' ),
				'comment' => array( 'required' => true, 'type' => 'string', 'maxLength' => 5000 ),
				'target' => array( 'required' => true, 'type' => 'object' ),
				'page_title' => array( 'type' => 'string', 'maxLength' => 191 ),
				'page_id' => array( 'type' => 'integer', 'minimum' => 0 ),
			) ),
		) );
		register_rest_route( 'pencil/v1', '/comments/(?P<id>\d+)', array(
			array( 'methods' => WP_REST_Server::READABLE, 'callback' => array( __CLASS__, 'read_one' ), 'permission_callback' => array( __CLASS__, 'can_comment' ) ),
			array(
			'methods' => WP_REST_Server::EDITABLE, 'callback' => array( __CLASS__, 'update_status' ),
			'permission_callback' => static function () { return Pencil_Client::can_manage(); },
			'args' => array( 'resolved' => array( 'required' => true, 'type' => 'boolean' ) ),
			),
		) );
		register_rest_route( 'pencil/v1', '/comments/(?P<id>\d+)/own', array(
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_own' ),
				'permission_callback' => array( __CLASS__, 'can_comment' ),
			),
		) );
		register_rest_route( 'pencil/v1', '/comments/(?P<id>\d+)/text', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'edit_text' ),
				'permission_callback' => array( __CLASS__, 'can_comment' ),
				'args'                => array( 'comment' => array( 'required' => true, 'type' => 'string', 'maxLength' => 5000 ) ),
			),
		) );
	}

	/** Delete a comment. Only its author may. */
	public static function delete_own( $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private comment lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE comment_id = %d', self::table_name(), $id ) );
		if ( ! $row ) {
			return new WP_Error( 'pencil_comment_missing', __( 'This comment could not be found.', 'pencilino' ), array( 'status' => 404 ) );
		}
		if ( (int) $row->user_id !== get_current_user_id() || 0 === get_current_user_id() ) {
			return new WP_Error( 'pencil_comment_forbidden', __( 'You can only delete your own comments.', 'pencilino' ), array( 'status' => 403 ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared delete of one private comment.
		$result = $wpdb->delete( self::table_name(), array( 'comment_id' => $id ), array( '%d' ) );
		if ( ! $result ) {
			return new WP_Error( 'pencil_comment_delete', __( 'The comment could not be deleted. Please try again.', 'pencilino' ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/** Change the text of a comment. Only its author may, and it is marked as edited. */
	public static function edit_text( $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private comment lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE comment_id = %d', self::table_name(), $id ) );
		if ( ! $row ) {
			return new WP_Error( 'pencil_comment_missing', __( 'This comment could not be found.', 'pencilino' ), array( 'status' => 404 ) );
		}
		if ( (int) $row->user_id !== get_current_user_id() || 0 === get_current_user_id() ) {
			return new WP_Error( 'pencil_comment_forbidden', __( 'You can only edit your own comments.', 'pencilino' ), array( 'status' => 403 ) );
		}
		$text = sanitize_textarea_field( $request['comment'] );
		if ( '' === trim( $text ) || strlen( $text ) > 20000 ) {
			return new WP_Error( 'pencil_comment_text', __( 'Write a comment of up to 5,000 characters.', 'pencilino' ), array( 'status' => 400 ) );
		}
		if ( $text === $row->comment_text ) {
			return rest_ensure_response( self::serialise( $row ) );
		}
		$edited = current_time( 'mysql', true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared update of one private comment.
		$result = $wpdb->update( self::table_name(), array( 'comment_text' => $text, 'edited_at' => $edited ), array( 'comment_id' => $id ), array( '%s', '%s' ), array( '%d' ) );
		if ( false === $result ) {
			return new WP_Error( 'pencil_comment_save', __( 'The comment could not be saved. Please try again.', 'pencilino' ), array( 'status' => 500 ) );
		}
		$row->comment_text = $text;
		$row->edited_at    = $edited;
		return rest_ensure_response( self::serialise( $row ) );
	}

	public static function read_one( $request ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private comment permalink lookup.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE comment_id = %d', self::table_name(), absint( $request['id'] ) ) );
		return $row ? rest_ensure_response( self::serialise( $row ) ) : new WP_Error( 'pencil_comment_missing', __( 'This comment could not be found.', 'pencilino' ), array( 'status' => 404 ) );
	}

	/** A canonical same-site page URL; never store editor tokens or arbitrary URLs. */
	public static function page_url( $url ) {
		$url = esc_url_raw( $url );
		$parts = wp_parse_url( $url );
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! $parts || empty( $parts['host'] ) || strtolower( $parts['host'] ) !== strtolower( $home['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) || ( $parts['port'] ?? null ) !== ( $home['port'] ?? null ) ) {
			return new WP_Error( 'pencil_comment_page', __( 'Choose a page on this website.', 'pencilino' ), array( 'status' => 400 ) );
		}
		$path = $parts['path'] ?? '/';
		$home_path = trailingslashit( $home['path'] ?? '/' );
		if ( 0 !== strpos( trailingslashit( $path ), $home_path ) || preg_match( '~/(?:wp-admin|wp-login\.php|wp-json)(?:/|$)~', $path ) ) {
			return new WP_Error( 'pencil_comment_page', __( 'Comments belong on the frontend website.', 'pencilino' ), array( 'status' => 400 ) );
		}
		$canonical = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $path;
		$query = array();
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}
		// Only structural WordPress query arguments; analytics and auth tokens are excluded.
		$query = array_intersect_key( $query, array_flip( array( 'p', 'page_id', 'post_type', 'name', 'pagename', 'paged', 'page', 's', 'cat', 'tag' ) ) );
		$query = array_filter( $query, 'is_scalar' );
		ksort( $query );
		return add_query_arg( $query, $canonical );
	}

	public static function read_page( $request ) {
		global $wpdb;
		$url = self::page_url( $request['page_url'] );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private page inbox from a custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE page_key = %s AND resolved_at IS NULL ORDER BY comment_id ASC', self::table_name(), hash( 'sha256', $url ) ) );
		return rest_ensure_response( array_map( array( __CLASS__, 'serialise' ), $rows ) );
	}

	public static function create( $request ) {
		global $wpdb;
		$url = self::page_url( $request['page_url'] );
		if ( is_wp_error( $url ) ) {
			return $url;
		}
		$text = sanitize_textarea_field( $request['comment'] );
		if ( '' === trim( $text ) || strlen( $text ) > 20000 ) {
			return new WP_Error( 'pencil_comment_text', __( 'Write a comment of up to 5,000 characters.', 'pencilino' ), array( 'status' => 400 ) );
		}
		$target = (array) $request['target'];
		$clean = array();
		foreach ( array( 'selector' => 2000, 'field' => 191, 'id' => 191, 'tag' => 30, 'label' => 191, 'text' => 240 ) as $key => $max ) {
			$value = isset( $target[ $key ] ) && is_scalar( $target[ $key ] ) ? sanitize_text_field( (string) $target[ $key ] ) : '';
			$clean[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $max ) : substr( $value, 0, $max );
		}
		if ( empty( $clean['selector'] ) && empty( $clean['field'] ) && empty( $clean['id'] ) ) {
			return new WP_Error( 'pencil_comment_target', __( 'Select an element to attach your comment.', 'pencilino' ), array( 'status' => 400 ) );
		}
		foreach ( array( 'x', 'y' ) as $key ) {
			$clean[ $key ] = isset( $target[ $key ] ) && is_numeric( $target[ $key ] ) ? min( 1, max( 0, (float) $target[ $key ] ) ) : 0.5;
		}
		$user = wp_get_current_user();
		$values = array( 'page_url' => $url, 'page_key' => hash( 'sha256', $url ), 'page_title' => sanitize_text_field( $request['page_title'] ?? '' ), 'page_id' => absint( $request['page_id'] ?? 0 ), 'target' => wp_json_encode( $clean ), 'comment_text' => $text, 'user_id' => $user->ID, 'user_name' => $user->display_name, 'created_at' => current_time( 'mysql', true ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- $wpdb->insert prepares all values.
		$result = $wpdb->insert( self::table_name(), $values, array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ) );
		if ( false === $result ) {
			return new WP_Error( 'pencil_comment_save', __( 'The comment could not be saved. Please try again.', 'pencilino' ), array( 'status' => 500 ) );
		}
		$values['comment_id'] = $wpdb->insert_id;
		$values['resolved_at'] = null;
		return new WP_REST_Response( self::serialise( (object) $values ), 201 );
	}

	public static function serialise( $row ) {
		return array( 'id' => (int) $row->comment_id, 'page_url' => $row->page_url, 'page_title' => $row->page_title, 'target' => json_decode( $row->target, true ), 'comment' => $row->comment_text, 'author' => $row->user_name, 'created_at' => $row->created_at, 'edited_at' => ! empty( $row->edited_at ) ? $row->edited_at : null, 'mine' => get_current_user_id() > 0 && (int) $row->user_id === get_current_user_id(), 'resolved' => ! empty( $row->resolved_at ) );
	}

	public static function set_resolved( $id, $resolved ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Private custom-table lookup.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT comment_id FROM %i WHERE comment_id = %d', self::table_name(), $id ) );
		if ( ! $exists ) {
			return new WP_Error( 'pencil_comment_missing', __( 'This comment could not be found.', 'pencilino' ), array( 'status' => 404 ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared update; inbox reads fresh rows on each request.
		$result = $wpdb->update( self::table_name(), array( 'resolved_at' => $resolved ? current_time( 'mysql', true ) : null, 'resolved_by' => $resolved ? get_current_user_id() : 0 ), array( 'comment_id' => absint( $id ) ), array( '%s', '%d' ), array( '%d' ) );
		return false === $result ? new WP_Error( 'pencil_comment_update', __( 'The comment status could not be saved.', 'pencilino' ), array( 'status' => 500 ) ) : true;
	}

	public static function update_status( $request ) {
		$result = self::set_resolved( absint( $request['id'] ), (bool) $request['resolved'] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'id' => absint( $request['id'] ), 'resolved' => (bool) $request['resolved'] ) );
	}

	public static function admin_resolve() {
		if ( ! Pencil_Client::can_manage() ) {
			wp_die( esc_html__( 'You cannot resolve comments.', 'pencilino' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		check_admin_referer( 'pencil_resolve_comment_' . $id );
		$result = self::set_resolved( $id, ! empty( $_POST['resolved'] ) );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => Pencil_Admin::MENU_SLUG, 'pencil-comment-status' => ! empty( $_POST['resolved'] ) ? 'resolved' : 'open' ), admin_url( 'admin.php' ) ) . '#comments' );
		exit;
	}

	public static function inbox( $resolved = false, $page = 1 ) {
		global $wpdb;
		$offset = ( max( 1, absint( $page ) ) - 1 ) * 50;
		if ( $resolved ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated private inbox reads current status.
			return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE resolved_at IS NOT NULL ORDER BY comment_id DESC LIMIT 51 OFFSET %d', self::table_name(), $offset ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Paginated private inbox reads current status.
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE resolved_at IS NULL ORDER BY comment_id DESC LIMIT 51 OFFSET %d', self::table_name(), $offset ) );
	}
}
