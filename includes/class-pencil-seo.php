<?php
/**
 * Pencilino and SEO plugins.
 *
 * Pencilino has no SEO fields of its own: the SEO plugin of the site owns titles, descriptions and tags.
 * Pencilino pages keep their content in the theme, so `post_content` is empty and the content analysis of
 * Rank Math and Yoast SEO sees an empty page. This class gives those plugins the text of the rendered page
 * in the editor, also when the SEO plugin is installed later. It only runs in the editor, so visitors and
 * the front end never depend on it.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_SEO {
	const META_TITLE       = '_pencil_seo_title';
	const META_DESCRIPTION = '_pencil_seo_description';
	const CACHE_PREFIX     = 'pencil_seo_text_';
	const CACHE_VERSION    = 'pencil_seo_text_version';
	const CACHE_TIME       = 600;

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 99 );
		add_action( 'pencilino_saved', array( __CLASS__, 'flush' ) );
		add_action( 'save_post', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Name of the active SEO plugin, or an empty string when there is none.
	 *
	 * @return string
	 */
	public static function detected_plugin() {
		$constants = array(
			'Yoast SEO'         => 'WPSEO_VERSION',
			'Rank Math'         => 'RANK_MATH_VERSION',
			'All in One SEO'    => 'AIOSEO_VERSION',
			'SEOPress'          => 'SEOPRESS_VERSION',
			'The SEO Framework' => 'THE_SEO_FRAMEWORK_VERSION',
			'Slim SEO'          => 'SLIM_SEO_VER',
			'SmartCrawl'        => 'SMARTCRAWL_VERSION',
			'Squirrly SEO'      => 'SQ_VERSION',
			'SureRank'          => 'SURERANK_VERSION',
		);

		$found = '';

		foreach ( $constants as $name => $constant ) {
			if ( defined( $constant ) ) {
				$found = $name;
				break;
			}
		}

		if ( '' === $found ) {
			$folders = '#^(wordpress-seo|wordpress-seo-premium|seo-by-rank-math|seo-by-rank-math-pro|all-in-one-seo-pack|all-in-one-seo-pack-pro|wp-seopress|wp-seopress-pro|autodescription|slim-seo|wpmu-dev-seo|squirrly-seo|surerank)/#';

			foreach ( (array) get_option( 'active_plugins', array() ) as $basename ) {
				if ( preg_match( $folders, (string) $basename ) ) {
					$found = (string) preg_replace( '#/.*$#', '', $basename );
					break;
				}
			}
		}

		/**
		 * Let a site say that another plugin handles SEO. Return its name, or an empty string for none.
		 *
		 * @param string $found Detected plugin name.
		 */
		return (string) apply_filters( 'pencil_seo_plugin_detected', apply_filters( 'pencilino_seo_plugin_detected', $found ) );
	}

	/**
	 * Load the bridge on the edit screen of a published page or post whose content lives in the theme.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'post.php' !== $hook ) {
			return;
		}

		$rank_math = defined( 'RANK_MATH_VERSION' );
		$yoast     = defined( 'WPSEO_VERSION' );

		if ( ! $rank_math && ! $yoast ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! is_post_type_viewable( $post->post_type ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) || '' !== trim( wp_strip_all_tags( (string) $post->post_content ) ) ) {
			return;
		}

		/**
		 * Turn the bridge off for a page, or for the whole site by returning false.
		 *
		 * @param bool    $enabled Whether Pencilino gives the page text to the SEO plugin.
		 * @param WP_Post $post    The page being edited.
		 */
		if ( ! apply_filters( 'pencilino_seo_bridge', true, $post ) ) {
			return;
		}

		$html = self::page_html( $post );

		if ( '' === $html ) {
			return;
		}

		$data = 'window.pencilSeoText = ' . wp_json_encode( $html ) . ';';

		if ( $rank_math ) {
			wp_register_script( 'pencilino-seo-rank-math', false, array( 'wp-hooks', 'rank-math-analyzer' ), PENCIL_VERSION, true );
			wp_enqueue_script( 'pencilino-seo-rank-math' );
			wp_add_inline_script( 'pencilino-seo-rank-math', $data . self::rank_math_script() );
		}

		if ( $yoast ) {
			wp_register_script( 'pencilino-seo-yoast', false, array( 'jquery' ), PENCIL_VERSION, true );
			wp_enqueue_script( 'pencilino-seo-yoast' );
			wp_add_inline_script( 'pencilino-seo-yoast', $data . self::yoast_script() );
		}
	}

	/**
	 * Add the page text to the Rank Math content analysis.
	 *
	 * @return string
	 */
	private static function rank_math_script() {
		return '( function () {
			if ( ! window.pencilSeoText || ! window.wp || ! wp.hooks ) { return; }
			wp.hooks.addFilter( "rank_math_content", "pencilino/page-text", function ( content ) {
				return ( content || "" ) + window.pencilSeoText;
			} );
			var tries = 0;
			( function refresh() {
				if ( window.rankMathEditor && "function" === typeof window.rankMathEditor.refresh ) {
					window.rankMathEditor.refresh( "content" );
				} else if ( tries++ < 40 ) {
					setTimeout( refresh, 250 );
				}
			}() );
		}() );';
	}

	/**
	 * Add the page text to the Yoast SEO content analysis.
	 *
	 * @return string
	 */
	private static function yoast_script() {
		return '( function ( $ ) {
			if ( ! window.pencilSeoText ) { return; }
			var done = false;
			function register() {
				if ( done || ! window.YoastSEO || ! YoastSEO.app || ! YoastSEO.app.registerPlugin ) { return; }
				done = true;
				YoastSEO.app.registerPlugin( "pencilino", { status: "ready" } );
				YoastSEO.app.registerModification( "content", function ( content ) {
					return ( content || "" ) + window.pencilSeoText;
				}, "pencilino", 10 );
			}
			$( window ).on( "YoastSEO:ready", register );
			register();
		}( jQuery ) );';
	}

	/**
	 * The content of the rendered page, as a visitor sees it: the main element without scripts, menus and forms.
	 * Cached for ten minutes, and refreshed when the page or a Pencilino field is saved.
	 *
	 * @param WP_Post $post The page.
	 * @return string Empty when the page could not be read.
	 */
	private static function page_html( WP_Post $post ) {
		$key    = self::CACHE_PREFIX . $post->ID . '_' . (int) get_option( self::CACHE_VERSION, 0 );
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return (string) $cached;
		}

		$response = wp_remote_get(
			get_permalink( $post ),
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);

		$html = '';

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$html = self::extract( (string) wp_remote_retrieve_body( $response ) );
		}

		// Also remember a failed read for a short while, so a blocked loopback does not slow down every page load.
		set_transient( $key, $html, '' === $html ? 60 : self::CACHE_TIME );

		return $html;
	}

	/**
	 * Take the main element of a page and drop what is not content.
	 *
	 * @param string $page Full HTML of the page.
	 * @return string
	 */
	private static function extract( $page ) {
		if ( '' === trim( $page ) ) {
			return '';
		}

		if ( ! class_exists( 'DOMDocument' ) ) {
			if ( ! preg_match( '#<main\b[^>]*>(.*)</main>#is', $page, $match ) ) {
				return '';
			}
			$html = (string) preg_replace( '#<(script|style|svg|nav|footer|form|noscript|template|iframe)\b.*?</\1>#is', '', $match[1] );
			return trim( $html );
		}

		$doc      = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8" ?>' . $page, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$root = $doc->getElementsByTagName( 'main' )->item( 0 );
		$drop = array( 'script', 'style', 'svg', 'nav', 'footer', 'form', 'noscript', 'template', 'iframe' );

		if ( ! $root ) {
			$root   = $doc->getElementsByTagName( 'body' )->item( 0 );
			$drop[] = 'header';
		}

		if ( ! $root ) {
			return '';
		}

		foreach ( $drop as $tag ) {
			foreach ( iterator_to_array( $root->getElementsByTagName( $tag ) ) as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		// Hidden states (pencil-reveal) only show after an action, so they are not part of the page text.
		$xpath = new DOMXPath( $doc );
		foreach ( iterator_to_array( $xpath->query( './/*[@hidden]', $root ) ) as $node ) {
			if ( $node->parentNode ) {
				$node->parentNode->removeChild( $node );
			}
		}

		$html = '';
		foreach ( $root->childNodes as $child ) {
			$html .= $doc->saveHTML( $child );
		}

		return trim( (string) preg_replace( '/\s+/', ' ', $html ) );
	}

	/**
	 * Read the page again on the next visit to the editor.
	 *
	 * @return void
	 */
	public static function flush() {
		update_option( self::CACHE_VERSION, (int) get_option( self::CACHE_VERSION, 0 ) + 1, false );
	}

	/**
	 * SEO title saved by older Pencilino versions, which had their own SEO box. Kept so older themes keep working.
	 *
	 * @param int $post_id Post ID. The current page when 0.
	 * @return string
	 */
	public static function get_title( $post_id = 0 ) {
		$post_id = $post_id ? absint( $post_id ) : self::current_id();

		return $post_id ? trim( (string) get_post_meta( $post_id, self::META_TITLE, true ) ) : '';
	}

	/**
	 * Meta description saved by older Pencilino versions. Kept so older themes keep working.
	 *
	 * @param int $post_id Post ID. The current page when 0.
	 * @return string
	 */
	public static function get_description( $post_id = 0 ) {
		$post_id = $post_id ? absint( $post_id ) : self::current_id();

		return $post_id ? trim( (string) get_post_meta( $post_id, self::META_DESCRIPTION, true ) ) : '';
	}

	/**
	 * The page or post that the current request is about, or 0 for archives, search and so on.
	 *
	 * @return int
	 */
	private static function current_id() {
		if ( is_admin() ) {
			return 0;
		}

		if ( is_singular() ) {
			return (int) get_queried_object_id();
		}

		if ( is_home() && ! is_front_page() ) {
			return (int) get_option( 'page_for_posts' );
		}

		return 0;
	}
}

if ( ! function_exists( 'pencilino_seo_plugin' ) ) {
	/**
	 * Name of the active SEO plugin, or an empty string when there is none.
	 *
	 * @return string
	 */
	function pencilino_seo_plugin() {
		return Pencil_SEO::detected_plugin();
	}
}

if ( ! function_exists( 'pencilino_seo_title' ) ) {
	/**
	 * The SEO title saved by older Pencilino versions. Empty when none is set.
	 *
	 * @param int $post_id Post ID. The current page when 0.
	 * @return string
	 */
	function pencilino_seo_title( $post_id = 0 ) {
		return Pencil_SEO::get_title( $post_id );
	}
}

if ( ! function_exists( 'pencilino_seo_description' ) ) {
	/**
	 * The meta description saved by older Pencilino versions. Empty when none is set.
	 *
	 * @param int $post_id Post ID. The current page when 0.
	 * @return string
	 */
	function pencilino_seo_description( $post_id = 0 ) {
		return Pencil_SEO::get_description( $post_id );
	}
}
