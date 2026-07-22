<?php
/**
 * Contact Form 7 Capture
 *
 * Contact Form 7 does not persist submissions — it mails them and throws them
 * away. This class listens for submissions and stores them in the plugin's own
 * tables so they can be managed as leads.
 *
 * Only submissions received after activation are captured; there is no history
 * to import.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures Contact Form 7 submissions into the plugin's own tables,
 * since CF7 does not store entries itself.
 */
class DXLEDA_CF7 {

	/**
	 * Register hooks. No-op when Contact Form 7 is not active.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! DXLEDA_Sources::is_available( DXLEDA_Sources::CF7 ) ) {
			return;
		}

		// wpcf7_submit fires for every submission attempt, with the outcome.
		add_action( 'wpcf7_submit', array( __CLASS__, 'capture' ), 10, 2 );
	}

	/**
	 * Store a submission as a lead.
	 *
	 * @param WPCF7_ContactForm $contact_form The CF7 form instance.
	 * @param array             $result       CF7 submission result.
	 * @return void
	 */
	public static function capture( $contact_form, $result ) {
		$status = isset( $result['status'] ) ? $result['status'] : '';

		// mail_sent and mail_failed both mean a real person passed validation;
		// the mail transport failing is not the lead's fault. Spam and
		// validation failures are not leads.
		if ( ! in_array( $status, array( 'mail_sent', 'mail_failed' ), true ) ) {
			return;
		}

		$submission = WPCF7_Submission::get_instance();

		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();

		if ( ! is_array( $posted ) || empty( $posted ) ) {
			return;
		}

		$entry_id = self::insert_entry( (int) $contact_form->id(), $posted );

		if ( ! $entry_id ) {
			return;
		}

		/**
		 * Fires after a Contact Form 7 submission has been stored as a lead.
		 *
		 * Mirrors the Forminator path so notification and automation code can
		 * treat both sources the same way.
		 *
		 * @param int    $entry_id Newly created entry ID.
		 * @param int    $form_id  Contact Form 7 form ID.
		 * @param string $source   Always DXLEDA_Sources::CF7.
		 */
		do_action( 'dxleda_lead_captured', $entry_id, (int) $contact_form->id(), DXLEDA_Sources::CF7 );
	}

	/**
	 * Write one entry plus its field values.
	 *
	 * @param int   $form_id Form ID.
	 * @param array $posted Raw posted data keyed by field name.
	 * @return int Entry ID, or 0 on failure.
	 */
	private static function insert_entry( $form_id, $posted ) {
		global $wpdb;

		$entries_table = DXLEDA_Sources::entries_table( DXLEDA_Sources::CF7 );
		$meta_table    = DXLEDA_Sources::meta_table( DXLEDA_Sources::CF7 );

		$inserted = $wpdb->insert(
			$entries_table,
			array(
				'form_id'      => $form_id,
				'date_created' => current_time( 'mysql' ),
			)
		);

		if ( ! $inserted ) {
			return 0;
		}

		$entry_id = (int) $wpdb->insert_id;

		foreach ( $posted as $key => $value ) {
			if ( self::is_internal_field( $key ) ) {
				continue;
			}

			$wpdb->insert(
				$meta_table,
				array(
					'entry_id'   => $entry_id,
					'meta_key'   => substr( sanitize_text_field( $key ), 0, 191 ),
					'meta_value' => self::normalise_value( $value ),
				)
			);
		}

		return $entry_id;
	}

	/**
	 * CF7 mixes its own bookkeeping fields into the posted data. They are not
	 * lead content and would only clutter the dashboard and CSV export.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	private static function is_internal_field( $key ) {
		// Everything CF7 adds itself is prefixed with an underscore
		// (_wpcf7, _wpcf7_version, _wpcf7_unit_tag, _wpnonce, …).
		return strpos( $key, '_' ) === 0;
	}

	/**
	 * Flatten a posted value to something storable.
	 *
	 * Checkbox and multi-select fields arrive as arrays; everything else is a
	 * string. Arrays are serialized so maybe_unserialize() on read gives the
	 * array back, matching how Forminator meta behaves.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function normalise_value( $value ) {
		if ( is_array( $value ) ) {
			return maybe_serialize( array_map( 'sanitize_text_field', $value ) );
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Remove a CF7 entry and its field values.
	 *
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public static function delete_entry( $entry_id ) {
		global $wpdb;

		$entry_id = (int) $entry_id;

		$wpdb->delete( DXLEDA_Sources::meta_table( DXLEDA_Sources::CF7 ), array( 'entry_id' => $entry_id ) );

		return $wpdb->delete( DXLEDA_Sources::entries_table( DXLEDA_Sources::CF7 ), array( 'entry_id' => $entry_id ) ) !== false;
	}
}
