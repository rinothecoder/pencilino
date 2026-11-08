<?php
/**
 * Spacing adjustments: designers and developers nudge padding and margin on the page.
 *
 * Values are never typed. Each side takes one step of the site's own size scale, so an
 * adjustment prints as `padding-top: var(--size-40) !important`. The adjustments live in the
 * database and print as one small style block in the head. Nothing is written to theme files.
 * A clean-up prompt later folds them into the theme, after which they are cleared.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Spacing {
	const OPTION     = 'pencil_spacing';
	const MAX_ITEMS  = 300;
	const MAX_SELECT = 300;
	const MAX_STEP   = 400;
	const PREFIX_OPTION = 'pencil_spacing_prefix';

	/** The scale offered when the site does not define its own --size-N variables. */
	const DEFAULT_SCALE = array( 0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80, 96, 128, 160 );

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 99 );
		add_action( 'wp_ajax_pencil_spacing_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_pencil_spacing_reset', array( __CLASS__, 'ajax_reset' ) );
		add_action( 'wp_ajax_pencil_spacing_where', array( __CLASS__, 'ajax_where' ) );
	}

	/** The properties that can be adjusted. */
	public static function properties() {
		$list = array();
		foreach ( array( 'padding', 'margin' ) as $base ) {
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$list[] = $base . '-' . $side;
			}
		}
		$list[] = 'gap';
		return $list;
	}

	/** All stored adjustments: id => array( selector, props ). */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function count() {
		return count( self::all() );
	}

	/** Allow only plain structural selectors: tags, ids, classes, child combinators, nth-of-type. */
	public static function clean_selector( $value ) {
		$value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
		if ( '' === $value || strlen( $value ) > self::MAX_SELECT ) {
			return '';
		}
		if ( ! preg_match( '/^[A-Za-z0-9_#.:() >-]+$/', $value ) ) {
			return '';
		}
		// Pseudo selectors: only the structural ones we generate.
		if ( preg_match_all( '/:([a-z-]+)/', $value, $m ) ) {
			foreach ( $m[1] as $pseudo ) {
				if ( ! in_array( $pseudo, array( 'nth-of-type', 'first-child', 'last-child', 'first-of-type', 'last-of-type' ), true ) ) {
					return '';
				}
			}
		}
		return $value;
	}

	/** Property => step (whole number from the scale). Anything else is dropped. */
	public static function clean_props( $props ) {
		$out = array();
		if ( ! is_array( $props ) ) {
			return $out;
		}
		foreach ( self::properties() as $name ) {
			if ( ! isset( $props[ $name ] ) || '' === $props[ $name ] || null === $props[ $name ] ) {
				continue;
			}
			if ( ! preg_match( '/^[0-9]{1,3}$/', (string) $props[ $name ] ) ) {
				continue;
			}
			$step = (int) $props[ $name ];
			if ( $step <= self::MAX_STEP ) {
				$out[ $name ] = $step;
			}
		}
		return $out;
	}

	public static function id_for( $selector ) {
		return substr( md5( $selector ), 0, 10 );
	}

	/** Prefix of the site size variables: `pcl-` for --pcl-size-16, empty for --size-16. */
	public static function prefix() {
		$prefix = (string) get_option( self::PREFIX_OPTION, '' );
		return preg_match( '/^([a-z0-9]+-){0,3}$/', $prefix ) ? $prefix : '';
	}

	/** One small style block. Prints for every visitor, because it is part of the design. */
	public static function css() {
		$css = '';
		foreach ( self::all() as $item ) {
			if ( empty( $item['selector'] ) || empty( $item['props'] ) ) {
				continue;
			}
			$selector = self::clean_selector( $item['selector'] );
			$props    = self::clean_props( $item['props'] );
			if ( '' === $selector || ! $props ) {
				continue;
			}
			$rules = '';
			foreach ( $props as $name => $step ) {
				$rules .= $name . ':var(--size-' . $step . ',' . $step . 'px) !important;';
			}
			$css .= $selector . '{' . $rules . "}\n";
		}
		return $css;
	}

	public static function print_css() {
		$css = self::css();
		if ( '' === $css ) {
			return;
		}
		echo "<style id=\"pencil-dev-spacing-output\">\n/* Pencilino spacing adjustments. Fold into the theme, then clear. */\n" . $css . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors and numbers are whitelisted above.
	}

	/** What the panel needs on every page. */
	public static function localized() {
		$entries = array();
		foreach ( self::all() as $id => $item ) {
			$entries[] = array(
				'id'       => $id,
				'selector' => $item['selector'],
				'props'    => (object) $item['props'],
			);
		}
		return array(
			'nonce'   => wp_create_nonce( 'pencil_spacing' ),
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'scale'   => self::DEFAULT_SCALE,
			'prefix'  => self::prefix(),
			'entries' => $entries,
		);
	}

	/** Every handler checks the nonce itself first. */
	private static function guard() {
		if ( ! Pencil_Dev::allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Dev mode is not available for you.', 'pencilino' ) ), 403 );
		}
	}

	/** Save the adjustments of one element. Empty props remove it. */
	public static function ajax_save() {
		check_ajax_referer( 'pencil_spacing', 'nonce' );
		self::guard();
		$selector = isset( $_POST['selector'] ) ? self::clean_selector( wp_unslash( $_POST['selector'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by clean_selector().
		$props    = isset( $_POST['props'] ) ? json_decode( wp_unslash( $_POST['props'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every value is checked by clean_props().
		if ( '' === $selector ) {
			wp_send_json_error( array( 'message' => __( 'This element cannot be adjusted.', 'pencilino' ) ), 400 );
		}
		$props = self::clean_props( $props );
		if ( isset( $_POST['prefix'] ) ) {
			$new_prefix = sanitize_text_field( wp_unslash( $_POST['prefix'] ) );
			if ( preg_match( '/^([a-z0-9]+-){0,3}$/', $new_prefix ) ) {
				update_option( self::PREFIX_OPTION, $new_prefix, true );
			}
		}
		$all   = self::all();
		$id    = self::id_for( $selector );
		if ( $props ) {
			if ( ! isset( $all[ $id ] ) && count( $all ) >= self::MAX_ITEMS ) {
				wp_send_json_error( array( 'message' => __( 'Too many adjustments. Fold them into the theme first.', 'pencilino' ) ), 400 );
			}
			$all[ $id ] = array(
				'selector' => $selector,
				'props'    => $props,
			);
		} else {
			unset( $all[ $id ] );
		}
		update_option( self::OPTION, $all, true );
		wp_send_json_success( array( 'id' => $id, 'count' => count( $all ) ) );
	}

	/** Remove one adjustment, or all of them with id=all. */
	public static function ajax_reset() {
		check_ajax_referer( 'pencil_spacing', 'nonce' );
		self::guard();
		$id  = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';
		$all = self::all();
		if ( 'all' === $id ) {
			$all = array();
		} else {
			unset( $all[ $id ] );
		}
		update_option( self::OPTION, $all, true );
		wp_send_json_success( array( 'count' => count( $all ) ) );
	}

	/**
	 * Where else could this element live? Looks for its classes in the theme's PHP templates.
	 * Only reads files, no crawl. Returns file names and whether the element is in shared parts.
	 */
	public static function ajax_where() {
		check_ajax_referer( 'pencil_spacing', 'nonce' );
		self::guard();
		$classes = array();
		if ( isset( $_POST['classes'] ) ) {
			foreach ( explode( ' ', sanitize_text_field( wp_unslash( $_POST['classes'] ) ) ) as $c ) {
				if ( preg_match( '/^[A-Za-z0-9_-]{2,60}$/', $c ) ) {
					$classes[] = $c;
				}
			}
		}
		$classes = array_slice( $classes, 0, 6 );
		$files   = array();
		if ( $classes ) {
			$dirs = array_unique( array( get_stylesheet_directory(), get_template_directory() ) );
			foreach ( $dirs as $dir ) {
				$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
				$seen     = 0;
				foreach ( $iterator as $file ) {
					if ( ++$seen > 400 ) {
						break;
					}
					if ( 'php' !== strtolower( $file->getExtension() ) || $file->getSize() > 400000 ) {
						continue;
					}
					$code = file_get_contents( $file->getPathname() );
					foreach ( $classes as $c ) {
						if ( preg_match( '/class\s*=\s*["\'][^"\']*(?<![A-Za-z0-9_-])' . preg_quote( $c, '/' ) . '(?![A-Za-z0-9_-])/', $code ) ) {
							$files[] = ltrim( str_replace( $dir, '', $file->getPathname() ), '/\\' );
							break;
						}
					}
				}
			}
		}
		wp_send_json_success( array( 'files' => array_values( array_unique( $files ) ) ) );
	}
}