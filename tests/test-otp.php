<?php
/**
 * Smoke tests for DXLEDA_OTP (encryption + token lifecycle).
 */
class Test_DXLEDA_OTP extends WP_UnitTestCase {

	public function test_encrypt_decrypt_roundtrip() {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			$this->markTestSkipped( 'OpenSSL is not available.' );
		}

		$secret = 'brevo-SMTP-key_123!@#';
		$stored = DXLEDA_OTP::encrypt_secret( $secret );

		$this->assertStringStartsWith( 'fldenc:', $stored );
		$this->assertNotSame( $secret, $stored );
		$this->assertSame( $secret, DXLEDA_OTP::decrypt_secret( $stored ) );
	}

	public function test_decrypt_treats_unmarked_value_as_legacy_plaintext() {
		$this->assertSame( 'old-plain-pw', DXLEDA_OTP::decrypt_secret( 'old-plain-pw' ) );
	}

	public function test_encrypt_empty_returns_empty() {
		$this->assertSame( '', DXLEDA_OTP::encrypt_secret( '' ) );
	}

	public function test_verify_otp_then_consume_token() {
		$email = 'user@example.com';

		// Emulate a code having been sent (send_otp stores it this way).
		set_transient( 'dxleda_otp_' . md5( $email ), '123456', 600 );

		$token = DXLEDA_OTP::verify_otp( $email, '123456' );
		$this->assertNotEmpty( $token );
		$this->assertTrue( DXLEDA_OTP::verify_token( $token ) );

		// Correct code is single-use.
		$this->assertFalse( DXLEDA_OTP::verify_otp( $email, '123456' ) );

		DXLEDA_OTP::consume_token( $token );
		$this->assertFalse( DXLEDA_OTP::verify_token( $token ) );
	}

	public function test_verify_otp_rejects_wrong_code() {
		$email = 'user2@example.com';
		set_transient( 'dxleda_otp_' . md5( $email ), '111111', 600 );

		$this->assertFalse( DXLEDA_OTP::verify_otp( $email, '999999' ) );
	}

	public function test_form_not_enabled_by_default() {
		$this->assertFalse( DXLEDA_OTP::is_form_enabled( 4242 ) );
	}
}
