<?php
/**
 * REST controller powering the order hierarchy explorer.
 *
 * @package OrderCategorize\Rest
 */

declare(strict_types=1);

namespace OrderCategorize\Rest;

use OrderCategorize\Orders\OrderHierarchyService;
use WP_REST_Request;
use WP_REST_Response;
/**
 * Registers the REST endpoints used by the admin app.
 */
class OrderHierarchyController {
	/**
	 * Namespace for the routes.
	 */
	private const NAMESPACE = 'order-categorize/v1';

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/hierarchy',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'handle_get_hierarchy' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
					'args'                => array(
						'step' => array(
							'type'              => 'integer',
							'required'          => false,
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'path' => array(
							'description' => 'Selection path describing the current drilldown.',
							'type'        => 'array',
							'required'    => false,
						),
					),
				),
			)
		);
	}

	/**
	 * Handle GET requests for the hierarchy endpoint.
	 *
	 * @param WP_REST_Request $request Incoming request object.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_get_hierarchy( WP_REST_Request $request ) {
		$step = (int) $request->get_param( 'step' );
		if ( $step < 1 ) {
			$step = 1;
		}

		$path_param = $request->get_param( 'path' );
		$path       = self::parse_path( $path_param );

		$service = new OrderHierarchyService();
		$data    = $service->get_step_data( $step, $path );

		return rest_ensure_response( $data );
	}

	/**
	 * Ensure the current user can access the endpoint.
	 *
	 * @return bool
	 */
	public static function permissions_check(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Parse the path parameter from the REST request.
	 *
	 * @param mixed $raw Raw path parameter.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function parse_path( $raw ): array {
		if ( null === $raw || '' === $raw ) {
			return array();
		}

		if ( is_string( $raw ) ) {
			$raw = array( $raw );
		}

		if ( ! is_array( $raw ) ) {
			return array();
		}

		$allowed_keys = array_flip( array( 'type', 'id', 'attribute', 'value' ) );
		$path         = array();

		foreach ( $raw as $entry ) {
			if ( is_string( $entry ) ) {
				$path[] = self::parse_path_token( $entry );
				continue;
			}

			if ( is_array( $entry ) ) {
				$path[] = array_intersect_key( $entry, $allowed_keys );
			}
		}

		return array_values(
			array_filter(
				$path,
				static fn( $item ) => ! empty( $item['type'] ?? null )
			)
		);
	}

	/**
	 * Parse a single tokenized path entry.
	 *
	 * @param string $token Token in the form type:value[:value].
	 *
	 * @return array<string,string>
	 */
	private static function parse_path_token( string $token ): array {
		$parts = explode( ':', $token, 3 );
		$type  = sanitize_key( $parts[0] ?? '' );

		if ( 'product' === $type && isset( $parts[1] ) ) {
			return array(
				'type' => 'product',
				'id'   => (string) absint( $parts[1] ),
			);
		}

		if ( 'attribute' === $type && isset( $parts[1], $parts[2] ) ) {
			return array(
				'type'      => 'attribute',
				'attribute' => sanitize_key( $parts[1] ),
				'value'     => rawurldecode( (string) $parts[2] ),
			);
		}

		return array();
	}
}
