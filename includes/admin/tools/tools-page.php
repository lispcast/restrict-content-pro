<?php
/**
 * Tools Page
 *
 * @package     Restrict Content Pro
 * @subpackage  Admin/Tools
 * @copyright   Copyright (c) 2017, Restrict Content Pro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

/**
 * Displays the Tools page
 *
 * @since 2.5
 * @return void
 */
function rcp_tools_page() {
	if( ! current_user_can( 'rcp_view_payments' ) ) {
		return;
	}

	include RCP_PLUGIN_DIR . 'includes/admin/tools/system-info.php';
?>

	<div class="wrap">
		<h1><?php _e( 'Restrict Content Pro Tools', 'rcp' ); ?></h1>

		<form action="<?php echo esc_url( admin_url( 'admin.php?page=rcp-tools' ) ); ?>" method="post" dir="ltr">
			<textarea readonly="readonly" onclick="this.focus(); this.select()" id="rcp-system-info-textarea" name="rcp-sysinfo" title="To copy the system info, click below then press Ctrl + C (PC) or Cmd + C (Mac)."><?php echo rcp_tools_system_info_report(); ?></textarea>
			<p class="submit">
				<input type="hidden" name="rcp-action" value="download_sysinfo" />
				<?php submit_button( 'Download System Info File', 'primary', 'rcp-download-sysinfo', false ); ?>
			</p>
		</form>
	</div>
<?php
}

/**
 * Listens for system info download requests and delivers the file
 *
 * @since 2.5
 * @return void
 */
function rcp_tools_sysinfo_download() {

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return;
	}

	if ( ! current_user_can( 'rcp_view_payments' ) ) {
		return;
	}

	if ( ! isset( $_POST['rcp-download-sysinfo'] ) ) {
		return;
	}

	nocache_headers();

	header( 'Content-Type: text/plain' );
	header( 'Content-Disposition: attachment; filename="rcp-system-info.txt"' );

	echo wp_strip_all_tags( $_POST['rcp-sysinfo'] );
	exit;
}
add_action( 'admin_init', 'rcp_tools_sysinfo_download' );