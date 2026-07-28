<?php
/**
 * Settings Template — Administrators only
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! DXLEDA_Roles::is_admin() ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'devxpert-lead-dashboard-for-forminator' ) );
}

// Variables here live in the scope of the render_settings_page() method that
// include()s this template — not the global scope — so the global-prefix rule
// does not apply.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Handle form submission.
$dxleda_settings_nonce = isset( $_POST['dxleda_settings_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['dxleda_settings_nonce'] ) ) : '';
if ( isset( $_POST['dxleda_save_settings'] ) && wp_verify_nonce( $dxleda_settings_nonce, 'dxleda_save_settings' ) ) {
	update_option( 'dxleda_email_notifications', isset( $_POST['dxleda_email_notifications'] ) ? 1 : 0 );
	update_option( 'dxleda_notification_email', sanitize_email( wp_unslash( $_POST['dxleda_notification_email'] ?? '' ) ) );
	update_option( 'dxleda_auto_assign', isset( $_POST['dxleda_auto_assign'] ) ? 1 : 0 );
	update_option( 'dxleda_default_assignee', isset( $_POST['dxleda_default_assignee'] ) ? intval( $_POST['dxleda_default_assignee'] ) : 0 );
	update_option( 'dxleda_leads_per_page', isset( $_POST['dxleda_leads_per_page'] ) ? intval( $_POST['dxleda_leads_per_page'] ) : 20 );

	// Brevo SMTP / OTP settings.
	update_option( 'dxleda_smtp_host', sanitize_text_field( wp_unslash( $_POST['dxleda_smtp_host'] ?? '' ) ) );
	update_option( 'dxleda_smtp_port', intval( $_POST['dxleda_smtp_port'] ?? 587 ) );
	update_option( 'dxleda_smtp_username', sanitize_text_field( wp_unslash( $_POST['dxleda_smtp_username'] ?? '' ) ) );
	// Only update password if a new value was actually submitted (non-empty).
	// Stored encrypted at rest; decrypted only when sending mail.
	if ( ! empty( $_POST['dxleda_smtp_password'] ) ) {
		$dxleda_new_pw = sanitize_text_field( wp_unslash( $_POST['dxleda_smtp_password'] ) );
		update_option( 'dxleda_smtp_password', DXLEDA_OTP::encrypt_secret( $dxleda_new_pw ) );
	}
	update_option( 'dxleda_smtp_encryption', sanitize_text_field( wp_unslash( $_POST['dxleda_smtp_encryption'] ?? 'tls' ) ) );
	update_option( 'dxleda_brevo_sender_name', sanitize_text_field( wp_unslash( $_POST['dxleda_brevo_sender_name'] ?? get_bloginfo( 'name' ) ) ) );
	update_option( 'dxleda_brevo_sender_email', sanitize_email( wp_unslash( $_POST['dxleda_brevo_sender_email'] ?? '' ) ) );
	update_option( 'dxleda_otp_enabled_forms', array_map( 'intval', (array) ( $_POST['dxleda_otp_enabled_forms'] ?? array() ) ) );

	// Telegram alerts.
	update_option( 'dxleda_telegram_enabled', isset( $_POST['dxleda_telegram_enabled'] ) ? 1 : 0 );
	// Chat IDs are negative for groups and may be "@channelname", so this is
	// deliberately not cast to an integer.
	update_option( 'dxleda_telegram_chat_id', sanitize_text_field( wp_unslash( $_POST['dxleda_telegram_chat_id'] ?? '' ) ) );
	// As with the SMTP password: only overwrite when something was typed, and
	// store it encrypted at rest.
	if ( ! empty( $_POST['dxleda_telegram_bot_token'] ) ) {
		$dxleda_new_token = sanitize_text_field( wp_unslash( $_POST['dxleda_telegram_bot_token'] ) );
		update_option( 'dxleda_telegram_bot_token', DXLEDA_OTP::encrypt_secret( $dxleda_new_token ) );
	}
	// Keep only well-formed "source|id" composites.
	$dxleda_tg_forms = array_values(
		array_filter(
			array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['dxleda_telegram_enabled_forms'] ?? array() ) ) ),
			function ( $key ) {
				$parts = explode( '|', $key );
				return 2 === count( $parts ) && DXLEDA_Sources::is_valid( $parts[0] ) && ctype_digit( $parts[1] );
			}
		)
	);
	update_option( 'dxleda_telegram_enabled_forms', $dxleda_tg_forms );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'devxpert-lead-dashboard-for-forminator' ) . '</p></div>';
}

$email_notifications = get_option( 'dxleda_email_notifications', 0 );
$notification_email  = get_option( 'dxleda_notification_email', get_option( 'admin_email' ) );
$auto_assign         = get_option( 'dxleda_auto_assign', 0 );
$default_assignee    = get_option( 'dxleda_default_assignee', 0 );
$leads_per_page      = get_option( 'dxleda_leads_per_page', 20 );

// Brevo SMTP / OTP settings.
$smtp_host          = get_option( 'dxleda_smtp_host', 'smtp-relay.brevo.com' );
$smtp_port          = get_option( 'dxleda_smtp_port', 587 );
$smtp_username      = get_option( 'dxleda_smtp_username', '' );
$smtp_encryption    = get_option( 'dxleda_smtp_encryption', 'tls' );
$brevo_sender_name  = get_option( 'dxleda_brevo_sender_name', get_bloginfo( 'name' ) );
$brevo_sender_email = get_option( 'dxleda_brevo_sender_email', get_option( 'admin_email' ) );
$otp_enabled_forms  = array_map( 'intval', (array) get_option( 'dxleda_otp_enabled_forms', array() ) );
// OTP verification is gated on Forminator's submit-errors filter, so only
// Forminator forms can be protected. Contact Form 7 forms are deliberately
// excluded rather than listed and silently ignored.
$all_forms = array_values(
	array_filter(
		DXLEDA_Leads::get_forms(),
		function ( $form ) {
			return DXLEDA_Sources::FORMINATOR === $form['source'];
		}
	)
);

// Telegram alerts. Unlike OTP, alerts work for every source, so this list is
// not filtered — and forms are keyed by "source|id" because a form ID is only
// unique within its own source.
$telegram_enabled       = get_option( 'dxleda_telegram_enabled', 0 );
$telegram_chat_id       = get_option( 'dxleda_telegram_chat_id', '' );
$telegram_has_token     = '' !== DXLEDA_Telegram::bot_token();
$telegram_enabled_forms = array_map( 'strval', (array) get_option( 'dxleda_telegram_enabled_forms', array() ) );
$telegram_all_forms     = DXLEDA_Leads::get_forms();

$team_users   = DXLEDA_Roles::get_team_users();
$sales_admins = DXLEDA_Roles::get_sales_admins();
?>

<div class="wrap fld-settings-page">
	<div class="fld-hero">
		<div class="fld-hero-brand">
			<span class="fld-logo-mark" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="4"/></svg>
			</span>
			<div>
				<h1 class="fld-page-title"><?php esc_html_e( 'Lead Dashboard Settings', 'devxpert-lead-dashboard-for-forminator' ); ?></h1>
				<p class="fld-hero-sub"><?php esc_html_e( 'Notifications, spam protection, and team access.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
			</div>
		</div>
	</div>

	<form method="post" class="fld-settings-form">
		<?php wp_nonce_field( 'dxleda_save_settings', 'dxleda_settings_nonce' ); ?>

		<!-- General Settings -->
		<div class="fld-settings-section">
			<h2><?php esc_html_e( 'General Settings', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dxleda_leads_per_page"><?php esc_html_e( 'Leads Per Page', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="number" id="dxleda_leads_per_page" name="dxleda_leads_per_page"
								value="<?php echo esc_attr( $leads_per_page ); ?>" min="10" max="100">
						<p class="description"><?php esc_html_e( 'Number of leads to show per page in the leads list.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Notification Settings -->
		<div class="fld-settings-section">
			<h2><?php esc_html_e( 'Notification Settings', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dxleda_email_notifications"><?php esc_html_e( 'Email Notifications', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="dxleda_email_notifications" name="dxleda_email_notifications"
									value="1" <?php checked( $email_notifications, 1 ); ?>>
							<?php esc_html_e( 'Send email notifications for new leads', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_notification_email"><?php esc_html_e( 'Notification Email', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="email" id="dxleda_notification_email" name="dxleda_notification_email"
								value="<?php echo esc_attr( $notification_email ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Email address to receive new lead notifications.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Telegram Alerts -->
		<div class="fld-settings-section">
			<h2><?php esc_html_e( 'Telegram Alerts', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>

			<p class="description" style="margin-bottom:12px;">
				<?php esc_html_e( 'Push every new lead straight to a Telegram chat so your team sees it without opening WordPress. Alerts are one-way: tap the link in the message to work the lead in the dashboard.', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</p>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Setup: message @BotFather on Telegram and send /newbot to create a bot and get its token. Then message @userinfobot to get your personal chat ID, or add the bot to a group and use that group\'s ID. Save these settings, then use the test button below to confirm it works.', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dxleda_telegram_enabled"><?php esc_html_e( 'Telegram Alerts', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="dxleda_telegram_enabled" name="dxleda_telegram_enabled"
									value="1" <?php checked( $telegram_enabled, 1 ); ?>>
							<?php esc_html_e( 'Send a Telegram message for each new lead', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_telegram_bot_token"><?php esc_html_e( 'Bot Token', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="password" id="dxleda_telegram_bot_token" name="dxleda_telegram_bot_token"
								value="" class="regular-text" autocomplete="new-password"
								placeholder="<?php echo $telegram_has_token ? esc_attr__( 'Saved — leave blank to keep', 'devxpert-lead-dashboard-for-forminator' ) : esc_attr__( '123456789:AA...', 'devxpert-lead-dashboard-for-forminator' ); ?>">
						<p class="description">
							<?php esc_html_e( 'From @BotFather. Stored encrypted. Leave blank to keep the saved token.', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_telegram_chat_id"><?php esc_html_e( 'Chat ID', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="text" id="dxleda_telegram_chat_id" name="dxleda_telegram_chat_id"
								value="<?php echo esc_attr( $telegram_chat_id ); ?>" class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Where alerts are sent. A personal chat is a positive number, a group is negative (for example -1001234567890), and a channel can be given as @channelname.', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Test Connection', 'devxpert-lead-dashboard-for-forminator' ); ?></th>
					<td>
						<button type="button" class="button" id="dxleda-test-telegram">
							<?php esc_html_e( 'Send test message', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</button>
						<span id="dxleda-test-telegram-result" style="margin-left:10px;"></span>
						<p class="description">
							<?php esc_html_e( 'Save your settings first — the test uses the saved token and chat ID.', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Forms', 'devxpert-lead-dashboard-for-forminator' ); ?></th>
					<td>
						<?php if ( empty( $telegram_all_forms ) ) : ?>
							<p class="description"><?php esc_html_e( 'No forms found yet.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
						<?php else : ?>
							<?php foreach ( $telegram_all_forms as $telegram_form ) : ?>
								<?php $telegram_form_key = $telegram_form['source'] . '|' . intval( $telegram_form['id'] ); ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox"
											name="dxleda_telegram_enabled_forms[]"
											value="<?php echo esc_attr( $telegram_form_key ); ?>"
											<?php checked( in_array( $telegram_form_key, $telegram_enabled_forms, true ) ); ?>>
									<?php echo esc_html( $telegram_form['name'] ); ?>
									<span style="color:#999;font-size:12px;">
										(<?php echo esc_html( DXLEDA_Sources::label( $telegram_form['source'] ) ); ?>
										— ID: <?php echo esc_html( $telegram_form['id'] ); ?>)
									</span>
								</label>
							<?php endforeach; ?>
							<p class="description" style="margin-top:8px;">
								<?php esc_html_e( 'Leave every box unchecked to get alerts from all forms. Check specific forms to limit alerts to those.', 'devxpert-lead-dashboard-for-forminator' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p class="description">
				<?php esc_html_e( 'Alerts are sent in the background via WP-Cron so form submissions stay fast. If your site defines DISABLE_WP_CRON, make sure a real system cron is running or alerts will be delayed. Delivery failures are recorded in each lead\'s Activity Log.', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</p>
		</div>

		<!-- Assignment Settings -->
		<div class="fld-settings-section">
			<h2><?php esc_html_e( 'Lead Assignment', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dxleda_auto_assign"><?php esc_html_e( 'Auto-Assign Leads', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" id="dxleda_auto_assign" name="dxleda_auto_assign"
									value="1" <?php checked( $auto_assign, 1 ); ?>>
							<?php esc_html_e( 'Automatically assign new leads to a team member', 'devxpert-lead-dashboard-for-forminator' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_default_assignee"><?php esc_html_e( 'Default Assignee', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<select id="dxleda_default_assignee" name="dxleda_default_assignee">
							<option value="0"><?php esc_html_e( '— Select —', 'devxpert-lead-dashboard-for-forminator' ); ?></option>
							<?php foreach ( $team_users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $default_assignee, $user->ID ); ?>>
									<?php echo esc_html( $user->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Team member to auto-assign new leads to.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Status Labels -->
		<div class="fld-settings-section">
			<h2><?php esc_html_e( 'Lead Statuses', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>
			<p class="description"><?php esc_html_e( 'These are the available lead statuses:', 'devxpert-lead-dashboard-for-forminator' ); ?></p>

			<div class="fld-status-list">
				<div class="fld-status-item">
					<span class="fld-status-badge fld-status-new"><?php esc_html_e( 'New', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
					<span class="fld-status-desc"><?php esc_html_e( 'Newly submitted leads', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
				</div>
				<div class="fld-status-item">
					<span class="fld-status-badge fld-status-positive"><?php esc_html_e( 'Positive', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
					<span class="fld-status-desc"><?php esc_html_e( 'Qualified, interested leads', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
				</div>
				<div class="fld-status-item">
					<span class="fld-status-badge fld-status-negative"><?php esc_html_e( 'Negative', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
					<span class="fld-status-desc"><?php esc_html_e( 'Unqualified or uninterested leads', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
				</div>
				<div class="fld-status-item">
					<span class="fld-status-badge fld-status-follow_up"><?php esc_html_e( 'Follow Up', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
					<span class="fld-status-desc"><?php esc_html_e( 'Requires follow-up action', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
				</div>
				<div class="fld-status-item">
					<span class="fld-status-badge fld-status-converted"><?php esc_html_e( 'Converted', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
					<span class="fld-status-desc"><?php esc_html_e( 'Lead converted to customer', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
				</div>
				<div class="fld-status-item">
					<span class="fld-status-badge fld-status-closed"><?php esc_html_e( 'Closed', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
					<span class="fld-status-desc"><?php esc_html_e( 'Lead closed/archived', 'devxpert-lead-dashboard-for-forminator' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Spam Prevention — Brevo SMTP OTP -->
		<div class="fld-settings-section">
			<h2><?php esc_html_e( 'Spam Prevention — Email OTP', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Require visitors to verify their email via a one-time code sent through Brevo SMTP before a form submission becomes a lead.', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</p>

			<h3 style="margin-top:16px;"><?php esc_html_e( 'Brevo SMTP Settings', 'devxpert-lead-dashboard-for-forminator' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dxleda_smtp_host"><?php esc_html_e( 'SMTP Host', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="text" id="dxleda_smtp_host" name="dxleda_smtp_host"
								value="<?php echo esc_attr( $smtp_host ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_smtp_port"><?php esc_html_e( 'SMTP Port', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="number" id="dxleda_smtp_port" name="dxleda_smtp_port"
								value="<?php echo esc_attr( $smtp_port ); ?>" min="1" max="65535" style="width:100px;">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_smtp_encryption"><?php esc_html_e( 'Encryption', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<select id="dxleda_smtp_encryption" name="dxleda_smtp_encryption">
							<option value="tls"  <?php selected( $smtp_encryption, 'tls' ); ?>>TLS (STARTTLS — Port 587)</option>
							<option value="ssl"  <?php selected( $smtp_encryption, 'ssl' ); ?>>SSL — Port 465</option>
							<option value=""     <?php selected( $smtp_encryption, '' ); ?>>None</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_smtp_username"><?php esc_html_e( 'SMTP Username', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="text" id="dxleda_smtp_username" name="dxleda_smtp_username"
								value="<?php echo esc_attr( $smtp_username ); ?>" class="regular-text"
								autocomplete="off">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_smtp_password"><?php esc_html_e( 'SMTP Password', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="password" id="dxleda_smtp_password" name="dxleda_smtp_password"
								value="" placeholder="<?php esc_attr_e( 'Leave blank to keep current password', 'devxpert-lead-dashboard-for-forminator' ); ?>"
								class="regular-text" autocomplete="new-password">
						<p class="description"><?php esc_html_e( 'Leave blank to keep the saved password. Enter a new value only if you want to change it.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
					</td>
				</tr>
			</table>

			<h3 style="margin-top:20px;"><?php esc_html_e( 'Sender Identity', 'devxpert-lead-dashboard-for-forminator' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="dxleda_brevo_sender_name"><?php esc_html_e( 'From Name', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="text" id="dxleda_brevo_sender_name" name="dxleda_brevo_sender_name"
								value="<?php echo esc_attr( $brevo_sender_name ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="dxleda_brevo_sender_email"><?php esc_html_e( 'From Email', 'devxpert-lead-dashboard-for-forminator' ); ?></label>
					</th>
					<td>
						<input type="email" id="dxleda_brevo_sender_email" name="dxleda_brevo_sender_email"
								value="<?php echo esc_attr( $brevo_sender_email ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Must match a verified sender in your Brevo account.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
					</td>
				</tr>
			</table>

			<h3 style="margin-top:20px;"><?php esc_html_e( 'Enable OTP for Forms', 'devxpert-lead-dashboard-for-forminator' ); ?></h3>
			<p class="description" style="margin-bottom:10px;">
				<?php esc_html_e( 'Email verification is currently available for Forminator forms only.', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Protected Forms', 'devxpert-lead-dashboard-for-forminator' ); ?></th>
					<td>
						<?php if ( empty( $all_forms ) ) : ?>
							<p class="description"><?php esc_html_e( 'No Forminator forms found.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>
						<?php else : ?>
							<?php foreach ( $all_forms as $form ) : ?>
								<label style="display:block;margin-bottom:6px;">
									<input type="checkbox"
											name="dxleda_otp_enabled_forms[]"
											value="<?php echo esc_attr( $form['id'] ); ?>"
											<?php checked( in_array( intval( $form['id'] ), $otp_enabled_forms, true ) ); ?>>
									<?php echo esc_html( $form['name'] ); ?>
									<span style="color:#999;font-size:12px;">(ID: <?php echo esc_html( $form['id'] ); ?>)</span>
								</label>
							<?php endforeach; ?>
							<p class="description" style="margin-top:8px;">
								<?php esc_html_e( 'Checked forms require email verification before submission is accepted as a lead.', 'devxpert-lead-dashboard-for-forminator' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<input type="submit" name="dxleda_save_settings" class="button button-primary button-large"
					value="<?php esc_attr_e( 'Save Settings', 'devxpert-lead-dashboard-for-forminator' ); ?>">
		</p>
	</form>

	<!-- ============================================================
		Sales Admin User Management — visible to administrators only
		============================================================ -->
	<div class="fld-settings-section fld-user-management">
		<h2><?php esc_html_e( 'Sales Admin Users', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: 1: opening <strong> tag, 2: closing </strong> tag */
				esc_html__( 'Users with the %1$sSales Admin%2$s role can log in and access the Lead Dashboard. They can view all leads and add feedback. Only Administrators can access Settings.', 'devxpert-lead-dashboard-for-forminator' ),
				'<strong>',
				'</strong>'
			);
			?>
		</p>

		<!-- Current Sales Admins -->
		<h3><?php esc_html_e( 'Current Sales Admins', 'devxpert-lead-dashboard-for-forminator' ); ?></h3>
		<table class="wp-list-table widefat fixed striped" id="fld-sales-admin-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'devxpert-lead-dashboard-for-forminator' ); ?></th>
					<th><?php esc_html_e( 'Email', 'devxpert-lead-dashboard-for-forminator' ); ?></th>
					<th><?php esc_html_e( 'Action', 'devxpert-lead-dashboard-for-forminator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $sales_admins ) ) : ?>
					<tr id="fld-no-sales-admins">
						<td colspan="3"><?php esc_html_e( 'No Sales Admin users yet.', 'devxpert-lead-dashboard-for-forminator' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $sales_admins as $sa_user ) : ?>
						<tr id="fld-sa-row-<?php echo esc_attr( $sa_user->ID ); ?>">
							<td><?php echo esc_html( $sa_user->display_name ); ?></td>
							<td><?php echo esc_html( $sa_user->user_email ); ?></td>
							<td>
								<button class="button fld-remove-sales-admin"
										data-id="<?php echo esc_attr( $sa_user->ID ); ?>"
										data-name="<?php echo esc_attr( $sa_user->display_name ); ?>">
									<?php esc_html_e( 'Remove', 'devxpert-lead-dashboard-for-forminator' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Add Sales Admin -->
		<h3 style="margin-top:24px;"><?php esc_html_e( 'Add Sales Admin', 'devxpert-lead-dashboard-for-forminator' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Assign the Sales Admin role to any existing WordPress user (except administrators).', 'devxpert-lead-dashboard-for-forminator' ); ?></p>

		<div class="fld-add-sales-admin-form">
			<select id="fld-assign-user-select" style="min-width:280px;">
				<option value=""><?php esc_html_e( '— Select a user —', 'devxpert-lead-dashboard-for-forminator' ); ?></option>
			</select>
			<button id="fld-assign-sales-admin" class="button button-primary">
				<?php esc_html_e( 'Add as Sales Admin', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</button>
			<span id="fld-assign-status" style="margin-left:12px;"></span>
		</div>
	</div>

	<!-- Database Tools -->
	<div class="fld-settings-section fld-danger-zone">
		<h2><?php esc_html_e( 'Database Tools', 'devxpert-lead-dashboard-for-forminator' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use these tools with caution.', 'devxpert-lead-dashboard-for-forminator' ); ?></p>

		<div class="fld-tools">
			<button id="fld-clear-activity" class="button">
				<?php esc_html_e( 'Clear Activity Log', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</button>
			<button id="fld-reset-statuses" class="button">
				<?php esc_html_e( 'Reset All Statuses', 'devxpert-lead-dashboard-for-forminator' ); ?>
			</button>
			<span id="fld-db-tool-status" style="margin-left:12px;"></span>
		</div>
	</div>
</div>
