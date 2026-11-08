<?php
/**
 * Focus mode: a calmer WordPress for the users you choose.
 *
 * Nobody gets new rights here. Users keep their normal WordPress role (Editor,
 * Shop manager, ...). Focus mode only changes how WordPress looks and where they land.
 *
 * @package Pencilino
 */
defined( 'ABSPATH' ) || exit;

final class Pencil_Client {

	const USERS_OPTION   = 'pencil_focus_users';
	const TOOLBAR_OPTION = 'pencil_focus_toolbar';
	const WEBSITE_OPTION = 'pencil_focus_website';
	const MAX_RESULTS    = 8;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'migrate_autoload' ), 1 );
		add_action( 'init', array( __CLASS__, 'migrate_clients_role' ), 2 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
		add_filter( 'login_message', array( __CLASS__, 'logged_out_message' ) );
		add_filter( 'wp_login_errors', array( __CLASS__, 'logged_out_errors' ) );
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'login_assets' ) );
		add_filter( 'show_admin_bar', array( __CLASS__, 'show_admin_bar' ) );
		add_action( 'admin_init', array( __CLASS__, 'guard_admin' ), 20 );
		// Last, so the toolbar keeps only what these users need, whatever other plugins add.
		add_action( 'admin_bar_menu', array( __CLASS__, 'focus_toolbar' ), PHP_INT_MAX );
		add_action( 'admin_menu', array( __CLASS__, 'hide_pencilino_menu' ), 999 );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_pencil_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_ajax_pencil_focus_search', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_pencil_focus_users', array( __CLASS__, 'ajax_users' ) );
		add_action( 'delete_user', array( __CLASS__, 'forget_user' ) );
	}

	/** IDs of the users who get Focus mode. */
	public static function user_ids() {
		static $ids = null;
		if ( null === $ids ) {
			$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) get_option( self::USERS_OPTION, array() ) ) ) ) );
		}
		return $ids;
	}

	/** Whether anyone has Focus mode. */
	public static function enabled() {
		return (bool) self::user_ids();
	}

	/** Whether this user has Focus mode. Administrators never do, so nobody locks themselves out. */
	public static function is_client( $user = null ) {
		$user = $user instanceof WP_User ? $user : wp_get_current_user();
		return $user->exists() && in_array( (int) $user->ID, self::user_ids(), true ) && ! in_array( 'administrator', (array) $user->roles, true );
	}

	/** Who may change Pencilino itself: administrators, never users in Focus mode. */
	public static function can_manage() {
		return current_user_can( 'manage_options' ) && ! self::is_client();
	}

	public static function clean_admin() {
		return self::is_client();
	}

	/** The two switches. Both are on until an administrator turns them off. */
	public static function toolbar_on() {
		return '0' !== (string) get_option( self::TOOLBAR_OPTION, '1' );
	}

	public static function website_on() {
		return '0' !== (string) get_option( self::WEBSITE_OPTION, '1' );
	}

	/** Settings rows: option key => label and help. */
	public static function switch_fields() {
		return array(
			'toolbar' => array( __( 'Simple top bar', 'pencilino' ), __( 'Only Dashboard (or Back to website) and Log out. Off shows the normal WordPress top bar.', 'pencilino' ) ),
			'website' => array( __( 'Start on the website', 'pencilino' ), __( 'After logging in, these users land on the website instead of the dashboard.', 'pencilino' ) ),
		);
	}

	/**
	 * One-time move: small settings that are read on every request load together with
	 * the rest of WordPress's options, instead of one database lookup each.
	 *
	 * @return void
	 */
	/**
	 * Pencilino by Rino 2.0.x had its own Clients role. Pencilino no longer uses it, so the people
	 * who had it become Editors with Focus mode, which keeps their simple dashboard. Runs once:
	 * when the old role is gone, this costs nothing.
	 *
	 * @return void
	 */
	public static function migrate_clients_role() {
		if ( ! get_role( 'pencil_client' ) ) {
			return;
		}

		$moved = array();
		for ( $round = 0; $round < 50; $round++ ) {
			$ids = get_users( array( 'role' => 'pencil_client', 'fields' => 'ID', 'number' => 200 ) );
			if ( ! $ids ) {
				break;
			}
			foreach ( $ids as $id ) {
				$user = new WP_User( (int) $id );
				$user->remove_role( 'pencil_client' );
				if ( ! array_intersect( array( 'administrator', 'editor' ), (array) $user->roles ) ) {
					$user->add_role( 'editor' );
				}
				if ( ! in_array( 'administrator', (array) $user->roles, true ) ) {
					$moved[] = (int) $id;
				}
			}
		}

		if ( $moved ) {
			update_option( self::USERS_OPTION, array_values( array_unique( array_merge( self::user_ids(), $moved ) ) ), true );
			$roles = Pencil_Access::saved_roles();
			if ( is_array( $roles ) && ! in_array( 'editor', $roles, true ) ) {
				$roles[] = 'editor';
				update_option( Pencil_Access::OPTION, $roles, true );
			}
		}

		remove_role( 'pencil_client' );
		delete_option( 'pencil_client_mode' );
		delete_option( 'pencil_client_capabilities' );
	}

	public static function migrate_autoload() {
		if ( get_option( 'pencil_autoload_v1' ) ) {
			return;
		}
		if ( function_exists( 'wp_set_option_autoload_values' ) ) {
			wp_set_option_autoload_values(
				array(
					'pencil_activity_db_version' => true,
					'pencil_comments_db_version' => true,
				)
			);
		}
		update_option( 'pencil_autoload_v1', '1', true );
	}

	public static function login_redirect( $redirect, $requested, $user ) {
		return $user instanceof WP_User && self::is_client( $user ) && self::website_on() ? home_url( '/' ) : $redirect;
	}

	public static function show_admin_bar( $show ) {
		return self::clean_admin() ? true : $show;
	}

	public static function logged_out_url() {
		return add_query_arg( array( 'loggedout' => 'true', 'pencil-logged-out' => '1' ), wp_login_url() );
	}

	private static function is_logged_out_screen() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation flag.
		return self::enabled() && ! is_user_logged_in() && isset( $_GET['pencil-logged-out'], $_GET['loggedout'] ) && '1' === $_GET['pencil-logged-out'] && 'true' === $_GET['loggedout'];
	}

	public static function logged_out_message( $message ) {
		if ( ! self::is_logged_out_screen() ) {
			return $message;
		}
		return $message . '<div class="pencil-logged-out"><h2>' . esc_html__( 'You are logged out.', 'pencilino' ) . '</h2><p>' . esc_html__( 'You can now view the website without Pencilino.', 'pencilino' ) . '</p><a class="pencil-logged-out__button" href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Back to website', 'pencilino' ) . '</a></div>';
	}

	public static function logged_out_errors( $errors ) {
		if ( self::is_logged_out_screen() ) {
			$errors->remove( 'loggedout' );
		}
		return $errors;
	}

	public static function login_assets() {
		if ( self::is_logged_out_screen() ) {
			wp_enqueue_style( 'pencil-login', PENCIL_PLUGIN_URL . 'admin/css/login.css', array(), PENCIL_VERSION );
		}
	}

	/** Pencilino's own admin page is for administrators; send Focus mode users to the dashboard instead. */
	public static function guard_admin() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		if ( self::clean_admin() && isset( $_GET['page'] ) && Pencil_Admin::MENU_SLUG === $_GET['page'] ) {
			wp_safe_redirect( admin_url() );
			exit;
		}
	}

	/**
	 * Keep the WordPress toolbar, with only the way between website and dashboard on the left
	 * and Log out on the right. On small screens WordPress adds its own menu button.
	 */
	public static function focus_toolbar( $bar ) {
		if ( ! self::clean_admin() || ! self::toolbar_on() ) {
			return;
		}
		foreach ( $bar->get_nodes() as $node ) {
			if ( empty( $node->group ) && 'menu-toggle' !== $node->id ) {
				$bar->remove_node( $node->id );
			}
		}
		$in_admin = is_admin();
		$bar->add_node(
			array(
				'id'    => 'pencil-switch',
				'title' => esc_html( $in_admin ? __( 'Back to website', 'pencilino' ) : __( 'Dashboard', 'pencilino' ) ),
				'href'  => $in_admin ? home_url( '/' ) : admin_url(),
			)
		);
		$bar->add_node(
			array(
				'id'     => 'pencil-logout',
				'parent' => 'top-secondary',
				'title'  => esc_html__( 'Log out', 'pencilino' ),
				'href'   => wp_logout_url( self::logged_out_url() ),
			)
		);
	}

	public static function hide_pencilino_menu() {
		if ( self::clean_admin() ) {
			remove_menu_page( Pencil_Admin::MENU_SLUG );
		}
	}

	public static function body_class( $classes ) {
		if ( ! self::clean_admin() ) {
			return $classes;
		}
		return $classes . ' pencil-client-admin';
	}

	public static function enqueue_assets() {
		if ( ! self::clean_admin() ) {
			return;
		}
		// The file date as version, so caches and CDNs pick up every change.
		wp_enqueue_style( 'pencil-client', PENCIL_PLUGIN_URL . 'admin/css/client.css', array(), Pencil_Plugin::asset_version( 'admin/css/client.css' ) );
		$screen = get_current_screen();
		$deps = $screen && $screen->is_block_editor() ? array( 'wp-data', 'wp-dom-ready', 'wp-preferences' ) : array();
		wp_enqueue_script( 'pencil-client', PENCIL_PLUGIN_URL . 'admin/js/client.js', $deps, Pencil_Plugin::asset_version( 'admin/js/client.js' ), true );
		wp_localize_script( 'pencil-client', 'pencilClient', array( 'trash' => __( 'Move to trash', 'pencilino' ), 'keep' => __( 'Keep editing', 'pencilino' ), 'question' => __( 'Move this content to trash?', 'pencilino' ), 'description' => __( 'You can restore it from the trash later.', 'pencilino' ) ) );
	}

	/** One user as the picker shows it. */
	public static function user_row( $user ) {
		$names = wp_roles()->get_names();
		$role  = ! empty( $user->roles ) ? reset( $user->roles ) : '';
		return array(
			'id'    => (int) $user->ID,
			'name'  => $user->display_name ? $user->display_name : $user->user_login,
			'email' => $user->user_email,
			'role'  => isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : '',
			// Their role may be switched off under Features, Access. Then they cannot edit with Pencilino.
			'warn'  => Pencil_Access::allows( $user ) ? '' : __( 'This role has no access to Pencilino. Change it under Features, Access.', 'pencilino' ),
		);
	}

	/** The chosen users, for the settings screen. Only these are loaded, never the whole user list. */
	public static function chosen_users() {
		$ids = self::user_ids();
		if ( ! $ids ) {
			return array();
		}
		return array_map( array( __CLASS__, 'user_row' ), get_users( array( 'include' => $ids, 'orderby' => 'display_name', 'number' => count( $ids ) ) ) );
	}

	/** Every handler checks the nonce itself first. */
	private static function ajax_guard() {
		if ( ! self::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot change these settings.', 'pencilino' ) ), 403 );
		}
	}

	/** Search by name or email. Needs two letters, returns a handful of users and never administrators. */
	public static function ajax_search() {
		check_ajax_referer( 'pencil_focus', 'nonce' );
		self::ajax_guard();
		$term = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'users' => array(), 'more' => 0 ) );
		}
		$query = new WP_User_Query(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'role__not_in'   => array( 'administrator' ),
				'exclude'        => self::user_ids(), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- User query with a short list of IDs.
				'orderby'        => 'display_name',
				'number'         => self::MAX_RESULTS,
				'count_total'    => true,
			)
		);
		$users = array_map( array( __CLASS__, 'user_row' ), $query->get_results() );
		wp_send_json_success( array( 'users' => $users, 'more' => max( 0, (int) $query->get_total() - count( $users ) ) ) );
	}

	/** Add or remove one user. */
	public static function ajax_users() {
		check_ajax_referer( 'pencil_focus', 'nonce' );
		self::ajax_guard();
		$id = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$ids = array_map( 'absint', (array) get_option( self::USERS_OPTION, array() ) );
		if ( 'add' === $do ) {
			$user = get_userdata( $id );
			if ( ! $user || in_array( 'administrator', (array) $user->roles, true ) ) {
				wp_send_json_error( array( 'message' => __( 'This user cannot be added.', 'pencilino' ) ), 400 );
			}
			$ids[] = $id;
		} elseif ( 'remove' === $do ) {
			$ids = array_diff( $ids, array( $id ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Nothing to do.', 'pencilino' ) ), 400 );
		}
		update_option( self::USERS_OPTION, array_values( array_unique( array_map( 'absint', $ids ) ) ), true );
		wp_send_json_success( array( 'count' => count( $ids ) ) );
	}

	/** A deleted user drops out of the list. */
	public static function forget_user( $id ) {
		$ids = array_map( 'absint', (array) get_option( self::USERS_OPTION, array() ) );
		if ( in_array( (int) $id, $ids, true ) ) {
			update_option( self::USERS_OPTION, array_values( array_diff( $ids, array( (int) $id ) ) ), true );
		}
	}

	public static function save_settings() {
		if ( ! self::can_manage() ) {
			wp_die( esc_html__( 'You cannot change these settings.', 'pencilino' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'pencil_save_settings' );
		$group = isset( $_POST['pencil_group'] ) ? sanitize_key( wp_unslash( $_POST['pencil_group'] ) ) : '';
		if ( 'focus' === $group ) {
			update_option( self::TOOLBAR_OPTION, ! empty( $_POST['focus_toolbar'] ) ? '1' : '0', true );
			update_option( self::WEBSITE_OPTION, ! empty( $_POST['focus_website'] ) ? '1' : '0', true );
		}
		if ( 'access' === $group ) {
			Pencil_Access::save_from_post();
		}
		if ( 'view' === $group ) {
			if ( ! empty( $_POST['show_hidden'] ) ) {
				update_user_meta( get_current_user_id(), Pencil_Plugin::SHOW_HIDDEN_META, '1' );
			} else {
				delete_user_meta( get_current_user_id(), Pencil_Plugin::SHOW_HIDDEN_META );
			}
		}
		wp_safe_redirect( add_query_arg( array( 'page' => Pencil_Admin::MENU_SLUG, 'pencil-settings-saved' => '1' ), admin_url( 'admin.php' ) ) . ( 'view' === $group ? '#settings-hidden' : ( 'dev' === $group ? '#settings-dev' : ( 'access' === $group ? '#settings-access' : '#settings-focus' ) ) ) );
		exit;
	}
}