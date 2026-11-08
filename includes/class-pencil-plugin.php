<?php
/**
 * Plugin bootstrap and editor interface.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Plugin {
	/**
	 * User meta key: show hidden states (elements with the pencil-reveal class) while editing.
	 */
	const SHOW_HIDDEN_META = 'pencil_show_hidden';

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'render_editor' ), 5 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Whether the current user chose to show hidden states.
	 *
	 * @return bool
	 */
	public static function show_hidden() {
		return (bool) get_user_meta( get_current_user_id(), self::SHOW_HIDDEN_META, true );
	}

	/**
	 * Add pencil-show-hidden to the body for editors who switched hidden states on.
	 *
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		if ( self::can_edit() && self::show_hidden() ) {
			$classes[] = 'pencil-show-hidden';
		}
		return $classes;
	}

	/**
	 * Whether the current visitor may use Pencilino.
	 *
	 * Administrators and Editors by default, or the roles chosen under Features, Access. Without a choice, Contributors and Authors are left out, as they have
	 * `edit_posts` but not `edit_pages`, so they are left out.
	 *
	 * @return bool
	 */
	public static function can_edit() {
		$can_edit = is_user_logged_in() && Pencil_Access::allows( wp_get_current_user() );

		/**
		 * Filter who may open Pencilino and save content.
		 *
		 * @param bool $can_edit Whether the current user may edit.
		 */
		return (bool) apply_filters( 'pencil_can_edit', apply_filters( 'pencilino_can_edit', $can_edit ) );
	}

	/**
	 * Register save endpoints.
	 *
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			'pencil/v1',
			'/fields/(?P<id>[a-z0-9._-]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'save_field' ),
				'permission_callback' => array( __CLASS__, 'can_edit' ),
				'args'                => array(
					'id'      => array(
						'required' => true,
					),
					'value'   => array(
						'required' => true,
					),
					'page_id' => array(
						'required'          => false,
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
				),
			)
		);
	}

	/**
	 * Save an editable field.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function save_field( WP_REST_Request $request ) {
		$id     = (string) $request->get_param( 'id' );
		$schema = Pencil_Fields::get_schema( $id );

		if ( ! $schema ) {
			return new WP_Error(
				'pencil_unknown_field',
				__( 'This content field is no longer available.', 'pencilino' ),
				array( 'status' => 404 )
			);
		}

		$old_value = Pencil_Fields::get_current_value( $id );
		$value     = Pencil_Fields::save( $id, $request->get_param( 'value' ) );

		if ( is_wp_error( $value ) ) {
			return $value;
		}

		Pencil_Activity::record(
			$id,
			$schema,
			$old_value,
			$value,
			absint( $request->get_param( 'page_id' ) )
		);

		$response = array(
			'id'    => $id,
			'type'  => $schema['type'],
			'value' => $value,
		);

		if ( 'image' === $schema['type'] ) {
			$response['image'] = Pencil_Fields::get_image_data(
				$value,
				isset( $schema['size'] ) ? $schema['size'] : 'full'
			);
		}

		return rest_ensure_response(
			$response
		);
	}

	/**
	 * Whether the browser says this page is loaded inside a frame, such as the Pencilino popup.
	 *
	 * @return bool
	 */
	public static function in_iframe() {
		return isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) && 'iframe' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ) );
	}

	/**
	 * Load editor-only assets for authorised users.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		// The popup must never open another popup, so the website editor stays out of frames.
		if ( self::in_iframe() ) {
			return;
		}
		if ( ! self::can_edit() ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'pencil-editor',
			PENCIL_PLUGIN_URL . 'assets/css/editor.css',
			array( 'dashicons' ),
			self::asset_version( 'assets/css/editor.css' )
		);

		wp_enqueue_script(
			'pencil-editor',
			PENCIL_PLUGIN_URL . 'assets/js/editor.js',
			array(),
			self::asset_version( 'assets/js/editor.js' ),
			true
		);

		wp_localize_script(
			'pencil-editor',
			'pencilEditor',
			array(
				'restUrl' => esc_url_raw( rest_url( 'pencil/v1/fields/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'pageId'  => get_queried_object_id(),
				'commentsUrl' => esc_url_raw( rest_url( 'pencil/v1/comments' ) ),
				'clientMode' => Pencil_Client::clean_admin(),
				'popupEditing' => Pencil_Modal::enabled(),
				// Swapping a region in place only suits themes that restart their own scripts, so a reload is the default.
				'regionSwap' => (bool) apply_filters( 'pencilino_region_swap', false ),
				'labels'  => array(
					'openPencil'  => __( 'Edit', 'pencilino' ),
					'closePencil' => Pencil_Dev::allowed() ? __( 'Edit', 'pencilino' ) : __( 'Done', 'pencilino' ),
					'saved'       => __( 'Saved', 'pencilino' ),
					'error'       => __( 'Something went wrong. Please try again.', 'pencilino' ),
					'chooseImage' => __( 'Choose image', 'pencilino' ),
					'useImage'    => __( 'Use image', 'pencilino' ),
					'postComment' => __( 'Send', 'pencilino' ),
					'commentSaved' => __( 'Comment posted', 'pencilino' ),
				'commentEdited' => __( 'Comment updated', 'pencilino' ),
				'statusOpen' => __( 'Open', 'pencilino' ),
				'statusResolved' => __( 'Resolved', 'pencilino' ),
				'editedLabel' => __( 'edited', 'pencilino' ),
				'deleteLabel' => __( 'Delete', 'pencilino' ),
				'deleteSure' => __( 'Click again to delete', 'pencilino' ),
				'commentDeleted' => __( 'Comment deleted', 'pencilino' ),
					'writeComment' => __( 'Write a comment first.', 'pencilino' ),
					'saveChanges' => __( 'Save', 'pencilino' ),
					'section' => __( 'Page section', 'pencilino' ),
					'locationMissing' => __( 'The original element has changed. This comment is still saved with its page context.', 'pencilino' ),
					'selectText'  => __( 'Select the text you want to turn into a link first.', 'pencilino' ),
					'editContent' => __( 'Edit content', 'pencilino' ),
					'closeEditor' => __( 'Close', 'pencilino' ),
					'save' => __( 'Save', 'pencilino' ),
				'editAdmin' => __( 'Edit in the editor', 'pencilino' ),
				'editManaged' => Pencil_Modal::enabled() ? __( 'Edit this content', 'pencilino' ) : __( 'Editable in the editor', 'pencilino' ),
					'loadingEditor' => __( 'Loading editor...', 'pencilino' ),
					'updating' => __( 'Updating...', 'pencilino' ),
					'discardChanges' => __( 'You have unsaved changes. Close without saving?', 'pencilino' ),
			'openAdmin' => __( 'Open in dashboard', 'pencilino' ),
				),
			)
		);
	}

	/**
	 * Render the minimal editor interface.
	 *
	 * @return void
	 */
	public static function render_editor() {
		if ( self::in_iframe() ) {
			return;
		}
		if ( ! self::can_edit() ) {
			return;
		}
		?>
		<div class="pencil-ui pencil-toolbar" data-pencil-toolbar hidden>
			<button class="pencil-toolbar__select" type="button" data-pencil-toggle aria-pressed="false">
				<span data-pencil-toggle-label><?php esc_html_e( 'Edit', 'pencilino' ); ?></span>
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
			</button>
		</div>

		<div class="pencil-ui pencil-status" data-pencil-status role="status" aria-live="polite" hidden></div>

		<div class="pencil-ui pencil-highlight" data-pencil-highlight hidden></div>

		<div
			class="pencil-ui pencil-popup"
			data-pencil-popup
			role="dialog"
			aria-labelledby="pencil-popup-title"
			hidden
		>
			<form data-pencil-form>
				<div class="pencil-popup__label" id="pencil-popup-title" data-pencil-popup-label></div>
				<div class="pencil-popup__header">
					<div class="pencil-popup__switch" role="tablist" aria-label="<?php esc_attr_e( 'Selected element action', 'pencilino' ); ?>">
						<button type="button" role="tab" aria-selected="true" data-pencil-mode="edit"><span class="dashicons dashicons-edit" aria-hidden="true"></span><?php esc_html_e( 'Edit', 'pencilino' ); ?></button>
						<button type="button" role="tab" aria-selected="false" data-pencil-mode="comment"><span class="dashicons dashicons-admin-comments" aria-hidden="true"></span><?php esc_html_e( 'Comment', 'pencilino' ); ?></button>
					</div>
					<button class="pencil-popup__close" type="button" data-pencil-cancel aria-label="<?php esc_attr_e( 'Close', 'pencilino' ); ?>"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
				</div>
				<div class="pencil-popup__comment" data-pencil-comment-editor hidden>
					<textarea class="pencil-popup__input" rows="4" maxlength="5000" placeholder="<?php esc_attr_e( 'Write a comment', 'pencilino' ); ?>" aria-label="<?php esc_attr_e( 'Comment', 'pencilino' ); ?>" data-pencil-comment-input></textarea>
					<p class="pencil-popup__hint"><?php esc_html_e( 'Private feedback for your site team. This does not change the page.', 'pencilino' ); ?></p>
				</div>
				<div class="pencil-popup__managed" data-pencil-managed-editor hidden>
					<p class="pencil-popup__hint pencil-popup__noaccess" data-pencil-popup-noaccess role="status" hidden><?php esc_html_e( 'You do not have access to edit this. It is managed by another plugin. Ask an administrator if you need to change it.', 'pencilino' ); ?></p>
					<a class="pencil-popup__managed-field" data-pencil-popup-managed-link href="#"><span data-pencil-popup-managed-text><?php esc_html_e( 'Edit this content', 'pencilino' ); ?></span><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>
				</div>
				<div class="pencil-popup__comment pencil-popup__detail" data-pencil-comment-detail hidden>
					<div class="pencil-popup__who"><span class="pencil-popup__avatar" data-pencil-comment-avatar aria-hidden="true"></span><span><strong data-pencil-comment-author></strong><small data-pencil-comment-meta></small></span></div>
					<p data-pencil-comment-body class="pencil-popup__note"></p>
					<div class="pencil-popup__edit" data-pencil-comment-edit-form hidden>
						<textarea class="pencil-popup__input" rows="4" maxlength="5000" aria-label="<?php esc_attr_e( 'Edit your comment', 'pencilino' ); ?>" data-pencil-comment-edit-input></textarea>
						<div class="pencil-popup__edit-actions"><button type="button" class="pencil-popup__button pencil-popup__button--quiet" data-pencil-comment-delete><?php esc_html_e( 'Delete', 'pencilino' ); ?></button><button type="button" class="pencil-popup__button pencil-popup__button--primary" data-pencil-comment-edit-save><?php esc_html_e( 'Save', 'pencilino' ); ?></button></div>
					</div>
					<button type="button" class="pencil-popup__link" data-pencil-comment-edit hidden><?php esc_html_e( 'Edit', 'pencilino' ); ?></button>
				</div>
				<div data-pencil-text-editor>
					<textarea class="pencil-popup__input" id="pencil-popup-value" name="value" rows="1" wrap="soft" autocomplete="off" aria-labelledby="pencil-popup-title" data-pencil-input></textarea>
				</div>
				<div class="pencil-popup__richtext-editor" data-pencil-richtext-editor hidden>
					<div class="pencil-popup__toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Formatting', 'pencilino' ); ?>">
						<?php
						$tools = array(
							'bold'                => array( 'editor-bold', __( 'Bold', 'pencilino' ) ),
							'italic'              => array( 'editor-italic', __( 'Italic', 'pencilino' ) ),
							'link'                => array( 'admin-links', __( 'Link', 'pencilino' ) ),
							'insertUnorderedList' => array( 'editor-ul', __( 'Bulleted list', 'pencilino' ) ),
							'insertOrderedList'   => array( 'editor-ol', __( 'Numbered list', 'pencilino' ) ),
						);

						foreach ( $tools as $command => $tool ) :
							?>
							<button class="pencil-popup__tool" type="button" data-pencil-command="<?php echo esc_attr( $command ); ?>" aria-label="<?php echo esc_attr( $tool[1] ); ?>" title="<?php echo esc_attr( $tool[1] ); ?>" aria-pressed="false">
								<span class="dashicons dashicons-<?php echo esc_attr( $tool[0] ); ?>" aria-hidden="true"></span>
							</button>
						<?php endforeach; ?>
					</div>
					<div class="pencil-popup__richtext" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="pencil-popup-title" data-pencil-richtext-input></div>
					<div class="pencil-popup__link" data-pencil-link-row hidden>
						<input class="pencil-popup__input" type="text" autocomplete="off" inputmode="url" placeholder="<?php esc_attr_e( 'https://example.com or /contact', 'pencilino' ); ?>" aria-label="<?php esc_attr_e( 'Link address', 'pencilino' ); ?>" data-pencil-link-input>
						<button class="pencil-popup__button pencil-popup__button--quiet" type="button" data-pencil-link-apply>
							<?php esc_html_e( 'Apply', 'pencilino' ); ?>
						</button>
					</div>
				</div>
				<div class="pencil-popup__button-editor" data-pencil-button-editor hidden>
					<label class="pencil-popup__field">
						<span class="pencil-popup__field-label"><?php esc_html_e( 'Text', 'pencilino' ); ?></span>
						<input class="pencil-popup__input" type="text" autocomplete="off" data-pencil-button-text>
					</label>
					<label class="pencil-popup__field">
						<span class="pencil-popup__field-label"><?php esc_html_e( 'Link', 'pencilino' ); ?></span>
						<input class="pencil-popup__input" type="text" autocomplete="off" inputmode="url" placeholder="<?php esc_attr_e( 'https://example.com or /contact', 'pencilino' ); ?>" data-pencil-button-url>
					</label>
				</div>
				<div class="pencil-popup__image-editor" data-pencil-image-editor hidden>
					<div class="pencil-popup__image-preview">
						<img src="" alt="" data-pencil-image-preview>
					</div>
					<button class="pencil-popup__replace" type="button" data-pencil-replace-image>
						<?php esc_html_e( 'Replace image', 'pencilino' ); ?>
					</button>
				</div>
				<p class="pencil-popup__error" data-pencil-error hidden></p>
				<div class="pencil-popup__actions">
					<button class="pencil-popup__button pencil-popup__button--primary" type="submit" data-pencil-save>
						<?php esc_html_e( 'Save', 'pencilino' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Return a cache-busting asset version.
	 *
	 * @param string $relative_path Plugin-relative path.
	 * @return string
	 */
	public static function asset_version( $relative_path ) {
		$path = PENCIL_PLUGIN_PATH . $relative_path;

		return file_exists( $path ) ? (string) filemtime( $path ) : PENCIL_VERSION;
	}
}
