<?php
/**
 * Theme-facing Pencilino helpers.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return edit-mode attributes for a registered field, or an empty string for visitors.
 *
 * @internal
 *
 * @param array $field Registered field from Pencil_Fields::register().
 * @return string Escaped attribute string with a leading space.
 */
function pencil_internal_edit_attributes( $field ) {
	if ( '' === $field['id'] || ! Pencil_Plugin::can_edit() ) {
		return '';
	}

	$attrs = array(
		'data-pencil-field' => $field['id'],
		'data-pencil-label' => $field['schema']['label'],
		'data-pencil-type'  => $field['schema']['type'],
		'tabindex'       => '-1',
	);

	if ( ! empty( $field['schema']['max_length'] ) ) {
		$attrs['data-pencil-max-length'] = (string) absint( $field['schema']['max_length'] );
	}

	$html = '';

	foreach ( $attrs as $name => $value ) {
		$html .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
	}

	return $html;
}

/**
 * Return sanitized theme classes from a helper's `class` argument.
 *
 * @internal
 *
 * @param array    $args          Helper arguments.
 * @param string[] $extra_classes Classes to add.
 * @return string
 */
function pencil_internal_class_list( $args, $extra_classes = array() ) {
	$classes = isset( $args['class'] ) ? preg_split( '/\s+/', (string) $args['class'] ) : array();
	$classes = array_merge( $extra_classes, array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ', array_unique( array_filter( $classes ) ) );
}

/**
 * Return the inline image-slot style declared by a theme.
 *
 * @internal
 *
 * @param array $args Image settings.
 * @return string
 */
function pencil_internal_image_style( $args ) {
	$rules = array();

	if (
		! empty( $args['aspect_ratio'] )
		&& preg_match( '#^\s*(\d+(?:\.\d+)?)\s*(?:[/:]\s*(\d+(?:\.\d+)?))?\s*$#', (string) $args['aspect_ratio'], $matches )
	) {
		$rules[] = 'aspect-ratio:' . $matches[1] . ( ! empty( $matches[2] ) ? ' / ' . $matches[2] : '' );
	}

	$fits = array( 'cover', 'contain', 'fill', 'none', 'scale-down' );

	if ( isset( $args['object_fit'] ) && in_array( $args['object_fit'], $fits, true ) ) {
		$rules[] = 'object-fit:' . $args['object_fit'];
	} elseif ( $rules ) {
		$rules[] = 'object-fit:cover';
	}

	if ( ! empty( $args['object_position'] ) && preg_match( '/^[a-z0-9.%\s-]+$/i', (string) $args['object_position'] ) ) {
		$rules[] = 'object-position:' . trim( $args['object_position'] );
	}

	return implode( ';', $rules );
}

if ( ! function_exists( 'pencilino_text' ) ) {
	/**
	 * Render a Pencilino-owned single-line text field.
	 *
	 * @param string $id   Stable field ID.
	 * @param array  $args Field settings: label, default, max_length, scope.
	 * @return void
	 */
	function pencilino_text( $id, $args = array() ) {
		$field = Pencil_Fields::register( 'text', $id, $args );
		$attrs = pencil_internal_edit_attributes( $field );

		if ( '' === $attrs ) {
			echo esc_html( $field['value'] );
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes are escaped by pencil_internal_edit_attributes().
		printf( '<span class="pencil-field"%s>%s</span>', $attrs, esc_html( $field['value'] ) );
	}
}

if ( ! function_exists( 'pencilino_richtext' ) ) {
	/**
	 * Render a Pencilino-owned rich text field with bold, italic, links, and lists.
	 *
	 * Always rendered inside `<div class="pencil-richtext">` so layout is the same
	 * for visitors and editors.
	 *
	 * @param string $id   Stable field ID.
	 * @param array  $args Field settings: label, default, max_length, class, scope.
	 * @return void
	 */
	function pencilino_richtext( $id, $args = array() ) {
		$field   = Pencil_Fields::register( 'richtext', $id, $args );
		$classes = pencil_internal_class_list( $args, array( 'pencil-richtext' ) );
		$attrs   = pencil_internal_edit_attributes( $field );

		if ( '' !== $attrs ) {
			$classes .= ' pencil-field';
		}

		printf(
			'<div class="%s"%s>%s</div>',
			esc_attr( $classes ),
			$attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by pencil_internal_edit_attributes().
			wp_kses( $field['value'], Pencil_Fields::richtext_tags() )
		);
	}
}

if ( ! function_exists( 'pencilino_button' ) ) {
	/**
	 * Render a Pencilino-owned link button with editable text and URL.
	 *
	 * `before` and `after` hold theme-controlled markup inside the link, such as
	 * a decorative icon. The editable text is then wrapped in
	 * `<span class="pencil-button__text">`.
	 *
	 * @param string $id   Stable field ID.
	 * @param array  $args Field settings: label, default (array with text and url),
	 *                     max_length, class, target, before, after, scope.
	 * @return void
	 */
	function pencilino_button( $id, $args = array() ) {
		$field = Pencil_Fields::register( 'button', $id, $args );
		$value = $field['value'];
		$attrs = pencil_internal_edit_attributes( $field );

		if ( '' === $value['text'] && '' === $attrs ) {
			return;
		}

		$classes = pencil_internal_class_list( $args, '' !== $attrs ? array( 'pencil-field' ) : array() );
		$target  = isset( $args['target'] ) && '_blank' === $args['target'] ? ' target="_blank" rel="noopener"' : '';
		$before  = isset( $args['before'] ) ? wp_kses_post( $args['before'] ) : '';
		$after   = isset( $args['after'] ) ? wp_kses_post( $args['after'] ) : '';
		$text    = esc_html( $value['text'] );

		if ( '' !== $before || '' !== $after ) {
			$text = '<span class="pencil-button__text">' . $text . '</span>';
		}

		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Every part is escaped above or by pencil_internal_edit_attributes().
		printf(
			'<a%s href="%s"%s%s>%s%s%s</a>',
			$classes ? ' class="' . esc_attr( $classes ) . '"' : '',
			esc_url( '' !== $value['url'] ? $value['url'] : '#' ),
			$target,
			$attrs,
			$before,
			$text,
			$after
		);
		// phpcs:enable
	}
}

if ( ! function_exists( 'pencilino_image' ) ) {
	/**
	 * Render a Media Library-backed image field.
	 *
	 * @param string $id   Stable field ID.
	 * @param array  $args Image settings: label, default (attachment ID), size, class,
	 *                     loading, fetchpriority, aspect_ratio, object_fit,
	 *                     object_position, scope.
	 * @return void
	 */
	function pencilino_image( $id, $args = array() ) {
		$field         = Pencil_Fields::register( 'image', $id, $args );
		$attachment_id = $field['value'];

		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$attrs = array(
			'class' => pencil_internal_class_list( $args ),
		);
		$style = pencil_internal_image_style( $args );

		if ( $style ) {
			$attrs['style'] = $style;
		}

		if ( isset( $args['loading'] ) ) {
			$attrs['loading'] = sanitize_key( $args['loading'] );
		}

		if ( isset( $args['fetchpriority'] ) ) {
			$attrs['fetchpriority'] = sanitize_key( $args['fetchpriority'] );
		}

		if ( '' !== $field['id'] && Pencil_Plugin::can_edit() ) {
			$attrs['class']                  = trim( $attrs['class'] . ' pencil-field pencil-field--image' );
			$attrs['data-pencil-field']         = $field['id'];
			$attrs['data-pencil-label']         = $field['schema']['label'];
			$attrs['data-pencil-type']          = 'image';
			$attrs['data-pencil-attachment-id'] = (string) $attachment_id;
			$attrs['tabindex']               = '-1';
		}

		echo wp_get_attachment_image( $attachment_id, $field['schema']['size'], false, $attrs );
	}
}

if ( ! function_exists( 'pencilino_managed_region_open' ) ) {
	/**
	 * Open a region owned by another content system, such as custom fields or a shop.
	 *
	 * @param array $args Managed-region settings.
	 * @return void
	 */
	function pencilino_managed_region_open( $args = array() ) {
		$provider = isset( $args['provider'] ) ? sanitize_key( $args['provider'] ) : 'provider';
		$label    = isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : '';
		$field    = isset( $args['field'] ) ? preg_replace( '/[^A-Za-z0-9_:,\[\]-]/', '', (string) $args['field'] ) : '';
		$classes  = array( 'pencil-managed-region' );

		if ( ! empty( $args['class'] ) ) {
			$extra_classes = preg_split( '/\s+/', (string) $args['class'] );
			$classes       = array_merge( $classes, array_map( 'sanitize_html_class', $extra_classes ) );
		}

		$classes = array_filter( array_unique( $classes ) );
		?>
		<div
			class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
			<?php if ( Pencil_Plugin::can_edit() ) : ?>
				data-pencil-managed="<?php echo esc_attr( $provider ); ?>"
				<?php echo $label ? 'data-pencil-managed-label="' . esc_attr( $label ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>
				<?php if ( '' !== $field ) : ?>
					data-pencil-managed-field="<?php echo esc_attr( $field ); ?>"
				<?php endif; ?>
				<?php if ( ! empty( $args['edit_url'] ) ) : ?>
					data-pencil-managed-edit-url="<?php echo esc_url( $args['edit_url'] ); ?>"
				<?php endif; ?>
			<?php endif; ?>
		>
		<?php
	}
}

if ( ! function_exists( 'pencilino_managed_region_close' ) ) {
	/**
	 * Close a read-only provider region.
	 *
	 * @return void
	 */
	function pencilino_managed_region_close() {
		echo '</div>';
	}
}

if ( ! function_exists( 'pencilino_cleanup_prompt' ) ) {
	/** The clean-up prompt for an AI agent, so a theme can show it (for example on its brand guide). Empty when Dev mode is not available. */
	function pencilino_cleanup_prompt() {
		return class_exists( 'Pencil_Dev' ) ? Pencil_Dev::master_prompt() : '';
	}
}

/*
 * Older themes call the helpers by their former pencil_ names. Those names keep working.
 * New code uses the pencilino_ names.
 */
if ( ! function_exists( 'pencil_text' ) ) {
	function pencil_text( $id, $args = array() ) {
		return pencilino_text( $id, $args );
	}
}

if ( ! function_exists( 'pencil_richtext' ) ) {
	function pencil_richtext( $id, $args = array() ) {
		return pencilino_richtext( $id, $args );
	}
}

if ( ! function_exists( 'pencil_button' ) ) {
	function pencil_button( $id, $args = array() ) {
		return pencilino_button( $id, $args );
	}
}

if ( ! function_exists( 'pencil_image' ) ) {
	function pencil_image( $id, $args = array() ) {
		return pencilino_image( $id, $args );
	}
}

if ( ! function_exists( 'pencil_managed_region_open' ) ) {
	function pencil_managed_region_open( $args = array() ) {
		return pencilino_managed_region_open( $args );
	}
}

if ( ! function_exists( 'pencil_managed_region_close' ) ) {
	function pencil_managed_region_close() {
		return pencilino_managed_region_close();
	}
}

if ( ! function_exists( 'pencil_cleanup_prompt' ) ) {
	function pencil_cleanup_prompt() {
		return pencilino_cleanup_prompt();
	}
}

if ( ! function_exists( 'pencil_seo_plugin' ) ) {
	function pencil_seo_plugin() {
		return pencilino_seo_plugin();
	}
}

if ( ! function_exists( 'pencil_seo_title' ) ) {
	function pencil_seo_title( $post_id = 0 ) {
		return pencilino_seo_title( $post_id );
	}
}

if ( ! function_exists( 'pencil_seo_description' ) ) {
	function pencil_seo_description( $post_id = 0 ) {
		return pencilino_seo_description( $post_id );
	}
}
