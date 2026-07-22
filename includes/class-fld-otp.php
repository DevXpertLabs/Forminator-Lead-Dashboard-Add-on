<?php
/**
 * OTP Handler — Email verification via Brevo SMTP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DXLEDA_OTP {

	const OTP_TTL   = 600;   // 10 minutes
	const TOKEN_TTL = 1800;  // 30 minutes
	const RATE_MAX  = 5;     // max OTP sends per IP per hour.

	/**
	 * Set default SMTP credentials on first activation / first load.
	 * Uses add_option() so existing saved values are never overwritten.
	 */
	public static function init_defaults() {
		add_option( 'dxleda_smtp_host', 'smtp-relay.brevo.com' );
		add_option( 'dxleda_smtp_port', '587' );
		add_option( 'dxleda_smtp_username', '' );
		add_option( 'dxleda_smtp_password', '' );
		add_option( 'dxleda_smtp_encryption', 'tls' );
		add_option( 'dxleda_brevo_sender_name', get_bloginfo( 'name' ) );
		add_option( 'dxleda_brevo_sender_email', get_option( 'admin_email' ) );
	}

	/**
	 * Check whether OTP is enabled for a given form ID.
	 */
	public static function is_form_enabled( $form_id ) {
		$enabled = get_option( 'dxleda_otp_enabled_forms', array() );
		return in_array( intval( $form_id ), array_map( 'intval', (array) $enabled ), true );
	}

	/**
	 * Generate a 6-digit OTP, store it in a transient, and send it via SMTP.
	 *
	 * @return true|WP_Error
	 */
	public static function send_otp( $email, $form_id ) {
		$email = strtolower( trim( $email ) );

		// Rate limiting per IP.
		$ip       = self::get_client_ip();
		$rate_key = 'dxleda_otp_rate_' . md5( $ip );
		$attempts = (int) get_transient( $rate_key );

		if ( $attempts >= self::RATE_MAX ) {
			return new WP_Error( 'rate_limit', __( 'Too many verification requests. Please try again in an hour.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		set_transient( $rate_key, $attempts + 1, HOUR_IN_SECONDS );

		// Generate and store OTP, scoped to this email + form so a code issued
		// for one protected form cannot satisfy another.
		$code    = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
		$otp_key = self::otp_key( $email, $form_id );
		set_transient( $otp_key, $code, self::OTP_TTL );

		// Build and send email.
		$site_name = get_option( 'dxleda_brevo_sender_name', get_bloginfo( 'name' ) );
		/* translators: %s: site name */
		$subject = sprintf( __( '[%s] Your verification code', 'devxpert-lead-dashboard-for-forminator' ), $site_name );
		$html    = self::build_email_html( $code, $site_name );

		return self::smtp_send( $email, $subject, $html );
	}

	/**
	 * Verify a submitted OTP code for a specific form.
	 * Returns a one-time token on success, false on failure.
	 *
	 * @return string|false
	 */
	public static function verify_otp( $email, $code, $form_id ) {
		$email   = strtolower( trim( $email ) );
		$otp_key = self::otp_key( $email, $form_id );
		$stored  = get_transient( $otp_key );

		if ( false === $stored || trim( $code ) !== $stored ) {
			return false;
		}

		// Invalidate OTP immediately after use.
		delete_transient( $otp_key );

		// Issue a one-time verification token, bound to this email + form.
		$token     = wp_generate_password( 32, false );
		$token_key = 'dxleda_otp_token_' . $token;
		set_transient( $token_key, $email . '|' . intval( $form_id ), self::TOKEN_TTL );

		return $token;
	}

	/**
	 * Check whether a verification token is valid AND bound to the given form.
	 */
	public static function verify_token( $token, $form_id ) {
		if ( empty( $token ) ) {
			return false;
		}
		$stored = get_transient( 'dxleda_otp_token_' . sanitize_text_field( $token ) );
		if ( false === $stored ) {
			return false;
		}
		$parts         = explode( '|', $stored );
		$token_form_id = isset( $parts[1] ) ? intval( $parts[1] ) : 0;

		return intval( $form_id ) === $token_form_id;
	}

	/**
	 * Build the transient key for an email + form pair.
	 */
	private static function otp_key( $email, $form_id ) {
		return 'dxleda_otp_' . md5( strtolower( trim( $email ) ) . '|' . intval( $form_id ) );
	}

	/**
	 * Delete a token after successful form submission.
	 */
	public static function consume_token( $token ) {
		delete_transient( 'dxleda_otp_token_' . sanitize_text_field( $token ) );
	}

	/**
	 * Send an email via SMTP using wp_mail() + phpmailer_init hook.
	 *
	 * @return true|WP_Error
	 */
	private static function smtp_send( $to_email, $subject, $html ) {
		$username     = get_option( 'dxleda_smtp_username', '' );
		$password     = self::decrypt_secret( get_option( 'dxleda_smtp_password', '' ) );
		$sender_name  = get_option( 'dxleda_brevo_sender_name', get_bloginfo( 'name' ) );
		$sender_email = get_option( 'dxleda_brevo_sender_email', get_option( 'admin_email' ) );

		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error( 'no_smtp_creds', __( 'SMTP credentials are not configured.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		if ( ! is_email( $sender_email ) ) {
			return new WP_Error( 'invalid_sender', __( 'Sender email address is not valid.', 'devxpert-lead-dashboard-for-forminator' ) );
		}

		// Hook phpmailer only for this send.
		add_action( 'phpmailer_init', array( 'DXLEDA_OTP', 'configure_phpmailer' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $sender_name . ' <' . $sender_email . '>',
		);

		$sent = wp_mail( $to_email, $subject, $html, $headers );

		remove_action( 'phpmailer_init', array( 'DXLEDA_OTP', 'configure_phpmailer' ) );

		if ( ! $sent ) {
			global $phpmailer;
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- ErrorInfo is PHPMailer's own property name.
			$error_info = isset( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ? $phpmailer->ErrorInfo : '';
			return new WP_Error( 'mail_failed', __( 'Failed to send OTP email.', 'devxpert-lead-dashboard-for-forminator' ) . ( $error_info ? ' ' . $error_info : '' ) );
		}

		return true;
	}

	/**
	 * Configure PHPMailer to use Brevo SMTP.
	 * Called via phpmailer_init action — must be public static.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer
	 */
	public static function configure_phpmailer( $phpmailer ) {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer's own property names.
		$phpmailer->isSMTP();
		$phpmailer->Host       = get_option( 'dxleda_smtp_host', 'smtp-relay.brevo.com' );
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Port       = intval( get_option( 'dxleda_smtp_port', 587 ) );
		$phpmailer->Username   = get_option( 'dxleda_smtp_username', '' );
		$phpmailer->Password   = self::decrypt_secret( get_option( 'dxleda_smtp_password', '' ) );
		$phpmailer->SMTPSecure = get_option( 'dxleda_smtp_encryption', 'tls' );
		// phpcs:enable
	}

	/**
	 * Encryption marker for stored secrets.
	 */
	const ENC_PREFIX = 'fldenc:';

	/**
	 * Derive a 32-byte key from the site's auth salt.
	 */
	private static function secret_key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}

	/**
	 * Encrypt a secret for storage in wp_options.
	 * Falls back to plaintext if OpenSSL is unavailable.
	 *
	 * @param string $plain
	 * @return string
	 */
	public static function encrypt_secret( $plain ) {
		$plain = (string) $plain;

		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) ) {
			return $plain;
		}

		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'aes-256-cbc', self::secret_key(), OPENSSL_RAW_DATA, $iv );

		if ( false === $cipher ) {
			return $plain;
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage encoding for the encrypted secret, not obfuscation.
		return self::ENC_PREFIX . base64_encode( $iv . $cipher );
	}

	/**
	 * Decrypt a stored secret. Values without the marker are treated as
	 * legacy plaintext and returned unchanged.
	 *
	 * @param string $stored
	 * @return string
	 */
	public static function decrypt_secret( $stored ) {
		$stored = (string) $stored;

		if ( strpos( $stored, self::ENC_PREFIX ) !== 0 || ! function_exists( 'openssl_decrypt' ) ) {
			return $stored; // legacy plaintext or OpenSSL missing.
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding our own stored ciphertext, not obfuscated code.
		$raw = base64_decode( substr( $stored, strlen( self::ENC_PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= 16 ) {
			return '';
		}

		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'aes-256-cbc', self::secret_key(), OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Build the OTP email HTML body.
	 */
	private static function build_email_html( $code, $site_name ) {
		return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center">
        <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
          <tr>
            <td style="background:#2271b1;padding:24px 32px;">
              <h1 style="color:#ffffff;margin:0;font-size:20px;">' . esc_html( $site_name ) . '</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:32px;">
              <p style="color:#333;font-size:15px;margin:0 0 24px;">Your email verification code is:</p>
              <div style="background:#f0f4f8;border-radius:6px;padding:20px;text-align:center;margin-bottom:24px;">
                <span style="font-size:36px;font-weight:700;letter-spacing:10px;color:#2271b1;">' . esc_html( $code ) . '</span>
              </div>
              <p style="color:#666;font-size:13px;margin:0;">This code expires in <strong>10 minutes</strong>. Do not share it with anyone.</p>
            </td>
          </tr>
          <tr>
            <td style="background:#f9f9f9;padding:16px 32px;border-top:1px solid #eee;">
              <p style="color:#999;font-size:12px;margin:0;">If you did not request this code, you can safely ignore this email.</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
	}

	/**
	 * Get the real client IP, respecting common proxy headers.
	 */
	private static function get_client_ip() {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}
