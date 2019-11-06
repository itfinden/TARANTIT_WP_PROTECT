<?php
/**
 * Plugin Name: Login IP & Country Restriction
 * Plugin URI: https://iuliacazan.ro/login-ip-country-restriction/
 * Description: This plugin hooks in the authenticate filter. By default, the plugin is set to allow all access and you can configure the plugin to allow the login only from some specified IPs or the specified countries. PLEASE MAKE SURE THAT YOU CONFIGURE THE PLUGIN TO ALLOW YOUR OWN ACCESS. If you set a restriction by IP, then you have to add your own IP (if you are using the plugin in a local setup the IP is 127.0.0.1 or ::1, this is added in your list by default). If you set a restriction by country, then you have to select from the list of countries at least your country. The both types of restrictions work independent, so you can set only one type of restriction or both if you want.
 * Text Domain: sislrc
 * Domain Path: /langs
 * Version: 3.6
 * Author: Iulia Cazan
 * Author URI: https://profiles.wordpress.org/iulia-cazan
 * Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ
 * License: GPL2
 *
 * @package sislrc
 *
 * Copyright (C) 2014-2019 Iulia Cazan
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Define the plugin version.
define('SISANU_RCIL_DB_OPTION', 'sisanu_rcil');
define('SISANU_RCIL_CURRENT_DB_VERSION', 3.6);

/**
 * Class for Login IP & Country Restriction.
 */
class SISANU_Restrict_Country_IP_Login extends TIT_functions {

	const PLUGIN_NAME = 'Login IP & Country Restriction';
	const PLUGIN_SUPPORT_URL = 'https://wordpress.org/support/plugin/login-ip-country-restriction/';
	const PLUGIN_TRANSIENT = 'sislrc-plugin-notice';

	/**
	 * Allowed countries.
	 *
	 * @var array
	 */
	private static $allowed_countries = array();

	/**
	 * Allowed IPs.
	 *
	 * @var array
	 */
	private static $allowed_ips = array();

	/**
	 * All countries.
	 *
	 * @var boolean
	 */
	private static $all_countries = false;

	/**
	 * All IPs.
	 *
	 * @var boolean
	 */
	private static $all_ips = false;

	/**
	 * Maybe redirect the URLs.
	 *
	 * @var array
	 */
	private static $custom_redirects = array(
		'status' => 0,
		'login' => 0,
		'register' => 0,
		'urls' => array(),
	);

	/**
	 * If he current user restriction was assessed.
	 *
	 * @var boolean
	 */
	private static $curent_user_assessed = false;

	/**
	 * If he current user has restriction.
	 *
	 * @var boolean
	 */
	private static $curent_user_restriction = false;

	/**
	 * Class instance.
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * The plugin URL.
	 *
	 * @var string
	 */
	private static $plugin_url = '';

	/**
	 * Get active object instance
	 *
	 * @return object
	 */
	public static function get_instance() {

		if (!self::$instance) {
			self::$instance = new SISANU_Restrict_Country_IP_Login();
		}
		return self::$instance;
	}

	/**
	 * Class constructor. Includes constants, includes and init method.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Run action and filter hooks.
	 *
	 * @return void
	 */
	private function init() {
		self::load_settings();

		$ob_class = get_called_class();
		add_action('plugins_loaded', array($ob_class, 'load_textdomain'));

		if (false === self::$all_countries || false === self::$all_ips) {
			add_filter('authenticate', array($ob_class, 'sisanu_restrict_country'), 30, 3);

			// Maybe hookup redirects.
			if (!empty(self::$custom_redirects['status'])) {
				if (!empty(self::$custom_redirects['register'])) {
					add_action('wp_loaded', array($ob_class, 'maybe_restrict_register_url'));
				}
				if (!empty(self::$custom_redirects['login'])) {
					add_filter('wp_loaded', array($ob_class, 'maybe_restrict_login_url'));
				}
				if (!empty(self::$custom_redirects['urls'])) {
					add_filter('template_redirect', array($ob_class, 'maybe_restrict_custom_url'));
				}
			}
		}

		if (is_admin()) {
			add_action('init', array($ob_class, 'maybe_upgrade_version'), 1);
			add_action('init', array($ob_class, 'maybe_save_settings'), 1);
			self::$plugin_url = admin_url('options-general.php?page=login-ip-country-restriction-settings');
			add_action('admin_menu', array($ob_class, 'admin_menu'));

			// Enqueue the plugin assets for back-end.
			add_action('admin_enqueue_scripts', array($ob_class, 'load_assets'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($ob_class, 'plugin_action_links'));
		}

		add_action('admin_notices', array($ob_class, 'plugin_admin_notices'));
		add_action('wp_ajax_sislrc-plugin-deactivate-notice', array($ob_class, 'plugin_admin_notices_cleanup'));
		add_action('plugins_loaded', array($ob_class, 'plugin_ver_check'));
	}

	/**
	 * Redirect the login URL.
	 *
	 * @return void
	 */
	public static function maybe_restrict_login_url() {
		if ((substr_count($_SERVER['REQUEST_URI'], 'wp-login')
			&& !substr_count($_SERVER['REQUEST_URI'], 'action='))
			|| get_permalink() === wp_login_url()) {
			$restrict = self::user_has_restriction();
			if ($restrict) {
				header('location:' . home_url());
				die();
			}
		}
	}

	/**
	 * Redirect the register URL.
	 *
	 * @return void
	 */
	public static function maybe_restrict_register_url() {
		if ((substr_count($_SERVER['REQUEST_URI'], 'wp-login')
			&& substr_count($_SERVER['REQUEST_URI'], 'action=register'))
			|| get_permalink() === wp_registration_url()) {
			$restrict = self::user_has_restriction();
			if ($restrict) {
				header('location:' . home_url());
				die();
			}
		}
	}

	/**
	 * Redirect the custom URL.
	 *
	 * @return void
	 */
	public static function maybe_restrict_custom_url() {
		if (in_array(get_permalink(), self::$custom_redirects['urls'])) {
			$restrict = self::user_has_restriction();
			if ($restrict) {
				header('location:' . home_url());
				die();
			}
		}
	}

	/**
	 * Load th plugin settings.
	 *
	 * @return void
	 */
	public static function load_settings() {
		self::$allowed_countries = maybe_unserialize(get_option(SISANU_RCIL_DB_OPTION . '_allow_countries', array('*')));
		self::$allowed_ips = maybe_unserialize(get_option(SISANU_RCIL_DB_OPTION . '_allow_ips', array('*')));
		self::$all_countries = (in_array('*', self::$allowed_countries, true)) ? true : false;
		self::$all_ips = (in_array('*', self::$allowed_ips, true)) ? true : false;

		$custom_redirects = maybe_unserialize(get_option(SISANU_RCIL_DB_OPTION . '_custom_redirects', array()));
		self::$custom_redirects = wp_parse_args($custom_redirects, self::$custom_redirects);
	}

	/**
	 * The actions to be executed when the plugin is activated.
	 *
	 * @return void
	 */
	public static function maybe_upgrade_version() {
		$db_version = get_option(SISANU_RCIL_DB_OPTION . '_db_ver', 0);
		if (empty($db_version) || (float) SISANU_RCIL_CURRENT_DB_VERSION !== (float) $db_version) {
			// Preserve the previous settings if possible.
			$get_prev_ip = get_option(SISANU_RCIL_DB_OPTION . '_allow_ips', array('*'));
			$get_prev_co = get_option(SISANU_RCIL_DB_OPTION . '_allow_countries', array('*'));

			update_option(SISANU_RCIL_DB_OPTION . '_allow_countries', $get_prev_co);
			update_option(SISANU_RCIL_DB_OPTION . '_allow_ips', $get_prev_ip);
			update_option(SISANU_RCIL_DB_OPTION . '_db_ver', SISANU_RCIL_CURRENT_DB_VERSION);
		}
	}

	/**
	 * The actions to be executed when the plugin is activated.
	 *
	 * @return void
	 */
	public static function activate_plugin() {
		self::maybe_upgrade_version();
		set_transient(self::PLUGIN_TRANSIENT, true);
	}

	/**
	 * The actions to be executed when the plugin is deactivated.
	 *
	 * @return void
	 */
	public static function deactivate_plugin() {
		delete_option(SISANU_RCIL_DB_OPTION . '_db_ver');
		delete_option(SISANU_RCIL_DB_OPTION . '_allow_countries');
		delete_option(SISANU_RCIL_DB_OPTION . '_allow_ips');
		delete_option(SISANU_RCIL_DB_OPTION . '_custom_redirects');
		self::plugin_admin_notices_cleanup(false);
	}

	/**
	 * Load text domain for internalization
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain('sislrc', false, basename(dirname(__FILE__)) . '/langs/');
	}

	/**
	 * Load the plugin assets.
	 *
	 * @return void
	 */
	public static function load_assets() {
		if (!substr_count($_SERVER['REQUEST_URI'], 'page=login-ip-country-restriction-settings')) {
			// Fail-fast, we only add assets to this page.
			return;
		}

		// Enqueue the custom plugin styles.
		wp_enqueue_style(
			'sislrc',
			plugins_url('/assets/custom.css', __FILE__),
			array(),
			SISANU_RCIL_CURRENT_DB_VERSION,
			false
		);

		wp_register_script(
			'sislrc',
			plugins_url('/assets/custom.js', __FILE__),
			array('jquery'),
			SISANU_RCIL_CURRENT_DB_VERSION
		);
		wp_localize_script(
			'sislrc',
			'sislrcSettings',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
			)
		);
		wp_enqueue_script('sislrc');
	}

	/**
	 * Add the new menu in settings section that allows to configure the restriction.
	 *
	 * @return void
	 */
	public static function admin_menu() {
		add_submenu_page(
			'options-general.php',
			'<div class="dashicons dashicons-admin-site"></div> ' . esc_html__('Login IP & Country Restriction Settings', 'sislrc'),
			'<div class="dashicons dashicons-admin-site"></div> ' . esc_html__('Login IP & Country Restriction Settings', 'sislrc'),
			'manage_options',
			'login-ip-country-restriction-settings',
			array(get_called_class(), 'login_ip_country_restriction_settings')
		);
	}

	/**
	 * Maybe execute the options update if the nonce is valid, then redirect.
	 *
	 * @return void
	 */
	public static function maybe_save_settings() {
		$nonce = filter_input(INPUT_POST, '_login_ip_country_restriction_settings_nonce', FILTER_DEFAULT);
		if (!empty($nonce)) {
			if (!wp_verify_nonce($nonce, '_login_ip_country_restriction_settings_save')) {
				wp_die(esc_html__('Action not allowed.', 'sislrc'), esc_html__('Security Breach', 'sislrc'));
			}

			// Reset the plugin cache.
			self::reset_plugin_transients();

			$sel = filter_input(INPUT_POST, '_login_ip_country_restriction_settings', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);

			$_allow_ip_all = sanitize_text_field($sel['allow_ip_all']);
			$_allow_ip_restrict = sanitize_text_field($sel['allow_ip_restrict']);
			$_allow_country_all = sanitize_text_field($sel['allow_country_all']);
			$_allow_country_restrict = (!empty($sel['allow_country_restrict'])) ? array_map('sanitize_text_field', $sel['allow_country_restrict']) : array();

			$allow_ip = array('*');
			if (!empty($_allow_ip_all) && 'all' === $_allow_ip_all) {
				$allow_ip = array('*');
			} else {
				if (empty($_allow_ip_restrict)) {
					$allow_ip = array(
						'127.0.0.1',
						'::1',
					);
				} else {
					$_allow = preg_replace('/\s/', '', $_allow_ip_restrict);
					$allow_ip = explode(',', $_allow);
					$allow_ip[] = '127.0.0.1';
					$allow_ip[] = '::1';
					$allow_ip = array_unique($allow_ip);
					asort($allow_ip);
				}
			}
			update_option(SISANU_RCIL_DB_OPTION . '_allow_ips', $allow_ip);

			$allow_country = array('*');
			if (!empty($_allow_country_all) && 'all' === $_allow_country_all) {
				$allow_country = array('*');
			} else {
				if (empty($_allow_country_restrict) || 'all' === $_allow_country_all) {
					$allow_country = array('*');
					$country_code = self::get_user_country_name();
					if (!empty($country_code) && '!NA' !== $country_code) {
						$allow_country = array($country_code);
					}
				} else {
					$allow_country = $_allow_country_restrict;
					$allow_country = array_unique($allow_country);
					asort($allow_country);
				}
			}
			update_option(SISANU_RCIL_DB_OPTION . '_allow_countries', $allow_country);

			// Process redirects settings.
			$_urls = array();
			if (!empty($sel['redirect_urls'])) {
				$_urls = preg_replace('/\s/', '', $sel['redirect_urls']);
				$_urls = explode(',', $_urls);
				$_urls = array_unique($_urls);
				asort($_urls);
			}
			$custom_redirects = self::$custom_redirects;
			$custom_redirects['status'] = (!empty($sel['use_redirect'])) ? 1 : 0;
			$custom_redirects['login'] = (!empty($sel['redirect_login'])) ? 1 : 0;
			$custom_redirects['register'] = (!empty($sel['redirect_register'])) ? 1 : 0;
			$custom_redirects['urls'] = $_urls;
			update_option(SISANU_RCIL_DB_OPTION . '_custom_redirects', $custom_redirects);

			// Refresh the plugin object properties.
			self::load_settings();

			// Add admin notice on flushed transients.
			self::add_admin_notice(esc_html__('The settings were updated.', 'sislrc'));
		}
	}

	/**
	 * Add admin notices.
	 *
	 * @param string $text  The text to be outputted as the admin notice.
	 * @param string $class The admin notice class (notice-success is-dismissible, notice-error).
	 * @return void
	 */
	public static function add_admin_notice($text, $class = 'notice-success is-dismissible') {
		add_action('admin_notices', function () use ($text, $class) {
			?>
			<div class="notice <?php echo esc_attr($class); ?>">
				<p><?php echo wp_kses_post($text); ?></p>
			</div>
			<?php
}, 100);
	}

	/**
	 * Show the current settings and allow you to change the settings.
	 *
	 * @return void
	 */
	public static function login_ip_country_restriction_settings() {
		// Verify user capabilities in order to deny the access if the user does not have the capabilities.
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Action not allowed.', 'sislrc'));
		}

		$all_countries = self::get_countries_list();

		$t2 = filter_input(INPUT_POST, 'submit-tab2', FILTER_DEFAULT);
		$t3 = filter_input(INPUT_POST, 'submit-tab3', FILTER_DEFAULT);
		$current_tab = 'ic-menu-item-1';
		if (!empty($t2)) {
			$current_tab = 'ic-menu-item-2';
		} elseif (!empty($t3)) {
			$current_tab = 'ic-menu-item-3';
		}
		?>

		<div class="wrap ic-devops-wrap2 ic-devops-sislrc-tabs-wrapper ic-devops-sislrc">
			<h1><div class="dashicons dashicons-admin-site ic-devops"></div> <?php esc_html_e('Login IP & Country Restriction Settings', 'sislrc');?></h1>

			<div class="card">
				<ul>
					<li>
						<?php
if (true === self::$all_countries && true === self::$all_ips) {
			esc_html_e('Based on the current options there is no login restriction.', 'sislrc');
		} elseif (false === self::$all_ips) {
			echo esc_html(sprintf(
				// Translators: %1$s - list of IPs.
				__('Based on the current options there is a login restriction, only these IPs are allowed for login: %1$s.', 'sislrc'),
				implode(', ', self::$allowed_ips)
			));
		} elseif (false === self::$all_countries) {
			echo esc_html(sprintf(
				// Translators: %1$s - list of country names.
				__('Based on the current options there is a login restriction, this is allowed only from these countries: %1$s.', 'sislrc'),
				implode(', ', self::$allowed_countries)
			));
		}
		?>
					</li>
					<?php $res = self::current_user_has_restriction($_SERVER['REMOTE_ADDR'], self::get_user_country_name());?>
					<?php if (true === $res): ?>
						<li class="notice notice-error">
							<span class="dashicons dashicons-warning"></span> <?php esc_html_e('The restriction will apply to your user as well! Please make sure you change the restiction to allow your own access.', 'sislrc');?>
						</li>
					<?php endif;?>
					<li>
						<?php
echo wp_kses_post(sprintf(
			// Translators: %1$s - IP, %2$s - country code.
			__('Your current IP is %1$s and the country code is %2$s.', 'sislrc'),
			'<b>' . $_SERVER['REMOTE_ADDR'] . '</b>',
			'<b>' . self::get_user_country_name() . '</b>'
		));
		?>
					</li>
				</ul>
			</div>
			<br>

			<ul class="ic-menu" data-active="<?php echo esc_attr($current_tab); ?>">
				<li id="ic-menu-item-1"><div class="dashicons dashicons-shield"></div> <?php esc_html_e('IP Restriction', 'sislrc');?></li>
				<li id="ic-menu-item-2"><div class="dashicons dashicons-shield-alt"></div> <?php esc_html_e('Country Restriction', 'sislrc');?></li>
				<li id="ic-menu-item-3"><?php esc_html_e('Redirects', 'sislrc');?></li>
			</ul>

			<form action="<?php echo esc_url(self::$plugin_url); ?>" method="POST">
				<?php wp_nonce_field('_login_ip_country_restriction_settings_save', '_login_ip_country_restriction_settings_nonce');?>
				<ul class="ic-menu-items">
					<li id="ic-menu-item-1-content">
						<table class="fixed" width="100%">
							<tr class="v-top">
								<td width="170">
									<?php submit_button('', 'primary wide', '', false);?>
								</td>
								<td width="40%">
									<?php
$all = (in_array('*', self::$allowed_ips, true) && count(self::$allowed_ips) === 1) ? true : false;
		?>
									<label>
										<input type="radio" name="_login_ip_country_restriction_settings[allow_ip_all]"
											id="_login_ip_country_restriction_settings_allow_ip_all"
											value="all" <?php checked(true, $all);?>
											onclick="jQuery('#restrict_ip_list').hide();"/>
										<?php esc_html_e('No IP restriction', 'sislrc');?>
									</label>,
									<label>
										<input type="radio" name="_login_ip_country_restriction_settings[allow_ip_all]"
											id="_login_ip_country_restriction_settings_allow_ip_restrict"
											value="restrict" <?php checked(false, $all);?>
											onclick="jQuery('#restrict_ip_list').show()"/>
											<?php esc_html_e('Allow only specific IPs', 'sislrc');?>
									</label>
								</td>
								<td>
									<div class="rcil_elem <?php echo esc_attr((true === $all) ? 'off' : 'on'); ?>" id="restrict_ip_list">
										<textarea
											name="_login_ip_country_restriction_settings[allow_ip_restrict]"
											placeholder="111.111.111,222.222.222,333.333.333"
											class="wide" rows="2"><?php echo esc_html(implode(', ', self::$allowed_ips)); ?></textarea>
										<div class="small-font">
											<?php esc_html_e('* means any IP, you must remove it from the list if you want to apply a restriction.', 'sislrc');?>
											<?php esc_html_e('Separate the IPs with comma if there are more.', 'sislrc');?>
										</div>
									</div>
								</td>
							</tr>
						</table>
					</li>
					<li id="ic-menu-item-2-content">
						<table class="fixed" width="100%">
							<tr class="v-top">
								<td width="170">
									<?php submit_button('', 'primary wide', 'submit-tab2', false);?>
								</td>
								<td>
									<label>
										<input type="radio" name="_login_ip_country_restriction_settings[allow_country_all]"
											id="_login_ip_country_restriction_settings_allow_country_all"
											value="all" <?php checked(1, (in_array('*', self::$allowed_countries, true)), true);?>
											onclick="jQuery('#restrict_country_list').hide();"/>
											<?php esc_html_e('No country restriction', 'sislrc');?>
									</label>,
									<label>
										<input type="radio" name="_login_ip_country_restriction_settings[allow_country_all]"
											id="_login_ip_country_restriction_settings_allow_country_restrict"
											value="restrict" <?php checked(1, (!in_array('*', self::$allowed_countries, true)), true);?>
											onclick="jQuery('#restrict_country_list').show()"/>
											<?php esc_html_e('Allow only the selected countries', 'sislrc');?>
									</label>
								</td>
							</tr>
						</table>

						<div class="rcil_elem <?php echo ((in_array('*', self::$allowed_countries, true)) ? 'off' : 'on'); ?>" id="restrict_country_list">
							<br>
							<div>
								<h3 class="h4"><?php esc_html_e('Allowed countries', 'sislrc');?></h3>
								<?php if (!empty(self::$allowed_countries) && !in_array('*', self::$allowed_countries, true)): ?>
									<p><?php esc_html_e('This is the list of countries you selected. You can uncheck any and save again your options.', 'sislrc');?></p>
									<ul class="cols4">
										<?php foreach (self::$allowed_countries as $c): ?>
											<li>
												<label>
													<input type="checkbox"
														name="_login_ip_country_restriction_settings[allow_country_restrict][]"
														id="_login_ip_country_restriction_settings_allow_country_all"
														value="<?php echo esc_attr($c); ?>"
														checked="checked" /> <?php echo esc_html($all_countries[$c]); ?>
												</label>
											</li>
											<?php unset($all_countries[$c]);?>
										<?php endforeach;?>

										<li><?php submit_button('', 'primary', 'submit-tab2', false);?></li>
									</ul>
								<?php else: ?>
									(<?php esc_html_e('you did not select any country yet', 'sislrc');?>)
								<?php endif;?>
							</div>

							<br>
							<div>
								<h3 class="h4"><?php esc_html_e('Countries list', 'sislrc');?></h3>
								<p>
									<?php esc_html_e('Select only the countries you wish to allow access from.', 'sislrc');?>
								</p>
								<div class="rcil-letters-list">
									<?php foreach (range('A', 'Z') as $letter) {?>
										<a href="#letter<?php echo esc_attr($letter); ?>" class="button"><?php echo esc_html($letter); ?></a>
									<?php }?>
								</div>

								<?php $letter = '';?>
								<?php foreach ($all_countries as $c => $n): ?>
									<?php if ($n{0} !== $letter): ?>
										<?php if ('' !== $letter): ?>
											</ul></div>
										<?php endif;?>
										<?php $letter = $n{0};?>
										<p id="letter<?php echo esc_attr($letter); ?>" class="rcil-letters-title-wrap"><b class="h4"><?php echo esc_html($letter); ?></b></p>
										<div>
											<ul class="cols4">
												<li class="list-title">
													<?php submit_button('', 'wide primary', 'submit-tab2', false);?>
												</li>
									<?php endif;?>
									<li><label>
										<input type="checkbox"
											name="_login_ip_country_restriction_settings[allow_country_restrict][]"
											id="_login_ip_country_restriction_settings_allow_country_all"
											value="<?php echo esc_attr($c); ?>"
											<?php checked(1, (in_array($c, self::$allowed_countries, true)), true);?> />
											<?php echo esc_html($n); ?>
									</label></li>
								<?php endforeach;?>
									</ul>
								</div>

								<div class="clear"></div>
							</div>
							<div class="clear"></div>
						</div>
					</li>

					<li id="ic-menu-item-3-content">
						<table class="fixed" width="100%">
							<tr class="v-top">
								<td width="170">
									<?php submit_button('', 'primary wide', 'submit-tab3', false);?>
								</td>
								<td>
									<label>
										<input type="radio" name="_login_ip_country_restriction_settings[use_redirect]"
											id="_login_ip_country_restriction_settings_use_redirect0"
											value="0" <?php checked(0, self::$custom_redirects['status']);?>
											onclick="jQuery('#use_redirects_list').hide();"/>
										<?php esc_html_e('No redirects', 'sislrc');?>
									</label>,
									<label>
										<input type="radio" name="_login_ip_country_restriction_settings[use_redirect]"
											id="_login_ip_country_restriction_settings_use_redirect1"
											value="1" <?php checked(1, self::$custom_redirects['status']);?>
											onclick="jQuery('#use_redirects_list').show();"/>
										<?php esc_html_e('Yes, use redirects to frontpage when the URLs are accessed by someone that has a restriction.', 'sislrc');?>
									</label>
								</td>
							</tr>
						</table>

						<div class="rcil_elem <?php echo (1 == self::$custom_redirects['status'] ? 'on' : 'off'); ?>" id="use_redirects_list">
							<div>
								<p><?php esc_html_e('Please note that the restriction to the pages configured below is either by country, or not in the specified IPs list.', 'sislrc');?></p>
								<h3 class="h4"><?php esc_html_e('Login & Registration native pages', 'sislrc');?></h3>
								<br>
								<div class="indent">
									<label>
										<input type="checkbox" name="_login_ip_country_restriction_settings[redirect_login]"
											id="_login_ip_country_restriction_settings_redirect_login"
											value="1" <?php checked(1, self::$custom_redirects['login']);?>/>
										<?php
echo wp_kses_post(sprintf(
			// Translators: %1$s - url, %2$s - new url.
			__('Redirect login from %1$s to %2$s.', 'sislrc'),
			'<em>' . wp_login_url() . '</em>',
			'<em>' . home_url() . '</em>'
		));
		?>
									</label>
									<br>
									<label>
										<input type="checkbox" name="_login_ip_country_restriction_settings[redirect_register]"
											id="_login_ip_country_restriction_settings_redirect_register"
											value="1" <?php checked(1, self::$custom_redirects['register']);?>/>
										<?php
echo wp_kses_post(sprintf(
			// Translators: %1$s - url, %2$s - new url.
			__('Redirect registration from %1$s to %2$s.', 'sislrc'),
			'<em>' . wp_registration_url() . '</em>',
			'<em>' . home_url() . '</em>'
		));
		?>
									</label>
								</div>

								<br>

								<h3 class="h4"><?php esc_html_e('The following specified URLs', 'sislrc');?></h3>
								<br>
								<div class="indent">
									<textarea name="_login_ip_country_restriction_settings[redirect_urls]" class="wide" rows="2"><?php echo esc_html(implode(', ', self::$custom_redirects['urls'])); ?></textarea>
									<div class="small-font"><?php esc_html_e('(separate the URLs with comma)', 'sislrc');?></div>
								</div>
							</div>
						</div>
					</li>
				</ul>
			</form>
		</div>

		<br>
		<p>
			<em><span class="dashicons dashicons-format-quote"></span> <?php esc_html_e('The best thing about giving of ourselves is that what we get is always better than what we give.', 'sirsc');?></em>
			<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="hosted_button_id" value="JJA37EHZXWUTJ"><input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"><img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"></form>
		</p>
		<?php
}

	/**
	 * Return the countries list.
	 *
	 * @return array
	 */
	public static function get_countries_list() {
		$all_countries = array(
			'AF' => 'Afghanistan',
			'AX' => 'Aland Islands',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua And Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia And Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei Darussalam',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CA' => 'Canada',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos (Keeling) Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CG' => 'Congo',
			'CD' => 'Congo, Democratic Republic',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => 'Cote D\'Ivoire',
			'HR' => 'Croatia',
			'CU' => 'Cuba',
			'CY' => 'Cyprus',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'SV' => 'El Salvador',
			'GQ' => 'Equatorial Guinea',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands (Malvinas)',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GR' => 'Greece',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard Island & Mcdonald Islands',
			'VA' => 'Holy See (Vatican City State)',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran, Islamic Republic Of',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle Of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KR' => 'Korea',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Lao People\'s Democratic Republic',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libyan Arab Jamahiriya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourg',
			'MO' => 'Macao',
			'MK' => 'Macedonia',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'MV' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia, Federated States Of',
			'MD' => 'Moldova',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'AN' => 'Netherlands Antilles',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestinian Territory, Occupied',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Philippines',
			'PN' => 'Pitcairn',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion',
			'RO' => 'Romania',
			'RU' => 'Russian Federation',
			'RW' => 'Rwanda',
			'BL' => 'Saint Barthelemy',
			'SH' => 'Saint Helena',
			'KN' => 'Saint Kitts And Nevis',
			'LC' => 'Saint Lucia',
			'MF' => 'Saint Martin',
			'PM' => 'Saint Pierre And Miquelon',
			'VC' => 'Saint Vincent And Grenadines',
			'WS' => 'Samoa',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome And Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SK' => 'Slovakia',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia And Sandwich Isl.',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard And Jan Mayen',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syrian Arab Republic',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TH' => 'Thailand',
			'TL' => 'Timor-Leste',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TT' => 'Trinidad And Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks And Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'GB' => 'United Kingdom',
			'US' => 'United States',
			'UM' => 'United States Outlying Islands',
			'UY' => 'Uruguay',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela',
			'VN' => 'Viet Nam',
			'VG' => 'Virgin Islands, British',
			'VI' => 'Virgin Islands, U.S.',
			'WF' => 'Wallis And Futuna',
			'EH' => 'Western Sahara',
			'YE' => 'Yemen',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe',
		);

		return $all_countries;
	}

	/**
	 * Maybe fetch url content with cUrl.
	 *
	 * @param  string $url URL to be crawled.
	 * @return string
	 */
	public static function maybe_fetch_url($url) {
		$result = '';
		if (function_exists('curl_setopt')) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_AUTOREFERER, false);
			curl_setopt($ch, CURLOPT_FAILONERROR, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
			curl_setopt($ch, CURLOPT_HEADER, false);
			$result = @curl_exec($ch);
			$code = @curl_getinfo($ch);
			curl_close($ch);
			if (!empty($code['http_code']) && '404' == $code['http_code']) {
				$result = '';
			}
		}
		return $result;
	}

	/**
	 * Maybe a country code by cUrl.
	 *
	 * @param  string $url URL to be crawled.
	 * @return string
	 */
	public static function country_code_by_curl($url) {
		$code = '';
		$body = self::maybe_fetch_url($url);
		if (!empty($body)) {
			$user = @json_decode($body);
			$code = (!empty($user->geoplugin_countryCode)) ? $user->geoplugin_countryCode : ''; // PHPCS:ignore WordPress.NamingConventions.ValidVariableName
		}
		return $code;
	}

	/**
	 * Maybe a country code by JSON fetch.
	 *
	 * @param  string $url URL to be crawled.
	 * @return string
	 */
	public static function country_code_by_json($url) {
		$code = '';
		$body = wp_remote_get($url, array('timeout' => 120));
		if (!is_wp_error($body) && !empty($body['body'])) {
			$body = @json_decode($body['body']);
			$code = (!empty($body->geoplugin_countryCode)) ? $body->geoplugin_countryCode : ''; // PHPCS:ignore WordPress.NamingConventions.ValidVariableName
		}
		return $code;
	}

	/**
	 * Maybe a country code by php fetch.
	 *
	 * @param  string $url URL to be crawled.
	 * @return string
	 */
	public static function country_code_by_php($url) {
		$code = '';
		$body = maybe_unserialize(@file_get_contents($url));
		if (!empty($body['geoplugin_countryCode'])) {
			$code = (string) $body['geoplugin_countryCode'];
		}
		return $code;
	}

	/**
	 * Retrieves the current user country code based on the user IP.
	 *
	 * @return string
	 */
	public static function get_user_country_name() {
		$country_code = '!NA';
		$user_ip = $_SERVER['REMOTE_ADDR'];
		$trans_id = 'geo-country-code-' . md5($user_ip);
		$country_code = get_transient($trans_id);
		if (false === $country_code) {
			if (function_exists('geoip_record_by_name')) {
				// If GeoIP library is available, then let's use this.
				$user_details = geoip_record_by_name($user_ip);
				$country_code = (!empty($user_details['country_code'])) ? $user_details['country_code'] : $country_code;
				set_transient($trans_id, $country_code, 1 * HOUR_IN_SECONDS);
				return $country_code;
			} else {
				// First attempt by cUrl.
				$country_code = self::country_code_by_curl('http://www.geoplugin.net/json.gp?ip=' . $user_ip);
				if (!empty($country_code) && '!NA' !== $country_code) {
					// Fail-fast, we found it.
					set_transient($trans_id, $country_code, 1 * HOUR_IN_SECONDS);
					return $country_code;
				}

				// The GeoIP library is not available, so we are trying to use the public GeoPlugin.
				$country_code = self::country_code_by_json('http://www.geoplugin.net/json.gp?ip=' . $user_ip);
				if (!empty($country_code) && '!NA' !== $country_code) {
					// Fail-fast, we found it.
					set_transient($trans_id, $country_code, 1 * HOUR_IN_SECONDS);
					return $country_code;
				}

				$country_code = self::country_code_by_php('http://www.geoplugin.net/php.gp?ip=' . $user_ip);
				if (!empty($country_code) && '!NA' !== $country_code) {
					// Fail-fast, we found it.
					set_transient($trans_id, $country_code, 1 * HOUR_IN_SECONDS);
					return $country_code;
				}
			}
			$country_code = '!NA';
			set_transient($trans_id, $country_code, 1 * HOUR_IN_SECONDS);
		}

		return $country_code;
	}

	/**
	 * Assess if the current user has restrictions.
	 *
	 * @return boolean
	 */
	public static function user_has_restriction() {
		if (false === self::$curent_user_assessed) {
			// Proceed with the computation.
			$forbid = 0;
			if (false === self::$all_countries) {
				$country_code = self::get_user_country_name();
				if ('!NA' !== $country_code && !in_array($country_code, self::$allowed_countries, true)) {
					++$forbid;
				}
			}
			if (false === self::$all_ips && !in_array($_SERVER['REMOTE_ADDR'], self::$allowed_ips, true)) {
				++$forbid;
			}
			self::$curent_user_restriction = (!empty($forbid)) ? true : false;
			self::$curent_user_assessed = true;
		}

		// If we got this far, the user restriction was assessed.
		return self::$curent_user_restriction;
	}

	/**
	 * Assess if the sepcified user has restrictions.
	 *
	 * @param  string $ip           IP address.
	 * @param  string $country_code Country code.
	 * @return boolean
	 */
	public static function current_user_has_restriction($ip, $country_code) {
		$forbid = 0;
		if (false === self::$all_countries) {
			if ('!NA' !== $country_code && !in_array($country_code, self::$allowed_countries, true)) {
				++$forbid;
			}
		}
		if (false === self::$all_ips && !in_array($ip, self::$allowed_ips, true)) {
			++$forbid;
		}
		return (!empty($forbid)) ? true : false;
	}

	/**
	 * Returns the current user if this is allowed (hence defaults to WordPress functionality)
	 * or forbid access to authentication.
	 *
	 * @param  object $user Potential WP_User instance.
	 * @param  string $username Username.
	 * @param  string $password Passeword.
	 * @return object
	 */
	public static function sisanu_restrict_country($user, $username, $password) {
		$restrict = self::user_has_restriction();
		if (!empty($restrict)) {
			// The use country based on the user IP is not in the list of allowed countries and also the user IP is not in the allowed IPs list.
			wp_logout();
			wp_die(esc_html__('Forbidden!', 'sislrc'));
		} else {
			return $user;
		}
	}

	/**
	 * Add the plugin settings and plugin URL links.
	 *
	 * @param  array $links The plugin links.
	 * @return array
	 */
	public static function plugin_action_links($links) {
		$all = array();
		$all[] = '<a href="' . esc_url(self::$plugin_url) . '">' . esc_html__('Settings', 'sislrc') . '</a>';
		$all[] = '<a href="https://iuliacazan.ro/login-ip-country-restriction">' . esc_html__('Plugin URL', 'sislrc') . '</a>';
		$all = array_merge($all, $links);
		return $all;
	}

	/**
	 * The actions to be executed when the plugin is updated.
	 *
	 * @return void
	 */
	public static function plugin_ver_check() {
		$opt = str_replace('-', '_', self::PLUGIN_TRANSIENT) . '_db_ver';
		$dbv = get_option($opt, 0);
		if (SISANU_RCIL_CURRENT_DB_VERSION !== (float) $dbv) {
			update_option($opt, SISANU_RCIL_CURRENT_DB_VERSION);
			self::activate_plugin();
		}
	}

	/**
	 * Execute notices cleanup.
	 *
	 * @param  boolean $ajax Is AJAX call.
	 * @return void
	 */
	public static function plugin_admin_notices_cleanup($ajax = true) {
		// Delete transient, only display this notice once.
		delete_transient(self::PLUGIN_TRANSIENT);

		if (true === $ajax) {
			// No need to continue.
			wp_die();
		}
	}

	/**
	 * Admin notices.
	 *
	 * @return void
	 */
	public static function plugin_admin_notices() {
		$maybe_trans = get_transient(self::PLUGIN_TRANSIENT);
		if (!empty($maybe_trans)) {
			?>
			<style>.notice.<?php echo esc_attr(self::PLUGIN_TRANSIENT); ?>{background:rgba(176, 227, 126,0.2);border-left-color:rgb(176, 227, 126)}.notice.<?php echo esc_attr(self::PLUGIN_TRANSIENT); ?> img{max-width: 100%}</style>
			<script>(function($) { $(document).ready(function() { var $notice = $('.notice.<?php echo esc_attr(self::PLUGIN_TRANSIENT); ?>'); var $button = $notice.find('.notice-dismiss'); $notice.unbind('click'); $button.unbind('click'); $notice.on('click', '.notice-dismiss', function(e) { $.get( $notice.data('dismissurl') ); }); }); })(jQuery);</script>

			<div class="updated notice is-dismissible <?php echo esc_attr(self::PLUGIN_TRANSIENT); ?>"
				data-dismissurl="<?php echo esc_url(admin_url('admin-ajax.php?action=sislrc-plugin-deactivate-notice')); ?>">
				<p>
					<?php
echo wp_kses_post(sprintf(
				// Translators: %1$s - image URL, %2$s - icon URL, %3$s - donate URL, %4$s - link style, %5$s - icon style, %6$s - rating, %7$s - settings link, %8$s - settings title.
				__('<a href="%3$s" target="_blank"%4$s><img src="%1$s"></a><a href="%7$s" title="%8$s"><img src="%2$s"%5$s></a> <h3>Thank you for activating the plugin Login IP & Country Restriction!</h3>If you find the plugin useful and would like to support my work, please consider making a <a href="%3$s" target="_blank">donation</a>. It would make me very happy if you would leave a %6$s rating.', 'sislrc') . ' ' . __('A huge thanks in advance!', 'sislrc'),
				esc_url(plugin_dir_url(__FILE__) . '/assets/images/buy-me-a-coffee.png'),
				esc_url(plugin_dir_url(__FILE__) . '/assets/images/icon-128x128.png'),
				'https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ&item_name=Support for development and maintenance (' . urlencode(self::PLUGIN_NAME) . ')',
				' style="float:right; margin:20px"',
				' style="float:left; margin-right:20px; margin-top:10px; width:86px"',
				'<a href="' . self::PLUGIN_SUPPORT_URL . 'reviews/?rate=5#new-post" class="rating" target="_blank" title="' . esc_attr('A huge thanks in advance!', 'sislrc') . '">★★★★★</a>',
				esc_url(self::$plugin_url),
				self::PLUGIN_NAME . ' - ' . esc_html__('Settings', 'sislrc')
			));
			?>
					<div class="clear"></div>
				</p>
			</div>
			<?php
}
	}
}

$srcil = SISANU_Restrict_Country_IP_Login::get_instance();

register_activation_hook(__FILE__, array($srcil, 'activate_plugin'));
register_deactivation_hook(__FILE__, array($srcil, 'deactivate_plugin'));
