<?php
/**
 * Lightweight activity records for frontend content changes.
 *
 * @package Pencilino
 */

defined( 'ABSPATH' ) || exit;

final class Pencil_Activity {
	/**
	 * Database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1';

	/**
	 * Default number of activity records kept per site.
	 *
	 * @var int
	 */
	const KEEP = 500;

	/**
	 * Register activity storage hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
	}

	/**
	 * Create or update the activity table when needed.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( 'pencil_activity_db_version' ) ) {
			return;
		}

		self::install();
	}

	/**
	 * Create or update the activity table.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			activity_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			field_id varchar(191) NOT NULL,
			field_label varchar(191) NOT NULL DEFAULT '',
			field_type varchar(20) NOT NULL DEFAULT '',
			old_value longtext NOT NULL,
			new_value longtext NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_name varchar(191) NOT NULL DEFAULT '',
			page_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (activity_id),
			KEY created_at (created_at),
			KEY field_id (field_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off schema check right after dbDelta(); nothing to cache.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);

		if ( $table_name === $table_exists ) {
			update_option( 'pencil_activity_db_version', self::DB_VERSION, true );
		}
	}

	/**
	 * Record a successful field change.
	 *
	 * @param string                 $field_id Stable field ID.
	 * @param array<string, mixed>   $schema   Registered field schema.
	 * @param int|string|null        $old_value Previous value.
	 * @param int|string             $new_value Saved value.
	 * @param int                    $page_id Page where the edit was made.
	 * @return bool
	 */
	public static function record( $field_id, $schema, $old_value, $new_value, $page_id = 0 ) {
		global $wpdb;

		$field_type = isset( $schema['type'] ) ? sanitize_key( $schema['type'] ) : '';
		$old_value  = self::normalise_value( $old_value, $field_type );
		$new_value  = self::normalise_value( $new_value, $field_type );

		if ( $old_value === $new_value ) {
			return false;
		}

		$user = wp_get_current_user();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writes to the custom activity-log table; $wpdb->insert() already escapes/prepares its values.
		$result = $wpdb->insert(
			self::table_name(),
			array(
				'field_id'    => sanitize_text_field( $field_id ),
				'field_label' => isset( $schema['label'] ) ? sanitize_text_field( $schema['label'] ) : sanitize_text_field( $field_id ),
				'field_type'  => $field_type,
				'old_value'   => $old_value,
				'new_value'   => $new_value,
				'user_id'     => get_current_user_id(),
				'user_name'   => $user->exists() ? sanitize_text_field( $user->display_name ) : '',
				'page_id'     => absint( $page_id ),
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		self::prune();

		return true;
	}

	/**
	 * Keep only the newest records. Old and new values are stored in full, so an
	 * unbounded log grows with every edit.
	 *
	 * @return void
	 */
	private static function prune() {
		global $wpdb;

		/**
		 * Filter how many activity records a site keeps.
		 *
		 * @param int $keep Number of newest records to keep.
		 */
		$keep = max( 1, absint( apply_filters( 'pencil_activity_keep', self::KEEP ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, write path only.
		$cutoff = $wpdb->get_var(
			$wpdb->prepare( 'SELECT activity_id FROM %i ORDER BY activity_id DESC LIMIT 1 OFFSET %d', self::table_name(), $keep )
		);

		if ( $cutoff ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, write path only.
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE activity_id <= %d', self::table_name(), absint( $cutoff ) ) );
		}
	}

	/**
	 * Return the newest activity records.
	 *
	 * @param int $limit Maximum number of records.
	 * @return array<int, object>
	 */
	public static function get_recent( $limit = 100 ) {
		global $wpdb;

		$limit = min( 200, max( 1, absint( $limit ) ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom activity-log table has no core WP API; results are only read on the low-traffic Pencilino admin screen.
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY created_at DESC, activity_id DESC LIMIT %d',
				self::table_name(),
				$limit
			)
		);
	}

	/**
	 * Return the activity table name.
	 *
	 * @return string
	 */
	private static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'pencil_activity';
	}

	/**
	 * Convert a stored field value to its comparable database form.
	 *
	 * @param mixed  $value      Field value.
	 * @param string $field_type Field type.
	 * @return string
	 */
	private static function normalise_value( $value, $field_type ) {
		if ( 'image' === $field_type ) {
			return (string) absint( $value );
		}

		if ( 'button' === $field_type ) {
			$value = is_array( $value ) ? $value : array();

			return (string) wp_json_encode(
				array(
					'text' => isset( $value['text'] ) ? (string) $value['text'] : '',
					'url'  => isset( $value['url'] ) ? (string) $value['url'] : '',
				)
			);
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
