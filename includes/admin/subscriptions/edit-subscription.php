<?php
/**
 * Edit Subscription Page
 *
 * @package     Restrict Content Pro
 * @subpackage  Admin/Edit Subscription
 * @copyright   Copyright (c) 2017, Restrict Content Pro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

$level = rcp_get_subscription_details( absint( urldecode( $_GET['edit_subscription'] ) ) );
$level->role = empty( $level->role ) ? 'subscriber' : $level->role;

global $rcp_levels_db;
$trial_duration = ! empty( $level->trial_duration ) ? $level->trial_duration : 0;
$trial_duration_unit = in_array( $level->trial_duration_unit, array( 'day', 'month', 'year' ) ) ? $level->trial_duration_unit : 'day'
?>
<h1>
	<?php _e( 'Edit Subscription Level:', 'rcp' ); echo ' ' . stripslashes( $level->name ); ?>
	<a href="<?php echo admin_url( '/admin.php?page=rcp-member-levels' ); ?>" class="add-new-h2">
		<?php _e( 'Cancel', 'rcp' ); ?>
	</a>
</h1>
<form id="rcp-edit-subscription" action="" method="post">
	<table class="form-table">
		<tbody>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-name"><?php _e( 'Name', 'rcp' ); ?></label>
				</th>
				<td>
					<input name="name" id="rcp-name" type="text" value="<?php echo esc_attr( stripslashes( $level->name ) ); ?>"/>
					<p class="description"><?php _e( 'The name of this subscription. This is shown on the registration page.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-description"><?php _e( 'Description', 'rcp' ); ?></label>
				</th>
				<td>
					<textarea name="description" id="rcp-description"><?php echo esc_textarea( stripslashes( $level->description ) ); ?></textarea>
					<p class="description"><?php _e( 'The description of this subscription. This is shown on the registration page.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-level"><?php _e( 'Access Level', 'rcp' ); ?></label>
				</th>
				<td>
					<select id="rcp-level" name="level">
						<?php
						foreach( rcp_get_access_levels() as $access ) {
							echo '<option value="' . absint( $access ) . '" ' . selected( $access, $level->level, false ) . '">' . esc_html( $access ) . '</option>';
						}
						?>
					</select>
					<p class="description"><?php _e( 'Level of access this subscription gives.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-duration"><?php _e( 'Duration', 'rcp' ); ?></label>
				</th>
				<td>
					<input type="text" id="rcp-duration" style="width: 40px;" name="duration" value="<?php echo absint( $level->duration ); ?>"/>
					<select name="duration_unit" id="rcp-duration-unit">
						<option value="day" <?php selected( $level->duration_unit, 'day' ); ?>><?php _e( 'Day(s)', 'rcp' ); ?></option>
						<option value="month" <?php selected( $level->duration_unit, 'month' ); ?>><?php _e( 'Month(s)', 'rcp' ); ?></option>
						<option value="year" <?php selected( $level->duration_unit, 'year' ); ?>><?php _e( 'Year(s)', 'rcp' ); ?></option>
					</select>
					<p class="description"><?php _e( 'Length of time for this membership level. Enter 0 for unlimited.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="trial_duration"><?php _e('Free Trial Duration', 'rcp'); ?></label>
				</th>
				<td>
					<input type="text" id="trial_duration" style="width: 40px;" name="trial_duration" value="<?php echo absint( $trial_duration ); ?>"/>
					<select name="trial_duration_unit" id="trial_duration_unit">
						<option value="day" <?php selected( $trial_duration_unit, 'day' ); ?>><?php _e('Day(s)', 'rcp'); ?></option>
						<option value="month" <?php selected( $trial_duration_unit, 'month' ); ?>><?php _e('Month(s)', 'rcp'); ?></option>
						<option value="year" <?php selected( $trial_duration_unit, 'year' ); ?>><?php _e('Year(s)', 'rcp'); ?></option>
					</select>
					<p class="description">
						<?php _e('Length of time the free trial should last. Enter 0 for no free trial.', 'rcp'); ?>
						<span alt="f223" class="rcp-help-tip dashicons dashicons-editor-help" title="<?php _e( '<strong>Example</strong>: setting this to 7 days would give the member a 7-day free trial. The member would be billed at the end of the trial. <p><strong>Note:</strong> If you enable a free trial, the regular subscription duration and price must be greater than 0.</p>', 'rcp' ); ?>"></span>
					</p>
				</td>
			</tr>

			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-price"><?php _e( 'Price', 'rcp' ); ?></label>
				</th>
				<td>
					<input type="text" id="rcp-price" name="price" value="<?php echo esc_attr( $level->price ); ?>" pattern="^(\d+\.\d{2})|(\d+)$" style="width: 40px;"/>
					<p class="description"><?php _e( 'The price of this membership level. Enter 0 for free.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-fee"><?php _e( 'Signup Fee', 'rcp' ); ?></label>
				</th>
				<td>
					<input type="text" id="rcp-fee" name="fee" value="<?php echo esc_attr( $level->fee ); ?>" style="width: 40px;"/>
					<p class="description"><?php _e( 'Optional signup fee to charge subscribers for the first billing cycle. Enter a negative number to give a discount on the first payment.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-status"><?php _e( 'Status', 'rcp' ); ?></label>
				</th>
				<td>
					<select name="status" id="rcp-status">
						<option value="active" <?php selected( $level->status, 'active' ); ?>><?php _e( 'Active', 'rcp' ); ?></option>
						<option value="inactive" <?php selected( $level->status, 'inactive' ); ?>><?php _e( 'Inactive', 'rcp' ); ?></option>
					</select>
					<p class="description"><?php _e( 'Members may only sign up for active subscription levels.', 'rcp' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top">
					<label for="rcp-role"><?php _e( 'User Role', 'rcp' ); ?></label>
				</th>
				<td>
					<select name="role" id="rcp-role">
						<?php wp_dropdown_roles( $level->role ); ?>
					</select>
					<p class="description"><?php _e( 'The user role given to the member after signing up.', 'rcp' ); ?></p>
				</td>
			</tr>
			<?php do_action( 'rcp_edit_subscription_form', $level ); ?>
		</tbody>
	</table>
	<p class="submit">
		<input type="hidden" name="rcp-action" value="edit-subscription"/>
		<input type="hidden" name="subscription_id" value="<?php echo absint( urldecode( $_GET['edit_subscription'] ) ); ?>"/>
		<input type="submit" value="<?php _e( 'Update Subscription', 'rcp' ); ?>" class="button-primary"/>
	</p>
	<?php wp_nonce_field( 'rcp_edit_level_nonce', 'rcp_edit_level_nonce' ); ?>
</form>