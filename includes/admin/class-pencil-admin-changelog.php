<?php
/**
 * Pencilino admin: Change log tab: the list of edits, and the comments inbox.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Admin_Changelog {
	/**
	 * Change log tab: the content history and the client comments as two sub-tabs.
	 *
	 * @return void
	 */
	public static function render() {
		?>
		<div class="pencil-content pencil-content--onboarding">
			<div class="pencil-card pencil-card--full pencil-changes">
				<div data-subtabs>
					<div class="pencil-subtabs" role="tablist">
						<button type="button" class="pencil-subtabs__btn pencil-subtabs__btn--active" data-subtab="changes" data-subtab-hash="changes" role="tab" aria-selected="true"><?php esc_html_e( 'Changes', 'pencilino' ); ?></button>
						<button type="button" class="pencil-subtabs__btn" data-subtab="comments" data-subtab-hash="comments" role="tab" aria-selected="false"><?php esc_html_e( 'Comments', 'pencilino' ); ?></button>
					</div>

					<div data-subtab-panel="changes"><?php self::render_changes(); ?></div>
					<div data-subtab-panel="comments" hidden><?php self::render_comments(); ?></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Changes tab: open button and the activity log.
	 *
	 * @return void
	 */
	private static function render_changes() {
		$activities = Pencil_Activity::get_recent( 100 );
		?>
				<div class="pencil-page-intro">
					<span class="pencil-eyebrow"><?php esc_html_e( 'Content history', 'pencilino' ); ?></span>
					<h2><?php esc_html_e( 'Changes', 'pencilino' ); ?></h2>
				</div>

				<?php if ( empty( $activities ) ) : ?>
					<div class="pencil-admin__empty">
						<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						<h3><?php esc_html_e( 'No changes yet', 'pencilino' ); ?></h3>
						<p><?php esc_html_e( 'Every change saved through Pencilino appears here, with the value before and after.', 'pencilino' ); ?></p>
					</div>
				<?php else : ?>
					<ul class="pencil-admin__list">
						<?php foreach ( $activities as $activity ) : ?>
							<li class="pencil-admin__record">
								<?php self::render_activity_row( $activity ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
		<?php
	}

	/**
	 * Short message after a bulk action.
	 *
	 * @param string $done  Action that ran.
	 * @param int    $count Number of comments changed.
	 * @return string
	 */
	public static function bulk_notice( $done, $count ) {
		if ( ! $count ) {
			return __( 'No comments were changed.', 'pencilino' );
		}
		$number = number_format_i18n( $count );
		if ( 'resolve' === $done ) {
			/* translators: %s: number of comments. */
			return sprintf( _n( '%s comment resolved.', '%s comments resolved.', $count, 'pencilino' ), $number );
		}
		if ( 'reopen' === $done ) {
			/* translators: %s: number of comments. */
			return sprintf( _n( '%s comment reopened.', '%s comments reopened.', $count, 'pencilino' ), $number );
		}
		if ( 'delete' === $done ) {
			/* translators: %s: number of comments. */
			return sprintf( _n( '%s comment deleted.', '%s comments deleted.', $count, 'pencilino' ), $number );
		}
		/* translators: %s: number of comments. */
		return sprintf( _n( 'Undone: %s comment moved back.', 'Undone: %s comments moved back.', $count, 'pencilino' ), $number );
	}

	private static function render_comments() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only inbox filtering.
		$resolved = isset( $_GET['pencil-comment-status'] ) && 'resolved' === $_GET['pencil-comment-status'];
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination.
		$page = isset( $_GET['pencil-comment-page'] ) ? max( 1, absint( $_GET['pencil-comment-page'] ) ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message after a bulk action.
		$done = isset( $_GET['pencil-comment-done'] ) ? sanitize_key( wp_unslash( $_GET['pencil-comment-done'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only message after a bulk action.
		$changed    = isset( $_GET['pencil-comment-count'] ) ? absint( $_GET['pencil-comment-count'] ) : 0;
		$rows       = Pencil_Comments::inbox( $resolved, $page );
		$more       = count( $rows ) > 50;
		$rows       = array_slice( $rows, 0, 50 );
		$base       = add_query_arg( 'page', Pencil_Admin::MENU_SLUG, admin_url( 'admin.php' ) );
		$status     = $resolved ? 'resolved' : 'open';
		$can_manage = Pencil_Client::can_manage();
		$open_total = Pencil_Comments::count_status( false );
		$res_total  = Pencil_Comments::count_status( true );
		$total      = $resolved ? $res_total : $open_total;
		?>
				<div class="pencil-page-intro">
					<span class="pencil-eyebrow"><?php esc_html_e( 'Client feedback', 'pencilino' ); ?></span>
					<h2><?php esc_html_e( 'Comments', 'pencilino' ); ?></h2>
				</div>

				<?php if ( in_array( $done, array( 'resolve', 'reopen', 'delete', 'undo' ), true ) ) : ?>
					<div class="pencil-admin__notice" role="status" data-pencil-notice>
						<span><?php echo esc_html( self::bulk_notice( $done, $changed ) ); ?></span>
						<?php if ( $can_manage && $changed && in_array( $done, array( 'resolve', 'reopen' ), true ) && Pencil_Comments::has_undo() ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pencil_undo_comments"><?php wp_nonce_field( 'pencil_undo_comments' ); ?><button class="pencil-admin__linkbutton" type="submit"><?php esc_html_e( 'Undo', 'pencilino' ); ?></button></form>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<nav class="pencil-admin__filters" aria-label="<?php esc_attr_e( 'Comment status', 'pencilino' ); ?>">
					<a data-comment-filter="open" class="pencil-admin__edit<?php echo ! $resolved ? ' pencil-admin__edit--active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'pencil-comment-status', 'open', $base ) . '#comments' ); ?>"<?php echo ! $resolved ? ' aria-current="page"' : ''; ?>><?php echo esc_html( sprintf( /* translators: %s: number of open comments. */ __( 'Open (%s)', 'pencilino' ), number_format_i18n( $open_total ) ) ); ?></a>
					<a data-comment-filter="resolved" class="pencil-admin__edit<?php echo $resolved ? ' pencil-admin__edit--active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'pencil-comment-status', 'resolved', $base ) . '#comments' ); ?>"<?php echo $resolved ? ' aria-current="page"' : ''; ?>><?php echo esc_html( sprintf( /* translators: %s: number of resolved comments. */ __( 'Resolved (%s)', 'pencilino' ), number_format_i18n( $res_total ) ) ); ?></a>
				</nav>

				<?php if ( $resolved ) : ?>
					<p class="pencil-admin__hint"><?php esc_html_e( 'Resolved comments are kept until you delete them. Deleting here removes them for good.', 'pencilino' ); ?></p>
				<?php endif; ?>

				<?php if ( ! $rows ) : ?>
					<div class="pencil-admin__empty">
						<span class="dashicons dashicons-format-chat" aria-hidden="true"></span>
						<h3><?php echo esc_html( $resolved ? __( 'No resolved comments yet', 'pencilino' ) : __( 'No open comments', 'pencilino' ) ); ?></h3>
						<p><?php echo esc_html( $resolved ? __( 'Comments you resolve move here.', 'pencilino' ) : __( 'Private notes left on the website appear here.', 'pencilino' ) ); ?></p>
					</div>
				<?php else : ?>
					<?php if ( $can_manage ) : ?>
						<form id="pencil-bulk-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="pencil_bulk_comments">
							<input type="hidden" name="select_all" value="0">
							<?php wp_nonce_field( 'pencil_bulk_comments' ); ?>
						</form>

						<?php if ( $resolved ) : ?>
							<div class="pencil-admin__confirm" data-bulk-confirm hidden data-text-one="<?php esc_attr_e( 'Delete 1 comment permanently? This cannot be undone.', 'pencilino' ); ?>" data-text-many="<?php
								/* translators: %d: number of comments that will be deleted */
								esc_attr_e( 'Delete %d comments permanently? This cannot be undone.', 'pencilino' );
							?>">
								<p data-bulk-confirm-text></p>
								<button type="button" class="pencil-admin__edit" data-bulk-cancel><?php esc_html_e( 'Cancel', 'pencilino' ); ?></button>
								<button type="submit" form="pencil-bulk-form" name="bulk_action" value="delete" class="pencil-admin__edit pencil-admin__edit--danger"><?php esc_html_e( 'Yes, delete', 'pencilino' ); ?></button>
							</div>
						<?php endif; ?>

						<div class="pencil-admin__bulk" data-bulk-bar data-undo-label="<?php esc_attr_e( 'Undo', 'pencilino' ); ?>" data-total="<?php echo esc_attr( $total ); ?>" data-total-text="<?php echo esc_attr( number_format_i18n( $total ) ); ?>" data-label-none="<?php echo esc_attr( sprintf( /* translators: %s: number of comments. */ __( 'Select all %s', 'pencilino' ), number_format_i18n( $total ) ) ); ?>" data-label-some="<?php esc_attr_e( '%d selected', 'pencilino' ); ?>" data-label-all="<?php echo esc_attr( sprintf( /* translators: %s: number of comments. */ __( 'All %s selected', 'pencilino' ), number_format_i18n( $total ) ) ); ?>">
							<label class="pencil-admin__check"><input type="checkbox" data-bulk-all> <span data-bulk-label><?php echo esc_html( sprintf( /* translators: %s: number of comments. */ __( 'Select all %s', 'pencilino' ), number_format_i18n( $total ) ) ); ?></span></label>
							<span class="pencil-admin__bulk-spacer"></span>
							<?php if ( ! $resolved ) : ?>
								<button type="submit" form="pencil-bulk-form" name="bulk_action" value="resolve" class="pencil-admin__edit" data-when="some" hidden><?php esc_html_e( 'Resolve selected', 'pencilino' ); ?></button>
							<?php else : ?>
								<button type="submit" form="pencil-bulk-form" name="bulk_action" value="reopen" class="pencil-admin__edit" data-when="some" hidden><?php esc_html_e( 'Reopen selected', 'pencilino' ); ?></button>
								<button type="button" class="pencil-admin__edit pencil-admin__edit--danger" data-when="some" data-bulk-delete hidden><?php esc_html_e( 'Delete selected', 'pencilino' ); ?></button>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<ul class="pencil-admin__list">
						<?php
						foreach ( $rows as $row ) :
							$target     = json_decode( $row->target, true );
							$timestamp  = strtotime( $row->created_at . ' UTC' );
							$when       = $timestamp ? wp_date( 'j M Y, H:i', $timestamp ) : $row->created_at;
							$user_name  = $row->user_name ? $row->user_name : __( 'Unknown user', 'pencilino' );
							$avatar     = isset( $row->user_id ) ? get_avatar( absint( $row->user_id ), 20, '', '', array( 'class' => 'pencil-admin__avatar' ) ) : '';
							$who_markup = sprintf( '<span class="pencil-admin__user">%s<span>%s</span></span>', $avatar, esc_html( $user_name ) );
							if ( $row->page_title && $row->page_url ) {
								$where_markup = sprintf( '<a href="%s">%s</a>', esc_url( $row->page_url ), esc_html( $row->page_title ) );
							} else {
								$where_markup = sprintf( '<span class="pencil-admin__muted">%s</span>', esc_html__( 'the website', 'pencilino' ) );
							}
							$when_markup = sprintf( '<span class="pencil-admin__when">%s</span>', esc_html( $when ) );
							?>
							<li class="pencil-admin__record" data-comment-row>
								<div class="pencil-admin__record-head">
									<div class="pencil-admin__record-title">
										<?php if ( $can_manage ) : ?>
											<label class="pencil-admin__check"><span class="screen-reader-text"><?php esc_html_e( 'Select comment', 'pencilino' ); ?></span><input type="checkbox" name="ids[]" value="<?php echo absint( $row->comment_id ); ?>" form="pencil-bulk-form" data-bulk-row></label>
										<?php endif; ?>
										<strong class="pencil-admin__field"><?php echo esc_html( $target['label'] ?? __( 'Page element', 'pencilino' ) ); ?></strong>
									</div>
									<div class="pencil-admin__actions">
										<a class="pencil-admin__edit" href="<?php echo esc_url( add_query_arg( array( 'pencil-edit' => '1', 'pencil-comment' => $row->comment_id ), $row->page_url ) ); ?>"><?php esc_html_e( 'View', 'pencilino' ); ?></a>
										<?php if ( $can_manage ) : ?>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pencil_resolve_comment"><input type="hidden" name="comment_id" value="<?php echo absint( $row->comment_id ); ?>"><input type="hidden" name="resolved" value="<?php echo $resolved ? '0' : '1'; ?>"><?php wp_nonce_field( 'pencil_resolve_comment_' . $row->comment_id ); ?><button class="pencil-admin__edit" type="submit"><?php echo esc_html( $resolved ? __( 'Reopen', 'pencilino' ) : __( 'Resolve', 'pencilino' ) ); ?></button></form>
											<?php if ( $resolved ) : ?>
												<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="pencil_delete_comment"><input type="hidden" name="comment_id" value="<?php echo absint( $row->comment_id ); ?>"><?php wp_nonce_field( 'pencil_delete_comment_' . $row->comment_id ); ?><button class="pencil-admin__edit pencil-admin__edit--danger" type="submit"><?php esc_html_e( 'Delete', 'pencilino' ); ?></button></form>
											<?php endif; ?>
										<?php endif; ?>
									</div>
								</div>
								<p class="pencil-admin__meta-line">
									<?php
									echo wp_kses_post(
										sprintf(
											/* translators: 1: user name with avatar, 2: page or location, 3: date and time. */
											__( '%1$s commented on %2$s on %3$s', 'pencilino' ),
											$who_markup,
											$where_markup,
											$when_markup
										)
									);
									?>
								</p>
								<div class="pencil-admin__value-text pencil-admin__value-text--comment"><?php echo esc_html( $row->comment_text ); ?></div>
								<?php if ( ! empty( $row->edited_at ) ) : ?><p class="pencil-admin__meta-line"><?php esc_html_e( 'Edited', 'pencilino' ); ?></p><?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if ( $page > 1 || $more ) : ?>
					<nav class="pencil-admin__filters pencil-admin__filters--pager" aria-label="<?php esc_attr_e( 'Comment pages', 'pencilino' ); ?>">
						<?php if ( $page > 1 ) : ?><a class="pencil-admin__edit" href="<?php echo esc_url( add_query_arg( array( 'pencil-comment-status' => $status, 'pencil-comment-page' => $page - 1 ), $base ) . '#comments' ); ?>"><?php esc_html_e( 'Previous', 'pencilino' ); ?></a><?php endif; ?>
						<?php if ( $more ) : ?><a class="pencil-admin__edit" href="<?php echo esc_url( add_query_arg( array( 'pencil-comment-status' => $status, 'pencil-comment-page' => $page + 1 ), $base ) . '#comments' ); ?>"><?php esc_html_e( 'Next', 'pencilino' ); ?></a><?php endif; ?>
					</nav>
				<?php endif; ?>
		<?php
	}

	/**
	 * Render one activity record.
	 *
	 * @param object $activity Activity database record.
	 * @return void
	 */
	private static function render_activity_row( $activity ) {
		$timestamp  = strtotime( $activity->created_at . ' UTC' );
		$when       = $timestamp ? wp_date( 'j M Y, H:i', $timestamp ) : $activity->created_at;
		$user_name  = $activity->user_name ? $activity->user_name : __( 'Unknown user', 'pencilino' );
		$page_id    = absint( $activity->page_id );
		$page_title = $page_id ? get_the_title( $page_id ) : '';
		$page_url   = $page_id ? get_permalink( $page_id ) : '';
		$edit_url   = add_query_arg(
			array(
				'pencil-edit'  => '1',
				'pencil-field' => $activity->field_id,
			),
			$page_url ? $page_url : home_url( '/' )
		);

		$who_markup = sprintf(
			'<span class="pencil-admin__user">%s<span>%s</span></span>',
			get_avatar( absint( $activity->user_id ), 20, '', '', array( 'class' => 'pencil-admin__avatar' ) ),
			esc_html( $user_name )
		);

		if ( $page_title && $page_url ) {
			$where_markup = sprintf( '<a href="%s">%s</a>', esc_url( $page_url ), esc_html( $page_title ) );
		} else {
			$where_markup = sprintf( '<span class="pencil-admin__muted">%s</span>', esc_html__( 'a site-wide field', 'pencilino' ) );
		}

		$when_markup = sprintf( '<span class="pencil-admin__when">%s</span>', esc_html( $when ) );
		?>
		<div class="pencil-admin__record-head">
			<strong class="pencil-admin__field" title="<?php echo esc_attr( $activity->field_id ); ?>"><?php echo esc_html( $activity->field_label ); ?></strong>
			<a class="pencil-admin__edit" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'pencilino' ); ?></a>
		</div>
		<p class="pencil-admin__meta-line">
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: 1: user name with avatar, 2: page or location, 3: date and time. */
					__( '%1$s updated %2$s on %3$s', 'pencilino' ),
					$who_markup,
					$where_markup,
					$when_markup
				)
			);
			?>
		</p>
		<div class="pencil-admin__change">
			<?php self::render_value( __( 'Before:', 'pencilino' ), $activity->old_value, $activity->field_type ); ?>
			<span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
			<?php self::render_value( __( 'After:', 'pencilino' ), $activity->new_value, $activity->field_type ); ?>
		</div>
		<?php
	}

	/**
	 * Render one before or after value box.
	 *
	 * Rich text keeps its formatting so a change such as adding bold is visible.
	 *
	 * @param string $label      Screen-reader label.
	 * @param string $value      Stored value.
	 * @param string $field_type Field type.
	 * @return void
	 */
	private static function render_value( $label, $value, $field_type ) {
		$is_rich = 'richtext' === $field_type && '' !== trim( wp_strip_all_tags( (string) $value ) );
		?>
		<div class="pencil-admin__value-text<?php echo $is_rich ? ' pencil-admin__value-text--rich' : ''; ?>">
			<span class="screen-reader-text"><?php echo esc_html( $label ); ?> </span>
			<?php
			if ( $is_rich ) {
				echo wp_kses( $value, Pencil_Fields::richtext_tags() );
			} else {
				echo esc_html( self::display_value( $value, $field_type ) );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Return a readable plain-text activity value.
	 *
	 * @param string $value      Stored value.
	 * @param string $field_type Field type.
	 * @return string
	 */
	private static function display_value( $value, $field_type ) {
		if ( 'image' === $field_type ) {
			$attachment_id = absint( $value );

			if ( ! $attachment_id ) {
				return __( 'No image', 'pencilino' );
			}

			$title = get_the_title( $attachment_id );

			return $title ? $title : sprintf(
				/* translators: %d: Media attachment ID. */
				__( 'Media #%d', 'pencilino' ),
				$attachment_id
			);
		}

		if ( 'button' === $field_type ) {
			$button = json_decode( (string) $value, true );

			if ( is_array( $button ) && ! empty( $button['text'] ) ) {
				return sprintf( '%s (%s)', $button['text'], isset( $button['url'] ) ? $button['url'] : '' );
			}

			return __( 'Empty', 'pencilino' );
		}

		$value = trim( wp_strip_all_tags( (string) $value ) );

		if ( '' === $value ) {
			return __( 'Empty', 'pencilino' );
		}

		return $value;
	}
}
