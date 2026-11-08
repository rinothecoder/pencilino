<?php
/**
 * Access: which user roles may use Pencilino.
 *
 * Out of the box Administrators and Editors may use it. Once the list is saved, only the chosen roles may.
 * Administrators always may, so nobody locks themselves out. A chosen role gets access whatever page permissions it has.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Access {

	const OPTION = 'pencil_access_roles';

	/** The roles that can be chosen: every role on this site except Administrator. Slug => name. */
	public static function eligible_roles() {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			if ( 'administrator' !== $slug ) {
				$out[ $slug ] = translate_user_role( $role['name'] );
			}
		}
		return $out;
	}

	/** The saved list of roles, or null while nothing is saved (then only Editor, next to Administrator). */
	public static function saved_roles() {
		$saved = get_option( self::OPTION, null );
		return is_array( $saved ) ? array_values( array_map( 'sanitize_key', $saved ) ) : null;
	}

	/** Whether this role may use Pencilino. */
	public static function role_allowed( $slug ) {
		$saved = self::saved_roles();
		return null === $saved ? 'editor' === $slug : in_array( $slug, $saved, true );
	}

	/** Whether this user may use Pencilino. */
	public static function allows( $user ) {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return false;
		}
		if ( in_array( 'administrator', (array) $user->roles, true ) || is_super_admin( $user->ID ) ) {
			return true;
		}
		$saved = self::saved_roles();
		return (bool) array_intersect( (array) $user->roles, null === $saved ? array( 'editor' ) : $saved );
	}

	/** Save the roles from the settings form. */
	public static function save_from_post() {
		$roles = array();
		foreach ( array_keys( self::eligible_roles() ) as $slug ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in Pencil_Client::save_settings().
			if ( ! empty( $_POST[ 'access_role_' . $slug ] ) ) {
				$roles[] = $slug;
			}
		}
		update_option( self::OPTION, $roles, true );
	}

	/** A short status line for the overview. */
	public static function status() {
		$saved = self::saved_roles();
		if ( null === $saved ) {
			return __( 'Administrator and Editor', 'pencilino' );
		}
		/* translators: %d: number of roles besides Administrator */
		return $saved ? sprintf( _n( 'Administrator and %d role', 'Administrator and %d roles', count( $saved ), 'pencilino' ), count( $saved ) ) : __( 'Administrators only', 'pencilino' );
	}

	/** The panel in Features. */
	public static function render_panel() {
		?>
		<div class="pencil-content"><div class="pencil-card pencil-card--full pencil-access">
			<?php Pencil_Admin_UI::intro( __( 'Who can edit', 'pencilino' ), __( 'Access', 'pencilino' ) ); ?>
			<p class="pencil-focus__lead"><?php esc_html_e( 'Choose which user roles may edit on the website: the Edit button and the comments. Roles that are off do not see it at all.', 'pencilino' ); ?></p>
			<?php Pencil_Admin_UI::save_form_open( 'access' ); ?>
				<div class="pencil-setting-row pencil-setting-row--first pencil-focus__row"><span><strong><?php esc_html_e( 'Administrator', 'pencilino' ); ?></strong><small><?php esc_html_e( 'Administrators always have access to front-end editing, so you can never lock yourself out. Dev mode is set per user. Administrators are also the only ones who see the Pencilino page in the dashboard.', 'pencilino' ); ?></small></span></div>
				<?php foreach ( self::eligible_roles() as $slug => $name ) : ?>
					<div class="pencil-setting-row pencil-focus__row"><span><strong><?php echo esc_html( $name ); ?></strong></span><?php Pencil_Admin_UI::toggle( 'access_role_' . $slug, $name, self::role_allowed( $slug ) ); ?></div>
				<?php endforeach; ?>
			<?php Pencil_Admin_UI::save_form_close(); ?>
			<h3 class="pencil-focus__title"><?php esc_html_e( 'Dynamic content', 'pencilino' ); ?></h3>
			<p class="pencil-focus__sub"><?php esc_html_e( 'Texts and images that Pencilino makes editable work for every role that is on. Dynamic content, like WooCommerce products, custom fields or menus, belongs to its own plugin, and that plugin decides who may edit it. If a role is not allowed to, the area still gets its outline on the website, but the popup tells them they do not have access. Give the role the permission that plugin needs. WooCommerce products, for example, need the Shop manager role.', 'pencilino' ); ?></p>
			<p class="pencil-focus__note"><?php esc_html_e( 'Every role on this site is listed. A role that is on gets access to Pencilino, whatever its other permissions are. Focus mode and Dev mode work on top of this: they are set per user.', 'pencilino' ); ?></p>
		</div></div>
		<?php
	}
}
