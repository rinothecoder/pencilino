<?php
/**
 * Dev mode: an inspector on the website for the users you choose.
 *
 * Hover an element to see its classes, click it to see how it is styled and whether
 * the CSS follows your framework. Only the chosen users get the Dev button, so an
 * administrator who is not chosen sees nothing.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Dev {
	const USERS_OPTION  = 'pencil_dev_users';
	const PREFIX_OPTION = 'pencil_dev_prefix';
	const MAX_RESULTS   = 8;

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_pencil_dev_search', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_pencil_dev_users', array( __CLASS__, 'ajax_users' ) );
		add_action( 'delete_user', array( __CLASS__, 'forget_user' ) );
	}

	/** IDs of the users who get Dev mode. */
	public static function user_ids() {
		return array_values( array_unique( array_filter( array_map( 'absint', (array) get_option( self::USERS_OPTION, array() ) ) ) ) );
	}


	/** The start of your framework class names. The standard is pcl-, unless a different one was saved. */
	public static function prefix() {
		$saved = self::clean_prefix( get_option( self::PREFIX_OPTION, '' ) );
		return '' !== $saved ? $saved : 'pcl-';
	}

	public static function clean_prefix( $value ) {
		return substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value ), 0, 32 );
	}

	public static function save_prefix( $value ) {
		update_option( self::PREFIX_OPTION, self::clean_prefix( $value ), true );
	}

	/** Whether this user gets the Dev button. Needs the website editor too, and never applies to Focus mode users. */
	/** The rules every clean-up prompt shares. The short prompt in the popup and the master prompt in Settings are built from these. */
	public static function prompt_rules() {
		return array(
			'intro'  => 'Clean up this whole WordPress site, all pages and all templates, so it follows the Pencilino instructions again.',
			'read'   => 'First read all Pencilino instructions with the pencilino/get-instructions ability. Read every part in full and do not skip anything: the default instructions and the additions of the site owner come as one document, and the details matter. Then go through the site part by part, from the design system to SEO, mobile, speed and safety, and fix everything that does not follow them.',
			'swap'   => 'Class changes made in Dev mode are temporary overrides. Make each change in the templates by replacing the class on that element, and check that the new class gives the same result without the override.',
			'safe'   => 'The site must look the same afterwards. Tiny differences from merging near-identical values are fine. Never remove or overwrite content or saved Pencilino values, and do not change page URLs. If a fix would change content, a URL or how the site looks, do not make it: list it as a suggestion.',
			'report' => 'When you are done, give a short summary in non-technical, plain words: what you improved, and what still needs attention, such as missing images, a favicon, texts to fill in and the suggestions you did not carry out.',
		);
	}

	/**
	 * The clean-up prompt. It points the agent to the Pencilino instructions and only adds what a clean-up needs.
	 *
	 * @return string
	 */
	public static function master_prompt() {
		$rules = self::prompt_rules();
		$lines = array_merge(
			array( $rules['intro'], '', $rules['read'] ),
			array( '', $rules['safe'], '', $rules['report'] )
		);
		$changes = array();
		if ( class_exists( 'Pencil_Classes' ) ) {
			foreach ( Pencil_Classes::all() as $item ) {
				if ( empty( $item['selector'] ) || empty( $item['swaps'] ) ) {
					continue;
				}
				foreach ( Pencil_Classes::clean_swaps( $item['swaps'] ) as $swap ) {
					$changes[] = 'On ' . $item['selector'] . ', replace the class ' . $swap['from'] . ' with ' . $swap['to'] . '.';
				}
			}
		}
		if ( $changes ) {
			$at = array_search( $rules['safe'], $lines, true );
			$add = array_merge( array( $rules['swap'] ), array_map( static function ( $line ) {
				return '- ' . $line;
			}, array_slice( $changes, 0, 40 ) ), array( '' ) );
			array_splice( $lines, false === $at ? count( $lines ) : $at, 0, $add );
		}
		return implode( "\n", $lines );
	}

	public static function allowed() {
		if ( ! is_user_logged_in() || ! Pencil_Plugin::can_edit() || Pencil_Client::is_client() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}
		return in_array( get_current_user_id(), self::user_ids(), true );
	}

	public static function enqueue_assets() {
		if ( Pencil_Plugin::in_iframe() || ! self::allowed() ) {
			return;
		}


		wp_enqueue_style( 'pencil-dev', PENCIL_PLUGIN_URL . 'assets/css/dev.css', array( 'pencil-editor' ), Pencil_Plugin::asset_version( 'assets/css/dev.css' ) );
		wp_enqueue_script( 'pencil-dev', PENCIL_PLUGIN_URL . 'assets/js/dev.js', array( 'pencil-editor' ), Pencil_Plugin::asset_version( 'assets/js/dev.js' ), true );
		wp_enqueue_style( 'pencil-dev-spacing', PENCIL_PLUGIN_URL . 'assets/css/dev-spacing.css', array( 'pencil-dev' ), Pencil_Plugin::asset_version( 'assets/css/dev-spacing.css' ) );
		wp_enqueue_script( 'pencil-dev-spacing', PENCIL_PLUGIN_URL . 'assets/js/dev-spacing.js', array( 'pencil-dev' ), Pencil_Plugin::asset_version( 'assets/js/dev-spacing.js' ), true );
		wp_enqueue_script( 'pencil-dev-classes', PENCIL_PLUGIN_URL . 'assets/js/dev-classes.js', array( 'pencil-dev', 'pencil-dev-spacing' ), Pencil_Plugin::asset_version( 'assets/js/dev-classes.js' ), true );
		wp_localize_script(
			'pencil-dev',
			'pencilDev',
			array(
				'prefix' => self::prefix(),
				'cleanupUrl' => admin_url( 'admin.php?page=pencilino#ai-cleanup' ),
				'prompt' => self::prompt_rules(),
				'spacing' => Pencil_Spacing::localized(),
				'classes' => Pencil_Classes::localized(),
				'labels' => array(
					'dev'          => __( 'Dev', 'pencilino' ),
					'copied'       => __( 'Copied', 'pencilino' ),
					'copySelector' => __( 'Copy selector', 'pencilino' ),
					'close'        => __( 'Close', 'pencilino' ),
					'classes'      => __( 'Classes', 'pencilino' ),
					'panelTitle'   => __( 'Dev mode', 'pencilino' ),
					'copy'         => __( 'Copy', 'pencilino' ),
					'noClasses'    => __( 'No classes on this element.', 'pencilino' ),
					'field'        => __( 'Pencilino field', 'pencilino' ),
					'styling'      => __( 'Styling', 'pencilino' ),
					'font'         => __( 'Font', 'pencilino' ),
					'size'         => __( 'Size', 'pencilino' ),
					'color'        => __( 'Colour', 'pencilino' ),
					'hard'         => __( 'Hard-coded in the CSS', 'pencilino' ),
					'browser'      => __( 'Browser default', 'pencilino' ),
					'from'         => __( 'from', 'pencilino' ),
					'framework'    => __( 'Consistency check', 'pencilino' ),
					'done'         => __( 'Done', 'pencilino' ),
					'hideResults'  => __( 'Hide results', 'pencilino' ),
					'frameworkClass' => __( 'Framework class', 'pencilino' ),
					'variables'    => __( 'Uses variables', 'pencilino' ),
					'none'         => __( 'None', 'pencilino' ),
					'check'        => __( 'Check the whole page', 'pencilino' ),
					'checking'     => __( 'Checking...', 'pencilino' ),
					'hardFonts'    => __( 'Fonts', 'pencilino' ),
					'hardSizes'    => __( 'Sizes', 'pencilino' ),
					'hardColors'   => __( 'Colours', 'pencilino' ),
					'showMe'       => __( 'Elements to look at', 'pencilino' ),
					'blocked'      => __( 'Some stylesheets come from another domain and cannot be read.', 'pencilino' ),
					'textElements' => __( 'text elements use only variables (or the browser default).', 'pencilino' ),
					'limited'      => __( 'Only the first 1500 text elements were checked.', 'pencilino' ),
				),
			)
		);
	}

	/** Every handler checks the nonce itself first. */
	private static function ajax_guard() {
		if ( ! Pencil_Client::can_manage() ) {
			wp_send_json_error( array( 'message' => __( 'You cannot change these settings.', 'pencilino' ) ), 403 );
		}
	}

	/** One user as the picker shows it. */
	public static function chosen_users() {
		$ids = self::user_ids();
		if ( ! $ids ) {
			return array();
		}
		return array_map( array( 'Pencil_Client', 'user_row' ), get_users( array( 'include' => $ids, 'orderby' => 'display_name', 'number' => count( $ids ) ) ) );
	}

	/** Search by name or email. Two letters or more, a handful of results. Only administrators. */
	public static function ajax_search() {
		check_ajax_referer( 'pencil_dev', 'nonce' );
		self::ajax_guard();
		$term = isset( $_POST['q'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['q'] ) ) ) : '';
		if ( strlen( $term ) < 2 ) {
			wp_send_json_success( array( 'users' => array(), 'more' => 0 ) );
		}
		$query = new WP_User_Query(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'user_email', 'display_name', 'user_nicename' ),
				'capability__in' => array( 'manage_options' ),
				'exclude'        => array_merge( self::user_ids(), Pencil_Client::user_ids() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- User query with a short list of IDs.
				'orderby'        => 'display_name',
				'number'         => self::MAX_RESULTS,
				'count_total'    => true,
			)
		);
		$users = array_map( array( 'Pencil_Client', 'user_row' ), $query->get_results() );
		wp_send_json_success( array( 'users' => $users, 'more' => max( 0, (int) $query->get_total() - count( $users ) ) ) );
	}

	/** Add or remove one user. */
	public static function ajax_users() {
		check_ajax_referer( 'pencil_dev', 'nonce' );
		self::ajax_guard();
		$id  = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		$do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$ids = self::user_ids();
		if ( 'add' === $do ) {
			$user = get_userdata( $id );
			if ( ! $user || ! user_can( $user, 'manage_options' ) || in_array( $id, Pencil_Client::user_ids(), true ) ) {
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
		$ids = self::user_ids();
		if ( in_array( (int) $id, $ids, true ) ) {
			update_option( self::USERS_OPTION, array_values( array_diff( $ids, array( (int) $id ) ) ), true );
		}
	}
}