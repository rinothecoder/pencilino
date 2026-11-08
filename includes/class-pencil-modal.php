<?php
/**
 * Edit provider content in a popup on top of the page.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Modal {

	const FLAG   = 'pencil_modal';

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_frame_assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'wp_redirect', array( __CLASS__, 'keep_flag_on_redirect' ) );
	}

	/** Popup editing is the default. A developer can switch it off with the pencilino_popup_editing filter. */
	public static function enabled() {
		return (bool) apply_filters( 'pencilino_popup_editing', true );
	}

	/** Whether this admin screen is shown inside the popup on the website. */
	private static function is_frame_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation flag.
		if ( ! is_admin() || ! Pencil_Plugin::can_edit() ) {
			return false;
		}
		if ( isset( $_GET[ self::FLAG ] ) && '1' === $_GET[ self::FLAG ] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation flag.
			return true;
		}
		// Clicking around inside the popup drops the flag, so the browser's own frame hint keeps clients in popup style.
		return Pencil_Client::is_client() && Pencil_Plugin::in_iframe();
	}

	public static function body_class( $classes ) {
		return self::is_frame_request() ? $classes . ' pencil-modal-frame' : $classes;
	}

	public static function enqueue_frame_assets() {
		if ( ! self::is_frame_request() ) {
			return;
		}
		wp_enqueue_style( 'pencil-modal-frame', PENCIL_PLUGIN_URL . 'admin/css/modal-frame.css', array(), Pencil_Plugin::asset_version( 'admin/css/modal-frame.css' ) );
		$screen = get_current_screen();
		$deps   = $screen && $screen->is_block_editor() ? array( 'wp-data' ) : array();
		wp_enqueue_script( 'pencil-modal-frame', PENCIL_PLUGIN_URL . 'admin/js/modal-frame.js', $deps, Pencil_Plugin::asset_version( 'admin/js/modal-frame.js' ), true );
		// WordPress removes its result message from the address bar before footer scripts run, so report it from the request.
		$saved = false;
		foreach ( array( 'message', 'updated', 'settings-updated', 'saved', 'trashed', 'untrashed', 'deleted' ) as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result flag set by WordPress.
			$saved = $saved || isset( $_GET[ $key ] );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only presentation hint, sanitized by clean_field(). Only letters, numbers, colons, dashes and underscores get through.
		$field = isset( $_GET['pencil_field'] ) && is_string( $_GET['pencil_field'] ) ? self::clean_field( wp_unslash( $_GET['pencil_field'] ) ) : '';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only presentation hints: what the clicked region on the website showed.
		$hint  = isset( $_GET['pencil_hint'] ) && is_string( $_GET['pencil_hint'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['pencil_hint'] ) ), 0, 200 ) : '';
		$image = isset( $_GET['pencil_img'] ) && is_string( $_GET['pencil_img'] ) ? sanitize_file_name( wp_unslash( $_GET['pencil_img'] ) ) : '';
		// phpcs:enable
		$data  = array(
			'saved'     => $saved,
			'field'     => $field,
			'hint'      => $hint,
			'image'     => $image,
		);
		wp_localize_script( 'pencil-modal-frame', 'pencilModalFrame', $data );
	}

	/**
	 * WordPress redirects after saving, which would drop the popup flag and bring back the full admin.
	 *
	 * @param string $location Redirect target.
	 * @return string
	 */
	public static function keep_flag_on_redirect( $location ) {
		if ( ! is_admin() || ! is_string( $location ) ) {
			return $location;
		}
		$referer = wp_get_referer();
		if ( ! $referer || false === strpos( $referer, self::FLAG . '=1' ) ) {
			return $location;
		}
		$is_admin_url = 0 === strpos( $location, admin_url() );
		$is_relative  = ! preg_match( '#^(?:[a-z][a-z0-9+.-]*:)?//#i', $location ) && '/' !== substr( $location, 0, 1 );
		if ( ! $is_admin_url && ! $is_relative ) {
			return $location;
		}
		$location = add_query_arg( self::FLAG, '1', $location );
		// Saving reloads the screen, so keep pointing at the same field.
		$query = array();
		parse_str( (string) wp_parse_url( $referer, PHP_URL_QUERY ), $query );
		$field = isset( $query['pencil_field'] ) && is_string( $query['pencil_field'] ) ? $query['pencil_field'] : '';
		// The popup adds the field it found to the form, so a save keeps showing it.
		if ( '' === $field && isset( $_POST['pencil_field'] ) && is_string( $_POST['pencil_field'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presentation flag only.
			$field = wp_unslash( $_POST['pencil_field'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by clean_field() below.
		}
		$field = self::clean_field( $field );
		return '' !== $field ? add_query_arg( 'pencil_field', rawurlencode( $field ), $location ) : $location;
	}

	/**
	 * Keep only the characters a Pencilino field key can contain.
	 *
	 * @param string $field Raw field key.
	 * @return string
	 */
	private static function clean_field( $field ) {
		return mb_substr( preg_replace( '/[^A-Za-z0-9_:,\[\]-]/', '', (string) $field ), 0, 300 );
	}
}
