# ElasticPress WooCommerce

A WordPress plugin that integrates [ElasticPress](https://github.com/10up/ElasticPress) with [WooCommerce](https://www.woothemes.com/woocommerce/). This plugin will run all critical WooCommerce queries through Elasticsearch instead of MySQL making it possible to render pages and process complex eCommerce filters very fast.

## Requirements

* WooCommerce 2.5+
* ElasticPress 1.8+
* PHP 5.2.4+

## Reindexing

Some versions of ElasticPress WooCommerce require an ElasticPress reindex. When upgrading to or past the following versions, a [reindex](https://github.com/10up/elasticpress#single-site) is necessary:

* 1.1.3
* 1.1
* 1.0

## Usage

Install and activate [ElasticPress](https://github.com/10up/ElasticPress). Install and activate ElasticPress WooCommerce. Note that if you had previously been using ElasticPress, you should re-index after activating ElasticPress WooCommerce.

## Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/10up/elasticpress-woocommerce/issues?state=open).

## License

ElasticPress WooCommerce is free software; you can redistribute it and/or modify it under the terms of the [GNU General Public License](http://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.