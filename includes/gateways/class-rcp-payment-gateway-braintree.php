<?php
/**
 * Braintree Payment Gateway Class
 *
 * @package    Restrict Content Pro
 * @subpackage Classes/Gateways/Braintree
 * @copyright  Copyright (c) 2017, Sandhills Development
 * @license    http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.8
 */

class RCP_Payment_Gateway_Braintree extends RCP_Payment_Gateway {

	protected $merchantId;
	protected $publicKey;
	protected $privateKey;
	protected $encryptionKey;
	protected $environment;

	/**
	 * Initializes the gateway configuration.
	 *
	 * @since 2.8
	 * @return void
	 */
	public function init() {

		if ( version_compare( PHP_VERSION, '5.4.0', '<' ) ) {
			return;
		}

		global $rcp_options;

		$this->supports[] = 'one-time';
		$this->supports[] = 'recurring';
		$this->supports[] = 'fees';
		$this->supports[] = 'trial';
		$this->supports[] = 'gateway-submits-form';

		if ( $this->test_mode ) {
			$this->merchantId    = ! empty( $rcp_options['braintree_sandbox_merchantId'] ) ? sanitize_text_field( $rcp_options['braintree_sandbox_merchantId'] ) : '';
			$this->publicKey     = ! empty( $rcp_options['braintree_sandbox_publicKey'] ) ? sanitize_text_field( $rcp_options['braintree_sandbox_publicKey'] ) : '';
			$this->privateKey    = ! empty( $rcp_options['braintree_sandbox_privateKey'] ) ? sanitize_text_field( $rcp_options['braintree_sandbox_privateKey'] ) : '';
			$this->encryptionKey = ! empty( $rcp_options['braintree_sandbox_encryptionKey'] ) ? sanitize_text_field( $rcp_options['braintree_sandbox_encryptionKey'] ) : '';
			$this->environment   = 'sandbox';
		} else {
			$this->merchantId    = ! empty( $rcp_options['braintree_live_merchantId'] ) ? sanitize_text_field( $rcp_options['braintree_live_merchantId'] ) : '';
			$this->publicKey     = ! empty( $rcp_options['braintree_live_publicKey'] ) ? sanitize_text_field( $rcp_options['braintree_live_publicKey'] ) : '';
			$this->privateKey    = ! empty( $rcp_options['braintree_live_privateKey'] ) ? sanitize_text_field( $rcp_options['braintree_live_privateKey'] ) : '';
			$this->encryptionKey = ! empty( $rcp_options['braintree_live_encryptionKey'] ) ? sanitize_text_field( $rcp_options['braintree_live_encryptionKey'] ) : '';
			$this->environment   = 'production';
		}

		require_once RCP_PLUGIN_DIR . 'includes/libraries/braintree/lib/Braintree.php';

		Braintree_Configuration::environment( $this->environment );
		Braintree_Configuration::merchantId( $this->merchantId );
		Braintree_Configuration::publicKey( $this->publicKey );
		Braintree_Configuration::privateKey( $this->privateKey );

	}

	/**
	 * Validates the form fields.
	 * If there are any errors, it creates a new WP_Error instance
	 * via the rcp_errors() function.
	 *
	 * @see WP_Error::add()
	 * @uses rcp_errors()
	 * @return void
	 */
	public function validate_fields() {
		if ( empty( $_POST['rcp_braintree_fields_completed'] ) ) {
			rcp_errors()->add( 'missing_card_info', __( 'Credit card information incomplete.', 'rcp' ), 'register' );
		}
	}

	/**
	 * Processes a registration payment.
	 *
	 * @return void
	 */
	public function process_signup() {

		if ( empty( $_POST['payment_method_nonce'] ) ) {
			$this->handle_processing_error(
				new Exception(
					__( 'Missing Braintree payment nonce. Please try again. Contact support if the issue persists.', 'rcp' )
				)
			);
		}

		global $rcp_options;

		$paid     = false;
		$txn_args = array();
		$member   = new RCP_Member( $this->user_id );

		/**
		 * Set up the customer object.
		 *
		 * Get the customer record from Braintree if it already exists,
		 * otherwise create a new customer record.
		 */
		$customer = false;
		$payment_profile_id = $member->get_payment_profile_id();
		$payment_profile_id = ( ! empty( $payment_profile_id ) && false !== strpos( $payment_profile_id, 'bt_' ) ) ? $payment_profile_id : 'bt_' . $this->user_id;


		if ( $payment_profile_id ) {
			try {
				$customer = Braintree_Customer::find( $payment_profile_id );
			} catch ( Braintree_Exception_NotFound $e ) {
				$customer = false;
			} catch ( Exception $e ) {
				$this->handle_processing_error( $e );
			}
		}

		if ( ! $customer ) {

			try {
				$result = Braintree_Customer::create(
					array(
						'id'                 => 'bt_' . $this->user_id,
						'firstName'          => ! empty( $this->subscription_data['post_data']['rcp_user_first'] ) ? sanitize_text_field( $this->subscription_data['post_data']['rcp_user_first'] ) : '',
						'lastName'           => ! empty( $this->subscription_data['post_data']['rcp_user_last'] ) ? sanitize_text_field( $this->subscription_data['post_data']['rcp_user_last'] ) : '',
						'email'              => $this->subscription_data['user_email'],
						'riskData'           => array(
							'customerBrowser' => $_SERVER['HTTP_USER_AGENT'],
							'customerIp'      => rcp_get_ip()
						)
					)
				);

				if ( $result->success && $result->customer ) {
					$customer = $result->customer;
					$member->set_payment_profile_id( $customer->id );
				}

			} catch ( Exception $e ) {
				// Customer lookup/creation failed
				$this->handle_processing_error( $e );
			}
		}

		if ( empty( $customer ) ) {
			$this->handle_processing_error( new Exception( __( 'Unable to locate or create customer record. Please try again. Contact support is the problem persists.', 'rcp' ) ) );
		}

		/**
		 * Save the customer's payment method.
		 */
		try {

			$payment_method = Braintree_PaymentMethod::create( array(
				'customerId'         => $customer->id,
				'paymentMethodNonce' => $_POST['payment_method_nonce'],
				'options'            => array(
					'makeDefault' => true
				)
			) );

			if ( $payment_method->success ) {

				$payment_token = $payment_method->paymentMethod->token;

			} else {

				$this->handle_processing_error( new Exception( __( 'There was an error saving your payment information. Please try again. Contact support is the problem persists.', 'rcp' ) ) );

			}

		} catch ( Exception $e ) {

			$this->handle_processing_error( $e );

		}

		if ( empty( $payment_token ) ) {
			$this->handle_processing_error( new Exception( __( 'There was an error saving your payment information. Please try again. Contact support is the problem persists.', 'rcp' ) ) );

		}

		/**
		 * Set up the subscription values and create the subscription.
		 */
		if ( $this->auto_renew ) {

			/**
			 * Process signup fees and one-time discounts as a separate payment.
			 */
			if ( $this->initial_amount != $this->amount ) {

				try {
					$single_payment = Braintree_Transaction::sale( array(
						'amount'             => $this->initial_amount,
						'customerId'         => $customer->id,
						'paymentMethodToken' => $payment_token,
						'options'            => array(
							'submitForSettlement' => true
						)
					) );

					if ( $single_payment->success ) {

						$payment_data = array(
							'subscription'     => $this->subscription_data['subscription_name'],
							'date'             => date( 'Y-m-d g:i:s', time() ),
							'amount'           => $single_payment->transaction->amount,
							'user_id'          => $this->user_id,
							'payment_type'     => __( 'Braintree Credit Card Initial Payment', 'rcp' ),
							'subscription_key' => $this->subscription_data['key'],
							'transaction_id'   => $single_payment->transaction->id
						);
						$rcp_payments = new RCP_Payments;
						$rcp_payments->insert( $payment_data );


					} else {
						$this->handle_processing_error(
							new Exception(
								sprintf( __( 'There was a problem processing your payment. Message: %s', 'rcp' ), $single_payment->message )
							)
						);
					}

				} catch ( Exception $e ) {

					$this->handle_processing_error( $e );

				}

			}

			/**
			 * Cancel existing subscription if the member just upgraded to another one.
			 */
			if ( $member->just_upgraded() && $member->can_cancel() ) {
				$cancelled = $member->cancel_payment_profile( false );
			}

			/**
			 * Set up the trial values.
			 *
			 * Braintree only supports 'day' and 'month' units.
			 * If the trial is set to x year, force it to 12 months.
			 */
			if ( $this->is_trial() ) {

				$duration      = $this->subscription_data['trial_duration'];
				$duration_unit = $this->subscription_data['trial_duration_unit'];

				if ( 'year' === $duration_unit ) {
					$duration = '12';
					$duration_unit = 'month';
				}

				$txn_args['trialPeriod'] = true;
				$txn_args['trialDuration'] = $duration;
				$txn_args['trialDurationUnit'] = $duration_unit;
			}

			$txn_args['planId']             = $this->subscription_data['subscription_id'];
			$txn_args['price']              = $this->amount;
			$txn_args['paymentMethodToken'] = $payment_token;

			/**
			 * If this subscription is using a one-time discount code or
			 * a signup fee, we need to start the subscription at the end
			 * of the first period.
			 */
			if ( $this->initial_amount != $this->amount ) {
				$txn_args['firstBillingDate'] = date( 'Y-m-d g:i:s', strtotime( '+ ' . $this->subscription_data['length'] . ' ' . $this->subscription_data['length_unit'] ) );
			}

			try {
				$result = Braintree_Subscription::create( $txn_args );

				if ( $result->success ) {
					$paid = true;
				} else {
					$this->handle_processing_error(
						new Exception(
							sprintf( __( 'There was a problem processing your payment. Message: %s', 'rcp' ), $result->message )
						)
					);
				}

			} catch ( Exception $e ) {

				$this->handle_processing_error( $e );
			}

		}

		/**
		 * Process a one-time payment.
		 */
		if ( ! $this->auto_renew ) {

			$txn_args['customerId']                     = $customer->id;
			$txn_args['amount']                         = $this->initial_amount;
			$txn_args['paymentMethodToken']             = $payment_token;
			$txn_args['options']['submitForSettlement'] = true;

			try {
				$result = Braintree_Transaction::sale( $txn_args );

				if ( $result->success ) {
					$paid = true;
				} else {
					$this->handle_processing_error(
						new Exception(
							sprintf( __( 'There was a problem processing your payment. Message: %s', 'rcp' ), $result->message )
						)
					);
				}

			} catch ( Exception $e ) {

				$this->handle_processing_error( $e );
			}

		}

		/**
		 * Handle any errors that may have happened.
		 * If $result->success is not true, we very likely
		 * received a Braintree\Result\Error object, which will
		 * contain the reason for the error.
		 * Example error: Plan ID is invalid.
		 */
		if ( empty( $result ) || empty( $result->success ) ) {

			$message = sprintf( __( 'An error occurred. Please contact the site administrator: %s.', 'rcp' ), make_clickable( get_bloginfo( 'admin_email' ) ) ) . PHP_EOL;

			if ( ! empty( $result->message ) ) {
				$message .= sprintf( __( 'Error message: %s', 'rcp' ), $result->message ) . PHP_EOL;
			}

			$this->handle_processing_error( new Exception( $message ) );

		}

		/**
		 * Record the one-time payment and adjust the member properties.
		 */
		if ( $paid && ! $this->auto_renew ) {

			/**
			 * Cancel existing subscription.
			 */
			if ( $member->can_cancel() ) {
				$cancelled = $member->cancel_payment_profile( false );
			}

			// Log the one-time payment
			$payment_data = array(
				'subscription'     => $this->subscription_data['subscription_name'],
				'date'             => date( 'Y-m-d g:i:s', time() ),
				'amount'           => $result->transaction->amount,
				'user_id'          => $this->user_id,
				'payment_type'     => __( 'Braintree Credit Card One Time', 'rcp' ),
				'subscription_key' => $this->subscription_data['key'],
				'transaction_id'   => $result->transaction->id
			);
			$rcp_payments = new RCP_Payments;
			$rcp_payments->insert( $payment_data );

			// Update the member
			$member->renew( false, 'active', $member->calculate_expiration() );

		}

		if ( $paid && $this->auto_renew ) {

			$member->set_merchant_subscription_id( $result->subscription->id );

			/**
			 * Set the member status to active if this is a trial.
			 * Braintree does not send a webhook when a new trial
			 * subscription is created.
			 */
			if ( $this->is_trial() ) {

				$member->renew( true, 'active', $result->subscription->nextBillingDate->format( 'Y-m-d 23:59:59' ) );

			/**
			 * If this subscription used a one-time discount,
			 * set the expiration date to the first billing date.
			 */
			} elseif ( $this->initial_amount != $this->amount ) {

				$member->renew( true, 'active', $result->subscription->firstBillingDate->format( 'Y-m-d 23:59:59' ) );

			/**
			 * Set the expiration date for normal subscriptions.
			 */
			} else {

				$member->renew( true, 'active', $result->subscription->paidThroughDate->format( 'Y-m-d 23:59:59' ) );

			}
		}

		wp_redirect( $this->return_url ); exit;

	}

	/**
	 * Processes the Braintree webhooks.
	 *
	 * @return void
	 */
	public function process_webhooks() {

		if ( isset( $_GET['bt_challenge'] ) ) {
			try {
				$verify = Braintree_WebhookNotification::verify( $_GET['bt_challenge'] );
				die( $verify );
			} catch ( Exception $e ) {
				wp_die( 'Verification failed' );
			}
		}

		if ( ! isset( $_POST['bt_signature'] ) || ! isset( $_POST['bt_payload'] ) ) {
			return;
		}

		$data = false;

		try {
			$data = Braintree_WebhookNotification::parse( $_POST['bt_signature'], $_POST['bt_payload'] );
		} catch ( Exception $e ) {
			die( 'Invalid signature' );
		}

		if ( empty( $data->kind ) ) {
			die( 'Invalid webhook' );
		}

		/**
		 * Return early if this is a test webhook.
		 */
		if ( 'check' === $data->kind ) {
			die(200);
		}

		/**
		 * If this is a free trial cancellation, there will not
		 * be a transaction to reference in this webhook, which
		 * is where Braintree stores the customer ID associated
		 * with the webhook. We need to get the customer ID
		 * another way.
		 */
		if ( ! empty( $data->subscription->transactions) ) {

			$transaction = $data->subscription->transactions[0];
			$user_id     = rcp_get_member_id_from_profile_id( $transaction->customer['id'] );

		} elseif ( ! empty( $data->subscription->id ) ) {

			$user_id = rcp_get_member_id_from_subscription_id( $data->subscription->id );

		}

		if ( empty( $user_id ) ) {
			die( 'no user ID found' );
		}

		$member = new RCP_Member( $user_id );

		if ( ! $member->get_subscription_id() ) {
			die( 'no subscription ID for member' );
		}

		$rcp_payments = new RCP_Payments;

		/**
		 * Process the webhook.
		 *
		 * Descriptions of the webhook kinds below come from the Braintree developer docs.
		 * @see https://developers.braintreepayments.com/reference/general/webhooks/subscription/php
		 */
		switch ( $data->kind ) {

			/**
			 * A subscription is canceled.
			 */
			case 'subscription_canceled':

				if ( $member->just_upgraded() ) {
					die( 'subscription_canceled returned early. Member just upgraded.' );
				}

				$member->cancel();

				/**
				 * There won't be a paidThroughDate if a trial user cancels,
				 * so we need to check that it exists.
				 */
				if ( ! empty( $data->subscription->paidThroughDate ) ) {
					$member->set_expiration_date( $data->subscription->paidThroughDate->format( 'Y-m-d 23:59:59' ) );
				}

				$member->add_note( __( 'Subscription cancelled in Braintree', 'rcp' ) );

				die( 'braintree subscription cancelled' );

				break;

			/**
			 * A subscription successfully moves to the next billing cycle.
			 * This occurs if a new transaction is created. It will also occur
			 * when a billing cycle is skipped due to the presence of a
			 * negative balance that covers the cost of the subscription.
			 */
			case 'subscription_charged_successfully':

				if ( $rcp_payments->payment_exists( $transaction->id ) ) {
					die( 'duplicate payment found' );
				}

				$member->renew( true, 'active', $data->subscription->paidThroughDate->format( 'Y-m-d 23:59:59' ) );

				$payment_id = $rcp_payments->insert( array(
					'date'             => date_i18n( $transaction->createdAt->format( 'Y-m-d g:i:s' ) ),
					'payment_type'     => 'Braintree Credit Card',
					'user_id'          => $member->ID,
					'amount'           => $transaction->amount,
					'transaction_id'   => $transaction->id,
					'subscription'     => $member->get_subscription_name(),
					'subscription_key' => $member->get_subscription_key()
				) );

				$member->add_note( sprintf( __( 'Payment %s collected in Braintree', 'rcp' ), $payment_id ) );

				die( 'braintree payment recorded' );
				break;

			/**
			 * A subscription already exists and fails to create a successful charge.
			 * This will not trigger on manual retries or if the attempt to create a
			 * subscription fails due to an unsuccessful transaction.
			 */
			case 'subscription_charged_unsuccessfully':
				die( 'subscription_charged_unsuccessfully' );
				break;

			/**
			 * A subscription reaches the specified number of billing cycles and expires.
			 */
			case 'subscription_expired':

				$member->set_status( 'expired' );

				$member->set_expiration_date( $data->subscription->paidThroughDate->format( 'Y-m-d g:i:s' ) );

				$member->add_note( __( 'Subscription expired in Braintree', 'rcp' ) );

				die( 'member expired' );
				break;

			/**
			 * A subscription's trial period ends.
			 */
			case 'subscription_trial_ended':
				$member->renew( $member->is_recurring(), '', $data->subscription->billingPeriodEndDate->format( 'Y-m-d g:i:s' ) );
				$member->add_note( __( 'Trial ended in Braintree', 'rcp' ) );
				die( 'subscription_trial_ended processed' );
				break;

			/**
			 * A subscription's first authorized transaction is created.
			 * Subscriptions with trial periods will never trigger this notification.
			 */
			case 'subscription_went_active':

				$member->renew( true, 'active', $data->subscription->paidThroughDate->format( 'Y-m-d g:i:s' ) );

				if ( ! $rcp_payments->payment_exists( $transaction->id ) ) {

					$payment_id = $rcp_payments->insert( array(
						'date'             => date_i18n( $transaction->createdAt->format( 'Y-m-d g:i:s' ) ),
						'payment_type'     => 'Braintree Credit Card',
						'user_id'          => $member->ID,
						'amount'           => $transaction->amount,
						'transaction_id'   => $transaction->id,
						'subscription'     => $member->get_subscription_name(),
						'subscription_key' => $member->get_subscription_key()
					) );

					$member->add_note( sprintf( __( 'Subscription %s started in Braintree', 'rcp' ), $payment_id ) );
				}

				die( 'subscription went active' );
				break;

			/**
			 * A subscription has moved from the active status to the past due status.
			 * This occurs when a subscription’s initial transaction is declined.
			 */
			case 'subscription_went_past_due':
				$member->set_status( 'pending' );
				$member->add_note( __( 'Subscription went past due in Braintree', 'rcp' ) );
				die( 'subscription past due: member pending' );
				break;

			default:
				die( 'unrecognized webhook kind' );
				break;
		}
	}

	/**
	 * Handles the error processing.
	 *
	 * @param Exception $exception
	 */
	protected function handle_processing_error( $exception ) {

		do_action( 'rcp_registration_failed', $this );

		wp_die( $exception->getMessage(), __( 'Error', 'rcp' ), array( 'response' => 401 ) );

	}

	/**
	 * Outputs the credit card fields and related javascript.
	 */
	public function fields() {
		ob_start();
		rcp_get_template_part( 'card-form' );
		?>

		<input type="hidden" id="rcp-braintree-client-token" name="rcp-braintree-client-token" value="<?php echo esc_attr( Braintree_ClientToken::generate() ); ?>" />

		<script type="text/javascript">

			var rcp_form = document.getElementById("rcp_registration_form");

			/**
			 * Braintree requires data-braintree-name attributes on the inputs.
			 * Let's add them and remove the name attribute to prevent card
			 * data from being submitted to the server.
			 */
			var card_number = rcp_form.querySelector("[name='rcp_card_number']");
			var card_cvc    = rcp_form.querySelector("[name='rcp_card_cvc']");
			var card_zip    = rcp_form.querySelector("[name='rcp_card_zip']");
			var card_name   = rcp_form.querySelector("[name='rcp_card_name']");
			var card_month  = rcp_form.querySelector("[name='rcp_card_exp_month']");
			var card_year   = rcp_form.querySelector("[name='rcp_card_exp_year']");

			card_number.setAttribute('data-braintree-name', 'number');
			card_number.removeAttribute('name');

			card_cvc.setAttribute('data-braintree-name', 'cvv');
			card_cvc.removeAttribute('name');

			card_zip.setAttribute('data-braintree-name', 'postal_code');
			card_zip.removeAttribute('name');

			card_name.setAttribute('data-braintree-name', 'cardholder_name');
			card_name.removeAttribute('name');

			card_month.setAttribute('data-braintree-name', 'expiration_month');
			card_month.removeAttribute('name');

			card_year.setAttribute('data-braintree-name', 'expiration_year');
			card_year.removeAttribute('name');

			// Check that the credit card fields are filled.
			rcp_form.querySelector("#rcp_submit").addEventListener("click", function(event) {
				event.preventDefault();
				if ( card_number.value && card_cvc.value && card_zip.value && card_name.value && card_month.value && card_year.value ) {
					rcp_form.insertAdjacentHTML("beforeend", "<input name='rcp_braintree_fields_completed' type='hidden' value='true' />");
				}
			});

			jQuery('body').off('rcp_register_form_submission').on('rcp_register_form_submission', function rcp_braintree_register_form_submission_handler(event, response, form_id) {

				if ( response.gateway.slug !== 'braintree' ) {
					return;
				}

				event.preventDefault();

				var token = rcp_form.querySelector('#rcp-braintree-client-token').value;

				braintree.setup(token, 'custom', {

					id: 'rcp_registration_form',
					onReady: function (response) {
						var client = new braintree.api.Client({clientToken: token});
						client.tokenizeCard({
							number: rcp_form.querySelector("[data-braintree-name='number']").value,
							expirationDate: rcp_form.querySelector("[data-braintree-name='expiration_month']").value + '/' + rcp_form.querySelector("[data-braintree-name='expiration_year']").value
						}, function (err, nonce) {
							rcp_form.querySelector("[name='payment_method_nonce']").value = nonce;
							rcp_form.submit();
						});
					},
					onError: function (response) {
						//@todo
						console.log('onError');
						console.log(response);
					}

				});

			});
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Loads the Braintree javascript library.
	 */
	public function scripts() {
		wp_enqueue_script( 'rcp-braintree', 'https://js.braintreegateway.com/js/braintree-2.30.0.min.js' );
	}

}