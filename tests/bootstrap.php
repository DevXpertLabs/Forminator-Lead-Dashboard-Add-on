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

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Could not find {$_tests_dir}/includes/functions.php — run bin/install-wp-tests.sh first." . PHP_EOL;
    exit(1);
}

// Give access to tests_add_filter() before the test suite loads.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Load the plugin under test.
 *
 * The plugin skips loading its class files when Forminator is not active, so
 * for unit testing we require them directly — Forminator itself is not needed
 * to exercise the plugin's own data layer.
 */
function _dxleda_manually_load_plugin() {
    $dir = dirname(__DIR__);

    require $dir . '/devxpert-lead-dashboard-for-forminator.php';

    require_once $dir . '/includes/class-fld-roles.php';
    require_once $dir . '/includes/class-fld-database.php';
    require_once $dir . '/includes/class-fld-leads.php';
    require_once $dir . '/includes/class-fld-feedback.php';
    require_once $dir . '/includes/class-fld-otp.php';
    require_once $dir . '/includes/class-fld-notifications.php';
}
tests_add_filter('muplugins_loaded', '_dxleda_manually_load_plugin');

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
