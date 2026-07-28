<?php
/**
 * Tests for the read-only dxleda/v1 REST routes.
 *
 * @package DevXpert_Lead_Dashboard
 */

/**
 * REST API tests.
 */
class Test_DXLEDA_REST extends WP_UnitTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private $server;

	/**
	 * Spin up a REST server with our routes registered.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		DXLEDA_REST::init();

		do_action( 'rest_api_init' );
	}

	/**
	 * Tear down the REST server.
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Create a user holding the lead-dashboard capability and log them in.
	 *
	 * @return int User ID.
	 */
	private function login_team_member() {
		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$user = get_user_by( 'id', $user_id );
		$user->add_cap( DXLEDA_Roles::CAP );

		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Test that all three routes are registered.
	 */
	public function test_routes_are_registered() {
		$routes = $this->server->get_routes();

		$this->assertArrayHasKey( '/dxleda/v1/leads', $routes );
		$this->assertArrayHasKey( '/dxleda/v1/stats', $routes );
		$this->assertArrayHasKey( '/dxleda/v1/leads/(?P<source>[a-z0-9_-]+)/(?P<id>\d+)', $routes );
	}

	/**
	 * Test that an anonymous request is rejected.
	 */
	public function test_leads_requires_authentication() {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/dxleda/v1/leads' ) );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test that a logged-in user without the capability is rejected.
	 */
	public function test_leads_requires_the_dashboard_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/dxleda/v1/leads' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test that a capable user can read the collection.
	 */
	public function test_leads_returns_a_collection_for_capable_users() {
		$this->login_team_member();

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/dxleda/v1/leads' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
		$this->assertArrayHasKey( 'X-WP-Total', $response->get_headers() );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $response->get_headers() );
	}

	/**
	 * Test that an unknown source slug is rejected rather than ignored.
	 *
	 * Silently widening an unrecognised filter to "everything" would be the
	 * dangerous failure mode here.
	 */
	public function test_invalid_source_filter_is_rejected() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'source', 'not-a-real-source' );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that a known source slug is accepted.
	 */
	public function test_valid_source_filter_is_accepted() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'source', DXLEDA_Sources::FORMINATOR );

		$this->assertSame( 200, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that a malformed date is rejected.
	 */
	public function test_malformed_date_is_rejected() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'date_from', '27-07-2026' );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that a calendar-invalid date is rejected.
	 *
	 * The 30th of February parses under a loose check but is not a real date.
	 */
	public function test_impossible_date_is_rejected() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'date_from', '2026-02-30' );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that a well-formed date is accepted.
	 */
	public function test_valid_date_is_accepted() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'date_from', '2026-07-27' );

		$this->assertSame( 200, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that page size is capped.
	 */
	public function test_per_page_is_capped() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'per_page', 5000 );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that an unknown status is rejected by the enum.
	 */
	public function test_unknown_status_is_rejected() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/leads' );
		$request->set_param( 'status', 'not-a-status' );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}

	/**
	 * Test that a missing lead returns 404 rather than an empty 200.
	 */
	public function test_missing_lead_returns_404() {
		$this->login_team_member();

		$response = $this->server->dispatch(
			new WP_REST_Request( 'GET', '/dxleda/v1/leads/' . DXLEDA_Sources::FORMINATOR . '/999999' )
		);

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Test that the single-lead route is also gated.
	 */
	public function test_single_lead_requires_authentication() {
		wp_set_current_user( 0 );

		$response = $this->server->dispatch(
			new WP_REST_Request( 'GET', '/dxleda/v1/leads/' . DXLEDA_Sources::FORMINATOR . '/1' )
		);

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Test that stats are readable and gated.
	 */
	public function test_stats_route() {
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->server->dispatch( new WP_REST_Request( 'GET', '/dxleda/v1/stats' ) )->get_status() );

		$this->login_team_member();

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/dxleda/v1/stats' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test that an out-of-range reporting window is rejected.
	 */
	public function test_stats_days_is_bounded() {
		$this->login_team_member();

		$request = new WP_REST_Request( 'GET', '/dxleda/v1/stats' );
		$request->set_param( 'days', 4000 );

		$this->assertSame( 400, $this->server->dispatch( $request )->get_status() );
	}
}
