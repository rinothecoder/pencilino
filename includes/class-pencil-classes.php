<?php
/**
 * Class changes: swap one framework class for another on one element.
 *
 * Nothing is written to theme files. A swap is stored as the declarations of the target class,
 * copied onto the element through a selector that finds it again, and printed as one small style
 * block in the head. The clean-up prompt carries the exact class change into the theme, after
 * which the change is cleared.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Classes {
	const OPTION    = 'pencil_classes';
	const MAX_ITEMS = 300;
	const MAX_SWAPS = 8;
	const MAX_RULES = 12;

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'print_css' ), 99 );
		add_action( 'wp_ajax_pencil_classes_save', array( __CLASS__, 'ajax_save' ) );
		add_action( 'wp_ajax_pencil_classes_reset', array( __CLASS__, 'ajax_reset' ) );
	}

	/** What a class swap can carry: type, colour, background, corners and shadow. */
	public static function properties() {
		return array( 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'color', 'background-color', 'background', 'border-radius', 'box-shadow', 'border-color' );
	}

	/** All stored swaps: id => array( selector, swaps ). */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	public static function clean_class( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^-?[A-Za-z_][A-Za-z0-9_-]{0,79}$/', $value ) ? $value : '';
	}

	/** A media query as the CSS holds it, for example (min-width: 768px). Null means refused. */
	public static function clean_media( $value ) {
		$value = trim( preg_replace( '/[[:space:]]+/', ' ', (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		if ( strlen( $value ) > 160 || ! preg_match( '#^[A-Za-z0-9 ():,.%/-]+$#', $value ) ) {
			return null;
		}
		return $value;
	}

	/** One CSS value. Plain printable text only, no braces, semicolons, tags, urls or comments. */
	public static function clean_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value || strlen( $value ) > 240 || ! preg_match( '~^[ -\x7e]+$~', $value ) ) {
			return '';
		}
		if ( false !== strpbrk( $value, ';{}<>@!*' . chr( 92 ) ) || preg_match( '/url[[:space:]]*\(/i', $value ) || false !== stripos( $value, 'expression' ) || false !== stripos( $value, 'javascript' ) ) {
			return '';
		}
		return $value;
	}

	/** Rules: a list of media query plus declarations, from the target class. */
	public static function clean_rules( $rules ) {
		$out = array();
		if ( ! is_array( $rules ) ) {
			return $out;
		}
		foreach ( array_slice( $rules, 0, self::MAX_RULES ) as $rule ) {
			if ( ! is_array( $rule ) || empty( $rule['decls'] ) || ! is_array( $rule['decls'] ) ) {
				continue;
			}
			$media = self::clean_media( isset( $rule['media'] ) ? $rule['media'] : '' );
			if ( null === $media ) {
				continue;
			}
			$decls = array();
			foreach ( self::properties() as $name ) {
				if ( isset( $rule['decls'][ $name ] ) ) {
					$value = self::clean_value( $rule['decls'][ $name ] );
					if ( '' !== $value ) {
						$decls[ $name ] = $value;
					}
				}
			}
			if ( $decls ) {
				$out[] = array(
					'media' => $media,
					'decls' => $decls,
				);
			}
		}
		return $out;
	}

	/** The swaps of one element. One swap per original class. */
	public static function clean_swaps( $swaps ) {
		$out = array();
		if ( ! is_array( $swaps ) ) {
			return $out;
		}
		foreach ( array_slice( $swaps, 0, self::MAX_SWAPS ) as $swap ) {
			if ( ! is_array( $swap ) ) {
				continue;
			}
			$from  = self::clean_class( isset( $swap['from'] ) ? $swap['from'] : '' );
			$to    = self::clean_class( isset( $swap['to'] ) ? $swap['to'] : '' );
			$rules = self::clean_rules( isset( $swap['rules'] ) ? $swap['rules'] : array() );
			if ( '' === $from || '' === $to || $from === $to || ! $rules ) {
				continue;
			}
			$out[ $from ] = array(
				'from'  => $from,
				'to'    => $to,
				'rules' => $rules,
			);
		}
		return array_values( $out );
	}

	/** One small style block. Prints for every visitor, because it is part of the design. */
	public static function css() {
		$css = '';
		foreach ( self::all() as $item ) {
			$selector = empty( $item['selector'] ) ? '' : Pencil_Spacing::clean_selector( $item['selector'] );
			$swaps    = empty( $item['swaps'] ) ? array() : self::clean_swaps( $item['swaps'] );
			if ( '' === $selector || ! $swaps ) {
				continue;
			}
			foreach ( $swaps as $swap ) {
				foreach ( $swap['rules'] as $rule ) {
					$decls = '';
					foreach ( $rule['decls'] as $name => $value ) {
						$decls .= $name . ':' . $value . ' !important;';
					}
					$block = $selector . '{' . $decls . '}';
					$css  .= ( '' === $rule['media'] ? $block : '@media ' . $rule['media'] . '{' . $block . '}' ) . PHP_EOL;
				}
			}
		}
		return $css;
	}

	public static function print_css() {
		$css = self::css();
		if ( '' === $css ) {
			return;
		}
		echo '<style id="pencil-dev-classes-output">' . PHP_EOL . '/* Pencilino class changes. Make them in the theme, then clear. */' . PHP_EOL . $css . '</style>' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors, class names and values are whitelisted above.
	}

	/** What the panel needs on every page. */
	public static function localized() {
		$entries = array();
		foreach ( self::all() as $id => $item ) {
			if ( empty( $item['selector'] ) || empty( $item['swaps'] ) ) {
				continue;
			}
			$entries[] = array(
				'id'       => $id,
				'selector' => $item['selector'],
				'swaps'    => self::clean_swaps( $item['swaps'] ),
			);
		}
		return array(
			'nonce'   => wp_create_nonce( 'pencil_classes' ),
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'entries' => $entries,
		);
	}

	/** Every handler checks the nonce itself first. */
	private static function guard() {
		if ( ! Pencil_Dev::allowed() ) {
			wp_send_json_error( array( 'message' => __( 'Dev mode is not available for you.', 'pencilino' ) ), 403 );
		}
	}

	/** Save the swaps of one element. No swaps removes it. */
	public static function ajax_save() {
		check_ajax_referer( 'pencil_classes', 'nonce' );
		self::guard();
		$selector = isset( $_POST['selector'] ) ? Pencil_Spacing::clean_selector( wp_unslash( $_POST['selector'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by clean_selector().
		$swaps    = isset( $_POST['swaps'] ) ? json_decode( wp_unslash( $_POST['swaps'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON, every value is checked by clean_swaps().
		if ( '' === $selector ) {
			wp_send_json_error( array( 'message' => __( 'This element cannot be changed.', 'pencilino' ) ), 400 );
		}
		$sent  = is_array( $swaps ) ? count( $swaps ) : 0;
		$swaps = self::clean_swaps( $swaps );
		$all   = self::all();
		$id    = Pencil_Spacing::id_for( $selector );
		if ( $swaps ) {
			if ( ! isset( $all[ $id ] ) && count( $all ) >= self::MAX_ITEMS ) {
				wp_send_json_error( array( 'message' => __( 'Too many class changes. Make them in the theme first.', 'pencilino' ) ), 400 );
			}
			$all[ $id ] = array(
				'selector' => $selector,
				'swaps'    => $swaps,
			);
		} else {
			unset( $all[ $id ] );
		}
		update_option( self::OPTION, $all, true );
		wp_send_json_success( array( 'id' => $id, 'kept' => count( $swaps ), 'sent' => $sent, 'count' => count( $all ) ) );
	}

	/** Remove one element, or all of them with id=all. */
	public static function ajax_reset() {
		check_ajax_referer( 'pencil_classes', 'nonce' );
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
}