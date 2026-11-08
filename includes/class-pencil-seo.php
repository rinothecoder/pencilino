<?php
/**
 * SEO title and meta description per page, for sites that have no SEO plugin.
 *
 * When an SEO plugin is active this class does nothing, so the two never fight over the same tags.
 * Without one, every page and post gets a small "SEO" box with a title and a description. The theme does
 * not print anything for them: this class prints the description and the Open Graph tags, and changes the
 * title that WordPress prints itself. The theme only has to support `title-tag`.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_SEO {
	const META_TITLE       = '_pencil_seo_title';
	const META_DESCRIPTION = '_pencil_seo_description';
	const NONCE            = 'pencil_seo_save';

	/**
	 * Hook everything up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_meta' ) );
		add_action( 'init', array( __CLASS__, 'boot' ), 20 );
	}

	/**
	 * Register the two meta keys so they also work through the REST API.
	 *
	 * @return void
	 */
	public static function register_meta() {
		foreach ( array( self::META_TITLE => 'sanitize_text_field', self::META_DESCRIPTION => 'sanitize_text_field' ) as $key => $sanitize ) {
			register_post_meta(
				'',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
						return current_user_can( 'edit_post', $post_id );
					},
				)
			);
		}
	}

	/**
	 * Turn the features on, unless an SEO plugin is doing the job.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( '' !== self::detected_plugin() ) {
			return;
		}

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save' ), 10, 2 );

		if ( apply_filters( 'pencil_seo_print_head', apply_filters( 'pencilino_seo_print_head', true ) ) ) {
			add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
			add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );
		}
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
	 * Saved SEO title of a page or post.
	 *
	 * @param int $post_id Post ID. The current page when 0.
	 * @return string
	 */
	public static function get_title( $post_id = 0 ) {
		$post_id = $post_id ? absint( $post_id ) : self::current_id();

		return $post_id ? trim( (string) get_post_meta( $post_id, self::META_TITLE, true ) ) : '';
	}

	/**
	 * Saved meta description of a page or post.
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

	/**
	 * Post types that get the box.
	 *
	 * @return string[]
	 */
	private static function post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );

		return array_values( $types );
	}

	/**
	 * Use the saved title in the page title WordPress prints. The site name still follows it.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		$title = self::get_title();

		if ( '' === $title ) {
			return $parts;
		}

		$parts['title'] = $title;

		if ( is_front_page() ) {
			unset( $parts['tagline'] );
		}

		return $parts;
	}

	/**
	 * Print the description and the Open Graph tags.
	 *
	 * @return void
	 */
	public static function head() {
		$post_id = self::current_id();

		if ( ! $post_id ) {
			return;
		}

		$title       = self::get_title( $post_id );
		$description = self::get_description( $post_id );
		$shown_title = '' !== $title ? $title : get_the_title( $post_id );
		$url         = is_front_page() ? home_url( '/' ) : get_permalink( $post_id );
		$image       = get_the_post_thumbnail_url( $post_id, 'large' );

		$tags = array(
			'og:type'      => is_singular( 'post' ) ? 'article' : 'website',
			'og:title'     => $shown_title,
			'og:url'       => $url,
			'og:site_name' => get_bloginfo( 'name' ),
		);

		if ( '' !== $description ) {
			$tags['og:description'] = $description;
		}

		if ( $image ) {
			$tags['og:image'] = $image;
		}

		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}

		foreach ( $tags as $property => $content ) {
			if ( '' !== (string) $content ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}

		echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";
	}

	/**
	 * Add the SEO box to the edit screen.
	 *
	 * @return void
	 */
	public static function add_box() {
		foreach ( self::post_types() as $type ) {
			add_meta_box( 'pencil-seo', __( 'SEO', 'pencilino' ), array( __CLASS__, 'render_box' ), $type, 'normal', 'default' );
		}
	}

	/**
	 * Draw the SEO box.
	 *
	 * @param WP_Post $post The post being edited.
	 * @return void
	 */
	public static function render_box( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE . '_nonce' );

		$title       = (string) get_post_meta( $post->ID, self::META_TITLE, true );
		$description = (string) get_post_meta( $post->ID, self::META_DESCRIPTION, true );
		?>
		<p>
			<label for="pencil-seo-title"><strong><?php esc_html_e( 'SEO title', 'pencilino' ); ?></strong></label><br>
			<input type="text" id="pencil-seo-title" name="pencil_seo_title" value="<?php echo esc_attr( $title ); ?>" class="widefat" data-pencil-count="60">
			<span class="description"><span data-pencil-counter="pencil-seo-title">0</span> / 60. <?php esc_html_e( 'Leave empty to use the page title. The site name is added after it.', 'pencilino' ); ?></span>
		</p>
		<p>
			<label for="pencil-seo-description"><strong><?php esc_html_e( 'Meta description', 'pencilino' ); ?></strong></label><br>
			<textarea id="pencil-seo-description" name="pencil_seo_description" rows="3" class="widefat" data-pencil-count="155"><?php echo esc_textarea( $description ); ?></textarea>
			<span class="description"><span data-pencil-counter="pencil-seo-description">0</span> / 155. <?php esc_html_e( 'Leave empty to print no description. Search engines may show their own text.', 'pencilino' ); ?></span>
		</p>
		<script>
			( function () {
				document.querySelectorAll( '[data-pencil-count]' ).forEach( function ( field ) {
					var out = document.querySelector( '[data-pencil-counter="' + field.id + '"]' );
					var max = parseInt( field.getAttribute( 'data-pencil-count' ), 10 );
					var update = function () {
						out.textContent = String( field.value.length );
						out.style.color = field.value.length > max ? '#b32d2e' : '';
					};
					field.addEventListener( 'input', update );
					update();
				} );
			}() );
		</script>
		<?php
	}

	/**
	 * Save the two fields.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public static function save( $post_id, $post ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE . '_nonce' ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			self::META_TITLE       => 'pencil_seo_title',
			self::META_DESCRIPTION => 'pencil_seo_description',
		);

		foreach ( $fields as $key => $name ) {
			$value = isset( $_POST[ $name ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) ) : '';

			if ( '' === $value ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $value );
			}
		}
	}
}

if ( ! function_exists( 'pencilino_seo_plugin' ) ) {
	/**
	 * Name of the active SEO plugin, or an empty string when Pencilino handles the SEO fields itself.
	 *
	 * @return string
	 */
	function pencilino_seo_plugin() {
		return Pencil_SEO::detected_plugin();
	}
}

if ( ! function_exists( 'pencilino_seo_title' ) ) {
	/**
	 * The SEO title saved for a page or post. Empty when none is set.
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
	 * The meta description saved for a page or post. Empty when none is set.
	 *
	 * @param int $post_id Post ID. The current page when 0.
	 * @return string
	 */
	function pencilino_seo_description( $post_id = 0 ) {
		return Pencil_SEO::get_description( $post_id );
	}
}
