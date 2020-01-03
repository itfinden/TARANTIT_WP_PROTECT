<?php
if (!function_exists('Tarantit_Write_Log')) {

	function Tarantit_Write_Log($log) {
		if (true === WP_DEBUG) {
			if (is_array($log) || is_object($log)) {
				error_log(print_r($log, true));
			} else {
				error_log($log);
			}
		}
	}

}

Tarantit_Write_Log('THIS IS THE START OF MY CUSTOM DEBUG');
//i can log data like objects
Tarantit_Write_Log($whatever_you_want_to_log);

?>