<?php
/**
 * Telegram new-lead alerts.
 *
 * Pushes a message to a Telegram chat the moment a lead is captured, so the
 * sales team hears about it on their phone instead of waiting until somebody
 * opens wp-admin. Alerts are one-way: everything actionable still happens in
 * the dashboard, so no public callback endpoint is exposed.
 *
 * Every supported form plugin funnels into the shared "dxleda_lead_captured"
 * action, so this class needs no per-source capture code of its own.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends new-lead alerts to a Telegram chat.
 */
class DXLEDA_Telegram {

	/**
	 * Cron hook fired once per lead to perform the outbound request.
	 */
	const CRON_HOOK = 'dxleda_send_telegram_alert';

	/**
	 * Telegram hard-limits a message to 4096 characters. Stay under it with
	 * enough headroom that the footer link always survives.
	 */
	const MAX_MESSAGE = 3900;

	/**
	 * Per-field ceiling, so one long textarea cannot consume the whole message.
	 */
	const MAX_FIELD = 300;

	/**
	 * Seconds to wait on the Telegram API before giving up. The default
	 * wp_remote_post timeout of 5s is too tight for a cold connection.
	 */
	const TIMEOUT = 15;

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Runs after DXLEDA_Notifications (priority 10) so auto-assignment has
		// already happened and the alert can report the assignee.
		add_action( 'dxleda_lead_captured', array( __CLASS__, 'queue_alert' ), 20, 3 );

		// The actual HTTP request happens out-of-band on the cron event.
		add_action( self::CRON_HOOK, array( __CLASS__, 'send_alert' ), 10, 3 );
	}

	/**
	 * Whether alerts are switched on and fully configured.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		if ( ! get_option( 'dxleda_telegram_enabled', 0 ) ) {
			return false;
		}

		return '' !== self::bot_token() && '' !== self::chat_id();
	}

	/**
	 * Whether a given form should produce alerts.
	 *
	 * An empty opt-in list means "every form", which keeps the common case
	 * zero-configuration.
	 *
	 * Forms are identified by a "source|id" composite — the same convention the
	 * leads screen's form filter uses — because a form ID is only unique within
	 * its own source, so Forminator form 5 and CF7 form 5 are different forms.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $source  Source slug.
	 * @return bool
	 */
	public static function is_form_enabled( $form_id, $source = DXLEDA_Sources::FORMINATOR ) {
		$forms = get_option( 'dxleda_telegram_enabled_forms', array() );

		if ( ! is_array( $forms ) || empty( $forms ) ) {
			return true;
		}

		return in_array( self::form_key( $form_id, $source ), array_map( 'strval', $forms ), true );
	}

	/**
	 * Composite key identifying a form across sources.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $source  Source slug.
	 * @return string
	 */
	public static function form_key( $form_id, $source ) {
		return DXLEDA_Sources::sanitize( $source ) . '|' . (int) $form_id;
	}

	/**
	 * Decrypted bot token.
	 *
	 * The token goes straight into the request path, so it is constrained to
	 * the character set Telegram actually issues ("<digits>:<alphanumerics>").
	 * Stripping anything else keeps a mis-pasted value — a stray space or
	 * newline — from producing a malformed URL, and percent-encoding is
	 * avoided because an encoded colon is not reliably matched in a path.
	 *
	 * @return string
	 */
	public static function bot_token() {
		$token = trim( (string) DXLEDA_OTP::decrypt_secret( get_option( 'dxleda_telegram_bot_token', '' ) ) );

		return preg_replace( '/[^A-Za-z0-9:_-]/', '', $token );
	}

	/**
	 * Target chat ID.
	 *
	 * Kept as a string: group chat IDs are negative and channels can be given
	 * as "@channelusername", so this must not be cast to an integer.
	 *
	 * @return string
	 */
	public static function chat_id() {
		return trim( (string) get_option( 'dxleda_telegram_chat_id', '' ) );
	}

	/**
	 * Queue an alert for a freshly captured lead.
	 *
	 * Only the cheap checks run here — the outbound request is deferred to
	 * cron so the visitor's form submission never blocks on Telegram.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id  Form ID.
	 * @param string $source   Source slug.
	 */
	public static function queue_alert( $entry_id, $form_id, $source = DXLEDA_Sources::FORMINATOR ) {
		$entry_id = (int) $entry_id;
		$form_id  = (int) $form_id;
		$source   = DXLEDA_Sources::sanitize( $source );

		if ( $entry_id <= 0 || ! self::is_enabled() || ! self::is_form_enabled( $form_id, $source ) ) {
			return;
		}

		wp_schedule_single_event( time(), self::CRON_HOOK, array( $entry_id, $form_id, $source ) );
	}

	/**
	 * Cron callback: build and deliver the alert.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id  Form ID.
	 * @param string $source   Source slug.
	 * @return bool True when Telegram accepted the message.
	 */
	public static function send_alert( $entry_id, $form_id, $source = DXLEDA_Sources::FORMINATOR ) {
		$entry_id = (int) $entry_id;
		$source   = DXLEDA_Sources::sanitize( $source );

		// Re-check: settings may have changed between queueing and delivery.
		if ( ! self::is_enabled() ) {
			return false;
		}

		$lead = DXLEDA_Leads::get_lead( $entry_id, $source );

		if ( ! $lead ) {
			return false;
		}

		// The lead record already resolved its own form title, so prefer that
		// over the queued argument. They only disagree when a caller fired
		// dxleda_lead_captured with a wrong or stale form ID, and in that case
		// the alert would otherwise be headed "Form #1" instead of the real
		// title. Fall back to the argument only if the lead has no name.
		$form_name = ! empty( $lead['form_name'] )
			? $lead['form_name']
			: DXLEDA_Leads::form_name( $form_id, $source );

		$message = self::build_message( $lead, $form_name, $source );
		$result  = self::send_message( $message );

		if ( is_wp_error( $result ) ) {
			// Surface the failure where the team already looks — the lead's
			// own Activity Log — rather than a silent error_log() nobody reads.
			DXLEDA_Leads::log_activity(
				$entry_id,
				'telegram_failed',
				array( 'error' => $result->get_error_message() ),
				$source
			);

			return false;
		}

		return true;
	}

	/**
	 * Compose the alert body.
	 *
	 * Telegram's HTML parse mode is used rather than Markdown: MarkdownV2
	 * requires escaping some eighteen characters and legacy Markdown breaks on
	 * a stray underscore or asterisk in a submitted value, whereas HTML mode
	 * needs only three characters escaped.
	 *
	 * @param array  $lead      Lead array from DXLEDA_Leads::get_lead().
	 * @param string $form_name Form title.
	 * @param string $source    Source slug.
	 * @return string
	 */
	public static function build_message( $lead, $form_name, $source ) {
		$entry_id = isset( $lead['entry_id'] ) ? (int) $lead['entry_id'] : 0;

		$header = sprintf(
			/* translators: 1: form name, 2: form plugin name, e.g. Contact Form 7 */
			__( '&#128276; <b>New lead</b> from %1$s (%2$s)', 'devxpert-lead-dashboard-for-forminator' ),
			'<b>' . self::escape( $form_name ) . '</b>',
			self::escape( DXLEDA_Sources::label( $source ) )
		);

		$link = add_query_arg(
			array(
				'page'   => 'dxleda-leads',
				'entry'  => $entry_id,
				'source' => $source,
			),
			admin_url( 'admin.php' )
		);

		// esc_url() would encode the query separators as "&#038;"; the plain
		// "&amp;" that self::escape() produces is the safer bet for a
		// third-party HTML parser, and keeps the whole message consistently
		// escaped by one function.
		$footer = '<a href="' . self::escape( esc_url_raw( $link ) ) . '">'
			. self::escape( __( 'Open in dashboard', 'devxpert-lead-dashboard-for-forminator' ) )
			. '</a>';

		// Budget the field block so the header and footer always survive; the
		// alternative — truncating the finished string — could sever a tag.
		$budget = self::MAX_MESSAGE - self::length( $header ) - self::length( $footer ) - 4;

		$field_lines = array();
		$truncated   = false;

		foreach ( DXLEDA_Leads::humanize_fields( $lead ) as $field ) {
			$line = '<b>' . self::escape( $field['label'] ) . ':</b> '
				. self::escape( self::clip( $field['value'], self::MAX_FIELD ) );

			if ( self::length( $line ) + 1 > $budget ) {
				$truncated = true;
				break;
			}

			$field_lines[] = $line;
			$budget       -= self::length( $line ) + 1;
		}

		if ( $truncated ) {
			$field_lines[] = self::escape( __( '(truncated — open the dashboard for the full submission)', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		$parts = array( $header, '' );

		if ( ! empty( $field_lines ) ) {
			$parts[] = implode( "\n", $field_lines );
			$parts[] = '';
		}

		$parts[] = $footer;

		return implode( "\n", $parts );
	}

	/**
	 * POST a message to the Telegram Bot API.
	 *
	 * @param string $text Message body in HTML parse mode.
	 * @return true|WP_Error
	 */
	public static function send_message( $text ) {
		$token = self::bot_token();

		if ( '' === $token ) {
			return new WP_Error( 'dxleda_telegram_no_token', __( 'No Telegram bot token is configured.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . $token . '/sendMessage',
			array(
				'timeout' => self::TIMEOUT,
				'body'    => array(
					'chat_id'                  => self::chat_id(),
					'text'                     => $text,
					'parse_mode'               => 'HTML',
					// The dashboard link is behind wp-admin; a preview card
					// would only ever show a login page.
					'disable_web_page_preview' => 'true',
				),
			)
		);

		// Redact centrally, so no caller can leak the token by forgetting to.
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				$response->get_error_code(),
				self::redact( $response->get_error_message() )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Telegram reports application errors in the body, not the status code,
		// so a 200 alone is not success.
		if ( ! is_array( $body ) || empty( $body['ok'] ) ) {
			$description = '';

			if ( is_array( $body ) && ! empty( $body['description'] ) ) {
				$description = (string) $body['description'];
			}

			if ( '' === $description ) {
				$description = sprintf(
					/* translators: %d: HTTP status code */
					__( 'Telegram returned an unexpected response (HTTP %d).', 'devxpert-lead-dashboard-for-forminator' ),
					(int) wp_remote_retrieve_response_code( $response )
				);
			}

			return new WP_Error( 'dxleda_telegram_api_error', $description );
		}

		return true;
	}

	/**
	 * Send a fixed message so the user can verify their token and chat ID.
	 *
	 * @return true|WP_Error
	 */
	public static function send_test_message() {
		return self::send_message(
			sprintf(
				/* translators: %s: site name */
				__( '&#9989; <b>Test alert</b> from %s. Telegram lead alerts are working.', 'devxpert-lead-dashboard-for-forminator' ),
				'<b>' . self::escape( get_bloginfo( 'name' ) ) . '</b>'
			)
		);
	}

	/**
	 * Strip the bot token out of a message before it is stored or displayed.
	 *
	 * The token sits in the request path, and WordPress puts the full URL into
	 * the error message in at least one real case — a site running with
	 * WP_HTTP_BLOCK_EXTERNAL returns "User has blocked requests through HTTP to
	 * the URL: %s". Logging that verbatim would write the bot credential into
	 * the activity log, which Sales Admins can read even though they cannot
	 * reach the Settings screen that holds it.
	 *
	 * @param string $message Message that may embed the token.
	 * @return string
	 */
	private static function redact( $message ) {
		$message = (string) $message;
		$token   = self::bot_token();

		if ( '' === $token ) {
			return $message;
		}

		return str_replace( $token, '[redacted]', $message );
	}

	/**
	 * Escape a value for Telegram's HTML parse mode.
	 *
	 * Telegram asks for exactly the three characters below to be replaced;
	 * esc_html() would additionally convert quotes to entities, which is
	 * unnecessary noise inside message text.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape( $value ) {
		return str_replace(
			array( '&', '<', '>' ),
			array( '&amp;', '&lt;', '&gt;' ),
			(string) $value
		);
	}

	/**
	 * Shorten a raw value, before escaping, so no HTML entity is cut in half.
	 *
	 * @param string $value  Raw value.
	 * @param int    $length Maximum characters.
	 * @return string
	 */
	private static function clip( $value, $length ) {
		$value = (string) $value;

		if ( self::length( $value ) <= $length ) {
			return $value;
		}

		return rtrim( mb_substr( $value, 0, $length ) ) . '…';
	}

	/**
	 * Character length. Telegram counts characters, not bytes.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	private static function length( $value ) {
		return mb_strlen( (string) $value );
	}
}
