=== ElasticPress WooCommerce ===
Contributors: tlovett1, joshuaabenazer, 10up
Tags: debug, woocommerce, elasticpress, elasticsearch, ecommerce, search filters
Requires at least: 3.7.1
Tested up to: 4.6
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

= 1.2 =
* Search by product sku as well as product taxonomies on front and back end.

= 1.1.4 =
* Properly index coupons
* Don't send post_parent queries to Elasticsearch since EP can't support them

= 1.1.3 =
* Index order post meta
* Fix bug that was destroying admin order search queries
* Prevent WooCommerce from running expensive post meta MySQL query on admin order search
* Account for custom order statuses

= 1.1.2 =
* Fix post status indexes term search in admin
* Set ep_integrate in query and query_vars for backwards compat

= 1.1.1 =
* Fix undefined variable warning

= 1.1 =
* Properly support search in admin
* Properly index product/order post statuses

= 1.0 =
* Initial release