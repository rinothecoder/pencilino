<?php
/**
 * Pencilino admin page: the menu, the assets and the tab shell. Each tab has its own class in includes/admin/.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin {
	const MENU_SLUG = 'pencilino';

	/**
	 * Admin page hook suffix.
	 *
	 * @var string
	 */
	private static $page_hook = '';

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'redirect_legacy_slug' ), 1 );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Older bookmarks and links used the pre-rename page slug. Send them to the current one.
	 *
	 * Runs early on init, before WordPress checks access to the requested admin page.
	 *
	 * @return void
	 */
	public static function redirect_legacy_slug() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect of a page slug.
		if ( ! is_admin() || 'admin.php' !== ( $GLOBALS['pagenow'] ?? '' ) || ! isset( $_GET['page'] ) || 'pencil-by-rino' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'page', self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Add the Pencilino top-level menu.
	 *
	 * The icon is passed as a data URI so WordPress recolours it to match the
	 * user's admin colour scheme.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! Pencil_Client::can_manage() ) {
			return;
		}
		$svg  = file_get_contents( PENCIL_PLUGIN_PATH . 'admin/img/pencil-icon-white.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$icon = 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		self::$page_hook = add_menu_page(
			__( 'Pencilino', 'pencilino' ),
			__( 'Pencilino', 'pencilino' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_page' ),
			$icon,
			58
		);
	}

	/**
	 * Load styles and scripts only on the Pencilino admin page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( self::$page_hook !== $hook_suffix ) {
			return;
		}


		wp_enqueue_style(
			'pencil-admin',
			PENCIL_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			Pencil_Plugin::asset_version( 'admin/css/admin.css' )
		);
		wp_enqueue_script(
			'pencil-admin',
			PENCIL_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			Pencil_Plugin::asset_version( 'admin/js/admin.js' ),
			true
		);
		if ( Pencil_Client::can_manage() ) {
			wp_enqueue_script( 'pencil-focus', PENCIL_PLUGIN_URL . 'admin/js/focus.js', array(), Pencil_Plugin::asset_version( 'admin/js/focus.js' ), true );
			wp_localize_script( 'pencil-focus', 'pencilFocus', array( 'remove' => __( 'Remove', 'pencilino' ), 'one' => __( 'user has focus mode', 'pencilino' ), 'many' => __( 'users have focus mode', 'pencilino' ), 'noMatch' => __( 'No users found', 'pencilino' ), 'more' => __( 'more. Keep typing to narrow it down.', 'pencilino' ), 'error' => __( 'Could not save. Please try again.', 'pencilino' ) ) );
		}
	}

	/**
	 * Render the page. Every tab is in the DOM; JS switches between them.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! Pencil_Client::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'pencilino' ) );
		}

		$tabs = array(
			'activity'    => __( 'Change log', 'pencilino' ),
			'about'       => __( 'About', 'pencilino' ),
			'get-started' => __( 'How to use', 'pencilino' ),
		);
		if ( Pencil_Client::can_manage() ) {
			$tabs = array(
				'activity'        => $tabs['activity'],
				'templates'       => __( 'Templates', 'pencilino' ),
				'ai-instructions' => __( 'AI instructions', 'pencilino' ),
				'settings'        => __( 'Features', 'pencilino' ),
				'about'           => $tabs['about'],
				'get-started'     => $tabs['get-started'],
			);
		}
		?>
		<div class="pencil-wrap">

			<header class="pencil-header">
				<div class="pencil-header__left">
					<img src="<?php echo esc_url( add_query_arg( 'ver', filemtime( PENCIL_PLUGIN_PATH . 'admin/img/pencilino-icon-color.svg' ), PENCIL_PLUGIN_URL . 'admin/img/pencilino-icon-color.svg' ) ); ?>" alt="" aria-hidden="true" class="pencil-header__icon" width="32" height="32">
					<h1 class="pencil-header__title"><?php esc_html_e( 'Pencilino', 'pencilino' ); ?></h1>
					<span class="pencil-header__version">v<?php echo esc_html( PENCIL_VERSION ); ?></span>
				</div>
				<nav class="pencil-header__nav">
					<?php foreach ( $tabs as $slug => $label ) : ?>
						<a href="#<?php echo esc_attr( $slug ); ?>" data-tab="<?php echo esc_attr( $slug ); ?>" class="pencil-header__link">
							<?php echo esc_html( $label ); ?>
						</a>
					<?php endforeach; ?>
					<a class="pencil-header__open" href="<?php echo esc_url( add_query_arg( 'pencil-edit', '1', home_url( '/' ) ) ); ?>">
						<?php esc_html_e( 'Open Pencilino', 'pencilino' ); ?>
					</a>
				</nav>
			</header>
			<hr class="wp-header-end">

			<div data-tab-panel="activity"><?php Pencil_Admin_Changelog::render(); ?></div>
			<?php if ( Pencil_Client::can_manage() ) : ?><div data-tab-panel="templates" hidden><?php Pencil_Admin_Templates::render(); ?></div><?php endif; ?>
			<?php if ( Pencil_Client::can_manage() ) : ?><div data-tab-panel="settings" hidden><?php Pencil_Admin_Features::render(); ?></div><?php endif; ?>
			<?php if ( Pencil_Client::can_manage() ) : ?><div data-tab-panel="ai-instructions" hidden><?php Pencil_AI_Instructions::render(); ?></div><?php endif; ?>
			<div data-tab-panel="get-started" hidden><?php Pencil_Admin_Get_Started::render(); ?></div>
			<div data-tab-panel="about" hidden><?php Pencil_Admin_About::render(); ?></div>

		</div>
		<?php
	}
}
