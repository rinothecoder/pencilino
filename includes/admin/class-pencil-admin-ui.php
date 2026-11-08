<?php
/**
 * Small pieces of markup that several Pencilino admin tabs share.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin_UI {
	/**
	 * Eyebrow and title at the top of a screen.
	 *
	 * @param string $eyebrow Small text above the title.
	 * @param string $title   The title.
	 * @param bool   $rule    Show the rule under it.
	 * @return void
	 */
	public static function intro( $eyebrow, $title, $rule = true ) {
		echo '<div class="pencil-page-intro' . ( $rule ? ' pencil-page-intro--rule' : '' ) . '"><span class="pencil-eyebrow">' . esc_html( $eyebrow ) . '</span><h2>' . esc_html( $title ) . '</h2></div>';
	}

	/**
	 * Open a form that saves one group of settings by itself.
	 *
	 * @param string $group Settings group, as the save handler knows it.
	 * @return void
	 */
	public static function save_form_open( $group ) {
		echo '<form action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" method="post" data-pencil-autosave>';
		echo '<input type="hidden" name="action" value="pencil_save_settings"><input type="hidden" name="pencil_group" value="' . esc_attr( $group ) . '"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'pencil_save_settings' ) ) . '">';
	}

	/**
	 * Close a settings form. Without JavaScript it gets a Save button.
	 *
	 * @return void
	 */
	public static function save_form_close() {
		echo '<noscript><button class="pencil-button" type="submit">' . esc_html__( 'Save settings', 'pencilino' ) . '</button></noscript></form>';
	}

	/**
	 * An on and off switch.
	 *
	 * @param string $name    Field name.
	 * @param string $label   Accessible label.
	 * @param bool   $checked Whether it is on.
	 * @return void
	 */
	public static function toggle( $name, $label, $checked ) {
		echo '<span class="pencil-setting-switch"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1" role="switch" aria-label="' . esc_attr( $label ) . '"' . checked( (bool) $checked, true, false ) . '><span class="pencil-setting-switch__track" aria-hidden="true"></span></span>';
	}

	/**
	 * The search box and list that add users to Focus mode or Dev mode. focus.js drives it.
	 *
	 * @param string               $nonce_action Nonce action for the AJAX calls.
	 * @param array                $users        The chosen users, as JSON for the script.
	 * @param string               $list_label   Accessible label of the chosen list.
	 * @param array<string,string> $extra        Extra data attributes, such as the AJAX actions.
	 * @return void
	 */
	public static function user_picker( $nonce_action, $users, $list_label, $extra = array() ) {
		$attrs = '';
		foreach ( $extra as $name => $value ) {
			$attrs .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}
		?>
		<div class="pencil-focus" data-pencil-focus<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts above. ?> data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( $nonce_action ) ); ?>" data-users="<?php echo esc_attr( wp_json_encode( $users ) ); ?>">
			<span class="pencil-focus__saved" data-focus-saved role="status"><?php esc_html_e( 'Saved', 'pencilino' ); ?></span>
			<div class="pencil-focus__finder">
				<input type="text" data-focus-search role="combobox" aria-expanded="false" aria-autocomplete="list" autocomplete="off" placeholder="<?php esc_attr_e( 'Search users by name or email', 'pencilino' ); ?>" aria-label="<?php esc_attr_e( 'Search users', 'pencilino' ); ?>">
				<ul class="pencil-focus__results" data-focus-results role="listbox" hidden></ul>
			</div>
			<p class="pencil-focus__none" data-focus-none hidden><?php esc_html_e( 'No users yet. Search above to add someone.', 'pencilino' ); ?></p>
			<p class="pencil-focus__count" data-focus-count hidden></p>
			<ul class="pencil-focus__chosen" data-focus-chosen aria-label="<?php echo esc_attr( $list_label ); ?>"></ul>
		</div>
		<?php
	}
}
