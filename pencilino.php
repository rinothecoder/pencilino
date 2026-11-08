<?php
/**
 * Plugin Name: Pencilino
 * Description: Frontend content editing for AI-built WordPress themes. The theme owns the design, your client edits the words and images.
 * Version: 2.2.0
 * Plugin URI: https://github.com/rinothecoder/pencilino
 * Author: Rino de Boer
 * Text Domain: pencilino
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

/*
 * Older copies of this plugin (the first Pencil by Rino and Pencilino 2.0.x in the pencilino-by-rino
 * folder) use the same class names, so they cannot run together with this one.
 * When one of them is still active, pause Pencilino and ask the site owner to deactivate it.
 */
if ( defined( 'PENCIL_VERSION' ) || class_exists( 'Pencil_Admin', false ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$mine = plugin_basename( __FILE__ );
			$old  = '';
			foreach ( (array) get_option( 'active_plugins', array() ) as $active ) {
				if ( $active !== $mine && preg_match( '#^(pencil-by-rino|pencilino-by-rino)/#', $active ) ) {
					$old = $active;
					break;
				}
			}

			if ( '' === $old ) {
				printf(
					'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p></div>',
					esc_html__( 'Pencilino is paused.', 'pencilino' ),
					esc_html__( 'Another copy of this plugin is already running. Deactivate it and Pencilino starts working.', 'pencilino' )
				);
				return;
			}

			$plugins = get_plugins();
			$name    = isset( $plugins[ $old ]['Name'] ) ? $plugins[ $old ]['Name'] : $old;
			$url     = wp_nonce_url( admin_url( 'plugins.php?action=deactivate&plugin=' . rawurlencode( $old ) ), 'deactivate-plugin_' . $old );
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a></p></div>',
				esc_html__( 'Pencilino is paused.', 'pencilino' ),
				esc_html(
					sprintf(
						/* translators: %s: name of the older plugin that is still active */
						__( 'An older version, "%s", is still active, and the two cannot run together. Deactivate it and Pencilino starts working. Your saved fields and change history stay as they are.', 'pencilino' ),
						$name
					)
				),
				esc_url( $url ),
				esc_html(
					sprintf(
						/* translators: %s: name of the older plugin that is still active */
						__( 'Deactivate %s', 'pencilino' ),
						$name
					)
				)
			);
		}
	);
	return;
}

define( 'PENCIL_VERSION', '2.2.0' );
define( 'PENCIL_PLUGIN_FILE', __FILE__ );
define( 'PENCIL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PENCIL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Ideas and feedback form, linked from the About tab.
define( 'PENCIL_FEEDBACK_URL', 'https://rinodeboer.fillout.com/pencilino' );

require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-fields.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-templates.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-activity.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-admin.php';
require_once PENCIL_PLUGIN_PATH . 'includes/admin/class-pencil-admin-ui.php';
require_once PENCIL_PLUGIN_PATH . 'includes/admin/class-pencil-admin-changelog.php';
require_once PENCIL_PLUGIN_PATH . 'includes/admin/class-pencil-admin-templates.php';
require_once PENCIL_PLUGIN_PATH . 'includes/admin/class-pencil-admin-features.php';
require_once PENCIL_PLUGIN_PATH . 'includes/admin/class-pencil-admin-get-started.php';
require_once PENCIL_PLUGIN_PATH . 'includes/admin/class-pencil-admin-about.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-plugin.php';
require_once PENCIL_PLUGIN_PATH . 'includes/template-functions.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-access.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-client.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-dev.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-spacing.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-classes.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-modal.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-comments.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-seo.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-abilities.php';
require_once PENCIL_PLUGIN_PATH . 'includes/class-pencil-ai-instructions.php';

register_activation_hook( PENCIL_PLUGIN_FILE, array( 'Pencil_Activity', 'install' ) );

Pencil_Activity::init();
Pencil_Admin::init();
Pencil_Plugin::init();
Pencil_Client::init();
Pencil_Dev::init();
Pencil_Spacing::init();
Pencil_Classes::init();
Pencil_Modal::init();
Pencil_Comments::init();
Pencil_SEO::init();
Pencil_Abilities::init();
Pencil_AI_Instructions::init();

