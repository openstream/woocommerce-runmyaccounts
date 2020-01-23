=== WooCommerce RMA ===
Contributors: codestylist, openstream
Tags: Run my Accounts, WooCommerce, Billing
Requires at least: 4.7
Tested up to: 5.2.2
Requires PHP: 5.6
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plug-in connects WooCommerce with Run my Accounts.
The main features are:
- create invoices automatically in Run My Accounts after an order is placed in WooCommerce
- create customers automatically in Run My Accounts after a new user is created in WooCommerce

Please note, you need a paid account with Run my Accounts to use this plugin.

== Installation ==
Upload the files to your server and put it in your folder /plugins/.
Activate the plugin, enter the API key and check the settings page.

== Frequently Asked Questions ==

= Can I use this plugin without WooCommerce =

No. WooCommerce Run My Accounts requires WooCommerce to provide all features.

= Do I need a paid account with Run My Accounts =

Yes. Without the paid account you are not able to set up your company within Run My Accounts

= Is the plugin for free =

Yes. WooCommerce Run My Accounts plugin is for ever for free. Maybe, in the future, there can be a Pro version with more features. But, right now, all features are for free.

= I miss a feature. How can I get them? =

Please send a request to the developer Openstream Internet Solutions. We provide customization on an hourly rate. Otherwise you can fork the plugin on GitHub and develop the missing features by yourself. We only ask to send a pull request so, each user can benefit from your extension.

== Known issues ==
* Update customer in Run My Accounts if user data were updated in WooCommerce is nor working yet.
* Create an invoice, if client does not register on checkout page, does not work.

== Changelog ==
= 1.3.1 =
* Bug Fix - opened the wrong settings page when activating the plugin for the very first time

== Changelog ==
= 1.3.0 =
* Feature - added dedicated receivable account for each payment gateway
* Enhancement - use settings API for settings page
* Enhancement - added order note if invoice is created
* Enhancement - check if user_id is already linked to a Run My Accounts customer id before creating customer in Run My Accounts

== Changelog ==
= 1.2.0 =
* Feature - create customer in Run my Accounts when new WooCommerce user is created on checkout page
* Enhancement - added title to billing address if no WooCommerce Germanized is active
* Enhancement - added compatibility with WooCommerce Germanized and use the same user meta for billing title (e.g. billing_title)
* Enhancement - added warning if php extension curl is not loaded (is required for communication with Run My Accounts API)

= 1.1.1 =
* Tweak - new Run My Accounts API url

= 1.1 =
* Feature - added logging feature for RMA results
* Feature - added connection test on settings page
* Feature - added handling for different access data for live and test
* Enhancement - improved error handling
* Enhancement - improved security of files
* Localization - added German and German (formal) translation   

= 1.0 =
version 1.0 is the initial version