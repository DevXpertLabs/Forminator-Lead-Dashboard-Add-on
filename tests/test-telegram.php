<?php
/**
 * Tests for DXLEDA_Telegram message building and delivery.
 *
 * The network is stubbed through the pre_http_request filter, so these tests
 * assert on the request that would have been sent without ever contacting
 * Telegram.
 *
 * @package DevXpert_Lead_Dashboard
 */

/**
 * Telegram alert tests.
 */
class Test_DXLEDA_Telegram extends WP_UnitTestCase {

	/**
	 * Captured outbound request arguments, or null when nothing was sent.
	 *
	 * @var array|null
	 */
	private $captured = null;

	/**
	 * Canned response the stub returns, so individual tests can force failures.
	 *
	 * @var array
	 */
	private $stub_response = array();

	/**
	 * Install the HTTP stub and a working configuration.
	 */
	public function set_up() {
		parent::set_up();

		$this->captured      = null;
		$this->stub_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( array( 'ok' => true ) ),
		);

		update_option( 'dxleda_telegram_enabled', 1 );
		update_option( 'dxleda_telegram_bot_token', DXLEDA_OTP::encrypt_secret( '123456:TEST-TOKEN' ) );
		update_option( 'dxleda_telegram_chat_id', '-1001234567890' );
		update_option( 'dxleda_telegram_enabled_forms', array() );

		add_filter( 'pre_http_request', array( $this, 'stub_http' ), 10, 3 );
	}

	/**
	 * Remove the stub.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'stub_http' ), 10 );
		parent::tear_down();
	}

	/**
	 * Short-circuit every outbound HTTP request and record it.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 * @return array
	 */
	public function stub_http( $preempt, $args, $url ) {
		$this->captured = array(
			'url'  => $url,
			'args' => $args,
		);

		return $this->stub_response;
	}

	/**
	 * Build a lead array in the shape DXLEDA_Leads::get_lead() returns.
	 *
	 * @param array $meta Submitted field values.
	 * @return array
	 */
	private function make_lead( $meta ) {
		return array(
			'entry_id'     => 42,
			'form_id'      => 7,
			'source'       => DXLEDA_Sources::FORMINATOR,
			'source_label' => 'Forminator',
			'form_name'    => 'Contact Form',
			'date_created' => '2026-07-27 10:00:00',
			'status'       => 'new',
			'meta'         => $meta,
			'feedback'     => array(),
		);
	}

	/**
	 * Test that submitted values are escaped for Telegram's HTML parse mode.
	 */
	public function test_build_message_escapes_html_in_submitted_values() {
		$message = DXLEDA_Telegram::build_message(
			$this->make_lead( array( 'name' => '<script>alert("x")</script> Tom & Jerry' ) ),
			'Contact Form',
			DXLEDA_Sources::FORMINATOR
		);

		$this->assertStringContainsString( '&lt;script&gt;', $message );
		$this->assertStringContainsString( 'Tom &amp; Jerry', $message );
		$this->assertStringNotContainsString( '<script>', $message );
	}

	/**
	 * Test that the field label is humanized and rendered in bold.
	 */
	public function test_build_message_humanizes_field_labels() {
		$message = DXLEDA_Telegram::build_message(
			$this->make_lead( array( 'first-name-1' => 'Priya' ) ),
			'Contact Form',
			DXLEDA_Sources::FORMINATOR
		);

		$this->assertStringContainsString( '<b>First Name:</b> Priya', $message );
	}

	/**
	 * Test that a very long submission still fits inside Telegram's limit.
	 */
	public function test_build_message_stays_within_telegram_limit() {
		$meta = array();

		// Forty fields of 5000 characters each: far past the 4096 ceiling.
		for ( $i = 0; $i < 40; $i++ ) {
			$meta[ 'field_' . $i ] = str_repeat( 'a', 5000 );
		}

		$message = DXLEDA_Telegram::build_message( $this->make_lead( $meta ), 'Contact Form', DXLEDA_Sources::FORMINATOR );

		$this->assertLessThanOrEqual( 4096, mb_strlen( $message ) );
	}

	/**
	 * Test that the dashboard link survives truncation.
	 *
	 * The link is the whole point of the alert, so it must never be the thing
	 * that gets cut when a submission is oversized.
	 */
	public function test_build_message_keeps_dashboard_link_when_truncated() {
		$message = DXLEDA_Telegram::build_message(
			$this->make_lead( array( 'notes' => str_repeat( 'b', 20000 ) ) ),
			'Contact Form',
			DXLEDA_Sources::FORMINATOR
		);

		$this->assertStringContainsString( 'page=dxleda-leads', $message );
		$this->assertStringContainsString( 'entry=42', $message );
		$this->assertLessThanOrEqual( 4096, mb_strlen( $message ) );
	}

	/**
	 * Test that empty field values are omitted.
	 */
	public function test_build_message_skips_empty_fields() {
		$message = DXLEDA_Telegram::build_message(
			$this->make_lead(
				array(
					'name'  => 'Priya',
					'phone' => '',
				)
			),
			'Contact Form',
			DXLEDA_Sources::FORMINATOR
		);

		$this->assertStringContainsString( 'Priya', $message );
		$this->assertStringNotContainsString( 'Phone', $message );
	}

	/**
	 * Test that a successful send posts to the Telegram sendMessage endpoint.
	 */
	public function test_send_message_posts_expected_payload() {
		$result = DXLEDA_Telegram::send_message( 'hello' );

		$this->assertTrue( $result );
		$this->assertNotNull( $this->captured );
		$this->assertStringContainsString( '/bot123456:TEST-TOKEN/sendMessage', $this->captured['url'] );
		$this->assertSame( '-1001234567890', $this->captured['args']['body']['chat_id'] );
		$this->assertSame( 'HTML', $this->captured['args']['body']['parse_mode'] );
		$this->assertSame( 'hello', $this->captured['args']['body']['text'] );
	}

	/**
	 * Test that an application-level Telegram error surfaces its description.
	 *
	 * Telegram reports these with HTTP 200 and ok:false, so a status-code-only
	 * check would treat a failure as a success.
	 */
	public function test_send_message_returns_error_on_telegram_failure() {
		$this->stub_response = array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode(
				array(
					'ok'          => false,
					'description' => 'Bad Request: chat not found',
				)
			),
		);

		$result = DXLEDA_Telegram::send_message( 'hello' );

		$this->assertWPError( $result );
		$this->assertSame( 'Bad Request: chat not found', $result->get_error_message() );
	}

	/**
	 * Test that a transport error never carries the bot token back out.
	 *
	 * The token sits in the request path, and WordPress embeds the full URL in
	 * the error message when a site runs with WP_HTTP_BLOCK_EXTERNAL. That
	 * message is written to the activity log, which Sales Admins can read even
	 * though they cannot open the Settings screen holding the token.
	 */
	public function test_transport_error_redacts_the_bot_token() {
		$this->stub_response = new WP_Error(
			'http_request_not_executed',
			'User has blocked requests through HTTP to the URL: https://api.telegram.org/bot123456:TEST-TOKEN/sendMessage'
		);

		$result = DXLEDA_Telegram::send_message( 'hello' );

		$this->assertWPError( $result );
		$this->assertStringNotContainsString( '123456:TEST-TOKEN', $result->get_error_message() );
		$this->assertStringContainsString( '[redacted]', $result->get_error_message() );
	}

	/**
	 * Test that a missing token is reported rather than posted.
	 */
	public function test_send_message_requires_a_token() {
		update_option( 'dxleda_telegram_bot_token', '' );

		$this->assertWPError( DXLEDA_Telegram::send_message( 'hello' ) );
		$this->assertNull( $this->captured );
	}

	/**
	 * Test that alerts are considered configured only when fully set up.
	 */
	public function test_is_enabled_requires_switch_token_and_chat_id() {
		$this->assertTrue( DXLEDA_Telegram::is_enabled() );

		update_option( 'dxleda_telegram_enabled', 0 );
		$this->assertFalse( DXLEDA_Telegram::is_enabled() );

		update_option( 'dxleda_telegram_enabled', 1 );
		update_option( 'dxleda_telegram_chat_id', '' );
		$this->assertFalse( DXLEDA_Telegram::is_enabled() );

		update_option( 'dxleda_telegram_chat_id', '123' );
		update_option( 'dxleda_telegram_bot_token', '' );
		$this->assertFalse( DXLEDA_Telegram::is_enabled() );
	}

	/**
	 * Test that an empty opt-in list means every form is alerted on.
	 */
	public function test_empty_form_list_means_all_forms() {
		update_option( 'dxleda_telegram_enabled_forms', array() );

		$this->assertTrue( DXLEDA_Telegram::is_form_enabled( 7, DXLEDA_Sources::FORMINATOR ) );
		$this->assertTrue( DXLEDA_Telegram::is_form_enabled( 99, DXLEDA_Sources::CF7 ) );
	}

	/**
	 * Test that the opt-in list distinguishes forms across sources.
	 *
	 * Form IDs are only unique within a source, so Forminator form 5 and CF7
	 * form 5 must not be treated as the same form.
	 */
	public function test_form_opt_in_is_scoped_by_source() {
		update_option( 'dxleda_telegram_enabled_forms', array( DXLEDA_Sources::FORMINATOR . '|5' ) );

		$this->assertTrue( DXLEDA_Telegram::is_form_enabled( 5, DXLEDA_Sources::FORMINATOR ) );
		$this->assertFalse( DXLEDA_Telegram::is_form_enabled( 5, DXLEDA_Sources::CF7 ) );
		$this->assertFalse( DXLEDA_Telegram::is_form_enabled( 6, DXLEDA_Sources::FORMINATOR ) );
	}

	/**
	 * Test that the bot token round-trips through encryption at rest.
	 */
	public function test_bot_token_is_stored_encrypted() {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'OpenSSL is not available.' );
		}

		update_option( 'dxleda_telegram_bot_token', DXLEDA_OTP::encrypt_secret( '999:SECRET' ) );

		$this->assertStringStartsWith( 'fldenc:', get_option( 'dxleda_telegram_bot_token' ) );
		$this->assertSame( '999:SECRET', DXLEDA_Telegram::bot_token() );
	}

	/**
	 * Test that the header uses the lead's own form title, not the queued ID.
	 *
	 * A caller firing dxleda_lead_captured with a wrong form ID would otherwise
	 * produce an alert headed "Form #1" rather than the real form title.
	 */
	public function test_build_message_prefers_the_leads_own_form_name() {
		$lead = $this->make_lead( array( 'name' => 'Priya' ) );

		$message = DXLEDA_Telegram::build_message( $lead, $lead['form_name'], $lead['source'] );

		$this->assertStringContainsString( 'Contact Form', $message );
		$this->assertStringNotContainsString( 'Form #', $message );
	}

	/**
	 * Test that queueing schedules a cron event rather than sending inline.
	 *
	 * The visitor's form submission must never block on an outbound request.
	 */
	public function test_queue_alert_schedules_cron_and_sends_nothing_inline() {
		DXLEDA_Telegram::queue_alert( 42, 7, DXLEDA_Sources::FORMINATOR );

		$this->assertNull( $this->captured, 'queue_alert must not perform the HTTP request itself.' );
		$this->assertNotFalse(
			wp_next_scheduled( DXLEDA_Telegram::CRON_HOOK, array( 42, 7, DXLEDA_Sources::FORMINATOR ) )
		);
	}

	/**
	 * Test that nothing is queued while alerts are switched off.
	 */
	public function test_queue_alert_does_nothing_when_disabled() {
		update_option( 'dxleda_telegram_enabled', 0 );

		DXLEDA_Telegram::queue_alert( 43, 7, DXLEDA_Sources::FORMINATOR );

		$this->assertFalse(
			wp_next_scheduled( DXLEDA_Telegram::CRON_HOOK, array( 43, 7, DXLEDA_Sources::FORMINATOR ) )
		);
	}

	/**
	 * Test that a form left out of a non-empty opt-in list is not queued.
	 */
	public function test_queue_alert_respects_form_opt_in() {
		update_option( 'dxleda_telegram_enabled_forms', array( DXLEDA_Sources::FORMINATOR . '|5' ) );

		DXLEDA_Telegram::queue_alert( 44, 7, DXLEDA_Sources::FORMINATOR );

		$this->assertFalse(
			wp_next_scheduled( DXLEDA_Telegram::CRON_HOOK, array( 44, 7, DXLEDA_Sources::FORMINATOR ) )
		);
	}
}
