=== ElasticPress WooCommerce ===
Contributors: tlovett1, joshuaabenazer, 10up
Tags: debug, woocommerce, elasticpress, elasticsearch, ecommerce, search filters
Requires at least: 3.7.1
Tested up to: 4.5
Stable tag: trunk

Power WooCommerce with Elasticsearch for extremely fast product and order queries.

== Description ==

A WordPress plugin that integrates [ElasticPress](https://github.com/10up/ElasticPress) with [WooCommerce](https://www.woothemes.com/woocommerce/). This plugin will run all critical WooCommerce queries through Elasticsearch instead of MySQL making it possible to render pages and process complex eCommerce filters very fast.

= Requirements: =

* [ElasticPress 1.8+](https://wordpress.org/plugins/elasticpress)
* PHP 5.2.4+

== Installation ==
1. Install [ElasticPress](https://wordpress.org/plugins/elasticpress).
2. Install [WooCommerce](https://wordpress.org/plugins/woocommerce/).
3. Install the plugin in WordPress.

== Changelog ==

= 1.1.1 =
* Fix undefined variable warning

= 1.1 =
* Properly support search in admin
* Properly index product/order post statuses

= 1.0 =
* Initial release