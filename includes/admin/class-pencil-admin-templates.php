<?php
/**
 * Pencilino admin: Templates tab: the theme templates and where each one is used.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin_Templates {
	/**
	 * Templates tab: the template files of the active theme and where each one is used.
	 *
	 * @return void
	 */
	public static function render() {
		$theme     = wp_get_theme();
		$templates = Pencil_Templates::get_all( 10 );
		$listed    = wp_list_pluck( $templates, 'file' );
		$others    = array();
		foreach ( $templates as $item ) {
			if ( 'part' !== $item['type'] ) {
				$others[] = $item['file'];
			}
		}
		$groups    = self::template_groups();
		$present   = array();
		foreach ( $templates as $template ) {
			$present[ self::template_group( $template['type'] ) ] = true;
		}
		$guides    = get_posts( array( 'name' => 'brand-guide', 'post_type' => 'page', 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => 1 ) );
		$guide     = $guides ? $guides[0] : null;
		?>
		<div class="pencil-content pencil-content--onboarding">
			<div class="pencil-card pencil-card--full">
				<div class="pencil-page-intro">
					<span class="pencil-eyebrow"><?php echo wp_kses_post( $theme->display( 'Name', false ) ); ?></span>
					<h2><?php esc_html_e( 'Templates', 'pencilino' ); ?></h2>
					<p><?php esc_html_e( 'The template files in the active theme and where each one is used.', 'pencilino' ); ?></p>
					<?php if ( $guide ) : ?>
						<p><a class="pencil-admin__record-link" href="<?php echo esc_url( get_permalink( $guide ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Brand guide', 'pencilino' ); ?> &#8599;</a><?php if ( 'publish' === $guide->post_status ) : ?> <small><?php esc_html_e( 'This page is public. Set it to Private.', 'pencilino' ); ?></small><?php endif; ?></p>
					<?php endif; ?>
				</div>

				<?php if ( empty( $templates ) ) : ?>
					<div class="pencil-admin__empty">
						<span class="dashicons dashicons-layout" aria-hidden="true"></span>
						<h3><?php esc_html_e( 'No templates found', 'pencilino' ); ?></h3>
						<p><?php esc_html_e( 'The active theme has no PHP template files yet.', 'pencilino' ); ?></p>
					</div>
				<?php else : ?>
					<?php if ( count( $present ) > 1 ) : ?>
						<div class="pencil-filters" role="group" aria-label="<?php esc_attr_e( 'Filter templates', 'pencilino' ); ?>">
							<button type="button" class="pencil-filters__btn" data-pencil-filter="all" aria-pressed="true"><?php esc_html_e( 'All', 'pencilino' ); ?></button>
							<?php foreach ( $groups as $slug => $label ) : ?>
								<?php if ( isset( $present[ $slug ] ) ) : ?>
									<button type="button" class="pencil-filters__btn" data-pencil-filter="<?php echo esc_attr( $slug ); ?>" aria-pressed="false"><?php echo esc_html( $label ); ?></button>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<ul class="pencil-admin__list pencil-templates">
						<?php foreach ( $templates as $template ) : ?>
							<li class="pencil-admin__record" data-group="<?php echo esc_attr( self::template_group( $template['type'] ) ); ?>" id="<?php echo esc_attr( self::template_anchor( $template['file'] ) ); ?>">
								<div class="pencil-admin__record-head pencil-admin__record-head--start">
									<strong class="pencil-admin__field"><?php echo esc_html( $template['label'] ); ?></strong>
									<button type="button" class="pencil-admin__copy" data-pencil-copy="<?php echo esc_attr( $template['file'] ); ?>" title="<?php esc_attr_e( 'Click to copy the file name', 'pencilino' ); ?>">(<?php echo esc_html( $template['file'] ); ?>)</button>
								</div>
								<?php if ( $template['pages'] || $template['description'] || $template['used_in'] ) : ?>
									<div class="pencil-admin__meta-line"><?php self::render_template_usage( $template, $listed, $others ); ?></div>
								<?php endif; ?>
								<?php if ( $template['sample_url'] ) : ?>
									<a class="pencil-admin__record-link" href="<?php echo esc_url( $template['sample_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $template['sample_text'] ); ?> &#8599;</a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * The filter bubbles of the Templates tab: slug => label.
	 *
	 * @return array<string, string>
	 */
	private static function template_groups() {
		return array(
			'pages'    => __( 'Page templates', 'pencilino' ),
			'parts'    => __( 'Template parts', 'pencilino' ),
			'posts'    => __( 'Post templates', 'pencilino' ),
			'archives' => __( 'Archive templates', 'pencilino' ),
			'other'    => __( 'Other', 'pencilino' ),
		);
	}

	/**
	 * The filter bubble a template type belongs to.
	 *
	 * @param string $type Template type from Pencil_Templates.
	 * @return string
	 */
	private static function template_group( $type ) {
		switch ( $type ) {
			case 'front-page':
			case 'posts-page':
			case 'page-template':
			case 'page':
				return 'pages';
			case 'single':
				return 'posts';
			case 'archive':
				return 'archives';
			case 'part':
				return 'parts';
			default:
				return 'other';
		}
	}

	/**
	 * The element ID of one template in the list, so other entries can scroll to it.
	 *
	 * @param string $file Template file.
	 * @return string
	 */
	private static function template_anchor( $file ) {
		return 'pencil-template-' . md5( (string) $file );
	}

	/**
	 * Print where one template part is used: the templates that include it, as links that scroll to them.
	 *
	 * @param array<string, mixed> $template One item from Pencil_Templates::get_all().
	 * @param string[]             $listed   Files that have their own entry in the list.
	 * @return string
	 */
	private static function template_used_in( $template, $listed, $others = array() ) {
		$files = array_values( $template['used_in'] );
		$link  = function ( $file ) use ( $listed ) {
			return in_array( $file, $listed, true )
				? sprintf( '<a href="#%1$s">%2$s</a>', esc_attr( self::template_anchor( $file ) ), esc_html( $file ) )
				: esc_html( $file );
		};

		// A part that most templates load, like the header and footer, starts with a sentence in plain words.
		$covered = $others ? array_intersect( $others, $files ) : array();
		$lead    = '';
		if ( $others && count( $covered ) * 2 >= count( $others ) ) {
			$missing = array_values( array_diff( $others, $files ) );
			if ( $missing ) {
				/* translators: %s: template files, for example index.php and 404.php. */
				$lead = sprintf( esc_html__( 'Shown on every template except %s.', 'pencilino' ), wp_sprintf( '%l', array_map( 'esc_html', $missing ) ) );
			} else {
				$lead = esc_html__( 'Shown on every template.', 'pencilino' );
			}
		}

		// Then a quiet label and the first three templates. The rest opens from a small button at the end of the line.
		$shown = array_slice( $files, 0, 3 );
		$rest  = array_slice( $files, 3 );
		$label = '' !== $lead ? __( 'Templates:', 'pencilino' ) : __( 'Shown on:', 'pencilino' );
		$line  = '<span class="pencil-admin__label">' . esc_html( $label ) . '</span> ' . implode( ', ', array_map( $link, $shown ) );

		if ( $rest ) {
			/* translators: %d: number of templates that are not shown yet. */
			$more_label = sprintf( __( '%d more', 'pencilino' ), count( $rest ) );
			$line      .= '<span class="pencil-admin__rest" hidden>, ' . implode( ', ', array_map( $link, $rest ) ) . '</span> ';
			$line      .= '<button type="button" class="pencil-admin__more" data-pencil-more data-more="' . esc_attr( $more_label ) . '" data-less="' . esc_attr__( 'Show less', 'pencilino' ) . '" aria-expanded="false">' . esc_html( $more_label ) . '</button>';
		}

		return '' !== $lead ? $lead . '<br>' . $line : $line;
	}

	/**
	 * Print where one template is used: the pages that use it, or a short description.
	 *
	 * @param array<string, mixed> $template One item from Pencil_Templates::get_all().
	 * @param string[]             $listed   Files that have their own entry in the list.
	 * @return void
	 */
	private static function render_template_usage( $template, $listed = array(), $others = array() ) {
		$links = array();
		$parts = array();

		if ( ! empty( $template['used_in'] ) ) {
			echo self::template_used_in( $template, $listed, $others ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped where it is built.
			return;
		}

		foreach ( $template['pages'] as $page ) {
			$link = sprintf( '<a href="%s">%s</a>', esc_url( $page['url'] ), esc_html( $page['title'] ) );

			if ( 'publish' !== $page['status'] ) {
				$status = get_post_status_object( $page['status'] );
				$link  .= sprintf( ' <span class="pencil-admin__muted">(%s)</span>', esc_html( $status ? $status->label : $page['status'] ) );
			}

			$links[] = $link;
		}

		if ( $links ) {
			$more = (int) $template['pages_total'] - count( $links );
			$text = sprintf(
				/* translators: %s: list of page links. */
				__( 'Used on: %s', 'pencilino' ),
				implode( ', ', $links )
			);

			if ( $more > 0 ) {
				/* translators: %d: number of pages that are not listed. */
				$text .= ' ' . esc_html( sprintf( _n( 'and %d more page', 'and %d more pages', $more, 'pencilino' ), $more ) );
			}

			$parts[] = $text;
		}

		if ( $template['description'] ) {
			$text = esc_html( $template['description'] );

			if ( $template['url'] && '' !== $template['link_text'] && false !== strpos( $text, esc_html( $template['link_text'] ) ) ) {
				$needle = esc_html( $template['link_text'] );
				$anchor = sprintf( '<a href="%s">%s</a>', esc_url( $template['url'] ), $needle );
				$text   = substr_replace( $text, $anchor, strpos( $text, $needle ), strlen( $needle ) );
			} elseif ( $template['url'] ) {
				$text .= sprintf( ' <a href="%s">%s</a>', esc_url( $template['url'] ), esc_html__( 'View', 'pencilino' ) );
			}

			$parts[] = $text;
		}

		echo wp_kses_post( implode( ' ', $parts ) );
	}
}
