<?php
/**
 * Template overview: the template files of the active theme and where they are used.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Templates {
	/**
	 * Theme folders that hold included template files.
	 */
	const PART_FOLDERS = array( 'template-parts', 'parts', 'templates' );

	/**
	 * Page statuses that count as using a template.
	 */
	const PAGE_STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Display order per template type.
	 */
	const ORDER = array(
		'front-page'    => 10,
		'posts-page'    => 15,
		'page-template' => 20,
		'page'          => 30,
		'single'        => 40,
		'archive'       => 50,
		'other'         => 60,
		'woocommerce'   => 85,
		'fallback'      => 90,
		'part'          => 95,
		'custom'        => 99,
	);

	/**
	 * List the template files of the active theme and where each one is used.
	 *
	 * The list comes from the theme folder itself, so a new file shows up straight away.
	 *
	 * @param int $pages_limit How many pages to list per template.
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all( $pages_limit = 10 ) {
		$theme = wp_get_theme();
		$theme->cache_delete();

		$page_templates = $theme->get_page_templates( null, 'page' );
		$files          = array_keys( $theme->get_files( 'php', 1, true ) );
		$items          = array();

		foreach ( $files as $file ) {
			$item = self::describe( (string) $file, $page_templates, absint( $pages_limit ) );
			if ( $item ) {
				$items[] = $item;
			}
		}

		usort(
			$items,
			static function ( $a, $b ) {
				$order = self::ORDER[ $a['type'] ] - self::ORDER[ $b['type'] ];
				return $order ? $order : strcmp( $a['file'], $b['file'] );
			}
		);

		foreach ( $items as $index => $item ) {
			if ( 'single' === $item['type'] ) {
				$items[ $index ]['sample_url']  = self::template_sample_url( $item );
				$items[ $index ]['sample_text'] = __( 'View an example', 'pencilino' );
			}
		}

		return self::add_part_usage( $items, $files );
	}

	/**
	 * Find which template files include each template part, and a page that shows the part.
	 *
	 * Nothing is stored: the theme files are read when the tab opens.
	 *
	 * @param array<int, array<string, mixed>> $items Described templates.
	 * @param string[]                         $files All PHP files of the theme.
	 * @return array<int, array<string, mixed>>
	 */
	private static function add_part_usage( $items, $files ) {
		$has_parts = false;
		foreach ( $items as $item ) {
			if ( 'part' === $item['type'] ) {
				$has_parts = true;
				break;
			}
		}

		if ( ! $has_parts ) {
			return $items;
		}

		$known = array();
		foreach ( $files as $file ) {
			$known[ (string) $file ] = true;
		}

		$refs = array();
		foreach ( array_keys( $known ) as $file ) {
			foreach ( self::referenced_files( $file, $known ) as $target ) {
				if ( $target !== $file ) {
					$refs[ $target ][ $file ] = true;
				}
			}
		}

		$by_file = array();
		foreach ( $items as $item ) {
			$by_file[ $item['file'] ] = $item;
		}

		foreach ( $items as $index => $item ) {
			if ( 'part' !== $item['type'] || empty( $refs[ $item['file'] ] ) ) {
				continue;
			}

			$users = array_keys( $refs[ $item['file'] ] );
			sort( $users );

			$items[ $index ]['used_in']    = $users;
			$items[ $index ]['sample_url']  = self::part_sample_url( $item['file'], $refs, $by_file, array() );
			$items[ $index ]['sample_text'] = __( 'View a page that uses this', 'pencilino' );
		}

		return $items;
	}

	/**
	 * The theme files that one file loads with get_header(), get_template_part() or include.
	 *
	 * @param string              $file  Theme file, relative to the theme folder.
	 * @param array<string, bool> $known Theme files by path.
	 * @return string[]
	 */
	private static function referenced_files( $file, $known ) {
		$path = get_theme_file_path( $file );
		if ( ! is_readable( $path ) || filesize( $path ) > 524288 ) {
			return array();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local theme file.
		$code = (string) file_get_contents( $path );
		$out  = array();

		if ( preg_match_all( '/\bget_(header|footer|sidebar)\s*\(\s*(?:[\'"]([^\'"]*)[\'"])?/', $code, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) {
				$special = isset( $hit[2] ) ? $hit[2] : '';
				$named   = '' !== $special ? $hit[1] . '-' . $special . '.php' : '';
				$out[]   = ( '' !== $named && isset( $known[ $named ] ) ) ? $named : $hit[1] . '.php';
			}
		}

		if ( preg_match_all( '/\bget_template_part\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]([^\'"]+)[\'"])?/', $code, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $hit ) {
				$slug   = ltrim( $hit[1], '/' );
				$named  = ! empty( $hit[2] ) ? $slug . '-' . $hit[2] . '.php' : '';
				$out[]  = ( '' !== $named && isset( $known[ $named ] ) ) ? $named : $slug . '.php';
			}
		}

		if ( preg_match_all( '/\b(?:include|include_once|require|require_once|locate_template|load_template)\b[^;]*?[\'"]\/?([\w\-\/]+\.php)[\'"]/', $code, $m ) ) {
			foreach ( $m[1] as $target ) {
				$out[] = $target;
			}
		}

		return array_values( array_unique( array_filter( $out, static function ( $target ) use ( $known ) { return isset( $known[ $target ] ); } ) ) );
	}

	/**
	 * A front-end page that loads a template part: the first template that includes it, followed up the chain.
	 */
	private static function part_sample_url( $file, $refs, $by_file, $seen ) {
		if ( isset( $seen[ $file ] ) || count( $seen ) > 5 ) {
			return '';
		}

		$seen[ $file ] = true;
		$users        = isset( $refs[ $file ] ) ? array_keys( $refs[ $file ] ) : array();
		sort( $users );

		foreach ( $users as $user ) {
			if ( ! isset( $by_file[ $user ] ) ) {
				continue;
			}

			$url = 'part' === $by_file[ $user ]['type'] ? self::part_sample_url( $user, $refs, $by_file, $seen ) : self::template_sample_url( $by_file[ $user ] );
			if ( '' !== $url ) {
				return $url;
			}
		}

		return '';
	}

	/**
	 * The address of one real page that uses a template, or an empty string.
	 */
	private static function template_sample_url( $item ) {
		foreach ( $item['pages'] as $page ) {
			if ( 'publish' === $page['status'] && '' !== $page['url'] ) {
				return $page['url'];
			}
		}

		$name = basename( $item['file'], '.php' );

		switch ( $item['type'] ) {
			case 'front-page':
			case 'posts-page':
			case 'fallback':
				return home_url( '/' );
			case 'page':
				$type = 'page';
				break;
			case 'single':
				$type = 0 === strpos( $name, 'single-' ) ? substr( $name, 7 ) : 'post';
				break;
			case 'archive':
				return $item['url'] ? $item['url'] : '';
			default:
				return '';
		}

		if ( ! post_type_exists( $type ) ) {
			return '';
		}

		$posts = get_posts(
			array(
				'post_type'     => $type,
				'post_status'   => 'publish',
				'numberposts'   => 1,
				'no_found_rows' => true,
			)
		);

		return $posts ? (string) get_permalink( $posts[0] ) : '';
	}

	private static function describe( $file, $page_templates, $limit ) {
		$dir  = dirname( $file );
		$name = basename( $file, '.php' );

		if ( '.' === $dir && 'functions' === $name ) {
			return null;
		}

		if ( '.' !== $dir && ! in_array( $dir, self::PART_FOLDERS, true ) && ! isset( $page_templates[ $file ] ) ) {
			return null;
		}

		$item = array(
			'file'        => $file,
			'type'        => 'custom',
			'label'       => __( 'Custom template', 'pencilino' ),
			'description' => __( 'Not a standard WordPress template name. The theme loads it with its own code.', 'pencilino' ),
			'pages'       => array(),
			'pages_total' => 0,
			'url'         => '',
			'link_text'   => '',
			'used_in'     => array(),
			'sample_url'  => '',
			'sample_text' => '',
		);

		if ( isset( $page_templates[ $file ] ) ) {
			$posts               = self::pages_with_template( $file );
			$item['type']        = 'page-template';
			$item['label']       = sprintf(
				/* translators: %s: template name shown in the page Template menu. */
				__( 'Page template: %s', 'pencilino' ),
				html_entity_decode( wp_strip_all_tags( $page_templates[ $file ] ), ENT_QUOTES, 'UTF-8' )
			);
			$item['description'] = $posts ? '' : __( 'No page uses this template yet.', 'pencilino' );
			self::set_pages( $item, $posts, $limit );
			return $item;
		}

		if ( '.' !== $dir || preg_match( '/^(header|footer|sidebar)(-.+)?$/', $name ) ) {
			$item['type']        = 'part';
			$item['label']       = __( 'Template part', 'pencilino' );
			$item['description'] = __( 'Included by other templates.', 'pencilino' );

			if ( 0 === strpos( $name, 'header' ) ) {
				$item['label'] = __( 'Header', 'pencilino' );
			} elseif ( 0 === strpos( $name, 'footer' ) ) {
				$item['label'] = __( 'Footer', 'pencilino' );
			} elseif ( 0 === strpos( $name, 'sidebar' ) ) {
				$item['label'] = __( 'Sidebar', 'pencilino' );
			}

			return $item;
		}

		return self::describe_hierarchy( $item, $name, $limit );
	}

	/**
	 * Describe a top-level file by its place in the WordPress template hierarchy.
	 */
	private static function describe_hierarchy( $item, $name, $limit ) {
		if ( class_exists( 'WooCommerce' ) && ( 'woocommerce' === $name || 0 === strpos( $name, 'woocommerce-' ) ) ) {
			return self::describe_woocommerce_file( $item, $name );
		}

		if ( 'front-page' === $name ) {
			$item['type']  = 'front-page';
			$item['label'] = __( 'Front page', 'pencilino' );
			$front         = 'page' === get_option( 'show_on_front' ) ? get_post( (int) get_option( 'page_on_front' ) ) : null;

			if ( $front instanceof WP_Post ) {
				$item['description'] = '';
				self::set_pages( $item, array( $front ), $limit );
			} else {
				$item['description'] = __( 'The homepage of the site.', 'pencilino' );
			}

			return $item;
		}

		if ( 'home' === $name ) {
			$item['type']  = 'posts-page';
			$item['label'] = __( 'Posts page', 'pencilino' );
			$posts_page    = get_post( (int) get_option( 'page_for_posts' ) );

			if ( $posts_page instanceof WP_Post ) {
				$item['description'] = '';
				self::set_pages( $item, array( $posts_page ), $limit );
			} else {
				$item['description'] = __( 'The page that lists the latest posts.', 'pencilino' );
			}

			return $item;
		}

		if ( 'index' === $name ) {
			$item['type']        = 'fallback';
			$item['label']       = __( 'Fallback', 'pencilino' );
			$item['description'] = __( 'Used when no more specific template matches.', 'pencilino' );

			if ( ! file_exists( get_theme_file_path( 'page.php' ) ) && ! file_exists( get_theme_file_path( 'singular.php' ) ) ) {
				$item['description'] = __( 'Used when no more specific template matches. This includes pages that have no template of their own.', 'pencilino' );
			}

			return $item;
		}

		$simple = self::simple_templates();

		if ( isset( $simple[ $name ] ) ) {
			$item['type']        = $simple[ $name ][0];
			$item['label']       = $simple[ $name ][1];
			$item['description'] = $simple[ $name ][2];
			return $item;
		}

		if ( 0 === strpos( $name, 'page-' ) ) {
			return self::describe_page_file( $item, substr( $name, 5 ), $limit );
		}

		if ( 0 === strpos( $name, 'single-' ) ) {
			return self::describe_post_type_file( $item, substr( $name, 7 ), 'single' );
		}

		if ( 0 === strpos( $name, 'archive-' ) ) {
			return self::describe_post_type_file( $item, substr( $name, 8 ), 'archive' );
		}

		$prefixes = array(
			'taxonomy-' => __( 'Taxonomy archive', 'pencilino' ),
			'category-' => __( 'Category archive', 'pencilino' ),
			'tag-'      => __( 'Tag archive', 'pencilino' ),
			'author-'   => __( 'Author archive', 'pencilino' ),
		);

		foreach ( $prefixes as $prefix => $label ) {
			if ( 0 === strpos( $name, $prefix ) ) {
				$item['type']        = 'archive';
				$item['label']       = $label;
				$item['description'] = sprintf(
					/* translators: %s: slug or ID taken from the file name. */
					__( 'Used for one specific archive: %s.', 'pencilino' ),
					substr( $name, strlen( $prefix ) )
				);
				return $item;
			}
		}

		return $item;
	}

	/**
	 * Fixed template names: type, label and description.
	 */
	private static function simple_templates() {
		return array(
			'page'       => array( 'page', __( 'All pages', 'pencilino' ), __( 'Every page without a page template or a page-specific file.', 'pencilino' ) ),
			'singular'   => array( 'single', __( 'Single posts and pages', 'pencilino' ), __( 'Any single post, page or custom post type without a more specific template.', 'pencilino' ) ),
			'single'     => array( 'single', __( 'Single posts', 'pencilino' ), __( 'Every single post without a more specific template.', 'pencilino' ) ),
			'archive'    => array( 'archive', __( 'Archives', 'pencilino' ), __( 'Every archive without a more specific template.', 'pencilino' ) ),
			'category'   => array( 'archive', __( 'Category archives', 'pencilino' ), __( 'The archive of every category.', 'pencilino' ) ),
			'tag'        => array( 'archive', __( 'Tag archives', 'pencilino' ), __( 'The archive of every tag.', 'pencilino' ) ),
			'author'     => array( 'archive', __( 'Author archives', 'pencilino' ), __( 'The archive of every author.', 'pencilino' ) ),
			'date'       => array( 'archive', __( 'Date archives', 'pencilino' ), __( 'Archives by year, month or day.', 'pencilino' ) ),
			'taxonomy'   => array( 'archive', __( 'Taxonomy archives', 'pencilino' ), __( 'The archive of every custom taxonomy term.', 'pencilino' ) ),
			'search'     => array( 'other', __( 'Search results', 'pencilino' ), __( 'The search results page.', 'pencilino' ) ),
			'404'        => array( 'other', __( 'Not found', 'pencilino' ), __( 'Shown when a URL does not exist.', 'pencilino' ) ),
			'attachment' => array( 'other', __( 'Attachments', 'pencilino' ), __( 'The page of a single media file.', 'pencilino' ) ),
			'embed'      => array( 'other', __( 'Embeds', 'pencilino' ), __( 'Used when a page of this site is embedded elsewhere.', 'pencilino' ) ),
		);
	}

	/**
	 * A page-{slug}.php or page-{id}.php file.
	 */
	private static function describe_page_file( $item, $slug, $limit ) {
		$found = array();
		$posts = get_posts(
			array(
				'post_type'     => 'page',
				'post_status'   => self::PAGE_STATUSES,
				'name'          => $slug,
				'numberposts'   => -1,
				'no_found_rows' => true,
			)
		);

		foreach ( $posts as $post ) {
			$found[ $post->ID ] = $post;
		}

		if ( ctype_digit( $slug ) ) {
			$by_id = get_post( (int) $slug );
			if ( $by_id instanceof WP_Post && 'page' === $by_id->post_type ) {
				$found[ $by_id->ID ] = $by_id;
			}
		}

		$item['type']        = 'page';
		$item['label']       = __( 'Specific page', 'pencilino' );
		$item['description'] = $found ? '' : sprintf(
			/* translators: %s: slug or ID taken from the file name. */
			__( 'No page with the slug or ID %s exists yet.', 'pencilino' ),
			$slug
		);
		self::set_pages( $item, array_values( $found ), $limit );

		return $item;
	}

	/**
	 * A single-{post type}.php or archive-{post type}.php file.
	 */
	private static function describe_post_type_file( $item, $post_type, $kind ) {
		$object       = get_post_type_object( $post_type );
		$item['type'] = $kind;

		if ( ! $object ) {
			$item['label']       = 'single' === $kind ? __( 'Single post', 'pencilino' ) : __( 'Archive', 'pencilino' );
			$item['description'] = sprintf(
				/* translators: %s: name taken from the file name. */
				__( 'No post type named %s is registered.', 'pencilino' ),
				$post_type
			);
			return $item;
		}

		if ( 'single' === $kind ) {
			$counts = wp_count_posts( $post_type );
			$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;

			$item['label']       = sprintf(
				/* translators: %s: singular name of the post type. */
				__( 'Single: %s', 'pencilino' ),
				$object->labels->singular_name
			);
			$link_text = sprintf(
				/* translators: %d: number of published items. */
				__( '%d published', 'pencilino' ),
				$total
			);

			$item['description'] = sprintf(
				/* translators: 1: singular name of the post type, 2: number of published items, for example 5 published. */
				__( 'Every single %1$s (%2$s).', 'pencilino' ),
				strtolower( $object->labels->singular_name ),
				$link_text
			);
			return $item;
		}

		$item['label'] = sprintf(
			/* translators: %s: plural name of the post type. */
			__( 'Archive: %s', 'pencilino' ),
			$object->labels->name
		);

		if ( ! $object->has_archive && 'post' !== $post_type ) {
			$item['description'] = __( 'This post type has no archive turned on, so WordPress does not use this file.', 'pencilino' );
			return $item;
		}

		$link                = get_post_type_archive_link( $post_type );
		$item['url']         = $link ? $link : '';
		$item['description'] = sprintf(
			/* translators: %s: plural name of the post type. */
			__( 'The archive page for %s.', 'pencilino' ),
			strtolower( $object->labels->name )
		);

		return $item;
	}

	/**
	 * A woocommerce.php or woocommerce-*.php file. The theme loads these itself, so no pages are listed.
	 */
	private static function describe_woocommerce_file( $item, $name ) {
		$item['type']  = 'woocommerce';
		$item['label'] = __( 'WooCommerce template', 'pencilino' );

		if ( 'woocommerce' === $name ) {
			$item['description'] = __( 'WooCommerce loads this file for its own pages, such as the shop.', 'pencilino' );
		} else {
			$item['description'] = __( 'Used for WooCommerce pages, like the cart, checkout and account. The theme loads this file itself, so those pages are not listed here.', 'pencilino' );
		}

		return $item;
	}

	private static function pages_with_template( $file ) {
		return get_posts(
			array(
				'post_type'     => 'page',
				'post_status'   => self::PAGE_STATUSES,
				'meta_key'      => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only Templates tab.
				'meta_value'    => $file, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin-only Templates tab.
				'numberposts'   => -1,
				'orderby'       => 'title',
				'order'         => 'ASC',
				'no_found_rows' => true,
			)
		);
	}

	private static function set_pages( &$item, $posts, $limit ) {
		$item['pages_total'] = count( $posts );

		foreach ( array_slice( $posts, 0, $limit ) as $post ) {
			$title = trim( html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ) );

			$item['pages'][] = array(
				'id'     => (int) $post->ID,
				'title'  => '' !== $title ? $title : __( '(no title)', 'pencilino' ),
				'url'    => (string) get_permalink( $post ),
				'status' => $post->post_status,
			);
		}
	}
}