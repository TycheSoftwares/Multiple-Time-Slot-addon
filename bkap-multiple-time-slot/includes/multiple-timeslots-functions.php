<?php

/**
 * Fucntion to check if the addon is active or not. Used in other plugins.
 * 
 * @since 1.0
 */

function is_bkap_multi_time_active() {
	if ( is_plugin_active( 'bkap-multiple-time-slot/multiple-time-slot.php' ) ) {
		return true;
	} else {
		return false;
	}
}

?>