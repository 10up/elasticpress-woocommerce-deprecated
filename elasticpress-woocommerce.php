<?php
/**
 * Plugin Name: ElasticPress WooCommerce
 * Description: Integrate ElasticPress and Elasticsearch with WooCommerce
 * Version:     1.0
 * Author:      Taylor Lovett, 10up
 * Author URI:  http://10up.com
 * License:     GPLv2 or later
 */

/**
 * Index Woocommerce post types
 *
 * @param   array $post_types Existing post types.
 * @since   1.0
 * @return  array
 */
function epwc_post_types( $post_types ) {
	return array_unique( array_merge( $post_types, array(
		'shop_order',
		'product_variation',
		'product',
	) ) );
}
add_filter( 'ep_indexable_post_types', 'epwc_post_types', 10, 1 );

/**
 * Index Woocommerce post statuses
 *
 * @param   array $post_statuses Existing post statuses.
 * @since   1.0
 * @return  array
 */
function epwc_post_statuses( $post_statuses ) {
	return array_unique( array_merge( $post_statuses, array(
		'publish',
		'wc-cancelled',
		'wc-completed',
		'wp-failed',
		'wc-on-hold',
		'wc-pending',
		'wc-processing',
		'wc-refunded',
	) ) );
}
add_filter( 'ep_indexable_post_status', 'epwc_post_statuses', 10, 1 );

/**
 * Index Woocommerce meta
 *
 * @param   array $meta Existing post meta.
 * @param   array $post Post arguments array.
 * @since   1.0
 * @return  array
 */
function epwc_whitelist_meta_keys( $meta, $post ) {
	return array_unique( array_merge( $meta, array(
		'_thumbnail_id',
		'_product_attributes',
		'_wpb_vc_js_status',
		'_swatch_type',
		'total_sales',
		'_downloadable',
		'_virtual',
		'_regular_price',
		'_sale_price',
		'_tax_status',
		'_tax_class',
		'_purchase_note',
		'_featured',
		'_weight',
		'_length',
		'_width',
		'_height',
		'_visibility',
		'_sku',
		'_sale_price_dates_from',
		'_sale_price_dates_to',
		'_price',
		'_sold_individually',
		'_manage_stock',
		'_backorders',
		'_stock	',
		'_upsell_ids',
		'_crosssell_ids',
		'_stock_status',
		'_product_version',
		'_product_tabs',
		'_override_tab_layout',
		'_suggested_price',
		'_min_price',
		'_variable_billing',
		'_product_image_gallery',
		'_bj_lazy_load_skip_post',
		'_min_variation_price',
		'_max_variation_price',
		'_min_price_variation_id',
		'_max_price_variation_id',
		'_min_variation_regular_price',
		'_max_variation_regular_price',
		'_min_regular_price_variation_id',
		'_max_regular_price_variation_id',
		'_min_variation_sale_price',
		'_max_variation_sale_price',
		'_min_sale_price_variation_id',
		'_max_sale_price_variation_id',
		'_default_attributes',
		'_swatch_type_options',
	) ) );
}
add_filter( 'ep_prepare_meta_allowed_protected_keys', 'epwc_whitelist_meta_keys', 10, 2 );

/**
 * Make sure all loop shop post ins are IDS. We have to pass post objects here since we override
 * the fields=>id query for the layered filter nav query
 *
 * @param   array $posts Post object array.
 * @since   1.0
 * @return  array
 */
function epwc_convert_post_object_to_id( $posts ) {
	$new_posts = array();

	foreach ( $posts as $post ) {
		if ( is_object( $post ) ) {
			$new_posts[] = $post->ID;
		} else {
			$new_posts[] = $post;
		}
	}

	return $new_posts;
}

add_filter( 'woocommerce_layered_nav_query_post_ids', 'epwc_convert_post_object_to_id', 10, 4 );
add_filter( 'woocommerce_unfiltered_product_ids', 'epwc_convert_post_object_to_id', 10, 4 );


/**
 * Index Woocommerce taxonomies
 *
 * @param   array $taxonomies Index taxonomies array.
 * @param   array $post Post properties array.
 * @since   1.0
 * @return  array
 */
function epwc_whitelist_taxonomies( $taxonomies, $post ) {
	$woo_taxonomies = array();
	$product_type = get_taxonomy( 'product_type' );

	$woo_taxonomies[] = $product_type;

	/**
	 * Note product_shipping_class, product_cat, and product_tag are already public. Make
	 * sure to index non-public attribute taxonomies.
	 */
	if ( $attribute_taxonomies = wc_get_attribute_taxonomies() ) {
		foreach ( $attribute_taxonomies as $tax ) {
			if ( $name = wc_attribute_taxonomy_name( $tax->attribute_name ) ) {
				if ( empty( $tax->attribute_public ) ) {
					$woo_taxonomies[] = get_taxonomy( $name );
				}
			}
		}
	}

	return array_merge( $taxonomies, $woo_taxonomies );
}
add_filter( 'ep_sync_taxonomies', 'epwc_whitelist_taxonomies', 10, 2 );

/**
 * Translate args to ElasticPress compat format
 */
function epwc_translate_args( $query ) {

	$product_name = $query->get( 'product', false );

	/**
	 * Do nothing for single product queries
	 */
	if ( ! empty( $product_name ) ) {
		return;
	}

	/**
	 * Force ElasticPress if we are querying WC taxonomy
	 */
	$tax_query = $query->get( 'tax_query', array() );

	$supported_taxonomies = array(
		'product_cat',
		'pa_brand',
		'product_tag',
		'pa_sort-by',
	);

	if ( ! empty( $tax_query ) ) {

		/**
		 * First check if already set taxonomies are supported WC taxes
		 */
		foreach ( $tax_query as $taxonomy_array ) {
			if ( isset( $taxonomy_array['taxonomy'] ) && in_array( $taxonomy_array['taxonomy'], $supported_taxonomies ) ) {
				$query->query_vars['ep_integrate'] = true;
			}
		}
	}

	/**
	 * Next check if any taxonomies are in the root of query vars (shorthand form)
	 */
	foreach ( $supported_taxonomies as $taxonomy ) {
		$term = $query->get( $taxonomy, false );

		if ( ! empty( $term ) ) {
			$query->query_vars['ep_integrate'] = true;

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => array( $term ),
			);
		}
	}

	if ( ! empty( $tax_query ) ) {
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Force ElasticPress if product post type query
	 */
	$supported_post_types = array(
		'product',
	);

	$post_type = $query->get( 'post_type', false );
	if ( ! empty( $post_type ) && in_array( $post_type, $supported_post_types ) ) {
		$query->query_vars['ep_integrate'] = true;
	}

	/**
	 * If we have an ElasticPress query, do the following:
	 */
	if ( ! empty( $query->query_vars['ep_integrate'] ) ) {
		/**
		 * Make sure filters are suppressed
		 */
		$query->query['suppress_filters'] = false;
		$query->set( 'suppress_filters', false );

		/**
		 * We can't support any special fields parameters
		 */
		$fields = $query->get( 'fields', false );
		if ( 'ids' === $fields || 'id=>parent' === $fields ) {
			$query->set( 'fields', 'default' );
		}

		/**
		 * Handle meta queries
		 */
		$meta_query = $query->get( 'meta_query', array() );
		$meta_key = $query->get( 'meta_key', false );
		$meta_value = $query->get( 'meta_value', false );

		if ( ! empty( $meta_key ) && ! empty( $meta_value ) ) {
			$meta_query[] = array(
				'key' => $meta_key,
				'value' => $meta_value,
			);

			$query->set( 'meta_query', $meta_query );
		}

		/**
		 * Set orderby from GET param
		 */
		if ( ! empty( $_GET['orderby'] ) ) {

			switch ( $_GET['orderby'] ) {
				case 'popularity':
					$query->set( 'orderby', 'meta.total_sales.long' );
					$query->set( 'order', 'desc' );
					break;
				case 'price':
					$query->set( 'orderby', 'meta._price.long' );
					$query->set( 'order', 'asc' );
					break;
				case 'price-desc':
					$query->set( 'orderby', 'meta._price.long' );
					$query->set( 'order', 'desc' );
					break;
			}
		}

		$orderby = $query->get( 'orderby' );

		if ( ! empty( $orderby ) && 'rand' === $orderby ) {
			$query->set( 'orderby', false ); // Just order by relevance.
		}
	}
}
add_action( 'pre_get_posts', 'epwc_translate_args', 11, 1 );

/**
 * Don't index legacy meta property. We want to to keep things light ot save space and memory.
 *
 * @param   array $post_args Post arguments to be indexed in ES.
 * @param   int   $post_id Post ID.
 * @since   1.0
 * @return  array
 */
function epwc_remove_legacy_meta( $post_args, $post_id ) {
	if ( ! empty( $post_args['post_meta'] ) ) {
		unset( $post_args['post_meta'] );
	}

	return $post_args;
}
add_filter( 'ep_post_sync_args_post_prepare_meta', 'epwc_remove_legacy_meta', 10, 2 );

/**
 * Enable search integration in admin
 *
 * @since  1.0
 */
add_filter( 'ep_admin_wp_query_integration', '__return_true' );

/**
 * Index all post statuses
 *
 * @since  1.0
 * @param array $statuses Current EP statuses.
 */
function epwc_index_all_statuses( $statuses ) {
	return array_values( get_post_stati() );
}
add_filter( 'ep_indexable_post_status', 'epwc_index_all_statuses' );



