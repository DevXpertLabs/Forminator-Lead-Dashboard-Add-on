<?php
/**
 * Roles and Capabilities Handler
 *
 * Manages the custom 'dxleda_sales_admin' role and 'dxleda_manage_leads' capability.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DXLEDA_Roles {

	const ROLE_SLUG = 'dxleda_sales_admin';
	const CAP       = 'dxleda_manage_leads';

	/**
	 * Register role and ensure administrator has the capability.
	 * Runs on every plugins_loaded so role survives DB restores.
	 */
	public static function setup() {
		if ( ! get_role( self::ROLE_SLUG ) ) {
			add_role(
				self::ROLE_SLUG,
				__( 'Sales Admin', 'devxpert-lead-dashboard-for-forminator' ),
				array(
					'read'    => true,
					self::CAP => true,
				)
			);
		}

		$admin_role = get_role( 'administrator' );
		if ( $admin_role && ! $admin_role->has_cap( self::CAP ) ) {
			$admin_role->add_cap( self::CAP );
		}
	}

	/**
	 * Remove role and capability (called on deactivation).
	 */
	public static function teardown() {
		remove_role( self::ROLE_SLUG );

		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( self::CAP );
		}
	}

	/**
	 * Can the current user access the lead dashboard?
	 */
	public static function can_access() {
		return current_user_can( self::CAP );
	}

	/**
	 * Is the current user a full administrator?
	 */
	public static function is_admin() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * All users who can access the dashboard (administrators + sales admins).
	 */
	public static function get_team_users() {
		return get_users(
			array(
				'role__in' => array( 'administrator', self::ROLE_SLUG ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
			)
		);
	}

	/**
	 * Users currently holding the sales_admin role.
	 */
	public static function get_sales_admins() {
		return get_users(
			array(
				'role'    => self::ROLE_SLUG,
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);
	}

	/**
	 * Assign the sales_admin role to a user.
	 * Refuses to modify existing administrators.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function assign( $user_id ) {
		$user = get_userdata( intval( $user_id ) );
		if ( ! $user || $user->has_cap( 'manage_options' ) ) {
			return false;
		}
		$user->set_role( self::ROLE_SLUG );
		return true;
	}

	/**
	 * Remove the sales_admin role from a user (reverts to Subscriber).
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public static function remove( $user_id ) {
		$user = get_userdata( intval( $user_id ) );
		if ( ! $user ) {
			return false;
		}
		if ( in_array( self::ROLE_SLUG, (array) $user->roles, true ) ) {
			$user->set_role( 'subscriber' );
			return true;
		}
		return false;
	}
}
