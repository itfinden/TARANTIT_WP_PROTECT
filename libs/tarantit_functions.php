<?php
namespace TarantIT;
require_once 'tarantit_basic_functions.php';
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

	public static function Get_Info_Tarantit($action) {
		#$action = ['allow','200.11.200.191','description'];
		if (is_array($action)) {
			$action = implode('/', $action);
		}

		$query = "api.tarantit.com/v1" . $action;
		$output = '';
		$secret_key = 'itfinden.com';
		$secret_iv = '6F6A970A092B53382CCA22FEE54691940CB6D131A3A4CAB6A404';
		$encrypt_method = "AES-256-CBC";
		$info = 'TARANTIT|' . $secret_iv;

// hash
		$key = hash('sha256', $secret_key);

// iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
		$iv = substr(hash('sha256', $secret_iv), 0, 16);
		$output = openssl_encrypt($info, $encrypt_method, $key, 0, $iv);
		$output = base64_encode($output);

		$curl = curl_init(); // Create Curl Object.
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Allow self-signed certificates...
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // and certificates that don't match the hostname.
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return contents of transfer on curl_exec.
		curl_setopt($curl, CURLOPT_POSTFIELDS, ['Authorization' => $output, 'HTTP_HOST' => $secret_key]); // Set the username and password.
		curl_setopt($curl, CURLOPT_URL, $query); // Execute the query.
		$result = curl_exec($curl);
		if ($result == false) {
			Tarantit_Write_Log("Get_Info_Tarantit : curl_exec threw error \"" . curl_error($curl) . "\" for $query");
			// log error if curl exec fails
			return "FAIL REQUEST";
		} else {
			return $result;
		}

	}

}
?>


