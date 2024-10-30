=== CrossPeak OMS for WooCommerce ===
Contributors: crosspeak
Tags: WooCommerce, OMS, Order Management System, Call Center Software, Order Management for WooCommerce, 3PL Fullfillment, Drop Ship Fulfillment
Requires at least: 4.0.0
Tested up to: 6.3.1
Stable tag: 2.0.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easy eCommerce Order Management

== Description ==

This plugin integrates your existing WooCommerce store with CrossPeak OMS.

CrossPeak is a SaaS product that extends the order management functionality of WooCommerce.
The granular permissions environment enables completely separate user access and visibility for distinct workflows — outsourced or in-house customer service, warehouse or 3PL fulfillment and multi-level business management.

CrossPeak is designed to deliver a great customer experience for companies with virtual and flex inventory products.

Unlike other OMS products, CrossPeak is not a warehouse management tool, it is focused on delivering a connected customer experience after the sale of any virtual or unique inventory product through a permission based workflow. Customer Services, Call Center, 3PL partners and management teams all enjoy different levels of access to the same order data to provide a customer first ecosystem.

= View Our Online Demo =

This is a demo of the actual software in it's most basic configuration.
Each deployment can be customized to meet your specific needs.

Visit: [https://demo.crosspeakoms.com/](https://demo.crosspeakoms.com/)

Login Information:  UN: demo, PW: demo

= Request An In-Depth Demo =

[Fill out the this form](https://www.crosspeakoms.com/contact-us/) and we will contact you to schedule your personal web demo of the CrossPeak Order Management System for WooCommerce.


== Installation ==

1. Upload `crosspeakoms` to the `/wp-content/plugins/` directory
1. Activate the "CrossPeak OMS for WooCommerce" plugin through the 'Plugins' menu in WordPress
1. Create a store in CrossPeak OMS for WooCommerce at Settings -> More -> Web Stores and API Access
1. In WordPress, navigate to WooCommerce -> Settings -> API -> Keys/Apps.
	1. Create a new API key for CrossPeak with read/write permissions. We recommend that you create a new WordPress user to assign this key to.
	1. Enter this key into CrossPeak OMS
1. In CrossPeak, generate a new API key. You can use "WooCommerce" as the application name or anything else you desire.
1. In WordPress, navigate to WooCommerce -> Settings -> Integration and enter this API key.
1. Configure any other settings on this page that you need.

== Frequently Asked Questions ==

= Do I need a subscription to use this plugin? =

Yes, this plugin requires a CrossPeak OMS subscription to use.

= What is the pricing? =

Our [Pricing Table](https://www.crosspeakoms.com/pricing/)

= Can I see a demo? =

This is a demo of the actual software in it's most basic configuration.
Each deployment can be customized to meet your specific needs.

Visit: [https://demo.crosspeakoms.com/](https://demo.crosspeakoms.com/)

Login Information:  UN: demo, PW: demo

== Screenshots ==

1. This adds the settings to the WooCommerce Integrations page and allows you to enter your API keys
2.
3.
4.
5.
6.
7.

== Changelog ==

= 2.0.1 =
* Fix potential error with the 2.0 release.

= 2.0.0 =
* Change out all order processing for new API.

= 1.4.2 =
* Improve cart handling using updated WooCommerce functions.
* Add Sentry reporting if sentry is enabled and there are issues
* Minify javascript files.

= 1.4.1 =
* Better safer javascript tracking.
* Allow CrossPeak to get cart information from WooCommerce for Shipping and Tax calculations.
* Better order flagging for what needs to be sent.

= 1.3.0 =
* WooCommerce 3.x support
* Revamped WooCommerce sync with orders sending through WP Cron or WP-Cli
* Order note sending and receiving.
* Subscription payment token saving.
* Quick test function for testing connection.

= 1.2.0 =
* Improve the Saturday shipping checks

= 1.1.0 =
* Add support for the authorize.net plugin
* Fix permissions for checking out as guest
* Added coupon support
* New filters for additional support

= 1.0.0 =
* Initial Release

== Upgrade Notice ==

= 1.1.0 =
Add new features

= 1.0.0 =
Initial Release
