<?php
/**
 * Database Handler Class
 * 
 * Creates and manages custom database tables for lead tracking and feedback
 */

if (!defined('ABSPATH')) {
    exit;
}

class FLD_Database {

    /**
     * Create custom tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Lead status tracking table
        $table_lead_status = $wpdb->prefix . 'fld_lead_status';
        
        $sql_lead_status = "CREATE TABLE $table_lead_status (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            form_id bigint(20) NOT NULL,
            status varchar(50) NOT NULL DEFAULT 'new',
            assigned_to bigint(20) DEFAULT NULL,
            priority varchar(20) DEFAULT 'normal',
            source varchar(100) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY entry_id (entry_id),
            KEY form_id (form_id),
            KEY status (status),
            KEY assigned_to (assigned_to)
        ) $charset_collate;";

        // Feedback table
        $table_feedback = $wpdb->prefix . 'fld_feedback';
        
        $sql_feedback = "CREATE TABLE $table_feedback (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            feedback text NOT NULL,
            rating varchar(20) NOT NULL DEFAULT 'neutral',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY user_id (user_id),
            KEY rating (rating)
        ) $charset_collate;";

        // Activity log table
        $table_activity = $wpdb->prefix . 'fld_activity_log';
        
        $sql_activity = "CREATE TABLE $table_activity (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            details text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY entry_id (entry_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_lead_status);
        dbDelta($sql_feedback);
        dbDelta($sql_activity);

        // Update version
        update_option('fld_db_version', FLD_VERSION);
    }

    /**
     * Get table name with prefix
     */
    public static function get_table($table) {
        global $wpdb;
        return $wpdb->prefix . 'fld_' . $table;
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'fld_lead_status',
            $wpdb->prefix . 'fld_feedback',
            $wpdb->prefix . 'fld_activity_log',
        );

        foreach ( $tables as $table ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is constructed from trusted $wpdb->prefix and a hardcoded suffix.
            $wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
        }

        delete_option( 'fld_db_version' );
        delete_option( 'fld_version' );
    }
}
