<?php
/**
 * Integrates drilldown selections with the native WooCommerce order list.
 *
 * @package OrderCategorize\Admin\Orders
 */

declare(strict_types=1);

namespace OrderCategorize\Admin\Orders;

use OrderCategorize\Orders\OrderHierarchyService;
/**
 * Applies custom filters to the WooCommerce order list table.
 */
class ListTableFilters {
	/**
	 * Boot the filters.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( __CLASS__, 'filter_query_args' ) );
	}

	/**
	 * Adjust the query arguments based on selection context.
	 *
	 * @param array<string,mixed> $query_args Query arguments to be passed to wc_get_orders.
	 *
	 * @return array<string,mixed>
	 */
	public static function filter_query_args( array $query_args ): array {
		if ( ! is_admin() ) {
			return $query_args;
		}

		$path = self::build_path_from_request();
		if ( empty( $path ) ) {
			return $query_args;
		}

		$service = new OrderHierarchyService();
		$data    = $service->get_step_data( PHP_INT_MAX, $path );
		$orders  = $data['orders'] ?? array();

		if ( empty( $orders ) ) {
			$query_args['include'] = array( 0 );
			return $query_args;
		}

		$order_ids = array_map( 'intval', wp_list_pluck( $orders, 'id' ) );
		$query_args['include'] = $order_ids;

		return $query_args;
	}

	/**
	 * Build a selection path based on the current admin request.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function build_path_from_request(): array {
		$path = array();

		if ( isset( $_GET['orcz_product'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$product = absint( wp_unslash( $_GET['orcz_product'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( $product > 0 ) {
				$path[] = array(
					'type' => 'product',
					'id'   => (string) $product,
				);
			}
		}

		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! is_string( $key ) || ! str_starts_with( $key, 'orcz_attr_' ) ) {
				continue;
			}

			$attribute = sanitize_key( substr( $key, strlen( 'orcz_attr_' ) ) );
			if ( '' === $attribute ) {
				continue;
			}

			$raw_value = is_array( $value ) ? reset( $value ) : $value;
			$raw_value = sanitize_text_field( wp_unslash( (string) $raw_value ) );

			if ( '' === $raw_value ) {
				continue;
			}

			$path[] = array(
				'type'      => 'attribute',
				'attribute' => $attribute,
				'value'     => $raw_value,
			);
		}

		return $path;
	}
}
