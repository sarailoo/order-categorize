<?php
/**
 * Handles persistence and defaults for plugin settings.
 *
 * @package OrderCategorize\Settings
 */

declare(strict_types=1);

namespace OrderCategorize\Settings;

/**
 * Central repository for reading and writing plugin settings.
 */
class SettingsRepository {
	/**
	 * Option key used to persist plugin configuration.
	 */
	public const OPTION_NAME = 'order_categorize_settings';

	/**
	 * Retrieve the plugin settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return self::normalize( $saved );
	}

	/**
	 * Persist plugin settings using the WordPress options API.
	 *
	 * @param array<string,mixed> $settings Sanitized settings.
	 *
	 * @return void
	 */
	public static function update( array $settings ): void {
		update_option(
			self::OPTION_NAME,
			self::normalize( $settings )
		);
	}

	/**
	 * Retrieve the list of configured steps.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function get_hierarchy(): array {
		$settings = self::get();

		return $settings['hierarchy'];
	}

	/**
	 * Retrieve the set of order statuses to include.
	 *
	 * @return array<int,string>
	 */
	public static function get_statuses(): array {
		$settings = self::get();

		return $settings['order_statuses'];
	}

	/**
	 * Retrieve the configured depth of the funnel.
	 *
	 * @return int
	 */
	public static function get_depth(): int {
		$settings = self::get();

		return (int) ( $settings['depth'] ?? count( $settings['hierarchy'] ) );
	}

	/**
	 * Normalize a settings payload by merging with defaults and applying sanitization.
	 *
	 * @param array<string,mixed> $settings Raw settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function normalize( array $settings ): array {
		$settings = wp_parse_args(
			$settings,
			self::get_defaults()
		);

		$normalized                    = array();
		$normalized['hierarchy']       = self::sanitize_hierarchy( $settings['hierarchy'] ?? array() );
		$normalized['order_statuses']  = self::sanitize_statuses( $settings['order_statuses'] ?? array() );
		$normalized['depth']           = max( 1, (int) ( $settings['depth'] ?? count( $normalized['hierarchy'] ) ) );
		$normalized['depth']           = min( $normalized['depth'], count( $normalized['hierarchy'] ) );

		return $normalized;
	}

	/**
	 * Calculate the default configuration.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults(): array {
		return array(
			'depth'          => 3,
			'order_statuses' => array( 'wc-pending', 'wc-processing' ),
			'hierarchy'      => array(
				array(
					'type' => 'product',
				),
				array(
					'type'      => 'attribute',
					'attribute' => 'pa_period',
				),
				array(
					'type' => 'orders',
				),
			),
		);
	}

	/**
	 * Sanitize the hierarchy definition.
	 *
	 * @param mixed $hierarchy Raw hierarchy data.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function sanitize_hierarchy( $hierarchy ): array {
		if ( ! is_array( $hierarchy ) ) {
			$hierarchy = array();
		}

		$sanitized   = array();
		$has_product = false;

		foreach ( $hierarchy as $step ) {
			if ( ! is_array( $step ) || empty( $step['type'] ) ) {
				continue;
			}

			$type = sanitize_key( (string) $step['type'] );

			if ( 'product' === $type ) {
				if ( $has_product ) {
					continue;
				}
				$has_product = true;
				$sanitized[] = array( 'type' => 'product' );
				continue;
			}

			if ( 'attribute' === $type && ! empty( $step['attribute'] ) ) {
				$sanitized[] = array(
					'type'      => 'attribute',
					'attribute' => sanitize_key( (string) $step['attribute'] ),
				);
				continue;
			}

			if ( 'orders' === $type ) {
				$sanitized[] = array( 'type' => 'orders' );
			}
		}

		if ( ! $has_product ) {
			array_unshift(
				$sanitized,
				array(
					'type' => 'product',
				)
			);
		}

		return array_values( $sanitized );
	}

	/**
	 * Sanitize and validate order statuses.
	 *
	 * @param mixed $statuses Raw statuses value.
	 *
	 * @return array<int,string>
	 */
	private static function sanitize_statuses( $statuses ): array {
		if ( ! is_array( $statuses ) ) {
			$statuses = array();
		}

		$valid_statuses = array_keys( wc_get_order_statuses() );

		$clean = array();
		foreach ( $statuses as $status ) {
			$key = sanitize_key( (string) $status );
			if ( ! str_starts_with( $key, 'wc-' ) ) {
				$key = 'wc-' . $key;
			}
			if ( in_array( $key, $valid_statuses, true ) ) {
				$clean[] = $key;
			}
		}

		if ( empty( $clean ) ) {
			$clean = array( 'wc-pending' );
		}

		return array_values( array_unique( $clean ) );
	}
}
