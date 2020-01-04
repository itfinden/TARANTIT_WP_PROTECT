<?php
#Tarantit_Write_Log('THIS IS THE START OF MY CUSTOM DEBUG');
#Tarantit_Write_Log($whatever_you_want_to_log);

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

// Funcion o accion a realizar
function enviar_email() {
	return wp_mail('example@example.com', 'Notification ', 'Prueba', null);
}

// Cron comprobamos sino existe la funcion ya asignada y sino existe programamos el evento
function custom_cron_job() {
	if (!wp_next_scheduled('enviar_email')) {
		wp_schedule_event(current_time('timestamp'), 'every-5-minutes', 'enviar_email');
	}
}

// agregamos la accion
add_action('wp', 'custom_cron_job');

/**
 * Adds a custom cron schedule for every 5 minutes.
 *
 * @param array $schedules An array of non-default cron schedules.
 * @return array Filtered array of non-default cron schedules.
 */
function devhub_custom_cron_schedule($schedules) {
	$schedules['every-5-minutes'] = array('interval' => 5 * MINUTE_IN_SECONDS, 'display' => __('Every 5 minutes', 'devhub'));
	return $schedules;
}
add_filter('cron_schedules', 'devhub_custom_cron_schedule');

function custom_cron_job_recurrence($schedules) {
	if (!isset($schedules['10sec'])) {
		$schedules['10sec'] = array(
			'display' => __('Every 10 Seconds', 'twentyfifteen'),
			'interval' => 10,
		);
	}

	if (!isset($schedules['15sec'])) {
		$schedules['15sec'] = array(
			'display' => __('Every 15 Seconds', 'twentyfifteen'),
			'interval' => 15,
		);
	}

	return $schedules;
}
add_filter(‘cron_schedules’, ‘custom_cron_job_recurrence’);

?>