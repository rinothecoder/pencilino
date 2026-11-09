<?php
/**
 * Plugin Name: Pencilino by Rino
 * Description: Frontend content editing for AI-built WordPress themes. The theme owns the design, your client edits the words and images.
 * Version: 2.0.5
 * Plugin URI: https://github.com/rinothecoder/pencilino
 * Update URI: https://github.com/rinothecoder/pencilino/
 * Author: Rino de Boer
 * Text Domain: pencilino-by-rino
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'PENCIL_VERSION', '2.0.5' );
define( 'PENCIL_PLUGIN_FILE', __FILE__ );
define( 'PENCIL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PENCIL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Ideas and feedback form shown on the Release notes tab.
define( 'PENCIL_FEEDBACK_URL', 'https://rinodeboer.fillout.com/pencil-by-rino' );
define( 'PENCIL_SUPPORT_URL', 'https://github.com/rinothecoder/pencilino/issues' );

require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-fields.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-activity.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-admin.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-plugin.php';
require_once PENCIL_PLUGIN_PATH . 'includes/template-functions.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-client.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-comments.php';

register_activation_hook( PENCIL_PLUGIN_FILE, array( 'Pencil_Activity', 'install' ) );

Pencil_Activity::init();
Pencil_Admin::init();
Pencil_Plugin::init();
Pencil_Client::init();
Pencil_Comments::init();

require_once PENCIL_PLUGIN_PATH . 'includes/class-pencilino-updates.php';
Pencilino_Updates::boot();

/**
 * Pencilino has moved to WordPress.org. Tell site owners on the Dashboard and the Plugins screen.
 */
add_action(
	'admin_notices',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) || ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a class="button" href="%5$s" target="_blank" rel="noopener">%6$s</a></p></div>',
			esc_html__( 'Pencilino has moved to WordPress.org.', 'pencilino-by-rino' ),
			esc_html__( 'This version no longer gets new features. Install Pencilino from the plugin directory and activate it. It asks you to deactivate this older copy, and your content, change log and comments stay as they are.', 'pencilino-by-rino' ),
			esc_url( admin_url( 'plugin-install.php?s=pencilino&tab=search&type=term' ) ),
			esc_html__( 'Install Pencilino', 'pencilino-by-rino' ),
			esc_url( 'https://wordpress.org/plugins/pencilino/' ),
			esc_html__( 'View on WordPress.org', 'pencilino-by-rino' )
		);
	}
);
