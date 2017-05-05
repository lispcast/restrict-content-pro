<?php
/**
 * PayPal Functions
 *
 * @package     Restrict Content Pro
 * @subpackage  Gateways/PayPal/Functions
 * @copyright   Copyright (c) 2017, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/**
 * Determine if a member is a PayPal subscriber
 *
 * @param int $user_id The ID of the user to check
 *
 * @since       2.0
 * @access      public
 * @return      bool
*/
function rcp_is_paypal_subscriber( $user_id = 0 ) {

	if( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	$ret        = false;
	$member     = new RCP_Member( $user_id );
	$profile_id = $member->get_payment_profile_id();

	// Check if the member is a PayPal customer
	if( false !== strpos( $profile_id, 'I-' ) ) {

		$ret = true;

	}

	return (bool) apply_filters( 'rcp_is_paypal_subscriber', $ret, $user_id );
}

/**
 * Determine if PayPal API access is enabled
 *
 * @access      public
 * @since       2.1
 * @return      bool
 */
function rcp_has_paypal_api_access() {
	global $rcp_options;

	$ret    = false;
	$prefix = 'live_';

	if( rcp_is_sandbox() ) {
		$prefix = 'test_';
	}

	$username  = $prefix . 'paypal_api_username';
	$password  = $prefix . 'paypal_api_password';
	$signature = $prefix . 'paypal_api_signature';

	if( ! empty( $rcp_options[ $username ] ) && ! empty( $rcp_options[ $password ] ) && ! empty( $rcp_options[ $signature ] ) ) {

		$ret = true;

	}

	return $ret;
}

/**
 * Retrieve PayPal API credentials
 *
 * @access      public
 * @since       2.1
 * @return      array Array of credentials.
 */
function rcp_get_paypal_api_credentials() {
	global $rcp_options;

	$ret    = false;
	$prefix = 'live_';

	if( rcp_is_sandbox() ) {
		$prefix = 'test_';
	}

	$creds = array(
		'username'  => $rcp_options[ $prefix . 'paypal_api_username' ],
		'password'  => $rcp_options[ $prefix . 'paypal_api_password' ],
		'signature' => $rcp_options[ $prefix . 'paypal_api_signature' ]
	);

	return apply_filters( 'rcp_get_paypal_api_credentials', $creds );
}

/**
 * Process an update card form request
 *
 * @param int        $member_id  ID of the member.
 * @param RCP_Member $member_obj Member object.
 *
 * @access      private
 * @since       2.6
 * @return      void
 */
function rcp_paypal_update_billing_card( $member_id = 0, $member_obj ) {

	global $rcp_options;

	if( empty( $member_id ) ) {
		return;
	}

	if( ! is_a( $member_obj, 'RCP_Member' ) ) {
		return;
	}


	if( ! rcp_is_paypal_subscriber( $member_id ) ) {
		return;
	}

	if( rcp_is_sandbox() ) {

		$api_endpoint = 'https://api-3t.sandbox.paypal.com/nvp';

	} else {

		$api_endpoint = 'https://api-3t.paypal.com/nvp';

	}

	$error       = '';
	$customer_id = $member_obj->get_payment_profile_id();
	$credentials = rcp_get_paypal_api_credentials();

	$card_number    = isset( $_POST['rcp_card_number'] )    && is_numeric( $_POST['rcp_card_number'] )    ? $_POST['rcp_card_number']    : '';
	$card_exp_month = isset( $_POST['rcp_card_exp_month'] ) && is_numeric( $_POST['rcp_card_exp_month'] ) ? $_POST['rcp_card_exp_month'] : '';
	$card_exp_year  = isset( $_POST['rcp_card_exp_year'] )  && is_numeric( $_POST['rcp_card_exp_year'] )  ? $_POST['rcp_card_exp_year']  : '';
	$card_cvc       = isset( $_POST['rcp_card_cvc'] )       && is_numeric( $_POST['rcp_card_cvc'] )       ? $_POST['rcp_card_cvc']       : '';
	$card_zip       = isset( $_POST['rcp_card_zip'] ) ? sanitize_text_field( $_POST['rcp_card_zip'] ) : '' ;

	if ( empty( $card_number ) || empty( $card_exp_month ) || empty( $card_exp_year ) || empty( $card_cvc ) || empty( $card_zip ) ) {
		$error = __( 'Please enter all required fields.', 'rcp' );
	}

	if ( empty( $error ) ) {

		$args = array(
			'USER'                => $credentials['username'],
			'PWD'                 => $credentials['password'],
			'SIGNATURE'           => $credentials['signature'],
			'VERSION'             => '124',
			'METHOD'              => 'UpdateRecurringPaymentsProfile',
			'PROFILEID'           => $customer_id,
			'ACCT'                => $card_number,
			'EXPDATE'             => $card_exp_month . $card_exp_year,
			// needs to be in the format 062019
			'CVV2'                => $card_cvc,
			'ZIP'                 => $card_zip,
			'BUTTONSOURCE'        => 'EasyDigitalDownloads_SP',
		);

		$request = wp_remote_post( $api_endpoint, array(
			'timeout'     => 45,
			'sslverify'   => false,
			'body'        => $args,
			'httpversion' => '1.1',
		) );

		$body    = wp_remote_retrieve_body( $request );
		$code    = wp_remote_retrieve_response_code( $request );
		$message = wp_remote_retrieve_response_message( $request );

		if ( is_wp_error( $request ) ) {

			$error = $request->get_error_message();

		} elseif ( 200 == $code && 'OK' == $message ) {

			if( is_string( $body ) ) {
				$body = wp_parse_str( $body, $body );
			}

			if ( 'failure' === strtolower( $body['ACK'] ) ) {

				$error = $body['L_ERRORCODE0'] . ': ' . $body['L_LONGMESSAGE0'];

			} else {

				// Request was successful, but verify the profile ID that came back matches
				if ( $customer_id !== $body['PROFILEID'] ) {
					$error = __( 'Error updating subscription', 'rcp' );
				}

			}

		} else {

			$error = __( 'Something has gone wrong, please try again', 'rcp' );

		}

	}

	if( ! empty( $error ) ) {

		wp_redirect( add_query_arg( array( 'card' => 'not-updated', 'msg' => urlencode( $error ) ) ) ); exit;

	}

	wp_redirect( add_query_arg( array( 'card' => 'updated', 'msg' => '' ) ) ); exit;

}
add_action( 'rcp_update_billing_card', 'rcp_paypal_update_billing_card', 10, 2 );