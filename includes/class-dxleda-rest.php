<?php
/**
 * Read-only REST API for leads.
 *
 * Everything the dashboard shows has until now been reachable only through
 * admin-ajax, which means only from inside wp-admin. These routes expose the
 * same data to anything that can authenticate — a mobile app, a reporting
 * script, an automation platform — without adding a write surface.
 *
 * Authentication is standard WordPress: an application password for external
 * clients, or the logged-in cookie plus a nonce from the admin screens. Every
 * route requires the same capability the dashboard itself requires.
 *
 * @package DevXpert_Lead_Dashboard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the dxleda/v1 REST routes.
 */
class DXLEDA_REST {

	/**
	 * Route namespace.
	 */
	const NAMESPACE_V1 = 'dxleda/v1';

	/**
	 * Upper bound on page size, so a single call cannot ask for everything.
	 */
	const MAX_PER_PAGE = 100;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/leads',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_leads' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
					'args'                => self::get_collection_args(),
				),
				'schema' => array( __CLASS__, 'get_lead_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/leads/(?P<source>[a-z0-9_-]+)/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_lead' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
					'args'                => array(
						'source' => array(
							'description'       => __( 'Form plugin the entry came from.', 'devxpert-lead-dashboard-for-forminator' ),
							'type'              => 'string',
							'required'          => true,
							'validate_callback' => array( __CLASS__, 'validate_source' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'id'     => array(
							'description'       => __( 'Entry ID.', 'devxpert-lead-dashboard-for-forminator' ),
							'type'              => 'integer',
							'required'          => true,
							'minimum'           => 1,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
					),
				),
				'schema' => array( __CLASS__, 'get_lead_schema' ),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_stats' ),
					'permission_callback' => array( __CLASS__, 'permission_check' ),
					'args'                => array(
						'days' => array(
							'description'       => __( 'Size of the reporting window, in days.', 'devxpert-lead-dashboard-for-forminator' ),
							'type'              => 'integer',
							'default'           => 30,
							'minimum'           => 1,
							'maximum'           => 365,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Gate every route behind the dashboard capability.
	 *
	 * @return true|WP_Error
	 */
	public static function permission_check() {
		if ( DXLEDA_Roles::can_access() ) {
			return true;
		}

		return new WP_Error(
			'dxleda_rest_forbidden',
			__( 'You are not allowed to view leads.', 'devxpert-lead-dashboard-for-forminator' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	/**
	 * Argument definitions for the collection route.
	 *
	 * Note the explicit 'validate_callback' on every argument. WordPress only
	 * wires up rest_validate_request_arg automatically for args generated from
	 * a schema by rest_get_endpoint_args_for_schema(); on a hand-written args
	 * array, schema keywords such as enum, minimum and maximum are silently
	 * ignored unless the validator is named here.
	 *
	 * @return array
	 */
	private static function get_collection_args() {
		return array(
			'source'      => array(
				'description'       => __( 'Limit to one form plugin. Omit for all sources.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => array( __CLASS__, 'validate_optional_source' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'form_id'     => array(
				'description'       => __( 'Limit to one form.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			),
			'status'      => array(
				'description'       => __( 'Limit to one lead status.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => '',
				'enum'              => array_merge( array( '' ), array_keys( DXLEDA_Leads::get_statuses() ) ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'assigned_to' => array(
				'description'       => __( 'Limit to leads assigned to a user ID.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'integer',
				'default'           => 0,
				'minimum'           => 0,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			),
			'search'      => array(
				'description'       => __( 'Match against submitted field values.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_from'   => array(
				'description'       => __( 'Earliest submission date, as YYYY-MM-DD.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => array( __CLASS__, 'validate_date' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'date_to'     => array(
				'description'       => __( 'Latest submission date, as YYYY-MM-DD.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => '',
				'validate_callback' => array( __CLASS__, 'validate_date' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'page'        => array(
				'description'       => __( 'Page of results to return.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			),
			'per_page'    => array(
				'description'       => __( 'Results per page.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'integer',
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'absint',
			),
			'orderby'     => array(
				'description'       => __( 'Field to sort by.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => 'date_created',
				'enum'              => array( 'date_created', 'entry_id', 'lead_status' ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'order'       => array(
				'description'       => __( 'Sort direction.', 'devxpert-lead-dashboard-for-forminator' ),
				'type'              => 'string',
				'default'           => 'DESC',
				'enum'              => array( 'ASC', 'DESC', 'asc', 'desc' ),
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'sanitize_text_field',
			),
		);
	}

	/**
	 * GET /leads
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_leads( $request ) {
		$result = DXLEDA_Leads::get_leads(
			array(
				'source'      => $request['source'],
				'form_id'     => $request['form_id'],
				'status'      => $request['status'],
				'assigned_to' => $request['assigned_to'],
				'search'      => $request['search'],
				'date_from'   => $request['date_from'],
				'date_to'     => $request['date_to'],
				'page'        => $request['page'],
				'per_page'    => $request['per_page'],
				'orderby'     => $request['orderby'],
				'order'       => $request['order'],
			)
		);

		$response = rest_ensure_response( $result['leads'] );

		// Standard pagination headers, so clients can page without parsing the body.
		$response->header( 'X-WP-Total', (int) $result['total'] );
		$response->header( 'X-WP-TotalPages', (int) $result['pages'] );

		return $response;
	}

	/**
	 * GET /leads/{source}/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_lead( $request ) {
		$lead = DXLEDA_Leads::get_lead( $request['id'], $request['source'] );

		if ( ! $lead ) {
			return new WP_Error(
				'dxleda_rest_lead_not_found',
				__( 'No lead was found with that entry ID and source.', 'devxpert-lead-dashboard-for-forminator' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $lead );
	}

	/**
	 * GET /stats
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_stats( $request ) {
		return rest_ensure_response( DXLEDA_Leads::get_dashboard_stats( $request['days'] ) );
	}

	/**
	 * Require a source slug the plugin actually knows about.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_source( $value ) {
		return DXLEDA_Sources::is_valid( (string) $value );
	}

	/**
	 * Same, but an empty value is allowed and means "every source".
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_optional_source( $value ) {
		return '' === $value || DXLEDA_Sources::is_valid( (string) $value );
	}

	/**
	 * Require a real calendar date in Y-m-d form, or nothing at all.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool
	 */
	public static function validate_date( $value ) {
		$value = (string) $value;

		if ( '' === $value ) {
			return true;
		}

		$parsed = DateTime::createFromFormat( 'Y-m-d', $value );

		return $parsed && $parsed->format( 'Y-m-d' ) === $value;
	}

	/**
	 * Schema describing a lead, so the routes are self-documenting.
	 *
	 * @return array
	 */
	public static function get_lead_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'dxleda_lead',
			'type'       => 'object',
			'properties' => array(
				'entry_id'     => array(
					'description' => __( 'Entry ID, unique only within its source.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'source'       => array(
					'description' => __( 'Form plugin the entry came from.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'source_label' => array(
					'description' => __( 'Human-readable name of the form plugin.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'form_id'      => array(
					'description' => __( 'Form ID.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'form_name'    => array(
					'description' => __( 'Form title.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'date_created' => array(
					'description' => __( 'Submission date.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'status'       => array(
					'description' => __( 'Lead status.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'string',
					'enum'        => array_keys( DXLEDA_Leads::get_statuses() ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'assigned_to'  => array(
					'description' => __( 'User ID the lead is assigned to.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => array( 'integer', 'null' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'meta'         => array(
					'description' => __( 'Submitted field values, keyed by field name.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'object',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'feedback'     => array(
					'description' => __( 'Feedback notes left on the lead.', 'devxpert-lead-dashboard-for-forminator' ),
					'type'        => 'array',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);
	}
}
