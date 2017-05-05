<?php
/**
 * Deprecated Functions
 *
 * These are kept here for backwards compatibility with extensions that might be using them
 *
 * @package     Restrict Content Pro
 * @subpackage  Deprecated Functions
 * @copyright   Copyright (c) 2017, Restrict Content Pro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.5
 */

/**
 * Retrieve all payments from database
 *
 * @deprecated  1.5
 * @access      private
 * @param       int $offset The number to skip
 * @param       int $number The number to retrieve
 *
 * @return      array
*/
function rcp_get_payments( $offset = 0, $number = 20 ) {
	global $wpdb, $rcp_payments_db_name;
	if( $number > 0 ) {
		$payments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->escape( $rcp_payments_db_name ) . " ORDER BY id DESC LIMIT %d,%d;", absint( $offset ), absint( $number ) ) );
	} else {
		// when retrieving all payments, the query is cached
		$payments = get_transient( 'rcp_payments' );
		if( $payments === false ) {
			$payments = $wpdb->get_results( "SELECT * FROM " . $wpdb->escape( $rcp_payments_db_name ) . " ORDER BY id DESC;" ); // this is to get all payments
			set_transient( 'rcp_payments', $payments, 10800 );
		}
	}
	return $payments;
}


/**
 * Retrieve the total number of payments in the database
 *
 * @deprecated  1.5
 * @access      private
 * @return      int
*/
function rcp_count_payments() {
	global $wpdb, $rcp_payments_db_name;
	$count = get_transient( 'rcp_payments_count' );
	if( $count === false ) {
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM " . $rcp_payments_db_name . ";" );
		set_transient( 'rcp_payments_count', $count, 10800 );
	}
	return $count;
}


/**
 * Retrieve total site earnings
 *
 * @deprecated  1.5
 * @access      private
 * @return      float
*/
function rcp_get_earnings() {
	global $wpdb, $rcp_payments_db_name;
	$payments = get_transient( 'rcp_earnings' );
	if( $payments === false ) {
		$payments = $wpdb->get_results( "SELECT amount FROM " . $rcp_payments_db_name . ";" );
		// cache the payments query
		set_transient( 'rcp_earnings', $payments, 10800 );
	}
	$total = (float) 0.00;
	if( $payments ) :
		foreach( $payments as $payment ) :
			$total = $total + $payment->amount;
		endforeach;
	endif;
	return $total;
}


/**
 * Insert a payment into the database
 *
 * @deprecated  1.5
 * @access      private
 * @param       array $payment_data The data to store
 *
 * @return      INT the ID of the new payment, or false if insertion fails
*/
function rcp_insert_payment( $payment_data = array() ) {
	global $rcp_payments_db;
	return $rcp_payments_db->insert( $payment_data );
}


/**
 * Check if a payment already exists
 *
 * @deprecated  1.5
 * @access      private
 * @param       $type string The type of payment (web_accept, subscr_payment, Credit Card, etc)
 * @param       $date string/date The date of tpaen
 * @param       $subscriptionkey string The subscription key the payment is connected to
 * @return      bool
*/
function rcp_check_for_existing_payment( $type, $date, $subscription_key ) {

	global $wpdb, $rcp_payments_db_name;

	if( $wpdb->get_results( $wpdb->prepare("SELECT id FROM " . $rcp_payments_db_name . " WHERE `date`='%s' AND `subscription_key`='%s' AND `payment_type`='%s';", $date, $subscription_key, $type ) ) )
		return true; // this payment already exists

	return false; // this payment doesn't exist
}


/**
 * Retrieves the amount for the lat payment made by a user
 *
 * @access      private
 * @param       int $user_id The ID of the user to retrieve a payment amount for
 * @return      float
*/
function rcp_get_users_last_payment_amount( $user_id = 0 ) {
	global $wpdb, $rcp_payments_db_name;
	$query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $rcp_payments_db_name . " WHERE `user_id`='%d' ORDER BY id DESC LIMIT 1;", $user_id ) );
	return $query[0]->amount;
}


/**
 * Calculates a new expiration
 *
 * @deprecated  2.4
 * @access      private
 * @param       $expiration_object
 * @return      string
 */
function rcp_calc_member_expiration( $expiration_object ) {

	$member_expires = 'none';

	if( $expiration_object->duration > 0 ) {

		$current_time       = current_time( 'timestamp' );
		$last_day           = cal_days_in_month( CAL_GREGORIAN, date( 'n', $current_time ), date( 'Y', $current_time ) );

		$expiration_unit 	= $expiration_object->duration_unit;
		$expiration_length 	= $expiration_object->duration;
		$member_expires 	= date( 'Y-m-d H:i:s', strtotime( '+' . $expiration_length . ' ' . $expiration_unit . ' 23:59:59', current_time( 'timestamp' ) ) );

		if( date( 'j', $current_time ) == $last_day && 'day' != $expiration_unit ) {
			$member_expires = date( 'Y-m-d H:i:s', strtotime( $member_expires . ' +2 days', current_time( 'timestamp' ) ) );
		}

	}

	return apply_filters( 'rcp_calc_member_expiration', $member_expires, $expiration_object );
}

/**
 * Generate URL to download a PDF invoice
 *
 * @since 2.0
 * @deprecated 2.6 Use rcp_get_invoice_url() instead.
 * @return string
*/
function rcp_get_pdf_download_url( $payment_id = 0 ) {
	return rcp_get_invoice_url( $payment_id );
}

/**
 * User level checks
 *
 * @deprecated 2.7
 * @return void
 */
function rcp_user_level_checks() {
	if ( current_user_can( 'read' ) ) {
		if ( current_user_can( 'edit_posts' ) ) {
			if ( current_user_can( 'upload_files' ) ) {
				if ( current_user_can( 'moderate_comments' ) ) {
					if ( current_user_can( 'switch_themes' ) ) {
						//do nothing here for admin
					} else {
						add_filter( 'the_content', 'rcp_display_message_to_editors' );
					}
				} else {
					add_filter( 'the_content', 'rcp_display_message_authors' );
				}
			} else {
				add_filter( 'the_content', 'rcp_display_message_to_contributors' );
			}
		} else {
			add_filter( 'the_content', 'rcp_display_message_to_subscribers' );
		}
	} else {
		add_filter( 'the_content', 'rcp_display_message_to_non_loggged_in_users' );
	}
}
// add_action( 'loop_start', 'rcp_user_level_checks' );

/**
 * Display message to editors
 *
 * @deprecated 2.7
 *
 * @param string $content
 *
 * @return string
 */
function rcp_display_message_to_editors( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( $user_level == 'Administrator' && $has_access ) {
		return rcp_format_teaser( $message );
	}
	return $content;
}

/**
 * Display message to authors
 *
 * @deprecated 2.7
 *
 * @param string $content
 *
 * @return string
 */
function rcp_display_message_authors( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( ( $user_level == 'Administrator' || $user_level == 'Editor' )  && $has_access ) {
		return rcp_format_teaser( $message );
	}
	// return the content unfilitered
	return $content;
}

/**
 * Display message to contributors
 *
 * @deprecated 2.7
 *
 * @param string $content
 *
 * @return string
 */
function rcp_display_message_to_contributors( $content ) {
	global $rcp_options, $post, $user_ID;

	$message = $rcp_options['free_message'];
	$paid_message = $rcp_options['paid_message'];
	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( ( $user_level == 'Administrator' || $user_level == 'Editor' || $user_level == 'Author' ) && $has_access ) {
		return rcp_format_teaser( $message );
	}
	// return the content unfilitered
	return $content;
}

/**
 * Display message to subscribers
 *
 * @deprecated 2.7
 *
 * @param string $content
 *
 * @return string
 */
function rcp_display_message_to_subscribers( $content ) {
	global $rcp_options, $post, $user_ID;

	$message      = isset( $rcp_options['free_message'] ) ? $rcp_options['free_message'] : '';
 	$paid_message = isset( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : '';

	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );

	$has_access = false;
	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}
	if ( $user_level == 'Administrator' || $user_level == 'Editor' || $user_level == 'Author' || $user_level == 'Contributor' || !$has_access ) {
		return rcp_format_teaser( $message );
	}
	// return the content unfilitered
	return $content;
}

/**
 * Display error message to non-logged in users
 *
 * @deprecated 2.7
 *
 * @param string $content
 *
 * @return string
 */
function rcp_display_message_to_non_loggged_in_users( $content ) {
	global $rcp_options, $post, $user_ID;

	$message      = isset( $rcp_options['free_message'] ) ? $rcp_options['free_message'] : '';
	$paid_message = isset( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : '';

	if ( rcp_is_paid_content( $post->ID ) ) {
		$message = $paid_message;
	}

	$user_level   = get_post_meta( $post->ID, 'rcp_user_level', true );
	$access_level = get_post_meta( $post->ID, 'rcp_access_level', true );
	$has_access   = false;

	if ( rcp_user_has_access( $user_ID, $access_level ) ) {
		$has_access = true;
	}

	if ( ! is_user_logged_in() && ( $user_level == 'Administrator' || $user_level == 'Editor' || $user_level == 'Author' || $user_level == 'Contributor' || $user_level == 'Subscriber' ) && $has_access ) {
		return rcp_format_teaser( $message );
	}

	// return the content unfilitered
	return $content;
}

/**
 * Parses email template tags
 *
 * @deprecated 2.7
*/
function rcp_filter_email_tags( $message, $user_id, $display_name ) {

	$user = get_userdata( $user_id );

	$site_name = stripslashes_deep( html_entity_decode( get_bloginfo('name'), ENT_COMPAT, 'UTF-8' ) );

	$rcp_payments = new RCP_Payments();

	$message = str_replace( '%blogname%', $site_name, $message );
	$message = str_replace( '%username%', $user->user_login, $message );
	$message = str_replace( '%useremail%', $user->user_email, $message );
	$message = str_replace( '%firstname%', html_entity_decode( $user->first_name, ENT_COMPAT, 'UTF-8' ), $message );
	$message = str_replace( '%lastname%', html_entity_decode( $user->last_name, ENT_COMPAT, 'UTF-8' ), $message );
	$message = str_replace( '%displayname%', html_entity_decode( $display_name, ENT_COMPAT, 'UTF-8' ), $message );
	$message = str_replace( '%expiration%', rcp_get_expiration_date( $user_id ), $message );
	$message = str_replace( '%subscription_name%', html_entity_decode( rcp_get_subscription($user_id), ENT_COMPAT, 'UTF-8' ), $message );
	$message = str_replace( '%subscription_key%', rcp_get_subscription_key( $user_id ), $message );
	$message = str_replace( '%amount%', html_entity_decode( rcp_currency_filter( $rcp_payments->last_payment_of_user( $user_id ) ), ENT_COMPAT, 'UTF-8' ), $message );

	return apply_filters( 'rcp_email_tags', $message, $user_id );
}

/**
 * reverse of strstr()
 *
 * @deprecated 2.7.2
 *
 * @param string $haystack
 * @param string $needle
 *
 * @return string
 */
function rcp_rstrstr( $haystack, $needle ) {
	return substr( $haystack, 0, strpos( $haystack, $needle ) );
}

/**
 * Filter restricted content based on category restrictions
 *
 * @deprecated 2.7 This is now covered by rcp_filter_restricted_content()
 *
 * @access      public
 * @since       2.0
 * @return      $content
 */
function rcp_filter_restricted_category_content( $content ) {
	global $post, $rcp_options;

	$restrictions = array();

	foreach( rcp_get_restricted_taxonomies() as $taxonomy ) {
		$restriction = rcp_is_post_taxonomy_restricted( $post->ID, $taxonomy );

		// -1 means that the taxonomy terms are unrestricted
		if ( -1 === $restriction ) {
			continue;
		}

		// true or false. Whether or not the user has access to the restricted taxonomy terms
		$restrictions[] = $restriction;

	}

	if ( empty( $restrictions ) ) {
		return $content;
	}

	$restricted = ( apply_filters( 'rcp_restricted_taxonomy_match_all', false ) ) ? false !== array_search( true, $restrictions ) : false === array_search( false, $restrictions );

	if ( $restricted ) {

		$message = ! empty( $rcp_options['paid_message'] ) ? $rcp_options['paid_message'] : __( 'You need to have an active subscription to view this content.', 'rcp' );

		return rcp_format_teaser( $message );

	}

	return $content;

}
// add_filter( 'the_content', 'rcp_filter_restricted_category_content', 101 );