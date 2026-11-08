<?php
/**
 * Pencilino abilities, so AI agents can read client activity and feedback through MCP.
 *
 * Deliberately read and resolve only. Agents change the theme itself, so the
 * activity log keeps showing what people did in the frontend editor.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Abilities {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Same audience as the editor and the comment REST routes.
	 *
	 * @return bool
	 */
	public static function can_view() {
		return Pencil_Plugin::can_edit();
	}

	/**
	 * Same capability as the REST route that resolves comments.
	 *
	 * @return bool
	 */
	public static function can_resolve() {
		return Pencil_Client::can_manage();
	}

	public static function can_read_instructions() {
		return Pencil_Client::can_manage();
	}

	public static function can_set_prefix() {
		return Pencil_Client::can_manage();
	}

	private static function meta( $readonly, $idempotent ) {
		return array(
			'public'       => true,
			'show_in_rest' => true,
			'mcp'          => array( 'public' => true ),
			'annotations'  => array(
				'readonly'    => $readonly,
				'destructive' => false,
				'idempotent'  => $idempotent,
			),
		);
	}

	private static function add( $name, $label, $description, $properties, $required, $meta, $permission ) {
		$schema = array(
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
		);
		if ( $required ) {
			$schema['required'] = $required;
		} else {
			$schema['default'] = array();
		}
		wp_register_ability(
			'pencilino/' . $name,
			array(
				'label'               => 'Pencilino: ' . $label,
				'description'         => $description,
				'category'            => 'site',
				'input_schema'        => $schema,
				'output_schema'       => array( 'type' => 'object' ),
				'execute_callback'    => array( __CLASS__, str_replace( '-', '_', $name ) ),
				'permission_callback' => array( __CLASS__, $permission ),
				'meta'                => $meta,
			)
		);
	}

	/**
	 * Register the abilities.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		self::add(
			'list-changes',
			'List Changes',
			'List the newest content edits made through the Pencilino frontend editor, with the field, the old and new value, who made the edit, and the page. Image values are attachment IDs and button values are JSON with text and url. Times are UTC. Edits an agent makes to the theme are not part of this log. Requires an administrator or editor.',
			array( 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50, 'description' => 'How many of the newest changes to return.' ) ),
			array(),
			self::meta( true, true ),
			'can_view'
		);
		self::add(
			'list-comments',
			'List Comments',
			'List the private element comments that people left on the frontend, 50 per page. Each comment has its page, the comment text, the author and a target: selector, Pencilino field ID, element ID, tag, label, a short text excerpt, and the x and y position of the pin (0 to 1). Check the target data to find the element a comment is about. Requires an administrator or editor.',
			array(
				'status' => array( 'type' => 'string', 'enum' => array( 'open', 'resolved' ), 'default' => 'open', 'description' => 'open (default) or resolved.' ),
				'page'   => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
			),
			array(),
			self::meta( true, true ),
			'can_view'
		);
		self::add(
			'list-templates',
			'List Templates',
			'List the template files of the active theme and where each one is used: the pages that use a page template, the front page, specific pages, post types and archives. The list is read from the theme folder, so a new file appears straight away. Requires an administrator or editor.',
			array( 'pages_limit' => array( 'type' => 'integer', 'minimum' => 0, 'maximum' => 200, 'default' => 10, 'description' => 'How many pages to list per template. pages_total always holds the full count.' ) ),
			array(),
			self::meta( true, true ),
			'can_view'
		);
		$slugs   = array_keys( Pencil_AI_Instructions::sections() );
		$section = array( 'type' => 'string', 'description' => 'Optional. Return one section only. Leave it out to get every section.' );
		if ( $slugs ) {
			$section['enum'] = $slugs;
		}
		self::add(
			'get-instructions',
			'Get Instructions',
			'Read the Pencilino instructions for building and changing themes. Read them in full before you build or change a theme, and follow them. Do this every time, also for a theme that already exists: the instructions change over time, so bring the parts you work on in line with them and say what you changed. They come in sections. Without a section you get all of them as one text. The site owner can add their own instructions to a section: those follow the default text of that section and win when they conflict with it. The design system section holds the default design basics and the rules for the prefix; the framework field of the result tells you the prefix that is set now. Requires manage_options.',
			array( 'section' => $section ),
			array(),
			self::meta( true, true ),
			'can_read_instructions'
		);
		self::add(
			'set-prefix',
			'Set Prefix',
			'Tell Pencilino which prefix the framework classes and variables of this site use, for example pcl- or rdb-. Call it after you build or change a theme, so Dev mode can tell framework classes from classes that were added later. Only letters, numbers, hyphens and underscores, up to 32 characters. An empty string clears it. Requires manage_options.',
			array(
				'prefix' => array(
					'type'        => 'string',
					'description' => 'The prefix, for example pcl-.',
					'maxLength'   => 32,
				),
			),
			array( 'prefix' ),
			self::meta( false, true ),
			'can_set_prefix'
		);
		self::add(
			'resolve-comment',
			'Resolve Comment',
			'Mark a comment as resolved, or reopen it with resolved set to false. Only the status changes, never the comment itself. Requires manage_options.',
			array(
				'id'       => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Comment ID, as returned by list-comments.' ),
				'resolved' => array( 'type' => 'boolean', 'default' => true ),
			),
			array( 'id' ),
			self::meta( false, true ),
			'can_resolve'
		);
	}

	public static function list_changes( $input ) {
		$rows    = Pencil_Activity::get_recent( isset( $input['limit'] ) ? (int) $input['limit'] : 50 );
		$changes = array();
		foreach ( $rows as $row ) {
			$url       = $row->page_id ? get_permalink( (int) $row->page_id ) : '';
			$changes[] = array(
				'id'             => (int) $row->activity_id,
				'field_id'       => $row->field_id,
				'field_label'    => $row->field_label,
				'field_type'     => $row->field_type,
				'old_value'      => $row->old_value,
				'new_value'      => $row->new_value,
				'user_id'        => (int) $row->user_id,
				'user_name'      => $row->user_name,
				'page_id'        => (int) $row->page_id,
				'page_url'       => $url ? $url : '',
				'changed_at_utc' => $row->created_at,
			);
		}
		return array(
			'count'   => count( $changes ),
			'changes' => $changes,
		);
	}

	public static function list_comments( $input ) {
		$resolved = isset( $input['status'] ) && 'resolved' === $input['status'];
		$page     = isset( $input['page'] ) ? max( 1, (int) $input['page'] ) : 1;
		$rows     = Pencil_Comments::inbox( $resolved, $page );
		$has_more = count( $rows ) > 50;
		$comments = array();
		foreach ( array_slice( $rows, 0, 50 ) as $row ) {
			$item                    = Pencil_Comments::serialise( $row );
			$item['page_id']         = (int) $row->page_id;
			$item['resolved_at_utc'] = $row->resolved_at;
			$comments[]              = $item;
		}
		return array(
			'status'   => $resolved ? 'resolved' : 'open',
			'page'     => $page,
			'has_more' => $has_more,
			'comments' => $comments,
		);
	}

	public static function list_templates( $input ) {
		$limit     = isset( $input['pages_limit'] ) ? (int) $input['pages_limit'] : 10;
		$templates = Pencil_Templates::get_all( $limit );
		return array(
			'theme'     => wp_get_theme()->get_stylesheet(),
			'count'     => count( $templates ),
			'templates' => $templates,
		);
	}

	public static function get_instructions( $input ) {
		$overview = Pencil_AI_Instructions::overview();
		$slug     = isset( $input['section'] ) ? sanitize_key( $input['section'] ) : '';
		if ( '' !== $slug ) {
			foreach ( $overview as $item ) {
				if ( $item['slug'] === $slug ) {
					return array(
						'section' => $item,
						'text'    => Pencil_AI_Instructions::section_text( $slug ),
				'framework' => self::framework_info(),
					);
				}
			}
			return new WP_Error( 'pencilino_unknown_section', 'Unknown section. Use one of: ' . implode( ', ', wp_list_pluck( $overview, 'slug' ) ) . '.' );
		}
		return array(
			'version'  => PENCIL_VERSION,
			'sections' => $overview,
			'text'     => Pencil_AI_Instructions::full_text(),
			'framework' => self::framework_info(),
		);
	}


	/** The prefix that is set now, so an agent knows it without reading the text. */
	private static function framework_info() {
		return array(
			'prefix' => Pencil_Dev::prefix(),
		);
	}

	public static function set_prefix( $input ) {
		Pencil_Dev::save_prefix( isset( $input['prefix'] ) ? (string) $input['prefix'] : '' );
		return array( 'prefix' => Pencil_Dev::prefix() );
	}

	public static function resolve_comment( $input ) {
		$id       = absint( $input['id'] );
		$resolved = isset( $input['resolved'] ) ? (bool) $input['resolved'] : true;
		$result   = Pencil_Comments::set_resolved( $id, $resolved );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array(
			'id'       => $id,
			'resolved' => $resolved,
		);
	}
}
