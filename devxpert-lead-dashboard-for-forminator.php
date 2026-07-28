<?php
/**
 * Plugin Name: DevXpert Lead Dashboard for Forminator & Contact Form 7
 * Plugin URI: https://github.com/DevXpertLabs/Forminator-Lead-Dashboard-Add-on
 * Description: A Lead Management Dashboard for Forminator and Contact Form 7. Track form submissions as leads, add feedback, categorize them by status, and export to CSV.
 * Version: 1.2.0
 * Author: Anup Kankale
 * Author URI: https://anupkankale.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: devxpert-lead-dashboard-for-forminator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * Requires Forminator or Contact Form 7 (either is enough; both can be used at
 * once). "Requires Plugins" is deliberately not declared because WordPress has
 * no way to express an either/or dependency, and declaring Forminator would
 * block Contact Form 7-only installs.
 *
 * @package DevXpert_Lead_Dashboard
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'DXLEDA_VERSION', '1.2.0' );
define( 'DXLEDA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DXLEDA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DXLEDA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main Plugin Class
 */
class DevXpert_Lead_Dashboard {

	/**
	 * Single instance of the class.
	 *
	 * @var DevXpert_Lead_Dashboard|null
	 */
	private static $instance = null;

	/**
	 * Get single instance
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Set up roles/caps early (before init).
		add_action( 'plugins_loaded', array( $this, 'setup_roles' ), 5 );

		// Check that at least one supported form plugin is active.
		add_action( 'plugins_loaded', array( $this, 'check_dependencies' ) );

		// Initialize plugin.
		add_action( 'plugins_loaded', array( $this, 'init' ) );

		// Activation hook.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Deactivation hook.
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Check that at least one supported form plugin is active.
	 *
	 * Either Forminator or Contact Form 7 is enough; the dashboard simply shows
	 * whichever sources are present.
	 */
	public function check_dependencies() {
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-sources.php';

		if ( ! DXLEDA_Sources::available() ) {
			add_action( 'admin_notices', array( $this, 'dependency_missing_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Admin notice when no supported form plugin is installed
	 */
	public function dependency_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'DevXpert Lead Dashboard requires either Forminator or Contact Form 7 to be installed and activated.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		// Translations are loaded automatically by WordPress.org for hosted
		// plugins (WP 4.6+), so no load_plugin_textdomain() call is needed.

		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Load includes.
		$this->includes();

		// Apply any pending schema changes (an in-place plugin update never
		// re-runs the activation hook).
		DXLEDA_Database::maybe_upgrade();

		// Seed SMTP defaults on first load (add_option is a no-op if already set).
		DXLEDA_OTP::init_defaults();

		// Capture Contact Form 7 submissions, which CF7 itself does not store.
		DXLEDA_CF7::init();

		// New-lead automation: email notifications + auto-assignment.
		DXLEDA_Notifications::init();

		// New-lead alerts pushed to Telegram (opt-in, one-way).
		DXLEDA_Telegram::init();

		// Read-only REST API for consuming leads outside wp-admin.
		DXLEDA_REST::init();

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

			// Sales Admin restrictions: lock down the WP admin to our pages only.
			add_action( 'admin_init', array( $this, 'restrict_sales_admin_access' ) );
			add_action( 'admin_menu', array( $this, 'restrict_sales_admin_menu' ), 999 );
		}

		// Redirect Sales Admin users to Lead Dashboard after login
		// login_redirect covers wp-login.php.
		add_filter( 'login_redirect', array( $this, 'sales_admin_login_redirect' ), 999, 3 );
		// wp_login covers generic custom login forms.
		add_action( 'wp_login', array( $this, 'sales_admin_wp_login_redirect' ), 999, 2 );
		// woocommerce_login_redirect covers WooCommerce My Account login.
		add_filter( 'woocommerce_login_redirect', array( $this, 'sales_admin_woo_login_redirect' ), 999, 2 );

		// Clean up WP admin bar for Sales Admins.
		add_action( 'admin_bar_menu', array( $this, 'restrict_sales_admin_toolbar' ), 999 );

		// Public OTP endpoints (front-end forms — accessible to guests).
		add_action( 'wp_ajax_nopriv_dxleda_send_otp', array( $this, 'ajax_send_otp' ) );
		add_action( 'wp_ajax_dxleda_send_otp', array( $this, 'ajax_send_otp' ) );
		add_action( 'wp_ajax_nopriv_dxleda_verify_otp', array( $this, 'ajax_verify_otp' ) );
		add_action( 'wp_ajax_dxleda_verify_otp', array( $this, 'ajax_verify_otp' ) );

		// Forminator server-side gate (runs before entry is saved).
		add_filter( 'forminator_custom_form_submit_errors', array( $this, 'check_otp_on_submit' ), 10, 3 );

		// Public asset enqueue for OTP widget.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_dxleda_get_leads', array( $this, 'ajax_get_leads' ) );
		add_action( 'wp_ajax_dxleda_update_lead_status', array( $this, 'ajax_update_lead_status' ) );
		add_action( 'wp_ajax_dxleda_add_feedback', array( $this, 'ajax_add_feedback' ) );
		add_action( 'wp_ajax_dxleda_get_feedback', array( $this, 'ajax_get_feedback' ) );
		add_action( 'wp_ajax_dxleda_delete_feedback', array( $this, 'ajax_delete_feedback' ) );
		add_action( 'wp_ajax_dxleda_get_dashboard_stats', array( $this, 'ajax_get_dashboard_stats' ) );
		add_action( 'wp_ajax_dxleda_export_leads', array( $this, 'ajax_export_leads' ) );
		add_action( 'wp_ajax_dxleda_get_lead', array( $this, 'ajax_get_lead' ) );

		add_action( 'wp_ajax_dxleda_get_activity', array( $this, 'ajax_get_activity' ) );

		// Role management AJAX — admin only.
		add_action( 'wp_ajax_dxleda_get_assignable_users', array( $this, 'ajax_get_assignable_users' ) );
		add_action( 'wp_ajax_dxleda_assign_sales_admin', array( $this, 'ajax_assign_sales_admin' ) );
		add_action( 'wp_ajax_dxleda_remove_sales_admin', array( $this, 'ajax_remove_sales_admin' ) );

		// Database tools AJAX — admin only.
		add_action( 'wp_ajax_dxleda_clear_activity_log', array( $this, 'ajax_clear_activity_log' ) );
		add_action( 'wp_ajax_dxleda_reset_statuses', array( $this, 'ajax_reset_statuses' ) );

		// Telegram connection test — admin only.
		add_action( 'wp_ajax_dxleda_test_telegram', array( $this, 'ajax_test_telegram' ) );
	}

	/**
	 * Set up roles and capabilities
	 */
	public function setup_roles() {
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-roles.php';
		DXLEDA_Roles::setup();
	}

	/**
	 * Include required files
	 */
	private function includes() {
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-roles.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-sources.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-database.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-leads.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-cf7.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-feedback.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-otp.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-notifications.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-telegram.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-rest.php';
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Set up roles and capabilities.
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-roles.php';
		DXLEDA_Roles::setup();

		// Create custom tables.
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-sources.php';
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-database.php';
		DXLEDA_Database::create_tables();

		// Set default options.
		add_option( 'dxleda_version', DXLEDA_VERSION );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		require_once DXLEDA_PLUGIN_DIR . 'includes/class-dxleda-roles.php';
		DXLEDA_Roles::teardown();
		flush_rewrite_rules();
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Main menu — visible to administrators and sales admins.
		add_menu_page(
			__( 'Lead Dashboard', 'devxpert-lead-dashboard-for-forminator' ),
			__( 'Lead Dashboard', 'devxpert-lead-dashboard-for-forminator' ),
			DXLEDA_Roles::CAP,
			'dxleda-dashboard',
			array( $this, 'render_dashboard_page' ),
			'dashicons-chart-line',
			30
		);

		// Submenu - Dashboard.
		add_submenu_page(
			'dxleda-dashboard',
			__( 'Dashboard', 'devxpert-lead-dashboard-for-forminator' ),
			__( 'Dashboard', 'devxpert-lead-dashboard-for-forminator' ),
			DXLEDA_Roles::CAP,
			'dxleda-dashboard',
			array( $this, 'render_dashboard_page' )
		);

		// Submenu - All Leads.
		add_submenu_page(
			'dxleda-dashboard',
			__( 'All Leads', 'devxpert-lead-dashboard-for-forminator' ),
			__( 'All Leads', 'devxpert-lead-dashboard-for-forminator' ),
			DXLEDA_Roles::CAP,
			'dxleda-leads',
			array( $this, 'render_leads_page' )
		);

		// Submenu - Settings — administrators only.
		add_submenu_page(
			'dxleda-dashboard',
			__( 'Settings', 'devxpert-lead-dashboard-for-forminator' ),
			__( 'Settings', 'devxpert-lead-dashboard-for-forminator' ),
			'manage_options',
			'dxleda-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on our plugin pages. The page hook is built from the
		// sanitized menu title ("lead-dashboard") + the page slug (e.g.
		// "dxleda-leads"), so match on our unique "dxleda" prefix, which every
		// plugin page slug contains.
		if ( strpos( $hook, 'dxleda' ) === false ) {
			return;
		}

		// CSS.
		wp_enqueue_style(
			'dxleda-admin-styles',
			DXLEDA_PLUGIN_URL . 'assets/css/admin-styles.css',
			array(),
			DXLEDA_VERSION
		);

		// Chart.js — bundled locally (WP.org does not permit external CDN scripts).
		wp_enqueue_script(
			'dxleda-chartjs',
			DXLEDA_PLUGIN_URL . 'assets/js/chart.min.js',
			array(),
			'4.5.1',
			true
		);

		// Admin JS.
		wp_enqueue_script(
			'dxleda-admin-scripts',
			DXLEDA_PLUGIN_URL . 'assets/js/admin-scripts.js',
			array( 'jquery', 'dxleda-chartjs' ),
			DXLEDA_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'dxleda-admin-scripts',
			'dxleda_ajax',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'dxleda_nonce' ),
				'strings'  => array(
					'confirm_delete' => __( 'Are you sure you want to delete this?', 'devxpert-lead-dashboard-for-forminator' ),
					'loading'        => __( 'Loading...', 'devxpert-lead-dashboard-for-forminator' ),
					'error'          => __( 'An error occurred. Please try again.', 'devxpert-lead-dashboard-for-forminator' ),
					'success'        => __( 'Success!', 'devxpert-lead-dashboard-for-forminator' ),
				),
			)
		);

		// Settings page: user-management + database-tools script (was inline).
		if ( strpos( $hook, 'dxleda-settings' ) !== false ) {
			wp_enqueue_script(
				'dxleda-settings',
				DXLEDA_PLUGIN_URL . 'assets/js/fld-settings.js',
				array( 'jquery', 'dxleda-admin-scripts' ),
				DXLEDA_VERSION,
				true
			);
			wp_localize_script(
				'dxleda-settings',
				'dxleda_settings_l10n',
				array(
					'select_user'    => __( 'Please select a user.', 'devxpert-lead-dashboard-for-forminator' ),
					'remove_confirm' => __( 'Remove Sales Admin role from', 'devxpert-lead-dashboard-for-forminator' ),
					'clear_confirm'  => __( 'Permanently delete the entire activity log? This cannot be undone.', 'devxpert-lead-dashboard-for-forminator' ),
					'reset_confirm'  => __( 'Reset every lead back to "new"? Assignments and statuses will be cleared. This cannot be undone.', 'devxpert-lead-dashboard-for-forminator' ),
					'saving'         => __( 'Saving…', 'devxpert-lead-dashboard-for-forminator' ),
					'no_admins'      => __( 'No Sales Admin users yet.', 'devxpert-lead-dashboard-for-forminator' ),
					'remove'         => __( 'Remove', 'devxpert-lead-dashboard-for-forminator' ),
				)
			);
		}
	}

	/**
	 * Render Dashboard Page
	 */
	public function render_dashboard_page() {
		include DXLEDA_PLUGIN_DIR . 'templates/dashboard.php';
	}

	/**
	 * Render Leads Page
	 */
	public function render_leads_page() {
		include DXLEDA_PLUGIN_DIR . 'templates/leads.php';
	}

	/**
	 * Render Settings Page (administrators only)
	 */
	public function render_settings_page() {
		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'devxpert-lead-dashboard-for-forminator' ) );
		}
		include DXLEDA_PLUGIN_DIR . 'templates/settings.php';
	}

	/**
	 * Remove unneeded WP admin bar nodes for Sales Admins.
	 * Keeps: site name (home link), user account, logout.
	 *
	 * @param mixed $wp_admin_bar The admin bar instance.
	 */
	public function restrict_sales_admin_toolbar( $wp_admin_bar ) {
		if ( ! DXLEDA_Roles::can_access() || DXLEDA_Roles::is_admin() ) {
			return;
		}

		$remove = array(
			'wp-logo',
			'about',
			'wporg',
			'documentation',
			'support-forums',
			'feedback',
			'site-name',
			'view-site',
			'updates',
			'comments',
			'new-content',
			'edit',
		);

		foreach ( $remove as $node ) {
			$wp_admin_bar->remove_node( $node );
		}
	}

	/**
	 * Redirect Sales Admin users to the Lead Dashboard immediately after login.
	 *
	 * @param mixed $redirect_to Redirect destination URL.
	 * @param mixed $request Request.
	 * @param mixed $user The logged-in user.
	 */
	public function sales_admin_login_redirect( $redirect_to, $request, $user ) {
		if ( $user instanceof WP_User && in_array( DXLEDA_Roles::ROLE_SLUG, (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=dxleda-dashboard' );
		}
		return $redirect_to;
	}

	/**
	 * Redirect Sales Admins after login via any non-wp-login.php form.
	 * wp_login fires on every successful authentication.
	 *
	 * @param string $user_login The user login name.
	 * @param mixed  $user The logged-in user.
	 */
	public function sales_admin_wp_login_redirect( $user_login, $user ) {
		if ( $user instanceof WP_User && in_array( DXLEDA_Roles::ROLE_SLUG, (array) $user->roles, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=dxleda-dashboard' ) );
			exit;
		}
	}

	/**
	 * Override WooCommerce's own login redirect for Sales Admin users.
	 * woocommerce_login_redirect filter is WooCommerce's final redirect decision.
	 *
	 * @param mixed $redirect Redirect.
	 * @param mixed $user The logged-in user.
	 */
	public function sales_admin_woo_login_redirect( $redirect, $user ) {
		if ( $user instanceof WP_User && in_array( DXLEDA_Roles::ROLE_SLUG, (array) $user->roles, true ) ) {
			return admin_url( 'admin.php?page=dxleda-dashboard' );
		}
		return $redirect;
	}

	/**
	 * Remove every WP admin menu item for Sales Admins except our own pages.
	 * Runs at admin_menu priority 999 (after all menus are registered).
	 */
	public function restrict_sales_admin_menu() {
		if ( ! DXLEDA_Roles::can_access() || DXLEDA_Roles::is_admin() ) {
			return;
		}

		global $menu;

		foreach ( $menu as $item ) {
			$slug = isset( $item[2] ) ? $item[2] : '';
			if ( $slug && 'dxleda-dashboard' !== $slug ) {
				remove_menu_page( $slug );
			}
		}
	}

	/**
	 * Prevent Sales Admins from visiting any admin page outside our plugin.
	 * Runs on admin_init.
	 */
	public function restrict_sales_admin_access() {
		// Only applies to Sales Admin (not administrators, not guests).
		if ( ! DXLEDA_Roles::can_access() || DXLEDA_Roles::is_admin() ) {
			return;
		}

		// AJAX calls are always permitted.
		if ( wp_doing_ajax() ) {
			return;
		}

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only access-control redirect based on the current page; no state is changed.
		$script       = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Pages Sales Admin is allowed to access.
		$allowed_pages = array( 'dxleda-dashboard', 'dxleda-leads' );

		// Allow our plugin pages.
		if ( 'admin.php' === $script && in_array( $current_page, $allowed_pages, true ) ) {
			return;
		}

		// Allow the user's own profile page (password change, etc.).
		if ( 'profile.php' === $script ) {
			return;
		}

		// Everything else → redirect to Lead Dashboard.
		wp_safe_redirect( admin_url( 'admin.php?page=dxleda-dashboard' ) );
		exit;
	}

	/**
	 * Read the form source from the current AJAX request.
	 *
	 * Falls back to Forminator, which is what every lead created before
	 * multi-source support belongs to.
	 *
	 * @return string
	 */
	private function posted_source() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- every caller runs check_ajax_referer() first.
		$raw = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';

		return DXLEDA_Sources::sanitize( $raw );
	}

	/**
	 * Accept a date only in the Y-m-d shape the date inputs produce.
	 *
	 * Anything else becomes an empty string, i.e. "no filter", rather than a
	 * partial date that would silently match nothing.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function sanitize_date( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$parsed = DateTime::createFromFormat( 'Y-m-d', $value );

		return ( $parsed && $parsed->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * AJAX: Get Leads
	 */
	public function ajax_get_leads() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$form_id  = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 20;
		$search   = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		// The leads screen has always sent these two, but they were never read
		// here, so the Date From / Date To filters did nothing.
		$date_from = isset( $_POST['date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['date_from'] ) ) : '';
		$date_to   = isset( $_POST['date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['date_to'] ) ) : '';

		// An empty source means "all sources", so this filter is read directly
		// rather than through posted_source(), which defaults to Forminator.
		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$source = DXLEDA_Sources::is_valid( $source ) ? $source : '';

		$leads = DXLEDA_Leads::get_leads(
			array(
				'form_id'   => $form_id,
				'source'    => $source,
				'status'    => $status,
				'page'      => $page,
				'per_page'  => $per_page,
				'search'    => $search,
				'date_from' => self::sanitize_date( $date_from ),
				'date_to'   => self::sanitize_date( $date_to ),
			)
		);

		wp_send_json_success( $leads );
	}

	/**
	 * AJAX: Get Single Lead by Entry ID
	 */
	public function ajax_get_lead() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( 'Invalid entry ID' );
		}

		$lead = DXLEDA_Leads::get_lead( $entry_id, $this->posted_source() );

		if ( ! $lead ) {
			wp_send_json_error( 'Lead not found' );
		}

		wp_send_json_success( $lead );
	}

	/**
	 * AJAX: Update Lead Status
	 */
	public function ajax_update_lead_status() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $entry_id || ! in_array( $status, array( 'new', 'positive', 'negative', 'follow_up', 'converted', 'closed' ), true ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$result = DXLEDA_Leads::update_lead_status( $entry_id, $status, array(), $this->posted_source() );

		if ( $result ) {
			wp_send_json_success( 'Status updated' );
		} else {
			wp_send_json_error( 'Failed to update status' );
		}
	}

	/**
	 * AJAX: Add Feedback
	 */
	public function ajax_add_feedback() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;
		$feedback = isset( $_POST['feedback'] ) ? sanitize_textarea_field( wp_unslash( $_POST['feedback'] ) ) : '';
		$rating   = isset( $_POST['rating'] ) ? sanitize_text_field( wp_unslash( $_POST['rating'] ) ) : 'neutral';

		if ( ! $entry_id || empty( $feedback ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		$result = DXLEDA_Feedback::add_feedback(
			array(
				'entry_id' => $entry_id,
				'source'   => $this->posted_source(),
				'feedback' => $feedback,
				'rating'   => $rating,
				'user_id'  => get_current_user_id(),
			)
		);

		if ( $result ) {
			wp_send_json_success(
				array(
					'message'     => 'Feedback added',
					'feedback_id' => $result,
				)
			);
		} else {
			wp_send_json_error( 'Failed to add feedback' );
		}
	}

	/**
	 * AJAX: Get Feedback
	 */
	public function ajax_get_feedback() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( 'Invalid entry ID' );
		}

		$feedback = DXLEDA_Feedback::get_feedback( $entry_id, $this->posted_source() );

		wp_send_json_success( $feedback );
	}

	/**
	 * AJAX: Delete Feedback
	 */
	public function ajax_delete_feedback() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$feedback_id = isset( $_POST['feedback_id'] ) ? intval( $_POST['feedback_id'] ) : 0;

		if ( ! $feedback_id ) {
			wp_send_json_error( 'Invalid feedback ID' );
		}

		// Sales admins can only delete their own feedback entries.
		if ( ! DXLEDA_Roles::is_admin() ) {
			$owner = DXLEDA_Feedback::get_feedback_owner( $feedback_id );
			if ( get_current_user_id() !== $owner ) {
				wp_send_json_error( 'You can only delete your own feedback' );
			}
		}

		$result = DXLEDA_Feedback::delete_feedback( $feedback_id );

		if ( $result ) {
			wp_send_json_success( 'Feedback deleted' );
		} else {
			wp_send_json_error( 'Failed to delete feedback' );
		}
	}

	/**
	 * AJAX: Get Dashboard Stats
	 */
	public function ajax_get_dashboard_stats() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$date_range = isset( $_POST['date_range'] ) ? sanitize_text_field( wp_unslash( $_POST['date_range'] ) ) : '30';

		$stats = DXLEDA_Leads::get_dashboard_stats( $date_range );

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Export Leads
	 */
	public function ajax_export_leads() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		// Empty means "all sources".
		$source = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$source = DXLEDA_Sources::is_valid( $source ) ? $source : '';

		$csv_data = DXLEDA_Leads::export_leads_csv( $form_id, $status, $source );

		wp_send_json_success( array( 'csv' => $csv_data ) );
	}

	/**
	 * AJAX: Get the activity log for a single lead
	 */
	public function ajax_get_activity() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::can_access() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? intval( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( 'Invalid entry ID' );
		}

		wp_send_json_success( DXLEDA_Leads::get_activity( $entry_id, $this->posted_source() ) );
	}

	/**
	 * AJAX: Clear the entire activity log (administrators only)
	 */
	public function ajax_clear_activity_log() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$removed = DXLEDA_Leads::clear_activity_log();

		wp_send_json_success(
			array(
				/* translators: %d: number of activity log rows removed */
				'message' => sprintf( __( 'Activity log cleared (%d entries removed).', 'devxpert-lead-dashboard-for-forminator' ), $removed ),
			)
		);
	}

	/**
	 * AJAX: Reset all lead statuses to "new" (administrators only)
	 */
	public function ajax_reset_statuses() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$removed = DXLEDA_Leads::reset_all_statuses();

		wp_send_json_success(
			array(
				/* translators: %d: number of leads reset to "new" */
				'message' => sprintf( __( 'All statuses reset to "new" (%d leads affected).', 'devxpert-lead-dashboard-for-forminator' ), $removed ),
			)
		);
	}

	/**
	 * AJAX: Send a Telegram test message using the saved credentials.
	 *
	 * Obtaining a chat ID is the one genuinely fiddly setup step, so this
	 * surfaces Telegram's own error text ("chat not found", "Unauthorized")
	 * rather than a generic failure.
	 */
	public function ajax_test_telegram() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		if ( '' === DXLEDA_Telegram::bot_token() || '' === DXLEDA_Telegram::chat_id() ) {
			wp_send_json_error( __( 'Enter a bot token and chat ID, save the settings, then test.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		$result = DXLEDA_Telegram::send_test_message();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Test message sent. Check your Telegram chat.', 'devxpert-lead-dashboard-for-forminator' ),
			)
		);
	}

	/**
	 * AJAX: Get all users that can be assigned the sales_admin role
	 */
	public function ajax_get_assignable_users() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// All WP users excluding current administrators.
		$all_users       = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
		$sales_admin_ids = array_map(
			function ( $u ) {
				return $u->ID;
			},
			DXLEDA_Roles::get_sales_admins()
		);

		$list = array();
		foreach ( $all_users as $user ) {
			if ( $user->has_cap( 'manage_options' ) ) {
				continue; // skip administrators.
			}
			$list[] = array(
				'id'             => $user->ID,
				'name'           => $user->display_name,
				'email'          => $user->user_email,
				'is_sales_admin' => in_array( $user->ID, $sales_admin_ids, true ),
			);
		}

		wp_send_json_success( $list );
	}

	/**
	 * AJAX: Assign sales_admin role to a user
	 */
	public function ajax_assign_sales_admin() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( 'Invalid user ID' );
		}

		if ( DXLEDA_Roles::assign( $user_id ) ) {
			$user = get_userdata( $user_id );
			wp_send_json_success(
				array(
					/* translators: %s: user display name */
					'message' => sprintf( __( '%s is now a Sales Admin.', 'devxpert-lead-dashboard-for-forminator' ), $user->display_name ),
				)
			);
		} else {
			wp_send_json_error( __( 'Could not assign role. Administrators cannot be changed.', 'devxpert-lead-dashboard-for-forminator' ) );
		}
	}

	/**
	 * AJAX: Remove sales_admin role from a user
	 */
	public function ajax_remove_sales_admin() {
		check_ajax_referer( 'dxleda_nonce', 'nonce' );

		if ( ! DXLEDA_Roles::is_admin() ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

		if ( ! $user_id ) {
			wp_send_json_error( 'Invalid user ID' );
		}

		if ( DXLEDA_Roles::remove( $user_id ) ) {
			$user = get_userdata( $user_id );
			wp_send_json_success(
				array(
					/* translators: %s: user display name */
					'message' => sprintf( __( '%s has been removed from Sales Admin.', 'devxpert-lead-dashboard-for-forminator' ), $user->display_name ),
				)
			);
		} else {
			wp_send_json_error( __( 'User is not a Sales Admin.', 'devxpert-lead-dashboard-for-forminator' ) );
		}
	}
	/**
	 * Enqueue public-facing assets for OTP widget (only when OTP is configured)
	 */
	public function enqueue_public_assets() {
		$enabled_forms = get_option( 'dxleda_otp_enabled_forms', array() );
		if ( empty( $enabled_forms ) ) {
			return;
		}

		wp_enqueue_style(
			'dxleda-otp-styles',
			DXLEDA_PLUGIN_URL . 'assets/css/fld-otp.css',
			array(),
			DXLEDA_VERSION
		);

		wp_enqueue_script(
			'dxleda-otp-script',
			DXLEDA_PLUGIN_URL . 'assets/js/fld-otp.js',
			array( 'jquery' ),
			DXLEDA_VERSION,
			true
		);

		wp_localize_script(
			'dxleda-otp-script',
			'dxleda_otp_config',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'dxleda_otp_nonce' ),
				'enabled_forms' => array_map( 'intval', (array) $enabled_forms ),
				'strings'       => array(
					'send_otp'     => __( 'Send Verification Code', 'devxpert-lead-dashboard-for-forminator' ),
					'verify'       => __( 'Verify', 'devxpert-lead-dashboard-for-forminator' ),
					'verified'     => __( 'Email Verified ✓', 'devxpert-lead-dashboard-for-forminator' ),
					'otp_sent'     => __( 'Code sent to your email. Check your inbox.', 'devxpert-lead-dashboard-for-forminator' ),
					'invalid_otp'  => __( 'Invalid or expired code. Please try again.', 'devxpert-lead-dashboard-for-forminator' ),
					'enter_email'  => __( 'Please enter your email address first.', 'devxpert-lead-dashboard-for-forminator' ),
					'otp_required' => __( 'Please verify your email before submitting.', 'devxpert-lead-dashboard-for-forminator' ),
					'resend'       => __( 'Resend Code', 'devxpert-lead-dashboard-for-forminator' ),
				),
			)
		);
	}

	/**
	 * AJAX: Send OTP to the submitted email address
	 */
	public function ajax_send_otp() {
		check_ajax_referer( 'dxleda_otp_nonce', 'nonce' );

		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;

		if ( ! is_email( $email ) || ! DXLEDA_OTP::is_form_enabled( $form_id ) ) {
			wp_send_json_error( __( 'Invalid request.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		$result = DXLEDA_OTP::send_otp( $email, $form_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Verify the OTP code and return a one-time token
	 */
	public function ajax_verify_otp() {
		check_ajax_referer( 'dxleda_otp_nonce', 'nonce' );

		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		$form_id = isset( $_POST['form_id'] ) ? intval( $_POST['form_id'] ) : 0;

		if ( ! DXLEDA_OTP::is_form_enabled( $form_id ) ) {
			wp_send_json_error( __( 'Invalid request.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		$token = DXLEDA_OTP::verify_otp( $email, $code, $form_id );

		if ( $token ) {
			wp_send_json_success( array( 'token' => $token ) );
		} else {
			wp_send_json_error( __( 'Invalid or expired code.', 'devxpert-lead-dashboard-for-forminator' ) );
		}
	}

	/**
	 * Forminator hook: block form submission if OTP is enabled but token is missing/invalid.
	 *
	 * Forminator AJAX only serializes its own registered fields, so the hidden
	 * dxleda_otp_token input injected by JS is often absent from $_POST.
	 * We check the cookie first (always present in XHR) then fall back to $_POST.
	 *
	 * @param mixed $errors Existing submission errors.
	 * @param int   $form_id Form ID.
	 * @param mixed $field_data_array Submitted field data.
	 */
	public function check_otp_on_submit( $errors, $form_id, $field_data_array ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- signature is fixed by Forminator's submit-errors filter.
		if ( ! DXLEDA_OTP::is_form_enabled( $form_id ) ) {
			return $errors;
		}

		// Cookie takes priority — reliably present in Forminator's AJAX request.
		// Forminator verifies its own submission nonce before this filter runs;
		// here we only read our one-time OTP token (itself validated below).
		$token = '';
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Forminator validates the form nonce; the OTP token is validated via DXLEDA_OTP::verify_token().
		if ( ! empty( $_COOKIE['dxleda_otp_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_COOKIE['dxleda_otp_token'] ) );
		} elseif ( ! empty( $_POST['dxleda_otp_token'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_POST['dxleda_otp_token'] ) );
		}
        // phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $token || ! DXLEDA_OTP::verify_token( $token, $form_id ) ) {
			$errors[] = __( 'Please verify your email address before submitting.', 'devxpert-lead-dashboard-for-forminator' );
			return $errors;
		}

		DXLEDA_OTP::consume_token( $token );

		// Clear the cookie server-side so it cannot be reused.
		setcookie( 'dxleda_otp_token', '', time() - 3600, '/', '', is_ssl(), false );

		return $errors;
	}
}

// Initialize plugin.
DevXpert_Lead_Dashboard::get_instance();
