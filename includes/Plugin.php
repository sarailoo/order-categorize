<?php
/**
 * Main plugin bootstrap.
 *
 * @package OrderCategorize
 */

declare(strict_types=1);

namespace OrderCategorize;

/**
 * Primary plugin bootstrap class.
 */
class Plugin {
	/**
	 * Shared instance of the plugin bootstrap.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Return an instance of the plugin bootstrap.
	 *
	 * @return Plugin class instance
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() ) {
			return;
		}
	}
}
