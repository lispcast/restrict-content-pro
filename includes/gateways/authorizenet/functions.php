<?php
/**
 * Authorize.net Functions
 *
 * @package     Restrict Content Pro
 * @subpackage  Gateways/Authorize.net/Functions
 * @copyright   Copyright (c) 2017, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.7
 */

/**
 * Cancel an Authorize.net subscriber
 *
 * @param int $member_id ID of the member to cancel.
 *
 * @access      private
 * @since       2.7
 * @return      bool|WP_Error
 */
function rcp_authnet_cancel_member( $member_id = 0 ) {

	global $rcp_options;

	$ret             = true;
	if ( rcp_is_sandbox() ) {
		$api_login_id    = isset( $rcp_options['authorize_test_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_test_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_test_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_txn_key'] ) : '';
	} else {
		$api_login_id    = isset( $rcp_options['authorize_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_txn_key'] ) : '';
	}
	$md5_hash_value  = isset( $rcp_options['authorize_hash_value'] ) ? sanitize_text_field( $rcp_options['authorize_hash_value'] ) : '';

	require_once RCP_PLUGIN_DIR . 'includes/libraries/anet_php_sdk/AuthorizeNet.php';

	$member     = new RCP_Member( $member_id );
	$profile_id = str_replace( 'anet_', '', $member->get_payment_profile_id() );

	$arb        = new AuthorizeNetARB( $api_login_id, $transaction_key );
	$arb->setSandbox( rcp_is_sandbox() );

	$response   = $arb->cancelSubscription( $profile_id );

	if( ! $response->isOK() || $response->isError() ) {

		$error = $response->getErrorMessage();
		$ret   = new WP_Error( 'rcp_authnet_error', $error );

	}

	return $ret;
}


/**
 * Determine if a member is an Authorize.net Customer
 *
 * @param int $user_id The ID of the user to check
 *
 * @since       2.7
 * @access      public
 * @return      bool
*/
function rcp_is_authnet_subscriber( $user_id = 0 ) {

	if( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$ret = false;

	$member = new RCP_Member( $user_id );

	$profile_id = $member->get_payment_profile_id();

	// Check if the member is an Authorize.net customer
	if( false !== strpos( $profile_id, 'anet_' ) ) {

		$ret = true;

	}

	return (bool) apply_filters( 'rcp_is_authorizenet_subscriber', $ret, $user_id );
}

/**
 * Determine if all necessary API credentials are filled in
 *
 * @since  2.7
 * @return bool
 */
function rcp_has_authnet_api_access() {

	global $rcp_options;

	$ret = false;

	if ( rcp_is_sandbox() ) {
		$api_login_id    = $rcp_options['authorize_test_api_login'];
		$transaction_key = $rcp_options['authorize_test_txn_key'];
	} else {
		$api_login_id    = $rcp_options['authorize_api_login'];
		$transaction_key = $rcp_options['authorize_txn_key'];
	}

	if ( ! empty( $api_login_id ) && ! empty( $transaction_key ) ) {
		$ret = true;
	}

	return $ret;

}

/**
 * Process an update card form request for Authorize.net
 *
 * @param int        $member_id  ID of the member.
 * @param RCP_Member $member_obj Member object.
 *
 * @access      private
 * @since       2.7
 * @return      void
 */
function rcp_authorizenet_update_billing_card( $member_id = 0, $member_obj ) {

	global $rcp_options;

	if( empty( $member_id ) ) {
		return;
	}

	if( ! is_a( $member_obj, 'RCP_Member' ) ) {
		return;
	}

	if( ! rcp_is_authnet_subscriber( $member_id ) ) {
		return;
	}

	require_once RCP_PLUGIN_DIR . 'includes/libraries/anet_php_sdk/AuthorizeNet.php';

	if ( rcp_is_sandbox() ) {
		$api_login_id    = isset( $rcp_options['authorize_test_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_test_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_test_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_txn_key'] ) : '';
	} else {
		$api_login_id    = isset( $rcp_options['authorize_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_api_login'] ) : '';
		$transaction_key = isset( $rcp_options['authorize_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_txn_key'] ) : '';
	}
	$md5_hash_value  = isset( $rcp_options['authorize_hash_value'] ) ? sanitize_text_field( $rcp_options['authorize_hash_value'] ) : '';

	$error          = '';
	$card_number    = isset( $_POST['rcp_card_number'] )    && is_numeric( $_POST['rcp_card_number'] )    ? sanitize_text_field( $_POST['rcp_card_number'] )    : '';
	$card_exp_month = isset( $_POST['rcp_card_exp_month'] ) && is_numeric( $_POST['rcp_card_exp_month'] ) ? sanitize_text_field( $_POST['rcp_card_exp_month'] ) : '';
	$card_exp_year  = isset( $_POST['rcp_card_exp_year'] )  && is_numeric( $_POST['rcp_card_exp_year'] )  ? sanitize_text_field( $_POST['rcp_card_exp_year'] )  : '';
	$card_cvc       = isset( $_POST['rcp_card_cvc'] )       && is_numeric( $_POST['rcp_card_cvc'] )       ? sanitize_text_field( $_POST['rcp_card_cvc'] )       : '';
	$card_zip       = isset( $_POST['rcp_card_zip'] ) ? sanitize_text_field( $_POST['rcp_card_zip'] ) : '' ;

	if ( empty( $card_number ) || empty( $card_exp_month ) || empty( $card_exp_year ) || empty( $card_cvc ) || empty( $card_zip ) ) {
		$error = __( 'Please enter all required fields.', 'rcp' );
	}

	if ( empty( $error ) ) {

		$member     = new RCP_Member( $member_id );
		$profile_id = str_replace( 'anet_', '', $member->get_payment_profile_id() );

		$arb        = new AuthorizeNetARB( $api_login_id, $transaction_key );
		$arb->setSandbox( rcp_is_sandbox() );

		$subscription = new AuthorizeNet_Subscription;
		$subscription->creditCardCardNumber     = $card_number;
		$subscription->creditCardExpirationDate = $card_exp_year . '-' . $card_exp_month;
		$subscription->creditCardCardCode       = $card_cvc;

		$response = $arb->updateSubscription( $profile_id, $subscription );

		if( ! $response->isOK() || $response->isError() ) {

			$error = $response->getErrorMessage();

		}

	}

	if( ! empty( $error ) ) {

		wp_redirect( add_query_arg( array( 'card' => 'not-updated', 'msg' => urlencode( $error ) ) ) ); exit;

	}

	wp_redirect( add_query_arg( array( 'card' => 'updated', 'msg' => '' ) ) ); exit;

}
add_action( 'rcp_update_billing_card', 'rcp_authorizenet_update_billing_card', 10, 2 );