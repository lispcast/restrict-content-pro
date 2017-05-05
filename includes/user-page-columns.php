<?php
/**
 * User Page Columns
 *
 * Functions for adding extra columns to the Users > All Users table.
 *
 * @package     Restrict Content Pro
 * @subpackage  User Page Columns
 * @copyright   Copyright (c) 2017, Restrict Content Pro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/**
 * Add user columns for Subscription, Status, and Actions
 *
 * @param array $columns
 *
 * @return array
 */
function rcp_add_user_columns( $columns ) {
	$columns['rcp_subscription'] 	= __( 'Subscription', 'rcp' );
    $columns['rcp_status'] 			= __( 'Status', 'rcp' );
	$columns['rcp_links'] 			= __( 'Actions', 'rcp' );
    return $columns;
}
add_filter( 'manage_users_columns', 'rcp_add_user_columns' );

/**
 * Display user column values
 *
 * @param string $value       Column value.
 * @param string $column_name Name of the current column.
 * @param int    $user_id     ID of the user.
 *
 * @return string
 */
function rcp_show_user_columns( $value, $column_name, $user_id ) {
	if ( 'rcp_status' == $column_name )
		return rcp_get_status( $user_id );
	if ( 'rcp_subscription' == $column_name ) {
		return rcp_get_subscription( $user_id );
	}
	if ( 'rcp_links' == $column_name ) {
		$page = admin_url( '/admin.php?page=rcp-members' );
		if( rcp_is_active( $user_id ) ) {
			$links = '<a href="' . esc_url( $page ) . '&edit_member=' . esc_attr( absint( $user_id ) ) . '">' . __( 'Edit Subscription', 'rcp' ) . '</a>';
		} else {
			$links = '<a href="' . esc_url( $page ) . '&edit_member=' . esc_attr( absint( $user_id ) ) . '">' . __( 'Add Subscription', 'rcp' ) . '</a>';
		}
		
		return $links;
	}
	return $value;
}
add_filter( 'manage_users_custom_column',  'rcp_show_user_columns', 100, 3 );
