<?php
/**
 * PHPUnit bootstrap for the WordPress test suite.
 *
 * This file is only ever executed from the command line by PHPUnit. The guard
 * below blocks any direct web request while still allowing CLI execution.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) && PHP_SAPI !== 'cli' ) {
	exit;
}

$dxleda_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $dxleda_tests_dir ) {
	$dxleda_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$dxleda_tests_dir}/includes/functions.php" ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only bootstrap; WordPress is not loaded yet.
	echo "Could not find {$dxleda_tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first." . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() before the test suite loads.
require_once "{$dxleda_tests_dir}/includes/functions.php";

/**
 * Load the plugin under test.
 *
 * The plugin skips loading its class files when no supported form plugin is
 * active, so for unit testing we require them directly — neither Forminator nor
 * Contact Form 7 is needed to exercise the plugin's own data layer.
 */
function dxleda_manually_load_plugin() {
	$dir = dirname( __DIR__ );

	require $dir . '/devxpert-lead-dashboard-for-forminator.php';

	require_once $dir . '/includes/class-dxleda-roles.php';
	require_once $dir . '/includes/class-dxleda-sources.php';
	require_once $dir . '/includes/class-dxleda-database.php';
	require_once $dir . '/includes/class-dxleda-leads.php';
	require_once $dir . '/includes/class-dxleda-cf7.php';
	require_once $dir . '/includes/class-dxleda-feedback.php';
	require_once $dir . '/includes/class-dxleda-otp.php';
	require_once $dir . '/includes/class-dxleda-notifications.php';
}
tests_add_filter( 'muplugins_loaded', 'dxleda_manually_load_plugin' );

// Start up the WP testing environment.
require "{$dxleda_tests_dir}/includes/bootstrap.php";
