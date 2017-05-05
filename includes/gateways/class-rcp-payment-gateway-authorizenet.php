<?php
/**
 * Payment Gateway Authorize.net Class
 *
 * @package     Restrict Content Pro
 * @subpackage  Classes/Roles
 * @copyright   Copyright (c) 2017, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.7
*/

class RCP_Payment_Gateway_Authorizenet extends RCP_Payment_Gateway {

	private $md5_hash_value;
	private $api_login_id;
	private $transaction_key;

	/**
	* get things going
	*
	* @since      2.7
	*/
	public function init() {
		global $rcp_options;

		$this->supports[]  = 'one-time';
		$this->supports[]  = 'recurring';
		$this->supports[]  = 'fees';
		$this->supports[]  = 'trial';

		if ( $this->test_mode ) {
			$this->api_login_id    = isset( $rcp_options['authorize_test_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_test_api_login'] ) : '';
			$this->transaction_key = isset( $rcp_options['authorize_test_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_test_txn_key'] ) : '';
		} else {
			$this->api_login_id    = isset( $rcp_options['authorize_api_login'] ) ? sanitize_text_field( $rcp_options['authorize_api_login'] ) : '';
			$this->transaction_key = isset( $rcp_options['authorize_txn_key'] ) ? sanitize_text_field( $rcp_options['authorize_txn_key'] ) : '';
		}

		$this->md5_hash_value  = isset( $rcp_options['authorize_hash_value'] ) ? sanitize_text_field( $rcp_options['authorize_hash_value'] ) : '';

		require_once RCP_PLUGIN_DIR . 'includes/libraries/anet_php_sdk/AuthorizeNet.php';

	} // end init

	/**
	 * Validate additional fields during registration submission
	 *
	 * @since 2.7
	 */
	public function validate_fields() {

		if( empty( $_POST['rcp_card_cvc'] ) ) {
			rcp_errors()->add( 'missing_card_code', __( 'The security code you have entered is invalid', 'rcp' ), 'register' );
		}

		if( empty( $_POST['rcp_card_zip'] ) ) {
			rcp_errors()->add( 'missing_card_zip', __( 'Please enter a Zip / Postal Code code', 'rcp' ), 'register' );
		}

		if ( empty( $this->api_login_id ) || empty( $this->transaction_key ) ) {
			rcp_errors()->add( 'missing_authorize_settings', __( 'Authorize.net API Login ID or Transaction key is missing.', 'rcp' ), 'register' );
		}

		$sub_id = ! empty( $_POST['rcp_level'] ) ? absint( $_POST['rcp_level'] ) : false;

		if( $sub_id ) {

			$sub = rcp_get_subscription_length( $sub_id );

			if( 'day' == $sub->duration_unit && $sub->duration < 7 ) {
				rcp_errors()->add( 'invalid_authorize_length', __( 'Authorize.net does not permit subscriptions with renewal periods less than 7 days.', 'rcp' ), 'register' );
			}

		}

	}

	/**
	 * Process registration
	 *
	 * @since 2.7
	 */
	public function process_signup() {

		if ( empty( $this->api_login_id ) || empty( $this->transaction_key ) ) {
			rcp_errors()->add( 'missing_authorize_settings', __( 'Authorize.net API Login ID or Transaction key is missing.', 'rcp' ) );
		}

		$member = new RCP_Member( $this->user_id );

		$length = $this->length;
		$unit   = $this->length_unit . 's';

		if( 'years' == $unit && 1 == $length ) {
			$unit   = 'months';
			$length = 12;
		}

		$names = explode( ' ', sanitize_text_field( $_POST['rcp_card_name'] ) );
		$fname = isset( $names[0] ) ? $names[0] : $member->user_first;

		if( ! empty( $names[1] ) ) {
			unset( $names[0] );
			$lname = implode( ' ', $names );
		} else {
			$lname = $member->user_last;
		}

		try {

			$subscription = new AuthorizeNet_Subscription;
			$subscription->name = substr( $this->subscription_name . ' - ' . $this->subscription_key, 0, 50 ); // Max of 50 characters
			$subscription->intervalLength = $length;
			$subscription->intervalUnit = $unit;
			$subscription->startDate = date( 'Y-m-d' );
			$subscription->totalOccurrences = $this->auto_renew ? 9999 : 1;
			$subscription->trialOccurrences = $this->auto_renew ? 1 : 0;
			$subscription->amount = $this->amount;
			$subscription->trialAmount = $this->initial_amount;
			$subscription->creditCardCardNumber = sanitize_text_field( $_POST['rcp_card_number'] );
			$subscription->creditCardExpirationDate = sanitize_text_field( $_POST['rcp_card_exp_year'] ) . '-' . sanitize_text_field( $_POST['rcp_card_exp_month'] );
			$subscription->creditCardCardCode = sanitize_text_field( $_POST['rcp_card_cvc'] );
			$subscription->orderDescription = $this->subscription_key;
			$subscription->customerEmail = $this->email;
			$subscription->billToFirstName = $fname;
			$subscription->billToLastName = $lname;
			$subscription->billToZip = sanitize_text_field( $_POST['rcp_card_zip'] );

			// Delay start date for free trials.
			if ( $this->is_trial() ) {
				$subscription->startDate = date( 'Y-m-d', strtotime( $this->subscription_data['trial_duration'] . ' ' . $this->subscription_data['trial_duration_unit'], current_time( 'timestamp' ) ) );
			}


			$arb = new AuthorizeNetARB( $this->api_login_id, $this->transaction_key );
			$arb->setSandbox( rcp_is_sandbox() );
			$response = $arb->createSubscription( $subscription );

			if ( $response->isOK() && ! $response->isError() ) {

				// If the customer has an existing subscription, we need to cancel it
				if( $member->just_upgraded() && $member->can_cancel() ) {
					$cancelled = $member->cancel_payment_profile( false );
				}

				$member->set_recurring( $this->auto_renew );
				$member->set_expiration_date( $member->calculate_expiration() );
				$member->set_status( 'active' );
				$member->add_note( __( 'Subscription started in Authorize.net', 'rcp' ) );
				$member->set_payment_profile_id( 'anet_' . $response->getSubscriptionId() );

				if ( ! is_user_logged_in() ) {

					// log the new user in
					rcp_login_user_in( $this->user_id, $this->user_name, $_POST['rcp_user_pass'] );

				}

				do_action( 'rcp_authorizenet_signup', $this->user_id, $this, $response );

			} else {

				do_action( 'rcp_registration_failed', $this );

				wp_die( $response->getErrorMessage(), __( 'Error', 'rcp' ), array( 'response' => '401' ) );

			}

		} catch ( AuthorizeNetException $e ) {
			do_action( 'rcp_registration_failed', $this );
			wp_die( $e->getMessage(), __( 'Error', 'rcp' ), array( 'response' => '401' ) );
		}

		// redirect to the success page, or error page if something went wrong
		wp_redirect( $this->return_url ); exit;
	}

	/**
	 * Proccess webhooks
	 *
	 * @since 2.7
	 */
	public function process_webhooks() {

		global $rcp_payments_db;

		if ( empty( $_GET['listener'] ) || 'authnet' != $_GET['listener'] ) {
			return;
		}

		if( ! $this->is_silent_post_valid( $_POST ) ) {
			die( 'invalid silent post' );
		}

		$anet_subscription_id = intval( $_POST['x_subscription_id'] );

		if ( $anet_subscription_id ) {

			$response_code = intval( $_POST['x_response_code'] );
			$reason_code   = intval( $_POST['x_response_reason_code'] );

			$member_id = rcp_get_member_id_from_profile_id( 'anet_' . $anet_subscription_id );

			if( empty( $member_id ) ) {
				die( 'no member found' );
			}

			$member   = new RCP_Member( $member_id );
			$payments = new RCP_Payments();

			if ( 1 == $response_code ) {

				// Approved
				$renewal_amount = sanitize_text_field( $_POST['x_amount'] );
				$transaction_id = sanitize_text_field( $_POST['x_trans_id'] );

				$payment_data = array(
					'date'             => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
					'subscription'     => $member->get_subscription_name(),
					'payment_type'     => 'Credit Card',
					'subscription_key' => $member->get_subscription_key(),
					'amount'           => $renewal_amount,
					'user_id'          => $member->ID,
					'transaction_id'   => $transaction_id
				);

				if ( intval( $_POST['x_subscription_paynum'] ) > 1 ) {
					$member->renew( $member->is_recurring() );
				}
				$payments->insert( $payment_data );
				$member->add_note( __( 'Subscription processed in Authorize.net', 'rcp' ) );

				do_action( 'rcp_authorizenet_silent_post_payment', $member, $this );

			} elseif ( 2 == $response_code ) {

				// Declined
				if ( ! empty( $_POST['x_trans_id'] ) ) {
					$this->webhook_event_id = sanitize_text_field( $_POST['x_trans_id'] );
				}

				do_action( 'rcp_recurring_payment_failed', $member, $this );
				do_action( 'rcp_authorizenet_silent_post_error', $member, $this );

			} elseif ( 3 == $response_code || 8 == $reason_code ) {

				// An expired card
				if ( ! empty( $_POST['x_trans_id'] ) ) {
					$this->webhook_event_id = sanitize_text_field( $_POST['x_trans_id'] );
				}

				do_action( 'rcp_recurring_payment_failed', $member, $this );
				do_action( 'rcp_authorizenet_silent_post_error', $member, $this );

			} else {

				// Other Error
				do_action( 'rcp_authorizenet_silent_post_error', $member, $this );

			}
		}

		die( 'success');
	}

	/**
	 * Load credit card fields
	 *
	 * @since 2.7
	 */
	public function fields() {
		ob_start();
		rcp_get_template_part( 'card-form' );
		return ob_get_clean();
	}

	/**
	 * Determines if the silent post is valid by verifying the MD5 Hash
	 *
	 * @access  public
	 * @since   2.7
	 * @param   array $request The Request array containing data for the silent post
	 * @return  bool
	 */
	public function is_silent_post_valid( $request ) {

		$auth_md5 = isset( $request['x_MD5_Hash'] ) ? $request['x_MD5_Hash'] : '';

		//Sanity check to ensure we have an MD5 Hash from the silent POST
		if( empty( $auth_md5 ) ) {
			return false;
		}

		$str           = $this->md5_hash_value . $request['x_trans_id'] . $request['x_amount'];
		$generated_md5 = strtoupper( md5( $str ) );

		return hash_equals( $generated_md5, $auth_md5 );
	}

}
