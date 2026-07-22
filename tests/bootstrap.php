<?php
/**
 * PHPUnit bootstrap for the WordPress test suite.
 *
 * This file is only ever executed from the command line by PHPUnit. The guard
 * below blocks any direct web request while still allowing CLI execution.
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

	require_once $dir . '/includes/class-fld-roles.php';
	require_once $dir . '/includes/class-fld-sources.php';
	require_once $dir . '/includes/class-fld-database.php';
	require_once $dir . '/includes/class-fld-leads.php';
	require_once $dir . '/includes/class-fld-cf7.php';
	require_once $dir . '/includes/class-fld-feedback.php';
	require_once $dir . '/includes/class-fld-otp.php';
	require_once $dir . '/includes/class-fld-notifications.php';
}
tests_add_filter( 'muplugins_loaded', 'dxleda_manually_load_plugin' );

// Start up the WP testing environment.
require "{$dxleda_tests_dir}/includes/bootstrap.php";
