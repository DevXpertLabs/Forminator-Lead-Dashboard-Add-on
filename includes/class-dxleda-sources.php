<?php
/**
 * Form Source Registry
 *
 * Describes each form plugin the dashboard can read leads from, so the rest of
 * the plugin never has to name a specific vendor's tables or API.
 *
 * A "source" is the form plugin a lead came from. Entry IDs are only unique
 * within a source, so a lead is always identified by the pair (entry_id, source).
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of supported form-plugin sources and their table locations.
 */
class DXLEDA_Sources {

	const FORMINATOR = 'forminator';
	const CF7        = 'cf7';

	/**
	 * Every source the plugin knows about, whether or not it is installed.
	 *
	 * @return array<string,string> Map of slug => human-readable label.
	 */
	public static function all() {
		return array(
			self::FORMINATOR => __( 'Forminator', 'devxpert-lead-dashboard-for-forminator' ),
			self::CF7        => __( 'Contact Form 7', 'devxpert-lead-dashboard-for-forminator' ),
		);
	}

	/**
	 * Sources whose form plugin is actually active on this site.
	 *
	 * @return array<string,string> Map of slug => label.
	 */
	public static function available() {
		$available = array();

		foreach ( self::all() as $slug => $label ) {
			if ( self::is_available( $slug ) ) {
				$available[ $slug ] = $label;
			}
		}

		return $available;
	}

	/**
	 * Is this source's form plugin active?
	 *
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function is_available( $source ) {
		switch ( $source ) {
			case self::FORMINATOR:
				return class_exists( 'Forminator' );

			case self::CF7:
				return defined( 'WPCF7_VERSION' ) || class_exists( 'WPCF7_ContactForm' );
		}

		return false;
	}

	/**
	 * Is this a slug the plugin recognises?
	 *
	 * @param string $source Source slug.
	 * @return bool
	 */
	public static function is_valid( $source ) {
		return array_key_exists( $source, self::all() );
	}

	/**
	 * Coerce untrusted input to a known source slug.
	 *
	 * Falls back to Forminator, which is what every pre-1.1 row is.
	 *
	 * @param mixed $source Source slug.
	 * @return string
	 */
	public static function sanitize( $source ) {
		$source = is_string( $source ) ? $source : '';

		return self::is_valid( $source ) ? $source : self::FORMINATOR;
	}

	/**
	 * Human-readable label for a source slug.
	 *
	 * @param string $source Source slug.
	 * @return string
	 */
	public static function label( $source ) {
		$all = self::all();

		return isset( $all[ $source ] ) ? $all[ $source ] : $source;
	}

	/**
	 * Table holding this source's submissions.
	 *
	 * @param string $source Source slug.
	 * @return string Prefixed table name, empty for an unknown source.
	 */
	public static function entries_table( $source ) {
		global $wpdb;

		switch ( $source ) {
			case self::FORMINATOR:
				return $wpdb->prefix . 'frmt_form_entry';

			case self::CF7:
				return $wpdb->prefix . 'dxleda_cf7_entries';
		}

		return '';
	}

	/**
	 * Table holding this source's submission field values.
	 *
	 * @param string $source Source slug.
	 * @return string Prefixed table name, empty for an unknown source.
	 */
	public static function meta_table( $source ) {
		global $wpdb;

		switch ( $source ) {
			case self::FORMINATOR:
				return $wpdb->prefix . 'frmt_form_entry_meta';

			case self::CF7:
				return $wpdb->prefix . 'dxleda_cf7_entry_meta';
		}

		return '';
	}

	/**
	 * Extra WHERE condition needed to isolate real form entries in the entries
	 * table, without a leading AND.
	 *
	 * Forminator stores polls and quizzes in the same table as forms; our own
	 * CF7 table holds nothing else, so it needs no filter.
	 *
	 * @param string $source Source slug.
	 * @return string SQL fragment, or empty string.
	 */
	public static function entries_where( $source ) {
		if ( self::FORMINATOR === $source ) {
			return "entry_type = 'custom-forms'";
		}

		return '';
	}

	/**
	 * Map of form ID => form name for one source.
	 *
	 * @param string $source Source slug.
	 * @return array<int,string>
	 */
	public static function form_names( $source ) {
		$map = array();

		if ( ! self::is_available( $source ) ) {
			return $map;
		}

		if ( self::FORMINATOR === $source && class_exists( 'Forminator_API' ) ) {
			// Pass a high per-page so we get every form, not just the first 10.
			$forms = Forminator_API::get_forms( null, 1, 999 );

			if ( is_array( $forms ) ) {
				foreach ( $forms as $form ) {
					$settings = (array) $form->settings;

					$map[ (int) $form->id ] = ! empty( $settings['formName'] )
						? $settings['formName']
						: ( 'Form #' . $form->id );
				}
			}
		}

		if ( self::CF7 === $source && class_exists( 'WPCF7_ContactForm' ) ) {
			$forms = WPCF7_ContactForm::find( array( 'posts_per_page' => -1 ) );

			if ( is_array( $forms ) ) {
				foreach ( $forms as $form ) {
					$title = $form->title();

					$map[ (int) $form->id() ] = '' !== $title ? $title : ( 'Form #' . $form->id() );
				}
			}
		}

		return $map;
	}
}
