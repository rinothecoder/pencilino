<?php
/**
 * Fallback styles for the block editor.
 *
 * The editor does not load a theme's front-end CSS. A theme has to ask for editor styles with
 * add_editor_style(). When it does not, WordPress uses the browser's default serif font, which
 * makes every post look unfinished. In that case Pencilino adds a small, clean set of styles.
 * A theme with its own editor styles is never touched.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Editor_Styles {
	/**
	 * Register the hook.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'block_editor_settings_all', array( __CLASS__, 'add_fallback' ), 20 );
	}

	/**
	 * Add the fallback styles when the theme has no editor styles.
	 *
	 * Return false from the pencilino_editor_fallback_styles filter to turn this off.
	 *
	 * @param array $settings Block editor settings.
	 * @return array
	 */
	public static function add_fallback( $settings ) {
		if ( ! apply_filters( 'pencilino_editor_fallback_styles', true ) ) {
			return $settings;
		}

		$styles = isset( $settings['styles'] ) && is_array( $settings['styles'] ) ? $settings['styles'] : array();
		foreach ( $styles as $style ) {
			if ( isset( $style['__unstableType'] ) && 'theme' === $style['__unstableType'] ) {
				return $settings;
			}
		}

		$css = file_get_contents( PENCIL_PLUGIN_PATH . 'admin/css/editor-fallback.css' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $css || '' === $css ) {
			return $settings;
		}

		$styles[]           = array(
			'css'            => $css,
			'__unstableType' => 'plugin',
		);
		$settings['styles'] = $styles;

		return $settings;
	}
}
