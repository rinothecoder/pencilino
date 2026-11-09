<?php
/**
 * Pencilino field registration and value storage.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Fields {
	/**
	 * Supported field types.
	 *
	 * @var string[]
	 */
	const TYPES = array( 'text', 'richtext', 'image', 'button' );

	/**
	 * Rich text length (visible characters) when the theme sets no max_length.
	 */
	const RICHTEXT_DEFAULT_MAX = 20000;

	/**
	 * Longest accepted link address.
	 */
	const URL_MAX = 2048;

	/**
	 * Largest serialized size of all saved values for one site, in bytes.
	 */
	const VALUES_MAX_BYTES = 1048576;

	/**
	 * Fields registered during the current request.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static $fields = array();

	/**
	 * Register a field for this request and return its resolved ID, schema, and current value.
	 *
	 * The ID is empty when the requested ID is invalid. Helpers then render the
	 * default value without edit metadata.
	 *
	 * @param string $type Field type.
	 * @param string $id   Stable field ID.
	 * @param array  $args Field settings.
	 * @return array{id: string, schema: array<string, mixed>, value: mixed}
	 */
	public static function register( $type, $id, $args = array() ) {
		$args   = is_array( $args ) ? $args : array();
		$id     = self::resolve_id( $id, $args );
		$schema = self::build_schema( $type, $id, $args );

		if ( '' === $id ) {
			return array(
				'id'     => '',
				'schema' => $schema,
				'value'  => $schema['default'],
			);
		}

		self::$fields[ $id ] = $schema;
		// Only people who can edit need the saved schema (it checks their saves), so visitors never read or write it.
		if ( Pencil_Plugin::can_edit() ) {
			self::persist_schema( $id, $schema );
		}

		return array(
			'id'     => $id,
			'schema' => $schema,
			'value'  => self::get_current_value( $id ),
		);
	}

	/**
	 * Return a persisted field schema.
	 *
	 * @param string $id Stable field ID.
	 * @return array<string, mixed>|null
	 */
	public static function get_schema( $id ) {
		$id = self::validate_id( $id );

		if ( '' === $id ) {
			return null;
		}

		if ( isset( self::$fields[ $id ] ) ) {
			return self::$fields[ $id ];
		}

		$registry = get_option( 'pencil_field_registry', array() );

		if ( ! is_array( $registry ) || ! isset( $registry[ $id ] ) || ! is_array( $registry[ $id ] ) ) {
			return null;
		}

		return $registry[ $id ];
	}

	/**
	 * Return the current saved value or the registered theme default.
	 *
	 * @param string $id Stable field ID.
	 * @return mixed Null when the field is unknown.
	 */
	public static function get_current_value( $id ) {
		$schema = self::get_schema( $id );

		if ( ! $schema ) {
			return null;
		}

		$values = self::get_values();

		if ( array_key_exists( $id, $values ) ) {
			return self::cast( $schema['type'], $values[ $id ] );
		}

		return self::cast( $schema['type'], isset( $schema['default'] ) ? $schema['default'] : null );
	}

	/**
	 * Validate and save a submitted field value.
	 *
	 * @param string $id    Stable field ID.
	 * @param mixed  $value Submitted value.
	 * @return mixed|WP_Error Saved value.
	 */
	public static function save( $id, $value ) {
		$schema = self::get_schema( $id );

		if ( ! $schema || ! in_array( $schema['type'], self::TYPES, true ) ) {
			return new WP_Error(
				'pencil_unknown_field',
				__( 'This content field is no longer available.', 'pencilino' ),
				array( 'status' => 404 )
			);
		}

		$value = self::validate_value( $schema, $value );

		if ( is_wp_error( $value ) ) {
			return $value;
		}

		$values        = self::get_values();
		$values[ $id ] = $value;

		if ( strlen( maybe_serialize( $values ) ) > self::VALUES_MAX_BYTES ) {
			return new WP_Error(
				'pencil_storage_full',
				__( 'There is no room for more content. Shorten some texts and try again.', 'pencilino-by-rino' ),
				array( 'status' => 413 )
			);
		}

		if ( false === get_option( 'pencil_values', false ) ) {
			add_option( 'pencil_values', $values, '', 'no' );
		} else {
			update_option( 'pencil_values', $values, false );
		}

		return $value;
	}

	/**
	 * Sanitize rich text to the restricted Pencilino formatting set.
	 *
	 * @param mixed $value Raw HTML or plain text.
	 * @return string
	 */
	public static function sanitize_richtext( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		// Strip tags first, so text left over from removed blocks is still wrapped in paragraphs.
		$value = wpautop( wp_kses( $value, self::richtext_tags() ) );

		// Normalise link targets, e.g. "example.com" becomes "http://example.com".
		$value = preg_replace_callback(
			'/href="([^"]*)"/i',
			static function ( $matches ) {
				return 'href="' . esc_url( wp_specialchars_decode( $matches[1], ENT_QUOTES ), self::allowed_protocols() ) . '"';
			},
			$value
		);

		// Drop paragraphs left empty by the browser editor.
		$value = preg_replace( '#<p>(?:\s|&nbsp;|<br\s*/?>)*</p>#i', '', $value );

		return trim( $value );
	}

	/**
	 * Link protocols allowed in rich text and buttons. Relative links always work.
	 *
	 * @return string[]
	 */
	public static function allowed_protocols() {
		/**
		 * Filter the link protocols editors may use.
		 *
		 * @param string[] $protocols Protocols without the colon.
		 */
		return (array) apply_filters( 'pencil_allowed_protocols', array( 'http', 'https', 'mailto', 'tel', 'sms' ) );
	}

	/**
	 * HTML allowed in rich text fields.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function richtext_tags() {
		return array(
			'p'      => array(),
			'br'     => array(),
			'strong' => array(),
			'b'      => array(),
			'em'     => array(),
			'i'      => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'a'      => array( 'href' => true ),
		);
	}

	/**
	 * Return frontend data for an image attachment.
	 *
	 * @param int    $attachment_id Image attachment ID.
	 * @param string $size          Registered WordPress image size.
	 * @return array<string, int|string>|null
	 */
	public static function get_image_data( $attachment_id, $size = 'full' ) {
		$attachment_id = absint( $attachment_id );
		$image         = wp_get_attachment_image_src( $attachment_id, $size );

		if ( ! $image ) {
			return null;
		}

		return array(
			'id'     => $attachment_id,
			'url'    => $image[0],
			'width'  => absint( $image[1] ),
			'height' => absint( $image[2] ),
			'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, $size ),
			'sizes'  => (string) wp_get_attachment_image_sizes( $attachment_id, $size ),
			'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Validate a field ID and apply its optional page scope.
	 *
	 * With `'scope' => 'page'`, the ID is prefixed with the current page, so
	 * pages that share a template keep separate content.
	 *
	 * @param string $id   Stable field ID.
	 * @param array  $args Field settings.
	 * @return string Empty when invalid.
	 */
	private static function resolve_id( $id, $args ) {
		$id = self::validate_id( $id );

		if ( '' === $id || empty( $args['scope'] ) || 'page' !== $args['scope'] ) {
			return $id;
		}

		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : get_queried_object_id();

		if ( ! $post_id ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'A page-scoped Pencilino field was rendered without a page. It is stored as a site-wide field instead.', 'pencilino' ),
				esc_html( PENCIL_VERSION )
			);

			return $id;
		}

		return 'page-' . $post_id . '.' . $id;
	}

	/**
	 * Validate a field ID.
	 *
	 * @param string $id Proposed field ID.
	 * @return string Empty when invalid.
	 */
	private static function validate_id( $id ) {
		$id = (string) $id;

		if ( ! preg_match( '/^[a-z0-9][a-z0-9._-]*$/', $id ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Pencilino field IDs may contain lowercase letters, numbers, dots, dashes, and underscores.', 'pencilino' ),
				esc_html( PENCIL_VERSION )
			);

			return '';
		}

		return $id;
	}

	/**
	 * Build a sanitized field schema from theme arguments.
	 *
	 * @param string $type Field type.
	 * @param string $id   Resolved field ID.
	 * @param array  $args Field settings.
	 * @return array<string, mixed>
	 */
	private static function build_schema( $type, $id, $args ) {
		$schema = array(
			'type'  => $type,
			'label' => isset( $args['label'] ) ? sanitize_text_field( $args['label'] ) : $id,
		);

		switch ( $type ) {
			case 'richtext':
				$schema['default']    = self::sanitize_richtext( isset( $args['default'] ) ? $args['default'] : '' );
				$schema['max_length'] = isset( $args['max_length'] ) ? absint( $args['max_length'] ) : 0;
				break;

			case 'image':
				$schema['default'] = isset( $args['default'] ) ? absint( $args['default'] ) : 0;
				$schema['size']    = isset( $args['size'] ) ? sanitize_key( $args['size'] ) : 'full';
				break;

			case 'button':
				$default              = isset( $args['default'] ) && is_array( $args['default'] ) ? $args['default'] : array();
				$schema['default']    = array(
					'text' => isset( $default['text'] ) ? sanitize_text_field( $default['text'] ) : '',
					'url'  => isset( $default['url'] ) ? esc_url_raw( $default['url'] ) : '',
				);
				$schema['max_length'] = isset( $args['max_length'] ) ? max( 1, absint( $args['max_length'] ) ) : 40;
				break;

			default:
				$schema['type']       = 'text';
				$schema['default']    = isset( $args['default'] ) ? sanitize_text_field( $args['default'] ) : '';
				$schema['max_length'] = isset( $args['max_length'] ) ? max( 1, absint( $args['max_length'] ) ) : 160;
		}

		return $schema;
	}

	/**
	 * Validate a submitted value against its schema.
	 *
	 * @param array<string, mixed> $schema Field schema.
	 * @param mixed                $value  Submitted value.
	 * @return mixed|WP_Error Sanitized value.
	 */
	private static function validate_value( $schema, $value ) {
		switch ( $schema['type'] ) {
			case 'image':
				$attachment_id = absint( $value );

				if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
					return new WP_Error(
						'pencil_invalid_image',
						__( 'Choose an image from the Media Library.', 'pencilino' ),
						array( 'status' => 400 )
					);
				}

				return $attachment_id;

			case 'richtext':
				$value      = self::sanitize_richtext( is_scalar( $value ) ? $value : '' );
				$max_length = ! empty( $schema['max_length'] ) ? absint( $schema['max_length'] ) : self::RICHTEXT_DEFAULT_MAX;

				return self::check_length( wp_strip_all_tags( $value ), $max_length )
					? $value
					: self::length_error( $max_length );

			case 'button':
				$value = is_array( $value ) ? $value : array();
				$text  = isset( $value['text'] ) && is_scalar( $value['text'] ) ? sanitize_text_field( (string) $value['text'] ) : '';
				$raw   = isset( $value['url'] ) && is_scalar( $value['url'] ) ? trim( (string) $value['url'] ) : '';
				$url   = strlen( $raw ) <= self::URL_MAX ? esc_url_raw( $raw, self::allowed_protocols() ) : '';

				if ( '' === $text ) {
					return new WP_Error(
						'pencil_button_text_required',
						__( 'Add the button text.', 'pencilino' ),
						array( 'status' => 400 )
					);
				}

				if ( '' === $url ) {
					return new WP_Error(
						'pencil_button_url_required',
						__( 'Add a valid link, such as https://example.com or /contact.', 'pencilino' ),
						array( 'status' => 400 )
					);
				}

				$max_length = isset( $schema['max_length'] ) ? absint( $schema['max_length'] ) : 40;

				if ( ! self::check_length( $text, $max_length ) ) {
					return self::length_error( $max_length );
				}

				return array(
					'text' => $text,
					'url'  => $url,
				);

			default:
				$value      = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
				$max_length = isset( $schema['max_length'] ) ? absint( $schema['max_length'] ) : 160;

				return self::check_length( $value, $max_length ) ? $value : self::length_error( $max_length );
		}
	}

	/**
	 * Whether text fits a maximum length. A maximum of 0 means no limit.
	 *
	 * @param string $text       Plain text.
	 * @param int    $max_length Maximum number of characters.
	 * @return bool
	 */
	private static function check_length( $text, $max_length ) {
		if ( ! $max_length ) {
			return true;
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text ) : strlen( $text );

		return $length <= $max_length;
	}

	/**
	 * Return a length validation error.
	 *
	 * @param int $max_length Maximum number of characters.
	 * @return WP_Error
	 */
	private static function length_error( $max_length ) {
		return new WP_Error(
			'pencil_value_too_long',
			sprintf(
				/* translators: %d: Maximum number of characters. */
				__( 'Keep this text to %d characters or fewer.', 'pencilino' ),
				absint( $max_length )
			),
			array( 'status' => 400 )
		);
	}

	/**
	 * Cast a stored value to its field type.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Stored value.
	 * @return mixed
	 */
	private static function cast( $type, $value ) {
		switch ( $type ) {
			case 'image':
				return absint( $value );

			case 'button':
				$value = is_array( $value ) ? $value : array();

				return array(
					'text' => isset( $value['text'] ) ? (string) $value['text'] : '',
					'url'  => isset( $value['url'] ) ? (string) $value['url'] : '',
				);

			default:
				return is_scalar( $value ) ? (string) $value : '';
		}
	}

	/**
	 * Return all saved Pencilino values.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_values() {
		$values = get_option( 'pencil_values', array() );

		return is_array( $values ) ? $values : array();
	}

	/**
	 * Persist validation data so REST saves can validate rendered fields.
	 *
	 * @param string $id     Stable field ID.
	 * @param array  $schema Field schema.
	 * @return void
	 */
	private static function persist_schema( $id, $schema ) {
		$registry = get_option( 'pencil_field_registry', array() );
		$registry = is_array( $registry ) ? $registry : array();

		if ( isset( $registry[ $id ] ) && $schema === $registry[ $id ] ) {
			return;
		}

		$registry[ $id ] = $schema;

		if ( false === get_option( 'pencil_field_registry', false ) ) {
			add_option( 'pencil_field_registry', $registry, '', 'no' );
		} else {
			update_option( 'pencil_field_registry', $registry, false );
		}
	}
}
