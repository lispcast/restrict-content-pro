<?php
/**
 * Cron Functions
 *
 * Schedules events.
 *
 * @package     Restrict Content Pro
 * @subpackage  Cron Functions
 * @copyright   Copyright (c) 2017, Restrict Content Pro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/**
 * Set up the following cron job events:
 *
 * Daily: Expired users check
 * @see rcp_check_for_expired_users()
 *
 * Daily: Send expiring soon notices
 * @see rcp_check_for_soon_to_expire_users()
 *
 * Daily: Check and update member counts
 * @see rcp_check_member_counts()
 *
 * @return void
 */
function rcp_setup_cron_jobs() {

	if ( ! wp_next_scheduled( 'rcp_expired_users_check' ) ) {
		wp_schedule_event( current_time( 'timestamp' ), 'daily', 'rcp_expired_users_check' );
	}

	if ( ! wp_next_scheduled( 'rcp_send_expiring_soon_notice' ) ) {
		wp_schedule_event( current_time( 'timestamp' ), 'daily', 'rcp_send_expiring_soon_notice' );
	}

	if ( ! wp_next_scheduled( 'rcp_check_member_counts' ) ) {
		wp_schedule_event( current_time( 'timestamp' ), 'daily', 'rcp_check_member_counts' );
	}
}
add_action('wp', 'rcp_setup_cron_jobs');

/**
 * Check for expired users
 *
 * Runs each day and checks for expired users. If their account has expired, their status
 * is updated to "expired" and, based on settings, they may receive an email.
 *
 * @see rcp_email_on_expiration()
 *
 * @return void
 */
function rcp_check_for_expired_users() {

	global $wpdb;

	$current_time = date( 'Y-m-d H:i:s', strtotime( '-1 day', current_time( 'timestamp' ) ) );

	$query = "SELECT ID FROM $wpdb->users
		INNER JOIN $wpdb->usermeta ON ($wpdb->users.ID = $wpdb->usermeta.user_id)
		INNER JOIN $wpdb->usermeta AS mt1 ON ($wpdb->users.ID = mt1.user_id)
		INNER JOIN $wpdb->usermeta AS mt2 ON ($wpdb->users.ID = mt2.user_id)
		WHERE 1=1 AND ( ($wpdb->usermeta.meta_key = 'rcp_expiration'
			AND CAST($wpdb->usermeta.meta_value AS DATETIME) < '$current_time')
			AND  (mt1.meta_key = 'rcp_expiration'
				AND CAST(mt1.meta_value AS CHAR) != 'none')
			AND  (mt2.meta_key = 'rcp_status'
				AND CAST(mt2.meta_value AS CHAR) = 'active') )
		ORDER BY user_login ASC LIMIT 9999";

	$query = apply_filters( 'rcp_check_for_expired_users_query_filter', $query );

	$expired_members = $wpdb->get_results( $query );
	$expired_members = wp_list_pluck( $expired_members, 'ID' );
	$expired_members = apply_filters( 'rcp_check_for_expired_users_members_filter', $expired_members );

	if( $expired_members ) {
		foreach( $expired_members as $key => $member_id ) {

			$expiration_date = rcp_get_expiration_timestamp( $member_id );
			if( $expiration_date && strtotime( '-2 days', current_time( 'timestamp' ) ) > $expiration_date ) {
				rcp_set_status( $member_id, 'expired' );
			}
		}
	}
}
//add_action( 'admin_init', 'rcp_check_for_expired_users' );
add_action( 'rcp_expired_users_check', 'rcp_check_for_expired_users' );

/**
 * Check for soon-to-expire users
 *
 * Runs each day and checks for members that are soon to expire. Based on settings, each
 * member gets sent an expiry notice email.
 *
 * @uses rcp_get_renewal_reminder_period()
 * @uses rcp_email_expiring_notice()
 *
 * @return void
 */
function rcp_check_for_soon_to_expire_users() {

	$renewal_period = rcp_get_renewal_reminder_period();

	if( 'none' == $renewal_period )
		return; // Don't send renewal reminders

	$args = array(
		'meta_query'     => array(
			'relation'   => 'AND',
			array(
				'key'    => 'rcp_expiration',
				'value'  => current_time( 'mysql' ),
				'type'   => 'DATETIME',
				'compare'=> '>='
			),
			array(
				'key'    => 'rcp_expiration',
				'value'  => date( 'Y-m-d H:i:s', strtotime( $renewal_period, current_time( 'timestamp' ) ) ),
				'type'   => 'DATETIME',
				'compare'=> '<='
			),
			array(
				'key'    => 'rcp_recurring',
				'compare'=> 'NOT EXISTS'
			),
			array(
				'key'    => 'rcp_status',
				'compare'=> '=',
				'value'  => 'active'
			)
		),
		'number' 		=> 9999,
		'count_total' 	=> false,
		'fields'        => 'ids'
	);

	$expiring_members = get_users( $args );
	if( $expiring_members ) {
		foreach( $expiring_members as $member ) {

			if( get_user_meta( $member, '_rcp_expiring_soon_email_sent', true ) )
				continue;

			rcp_email_expiring_notice( $member );
			add_user_meta( $member, '_rcp_expiring_soon_email_sent', 'yes' );
			rcp_add_member_note( $member, __( 'Expiration notice was emailed to the member.', 'rcp' ) );

		}
	}
}
add_action( 'rcp_send_expiring_soon_notice', 'rcp_check_for_soon_to_expire_users' );

/**
 * Counts the active members on a subscription level to ensure counts are accurate.
 *
 * Runs once per day
 *
 * @since 2.6
 *
 * @return void
 */
function rcp_check_member_counts() {

	global $rcp_levels_db;
	$levels = $rcp_levels_db->get_levels();

	if( ! $levels ) {
		return;
	}

	$statuses = array( 'active', 'pending', 'cancelled', 'expired', 'free' );

	foreach( $levels as $level ) {

		foreach( $statuses as $status ) {

			$count = rcp_count_members( $level->id, $status );
			$key   = $level->id . '_' . $status . '_member_count';
			$rcp_levels_db->update_meta( $level->id, $key, $count );

		}

	}
}
add_action( 'rcp_check_member_counts', 'rcp_check_member_counts' );