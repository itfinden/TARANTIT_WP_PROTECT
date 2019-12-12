<?php
namespace TarantIT;

/**
 *
 */
class Tarantit_Functions {

	/**
	 * Remove the transients set when verifying the restrictions.
	 *
	 * @return void
	 */
	public static function reset_plugin_transients() {
		global $wpdb;
		// Remove all the transients records in one query.
		$tmp_query = $wpdb->prepare(
			' DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE %s OR option_name LIKE %s ',
			$wpdb->esc_like('_transient_geo-country-code') . '%',
			$wpdb->esc_like('_transient_timeout_geo-country-code') . '%'
		);
		$wpdb->query($tmp_query); // WPCS: Unprepared SQL OK.

		if (is_multisite()) {
			// Attempt to flush transient also on multisite.
			$tmp_query = $wpdb->prepare(
				' DELETE FROM ' . $wpdb->sitemeta . ' WHERE meta_key LIKE %s OR option_name LIKE %s ',
				$wpdb->esc_like('_transient_geo-country-code') . '%',
				$wpdb->esc_like('_transient_timeout_geo-country-code') . '%'
			);
			$wpdb->query($tmp_query); // WPCS: Unprepared SQL OK.
		}
	}

}
?>