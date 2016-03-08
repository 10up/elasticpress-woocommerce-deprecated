<?php
/**
 * Plugin Name: ElasticPress WooCommerce
 * Description: Integrate ElasticPress and Elasticsearch with WooCommerce
 * Version:     1.1.3
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
		'shop_order' => 'shop_order',
		'shop_order_refund' => 'shop_order_refund',
		'product_variation' => 'product_variation',
		'product' => 'product',
	) ) );
}
add_filter( 'ep_indexable_post_types', 'epwc_post_types', 10, 1 );

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
		'_customer_user',
		'_variable_billing',
		'_wc_average_rating',
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
		'_order_key',
		'_billing_company',
		'_billing_address_1',
		'_billing_address_2',
		'_billing_city',
		'_billing_postcode',
		'_billing_country',
		'_billing_state',
		'_billing_email',
		'_billing_phone',
		'_shipping_address_1',
		'_shipping_address_2',
		'_shipping_city',
		'_shipping_postcode',
		'_shipping_country',
		'_shipping_state',
		'_billing_last_name',
		'_billing_first_name',
		'_shipping_first_name',
		'_shipping_last_name',
	) ) );
}
add_filter( 'ep_prepare_meta_allowed_protected_keys', 'epwc_whitelist_meta_keys', 10, 2 );

/**
 * Prevent order fields search meta query
 *
 * @since  1.1.3
 */
add_filter( 'woocommerce_shop_order_search_fields', '__return_false' );

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

	// Lets make sure this doesn't interfere with the CLI
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	$product_name = $query->get( 'product', false );

	/**
	 * Do nothing for single product queries
	 */
	if ( ! empty( $product_name ) ) {
		return;
	}

	// Flag to check and make sure we are in a WooCommerce specific query
	$epwc_integrate = false;

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
				$epwc_integrate = true;
			}
		}
	}

	/**
	 * Next check if any taxonomies are in the root of query vars (shorthand form)
	 */
	foreach ( $supported_taxonomies as $taxonomy ) {
		$term = $query->get( $taxonomy, false );

		if ( ! empty( $term ) ) {
			$epwc_integrate = true;

			$terms = array( $term );

			// to add child terms to the tax query
			if ( is_taxonomy_hierarchical( $taxonomy ) ) {
				$term_object = get_term_by( 'slug', $term, $taxonomy );
				$children    = get_term_children( $term_object->term_id, $taxonomy );
				if ( $children ) {
					foreach ( $children as $child ) {
						$child_object = get_term( $child, $taxonomy );
						$terms[]      = $child_object->slug;
					}
				}

			}

			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
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
		'shop_order',
		'shop_order_refund',
		'product_variation'
	);

	$post_type = $query->get( 'post_type', false );

	// For orders it queries an array of shop_order and shop_order_refund post types, hence an array_diff
	if ( ! empty( $post_type ) && ( in_array( $post_type, $supported_post_types ) || ( is_array( $post_type ) && ! array_diff( $post_type, $supported_post_types ) ) ) ) {
		$epwc_integrate = true;
	}

	/**
	 * If we have a WooCommerce specific query, lets hook it to ElasticPress and make the query ElasticSearch friendly
	 */
	if ( $epwc_integrate || $query->is_search() ) {

		// Handles the WC Top Rated Widget
		if ( has_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) ) ) {
			remove_filter( 'posts_clauses', array( WC()->query, 'order_by_rating_post_clauses' ) );
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'meta_key', '_wc_average_rating' );
		}

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
		 * Make sure filters are suppressed
		 */
		$query->query['suppress_filters'] = false;
		$query->set( 'suppress_filters', false );

		/**
		 * This stuff will mess up search queries
		 */
		if ( ! $query->is_search() ) {
			$query->query_vars['ep_integrate'] = true;
			$query->query['ep_integrate'] = true;

			// Assuming $post_type to be product if empty
			if ( empty( $post_type ) || 'product' === $post_type ) {

				/**
				 * Set orderby from GET param
				 * Also make sure the orderby param affects only the main query
				 */
				if ( ! empty( $_GET['orderby'] ) && $query->is_main_query() ) {

					switch ( $_GET['orderby'] ) {
						case 'popularity':
							$query->set( 'orderby', epwc_get_orderby_meta_mapping( 'total_sales' ) );
							$query->set( 'order', 'desc' );
							break;
						case 'price':
						case 'price-desc':
							$query->set( 'orderby', epwc_get_orderby_meta_mapping( '_price' ) );
							break;
						case 'rating' :
							$query->set( 'orderby', epwc_get_orderby_meta_mapping( '_wc_average_rating' ) );
							$query->set( 'order', 'desc' );
							break;
						case 'date':
							$query->set( 'orderby', epwc_get_orderby_meta_mapping( 'date' ) );
							break;
						default:
							$query->set( 'orderby', epwc_get_orderby_meta_mapping( 'menu_order' ) ); // Order by menu and title.
					}
				} else {
					$orderby = $query->get( 'orderby', 'date' ); // Default to date
					if ( in_array( $orderby, array( 'meta_value_num', 'meta_value' ) ) ) {
						$orderby = $query->get( 'meta_key', 'date' ); // Default to date
					}
					$query->set( 'orderby', epwc_get_orderby_meta_mapping( $orderby ) );
				}
			} // Conditional check for orders
			elseif ( in_array( $post_type, array( 'shop_order', 'shop_order_refund' ) ) || $post_type === array( 'shop_order', 'shop_order_refund' ) ) {
				$query->set( 'order', 'desc' );
			} elseif ( 'product_variation' === $post_type ) {
				$query->set( 'orderby', 'menu_order' );
				$query->set( 'order', 'asc' );
			}

			$orderby = $query->get( 'orderby' );

			if ( ! empty( $orderby ) && 'rand' === $orderby ) {
				$query->set( 'orderby', false ); // Just order by relevance.
			}
		} else {
			// Search query
			if ( 'shop_order' === $post_type ) {
				$search_fields = $query->get( 'search_fields', array( 'post_title', 'post_content', 'post_excerpt' ) );

				$search_fields['meta'] = array_map( 'wc_clean', apply_filters( 'epwc_shop_order_search_fields', array(
					'_order_key',
					'_billing_company',
					'_billing_address_1',
					'_billing_address_2',
					'_billing_city',
					'_billing_postcode',
					'_billing_country',
					'_billing_state',
					'_billing_email',
					'_billing_phone',
					'_shipping_address_1',
					'_shipping_address_2',
					'_shipping_city',
					'_shipping_postcode',
					'_shipping_country',
					'_shipping_state',
					'_billing_last_name',
					'_billing_first_name',
					'_shipping_first_name',
					'_shipping_last_name',
				) ) );

				$query->set( 'search_fields', $search_fields );
			}
		}
	}
}
add_action( 'pre_get_posts', 'epwc_translate_args', 11, 1 );

/**
 * Fetch the ES related meta mapping for orderby
 *
 * @param  $meta_key The meta key to get the mapping for.
 * @return string    The mapped meta key.
 */
function epwc_get_orderby_meta_mapping( $meta_key ) {
	$mapping = apply_filters( 'epwc_orderby_meta_mapping',
		array(
			'menu_order'         => 'menu_order title date',
			'menu_order title'   => 'menu_order title date',
			'total_sales'        => 'meta.total_sales.long date',
			'_wc_average_rating' => 'meta._wc_average_rating.double date',
			'_price'             => 'meta._price.long date',
		) );

	if ( isset( $mapping[ $meta_key ] ) ) {
		return $mapping[ $meta_key ];
	}

	return 'date';
}

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
 * Fetches all necessary WooCommerce related post statuses
 *
 * @since  1.0
 */
function epwc_get_statuses() {
	$post_statuses = get_post_stati();

	unset( $post_statuses['auto-draft'] );

	return array_values( $post_statuses );
}
add_filter( 'ep_indexable_post_status', 'epwc_get_statuses' );

/**
 * Handle Woo Commerce related formatted args
 *
 * @since  1.0
 * @param  array $formatted_args The formatted WP query arguments
 * @return array
 */
function epwc_formatted_args( $formatted_args, $args ) {

	if ( is_admin() ) {
		if ( isset( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ) {
			$post_status = array( $_GET['post_status'] );
		} else {
			$post_status = epwc_get_statuses();

			// Lets make sure the thrashed posts are not accounted for in the default edit post listing
			$trash_index = array_search( 'trash', $post_status );
			if ( $trash_index ) {
				unset( $post_status[ $trash_index ] );
			}

			$post_status = array_values( $post_status );
		}
	} else {

		// Setting a collection of post status for the front-end display
		$post_status = array(
			'publish',
			'wc-cancelled',
			'wc-completed',
			'wp-failed',
			'wc-on-hold',
			'wc-pending',
			'wc-processing',
			'wc-refunded',
		);

		// Include Custom WooCommerce Order Statuses to this list of front-end display post statuses
		if ( function_exists( 'wc_get_order_statuses' ) ) {

			$woo_order_statuses = wc_get_order_statuses();
			if ( ! empty( $woo_order_statuses ) ) {
				$woo_order_statuses = array_keys( $woo_order_statuses );

				$post_status = array_values( array_unique( array_merge( $post_status, $woo_order_statuses ) ) );
			}
		}

		$post_status = apply_filters( 'epwc_valid_front_end_post_statuses', $post_status, $formatted_args, $args );

		// Narrow down to the post parent for product variations
		if ( 'product_variation' == $args['post_type'] ) {
			if ( isset( $args['post_parent'] ) && $args['post_parent'] ) {
				$formatted_args['filter']['and'][] = array(
					'term' => array( 'post_parent' => $args['post_parent'] ),
				);
			}
		}
	}

	// Add post status detail to the query.
	if ( $post_status ) {
		$formatted_args['filter']['and'][] = array(
			'terms' => array( 'post_status' => $post_status ),
		);
	}

	return $formatted_args;
}
add_filter( 'ep_formatted_args',  'epwc_formatted_args' , 10, 2 );
