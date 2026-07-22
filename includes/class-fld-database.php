<?php
/**
 * Database Handler Class
 *
 * Creates and manages custom database tables for lead tracking and feedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DXLEDA_Database {

	/**
	 * Create custom tables
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Lead status tracking table.
		//
		// A lead is identified by (entry_id, source), not entry_id alone: entry
		// IDs are only unique within their own form plugin, so Forminator entry
		// #5 and Contact Form 7 entry #5 are different leads.
		$table_lead_status = $wpdb->prefix . 'dxleda_lead_status';

		$sql_lead_status = "CREATE TABLE $table_lead_status (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'forminator',
            form_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'new',
            assigned_to bigint(20) DEFAULT NULL,
            priority varchar(20) DEFAULT 'normal',
            lead_source varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_source (entry_id, source),
            KEY form_id (form_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";

		// Feedback table.
		$table_feedback = $wpdb->prefix . 'dxleda_feedback';

		$sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'forminator',
            user_id bigint(20) NOT NULL,
            feedback text NOT NULL,
            rating varchar(20) NOT NULL DEFAULT 'neutral',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entry_source (entry_id, source),
            KEY user_id (user_id),
            KEY rating (rating)
        ) $charset_collate;";

		// Activity log table.
		$table_activity = $wpdb->prefix . 'dxleda_activity_log';

		$sql_activity = "CREATE TABLE $table_activity (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            source varchar(20) NOT NULL DEFAULT 'forminator',
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entry_source (entry_id, source),
            KEY user_id (user_id)
        ) $charset_collate;";

		// Contact Form 7 entry storage.
		//
		// CF7 does not persist submissions anywhere — it mails them and discards
		// them — so the plugin captures them itself. Column names deliberately
		// mirror Forminator's frmt_form_entry / frmt_form_entry_meta so both
		// sources can be read through one UNION in DXLEDA_Leads.
		$table_cf7_entries = $wpdb->prefix . 'dxleda_cf7_entries';

		$sql_cf7_entries = "CREATE TABLE $table_cf7_entries (
            entry_id bigint(20) NOT NULL AUTO_INCREMENT,
            form_id bigint(20) NOT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (entry_id),
            KEY form_id (form_id),
            KEY date_created (date_created)
        ) $charset_collate;";

		$table_cf7_meta = $wpdb->prefix . 'dxleda_cf7_entry_meta';

		$sql_cf7_meta = "CREATE TABLE $table_cf7_meta (
            meta_id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            meta_key varchar(191) NOT NULL,
            meta_value longtext,
            PRIMARY KEY (meta_id),
            KEY entry_id (entry_id),
            KEY meta_key (meta_key)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Rescue the pre-1.1 "source" column *before* dbDelta runs. dbDelta would
		// narrow it from varchar(100) to varchar(20) to match the new definition,
		// silently truncating the marketing-origin values it still holds.
		self::premigrate_lead_source();

		dbDelta( $sql_lead_status );
		dbDelta( $sql_feedback );
		dbDelta( $sql_activity );
		dbDelta( $sql_cf7_entries );
		dbDelta( $sql_cf7_meta );

		// dbDelta never drops an index it no longer recognises, so the pre-1.1
		// single-column UNIQUE KEY has to go explicitly.
		self::migrate_unique_key();

		// Update version.
		update_option( 'dxleda_db_version', DXLEDA_VERSION );
	}

	/**
	 * Does a table exist?
	 *
	 * @param string $table Prefixed table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- schema inspection; no cache applies.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Column names of a table.
	 *
	 * @param string $table Prefixed table name.
	 * @return string[]
	 */
	private static function columns( $table ) {
		global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- schema inspection on a plugin-owned table; name from trusted prefix.
		return (array) $wpdb->get_col( 'DESC `' . esc_sql( $table ) . '`', 0 );
	}

	/**
	 * Move the pre-1.1 marketing-origin values out of "source" (schema < 1.1.0).
	 *
	 * Pre-1.1 installs used "source" to mean where a lead came from
	 * commercially. That meaning now lives in "lead_source"; "source" identifies
	 * the form plugin, and every existing row is a Forminator lead.
	 *
	 * Must run before dbDelta — see the call site.
	 *
	 * @return void
	 */
	private static function premigrate_lead_source() {
		global $wpdb;

		$table = $wpdb->prefix . 'dxleda_lead_status';

		if ( ! self::table_exists( $table ) ) {
			return; // Fresh install: dbDelta creates the current schema directly.
		}

		$columns = self::columns( $table );

		// Already migrated, or a fresh 1.1+ table.
		if ( in_array( 'lead_source', $columns, true ) || ! in_array( 'source', $columns, true ) ) {
			return;
		}

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- schema migration on a plugin-owned table; name from trusted prefix.

		$wpdb->query(
			'ALTER TABLE `' . esc_sql( $table ) . "` ADD COLUMN `lead_source` varchar(100) DEFAULT '' AFTER `priority`"
		);

		// Copy at full width, then reset "source" to its new meaning.
		$wpdb->query( 'UPDATE `' . esc_sql( $table ) . '` SET `lead_source` = `source`' );
		$wpdb->query( 'UPDATE `' . esc_sql( $table ) . "` SET `source` = 'forminator'" );

        // phpcs:enable
	}

	/**
	 * Replace the single-column unique key with the composite (entry_id, source)
	 * one (schema < 1.1.0).
	 *
	 * @return void
	 */
	private static function migrate_unique_key() {
		global $wpdb;

		$table = $wpdb->prefix . 'dxleda_lead_status';

		if ( ! self::table_exists( $table ) ) {
			return;
		}

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- schema migration on a plugin-owned table; name from trusted prefix.

		$indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . esc_sql( $table ) . '`' );
		$names   = wp_list_pluck( $indexes, 'Key_name' );

		if ( in_array( 'entry_id', $names, true ) ) {
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table ) . '` DROP INDEX `entry_id`' );
		}

		if ( ! in_array( 'entry_source', $names, true ) ) {
			$wpdb->query(
				'ALTER TABLE `' . esc_sql( $table ) . '` ADD UNIQUE KEY `entry_source` (`entry_id`, `source`)'
			);
		}

        // phpcs:enable
	}

	/**
	 * Run pending schema upgrades.
	 *
	 * create_tables() only runs on activation, so a plugin updated in place
	 * (via WordPress.org) would never pick up a new schema. Called on every
	 * load; the version check makes it a no-op in the common case.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'dxleda_db_version' ) === DXLEDA_VERSION ) {
			return;
		}

		self::create_tables();
	}

	/**
	 * Get table name with prefix
	 */
	public static function get_table( $table ) {
		global $wpdb;
		return $wpdb->prefix . 'dxleda_' . $table;
	}

	/**
	 * Drop all tables (for uninstall)
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			$wpdb->prefix . 'dxleda_lead_status',
			$wpdb->prefix . 'dxleda_feedback',
			$wpdb->prefix . 'dxleda_activity_log',
			$wpdb->prefix . 'dxleda_cf7_entries',
			$wpdb->prefix . 'dxleda_cf7_entry_meta',
		);

		foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is constructed from trusted $wpdb->prefix and a hardcoded suffix.
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
		}

		delete_option( 'dxleda_db_version' );
		delete_option( 'dxleda_version' );
	}
}
