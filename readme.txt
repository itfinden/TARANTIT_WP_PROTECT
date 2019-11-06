=== Login IP & Country Restriction ===
Contributors: Iulia Cazan
Tags: login restriction, security, authenticate, ip, country code, login filter, login, restrict, allow IP, allow country code, auth, security redirect
Requires at least: not tested
Tested up to: 5.2.2
Stable tag: 3.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Donate Link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=JJA37EHZXWUTJ


== Description ==
This plugin hooks in the authenticate filter. By default, the plugin is set to allow all access and you can configure the plugin to allow the login only from some specified IPs or the specified countries. PLEASE MAKE SURE THAT YOU CONFIGURE THE PLUGIN TO ALLOW YOUR OWN ACCESS. If you set a restriction by IP, then you have to add your own IP (if you are using the plugin in a local setup the IP is 127.0.0.1 or ::1, this is added in your list by default). If you set a restriction by country, then you have to select from the list of countries at least your country. Both types of restrictions work independent, so you can set only one type of restriction or both if you want. Also, you can configure the redirects to frontpage when the URLs are accessed by someone that has a restriction. The restriction is either by country, or not in the specified IPs list.


== Installation ==
* Upload `Login IP & Country Restriction` to the `/wp-content/plugins/` directory of your application
* Login as Admin
* Activate the plugin through the 'Plugins' menu in WordPress


== Hooks ==
authenticate


== Frequently Asked Questions ==
None


== Screenshots ==
1. How to configure a login IP rescription.
2. How to configure a login restriction by country.
3. How to configure redirects to homepage for visitors that match the restriction condition you setup.


== Changelog ==
= 3.6 =
* Tested up to 5.2.2
* Fix settings last tab select after save
* Sticky letters list, for better navigation
* Added more padding to the countries letters blocks (for better view on initial scroll)

= 3.5 =
* Tested up to 5.2.1
* Added new screenshots with the latest UI

= 3.4 =
* Tested up to 5.1.1
* UI update, compact options, responsive
* Add redirect options
* Add current user info and restriction info

= 3.3 =
* Tested up to 4.9.7
* Added translations
* Added geoplugin fallback

= 3.2 =
* Tested up to 4.8.3
* Added the readable info about the login restriction
* Added the countries letters for a faster navigation
* Added more save buttons

= 3.1 =
* Update the method to retrieve the data

= 3.0 =
* The allowed countries are separated visually from the rest of countries, compatibility update

= 2.0 =
* allow to configure the IP list
* allow to select the allowed countries


== Upgrade Notice ==
None


== License ==
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.


== Version history ==
3.6 - Tested up to 5.2.2, fix settings last tab select after save, sticky letters list, for better navigation, more padding to the countries letters blocks
3.5 - Tested up to 5.2.1, new screenshots with the latest UI
3.4 - Tested up to 5.1.1, UI update, add redirect options, add current user info and restriction info
3.3 - Tested up to 4.9.7, added translations, added geoplugin fallback
3.2 - Tested up to 4.8.3, added the readable info about the login restriction, added the countries letters for a faster navigation
3.1 - Update method
3.0 - The allowed countries are separated visually from the rest of countries + version test
2.0 - Configurable version
1.0 - Initial version
