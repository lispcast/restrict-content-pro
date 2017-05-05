<?php
/**
 * Checkout Functions
 *
 * @package     Restrict Content Pro
 * @subpackage  Gateways/2Checkout/Functions
 * @copyright   Copyright (c) 2017, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/**
 * Cancel a 2checkout subscriber
 *
 * @param int $member_id ID of the member to cancel.
 *
 * @access      private
 * @since       2.4
 * @return      bool|WP_Error
 */
function rcp_2checkout_cancel_member( $member_id = 0 ) {

	global $rcp_options;

	$user_name = defined( 'TWOCHECKOUT_ADMIN_USER' ) ? TWOCHECKOUT_ADMIN_USER : '';
	$password  = defined( 'TWOCHECKOUT_ADMIN_PASSWORD' ) ? TWOCHECKOUT_ADMIN_PASSWORD : '';

	if( empty( $user_name ) || empty( $password ) ) {
		return new WP_Error( 'missing_username_or_password', __( 'The 2Checkout API username and password must be defined', 'rcp' ) );
	}

	if( ! class_exists( 'Twocheckout' ) ) {
		require_once RCP_PLUGIN_DIR . 'includes/libraries/twocheckout/Twocheckout.php';
	}

	$secret_word = rcp_is_sandbox() ? trim( $rcp_options['twocheckout_secret_word'] ) : '';;
	$test_mode   = rcp_is_sandbox();

	if( $test_mode ) {

		$secret_key      = isset( $rcp_options['twocheckout_test_private'] )     ? trim( $rcp_options['twocheckout_test_private'] )     : '';
		$publishable_key = isset( $rcp_options['twocheckout_test_publishable'] ) ? trim( $rcp_options['twocheckout_test_publishable'] ) : '';
		$seller_id       = isset( $rcp_options['twocheckout_test_seller_id'] )   ? trim( $rcp_options['twocheckout_test_seller_id'] )   : '';
		$environment     = 'sandbox';

	} else {

		$secret_key      = isset( $rcp_options['twocheckout_live_private'] )     ? trim( $rcp_options['twocheckout_live_private'] )     : '';
		$publishable_key = isset( $rcp_options['twocheckout_live_publishable'] ) ? trim( $rcp_options['twocheckout_live_publishable'] ) : '';
		$seller_id       = isset( $rcp_options['twocheckout_live_seller_id'] )   ? trim( $rcp_options['twocheckout_live_seller_id'] )   : '';
		$environment     = 'production';

	}

	try {

		Twocheckout::privateKey( $secret_key );
		Twocheckout::sellerId( $seller_id );
		Twocheckout::username( TWOCHECKOUT_ADMIN_USER );
		Twocheckout::password( TWOCHECKOUT_ADMIN_PASSWORD );
		Twocheckout::sandbox( $test_mode );

		$member    = new RCP_Member( $member_id );
		$sale_id   = str_replace( '2co_', '', $member->get_payment_profile_id() );
		$cancelled = Twocheckout_Sale::stop( array( 'sale_id' => $sale_id ) );

		if( $cancelled['response_code'] == 'OK' ) {
			return true;
		}

	} catch ( Twocheckout_Error $e) {

		return new WP_Error( '2checkout_cancel_failed', $e->getMessage() );

	}

}


/**
 * Determine if a member is a 2Checkout Customer
 *
 * @param int $user_id The ID of the user to check
 *
 * @since       2.4
 * @access      public
 * @return      bool
*/
function rcp_is_2checkout_subscriber( $user_id = 0 ) {

	if( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$ret = false;

	$member = new RCP_Member( $user_id );

	$profile_id = $member->get_payment_profile_id();

	// Check if the member is a Stripe customer
	if( false !== strpos( $profile_id, '2co_' ) ) {

		$ret = true;

	}

	return (bool) apply_filters( 'rcp_is_2checkout_subscriber', $ret, $user_id );
}