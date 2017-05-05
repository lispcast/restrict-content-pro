<?php
/**
 * Logs Pages
 *
 * @package     Restrict Content Pro
 * @subpackage  Admin/Logs
 * @copyright   Copyright (c) 2017, Restrict Content Pro
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

require_once( dirname( __FILE__ ) . '/logs-list-table.php' );

/**
 * Render the logs page
 *
 * @return void
 */
function rcp_logs_page() {
	?>
	<div class="wrap">

        <div id="icon-tools" class="icon32"><br/></div>
        <h1><?php _e( 'RCP Error Logs', 'rcp' ); ?></h1>

       	<form method="get" id="rcp-error-logs">
       		<input type="hidden" name="page" value="rcp-logs"/>
	        <?php

	        $logs_table = new RCP_Logs_List_Table();

	        //Fetch, prepare, sort, and filter our data...
	        $logs_table->prepare_items();

	        $logs_table->views();

	        $logs_table->display();

        ?>
    	</form>
    </div>
    <?php
}