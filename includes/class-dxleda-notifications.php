<?php
/**
 * New-lead automation: email notifications and auto-assignment.
 *
 * Every supported form plugin funnels into the shared "dxleda_lead_captured"
 * action, so that whenever a submission becomes a lead — whichever plugin it
 * came from — the configured team is notified and the lead is optionally
 * assigned to a default team member.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends new-lead email notifications and handles auto-assignment.
 */
class DXLEDA_Notifications {

	/**
	 * Register the source hooks and the shared handler.
	 */
	public static function init() {
		// Forminator fires this after an entry row has been saved.
		// Signature: do_action( 'forminator_form_after_save_entry', $form_id, $response ).
		add_action( 'forminator_form_after_save_entry', array( __CLASS__, 'on_forminator_lead' ), 20, 2 );

		// Contact Form 7 submissions are captured by DXLEDA_CF7, which fires
		// dxleda_lead_captured directly.
		add_action( 'dxleda_lead_captured', array( __CLASS__, 'on_new_lead' ), 10, 3 );
	}

	/**
	 * Translate Forminator's after-save event into the shared lead action.
	 *
	 * @param int   $form_id  Forminator form ID.
	 * @param array $response Save response; contains the new entry_id on success.
	 */
	public static function on_forminator_lead( $form_id, $response ) {
		// Only act on successful saves that produced an entry ID.
		if ( ! is_array( $response ) || empty( $response['success'] ) || empty( $response['entry_id'] ) ) {
			return;
		}

		do_action(
			'dxleda_lead_captured',
			intval( $response['entry_id'] ),
			intval( $form_id ),
			DXLEDA_Sources::FORMINATOR
		);
	}

	/**
	 * Handle a freshly captured lead from any source.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id Form ID.
	 * @param string $source Source slug.
	 */
	public static function on_new_lead( $entry_id, $form_id, $source = DXLEDA_Sources::FORMINATOR ) {
		$entry_id = intval( $entry_id );
		$form_id  = intval( $form_id );
		$source   = DXLEDA_Sources::sanitize( $source );

		self::maybe_auto_assign( $entry_id, $form_id, $source );
		self::maybe_notify( $entry_id, $form_id, $source );
	}

	/**
	 * Assign the lead to the default assignee when auto-assign is enabled.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id Form ID.
	 * @param string $source Source slug.
	 */
	private static function maybe_auto_assign( $entry_id, $form_id, $source ) {
		if ( ! get_option( 'dxleda_auto_assign', 0 ) ) {
			return;
		}

		$assignee = intval( get_option( 'dxleda_default_assignee', 0 ) );
		if ( $assignee <= 0 ) {
			return;
		}

		// Only assign to a user who can actually work leads.
		$user = get_userdata( $assignee );
		if ( ! $user || ( ! user_can( $user, DXLEDA_Roles::CAP ) && ! user_can( $user, 'manage_options' ) ) ) {
			return;
		}

		DXLEDA_Leads::assign_lead( $entry_id, $form_id, $assignee, $source );
	}

	/**
	 * Email the configured recipient about the new lead when enabled.
	 *
	 * @param int    $entry_id Entry ID.
	 * @param int    $form_id Form ID.
	 * @param string $source Source slug.
	 */
	private static function maybe_notify( $entry_id, $form_id, $source ) {
		if ( ! get_option( 'dxleda_email_notifications', 0 ) ) {
			return;
		}

		$to = sanitize_email( get_option( 'dxleda_notification_email', get_option( 'admin_email' ) ) );
		if ( ! is_email( $to ) ) {
			return;
		}

		$form_name = DXLEDA_Leads::form_name( $form_id, $source );
		$lead      = DXLEDA_Leads::get_lead( $entry_id, $source );

		$subject = sprintf(
			/* translators: 1: form name, 2: entry ID */
			__( '[%1$s] New lead #%2$d', 'devxpert-lead-dashboard-for-forminator' ),
			$form_name,
			$entry_id
		);

		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: form name, 2: form plugin name, e.g. Contact Form 7 */
			__( 'A new lead was submitted via "%1$s" (%2$s).', 'devxpert-lead-dashboard-for-forminator' ),
			$form_name,
			DXLEDA_Sources::label( $source )
		);
		$lines[] = '';

		if ( $lead && ! empty( $lead['meta'] ) && is_array( $lead['meta'] ) ) {
			foreach ( $lead['meta'] as $key => $value ) {
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
				$value = trim( (string) $value );
				if ( '' === $value ) {
					continue;
				}
				$lines[] = self::humanize_key( $key ) . ': ' . $value;
			}
			$lines[] = '';
		}

		$lines[] = __( 'View in the Lead Dashboard:', 'devxpert-lead-dashboard-for-forminator' );
		$lines[] = admin_url( 'admin.php?page=dxleda-leads' );

		$body = implode( "\n", $lines );

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Turn a form field key into a human-readable label.
	 *
	 * @param string $key Key.
	 */
	private static function humanize_key( $key ) {
		$key = preg_replace( '/-\d+$/', '', (string) $key );      // strip trailing "-1".
		$key = str_replace( array( '-', '_' ), ' ', $key );
		return ucwords( trim( $key ) );
	}
}
