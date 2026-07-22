<?php
/**
 * Uninstall routine — runs when the plugin is deleted from Plugins > Installed Plugins.
 * Drops all plugin tables and removes all plugin options from wp_options.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$dxleda_tables = array(
	$wpdb->prefix . 'dxleda_lead_status',
	$wpdb->prefix . 'dxleda_feedback',
	$wpdb->prefix . 'dxleda_activity_log',
	$wpdb->prefix . 'dxleda_cf7_entries',
	$wpdb->prefix . 'dxleda_cf7_entry_meta',
);

foreach ( $dxleda_tables as $dxleda_table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table name is constructed from trusted $wpdb->prefix and a hardcoded suffix.
	$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $dxleda_table ) . '`' );
}

// Remove all plugin options.
$dxleda_options = array(
	'dxleda_version',
	'dxleda_db_version',
	'dxleda_email_notifications',
	'dxleda_notification_email',
	'dxleda_auto_assign',
	'dxleda_default_assignee',
	'dxleda_leads_per_page',
	'dxleda_smtp_host',
	'dxleda_smtp_port',
	'dxleda_smtp_username',
	'dxleda_smtp_password',
	'dxleda_smtp_encryption',
	'dxleda_brevo_sender_name',
	'dxleda_brevo_sender_email',
	'dxleda_otp_enabled_forms',
);

foreach ( $dxleda_options as $dxleda_option ) {
	delete_option( $dxleda_option );
}
