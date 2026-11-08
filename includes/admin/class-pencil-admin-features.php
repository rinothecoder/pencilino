<?php
/**
 * Pencilino admin: Features tab: Access, Focus mode, Dev mode, Clean-up, Hidden status and Plugin updates.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin_Features {
	public static function render() {
		$focus_count  = count( Pencil_Client::user_ids() );
		$role_ready   = $focus_count > 0;
		$hidden_on    = Pencil_Plugin::show_hidden();
		$dev_count    = count( Pencil_Dev::user_ids() );
		/* translators: %d: number of users */
		$dev_status   = $dev_count > 0 ? sprintf( _n( '%d user', '%d users', $dev_count, 'pencilino' ), $dev_count ) : __( 'Nobody yet', 'pencilino' );

		/* translators: %d: number of users */
		$focus_status   = $role_ready ? sprintf( _n( '%d user', '%d users', $focus_count, 'pencilino' ), $focus_count ) : __( 'Nobody yet', 'pencilino' );
		$hidden_status  = $hidden_on ? __( 'On', 'pencilino' ) : __( 'Off', 'pencilino' );

		// The parts of this tab, in menu order: label, icon, whether it is set up, and its status line.
		$sections = array(
			'access'    => array( __( 'Access', 'pencilino' ), 'dashicons-lock', null !== Pencil_Access::saved_roles(), Pencil_Access::status() ),
			'focus'     => array( __( 'Focus mode', 'pencilino' ), 'dashicons-admin-users', $role_ready, $focus_status ),
			'dev'       => array( __( 'Dev mode', 'pencilino' ), 'dashicons-editor-code', $dev_count > 0, $dev_status ),
			'hidden'    => array( __( 'Hidden status', 'pencilino' ), 'dashicons-visibility', $hidden_on, $hidden_status ),
		);
		?>
			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notices.
			if ( isset( $_GET['pencil-settings-saved'] ) ) : ?><div class="pencil-toast" role="status" data-pencil-toast><?php esc_html_e( 'Settings saved.', 'pencilino' ); ?></div><?php endif; ?>
		<div class="pencil-shell" data-subtabs>
			<nav class="pencil-side" aria-label="<?php esc_attr_e( 'Settings sections', 'pencilino' ); ?>">
				<button type="button" class="pencil-side__item pencil-subtabs__btn--active" data-subtab="overview" data-subtab-hash="settings-overview" aria-selected="true"><span><?php esc_html_e( 'Overview', 'pencilino' ); ?></span></button>
				<?php foreach ( $sections as $key => $part ) : ?>
					<button type="button" class="pencil-side__item" data-subtab="<?php echo esc_attr( $key ); ?>" data-subtab-hash="settings-<?php echo esc_attr( $key ); ?>" aria-selected="false"><span><?php echo esc_html( $part[0] ); ?></span><i class="pencil-side__dot<?php echo $part[2] ? ' show' : ''; ?>"></i></button>
				<?php endforeach; ?>
			</nav>

			<div class="pencil-main">
				<div data-subtab-panel="overview">
					<div class="pencil-content"><div class="pencil-card pencil-card--full">
						<?php Pencil_Admin_UI::intro( __( 'Features', 'pencilino' ), __( 'Overview', 'pencilino' ), false ); ?>
						<p class="pencil-overview__lead"><?php esc_html_e( 'Everything you can set up in Pencilino, and where each part stands.', 'pencilino' ); ?></p>
						<div class="pencil-overview__grid">
							<?php foreach ( $sections as $key => $part ) : ?>
							<a class="pencil-overview__tile" href="#settings-<?php echo esc_attr( $key ); ?>"><span class="pencil-overview__icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr( $part[1] ); ?>"></span></span><span class="pencil-overview__body"><strong><?php echo esc_html( $part[0] ); ?></strong><span class="pencil-overview__status<?php echo $part[2] ? ' is-on' : ''; ?>"><?php echo esc_html( $part[3] ); ?></span></span><span class="pencil-overview__go dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span></a>
						<?php endforeach; ?>
						</div>
					</div></div>
				</div>

				<div data-subtab-panel="access" hidden>
					<?php Pencil_Access::render_panel(); ?>
				</div>

				<div data-subtab-panel="focus" hidden>
		<div class="pencil-content"><div class="pencil-card pencil-card--full">
			<?php Pencil_Admin_UI::intro( __( 'A calmer WordPress', 'pencilino' ), __( 'Focus mode', 'pencilino' ) ); ?>
			<p class="pencil-focus__lead"><?php esc_html_e( 'Give the people who edit their own website a calmer WordPress. Pick the users below. They keep their normal role.', 'pencilino' ); ?></p>

			<h3 class="pencil-focus__title"><?php esc_html_e( 'Who gets focus mode', 'pencilino' ); ?></h3>
			<p class="pencil-focus__sub"><?php esc_html_e( 'Administrators can\'t be added.', 'pencilino' ); ?> <?php esc_html_e( 'Need a new user?', 'pencilino' ); ?> <a href="<?php echo esc_url( admin_url( 'user-new.php' ) ); ?>"><?php esc_html_e( 'Add one in Users', 'pencilino' ); ?></a>.</p>

			<?php Pencil_Admin_UI::user_picker( 'pencil_focus', Pencil_Client::chosen_users(), __( 'Users with focus mode', 'pencilino' ) ); ?>

			<h3 class="pencil-focus__title"><?php esc_html_e( 'What they see', 'pencilino' ); ?></h3>
			<p class="pencil-focus__sub"><?php esc_html_e( 'These two settings apply to everyone selected above.', 'pencilino' ); ?></p>
			<?php Pencil_Admin_UI::save_form_open( 'focus' ); ?>
				<?php foreach ( Pencil_Client::switch_fields() as $key => $field ) : ?>
					<div class="pencil-setting-row pencil-focus__row"><span><strong><?php echo esc_html( $field[0] ); ?></strong><small><?php echo esc_html( $field[1] ); ?></small></span><?php Pencil_Admin_UI::toggle( 'focus_' . $key, $field[0], 'toolbar' === $key ? Pencil_Client::toolbar_on() : Pencil_Client::website_on() ); ?></div>
				<?php endforeach; ?>
				<?php Pencil_Admin_UI::save_form_close(); ?>
			<p class="pencil-focus__note"><?php esc_html_e( 'Want to hide more menu items for them? You need a menu plugin for that, such as Adminino.', 'pencilino' ); ?></p>
		</div></div>
				</div>

				<div data-subtab-panel="dev" hidden>
		<div class="pencil-content"><div class="pencil-card pencil-card--full">
			<?php Pencil_Admin_UI::intro( __( 'See what the AI built', 'pencilino' ), __( 'Dev mode', 'pencilino' ) ); ?>
			<p class="pencil-focus__lead"><?php esc_html_e( 'Dev mode adds a Dev button next to Edit on the website. Click an element to change its framework class and to check whether the CSS follows your framework. Dev mode works best when your framework classes start with a prefix. Agents that build with Pencilino add it for you.', 'pencilino' ); ?></p>

			<h3 class="pencil-focus__title"><?php esc_html_e( 'Who gets Dev mode', 'pencilino' ); ?></h3>
			<p class="pencil-focus__sub"><?php esc_html_e( 'Dev mode is for administrators only. Only the administrators below see the Dev button. Add yourself to try it.', 'pencilino' ); ?></p>

			<?php Pencil_Admin_UI::user_picker( 'pencil_dev', Pencil_Dev::chosen_users(), __( 'Users with Dev mode', 'pencilino' ), array( 'data-action-search' => 'pencil_dev_search', 'data-action-users' => 'pencil_dev_users', 'data-one' => __( 'user has Dev mode', 'pencilino' ), 'data-many' => __( 'users have Dev mode', 'pencilino' ) ) ); ?>

			<p class="pencil-focus__note"><?php esc_html_e( 'Dev mode reads the page in your browser. Nothing is sent or stored.', 'pencilino' ); ?></p>
		</div></div>
				</div>

				<div data-subtab-panel="hidden" hidden>
		<div class="pencil-content"><div class="pencil-card pencil-card--full">
			<?php Pencil_Admin_UI::intro( __( 'Hidden statuses', 'pencilino' ), __( 'Show hidden status', 'pencilino' ) ); ?>
			<?php Pencil_Admin_UI::save_form_open( 'view' ); ?>
				<div class="pencil-setting-row pencil-setting-row--first pencil-setting-row--text"><span><small><?php esc_html_e( 'By default, forms don\'t show the messages people see after they fill them out, like a thank-you or an error. Turn this on to see them too, and edit them with Pencilino.', 'pencilino' ); ?></small></span><?php Pencil_Admin_UI::toggle( 'show_hidden', __( 'Show hidden status', 'pencilino' ), Pencil_Plugin::show_hidden() ); ?></div>
				<div class="pencil-compare">
					<div class="pencil-compare__panel">
						<p class="pencil-compare__title"><?php esc_html_e( 'Off', 'pencilino' ); ?></p>
						<div class="pencil-shot pencil-shot--site pencil-shot--form" aria-hidden="true"><span class="pencil-shot__formtitle"><?php esc_html_e( 'Contact us', 'pencilino' ); ?></span><span class="pencil-shot__input"><?php esc_html_e( 'Your name', 'pencilino' ); ?></span><span class="pencil-shot__input"><?php esc_html_e( 'Your email', 'pencilino' ); ?></span><span class="pencil-shot__button"><?php esc_html_e( 'Send', 'pencilino' ); ?></span></div>
					</div>
					<span class="pencil-compare__arrow" aria-hidden="true"><span class="dashicons dashicons-arrow-right-alt"></span></span>
					<div class="pencil-compare__panel">
						<p class="pencil-compare__title"><?php esc_html_e( 'On', 'pencilino' ); ?></p>
						<div class="pencil-shot pencil-shot--site pencil-shot--form" aria-hidden="true"><span class="pencil-shot__formtitle"><?php esc_html_e( 'Contact us', 'pencilino' ); ?></span><span class="pencil-shot__input"><?php esc_html_e( 'Your name', 'pencilino' ); ?></span><span class="pencil-shot__input"><?php esc_html_e( 'Your email', 'pencilino' ); ?></span><span class="pencil-shot__button"><?php esc_html_e( 'Send', 'pencilino' ); ?></span><span class="pencil-shot__ok"><?php esc_html_e( 'Thanks, your message is sent', 'pencilino' ); ?></span></div>
					</div>
				</div>
				<?php Pencil_Admin_UI::save_form_close(); ?>
		</div></div>
				</div>
			</div>
		</div>
		<?php
	}
}
