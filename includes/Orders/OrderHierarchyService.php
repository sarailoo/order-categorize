<?php
/**
 * Aggregates WooCommerce order data to drive the hierarchy explorer.
 *
 * @package OrderCategorize\Orders
 */

declare(strict_types=1);

namespace OrderCategorize\Orders;

use OrderCategorize\Settings\SettingsRepository;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
/**
 * Provides aggregated order counts for each step in the explorer.
 */
class OrderHierarchyService {
	/**
	 * Retrieve the data required for a given step and selection path.
	 *
	 * @param int                               $step Step number (1-indexed).
	 * @param array<int,array<string,string>>   $path Selection path.
	 *
	 * @return array<string,mixed>
	 */
	public function get_step_data( int $step, array $path ): array {
		$settings  = SettingsRepository::get();
		$hierarchy = $settings['hierarchy'];
		$depth     = (int) $settings['depth'];

		$step = max( 1, min( $step, $depth ) );

		$current_step = $hierarchy[ $step - 1 ] ?? array( 'type' => 'product' );
		$next_step    = $hierarchy[ $step ] ?? null;
		$statuses     = $settings['order_statuses'];

		$normalized_path = $this->normalize_path( $path, $hierarchy );
		$breadcrumbs     = $this->build_breadcrumbs( $normalized_path );

		if ( 'orders' === ( $current_step['type'] ?? '' ) || null === $next_step ) {
			$orders = $this->collect_orders( $normalized_path, $statuses );

			return array(
				'step'             => $step,
				'depth'            => $depth,
				'step_type'        => 'orders',
				'breadcrumbs'      => $breadcrumbs,
				'items'            => array(),
				'orders'           => $this->format_orders( $orders ),
				'orders_admin_url' => $this->build_orders_admin_url( $normalized_path ),
				'terminal'         => true,
			);
		}

		if ( 'product' === ( $current_step['type'] ?? '' ) ) {
			$orders = $this->collect_orders( array(), $statuses );

			return array(
				'step'             => $step,
				'depth'            => $depth,
				'step_type'        => 'product',
				'next_step_type'   => $next_step['type'] ?? null,
				'breadcrumbs'      => $breadcrumbs,
				'items'            => $this->aggregate_products( $orders ),
				'terminal'         => false,
			);
		}

		if ( 'attribute' === ( $current_step['type'] ?? '' ) ) {
			$product_id = $this->extract_product_id( $normalized_path );

			$attribute = (string) ( $current_step['attribute'] ?? '' );
			$orders    = $this->collect_orders( $normalized_path, $statuses );
			$items     = $this->aggregate_attribute( $orders, $attribute, $product_id );

			return array(
				'step'             => $step,
				'depth'            => $depth,
				'step_type'        => 'attribute',
				'attribute'        => $attribute,
				'next_step_type'   => $next_step['type'] ?? null,
				'breadcrumbs'      => $breadcrumbs,
				'items'            => $items,
				'terminal'         => empty( $items ) && ( ! $next_step || 'orders' === $next_step['type'] ),
			);
		}

		return array(
			'step'             => $step,
			'depth'            => $depth,
			'step_type'        => $current_step['type'] ?? 'product',
			'breadcrumbs'      => $breadcrumbs,
			'items'            => array(),
			'terminal'         => false,
		);
	}

	/**
	 * Convert the selection path into a canonical structure.
	 *
	 * @param array<int,array<string,string>> $path      Raw path.
	 * @param array<int,array<string,string>> $hierarchy Configured hierarchy.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function normalize_path( array $path, array $hierarchy ): array {
		$normalized = array();

		foreach ( $path as $index => $selection ) {
			if ( ! is_array( $selection ) ) {
				continue;
			}

			$type = $selection['type'] ?? ( $hierarchy[ $index ]['type'] ?? '' );

			if ( 'product' === $type && ! empty( $selection['id'] ) ) {
				$normalized[] = array(
					'type' => 'product',
					'id'   => (string) absint( $selection['id'] ),
				);
				continue;
			}

			if ( 'attribute' === $type && ! empty( $selection['attribute'] ) && array_key_exists( 'value', $selection ) ) {
				$normalized[] = array(
					'type'      => 'attribute',
					'attribute' => (string) $selection['attribute'],
					'value'     => (string) $selection['value'],
				);
			}
		}

		return $normalized;
	}

	/**
	 * Build breadcrumb entries for the UI.
	 *
	 * @param array<int,array<string,string>> $path Normalized path.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function build_breadcrumbs( array $path ): array {
		$breadcrumbs = array();

		foreach ( $path as $selection ) {
			if ( 'product' === ( $selection['type'] ?? '' ) ) {
				$product = wc_get_product( (int) $selection['id'] );
				if ( ! $product ) {
					continue;
				}
				$breadcrumbs[] = array(
					'type'  => 'product',
					'id'    => (string) $product->get_id(),
					'label' => $product->get_name(),
				);
				continue;
			}

			if ( 'attribute' === ( $selection['type'] ?? '' ) ) {
				$breadcrumbs[] = array(
					'type'      => 'attribute',
					'attribute' => $selection['attribute'],
					'value'     => $selection['value'],
					'label'     => $this->get_attribute_value_label( $selection['attribute'], $selection['value'] ),
				);
			}
		}

		return $breadcrumbs;
	}

	/**
	 * Aggregate orders by product.
	 *
	 * @param array<int,WC_Order> $orders Orders to evaluate.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function aggregate_products( array $orders ): array {
		$stats = array();

		foreach ( $orders as $order ) {
			$item_products = array();

			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				$product_id = $item->get_product_id();
				if ( $product_id ) {
					$item_products[ $product_id ] = true;
				}
			}

			foreach ( array_keys( $item_products ) as $product_id ) {
				if ( ! isset( $stats[ $product_id ] ) ) {
					$stats[ $product_id ] = array(
						'count'    => 0,
						'orderIds' => array(),
					);
				}

				if ( ! in_array( $order->get_id(), $stats[ $product_id ]['orderIds'], true ) ) {
					$stats[ $product_id ]['count']++;
					$stats[ $product_id ]['orderIds'][] = $order->get_id();
				}
			}
		}

		$items = array();
		foreach ( $stats as $product_id => $data ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				continue;
			}

			$items[] = array(
				'id'        => $product->get_id(),
				'type'      => 'product',
				'label'     => $product->get_name(),
				'thumbnail' => $this->get_product_thumbnail( $product ),
				'count'     => (int) $data['count'],
			);
		}

		usort(
			$items,
			static function ( array $left, array $right ): int {
				return $right['count'] <=> $left['count'];
			}
		);

		return $items;
	}

	/**
	 * Aggregate orders for a specific attribute.
	 *
	 * @param array<int,WC_Order> $orders     Orders under consideration.
	 * @param string              $attribute  Attribute slug (e.g., pa_period).
	 * @param int|null            $product_id Selected product ID.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function aggregate_attribute( array $orders, string $attribute, ?int $product_id = null ): array {
		if ( '' === $attribute ) {
			return array();
		}

		$meta_key = 'attribute_' . $attribute;

		$stats = array();
		foreach ( $orders as $order ) {
			$values_for_order = array();

			foreach ( $order->get_items( 'line_item' ) as $item ) {
				if ( ! $item instanceof WC_Order_Item_Product ) {
					continue;
				}

				if ( $product_id && (int) $item->get_product_id() !== $product_id ) {
					continue;
				}

				$value = $item->get_meta( $meta_key, true );
				if ( ! $value && $item->get_variation_id() ) {
					$variation = wc_get_product( $item->get_variation_id() );
					if ( $variation ) {
						$value = $variation->get_attribute( $attribute );
					}
				}

				if ( '' === $value || null === $value ) {
					continue;
				}

				$values_for_order[ (string) $value ] = true;
			}

			foreach ( array_keys( $values_for_order ) as $value ) {
				if ( ! isset( $stats[ $value ] ) ) {
					$stats[ $value ] = array(
						'count'    => 0,
						'orderIds' => array(),
					);
				}

				if ( ! in_array( $order->get_id(), $stats[ $value ]['orderIds'], true ) ) {
					$stats[ $value ]['count']++;
					$stats[ $value ]['orderIds'][] = $order->get_id();
				}
			}
		}

		$items = array();
		foreach ( $stats as $value => $data ) {
			$items[] = array(
				'id'        => $value,
				'type'      => 'attribute',
				'attribute' => $attribute,
				'label'     => $this->get_attribute_value_label( $attribute, $value ),
				'count'     => (int) $data['count'],
			);
		}

		usort(
			$items,
			static function ( array $left, array $right ): int {
				return $right['count'] <=> $left['count'];
			}
		);

		return $items;
	}

	/**
	 * Collect orders that match the provided selection path.
	 *
	 * @param array<int,array<string,string>> $path     Selection path.
	 * @param array<int,string>               $statuses Order statuses to include.
	 *
	 * @return array<int,WC_Order>
	 */
	private function collect_orders( array $path, array $statuses ): array {
		$args = array(
			'status' => $statuses,
			'limit'  => -1,
			'return' => 'ids',
		);

		$product_id = $this->extract_product_id( $path );
		if ( $product_id ) {
			$args['product_id'] = $product_id;
		}

		$order_ids = wc_get_orders( $args );
		$orders    = array();

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$orders[] = $order;
			}
		}

		$attribute_filters = $this->extract_attribute_filters( $path );
		if ( empty( $attribute_filters ) ) {
			return $orders;
		}

		return $this->filter_orders_by_attributes( $orders, $product_id, $attribute_filters );
	}

	/**
	 * Filter orders by product attribute selections.
	 *
	 * @param array<int,WC_Order>            $orders            Orders to filter.
	 * @param int|null                       $product_id        Selected product ID.
	 * @param array<string,string>           $attribute_filters Attribute filters keyed by slug.
	 *
	 * @return array<int,WC_Order>
	 */
	private function filter_orders_by_attributes( array $orders, ?int $product_id, array $attribute_filters ): array {
		$filtered = array();

		foreach ( $orders as $order ) {
			if ( $this->order_matches_attributes( $order, $product_id, $attribute_filters ) ) {
				$filtered[] = $order;
			}
		}

		return $filtered;
	}

	/**
	 * Determine if an order matches the selected attribute filters.
	 *
	 * @param WC_Order              $order             Order instance.
	 * @param int|null              $product_id        Product ID constraint.
	 * @param array<string,string>  $attribute_filters Attribute => value map.
	 *
	 * @return bool
	 */
	private function order_matches_attributes( WC_Order $order, ?int $product_id, array $attribute_filters ): bool {
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			if ( $product_id && (int) $item->get_product_id() !== $product_id ) {
				continue;
			}

			$matched_all = true;
			foreach ( $attribute_filters as $attribute => $value ) {
				$current_value = $this->get_item_attribute_value( $item, $attribute );
				if ( $current_value !== $value ) {
					$matched_all = false;
					break;
				}
			}

			if ( $matched_all ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract the product ID from the selection path.
	 *
	 * @param array<int,array<string,string>> $path Selection path.
	 *
	 * @return int|null
	 */
	private function extract_product_id( array $path ): ?int {
		foreach ( $path as $selection ) {
			if ( 'product' === ( $selection['type'] ?? '' ) ) {
				return (int) $selection['id'];
			}
		}
		return null;
	}

	/**
	 * Extract attribute filters from the selection path.
	 *
	 * @param array<int,array<string,string>> $path Selection path.
	 *
	 * @return array<string,string>
	 */
	private function extract_attribute_filters( array $path ): array {
		$filters = array();
		foreach ( $path as $selection ) {
			if ( 'attribute' === ( $selection['type'] ?? '' ) && isset( $selection['attribute'], $selection['value'] ) ) {
				$filters[ $selection['attribute'] ] = $selection['value'];
			}
		}

		return $filters;
	}

	/**
	 * Retrieve an attribute value for an order item.
	 *
	 * @param WC_Order_Item_Product $item      Line item.
	 * @param string                $attribute Attribute slug.
	 *
	 * @return string|null
	 */
	private function get_item_attribute_value( WC_Order_Item_Product $item, string $attribute ): ?string {
		$meta_key = 'attribute_' . $attribute;

		$value = $item->get_meta( $meta_key, true );
		if ( '' !== $value && null !== $value ) {
			return (string) $value;
		}

		if ( $item->get_variation_id() ) {
			$variation = wc_get_product( $item->get_variation_id() );
			if ( $variation ) {
				$value = $variation->get_attribute( $attribute );
				if ( '' !== $value && null !== $value ) {
					return (string) $value;
				}
			}
		}

		return null;
	}

	/**
	 * Produce a label for an attribute value.
	 *
	 * @param string $attribute Attribute slug.
	 * @param string $value     Attribute value slug.
	 *
	 * @return string
	 */
	private function get_attribute_value_label( string $attribute, string $value ): string {
		$taxonomy = $attribute;

		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $term->name;
			}
		}

		return strtoupper( (string) $value );
	}

	/**
	 * Format product thumbnail URL.
	 *
	 * @param WC_Product $product Product instance.
	 *
	 * @return string|null
	 */
	private function get_product_thumbnail( WC_Product $product ): ?string {
		$image_id = $product->get_image_id();
		if ( ! $image_id ) {
			return null;
		}

		$url = wp_get_attachment_image_url( $image_id, 'medium' );

		return $url ?: null;
	}

	/**
	 * Prepare order objects for JSON serialization.
	 *
	 * @param array<int,WC_Order> $orders Orders to format.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function format_orders( array $orders ): array {
		$formatted = array();

		foreach ( $orders as $order ) {
			$status = $order->get_status();
			$date   = $order->get_date_created();

			$formatted[] = array(
				'id'        => $order->get_id(),
				'number'    => $order->get_order_number(),
				'status'    => array(
					'key'   => 'wc-' . $status,
					'label' => wc_get_order_status_name( 'wc-' . $status ),
				),
				'total'     => $order->get_formatted_order_total(),
				'customer'  => $order->get_formatted_billing_full_name() ?: $order->get_billing_email(),
				'date'      => $date ? $date->date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) : '',
				'edit_url'  => $order->get_edit_order_url(),
			);
		}

		return $formatted;
	}

	/**
	 * Build the admin URL to view filtered orders.
	 *
	 * @param array<int,array<string,string>> $path Selection path.
	 *
	 * @return string
	 */
	private function build_orders_admin_url( array $path ): string {
		$args = array(
			'post_type' => 'shop_order',
		);

		$product_id = $this->extract_product_id( $path );
		if ( $product_id ) {
			$args['orcz_product'] = $product_id;
		}

		foreach ( $this->extract_attribute_filters( $path ) as $attribute => $value ) {
			$args[ 'orcz_attr_' . $attribute ] = $value;
		}

		return add_query_arg( $args, admin_url( 'edit.php' ) );
	}
}
